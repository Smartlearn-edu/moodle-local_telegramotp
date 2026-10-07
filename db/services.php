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
 * External service functions for local_telegramotp.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_telegramotp_send_otp' => [
        'classname'     => 'local_telegramotp\external\otp',
        'methodname'    => 'send_otp',
        'description'   => 'Send verification code via Telegram Gateway API',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ],
    'local_telegramotp_verify_and_register' => [
        'classname'     => 'local_telegramotp\external\otp',
        'methodname'    => 'verify_and_register',
        'description'   => 'Verify Telegram OTP and complete account registration',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
    ],
];
