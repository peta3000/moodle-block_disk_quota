# Storage hierarchy and backup categories: test guide

Target base: `peta3000/moodle-block_disk_quota`, branch
`all_backups_and_moodledata`, commit
`956cf9ecbcc751c6901b4d0f7f3470369793e045`.

Apply this new series on top of that feature branch, which already contains the
previous two patches. Do not reapply the old patches. The four numbered patches
must be applied in order. Test this feature on your local/staging instance.

## What changes

1. The quota block and main report use **database + actual moodledata + actual
   Moodle installation** storage. Database file records are no longer used for
   that total. Before the first successful new measurement, the UI says that
   storage has not yet been measured.
2. `/blocks/disk_quota/index.php` shows three source rows and their sum.
   Moodledata links to `/blocks/disk_quota/moodledata.php`, with immediate
   subdirectories and a root-files/metadata row. Code and database have one
   size each. Source sizes and directory detail come from the same saved scan.
3. `/blocks/disk_quota/content_types.php` retains the logical MIME-type view
   from Moodle file records, separately from occupied storage. Its `backup`
   link opens a category menu rather than a combined file list.
4. Backup categories use these exact filters:

   | Category | Context | Component | File area |
   | --- | --- | --- | --- |
   | User file areas | User | `user` | `backup` |
   | Manual course backups | Course | `backup` | `course` |
   | Automated course backups in course storage | Course | `backup` | `automated` |
   | Separate directory | Filesystem | Configured automatic-backup destination | Nonempty `.mbz` files |
   | Other file areas | Any other matching backup file record | Other | Other |

   The first three are distinct locations. The additional "Other" view keeps
   section/activity/unusual file-area records accessible. File-area rows can
   show the file creator, or the user-context owner for personal backups.
   Directory archives are not opened to infer course or user identities.

## Apply and commit

Start with a clean plugin checkout on `all_backups_and_moodledata`. Extract the
patch bundle outside the checkout. Each patch is a regular Git diff. Either
use the included `apply-series.sh` to apply/stage/commit one theme at a time,
or apply and commit each numbered file manually:

```sh
git switch all_backups_and_moodledata
git status --short
git apply --check /path/to/patches/0001-storage-measurement.patch
git apply --index /path/to/patches/0001-storage-measurement.patch
git commit -m "Measure database, moodledata and code storage"
```

Then repeat for 0002, 0003 and 0004 in order. Use the titles in `series.txt`.
Push only after testing. In the parent Moodle checkout, commit the updated
submodule reference after pushing the plugin commits.

## Activate in the known local Docker container

These commands use the webserver container and paths supplied in the
conversation. They assume its bind-mounted host checkout points at the patched
plugin commit. The code root remains `/var/www/html`; on modern Moodle its public
web root can be `/var/www/html/public`.

```sh
docker restart moodle-migration-509-webserver-1

docker exec --user www-data --workdir /var/www/html \
  moodle-migration-509-webserver-1 \
  php admin/cli/upgrade.php --non-interactive

docker exec --user www-data --workdir /var/www/html \
  moodle-migration-509-webserver-1 \
  php admin/cli/purge_caches.php

docker exec --user www-data --workdir /var/www/html \
  moodle-migration-509-webserver-1 \
  php admin/cli/scheduled_task.php \
  --execute='\block_disk_quota\task\get_disk_usage'
```

The plugin version increases to `2026101500`. No new tables or columns are
introduced. The task user needs directory traversal and stat access throughout
both storage roots, plus permission to execute the database size query.
Update/restart a separate cron or worker container too if it uses another code
checkout/image. Allow sufficient cron memory/time for a full file pool scan.

## Automated checks

The self-contained regression runner needs PHP 8.1+ with PDO SQLite and a
64-bit Linux runtime. It does not bootstrap Moodle or touch your actual site
configuration/database. It uses temporary filesystem fixtures, an in-memory
SQLite database, and minimal Moodle adapters, then removes its fixtures.

From the plugin checkout:

```sh
php tests/standalone/regression.php
```

It checks source totals and directory reconciliation, inclusion of `filedir`,
the modern `root`/`dirroot` layout, nested roots in both directions, failed scans
and writes preserving the previous snapshot, DB adapter selection/rejection,
category isolation, user/course ID collisions, pagination and category links.
The native CLI run also checks allocated blocks for hard links and symlinks.

Moodle PHPUnit regression cases are in `tests/usage/filesystem_usage_test.php`
and `tests/usage/backup_categories_test.php`; the earlier tests were adapted to
the new physical metric. With your Moodle PHPUnit environment already set up,
run the plugin's tests using its relative path in that checkout, for example:

