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

class quota_manager {

    protected $snapshot = null;

    public function get_total_disk_space_used() {
        $this->snapshot = (new storage_usage())->measure();
        return $this->snapshot['total_bytes'] / 1000000000;
    }

    /** Logical content metadata only; never added to the physical storage total. */
    public function get_usage_details() {
        return (new internal_space_usage())->get_usage_details();
    }

    public function record_space_used($used, $quota) {
        global $DB;
        if ($this->snapshot === null) {
            throw new \RuntimeException('Measure storage before recording its total.');
        }
        $json = json_encode($this->snapshot, JSON_THROW_ON_ERROR);
        $rec = (object)array('timemeasured' => $this->snapshot['timemeasured'],
            'usedgb' => $this->snapshot['total_bytes'] / 1000000000, 'quotagb' => $quota);
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('block_disk_quota_measurement', $rec);
        set_config(storage_usage::CONFIG_KEY, $json, 'block_disk_quota');
        $transaction->allow_commit();
    }

    public static function get_quota_and_space_used_gb() {
        global $DB;
        $snapshot = storage_usage::saved();
        $used = $snapshot === null ? null : $snapshot['total_bytes'] / 1000000000;
        $a = new \stdClass;
        $a->quota = get_config('block_disk_quota', 'quota_gb');
        $a->used = $used;
        $a->warn_limit = (float)get_config('block_disk_quota', 'warn_when_within_gb_of_limit');
        return $a;
    }

    public static function get_activeusers_and_quota() {
        global $DB;
        $a = new \stdClass;
        $a->quota = get_config('block_disk_quota', 'quota_activeusers');
        $lastyear = strtotime("-1 year", time());
        $a->activeusers = $DB->get_field_sql("
            SELECT count(*)
              FROM {user}
             WHERE lastaccess > :lastyear
        ", array('lastyear' => $lastyear));
        return $a;
    }

    /**
     * For measurements that are older than 30 days, only keep one measurement per day.
     *
     * The measurement kept will be the smallest freespace measurement taken during that day. In
     * case several smallest freespace measurements are the same for a single day, only one of these
     * will be kept.
     */
    public function reduce_old_measurements() {
        global $DB;
        $thirtydaysago = time() - (60 * 60 * 24 * 30);
        $days = array();
        $records = $DB->get_records_select(
            'block_disk_quota_measurement',
            'timemeasured < ? AND historic = 0',
            array($thirtydaysago));
        if (!$records) {
            return;
        }
        foreach ($records as $id => $record) {
            $date = date('Y.m.d', $record->timemeasured);
            $freespace = $record->quotagb - $record->usedgb;
            if (!isset($days[$date]) or $days[$date][1] > $freespace) {
                $days[$date] = array($id, $freespace);
            }
        }

        // Extract the ids that should not be deleted.
        $keepids = array();
        foreach ($days as $info) {
            $keepids[] = $info[0];
        }

        // Delete all those old records but the ones that are being kept.
        $keepidslist = implode(',', $keepids);
        $DB->delete_records_select(
            'block_disk_quota_measurement',
            "timemeasured < ? AND historic = 0 AND id NOT IN ($keepidslist)",
            array($thirtydaysago, $keepidslist));

        // Set the historic flag on the kept records, so that they will not be considered again by this method.
        $DB->execute("UPDATE {block_disk_quota_measurement} set historic=1 where id in ($keepidslist)");
    }
}
