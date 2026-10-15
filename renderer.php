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

use block_disk_quota\usage\quota_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * disk_quota block renderer
 */
class block_disk_quota_renderer extends plugin_renderer_base {

    public function current_usage() {
        $vars = array(
                'usage' => $this->disk_quota_usage(),
                'activeusers' => $this->activeusers_usage(),
                'url' => new moodle_url('/blocks/disk_quota/index.php'),
                'link_text' => get_string('detailedmore', 'moodle'),
        );
        return $this->render_from_template('block_disk_quota/current_usage', $vars);
    }

    public function disk_quota_usage() {
        $quotamanager = new quota_manager();
        $info = $quotamanager->get_quota_and_space_used_gb();
        $info->quota = round(floatval($info->quota), 1);

        $classes = '';
        if (is_null($info->used)) {
            $info->used = get_string('storage_not_measured', 'block_disk_quota');
        } else {
            $info->used = round(floatval($info->used), 3);
            $diff = $info->quota - $info->used;

            if ($diff <= 0) {
                $classes .= ' disk-quota--danger';
            } else if ($diff <= $info->warn_limit) {
                $classes .= ' disk-quota--warning';
            }
        }
        $vars = array(
                'space_used' => get_string('quota_used', 'block_disk_quota', $info),
                'classes' => $classes
        );
        return $this->render_from_template('block_disk_quota/disk_quota_usage', $vars);
    }

    /**
     * Source: https://stackoverflow.com/a/2510540
     *
     * @param int $size Size in Bytes
     * @param int $precision
     * @return string
     */
    protected static function format_bytes($size, $precision = 2) {
        if ($size <= 0) {
            return '0 B';
        }
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $unit = min(count($suffixes) - 1, max(0, (int)floor(log($size, 1000))));
        return round($size / pow(1000, $unit), $precision) . ' ' . $suffixes[$unit];
    }

    /**
     * @param int $page
     * @param int $limit
     * @return bool|string
     * @throws moodle_exception
     */
    public function disk_backup_usage($page = 1, $limit = 100, $category = 'all') {
        if ($limit < 1) {
            throw new \InvalidArgumentException("Limit should be >= 1");
        }
        $page = max(1, (int)$page);
        $backupusage = new block_disk_quota\usage\internal_backup_usage($category);
        $vars = $backupusage->get_backup_paginated(($page - 1) * $limit, $limit);
        $page = (int)floor($vars->limitfrom / $limit) + 1;
        // Render each line as sting, to be compatible with mustache.
        $vars->data = array_values(array_map(function($element) {
            $element = clone $element;
            $element->filesize = self::format_bytes($element->filesize);
            if (!empty($element->course_id)) {
                $element->course_url = new moodle_url("/course/view.php", array("id" => $element->course_id));
            }
            if (!empty($element->user_id)) {
                $element->user_url = new moodle_url('/user/profile.php', array('id' => $element->user_id));
            }
            return "" . $this->render_from_template('block_disk_quota/disk_backup_usage_line', (array) $element);
        }, $vars->data));

        return $this->render_from_template('block_disk_quota/disk_backup_usage', (array) $vars) . "\n" .
                $this->render_backup_pagination($page, $limit, $vars->total, function($page) use ($category) {
                    return new moodle_url("/blocks/disk_quota/backups.php", ["page" => $page, "category" => $category]);
                });
    }

    public function backup_categories() {
        $output = '';
        foreach (\block_disk_quota\usage\internal_backup_usage::categories() as $category => $key) {
            $output .= html_writer::tag('li', html_writer::link(
                new moodle_url('/blocks/disk_quota/backups.php', array('category' => $category)),
                get_string($key, 'block_disk_quota')));
        }
        return html_writer::tag('ul', $output);
    }

    public function usage_detail() {
        $snapshot = \block_disk_quota\usage\storage_usage::saved();
        $output = $this->disk_quota_usage();
        if ($snapshot === null) {
            $output .= html_writer::tag('p', get_string('storage_run_task', 'block_disk_quota'));
        } else {
            $table = new html_table();
            $table->head = array(get_string('storage_source', 'block_disk_quota'),
                get_string('gigabytes_used', 'block_disk_quota'));
            $table->data = array(
                array(get_string('storage_database', 'block_disk_quota'),
                    round($snapshot['database']['bytes'] / 1000000000, 3)),
                array(html_writer::link(new moodle_url('/blocks/disk_quota/moodledata.php'),
                    get_string('storage_moodledata', 'block_disk_quota') . ' (' . s($snapshot['moodledata']['path']) . ')'),
                    round($snapshot['moodledata']['bytes'] / 1000000000, 3)),
                array(get_string('storage_code', 'block_disk_quota') . ' (' . s($snapshot['code']['path']) . ')',
                    round($snapshot['code']['bytes'] / 1000000000, 3)),
                array(html_writer::tag('strong', get_string('storage_total', 'block_disk_quota')),
                    html_writer::tag('strong', round($snapshot['total_bytes'] / 1000000000, 3))),
            );
            $output .= html_writer::table($table) . $this->measurement_note($snapshot);
        }
        $output .= html_writer::tag('p', html_writer::link(new moodle_url('/blocks/disk_quota/content_types.php'),
            get_string('content_types', 'block_disk_quota')));
        return $output;
    }

