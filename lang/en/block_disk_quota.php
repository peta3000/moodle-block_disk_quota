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
/**
 * Strings for component 'block_disk_quota', language 'en'
 *
 * @package   block_recent_activity
 * @copyright 2015 Liip AG {@link http://liip.ch}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Liip Usage Quota';
$string['disk_quota:addinstance'] = 'Add a new Disk Quota block';
$string['disk_quota:myaddinstance'] = 'Add a new Disk Quota block to My Home (aka Dashboard)';
$string['disk_quota:viewblock'] = 'View a disk quota block';
$string['disk_quota:viewusage'] = 'View disk quota details page';
$string['err_cannot_uninstall_plugin'] = 'Uninstalling this plugin is not possible';
$string['quota_used'] = '{$a->used} of {$a->quota} GB used';
$string['gigabytes_used'] = 'GB used';
$string['active_users'] = '{$a->activeusers} active users ({$a->quota} allowed)';


// Tasks.
$string['task_get_disk_usage'] = 'Get disk usage';

// Settings strings.
$string['quota_gb'] = 'Disk Quota';
$string['quota_gb_desc'] = 'Disk Quota in gigabytes';
$string['quota_activeusers'] = 'Active users Quota';
$string['quota_activeusers_desc'] = 'Quota of active users (connected in the last 365 days)';

$string['backup_filename'] = 'Filename';
$string['backup_course'] = 'Course';
$string['backup_timemodified'] = 'Last modified';
$string['backup_size'] = 'Size';
$string['backup_page_title'] = 'Backups details';

$string['backup_storage'] = 'Storage location';
$string['backup_source_filearea'] = 'Moodle file area';
$string['backup_source_directory'] = 'Automatic backup directory';
$string['backup_course_unavailable'] = 'Not identified';
$string['backup_directory_unreadable'] = 'The configured automatic backup directory or a subdirectory could not be read. Check its path and permissions, then reload the report.';
