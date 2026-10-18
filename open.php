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
 * "Open with MEMOVIA": leaves an intent at MEMOVIA (60 s, this user, this course, this file), then starts the
 * normal LTI launch of the course's MEMOVIA activity. Moodle judges the access (require_login, visibility),
 * the LTI launch proves the identity: no token is made here.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_memovia\local\client;
use local_memovia\local\sync;

$cmid = required_param('cmid', PARAM_INT);
$key = optional_param('key', '', PARAM_RAW_TRIMMED);
$note = optional_param('note', '', PARAM_ALPHANUMEXT); // Lot B: link of a notification (a content shared to the class).

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/memovia:open', $context);
$PAGE->set_url(new moodle_url('/local/memovia/open.php', ['cmid' => $cmid] + ($note !== '' ? ['note' => $note] : [])));
$back = new moodle_url('/course/view.php', ['id' => $course->id]);
$tool = sync::tool_for_course($course);
$activation = $tool ? sync::activation_cm($course, $tool) : null;
if (!$activation) {
    throw new moodle_exception('err_noactivity', 'local_memovia', $back);
}

if ($note !== '') {
    // A notification link carries no sesskey (it comes from an e-mail): a confirmation page posts it. The link only works
    // through the course's MEMOVIA activity, and MEMOVIA only accepts a note shared to this course's class (intent).
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
    if (!preg_match($uuid, $note) || (int) $activation->id !== (int) $cm->id
            || !get_config('local_memovia', 'enabled') || !\core_availability\info_module::is_user_visible($cm, $USER->id)) {
        throw new moodle_exception('err_notready', 'local_memovia', $back);
    }
    if (!data_submitted() || !confirm_sesskey()) {
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('open', 'local_memovia'));
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('open_confirm', 'local_memovia'),
            new single_button(
                new moodle_url('/local/memovia/open.php', ['cmid' => $cmid, 'note' => $note]),
                get_string('open', 'local_memovia'),
                'post',
                single_button::BUTTON_PRIMARY
            ),
            $back);
        echo $OUTPUT->footer();
        exit;
    }
    $noteid = $note;
} else {
    require_sesskey();
    if (!get_config('local_memovia', 'enabled') || !sync::visible_to_all($cm)
            || !\core_availability\info_module::is_user_visible($cm, $USER->id)) {
        throw new moodle_exception('err_notready', 'local_memovia', $back);
    }
    $row = $DB->get_record('local_memovia_file',
        ['courseid' => $course->id, 'cmid' => $cm->id, 'filekey' => $key, 'status' => 'ready']);
    if (!$row || empty($row->noteid)) {
        throw new moodle_exception('err_notready', 'local_memovia', $back);
    }
    $noteid = $row->noteid;
}
try {
    (new client(sync::apiurl(), $tool->clientid))->intent(sync::context_id($course), (string) $USER->id, $noteid);
} catch (moodle_exception $e) {
    throw new moodle_exception($e->debuginfo === 'NOT_FOUND' ? 'err_notready' : 'err_api', 'local_memovia', $back);
}
redirect(new moodle_url('/mod/lti/launch.php', ['id' => $activation->id]));
