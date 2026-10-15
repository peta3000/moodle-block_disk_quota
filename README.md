# Disk Quota block for Moodle

## Overview
This plugin allows automatically disabling the Moodle if it's total disk usage
exceeds the defined site-wide quota.  It allows seeing how much space is
currently being used and the currently set quota.  A detail view is available
to show what kind of files (e.g. backup, video, audio, document) are using
up how much space.

The use case for this plugin is for a managed Moodle.  It is assumed that
the Moodle admin users should not be allowed to change most of the settings
for this plugin.

The total disk usage details are collected every day (assuming
that the Moodle cron is running at least every day )

## Installation

### Forced config settings

In config.php, define ``$CFG->forced_plugin_settings`` (or add to it, if already defined) something like this:
```
$CFG->forced_plugin_settings = array(
    'block_disk_quota'  => array(
        'quota_gb' => 100,                     // Quota allocated for the entire Moodle in GB (GigaBytes, 1000^3 bytes)
        'quota_activeusers' => 300,            // Active users' quota (connections in the last 365 days)
    )
);
```
This will prevent these settings from being changed by anyone that doesn't have access to change the config.php file itself.

### Install the plugin
After having placed the ``disk_quota`` directory in the Moodle's ``blocks/`` directory, visit
``/admin/index.php`` or run the CLI upgrade script to install the plugin.

## Uninstall
This plugin cannot be removed by the admin interface.
In order to bypass this, you can comment the ``xmldb_block_disk_quota_uninstall`` function in ``db/uninstall.php``.

## Usage
By default, only admin users can add a disk usage block, or view an existing
disk usage block or the disk usage details page.  To change this, change the
permissions for the role you want to give access to; look for the disk_quota
permissions that you need to change - they should be self-explanatory.

The disk usage block shows how much space has been used and the quota.  It
also contains a link to the detail page, which shows how much space different
types of files are using.

For users with appropriate permissions, the detail page can be seen, regardless
of whether or not there are any block instances by navigating to the
``/blocks/disk_quota/`` page in Moodle.

Note that no block instance needs to exist for the cron job,
and automatic site blocking to work.

## Compatibility
This plugin should work on Moodle 2.7+.
The usage detail page may not work if using a non-PosgreSQL database.

## TODO
* Nicer visual indication of current space used.
* Handle local disk usage of repositories other than those tracked in the Moodle database.

## Additional moodledata storage

The scheduled measurement now adds regular file lengths in every top-level
`$CFG->dataroot` directory except `filedir`. The existing deduplicated `{files}`
calculation and MIME-type breakdown remain unchanged. Additional entries appear
as `moodledata/kurssicherung`, `moodledata/cache`, `moodledata/trashdir`, etc.
Files directly inside dataroot appear as `moodledata (root files)`.

This is a logical-byte total (decimal GB), not filesystem allocated blocks:
filesystem metadata, directory blocks and sparse-file allocation are not measured.
Symbolic links are skipped at every level, including linked directories. Real
mounted directories inside dataroot are traversed. Hard-linked files outside
filedir are counted for each pathname. Orphaned filedir files remain excluded.
Storage configured outside dataroot is not included.

Directory scanning happens only in `get_disk_usage`, not on report page views.
The latest directory breakdown is saved in plugin configuration in the same
database transaction as the total measurement. The existing MIME breakdown is
still calculated live, so it can differ from the latest scheduled total.
Before the first successful task execution, no directory rows are available.
Run the task after applying this patch:

```sh
php admin/cli/scheduled_task.php --execute='\block_disk_quota\task\get_disk_usage'
```

The task user must be able to list the tree and stat its files. An iteration or
stat exception aborts the new measurement, retaining the previous saved result;
check the task log and rerun after fixing access or a transient filesystem race.
Actively changing directories do not provide an atomic filesystem snapshot.
A scan of a large backup tree can take time; use the normal scheduled task.
No database schema changes are required.

Tests: `tests/usage/dataroot_space_usage_test.php` covers directory grouping,
filedir exclusion, root files, symbolic links, persisted breakdown and a failed
scan. Run with your Moodle PHPUnit installation before production deployment.
