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

namespace local_memovia\local;

/**
 * Which files of a course go to MEMOVIA, and one sync pass for a course.
 *
 * Only files visible to EVERYONE are sent: visible course, visible activity shown on the course page, no access
 * restriction on the activity or its section. A hidden or restricted file (an exam answer key opening on a date)
 * never leaves Moodle. Types: PDF, DOCX, PPTX (no images: photos of learners, attendance sheets).
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync {
    /** @var string[] accepted MIME types */
    const MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    /** @var int same limit as MEMOVIA: a larger file is never uploaded */
    const MAX_BYTES = 18 * 1024 * 1024;

    /**
     * MEMOVIA address from the settings, without trailing slash.
     *
     * @return string
     */
    public static function apiurl(): string {
        return rtrim((string) (get_config('local_memovia', 'apiurl') ?: 'https://app.memovia.io'), '/');
    }

    /**
     * SQL fragment selecting the MEMOVIA tools of the site (LTI 1.3, site level, configured, base URL on apiurl).
     *
     * @param string $alias table alias of lti_types
     * @return array [sql, params]
     */
    private static function tools_sql(string $alias): array {
        global $DB;
        $like = $DB->sql_like("{$alias}.baseurl", ':memoviabase');
        return ["{$alias}.state = :memoviastate AND {$alias}.course = :memoviasite AND {$alias}.ltiversion = :memoviav
                 AND {$alias}.clientid IS NOT NULL AND $like",
            ['memoviastate' => 1, 'memoviasite' => SITEID, 'memoviav' => '1.3.0',
             'memoviabase' => $DB->sql_like_escape(self::apiurl()) . '/%']];
    }

    /**
     * MEMOVIA tools that have an activity in the course.
     *
     * @param \stdClass $course course record
     * @return \stdClass[] lti_types records, by id
     */
    public static function tools_in_course(\stdClass $course): array {
        global $DB;
        [$sql, $params] = self::tools_sql('t');
        return $DB->get_records_sql("SELECT t.* FROM {lti_types} t
             WHERE $sql AND EXISTS (SELECT 1 FROM {lti} l WHERE l.typeid = t.id AND l.course = :memoviacourse)
          ORDER BY t.id", $params + ['memoviacourse' => $course->id]);
    }

    /**
     * The MEMOVIA tool of a course (regional Moodle: one tool per CFA, each restricted to its category).
     *
     * The tool whose activity is in the course: Moodle launches that one whatever the category (a restriction only
     * filters the activity chooser, on the EXACT category, mod/lti/locallib.php lti_get_lti_types_by_course), so its
     * classes and accounts live in that CFA. Several: the one restricted to the exact category of the course, if it is
     * the only one; otherwise none, and nothing is sent to a CFA that may not be the right one.
     *
     * @param \stdClass $course course record
     * @return \stdClass|null lti_types record
     */
    public static function tool_for_course(\stdClass $course): ?\stdClass {
        global $DB;
        $tools = self::tools_in_course($course);
        if (count($tools) <= 1) {
            return $tools ? reset($tools) : null;
        }
        $mine = array_filter($tools, fn($t) => $DB->record_exists('lti_types_categories',
            ['typeid' => $t->id, 'categoryid' => $course->category]));
        return count($mine) === 1 ? reset($mine) : null;
    }

    /**
     * The visible MEMOVIA activity of the course that carries no placed content (memovia_link).
     *
     * @param \stdClass $course course record
     * @param \stdClass $tool lti_types record
     * @return \cm_info|null
     */
    public static function activation_cm(\stdClass $course, \stdClass $tool): ?\cm_info {
        global $DB;
        $ltis = $DB->get_records('lti', ['course' => $course->id, 'typeid' => $tool->id], 'id', 'id, instructorcustomparameters');
        if (!$ltis) {
            return null;
        }
        foreach (get_fast_modinfo($course)->get_instances_of('lti') as $instanceid => $cm) {
            if (!isset($ltis[$instanceid]) || !$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            // Never an activity placed by Deep Linking: its launch opens its own content, not the clicked file.
            if (strpos((string) $ltis[$instanceid]->instructorcustomparameters, 'memovia_link') === false) {
                return $cm;
            }
        }
        return null;
    }

    /**
     * Visible to every participant of the course, without restriction.
     *
     * @param \cm_info $cm course module
     * @return bool
     */
    public static function visible_to_all(\cm_info $cm): bool {
        $section = $cm->get_section_info();
        return $cm->visible && $cm->visibleoncoursepage && empty($cm->availability) && !$cm->deletioninprogress
            && $section->visible && empty($section->availability);
    }

    /**
     * Candidate files: main file of each resource, every file of each folder, visible to all, accepted type.
     *
     * @param \stdClass $course course record
     * @return array [['cm' => cm_info, 'file' => stored_file, 'filekey' => string], ...]
     */
    public static function course_files(\stdClass $course): array {
        if (empty($course->visible)) {
            return [];
        }
        $fs = get_file_storage();
        $out = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if (!in_array($cm->modname, ['resource', 'folder'], true) || !self::visible_to_all($cm)) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            $files = $fs->get_area_files($context->id, 'mod_' . $cm->modname, 'content', 0, 'sortorder DESC, id ASC', false);
            if ($cm->modname === 'resource') {
                $files = array_slice($files, 0, 1); // The main file only (mod/resource/lib.php), not its attachments.
            }
            foreach ($files as $file) {
                if (!in_array($file->get_mimetype(), self::MIMES, true)) {
                    continue;
                }
                $key = $cm->modname === 'resource' ? '' : $file->get_filepath() . $file->get_filename();
                $out[] = ['cm' => $cm, 'file' => $file, 'filekey' => $key];
            }
        }
        return $out;
    }

    /**
     * LTI context id of the course: mod_lti sends the course id (lti_build_request).
     *
     * @param \stdClass $course course record
     * @return string
     */
    public static function context_id(\stdClass $course): string {
        return (string) $course->id;
    }

    /**
     * Courses that contain an activity of a MEMOVIA tool.
     *
     * @return \stdClass[]
     */
    public static function activated_courses(): array {
        global $DB;
        [$sql, $params] = self::tools_sql('t');
        return $DB->get_records_sql("SELECT c.* FROM {course} c
             WHERE c.id <> :memoviasiteid AND EXISTS (
                   SELECT 1 FROM {lti} l JOIN {lti_types} t ON t.id = l.typeid WHERE l.course = c.id AND $sql)
          ORDER BY c.id", $params + ['memoviasiteid' => SITEID]);
    }

    /**
     * One pass on a course: status (complete list) then upload of what changed. Asks MEMOVIA only when the list
     * changed, a file is being adapted, or once an hour.
     *
     * @param \stdClass $course course record
     * @param client $client MEMOVIA client
     * @param bool $force ask MEMOVIA even if nothing changed locally
     * @return array ['pushed' => n, 'skipped' => n, 'errors' => n, 'class' => 'ok'|'NO_CLASS_YET'|'cached']
     */
    public static function run_course(\stdClass $course, client $client, bool $force = false): array {
        global $DB;
        $result = ['pushed' => 0, 'skipped' => 0, 'errors' => 0, 'class' => 'cached'];
        $contextid = self::context_id($course);
        $byk = [];
        $list = [];
        foreach (self::course_files($course) as $f) {
            $byk[$f['cm']->id . ':' . $f['filekey']] = $f;
            $list[] = ['cm_id' => (int) $f['cm']->id, 'file_key' => $f['filekey'], 'contenthash' => $f['file']->get_contenthash()];
        }
        $fingerprint = sha1($client->tool_key() . json_encode($list)); // Another tool or address: asked again at once.
        $state = $DB->get_record('local_memovia_course', ['courseid' => $course->id]);
        $waiting = $DB->record_exists_select('local_memovia_file',
            "courseid = ? AND status IN ('pending', 'processing')", [$course->id]);
        if (!$force && $state && $state->fingerprint === $fingerprint && $state->classstatus === 'ok' && !$waiting
                && $state->lastcheck > time() - HOURSECS) {
            $result['skipped'] = count($list);
            return $result;
        }

        $answer = $client->status($contextid, $list);
        $record = $state ?: (object) ['courseid' => $course->id, 'eventscursor' => ''];
        $record->classstatus = ($answer['class'] ?? '') === 'ok' ? 'ok' : 'NO_CLASS_YET';
        $record->fingerprint = $fingerprint;
        $record->lastcheck = time();
        if ($state) {
            $DB->update_record('local_memovia_course', $record);
        } else {
            $DB->insert_record('local_memovia_course', $record);
        }
        $result['class'] = $record->classstatus;
        if ($record->classstatus !== 'ok') {
            return $result;
        }

        $local = [];
        foreach ($DB->get_records('local_memovia_file', ['courseid' => $course->id]) as $row) {
            $local[$row->cmid . ':' . $row->filekey] = $row;
        }
        $title = format_string($course->fullname, true, ['context' => \context_course::instance($course->id), 'escape' => false]);
        $seen = [];
        foreach ($answer['files'] ?? [] as $a) {
            $k = ((int) $a['cm_id']) . ':' . (string) $a['file_key'];
            if (!isset($byk[$k])) {
                continue;
            }
            $seen[$k] = true;
            $file = $byk[$k]['file'];
            $row = $local[$k] ?? (object) [
                'courseid' => $course->id, 'cmid' => (int) $a['cm_id'], 'filekey' => (string) $a['file_key'],
            ];
            $row->contenthash = $file->get_contenthash();
            $row->status = $a['status'] ?? 'pending';
            $row->noteid = $a['note_id'] ?? null;
            $row->error = isset($a['error']) ? substr((string) $a['error'], 0, 255) : null;
            if (!empty($a['needs_push'])) {
                if ($file->get_filesize() > self::MAX_BYTES) {
                    $row->status = 'too_large';
                    $row->error = 'TOO_LARGE';
                    $result['skipped']++;
                } else {
                    try {
                        $cm = $byk[$k]['cm'];
                        // Folder file: MEMOVIA titles it from the file name.
                        $cmcontext = \context_module::instance($cm->id);
                        $notetitle = $cm->modname === 'resource'
                            ? format_string($cm->name, true, ['context' => $cmcontext, 'escape' => false]) : '';
                        $r = $client->ingest($contextid, $title, (int) $a['cm_id'], (string) $a['file_key'], $file, $notetitle);
                        $row->status = $r['status'] ?? 'pending';
                        $row->noteid = $r['note_id'] ?? $row->noteid;
                        $row->error = null;
                        $result['pushed']++;
                    } catch (\moodle_exception $e) {
                        $row->status = 'failed';
                        $row->error = substr((string) $e->debuginfo, 0, 255);
                        $result['errors']++;
                    }
                }
            } else {
                $result['skipped']++;
            }
            $row->timesynced = time();
            if (isset($row->id)) {
                $DB->update_record('local_memovia_file', $row);
            } else {
                $DB->insert_record('local_memovia_file', $row);
            }
        }
        foreach ($local as $k => $row) {
            if (!isset($seen[$k]) && $row->status !== 'hidden') {
                $DB->set_field('local_memovia_file', 'status', 'hidden', ['id' => $row->id]);
            }
        }
        return $result;
    }

    /**
     * New MEMOVIA contents of the class (notes shared by the teacher, contents published) since the last pass: ONE
     * summary message per active learner of the course (not to teachers), through Moodle messaging (web and e-mail
     * according to each user's preferences). First pass: only the cursor is stored, no history is sent.
     *
     * @param \stdClass $course course record
     * @param client $client MEMOVIA client
     * @param \cm_info $activation the course's MEMOVIA activity (the links open through it)
     * @return int messages sent
     */
    public static function notify_course(\stdClass $course, client $client, \cm_info $activation): int {
        global $DB;
        $state = $DB->get_record('local_memovia_course', ['courseid' => $course->id]);
        $cursor = $state ? (string) $state->eventscursor : '';
        $answer = $client->events(self::context_id($course), $cursor);
        $events = $cursor === '' ? [] : array_values(array_filter($answer['events'] ?? [],
            fn($e) => is_array($e) && preg_match('/^[0-9a-f-]{36}$/', (string) ($e['note_id'] ?? ''))));
        $next = substr((string) ($answer['cursor'] ?? ''), 0, 40);
        if ($next !== '' && $next !== $cursor) {
            $record = $state ?: (object) ['courseid' => $course->id, 'classstatus' => '', 'fingerprint' => '', 'lastcheck' => 0];
            $record->eventscursor = $next;
            if ($state) {
                $DB->update_record('local_memovia_course', $record);
            } else {
                $DB->insert_record('local_memovia_course', $record);
            }
        }
        if (!$events) {
            return 0;
        }
        $context = \context_course::instance($course->id);
        // Plain text, s() for HTML.
        $coursename = format_string($course->fullname, true, ['context' => $context, 'escape' => false]);
        $items = [];
        $lines = [];
        foreach (array_slice($events, 0, 20) as $e) {
            $url = new \moodle_url('/local/memovia/open.php', ['cmid' => $activation->id, 'note' => $e['note_id']]);
            $title = \core_text::substr(trim(strip_tags((string) ($e['title'] ?? ''))), 0, 200); // MEMOVIA title: plain text.
            $label = get_string($e['kind'] === 'published' ? 'notify_published' : 'notify_note', 'local_memovia', $title);
            $items[] = \html_writer::tag('li', \html_writer::link($url, s($label)));
            $lines[] = '- ' . $label . ' : ' . $url->out(false);
        }
        $first = new \moodle_url('/local/memovia/open.php', ['cmid' => $activation->id, 'note' => $events[0]['note_id']]);
        $subject = get_string('notify_subject', 'local_memovia', $coursename);
        $sent = 0;
        foreach (get_enrolled_users($context, 'local/memovia:open', 0, 'u.*', null, 0, 0, true) as $user) {
            if (has_capability('moodle/course:update', $context, $user)) {
                continue; // Teachers share these contents themselves.
            }
            $message = new \core\message\message();
            $message->component = 'local_memovia';
            $message->name = 'newcontent';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = get_string('notify_intro', 'local_memovia', $coursename) . "\n\n" . implode("\n", $lines);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s(get_string('notify_intro', 'local_memovia', $coursename)))
                . \html_writer::tag('ul', implode('', $items));
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $first->out(false);
            $message->contexturlname = get_string('open', 'local_memovia');
            $message->courseid = $course->id;
            if (message_send($message)) {
                $sent++;
            }
        }
        return $sent;
    }
}
