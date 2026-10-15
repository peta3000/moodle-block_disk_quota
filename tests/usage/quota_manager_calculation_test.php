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
 * Unit tests for blocks/disk_quota/classes/usage/quota_manager.php.
 * This file try to test the quota calculation and maintenance mode only.
 */

namespace block_disk_quota\tests\usage;

use advanced_testcase;
use block_disk_quota\usage\quota_manager;

defined('MOODLE_INTERNAL') || die();

class quota_manager_calculation_test extends advanced_testcase {
    protected function setUp() {
        set_config('last_measurement_reduction', "1997.01.01", 'block_disk_quota');
        $this->resetAfterTest();
    }

    /**
     * Test the total disk space used calculations.
     */
    public function test_get_total_disk_space_used() {
        global $CFG;
        $data = $CFG->dataroot;
        $code = $CFG->root ?? $CFG->dirroot;
        $fixture = make_request_directory();
        mkdir($fixture . '/data');
        mkdir($fixture . '/code');
        try {
            $CFG->dataroot = $fixture . '/data';
            $CFG->root = $fixture . '/code';
            file_put_contents($CFG->dataroot . '/test', 'physical content');
            $manager = new quota_manager();
            $used = $manager->get_total_disk_space_used();
            $manager->record_space_used($used, 800);
            $snapshot = \block_disk_quota\usage\storage_usage::saved();
            $this->assertSame($snapshot['database']['bytes'] + $snapshot['moodledata']['bytes'] +
                $snapshot['code']['bytes'], $snapshot['total_bytes']);
            $this->assertEqualsWithDelta($used, $snapshot['total_bytes'] / 1000000000, 1e-12);
        } finally {
            $CFG->dataroot = $data;
            $CFG->root = $code;
        }
    }

    /**
     * Test the get_usage_details calculations with an empty setup.
     */
    public function test_get_usage_details_empty() {

        $quotamanager = new quota_manager();
        $usage = $quotamanager->get_usage_details();

        $expected = [
                "unknown" => 0,
                "application" => 0,
                "text" => 0,
                "audio" => 0,
                "document" => 0,
                "image" => $this->get_current_size_for_mime("image/%"), // By default, Moodle only setup 4 images.
                "video" => 0,
                "backup" => 0
        ];

        $this->validate_usage($expected, $usage);
    }

    /**
     * Test the current quota calculations for backups.
     */
    public function test_get_usage_details_provider() {
        return [
                ["application/vnd.moodle.backup", "backup", rand(1, 8)],
                ["application/json", "application", rand(1, 8)],
                ["video/mp4", "video", rand(1, 8)],
                ["text/html", "text", rand(1, 8)],
                ["audio/mp3", "audio", rand(1, 8)],
                ["document/markdown", "document", rand(1, 8)]
        ];
    }

    /**
     * Test the current quota calculations for video.
     *
     * @dataProvider test_get_usage_details_provider
     */
    public function test_get_usage_details_generic($mime, $key, $size) {
        $this->assert_usage_details_type($mime, $key, $size);
    }

    /**
     * Test the current quota calculations for images.
     */
    public function test_get_usage_details_image() {
        $image = $this->get_current_size_for_mime("image/%");
        $this->assert_usage_details_type("image/jpeg", "image", 2, $image + 2);
    }

    /**
     * Compare float safely (as 2 sames values may have a different precision).
     *
     * @param float $expected
     * @param float $result
     * @param string||null $message Message on failure.
     */
    protected function comparefloat($expected, $result, $message = null) {

        if ($message === null) {
            $message = "Invalid float comparaison. Expected $expected, got $result";
        }

        // Pre PHP 7.2 hack. Obfuscate the constant to avoid code checker issues.
        if (!defined("PHP_" . "FLOAT_EPSILON")) {
            $epsilon = 2.2204460492503e-16;
        } else {
            $epsilon = constant("PHP_" . "FLOAT_EPSILON");
        }

        // Compare float according to PHP recommendations (https://stackoverflow.com/a/3149007).
        $this->assertTrue(abs($expected - $result) < $epsilon, $message);
    }

    /**
     * Insert a file in the database.
     *
     * @param int $filesize
     * @param string $mimetype
     * @return bool|int
     */
    protected function insert_record_file($filesize = 10000, $mimetype = "image/jpeg") {
        global $DB;
        $file = (object) [
                "contenthash" => sha1(date("U") . "1" . rand(0, 99)),
                "pathnamehash" => sha1(date("U") . "2" . rand(0, 99) . $mimetype),
                "contextid" => 1,
                "component" => "core",
                "filearea" => "test",
                "itemid" => 0,
                "filepath" => "/",
                "filename" => "test-" . date("U") . ".jpg",
                "userid" => 1,
                "filesize" => $filesize,
                "mimetype" => $mimetype,
                "status" => 0,
                "source" => null,
                "author" => null,
                "license" => null,
                "timecreated" => date("U"),
                "timemodified" => date("U"),
                "sortorder" => 0,
                "referencefileid" => null
        ];

        return $DB->insert_record("files", $file);
    }

    /**
     * Get the filesite for a specific mimetype using an SQL Like statement.
     *
     * @param string $mimetype
     * @return float|int
     */
    private function get_current_size_for_mime($mimetype = 'image/%') {
        global $DB;
        $used = $DB->get_records_sql("SELECT sum(filesize) FROM {files} WHERE mimetype like ?", [$mimetype]);
        $sum = array_shift($used)->sum;
        return $sum / (1000 * 1000 * 1000);
    }

    /**
     * Validate the get_usage_details result according to the specificed array.
     * Compare only the required values.
     *
     * @param array $expected Expected result
     * @param array $usage Current result
     */
    private function validate_usage(array $expected, array $usage) {
        $this->assertArrayHasKey("total_gb", $usage);
        $this->assertArrayHasKey("breakdown", $usage);
        $breakdown = $usage["breakdown"];
        foreach ($expected as $type => $expectedvalue) {
            $this->assertArrayHasKey($type, $breakdown, "missing breakdown for $type");
            $this->comparefloat($expectedvalue, $breakdown[$type],
                    "Invalid value for $type. Expected: $expectedvalue, got " . $breakdown[$type]);
        }
    }

    /**
     * Assert that the calculation is working after adding a file. The file is not kept.
     *
     * @param string $mimetype Mimetype
     * @param string $key The result key that will be checked.
     * @param int $filesize Size of the file to insert (in GB)
     * @param int|null $expectedsize If null, takes $filesize instead.
     * @throws \dml_exception
     */
    protected function assert_usage_details_type($mimetype, $key, $filesize, $expectedsize = null) {
        global $DB;
        if ($expectedsize === null) {
            $expectedsize = $filesize;
        }
        $recordid = $this->insert_record_file($filesize * 1000 * 1000 * 1000, $mimetype);
        $t = new quota_manager();
        $usage = $t->get_usage_details();
        $expected = [$key => $expectedsize];
        try {
            $this->validate_usage($expected, $usage);
        } finally {
            $DB->delete_records("files", ["id" => $recordid]);
        }
    }
}
