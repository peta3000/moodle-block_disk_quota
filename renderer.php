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
            $info->used = 'unknown';
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
        $base = log($size, 1000);

        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
        $unit = floor($base);
        return round(pow(1000, $base - floor($base)), $precision) . ' ' . $suffixes[$unit];
    }

    /**
     * @param int $page
     * @param int $limit
     * @return bool|string
     * @throws moodle_exception
     */
    public function disk_backup_usage($page = 1, $limit = 100) {
        if ($limit < 1) {
            throw new \InvalidArgumentException("Limit should be >= 1");
        }
        $backupusage = new block_disk_quota\usage\internal_backup_usage();
        $vars = $backupusage->get_backup_paginated(($page - 1) * $limit, $limit);
        // Render each line as sting, to be compatible with mustache.
        $vars->data = array_values(array_map(function($element) {
            $element->filesize = self::format_bytes($element->filesize);
            $element->course_url = new moodle_url("/course/view.php", array("id" => $element->course_id));
            return "" . $this->render_from_template('block_disk_quota/disk_backup_usage_line', (array) $element);
        }, $vars->data));

        return $this->render_from_template('block_disk_quota/disk_backup_usage', (array) $vars) . "\n" .
                $this->render_backup_pagination($page, $limit, $vars->total, function($page) {
                    return new moodle_url("/blocks/disk_quota/backups.php", ["page" => $page]);
                });
    }

    public function usage_detail() {
        $vars = array(
                'overview' => $this->disk_quota_usage(),
                'detail' => $this->usage_detail_table(),
        );
        return $this->render_from_template('block_disk_quota/usage_detail', $vars);
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
            if ("backup" === $type && $value > 0.0) {
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
        return $this->render_from_template('block_disk_quota/activeusers_quota', $this->activeusers_usage());
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
