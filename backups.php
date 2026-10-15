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

require_once('../../config.php');
require_login();
$context = context_system::instance();
require_capability('block/disk_quota:viewusage', $context);
$category = optional_param('category', '', PARAM_ALPHA);
$page = max(1, optional_param('page', 1, PARAM_INT));
$categories = \block_disk_quota\usage\internal_backup_usage::categories();
if ($category !== '' && !array_key_exists($category, $categories)) {
    throw new moodle_exception('backup_invalid_category', 'block_disk_quota');
}
$PAGE->set_url('/blocks/disk_quota/backups.php', array('category' => $category, 'page' => $page));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$title = get_string('backup_page_title', 'block_disk_quota');
$PAGE->navbar->add(get_string('pluginname', 'block_disk_quota'), new moodle_url('/blocks/disk_quota/index.php'));
$PAGE->navbar->add(get_string('content_types', 'block_disk_quota'), new moodle_url('/blocks/disk_quota/content_types.php'));
$PAGE->navbar->add($title, new moodle_url('/blocks/disk_quota/backups.php'));
if ($category !== '') {
    $title = get_string($categories[$category], 'block_disk_quota');
    $PAGE->navbar->add($title);
}
$PAGE->set_title($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$renderer = $PAGE->get_renderer('block_disk_quota');
if ($category === '') {
    echo $renderer->backup_categories();
} else {
    echo html_writer::tag('p', html_writer::link(new moodle_url('/blocks/disk_quota/backups.php'),
        get_string('backup_all_categories', 'block_disk_quota')));
    echo html_writer::tag('p', get_string('backup_metadata_note', 'block_disk_quota'));
    if ($category === 'directory') {
        $destination = get_config('backup', 'backup_auto_destination');
        if (!$destination) {
            echo html_writer::tag('p', get_string('backup_directory_unconfigured', 'block_disk_quota'));
        } else {
            echo html_writer::tag('p', s($destination));
        }
    }
    echo $renderer->disk_backup_usage($page, 15, $category);
}
echo $OUTPUT->footer();
