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

/**
 * Apparent file sizes outside filedir, grouped by top-level dataroot entry.
 * Symbolic links are excluded: do not follow links outside dataroot or count loops.
 */
class dataroot_space_usage implements space_usage_interface {
    /** @var array|null The current scan, or null when displaying saved results. */
    private $details = null;

    /**
     * Scan in the scheduled task only. Failure aborts the new measurement.
     * @return array Usage in decimal GB.
     */
    public function refresh() {
        global $CFG;
        $breakdown = array('unknown' => 0.0);
        $totalbytes = 0;
        $rootbytes = 0;
        foreach (new \FilesystemIterator($CFG->dataroot, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->getFilename() === 'filedir' || $entry->isLink()) {
                continue;
            }
            if ($entry->isDir()) {
                $bytes = $this->directory_bytes($entry->getPathname());
                $breakdown['moodledata/' . $entry->getFilename()] = $bytes / 1000000000;
                $totalbytes += $bytes;
            } else if ($entry->isFile()) {
                $rootbytes += $entry->getSize();
            }
        }
        if ($rootbytes > 0) {
            $breakdown['moodledata (root files)'] = $rootbytes / 1000000000;
        }
        $this->details = array(
            'total_gb' => ($totalbytes + $rootbytes) / 1000000000,
            'breakdown' => $breakdown,
            'timemeasured' => time(),
        );
        return $this->details;
    }

    /** @return int Sum of regular file lengths, without following symbolic links. */
    private function directory_bytes($path) {
        $bytes = 0;
        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isLink()) {
                continue;
            }
            if ($entry->isDir()) {
                $bytes += $this->directory_bytes($entry->getPathname());
            } else if ($entry->isFile()) {
                $bytes += $entry->getSize();
            }
        }
        return $bytes;
    }

    /** @return array Current scan, or the most recent successful saved scan. */
    public function get_usage_details() {
        if ($this->details !== null) {
            return $this->details;
        }
        $saved = get_config('block_disk_quota', 'dataroot_usage_details');
        if ($saved !== false && $saved !== '') {
            $details = json_decode($saved, true);
            if (is_array($details) && isset($details['total_gb'], $details['breakdown'])) {
                return $details;
            }
        }
        return array('total_gb' => 0.0, 'breakdown' => array('unknown' => 0.0));
    }

    /** @return float Usage from the current or saved scan. */
    public function get_total_used() {
        $details = $this->get_usage_details();
        return $details['total_gb'];
    }
}
