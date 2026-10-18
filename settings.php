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
 * Admin settings: MEMOVIA address and on/off switch (off by default).
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_memovia', new lang_string('pluginname', 'local_memovia'));
    $settings->add(new admin_setting_configcheckbox(
        'local_memovia/enabled',
        new lang_string('enabled', 'local_memovia'),
        new lang_string('enabled_desc', 'local_memovia'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_memovia/apiurl',
        new lang_string('apiurl', 'local_memovia'),
        new lang_string('apiurl_desc', 'local_memovia'),
        'https://app.memovia.io',
        PARAM_URL
    ));
    $ADMIN->add('localplugins', $settings);
}
