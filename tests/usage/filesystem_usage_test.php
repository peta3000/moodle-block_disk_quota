<?php
// This file is part of the blocks/disk_quota Moodle plugin
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_disk_quota\tests\usage;

use advanced_testcase;
use block_disk_quota\usage\filesystem_usage;

defined('MOODLE_INTERNAL') || die();

/** Actual allocated-byte tests, intended for a native Linux Moodle PHPUnit run. */
class filesystem_usage_test extends advanced_testcase {
    public function test_filedir_is_included_and_breakdown_sums_to_total(): void {
        $root = make_request_directory();
        mkdir($root . '/filedir');
        file_put_contents($root . '/filedir/blob', str_repeat('x', 10000));
        file_put_contents($root . '/root-file', 'root');
        $scan = (new filesystem_usage())->measure($root);
        $this->assertArrayHasKey('filedir', $scan['breakdown']);
        $this->assertGreaterThan(0, $scan['breakdown']['filedir']);
        $this->assertSame(array_sum($scan['breakdown']), $scan['bytes']);
    }

    public function test_hard_links_and_symlink_targets_are_not_double_counted(): void {
        $root = make_request_directory();
        file_put_contents($root . '/original', str_repeat('x', 8192));
        if (!@link($root . '/original', $root . '/hardlink')) {
            $this->markTestSkipped('Hard links unavailable.');
        }
        if (!@symlink($root, $root . '/loop')) {
            $this->markTestSkipped('Symbolic links unavailable.');
        }
        $expected = 0;
        foreach (array($root, $root . '/original', $root . '/loop') as $path) {
            $expected += lstat($path)['blocks'] * 512;
        }
        $this->assertSame($expected, (new filesystem_usage())->measure($root)['bytes']);
    }

    public function test_nested_roots_are_excluded_from_parent(): void {
        $root = make_request_directory();
        mkdir($root . '/data');
        file_put_contents($root . '/data/blob', 'content');
        $scanner = new filesystem_usage();
        $data = $scanner->measure($root . '/data');
        $code = $scanner->measure($root, array($root . '/data'));
        $whole = (new filesystem_usage())->measure($root);
        $this->assertSame($whole['bytes'], $code['bytes'] + $data['bytes']);
        $this->assertArrayNotHasKey('data', $code['breakdown']);
    }
}
