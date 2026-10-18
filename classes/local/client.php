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
 * HTTP client of MEMOVIA (https://<apiurl>/lti/module), every call signed by jwt::sign.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string */
    private $endpoint;
    /** @var string */
    private $clientid;

    /**
     * Identity of the MEMOVIA tool this client talks to (part of the course fingerprint).
     *
     * @return string
     */
    public function tool_key(): string {
        return $this->endpoint . '|' . $this->clientid;
    }

    /**
     * Constructor.
     *
     * @param string $apiurl MEMOVIA address, e.g. https://app.memovia.io
     * @param string $clientid client_id of the MEMOVIA tool
     */
    public function __construct(string $apiurl, string $clientid) {
        if (strpos($apiurl, 'https://') !== 0) {
            // Files and token never in clear.
            throw new \moodle_exception('err_api', 'local_memovia', '', null, 'apiurl must use https');
        }
        $this->endpoint = rtrim($apiurl, '/') . '/lti/module';
        $this->clientid = $clientid;
    }

    /**
     * Status of every file of a course; MEMOVIA hides what is missing from the list.
     *
     * @param string $contextid LTI context id of the course
     * @param array $files [['cm_id' => int, 'file_key' => string, 'contenthash' => string], ...]
     * @return array
     */
    public function status(string $contextid, array $files): array {
        return $this->call(['action' => 'status', 'context_id' => $contextid, 'files' => array_values($files)]);
    }

    /**
     * Sends one file (multipart).
     *
     * @param string $contextid LTI context id of the course
     * @param string $coursetitle course full name, for the MEMOVIA folder
     * @param int $cmid course module id
     * @param string $filekey '' or filepath+filename
     * @param \stored_file $file the file
     * @param string $title title of the MEMOVIA note (activity name for a resource, file name in a folder)
     * @return array
     */
    public function ingest(
        string $contextid,
        string $coursetitle,
        int $cmid,
        string $filekey,
        \stored_file $file,
        string $title = ''
    ): array {
        return $this->call([
            'action' => 'ingest', 'context_id' => $contextid, 'course_title' => $coursetitle, 'cm_id' => (string) $cmid,
            'title' => $title,
            'file_key' => $filekey, 'filename' => $file->get_filename(), 'contenthash' => $file->get_contenthash(),
            'mimetype' => $file->get_mimetype(), 'file' => $file,
        ], true);
    }

    /**
     * "Open with MEMOVIA": the next LTI launch of this user in this course opens this note.
     *
     * @param string $contextid LTI context id of the course
     * @param string $sub Moodle user id (the LTI sub)
     * @param string $noteid MEMOVIA note id
     * @return array
     */
    public function intent(string $contextid, string $sub, string $noteid): array {
        return $this->call(['action' => 'intent', 'context_id' => $contextid, 'sub' => $sub, 'note_id' => $noteid]);
    }

    /**
     * New contents shared with the class after a cursor (MEMOVIA time, microseconds). Empty cursor: no event, only the
     * current cursor (first pass: no history is sent).
     *
     * @param string $contextid LTI context id of the course
     * @param string $since cursor returned by the previous call, or ''
     * @return array ['events' => [['kind', 'note_id', 'title', 'at'], ...], 'cursor' => string]
     */
    public function events(string $contextid, string $since): array {
        return $this->call(['action' => 'events', 'context_id' => $contextid, 'since' => $since]);
    }

    /**
     * One signed POST.
     *
     * @param array $params body
     * @param bool $multipart multipart/form-data (a stored_file in $params) instead of JSON
     * @return array decoded answer
     * @throws \moodle_exception apierror on network error or HTTP status other than 2xx
     */
    protected function call(array $params, bool $multipart = false): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . jwt::sign($this->clientid, $this->endpoint));
        if (!$multipart) {
            $curl->setHeader('Content-Type: application/json');
            $params = json_encode($params);
        }
        $body = $curl->post($this->endpoint, $params, ['CURLOPT_TIMEOUT' => 60, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        $data = json_decode((string) $body, true);
        if ($curl->get_errno() || $code < 200 || $code >= 300) {
            $error = is_array($data) && isset($data['error']) ? (string) $data['error'] : 'HTTP ' . $code;
            throw new \moodle_exception('err_api', 'local_memovia', '', null, $error);
        }
        return is_array($data) ? $data : [];
    }
}
