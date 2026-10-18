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

namespace local_memovia;

use local_memovia\local\sync;

/**
 * "Open with MEMOVIA" buttons and status banner on the course page and on a folder page.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Adds the data (JSON) and the script that places the buttons.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $DB;
        if (!get_config('local_memovia', 'enabled') || during_initial_install()) {
            return;
        }
        $isfolder = $PAGE->pagetype === 'mod-folder-view';
        if (strpos((string) $PAGE->pagetype, 'course-view-') !== 0 && !$isfolder) {
            return;
        }
        $course = $PAGE->course;
        if (empty($course->id) || $course->id == SITEID) {
            return;
        }
        $teacher = has_capability('moodle/course:update', \context_course::instance($course->id));
        $tool = sync::tool_for_course($course);
        if (!$tool && $teacher && !$isfolder && count(sync::tools_in_course($course)) > 1) {
            // Regional Moodle: two MEMOVIA tools (two CFA) in this course and no way to choose: nothing is sent.
            $hook->add_html(\html_writer::div(get_string('banner_twotools', 'local_memovia'),
                'alert alert-warning local-memovia-banner'));
            return;
        }
        if (!$tool || !sync::activation_cm($course, $tool)) {
            return;
        }
        $files = [];
        $ready = 0;
        foreach ($DB->get_records('local_memovia_file', ['courseid' => $course->id]) as $row) {
            if ($row->status === 'hidden' || ($isfolder && (int) $row->cmid !== (int) ($PAGE->cm->id ?? 0))) {
                continue;
            }
            $ready += $row->status === 'ready' ? 1 : 0;
            if ($row->status !== 'ready' && !$teacher) {
                continue; // Learners only see the buttons.
            }
            $reason = '';
            if (in_array($row->status, ['failed', 'too_large'], true)) {
                $code = 'error_' . ($row->error ?: 'other');
                $reason = get_string_manager()->string_exists($code, 'local_memovia') ? get_string($code, 'local_memovia')
                    : get_string('error_other', 'local_memovia');
            }
            $files[$row->cmid][$row->filekey] = ['status' => $row->status, 'reason' => $reason];
        }
        $banner = '';
        if ($teacher && !$isfolder) {
            $state = $DB->get_record('local_memovia_course', ['courseid' => $course->id]);
            $banner = ($state && $state->classstatus === 'ok') ? get_string('banner_ok', 'local_memovia', $ready)
                : get_string('banner_noclass', 'local_memovia');
        }
        $data = [
            'openurl' => (new \moodle_url('/local/memovia/open.php'))->out(false),
            'sesskey' => sesskey(),
            'page' => $isfolder ? 'folder' : 'course',
            'files' => (object) $files,
            'banner' => $banner,
            'labels' => [
                'open' => get_string('open', 'local_memovia'),
                'title' => get_string('open_title', 'local_memovia'),
                'notadapted' => get_string('notadapted', 'local_memovia'),
                'inprogress' => get_string('inprogress', 'local_memovia'),
            ],
        ];
        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsurl = new \moodle_url('/local/memovia/js/buttons.js', ['v' => get_config('local_memovia', 'version')]);
        $hook->add_html(\html_writer::tag('script', $json, ['type' => 'application/json', 'id' => 'local-memovia-data'])
            . \html_writer::script('', $jsurl));
    }
}
