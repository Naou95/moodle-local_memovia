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

namespace local_memovia\task;

use local_memovia\local\client;
use local_memovia\local\sync;

/**
 * Every 5 minutes: send the new or changed files of the courses with a MEMOVIA activity.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_files extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_files', 'local_memovia');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;
        if (!get_config('local_memovia', 'enabled')) {
            mtrace('local_memovia: disabled (Site administration > Plugins > Local plugins > MEMOVIA).');
            return;
        }
        foreach (sync::activated_courses() as $course) {
            $tool = sync::tool_for_course($course);
            if (!$tool || !sync::activation_cm($course, $tool)) {
                continue;
            }
            try {
                $client = new client(sync::apiurl(), $tool->clientid);
                $r = sync::run_course($course, $client);
                $sent = $DB->get_field('local_memovia_course', 'classstatus', ['courseid' => $course->id]) === 'ok'
                    ? sync::notify_course($course, $client, sync::activation_cm($course, $tool)) : 0;
                mtrace("local_memovia: course {$course->id} class={$r['class']} pushed={$r['pushed']} skipped={$r['skipped']}"
                    . " errors={$r['errors']} notified={$sent}");
            } catch (\moodle_exception $e) {
                mtrace("local_memovia: course {$course->id} error " . $e->debuginfo);
            }
        }
    }
}
