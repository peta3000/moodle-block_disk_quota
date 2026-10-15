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
 * Strings for component 'block_disk_quota', language 'de'
 *
 * @package   block_recent_activity
 * @copyright 2015 Liip AG {@link http://liip.ch}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Liip Nutzung Auslastung';
$string['disk_quota:addinstance'] = 'Neuen Speicherplatz-Auslastungs-Block hinzufügen';
$string['disk_quota:myaddinstance'] = 'Neuen Speicherplatz-Auslastungs-Block zu Meine Startseite (Dashboard) hinzufügen';
$string['disk_quota:viewblock'] = 'Speicherplatz-Auslastungs-Block ansehen';
$string['disk_quota:viewusage'] = 'Detail-Seite Speicherplatz-Auslastungs-Block ansehen';
$string['err_cannot_uninstall_plugin'] = 'Dieses Plugin kann nicht entfernt werden';
$string['quota_used'] = '{$a->used} von {$a->quota} GB benutzt';
$string['gigabytes_used'] = 'GB benutzt';
$string['active_users'] = '{$a->activeusers} aktive Benutzer ({$a->quota} erlaubt)';

// Tasks.
$string['task_get_disk_usage'] = 'Verwendeten Speicherplatz anzeigen';

// Settings strings.
$string['quota_gb'] = 'Speicherplatz-Limite';
$string['quota_gb_desc'] = 'Speicherplatz-Limite in Gigabyte';
$string['quota_activeusers'] = 'Active Benutzer-Limite';
$string['quota_activeusers_desc'] = 'Active Benutzer-Limite (verbunden in den letzten 365 Tagen)';

$string['backup_filename'] = 'Dateiname';
$string['backup_course'] = 'Kurs';
$string['backup_timemodified'] = 'Zuletzt geändert';
$string['backup_size'] = 'Größe';
$string['backup_page_title'] = 'Details zur Sicherung';

$string['backup_storage'] = 'Speicherort';
$string['backup_source_filearea'] = 'Moodle-Dateibereich';
$string['backup_source_directory'] = 'Verzeichnis für automatische Sicherungen';
$string['backup_course_unavailable'] = 'Nicht ermittelt';
$string['backup_directory_unreadable'] = 'Das konfigurierte Verzeichnis für automatische Sicherungen oder ein Unterverzeichnis konnte nicht gelesen werden. Prüfen Sie den Pfad und die Zugriffsrechte und laden Sie den Bericht erneut.';

$string['storage_not_measured'] = 'noch nicht gemessen';
$string['storage_run_task'] = 'Es liegt noch keine Speichermessung vor. Führen Sie den geplanten Task zur Speicherbelegung aus.';
$string['storage_source'] = 'Speicherquelle';
$string['storage_database'] = 'Datenbank';
$string['storage_moodledata'] = 'Moodledata';
$string['storage_code'] = 'Moodle-Code';
$string['storage_total'] = 'Gesamte Speicherbelegung';
$string['storage_directory'] = 'Verzeichnis / Dateien im Stammverzeichnis';
$string['storage_root_files'] = 'Dateien und symbolische Links im Stammverzeichnis sowie Verzeichnismetadaten';
$string['storage_measured_at'] = 'Gemessen am: {$a}';
$string['storage_measurement_note'] = 'Dezimale GB. Dateisystemgrössen basieren auf belegten Blöcken. Symbolischen Links wird nicht gefolgt, harte Links werden einmal gezählt. Die Datenbankgrösse stammt vom Datenbankserver, ohne Serverprotokolle und gemeinsam genutzte Infrastruktur.';
$string['storage_database_estimate'] = 'Die Datenbankgrösse unter MariaDB/MySQL ist eine Schätzung des Servers für Tabellendaten und Indizes.';
$string['content_types'] = 'Inhaltstypen';
$string['content_types_note'] = 'Logische Inhaltsgrössen aus den Moodle-Dateieinträgen, ohne Prüfung der physischen Dateien. Diese Werte werden nicht zur Speicherbelegung addiert. Sicherungen im separaten Verzeichnis erscheinen in den Sicherungskategorien, nicht in diesen MIME-Typ-Werten.';
