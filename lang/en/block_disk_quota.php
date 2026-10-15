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

$string['storage_not_measured'] = 'not measured';
$string['storage_run_task'] = 'No physical storage measurement is available. Run the Get disk usage scheduled task.';
$string['storage_source'] = 'Storage source';
$string['storage_database'] = 'Database';
$string['storage_moodledata'] = 'Moodledata';
$string['storage_code'] = 'Moodle code';
$string['storage_total'] = 'Total occupied storage';
$string['storage_directory'] = 'Directory / root files';
$string['storage_root_files'] = 'Root files, symbolic links and root directory metadata';
$string['storage_measured_at'] = 'Measured at: {$a}';
$string['storage_measurement_note'] = 'Decimal GB. Filesystem sizes use allocated blocks; symbolic links are not followed and hard links are counted once. Database size is reported by the database engine and excludes server logs and shared infrastructure.';
$string['storage_database_estimate'] = 'MariaDB/MySQL database size is an engine-reported estimate of table data and indexes.';
$string['content_types'] = 'Content types';
$string['content_types_note'] = 'Logical content sizes from Moodle file records; physical file availability is not checked. These sizes are not added to the occupied storage total. Backups in the separate directory appear in the backup categories, not in these MIME-type figures.';

$string['backup_category_user'] = 'Backups in user file areas';
$string['backup_category_course'] = 'Manual backups in course areas';
$string['backup_category_automated'] = 'Automated course backups (course area)';
$string['backup_category_directory'] = 'Automated course backups (separate directory)';
$string['backup_category_other'] = 'Other backup file areas';
$string['backup_user'] = 'User';
$string['backup_invalid_category'] = 'Unknown backup category.';
$string['backup_all_categories'] = 'Backup categories';
$string['backup_directory_unconfigured'] = 'No automatic-backup destination directory is configured.';
$string['backup_metadata_note'] = 'Moodle file-area entries may refer to files absent from this local instance. Course names are shown only when the storage context identifies a course. Directory archives are not opened to infer their course or creator.';
