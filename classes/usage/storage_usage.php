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

namespace block_disk_quota\usage;

defined('MOODLE_INTERNAL') || die();

/** One saved snapshot for the three physical storage sources. */
class storage_usage {
    const CONFIG_KEY = 'physical_storage_snapshot';

    public function measure() {
        global $CFG;
        if (PHP_INT_SIZE < 8) {
            throw new \RuntimeException('Storage measurement requires 64-bit PHP.');
        }
        $data = realpath($CFG->dataroot);
        // Moodle 5.1+ separates the installation root from its public dirroot.
        $code = realpath(!empty($CFG->root) ? $CFG->root : $CFG->dirroot);
        if ($data === false || $code === false || !is_dir($data) || !is_dir($code) || $data === $code) {
            throw new \RuntimeException('Moodledata and code must be accessible, distinct directories.');
        }
        $scanner = new filesystem_usage();
        // The more specific root owns its subtree. Cross-root hard links count once.
        if ($this->inside($code, $data)) {
            $codeusage = $scanner->measure($code);
            $datausage = $scanner->measure($data, array($code));
        } else {
            $datausage = $scanner->measure($data);
            $codeusage = $scanner->measure($code, $this->inside($data, $code) ? array($data) : array());
        }
        $database = (new database_usage())->measure();
        return array(
            'version' => 1,
            'timemeasured' => time(),
            'database' => $database,
            'moodledata' => $datausage,
            'code' => $codeusage,
            'total_bytes' => $database['bytes'] + $datausage['bytes'] + $codeusage['bytes'],
        );
    }

    private function inside($child, $parent) {
        return strpos($child, rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
    }

    public static function saved() {
        $json = get_config('block_disk_quota', self::CONFIG_KEY);
        if (!$json) {
            return null;
        }
        $snapshot = json_decode($json, true);
        if (!is_array($snapshot) || ($snapshot['version'] ?? null) !== 1 ||
                !isset($snapshot['total_bytes'], $snapshot['timemeasured'], $snapshot['database']['bytes'],
                    $snapshot['moodledata']['bytes'], $snapshot['code']['bytes'], $snapshot['moodledata']['breakdown'])) {
            return null;
        }
        return $snapshot;
    }
}
