<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'MEMOVIA';
$string['enabled'] = 'Send course files to MEMOVIA';
$string['enabled_desc'] = 'Every 5 minutes, the PDF, DOCX and PPTX files that are visible to everyone in courses containing a MEMOVIA activity are sent to MEMOVIA, adapted and shared with the class. Hidden or restricted files are never sent. Untick to stop (end of contract).';
$string['apiurl'] = 'MEMOVIA address';
$string['apiurl_desc'] = 'Must be the address of the MEMOVIA external tool registered on this site (LTI 1.3).';
$string['memovia:open'] = 'Open a course file with MEMOVIA';
$string['task_sync_files'] = 'Send course files to MEMOVIA';
$string['open'] = 'Open with MEMOVIA';
$string['open_title'] = 'Open this file in MEMOVIA, adapted for reading (opens a new tab)';
$string['notadapted'] = 'Not adapted';
$string['inprogress'] = 'Being adapted';
$string['banner_noclass'] = 'MEMOVIA: open the MEMOVIA activity of this course once to start adapting its files.';
$string['banner_ok'] = 'MEMOVIA is on · {$a} files adapted for the class';
$string['err_notready'] = 'This file is not adapted by MEMOVIA yet. Come back in a few minutes.';
$string['err_noactivity'] = 'This course has no visible MEMOVIA activity. Ask your teacher.';
$string['err_api'] = 'MEMOVIA could not be reached. Try again in a minute.';
$string['error_TOO_LARGE'] = 'file larger than 18 MB';
$string['error_MIME'] = 'file type not supported';
$string['error_DOCINTEL_REJECTED'] = 'file unreadable (protected or damaged)';
$string['error_EMPTY'] = 'no readable text (scanned image?)';
$string['error_DAILY_BUDGET'] = 'daily limit reached, retried automatically';
$string['error_other'] = 'adaptation failed';
$string['privacy:metadata:memovia'] = 'Course files visible to everyone in courses with a MEMOVIA activity are sent to MEMOVIA (EU) to be adapted for the class. When a user opens a file with MEMOVIA, their Moodle user id is sent to open the right file through the LTI launch.';
$string['privacy:metadata:memovia:userid'] = 'Moodle user id of the person who opens a file with MEMOVIA.';
$string['privacy:metadata:memovia:files'] = 'Course files (name and content) visible to everyone.';
$string['privacy:metadata:azure'] = 'MEMOVIA reads the text of the course files with Microsoft Azure Document Intelligence (EU region), its processor.';
$string['privacy:metadata:azure:files'] = 'Content of the course files, to extract their text.';
$string['error_TOO_MANY_ATTEMPTS'] = 'adaptation failed several times';
$string['error_TOO_MANY_PAGES'] = 'more than 300 pages';
$string['banner_twotools'] = 'MEMOVIA: this course has activities of two MEMOVIA tools (two institutions). Nothing is sent while both remain: keep only your institution\'s tool, or restrict each tool to its institution\'s category.';
$string['notify_subject'] = 'New in MEMOVIA: {$a}';
$string['notify_intro'] = 'Your teacher added new content in MEMOVIA for the course "{$a}":';
$string['notify_note'] = '{$a}';
$string['notify_published'] = '{$a} (published by your teacher)';
$string['open_confirm'] = 'Open this content in MEMOVIA?';
$string['messageprovider:newcontent'] = 'New MEMOVIA contents of the class';
