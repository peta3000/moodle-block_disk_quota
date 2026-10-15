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

class backup_categories_test extends advanced_testcase {
    public function test_backup_sources_are_mutually_exclusive(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($course->id);
        $usercontext = \context_user::instance($user->id);
        $common = array('itemid' => 0, 'filepath' => '/', 'userid' => $user->id,
            'mimetype' => 'application/vnd.moodle.backup');
        foreach (array(
            array('user', 'backup', $usercontext->id, 'user'),
            array('backup', 'course', $coursecontext->id, 'course'),
            array('backup', 'automated', $coursecontext->id, 'automated'),
            array('backup', 'section', $coursecontext->id, 'other'),
        ) as $source) {
            get_file_storage()->create_file_from_string($common + array('component' => $source[0],
                'filearea' => $source[1], 'contextid' => $source[2], 'filename' => $source[3] . '.mbz'), $source[3]);
        }
        $directory = make_request_directory();
        file_put_contents($directory . '/external.mbz', 'archive');
        set_config('backup_auto_destination', $directory, 'backup');
        $ids = array();
        foreach (array('user', 'course', 'automated', 'other', 'directory') as $category) {
            $inventory = new internal_backup_usage($category);
            $this->assertSame(1, $inventory->count());
            $row = $inventory->get_backup_details()[0];
            $this->assertSame($category, $row->category);
            $ids[] = $row->id;
        }
        $this->assertCount(5, array_unique($ids));
        $row = (new internal_backup_usage('user'))->get_backup_details()[0];
        $this->assertEquals($user->id, $row->user_id);
        $this->assertEmpty($row->course_id);
        $this->assertSame(5, (new internal_backup_usage())->count());
        // File-area views must not depend on access to the directory source.
        set_config('backup_auto_destination', $directory . '/missing', 'backup');
        $this->assertSame(1, (new internal_backup_usage('course'))->count());
        $this->expectException(\moodle_exception::class);
        (new internal_backup_usage('directory'))->count();
    }
}
