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
 * AJAX endpoint for Telegram OTP verification and registration.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

try {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHANUMEXT);

    switch ($action) {
        case 'send_otp':
        case 'resend_otp':
            $firstname = optional_param('firstname', '', PARAM_NOTAGS);
            $lastname  = optional_param('lastname', '', PARAM_NOTAGS);
            $email     = optional_param('email', '', PARAM_EMAIL);
            $password  = optional_param('password', '', PARAM_RAW);
            $phone     = optional_param('phone', '', PARAM_NOTAGS);
            $username  = optional_param('username', '', PARAM_RAW);
            $honeypot  = optional_param('website', '', PARAM_RAW);

            $result = \local_telegramotp\external\otp::send_otp(
                $firstname,
                $lastname,
                $email,
                $password,
                $phone,
                $username,
                $honeypot
            );
            echo json_encode($result);
            break;

        case 'verify_and_register':
            $requestid = required_param('request_id', PARAM_RAW);
            $otp       = required_param('otp', PARAM_NOTAGS);
            $firstname = optional_param('firstname', '', PARAM_NOTAGS);
            $lastname  = optional_param('lastname', '', PARAM_NOTAGS);
            $email     = optional_param('email', '', PARAM_EMAIL);
            $password  = optional_param('password', '', PARAM_RAW);
            $phone     = optional_param('phone', '', PARAM_NOTAGS);
            $username  = optional_param('username', '', PARAM_RAW);

            $result = \local_telegramotp\external\otp::verify_and_register(
                $requestid,
                $otp,
                $firstname,
                $lastname,
                $email,
                $password,
                $phone,
                $username
            );
            echo json_encode($result);
            break;

        case 'start_bot_verification':
            $firstname = optional_param('firstname', '', PARAM_NOTAGS);
            $lastname  = optional_param('lastname', '', PARAM_NOTAGS);
            $email     = optional_param('email', '', PARAM_EMAIL);
            $password  = optional_param('password', '', PARAM_RAW);
            $phone     = optional_param('phone', '', PARAM_NOTAGS);
            $username  = optional_param('username', '', PARAM_RAW);
            $honeypot  = optional_param('website', '', PARAM_RAW);

            $result = \local_telegramotp\external\otp::start_bot_verification(
                $firstname,
                $lastname,
                $email,
                $password,
                $phone,
                $username,
                $honeypot
            );
            echo json_encode($result);
            break;

        case 'check_bot_verification':
            $token = required_param('token', PARAM_RAW);

            $result = \local_telegramotp\external\otp::check_bot_verification($token);
            echo json_encode($result);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => get_string('error_invalid_action', 'local_telegramotp'),
            ]);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
die();
