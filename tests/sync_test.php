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

use local_memovia\local\client;
use local_memovia\local\sync;

/**
 * What goes to MEMOVIA, and one pass with a fake client.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_memovia\local\sync
 */
final class sync_test extends \advanced_testcase {
    /**
     * A MEMOVIA LTI 1.3 tool at site level.
     *
     * @param string $baseurl tool base URL
     * @return \stdClass lti_types record
     */
    private function tool(string $baseurl = 'https://app.memovia.io/lti/launch'): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_lti');
        $id = $generator->create_tool_types(['name' => 'MEMOVIA', 'baseurl' => $baseurl]);
        $DB->set_field('lti_types', 'ltiversion', '1.3.0', ['id' => $id]);
        $DB->set_field('lti_types', 'clientid', 'client-' . $id, ['id' => $id]);
        $DB->set_field('lti_types', 'state', 1, ['id' => $id]);
        return $DB->get_record('lti_types', ['id' => $id]);
    }

    public function test_course_files_only_visible_to_all_and_accepted_types(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'cours.pdf']);
        $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'cache.pdf', 'visible' => 0]);
        $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'restreint.pdf',
            'availability' => '{"op":"&","c":[{"type":"date","d":">=","t":' . (time() + DAYSECS) . '}],"showc":[true]}']);
        $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'photo.png']);
        $stealth = $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'stealth.pdf']);
        set_config('allowstealth', 1);
        set_coursemodule_visible($stealth->cmid, 1, 0); // Accessible by link but not shown on the course page.
        $this->assertSame('0', (string) $DB->get_field('course_modules', 'visibleoncoursepage', ['id' => $stealth->cmid]));
        $files = sync::course_files($DB->get_record('course', ['id' => $course->id]));
        $this->assertSame(['cours.pdf'], array_map(fn($f) => $f['file']->get_filename(), $files));
        $this->assertSame('cours.pdf', $files[0]['file']->get_filename());
        $this->assertSame('', $files[0]['filekey']);
        // Hidden course: nothing.
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
        $this->assertCount(0, sync::course_files($DB->get_record('course', ['id' => $course->id])));
    }

    public function test_tool_and_activation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->assertNull(sync::tool_for_course($course));
        $other = $this->tool('https://other-tool.example/lti/launch');
        $gen->create_module('lti', ['course' => $course->id, 'typeid' => $other->id, 'toolurl' => '']);
        $this->assertNull(sync::tool_for_course($course), 'a tool of another vendor is never used');
        $tool = $this->tool();
        $this->assertNull(sync::tool_for_course($course), 'no MEMOVIA activity in the course: no tool');
        $this->assertNull(sync::activation_cm($course, $tool), 'no MEMOVIA activity: nothing is sent');
        // An activity placed by Deep Linking (memovia_link) is never the activation one: its launch opens its own content.
        $linked = $gen->create_module('lti', ['course' => $course->id, 'typeid' => $tool->id, 'toolurl' => '',
            'instructorcustomparameters' => 'memovia_link=11111111-1111-4111-8111-111111111111']);
        $this->assertSame($tool->id, sync::tool_for_course($course)->id, 'the tool of the activity in the course');
        $this->assertNull(sync::activation_cm($course, $tool), 'only a placed content: no activation activity');
        $lti = $gen->create_module('lti', ['course' => $course->id, 'typeid' => $tool->id, 'toolurl' => '']);
        $this->assertSame((int) $lti->cmid, (int) sync::activation_cm($course, $tool)->id);
        $this->assertSame([(int) $course->id], array_map('intval', array_keys(sync::activated_courses())));
    }

    public function test_run_course_pushes_changed_files_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $res = $gen->create_module('resource', ['course' => $course->id, 'defaultfilename' => 'cours.pdf']);
        $client = new class('https://app.memovia.io', 'c') extends client {
            /** @var array calls */
            public $calls = [];
            protected function call(array $params, bool $multipart = false): array {
                $this->calls[] = $params['action'];
                if ($params['action'] === 'status') {
                    $files = array_map(
                        fn($f) => $f + ['status' => null, 'note_id' => null, 'needs_push' => true],
                        $params['files']
                    );
                    return ['class' => 'ok', 'files' => $files];
                }
                return ['note_id' => '11111111-1111-4111-8111-111111111111', 'status' => 'pending'];
            }
        };
        $r = sync::run_course($DB->get_record('course', ['id' => $course->id]), $client);
        $this->assertSame(1, $r['pushed']);
        $this->assertSame(['status', 'ingest'], $client->calls);
        $row = $DB->get_record('local_memovia_file', ['cmid' => $res->cmid]);
        $this->assertSame('pending', $row->status);
        // Nothing changed but a file is still being adapted: MEMOVIA is asked again (status only).
        $client->calls = [];
        sync::run_course($DB->get_record('course', ['id' => $course->id]), $client);
        $this->assertSame('status', $client->calls[0]);
        // Ready and unchanged: no call at all within the hour.
        $DB->set_field('local_memovia_file', 'status', 'ready', ['id' => $row->id]);
        $client->calls = [];
        $r = sync::run_course($DB->get_record('course', ['id' => $course->id]), $client);
        $this->assertSame([], $client->calls);
        $this->assertSame('cached', $r['class']);
        // Another MEMOVIA tool (preview to production, new registration): MEMOVIA is asked again at once.
        $other = new class('https://app.memovia.io', 'other-client') extends client {
            /** @var array calls */
            public $calls = [];
            protected function call(array $params, bool $multipart = false): array {
                $this->calls[] = $params['action'];
                return ['class' => 'NO_CLASS_YET', 'files' => []];
            }
        };
        sync::run_course($DB->get_record('course', ['id' => $course->id]), $other);
        $this->assertSame(['status'], $other->calls);
    }

    /**
     * Regional Moodle: one MEMOVIA tool per CFA, restricted to its category. The tool used for a course is the one
     * whose activity is in the course (Moodle launches that one, whatever the category); the exact category breaks a
     * tie; two tools and no way to choose: none (nothing is sent to the wrong CFA).
     */
    public function test_tool_for_course_regional(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $cata = $gen->create_category(['name' => 'CFA A']);
        $catb = $gen->create_category(['name' => 'CFA B']);
        $catc = $gen->create_category(['name' => 'Sub of B', 'parent' => $catb->id]);
        $toola = $this->tool();
        $toolb = $this->tool();
        $DB->insert_record('lti_types_categories', ['typeid' => $toola->id, 'categoryid' => $cata->id]);
        $DB->insert_record('lti_types_categories', ['typeid' => $toolb->id, 'categoryid' => $catb->id]);
        $inb = $gen->create_course(['category' => $catb->id]);
        $gen->create_module('lti', ['course' => $inb->id, 'typeid' => $toolb->id, 'toolurl' => '']);
        $this->assertSame($toolb->id, sync::tool_for_course($inb)->id, 'course of CFA B: tool B');
        $both = $gen->create_course(['category' => $catb->id]);
        $gen->create_module('lti', ['course' => $both->id, 'typeid' => $toola->id, 'toolurl' => '']);
        $gen->create_module('lti', ['course' => $both->id, 'typeid' => $toolb->id, 'toolurl' => '']);
        $this->assertSame($toolb->id, sync::tool_for_course($both)->id, 'two tools in a course of B: the one of category B');
        $sub = $gen->create_course(['category' => $catc->id]);
        $gen->create_module('lti', ['course' => $sub->id, 'typeid' => $toolb->id, 'toolurl' => '']);
        $this->assertSame($toolb->id, sync::tool_for_course($sub)->id, 'sub-category: the tool of its activity');
        $gen->create_module('lti', ['course' => $sub->id, 'typeid' => $toola->id, 'toolurl' => '']);
        $this->assertNull(sync::tool_for_course($sub), 'two tools, none restricted to this exact category: none');
        $this->assertCount(2, sync::tools_in_course($sub));
        $none = $gen->create_course(['category' => $cata->id]);
        $this->assertNull(sync::tool_for_course($none), 'no activity: none');
    }
}
