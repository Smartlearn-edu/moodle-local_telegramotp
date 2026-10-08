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

namespace local_telegramotp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_telegramotp\client;
use local_telegramotp\manager;

/**
 * External Web Service functions for Telegram OTP verification and registration.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class otp extends external_api {
    /**
     * Parameters for send_otp.
     *
     * @return external_function_parameters
     */
    public static function send_otp_parameters(): external_function_parameters {
        return new external_function_parameters([
            'firstname' => new external_value(PARAM_NOTAGS, 'User first name'),
            'lastname'  => new external_value(PARAM_NOTAGS, 'User last name'),
            'email'     => new external_value(PARAM_EMAIL, 'User email address'),
            'password'  => new external_value(PARAM_RAW, 'User password'),
            'phone'     => new external_value(PARAM_NOTAGS, 'User raw phone number'),
            'username'  => new external_value(PARAM_RAW, 'Optional custom username', VALUE_DEFAULT, ''),
            'honeypot'  => new external_value(PARAM_RAW, 'Anti-spam honeypot field', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Send verification OTP via Telegram Gateway.
     *
     * @param string $firstname User first name.
     * @param string $lastname User last name.
     * @param string $email User email.
     * @param string $password User password.
     * @param string $phone User phone number.
     * @param string $username User username.
     * @param string $honeypot Anti-spam honeypot.
     * @return array Result array.
     */
    public static function send_otp(
        string $firstname,
        string $lastname,
        string $email,
        string $password,
        string $phone,
        string $username = '',
        string $honeypot = ''
    ): array {
        global $CFG;

        $params = self::validate_parameters(self::send_otp_parameters(), [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'email'     => $email,
            'password'  => $password,
            'phone'     => $phone,
            'username'  => $username,
            'honeypot'  => $honeypot,
        ]);

        // Anti-spam honeypot check.
        if (!empty($params['honeypot'])) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => get_string('error_spam_detected', 'local_telegramotp'),
            ];
        }

        // Check if plugin is enabled.
        $enabled = (bool) get_config('local_telegramotp', 'enabled');
        if (!$enabled) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => get_string('error_registration_disabled', 'local_telegramotp'),
            ];
        }

        // Validate registration input.
        $validation = manager::validate_registration_data($params);
        if (!$validation['valid']) {
            $firsterror = reset($validation['errors']);
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => $firsterror,
            ];
        }

        $normalizedphone = $validation['normalized_phone'];
        $clientip = manager::get_client_ip();

        // Check cooldown.
        $cooldown = manager::check_cooldown($normalizedphone);
        if (!$cooldown['allowed']) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => $cooldown['remaining_seconds'],
                'message'    => get_string('error_cooldown_active', 'local_telegramotp', $cooldown['remaining_seconds']),
            ];
        }

        // Check phone rate limit.
        $phonelimit = manager::check_phone_rate_limit($normalizedphone);
        if (!$phonelimit['allowed']) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => get_string('error_rate_limit_phone', 'local_telegramotp'),
            ];
        }

        // Check IP rate limit.
        $iplimit = manager::check_ip_rate_limit($clientip);
        if (!$iplimit['allowed']) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => get_string('error_rate_limit_ip', 'local_telegramotp'),
            ];
        }

        // Dispatch OTP via Telegram Gateway API client.
        $client = new client();
        if (!$client->is_configured()) {
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => get_string('error_telegram_not_configured', 'local_telegramotp'),
            ];
        }

        $codelength = (int) get_config('local_telegramotp', 'code_length') ?: 6;
        $ttl = (int) get_config('local_telegramotp', 'ttl') ?: 300;

        $apiresult = $client->send_verification_message($normalizedphone, $codelength, $ttl);

        if (!$apiresult['success']) {
            $error = $apiresult['error'] ?? '';
            $msg = get_string('error_telegram_api', 'local_telegramotp');
            if ($error === 'PHONE_NUMBER_NOT_FOUND' || $error === 'PHONE_NUMBER_INVALID') {
                $msg = get_string('error_phone_not_found_tg', 'local_telegramotp');
            }
            return [
                'success'    => false,
                'request_id' => '',
                'cooldown'   => 0,
                'message'    => $msg,
            ];
        }

        $requestid = $apiresult['request_id'];
        manager::create_otp_request($normalizedphone, $params['email'], $requestid, $clientip);

        $configuredcooldown = (int) get_config('local_telegramotp', 'cooldown') ?: manager::DEFAULT_COOLDOWN;

        $msg = $client->is_test_mode()
            ? get_string('success_code_sent_test', 'local_telegramotp', $client->get_test_dummy_code())
            : get_string('success_code_sent', 'local_telegramotp');

        return [
            'success'    => true,
            'request_id' => $requestid,
            'cooldown'   => $configuredcooldown,
            'message'    => $msg,
        ];
    }

    /**
     * Return structure for send_otp.
     *
     * @return external_single_structure
     */
    public static function send_otp_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Whether OTP was successfully sent'),
            'request_id' => new external_value(PARAM_RAW, 'Telegram Gateway request ID'),
            'cooldown'   => new external_value(PARAM_INT, 'Cooldown time in seconds'),
            'message'    => new external_value(PARAM_TEXT, 'Human readable status message'),
        ]);
    }

    /**
     * Parameters for verify_and_register.
     *
     * @return external_function_parameters
     */
    public static function verify_and_register_parameters(): external_function_parameters {
        return new external_function_parameters([
            'request_id' => new external_value(PARAM_RAW, 'Telegram Gateway request ID'),
            'otp'        => new external_value(PARAM_NOTAGS, 'Verification code entered by user'),
            'firstname'  => new external_value(PARAM_NOTAGS, 'User first name'),
            'lastname'   => new external_value(PARAM_NOTAGS, 'User last name'),
            'email'      => new external_value(PARAM_EMAIL, 'User email address'),
            'password'   => new external_value(PARAM_RAW, 'User password'),
            'phone'      => new external_value(PARAM_NOTAGS, 'User raw phone number'),
            'username'   => new external_value(PARAM_RAW, 'Optional custom username', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Verify OTP and complete user registration.
     *
     * @param string $request_id Telegram Gateway request ID.
     * @param string $otp Code entered by user.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @param string $email Email.
     * @param string $password Password.
     * @param string $phone Phone.
     * @param string $username Username.
     * @return array Result array.
     */
    public static function verify_and_register(
        string $requestid,
        string $otp,
        string $firstname,
        string $lastname,
        string $email,
        string $password,
        string $phone,
        string $username = ''
    ): array {
        global $CFG;

        $params = self::validate_parameters(self::verify_and_register_parameters(), [
            'request_id' => $requestid,
            'otp'        => $otp,
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'email'      => $email,
            'password'   => $password,
            'phone'      => $phone,
            'username'   => $username,
        ]);

        $requestrecord = manager::get_request_by_id($params['request_id']);
        if (!$requestrecord || $requestrecord->status !== 'pending') {
            return [
                'success'      => false,
                'redirect_url' => '',
                'message'      => get_string('error_invalid_request', 'local_telegramotp'),
            ];
        }

        if ((int) $requestrecord->attempts >= manager::MAX_ATTEMPTS_PER_REQUEST) {
            return [
                'success'      => false,
                'redirect_url' => '',
                'message'      => get_string('error_max_attempts', 'local_telegramotp'),
            ];
        }

        // Validate code with Telegram Gateway API.
        $client = new client();
        $code = manager::normalize_arabic_digits(trim($params['otp']));
        $checkresult = $client->check_verification_status($params['request_id'], $code);

        if (!$checkresult['success']) {
            $attempts = manager::record_failed_attempt((int) $requestrecord->id);
            $remaining = manager::MAX_ATTEMPTS_PER_REQUEST - $attempts;

            $status = $checkresult['status'] ?? '';
            if ($status === 'expired') {
                $msg = get_string('error_code_expired', 'local_telegramotp');
            } else if ($remaining <= 0) {
                $msg = get_string('error_max_attempts', 'local_telegramotp');
            } else {
                $msg = get_string('error_invalid_code_remaining', 'local_telegramotp', $remaining);
            }

            return [
                'success'      => false,
                'redirect_url' => '',
                'message'      => $msg,
            ];
        }

        // Code is valid! Mark verified.
        manager::mark_verified((int) $requestrecord->id);

        // Re-validate registration data before creating account to prevent race conditions.
        $validation = manager::validate_registration_data($params);
        if (!$validation['valid']) {
            $firsterror = reset($validation['errors']);
            return [
                'success'      => false,
                'redirect_url' => '',
                'message'      => $firsterror,
            ];
        }

        // Create Moodle user and authenticate.
        $params['phone'] = $validation['normalized_phone'];
        manager::create_and_login_user($params);

        return [
            'success'      => true,
            'redirect_url' => (new \moodle_url('/my/'))->out(false),
            'message'      => get_string('success_registered', 'local_telegramotp'),
        ];
    }

    /**
     * Return structure for verify_and_register.
     *
     * @return external_single_structure
     */
    public static function verify_and_register_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether verification succeeded and user was logged in'),
            'redirect_url' => new external_value(PARAM_URL, 'Dashboard URL to redirect to upon success'),
            'message'      => new external_value(PARAM_TEXT, 'Human readable status message'),
        ]);
    }

    /**
     * Parameters for start_bot_verification.
     *
     * @return external_function_parameters
     */
    public static function start_bot_verification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'firstname' => new external_value(PARAM_NOTAGS, 'User first name'),
            'lastname'  => new external_value(PARAM_NOTAGS, 'User last name'),
            'email'     => new external_value(PARAM_EMAIL, 'User email address'),
            'password'  => new external_value(PARAM_RAW, 'User password'),
            'phone'     => new external_value(PARAM_NOTAGS, 'User raw phone number'),
            'username'  => new external_value(PARAM_RAW, 'Optional custom username', VALUE_DEFAULT, ''),
            'honeypot'  => new external_value(PARAM_RAW, 'Anti-spam honeypot field', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Initiate bot verification and obtain Telegram deep link.
     *
     * @param string $firstname User first name.
     * @param string $lastname User last name.
     * @param string $email User email.
     * @param string $password User password.
     * @param string $phone User phone number.
     * @param string $username User username.
     * @param string $honeypot Anti-spam honeypot.
     * @return array Result array.
     */
    public static function start_bot_verification(
        string $firstname,
        string $lastname,
        string $email,
        string $password,
        string $phone,
        string $username = '',
        string $honeypot = ''
    ): array {
        $params = self::validate_parameters(self::start_bot_verification_parameters(), [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'email'     => $email,
            'password'  => $password,
            'phone'     => $phone,
            'username'  => $username,
            'honeypot'  => $honeypot,
        ]);

        if (!empty($params['honeypot'])) {
            return [
                'success'      => false,
                'token'        => '',
                'bot_username' => '',
                'deeplink'     => '',
                'message'      => get_string('error_spam_detected', 'local_telegramotp'),
            ];
        }

        $enabled = (bool) get_config('local_telegramotp', 'enabled');
        if (!$enabled) {
            return [
                'success'      => false,
                'token'        => '',
                'bot_username' => '',
                'deeplink'     => '',
                'message'      => get_string('error_registration_disabled', 'local_telegramotp'),
            ];
        }

        return manager::create_bot_verification_token($params);
    }

    /**
     * Return structure for start_bot_verification.
     *
     * @return external_single_structure
     */
    public static function start_bot_verification_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether bot verification was initiated'),
            'token'        => new external_value(PARAM_RAW, 'Bot verification token'),
            'bot_username' => new external_value(PARAM_RAW, 'Telegram bot username'),
            'deeplink'     => new external_value(PARAM_RAW, 'Telegram deep link URL'),
            'message'      => new external_value(PARAM_TEXT, 'Status or instructions message'),
        ]);
    }

    /**
     * Parameters for check_bot_verification.
     *
     * @return external_function_parameters
     */
    public static function check_bot_verification_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token' => new external_value(PARAM_RAW, 'Bot verification token'),
        ]);
    }

    /**
     * Check verification status of a bot token.
     *
     * @param string $token Bot verification token.
     * @return array Result array.
     */
    public static function check_bot_verification(string $token): array {
        $params = self::validate_parameters(self::check_bot_verification_parameters(), [
            'token' => $token,
        ]);

        $status = manager::check_bot_verification_status($params['token']);
        return [
            'success'      => (bool)($status['success'] ?? false),
            'verified'     => (bool)($status['verified'] ?? false),
            'redirect_url' => (string)($status['redirect_url'] ?? ''),
            'message'      => (string)($status['message'] ?? ''),
        ];
    }

    /**
     * Return structure for check_bot_verification.
     *
     * @return external_single_structure
     */
    public static function check_bot_verification_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether request was processed'),
            'verified'     => new external_value(PARAM_BOOL, 'Whether user was verified and logged in'),
            'redirect_url' => new external_value(PARAM_URL, 'Redirect URL if verified', VALUE_DEFAULT, ''),
            'message'      => new external_value(PARAM_TEXT, 'Status message', VALUE_DEFAULT, ''),
        ]);
    }
}
