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

use Firebase\JWT\JWK;
use Firebase\JWT\JWT as FirebaseJWT;
use local_memovia\local\jwt;
use mod_lti\local\ltiopenid\jwks_helper;

/**
 * The module's token is checkable with the site's public LTI key (mod/lti/certs.php).
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_memovia\local\jwt
 */
final class jwt_test extends \advanced_testcase {
    public function test_sign_verifies_with_site_public_key(): void {
        global $CFG;
        $this->resetAfterTest();
        $token = jwt::sign('client-1', 'https://app.memovia.io/lti/module');
        $this->assertCount(3, explode('.', $token));
        $header = json_decode(FirebaseJWT::urlsafeB64Decode(explode('.', $token)[0]), true);
        $this->assertSame(jwks_helper::get_private_key()['kid'], $header['kid']);
        $this->assertSame('RS256', $header['alg']);
        $claims = (array) FirebaseJWT::decode($token, JWK::parseKeySet(jwks_helper::get_jwks()));
        $this->assertSame($CFG->wwwroot, $claims['iss']);
        $this->assertSame('client-1', $claims['sub']);
        $this->assertSame('https://app.memovia.io/lti/module', $claims['aud']);
        $this->assertSame(32, strlen($claims['jti']));
        $this->assertLessThanOrEqual(420, $claims['exp'] - $claims['iat']);
        $other = (array) FirebaseJWT::decode(jwt::sign('client-1', 'x'), JWK::parseKeySet(jwks_helper::get_jwks()));
        $this->assertNotSame($claims['jti'], $other['jti']);
    }
}
