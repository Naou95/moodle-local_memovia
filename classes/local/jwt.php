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

use Firebase\JWT\JWT as FirebaseJWT;
use mod_lti\local\ltiopenid\jwks_helper;

/**
 * Signs the calls to MEMOVIA with this site's LTI private key (the one mod_lti uses for launches).
 *
 * MEMOVIA checks the signature with the public key it already knows (mod/lti/certs.php, saved at LTI
 * registration): no shared secret to type or to leak. sub = client_id of the MEMOVIA tool.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class jwt {
    /**
     * A short-lived RS256 token for one call.
     *
     * @param string $clientid client_id of the MEMOVIA tool (lti_types.clientid)
     * @param string $aud https://<MEMOVIA host>/lti/module
     * @return string
     */
    public static function sign(string $clientid, string $aud): string {
        global $CFG;
        $key = jwks_helper::get_private_key();
        $now = time();
        $payload = [
            'iss' => $CFG->wwwroot,
            'sub' => $clientid,
            'aud' => $aud,
            'jti' => random_string(32),
            'iat' => $now - 60, // MEMOVIA accepts 60 s of clock drift.
            'exp' => $now + 300,
        ];
        return FirebaseJWT::encode($payload, $key['key'], 'RS256', $key['kid']);
    }
}
