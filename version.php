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
 * MEMOVIA for Moodle: course files adapted for the whole class, "Open with MEMOVIA" button.
 *
 * @package    local_memovia
 * @copyright  2026 MEMOVIA SAS
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_memovia';
$plugin->version = 2026101804;
$plugin->requires = 2024100700; // Moodle 4.5.
$plugin->supported = [405, 500];
$plugin->maturity = MATURITY_BETA;
$plugin->release = '0.2.1';
$plugin->dependencies = ['mod_lti' => ANY_VERSION];
