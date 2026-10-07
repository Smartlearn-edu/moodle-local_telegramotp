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

namespace local_telegramotp;

/**
 * HTTP client for Telegram Gateway REST API.
 *
 * Communicates with the official Telegram Gateway API (https://gatewayapi.telegram.org)
 * to send verification codes and check OTP statuses synchronously.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string Base URL for Telegram Gateway API. */
    protected const API_BASE_URL = 'https://gatewayapi.telegram.org';

    /** @var string API token for Telegram Gateway. */
    protected string $token;

    /**
     * Constructor.
     *
     * @param string|null $token Optional API token. If omitted, loaded from plugin config.
     */
    public function __construct(?string $token = null) {
        if ($token !== null && $token !== '') {
            $this->token = $token;
        } else {
            $this->token = (string) get_config('local_telegramotp', 'api_token');
        }
    }

    /**
     * Check if test / simulation mode is enabled.
     *
     * @return bool True if test mode is active.
     */
    public function is_test_mode(): bool {
        return (bool) get_config('local_telegramotp', 'test_mode');
    }

    /**
     * Get the configured dummy test code.
     *
     * @return string Dummy code (default: 123456).
     */
    public function get_test_dummy_code(): string {
        $dummy = (string) get_config('local_telegramotp', 'test_dummy_code');
        return $dummy !== '' ? $dummy : '123456';
    }

    /**
     * Check if the API client is configured with a token or running in test mode.
     *
     * @return bool True if configured or test mode active.
     */
    public function is_configured(): bool {
        return $this->is_test_mode() || !empty(trim($this->token));
    }

    /**
     * Send a verification code to a phone number.
     *
     * @param string $phone Destination phone number in E.164 format.
     * @param int $codelength Number of digits in the OTP (4, 6, or 8).
     * @param int $ttl Time to live in seconds (30 to 3600).
     * @return array Standard result array [success => bool, request_id => string, error => string].
     */
    public function send_verification_message(string $phone, int $codelength = 6, int $ttl = 300): array {
        if ($this->is_test_mode()) {
            $mockid = 'mock_req_' . substr(md5($phone . microtime()), 0, 16);
            return [
                'success'    => true,
                'request_id' => $mockid,
                'data'       => [
                    'request_id' => $mockid,
                    'mock'       => true,
                ],
            ];
        }

        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'api_not_configured',
            ];
        }

        $payload = [
            'phone_number' => $phone,
            'code_length'  => $codelength,
            'ttl'          => $ttl,
        ];

        $response = $this->post_request('/sendVerificationMessage', $payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];
        if (!empty($data['ok']) && !empty($data['result']['request_id'])) {
            return [
                'success'    => true,
                'request_id' => $data['result']['request_id'],
                'data'       => $data['result'],
            ];
        }

        $error = $data['error'] ?? 'unknown_api_error';
        return [
            'success' => false,
            'error'   => $error,
            'code'    => $data['error_code'] ?? 0,
        ];
    }

    /**
     * Check the status of a verification code entered by the user.
     *
     * @param string $requestid Telegram Gateway request ID.
     * @param string $code Verification code entered by user.
     * @return array Result array [success => bool, status => string, error => string].
     */
    public function check_verification_status(string $requestid, string $code): array {
        if ($this->is_test_mode() || str_starts_with($requestid, 'mock_req_')) {
            $expected = $this->get_test_dummy_code();
            if ($code === $expected) {
                return [
                    'success' => true,
                    'status'  => 'code_valid',
                ];
            }
            return [
                'success' => false,
                'status'  => 'code_invalid',
                'error'   => 'invalid_code',
            ];
        }

        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'api_not_configured',
            ];
        }

        $payload = [
            'request_id' => $requestid,
            'code'       => $code,
        ];

        $response = $this->post_request('/checkVerificationStatus', $payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];
        if (!empty($data['ok'])) {
            $verificationstatus = $data['result']['verification_status'] ?? [];
            $status = $verificationstatus['status'] ?? '';

            if ($status === 'code_valid') {
                return [
                    'success' => true,
                    'status'  => 'code_valid',
                ];
            }

            return [
                'success' => false,
                'status'  => $status,
                'error'   => $status !== '' ? $status : 'invalid_code',
            ];
        }

        $error = $data['error'] ?? 'verification_failed';
        return [
            'success' => false,
            'error'   => $error,
            'code'    => $data['error_code'] ?? 0,
        ];
    }

    /**
     * Revoke a pending verification message.
     *
     * @param string $requestid Telegram Gateway request ID.
     * @return array Standard result array.
     */
    public function revoke_verification_message(string $requestid): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'api_not_configured',
            ];
        }

        $payload = ['request_id' => $requestid];
        $response = $this->post_request('/revokeVerificationMessage', $payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];
        return [
            'success' => !empty($data['ok']),
            'data'    => $data['result'] ?? null,
            'error'   => $data['error'] ?? null,
        ];
    }

    /**
     * Check if a verification message can be sent to a specific phone number.
     *
     * @param string $phone Destination phone number in E.164.
     * @return array Standard result array.
     */
    public function check_send_ability(string $phone): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error'   => 'api_not_configured',
            ];
        }

        $payload = ['phone_number' => $phone];
        $response = $this->post_request('/checkSendAbility', $payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];
        return [
            'success'  => !empty($data['ok']) && !empty($data['result']['can_send']),
            'can_send' => !empty($data['result']['can_send']),
            'error'    => $data['error'] ?? null,
        ];
    }

    /**
     * Parse raw JSON response into structured array.
     *
     * @param string $rawresponse Raw JSON string.
     * @return array Structured array.
     */
    public function parse_response(string $rawresponse): array {
        $decoded = json_decode($rawresponse, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error'   => 'invalid_json_response',
            ];
        }
        return [
            'success' => true,
            'data'    => $decoded,
        ];
    }

    /**
     * Execute HTTP POST request against Telegram Gateway API.
     *
     * @param string $endpoint API endpoint path (e.g. /sendVerificationMessage).
     * @param array $payload Request payload data.
     * @return array Result array with [success, data, error].
     */
    protected function post_request(string $endpoint, array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = self::API_BASE_URL . $endpoint;
        $jsonpayload = json_encode($payload);

        $curl = new \curl();
        $options = [
            'CURLOPT_TIMEOUT'        => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($jsonpayload),
            ],
        ];

        $rawresponse = $curl->post($url, $jsonpayload, $options);
        $errno = $curl->get_errno();

        if ($errno) {
            return [
                'success' => false,
                'error'   => 'curl_error: ' . $curl->error,
                'code'    => $errno,
            ];
        }

        $parsed = $this->parse_response((string) $rawresponse);
        if (!$parsed['success']) {
            return [
                'success' => false,
                'error'   => 'invalid_json_response',
                'raw'     => $rawresponse,
            ];
        }

        return [
            'success' => true,
            'data'    => $parsed['data'],
        ];
    }
}