```sh
vendor/bin/phpunit public/blocks/disk_quota/tests
```

For layouts without `public/`, use `blocks/disk_quota/tests`. A separate
PHPUnit database/data directory must be configured before using Moodle PHPUnit.

## Manual acceptance checks

- Open the main report before running the new task: it must not reuse an old
  logical measurement as a physical one.
- After a successful task, check all three rows and the total. The unrounded
  source bytes sum exactly; individually rounded GB rows can differ by 0.001 GB
  from the rounded total. The block's used value comes from this same snapshot.
- Open Moodledata detail. `filedir`, `kurssicherung`, caches, and root files
  appear where present. An empty `filedir` has directory metadata only, not
  the logical file sizes imported in `mdl_files`.
- Add/remove a disposable file in moodledata and rerun the task. Only the next
  successful scan updates the physical overview; report page views do not scan
  the storage roots.
- Check content types: database-recorded backup/video/image sizes remain
  independent of which files exist locally and do not add to the physical sum.
- Open each backup category and inspect every page. Course/manual and automated
  course storage must not mix; user rows show the owner; separate-directory
  rows include relative archive paths. Empty categories have no extra pages.
- On a category with multiple pages, verify pagination retains `category=...`.
  An obsolete out-of-range page link opens its last valid page.
- Confirm a configured but missing backup directory reports an error only in
  its own category. Course and user lists remain available.
- On a test copy, an invalid/unreadable storage root or denied database size
  query must fail the task and preserve the last successful physical snapshot.

## Measurement scope and limits

- All sizes are decimal GB. Filesystem measurements use `lstat().blocks * 512`,
  so directory blocks and sparse-file allocation are represented. Linux/64-bit
  PHP is required; unsupported allocation stats cause an error, not a guessed
  zero. Filesystem metadata is not a complete accounting of all filesystem-wide
  overhead, compression/reflinks/snapshots, or the Docker storage layer.
- Symbolic-link targets are not followed. Root paths are resolved first, so an
  explicitly configured root can itself be a symlink. Real nested mounts are
  scanned. Hard links are charged once, with deterministic top-level traversal;
  nested code/data roots are assigned to the more specific source. Mount aliases
  of entire already-seen directories are not traversed again.
- Code uses `$CFG->root` when present (the full installation on Moodle 5.1+),
  otherwise `$CFG->dirroot`. Data uses `$CFG->dataroot`. External file stores,
  directories outside these roots, and symlink targets outside them are not
  added as a fourth source. A configured backup directory can be listed even
  if it is outside the measured roots.
- PostgreSQL uses `pg_database_size(current_database())`; this measures the
  configured database, not only Moodle-prefixed tables. MariaDB/MySQL uses
  `information_schema.tables` data/index sizes for the connected database;
  InnoDB values are estimates. Server logs, WAL/binlogs, backups of the database,
  and shared server overhead are not included by these database queries. Keep
  the database server's own data directory outside the measured code/data roots.
- A task run is not an atomic snapshot of an actively changing filesystem.
  Traversal/stat failures abort the measurement; inspect the task log and retry.
- File-area backup lists are metadata inventories, not file-existence audits.
  Directory lists identify nonempty `.mbz` files, not archive integrity or
  whether the archive was really produced by an automated task. The directory
  name represents Moodle's configured automated-backup destination.
- Historical `block_disk_quota_measurement` rows are retained. Rows predating
  this change describe the old logical metric and should not be compared
  directly to new physical totals. The new current display uses only the new
  snapshot configuration key and does not rewrite history.

## Validation performed while preparing the series

- PHP 8.3 syntax parsing for every PHP file in the patched plugin.
- Standalone regression suite executed on PHP 8.3.33 via WebAssembly with SQLite
  fixtures. Its native hard-link inode check is explicitly skipped there because
  the WebAssembly filesystem remaps host inode IDs.
- Focused template checks for escaping and the extra user column.
- Clean sequential application of all four patches to the fork's base commit,
  followed by byte comparisons with the final source tree.

A native Moodle/PHPUnit run, real PostgreSQL/MariaDB size query, and interactive
checks in your Docker instance remain to be performed. No remote commits,
containers, databases, or site configuration were changed while preparing this
bundle.

## Primary implementation references

- Moodle `backup_helper::store_backup_file()` on `MOODLE_502_STABLE`:
  https://github.com/moodle/moodle/blob/MOODLE_502_STABLE/public/backup/util/helper/backup_helper.class.php
- PostgreSQL database object size functions:
  https://www.postgresql.org/docs/current/functions-admin.html#FUNCTIONS-ADMIN-DBSIZE
- MariaDB information schema table size metadata:
  https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-tables-table
