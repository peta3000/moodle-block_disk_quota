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

## Storage hierarchy and backup reports

The current feature separates occupied storage from logical content metadata.
The scheduled task measures the configured database plus actual allocated
filesystem storage in moodledata and the Moodle installation. The main report
links to a moodledata directory breakdown and a separate logical content-type
report. Backups have distinct user, manual-course, automated-course and external
directory lists, plus a catch-all for other file areas.

Run the plugin upgrade and the scheduled task after applying the numbered patch
series. See [TESTING.md](TESTING.md) for installation commands, validation,
measurement scope, supported database engines and the history metric change.
