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

/** Allocated filesystem bytes. No shell commands and no traversal of symbolic links. */
class filesystem_usage {
    /** @var array Inodes already charged, including hard links shared between roots. */
    private $seen = array();

    /**
     * Roots must be canonical, distinct directories. Exclusions are canonical roots
     * charged by another source. Breakdown includes root inode/root files as one row.
     */
    public function measure($root, array $exclusions = array()) {
        $breakdown = array();
        $rootbytes = $this->inode_bytes($root, $duplicate);
        if ($duplicate) {
            return array('path' => $root, 'bytes' => 0, 'breakdown' => array('.' => 0));
        }
        $entries = iterator_to_array(new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS));
        ksort($entries, SORT_STRING);
        foreach ($entries as $entry) {
            $path = $entry->getPathname();
            if (in_array($path, $exclusions, true)) {
                continue;
            }
            if ($entry->isDir() && !$entry->isLink()) {
                $breakdown[$entry->getFilename()] = $this->walk($path, $exclusions);
            } else {
                $rootbytes += $this->inode_bytes($path);
            }
        }
        $breakdown['.'] = $rootbytes;
        return array('path' => $root, 'bytes' => array_sum($breakdown), 'breakdown' => $breakdown);
    }

    private function walk($path, array $exclusions) {
        if (in_array($path, $exclusions, true)) {
            return 0;
        }
        $bytes = $this->inode_bytes($path, $duplicate);
        if ($duplicate) {
            return 0;
        }
        if (is_dir($path) && !is_link($path)) {
            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
                $bytes += $this->walk($entry->getPathname(), $exclusions);
            }
        }
        return $bytes;
    }

    private function inode_bytes($path, &$duplicate = false) {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || !isset($stat['blocks']) || $stat['blocks'] < 0) {
            throw new \RuntimeException('Cannot measure allocated filesystem bytes: ' . $path);
        }
        $key = $stat['dev'] . ':' . $stat['ino'];
        // Single-link files need no inode entry; this keeps large file pools affordable.
        $track = ($stat['mode'] & 0170000) === 0040000 || $stat['nlink'] > 1;
        $duplicate = $track && isset($this->seen[$key]);
        if ($duplicate) {
            return 0;
        }
        if ($track) {
            $this->seen[$key] = true;
        }
        // POSIX st_blocks uses 512-byte units, independent of filesystem block size.
        return (int)$stat['blocks'] * 512;
    }
}
