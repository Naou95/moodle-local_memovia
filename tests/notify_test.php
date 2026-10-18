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
 * Notifications of new MEMOVIA contents through Moodle messaging.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_memovia\local\sync::notify_course
 */
final class notify_test extends \advanced_testcase {
    /**
     * A fake MEMOVIA that answers events with what the test gives.
     *
     * @param array $events events to return
     * @return client
     */
    private function client(array $events): client {
        return new class('https://app.memovia.io', 'c', $events) extends client {
            /** @var array */
            public $events;
            /** @var array */
            public $calls = [];
            public function __construct(string $apiurl, string $clientid, array $events) {
                parent::__construct($apiurl, $clientid);
                $this->events = $events;
            }
            protected function call(array $params, bool $multipart = false): array {
                $this->calls[] = $params;
                return ['events' => $params['since'] === '' ? [] : $this->events, 'cursor' => '2026-10-18T13:00:00.123456+00:00'];
            }
        };
    }

    public function test_notify_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Messages need it.
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'BP Coiffure']);
        $student = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $suspended = $gen->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $cm = $gen->create_module('lti', ['course' => $course->id, 'toolurl' => '']);
        $activation = get_fast_modinfo($course)->get_cm($cm->cmid);
        $events = [
            ['kind' => 'note', 'note_id' => '11111111-1111-4111-8111-111111111111',
                'title' => 'Les colorations <script>x</script>', 'at' => '2026-10-18T12:00:00+00:00'],
            ['kind' => 'published', 'note_id' => '22222222-2222-4222-8222-222222222222',
                'title' => 'La permanente', 'at' => '2026-10-18T12:30:00+00:00'],
        ];

        // First pass: the cursor is set, nothing is sent (no history).
        $sink = $this->redirectMessages();
        $this->assertSame(0, sync::notify_course($course, $this->client($events), $activation));
        $this->assertCount(0, $sink->get_messages());
        $cursor = $DB->get_field('local_memovia_course', 'eventscursor', ['courseid' => $course->id]);
        $this->assertSame('2026-10-18T13:00:00.123456+00:00', $cursor);

        // Next pass: one summary per active learner, none for the teacher nor a suspended learner.
        $client = $this->client($events);
        $this->assertSame(2, sync::notify_course($course, $client, $activation));
        $this->assertSame('2026-10-18T13:00:00.123456+00:00', $client->calls[0]['since']);
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $to = array_map(fn($m) => (int) $m->useridto, $messages);
        sort($to);
        $this->assertSame([(int) $student->id, (int) $other->id], $to);
        $m = $messages[0];
        $this->assertSame('local_memovia', $m->component);
        $this->assertSame('newcontent', $m->eventtype);
        $this->assertStringContainsString('BP Coiffure', $m->subject);
        $this->assertStringContainsString('La permanente', $m->fullmessagehtml);
        $this->assertStringNotContainsString('<script>', $m->fullmessagehtml);
        $this->assertStringContainsString('note=11111111-1111-4111-8111-111111111111', $m->fullmessagehtml);
        $this->assertStringNotContainsString('sesskey', $m->fullmessagehtml);

        // No new event: nothing.
        $sink->clear();
        $this->assertSame(0, sync::notify_course($course, $this->client([]), $activation));
        $this->assertCount(0, $sink->get_messages());
        $this->assertNotEmpty($teacher->id);
        $this->assertNotEmpty($suspended->id);
    }
}
