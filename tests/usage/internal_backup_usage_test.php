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
use block_disk_quota\usage\internal_backup_usage;

defined('MOODLE_INTERNAL') || die();

/** Regression coverage for the combined backup inventory and pagination. */
class internal_backup_usage_test extends advanced_testcase {
    public function test_combines_file_areas_and_directory_with_matching_pagination(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        // The admin user ID can coincide with a course ID; it must never create a false course link.
        $usercontext = \context_user::instance(2);
        $fs = get_file_storage();
        $common = array('component' => 'backup', 'filearea' => 'course', 'itemid' => 0,
            'filepath' => '/', 'mimetype' => 'application/vnd.moodle.backup');
        $coursefile = $fs->create_file_from_string($common + array('contextid' => $coursecontext->id,
            'filename' => 'course.mbz'), str_repeat('a', 100));
        $userfile = $fs->create_file_from_string($common + array('contextid' => $usercontext->id,
            'filename' => 'user.mbz'), str_repeat('b', 80));
        $root = make_request_directory();
        mkdir($root . '/nested');
        file_put_contents($root . '/external.mbz', str_repeat('c', 200));
        file_put_contents($root . '/nested/external.MBZ', str_repeat('d', 50));
        file_put_contents($root . '/unrelated.zip', 'ignore');
        file_put_contents($root . '/empty.mbz', '');
        set_config('backup_auto_destination', $root, 'backup');
        $inventory = new internal_backup_usage();
        $this->assertSame(4, $inventory->count());
        $all = $inventory->get_backup_details();
        $this->assertSame(array(200, 100, 80, 50), array_map(function($file) {
            return (int)$file->filesize;
        }, $all));
        $byid = array();
        foreach ($all as $record) {
            $byid[$record->id] = $record;
        }
        $this->assertEquals($course->id, $byid['file-' . $coursefile->get_id()]->course_id);
        $this->assertEmpty($byid['file-' . $userfile->get_id()]->course_id);
        $this->assertSame('nested/external.MBZ', $all[3]->location);
        $first = $inventory->get_backup_paginated(0, 3);
        $last = $inventory->get_backup_paginated(3, 3);
        $stale = $inventory->get_backup_paginated(90, 3);
        $this->assertSame(4, $first->total);
        $this->assertCount(3, $first->data);
        $this->assertTrue($first->hasnext);
        $this->assertCount(1, $last->data);
        $this->assertFalse($last->hasnext);
        $this->assertSame(3, $stale->limitfrom);
        $this->assertSame($last->data, $stale->data);
        $this->assertCount(4, array_unique(array_map(function($record) {
            return $record->id;
        }, array_merge($first->data, $last->data))));
    }

    public function test_no_directory_and_missing_directory(): void {
        $this->resetAfterTest();
        set_config('backup_auto_destination', '', 'backup');
        $inventory = new internal_backup_usage();
        $empty = $inventory->get_backup_paginated(30, 15);
        $this->assertSame(0, $empty->total);
        $this->assertSame(0, $empty->limitfrom);
        $this->assertSame(array(), $empty->data);
        set_config('backup_auto_destination', make_request_directory() . '/missing', 'backup');
        $this->expectException(\moodle_exception::class);
        (new internal_backup_usage())->count();
    }

    public function test_symlink_loops_are_excluded_and_order_is_stable(): void {
        $this->resetAfterTest();
        $root = make_request_directory();
        file_put_contents($root . '/a.mbz', 'backup');
        file_put_contents($root . '/b.mbz', 'backup');
        touch($root . '/a.mbz', 1700000000);
        touch($root . '/b.mbz', 1700000000);
        if (!function_exists('symlink') || !@symlink($root, $root . '/loop')) {
            $this->markTestSkipped('Symbolic links are unavailable.');
        }
        symlink($root . '/a.mbz', $root . '/linked.mbz');
        set_config('backup_auto_destination', $root, 'backup');
        $first = (new internal_backup_usage())->get_backup_details();
        $second = (new internal_backup_usage())->get_backup_details();
        $this->assertCount(2, $first);
        $this->assertEquals($first, $second);
    }
}