    public function moodledata_detail() {
        $snapshot = \block_disk_quota\usage\storage_usage::saved();
        if ($snapshot === null) {
            return html_writer::tag('p', get_string('storage_run_task', 'block_disk_quota'));
        }
        $table = new html_table();
        $table->head = array(get_string('storage_directory', 'block_disk_quota'),
            get_string('gigabytes_used', 'block_disk_quota'));
        $breakdown = $snapshot['moodledata']['breakdown'];
        arsort($breakdown);
        foreach ($breakdown as $name => $bytes) {
            $label = $name === '.' ? get_string('storage_root_files', 'block_disk_quota') : s($name);
            $table->data[] = array($label, round($bytes / 1000000000, 3));
        }
        $table->data[] = array(get_string('storage_total', 'block_disk_quota'),
            round($snapshot['moodledata']['bytes'] / 1000000000, 3));
        return html_writer::tag('p', s($snapshot['moodledata']['path'])) .
            html_writer::table($table) . $this->measurement_note($snapshot);
    }

    private function measurement_note(array $snapshot) {
        $output = html_writer::tag('p', get_string('storage_measured_at', 'block_disk_quota',
            userdate($snapshot['timemeasured'])));
        $output .= html_writer::tag('p', get_string('storage_measurement_note', 'block_disk_quota'));
        if ($snapshot['database']['method'] === 'mysql_table_estimate') {
            $output .= html_writer::tag('p', get_string('storage_database_estimate', 'block_disk_quota'));
        }
        return $output;
    }

    public function usage_detail_table() {
        $quotamanager = new quota_manager();
        $detail = $quotamanager->get_usage_details();
        $table = new html_table();
        $table->head = array(
                get_string('type', 'search'),
                get_string('gigabytes_used', 'block_disk_quota')
        );
        $data = array();

        $breakdown = $detail['breakdown'];
        arsort($breakdown);
        foreach ($breakdown as $type => $value) {
            if ("backup" === $type) {
                $backuplink = html_writer::link(new moodle_url("/blocks/disk_quota/backups.php"), $type);
                $data[] = array($backuplink, round($value, 3));
            } else {
                $data[] = array(s($type), round($value, 3));
            }
        }
        $table->data = $data;
        return html_writer::table($table);
    }

    public function activeusers_usage() {
        $quotamanager = new quota_manager();
        $info = $quotamanager->get_activeusers_and_quota();

        $classes = $info->activeusers >= $info->quota ? ' disk-quota--warning' : '';

        $vars = array(
                'active_users' => get_string('active_users', 'block_disk_quota', $info),
                'classes' => $classes
        );
        return $this->render_from_template('block_disk_quota/activeusers_usage', $vars);
    }

    public function activeusers_quota() {
        return $this->activeusers_usage();
    }

    /**
     * @param int $page
     * @param int $limit Must be > 0
     * @param int $total
     * @param callable $linkgenerator
     * @return string
     */
    private function render_backup_pagination($page, $limit, $total, callable $linkgenerator) {
        $page = intval($page);
        $pagefrom = max(1, $page - 3);
        $maxpage = ceil($total / $limit);
        $pageto = min($maxpage, $page + 3);

        if ($maxpage < 2) {
            return '';
        }

        $output = '<nav aria-label="Page"><div class="pagination">';
        if ($page > 1) {
            $url = call_user_func($linkgenerator, $page - 1);
            $output .= '<li class="page-item">' .
                    '<a href="' . $url . '" class="page-link">' .
                    '<span aria-hidden="true">«</span>' .
                    '<span class="sr-only">Previous</span>' .
                    '</a>' .
                    '</li>';
        }
        for ($p = $pagefrom; $p <= $pageto; $p++) {
            $class = array("page-item");
            $page === $p ? $class[] = "active" : null;
            $link = call_user_func($linkgenerator, $p);
            if ($page === $p) {
                // Current page.
                $output .= '<li class="page-item active"><a href="#" class="page-link">' . $p . '</a></li>';
            } else {
                $output .= '<li class="' . implode(" ", $class) . '">' .
                        '<a class="page-link" href="' . $link . '">' . $p . '</a></li>';
            }
        }
        if ($page < $maxpage) {
            $url = call_user_func($linkgenerator, $page + 1);
            $output .= '<li class="page-item">' .
                    '<a href="' . $url . '" class="page-link">' .
                    '<span aria-hidden="true">»</span>' .
                    '<span class="sr-only">Next</span>' .
                    '</a>' .
                    '</li>';
        }
        return $output . "</div>";
    }
}
