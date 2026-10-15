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

/** Size reported by the connected database engine, including indexes. */
class database_usage {
    public function measure() {
        global $DB;
        $family = $DB->get_dbfamily();
        if ($family === 'postgres') {
            $bytes = $DB->get_field_sql('SELECT pg_database_size(current_database())');
            $method = 'postgres_database_size';
        } else if ($family === 'mysql') {
            // MariaDB/MySQL: engine-reported data + indexes; an estimate for InnoDB.
            $bytes = $DB->get_field_sql('SELECT COALESCE(SUM(COALESCE(data_length, 0) +
                COALESCE(index_length, 0)), 0) FROM information_schema.tables WHERE table_schema = DATABASE()');
            $method = 'mysql_table_estimate';
        } else {
            throw new \RuntimeException('Disk quota database measurement supports PostgreSQL and MariaDB/MySQL.');
        }
        if (!is_numeric($bytes) || !is_finite((float)$bytes) || $bytes < 0) {
            throw new \RuntimeException('Database size measurement returned no valid size.');
        }
        return array('bytes' => (int)$bytes, 'method' => $method);
    }
}
