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

use core\hook\output\before_footer_html_generation;

/**
 * The buttons data is added only in a course with a MEMOVIA activity, and only for adapted files (learner).
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_memovia\hook_callbacks
 */
final class hook_test extends \advanced_testcase {
    /**
     * Runs the hook on the course page as the current user.
     *
     * @param \stdClass $course course
     * @return string html added
     */
    private function footer(\stdClass $course): string {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-view-topics');
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $hook = new before_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_footer($hook);
        return $hook->get_output();
    }

    public function test_buttons_data(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_memovia');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->assertSame('', $this->footer($course), 'no MEMOVIA activity: nothing added');

        $this->setAdminUser();
        $id = $gen->get_plugin_generator('mod_lti')->create_tool_types(
            ['name' => 'MEMOVIA', 'baseurl' => 'https://app.memovia.io/lti/launch']);
        $DB->set_field('lti_types', 'ltiversion', '1.3.0', ['id' => $id]);
        $DB->set_field('lti_types', 'clientid', 'client-x', ['id' => $id]);
        $DB->set_field('lti_types', 'state', 1, ['id' => $id]);
        $gen->create_module('lti', ['course' => $course->id, 'typeid' => $id, 'toolurl' => '']);
        $DB->insert_record('local_memovia_file', (object) [
            'courseid' => $course->id, 'cmid' => 501, 'filekey' => '', 'contenthash' => str_repeat('a', 40),
            'status' => 'ready', 'noteid' => 'n1', 'timesynced' => time(),
        ]);
        $DB->insert_record('local_memovia_file', (object) [
            'courseid' => $course->id, 'cmid' => 502, 'filekey' => '', 'contenthash' => str_repeat('b', 40),
            'status' => 'failed', 'error' => 'EMPTY', 'timesynced' => time(),
        ]);
        $DB->insert_record('local_memovia_file', (object) [
            'courseid' => $course->id, 'cmid' => 503, 'filekey' => '', 'contenthash' => str_repeat('c', 40),
            'status' => 'hidden', 'noteid' => 'n3', 'timesynced' => time(),
        ]);

        $this->setUser($student);
        $html = $this->footer($course);
        $this->assertMatchesRegularExpression('/id="local-memovia-data"/', $html);
        preg_match('#<script type="application/json" id="local-memovia-data">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertSame(['501'], array_map('strval', array_keys($data['files'])),
            'learner: adapted files only, never hidden ones');
        $this->assertSame('', $data['banner']);

        set_config('enabled', 0, 'local_memovia');
        $this->assertSame('', $this->footer($course), 'disabled: nothing added');
    }
}
