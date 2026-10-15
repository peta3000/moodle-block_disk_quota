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
use block_disk_quota\usage\dataroot_space_usage;
use block_disk_quota\usage\quota_manager;

defined('MOODLE_INTERNAL') || die();

/** Tests for additional dataroot usage and saved measurements. */
class dataroot_space_usage_test extends advanced_testcase {
    /** Create a small isolated dataroot tree. */
    private function fixture() {
        $root = make_request_directory();
        foreach (array('filedir', 'kurssicherung', 'cache', 'kurssicherung/nested') as $directory) {
            mkdir($root . '/' . $directory);
        }
        file_put_contents($root . '/filedir/excluded', str_repeat('x', 1000));
        file_put_contents($root . '/kurssicherung/backup.mbz', str_repeat('x', 100));
        file_put_contents($root . '/kurssicherung/nested/backup.mbz', str_repeat('x', 50));
        file_put_contents($root . '/cache/item', str_repeat('x', 20));
        file_put_contents($root . '/root-file', str_repeat('x', 10));
        return $root;
    }

    public function test_scan_excludes_filedir_and_groups_other_entries(): void {
        global $CFG;
        $this->resetAfterTest();
        $original = $CFG->dataroot;
        $root = $this->fixture();
        try {
            $CFG->dataroot = $root;
            $collector = new dataroot_space_usage();
            $details = $collector->refresh();
            $this->assertEqualsWithDelta(180 / 1000000000, $details['total_gb'], 1e-15);
            $this->assertEqualsWithDelta(150 / 1000000000,
                $details['breakdown']['moodledata/kurssicherung'], 1e-15);
            $this->assertEqualsWithDelta(20 / 1000000000,
                $details['breakdown']['moodledata/cache'], 1e-15);
            $this->assertEqualsWithDelta(10 / 1000000000,
                $details['breakdown']['moodledata (root files)'], 1e-15);
            $this->assertArrayNotHasKey('moodledata/filedir', $details['breakdown']);
        } finally {
            $CFG->dataroot = $original;
        }
    }

    public function test_symbolic_links_are_not_followed(): void {
        global $CFG;
        $this->resetAfterTest();
        $original = $CFG->dataroot;
        $root = $this->fixture();
        if (!function_exists('symlink') || !@symlink($root, $root . '/kurssicherung/loop')) {
            $this->markTestSkipped('Symbolic links are unavailable.');
        }
        symlink($root . '/filedir', $root . '/linked-filedir');
        symlink($root . '/root-file', $root . '/cache/linked-file');
        try {
            $CFG->dataroot = $root;
            $collector = new dataroot_space_usage();
            $details = $collector->refresh();
            $this->assertEqualsWithDelta(180 / 1000000000, $details['total_gb'], 1e-15);
            $this->assertArrayNotHasKey('moodledata/linked-filedir', $details['breakdown']);
        } finally {
            $CFG->dataroot = $original;
        }
    }

    public function test_measurement_saves_directory_breakdown(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $original = $CFG->dataroot;
        $root = $this->fixture();
        try {
            $CFG->dataroot = $root;
            $manager = new quota_manager();
            $used = $manager->get_total_disk_space_used();
            $manager->record_space_used($used, 800);
            $this->assertEqualsWithDelta($used,
                (new quota_manager())->get_usage_details()['total_gb'], 1e-12);
            $this->assertEquals(1, $DB->count_records('block_disk_quota_measurement'));
            // A page view uses the saved scan rather than scanning the filesystem again.
            file_put_contents($root . '/kurssicherung/backup.mbz', str_repeat('x', 200));
            $saved = (new dataroot_space_usage())->get_usage_details();
            $this->assertEqualsWithDelta(180 / 1000000000, $saved['total_gb'], 1e-15);
        } finally {
            $CFG->dataroot = $original;
        }
    }

    public function test_missing_dataroot_aborts_measurement(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $original = $CFG->dataroot;
        $root = $this->fixture();
        try {
            $CFG->dataroot = $root . '/missing';
            try {
                $manager = new quota_manager();
                $used = $manager->get_total_disk_space_used();
                $manager->record_space_used($used, 800);
                $this->fail('An inaccessible dataroot must abort the measurement.');
            } catch (\UnexpectedValueException $exception) {
                $this->assertEquals(0, $DB->count_records('block_disk_quota_measurement'));
                $this->assertFalse(get_config('block_disk_quota', 'dataroot_usage_details'));
            }
        } finally {
            $CFG->dataroot = $original;
        }
    }
}
