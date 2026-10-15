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

/** Lists Moodle-managed backups and archives in the automated backup destination. */
class internal_backup_usage {
    /** @var array|null Combined inventory, collected once per report request. */
    private $backups = null;

    public function get_backup_paginated($limitfrom = 0, $limitnum = 0) {
        $all = $this->get_backups();
        $result = new \stdClass();
        $result->total = count($all);
        $result->limitnum = max(0, (int)$limitnum);
        if ($result->total > 100 && $result->limitnum == 0) {
            $result->limitnum = 10;
        }
        $result->limitfrom = max(0, (int)$limitfrom);
        // Clamp stale/out-of-range page requests to the last available page.
        if ($result->limitnum > 0 && $result->limitfrom >= $result->total) {
            $result->limitfrom = $result->total > 0 ?
                (int)(floor(($result->total - 1) / $result->limitnum) * $result->limitnum) : 0;
        }
        $result->hasnext = $result->limitnum > 0 &&
            $result->total > $result->limitfrom + $result->limitnum;
        $result->data = array_slice($all, $result->limitfrom, $result->limitnum ?: null);
        return $result;
    }

    public function get_backup_details($limitfrom = 0, $limitnum = 0) {
        return array_slice($this->get_backups(), max(0, (int)$limitfrom), $limitnum > 0 ? (int)$limitnum : null);
    }

    public function count() {
        return count($this->get_backups());
    }

    /** Count and page exactly the same inventory, including backups outside course contexts. */
    private function get_backups() {
        global $DB;
        if ($this->backups !== null) {
            return $this->backups;
        }
        $records = $DB->get_records_sql(
            "SELECT f.id, f.filename, f.filesize, f.timemodified, f.contextid, f.component, f.filearea,
                    c.id AS course_id, c.fullname AS course_name
               FROM {files} f
          LEFT JOIN {context} ctx ON ctx.id = f.contextid
          LEFT JOIN {course} c ON ctx.contextlevel = :courselevel AND c.id = ctx.instanceid
              WHERE f.filesize > 0 AND f.mimetype = :mimetype",
            array('courselevel' => CONTEXT_COURSE, 'mimetype' => 'application/vnd.moodle.backup'));
        $backups = array();
        foreach ($records as $record) {
            $record->id = 'file-' . $record->id;
            $record->storage = get_string('backup_source_filearea', 'block_disk_quota');
            $record->location = $record->component . '/' . $record->filearea;
            if (empty($record->course_id)) {
                $record->course_name = get_string('backup_course_unavailable', 'block_disk_quota');
            }
            $backups[] = $record;
        }
        $directory = get_config('backup', 'backup_auto_destination');
        if ($directory !== false && trim($directory) !== '') {
            $directory = realpath($directory);
            if ($directory === false || !is_dir($directory) || !is_readable($directory)) {
                throw new \moodle_exception('backup_directory_unreadable', 'block_disk_quota');
            }
            try {
                $this->add_directory_backups($directory, '', $backups);
            } catch (\UnexpectedValueException $exception) {
                throw new \moodle_exception('backup_directory_unreadable', 'block_disk_quota');
            } catch (\RuntimeException $exception) {
                throw new \moodle_exception('backup_directory_unreadable', 'block_disk_quota');
            }
        }
        usort($backups, function($a, $b) {
            // Stable ties prevent records swapping between pages when sizes and dates match.
            if ($a->filesize != $b->filesize) {
                return $a->filesize > $b->filesize ? -1 : 1;
            }
            if ($a->timemodified != $b->timemodified) {
                return $a->timemodified < $b->timemodified ? -1 : 1;
            }
            return strcmp($a->id, $b->id);
        });
        $this->backups = $backups;
        return $this->backups;
    }

    /** Recursively list nonempty .mbz archives; never follow nested symbolic links. */
    private function add_directory_backups($directory, $relative, array &$backups) {
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isLink()) {
                continue;
            }
            $path = $relative . $entry->getFilename();
            if ($entry->isDir()) {
                $this->add_directory_backups($entry->getPathname(), $path . '/', $backups);
            } else if ($entry->isFile() && strtolower($entry->getExtension()) === 'mbz' && $entry->getSize() > 0) {
                $backups[] = (object)array(
                    'id' => 'directory-' . sha1($path),
                    'filename' => $entry->getFilename(),
                    'filesize' => $entry->getSize(),
                    'timemodified' => $entry->getMTime(),
                    'course_id' => null,
                    'course_name' => get_string('backup_course_unavailable', 'block_disk_quota'),
                    'storage' => get_string('backup_source_directory', 'block_disk_quota'),
                    'location' => $path,
                );
            }
        }
    }
}
