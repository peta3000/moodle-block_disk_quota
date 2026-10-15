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
 * Class InternalSpaceUsage
 *
 * Provides detail about the space used by the files that Moodle manages.
 */
class internal_space_usage implements space_usage_interface {
    public function get_usage_details() {
        global $DB;
        $gigabyte = 1000 * 1000 * 1000;

        $used = $DB->get_records_sql("
            SELECT
                files.mimetype,
                SUM(files.filesize) as size
            FROM (
                SELECT
                    mimetype,
                    filesize
                FROM {files}
                WHERE filesize > 0
                GROUP BY
                    mimetype,
                    filesize,
                    contenthash
            ) files
            GROUP BY files.mimetype
        ");

        $gbtotal = 0;
        $breakdown = array(
            'audio' => 0,
            'application' => 0,
            'backup' => 0,
            'document' => 0,
            'image' => 0,
            'text' => 0,
            'unknown' => 0,
            'video' => 0
        );

        foreach ($used as $record) {
            $gbsize = $record->size / $gigabyte;
            $gbtotal += $gbsize;

            $explodedmimetype = explode("/", (string)$record->mimetype, 2);
            $mimetype = $explodedmimetype[0] ?: 'unknown';
            $mimesubtype = $explodedmimetype[1] ?? '';
            if (!isset($breakdown[$mimetype])) {
                $breakdown[$mimetype] = 0;
            }

            // Exctract vnd.moodle.backup subtype from application mimetype.
            if ($mimesubtype === 'vnd.moodle.backup') {
                $breakdown['backup'] += $gbsize;
            } else {
                $breakdown[$mimetype] += $gbsize;
            }
        }

        return array(
            'total_gb' => $gbtotal,
            'breakdown' => $breakdown
        );
    }

    public function get_total_used() {
        global $DB;
        $used = $DB->get_field_sql(
            "select sum(filesize) / (1000 * 1000 * 1000)
               from (select filesize from {files} group by filesize, contenthash) distinct_files");

        return $used === false ? 0 : $used;
    }
}
