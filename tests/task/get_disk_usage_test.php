<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Unit tests for blocks/disk_quota/classes/task/get_disk_usage.php
 */

namespace block_disk_quota\tests\tasks;

use advanced_testcase;
use block_disk_quota\task\get_disk_usage;

defined('MOODLE_INTERNAL') || die();

class get_disk_usage_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        set_config('last_measurement_reduction', "1997.01.01", 'block_disk_quota');
        $this->resetAfterTest();
    }

    public function test_last_measurement_reduction() {
        $t = new get_disk_usage();
        $t->execute();

        $date = new \DateTime();
        $today = $date->format("Y.m.d");
        $this->assertEquals($today, get_config("block_disk_quota", "last_measurement_reduction"));
    }
}
