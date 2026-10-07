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

use advanced_testcase;

/**
 * Unit tests for Telegram Gateway API client.
 *
 * @package     local_telegramotp
 * @category    test
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_telegramotp\client
 */
class client_test extends advanced_testcase {
    /**
     * Test client configuration detection.
     */
    public function test_is_configured(): void {
        $clientwithouttoken = new client('');
        $this->assertFalse($clientwithouttoken->is_configured());

        $clientwithtoken = new client('test_token_12345');
        $this->assertTrue($clientwithtoken->is_configured());
    }

    /**
     * Test JSON response parsing.
     */
    public function test_parse_response(): void {
        $client = new client('dummy');

        // Valid JSON.
        $json = '{"ok": true, "result": {"request_id": "req_12345"}}';
        $parsed = $client->parse_response($json);
        $this->assertTrue($parsed['success']);
        $this->assertEquals('req_12345', $parsed['data']['result']['request_id']);

        // Invalid JSON.
        $badjson = '{invalid_json}';
        $parsedbad = $client->parse_response($badjson);
        $this->assertFalse($parsedbad['success']);
        $this->assertEquals('invalid_json_response', $parsedbad['error']);
    }

    /**
     * Test handling unconfigured client calls.
     */
    public function test_unconfigured_calls(): void {
        $client = new client('');

        $res = $client->send_verification_message('+966501234567');
        $this->assertFalse($res['success']);
        $this->assertEquals('api_not_configured', $res['error']);

        $rescheck = $client->check_verification_status('req_123', '123456');
        $this->assertFalse($rescheck['success']);
        $this->assertEquals('api_not_configured', $rescheck['error']);
    }

    /**
     * Test simulation test mode.
     */
    public function test_test_mode(): void {
        $this->resetAfterTest(true);

        set_config('test_mode', 1, 'local_telegramotp');
        set_config('test_dummy_code', '654321', 'local_telegramotp');

        $client = new client('');
        $this->assertTrue($client->is_configured());
        $this->assertTrue($client->is_test_mode());
        $this->assertEquals('654321', $client->get_test_dummy_code());

        // Send OTP in test mode.
        $sendres = $client->send_verification_message('+966501234567');
        $this->assertTrue($sendres['success']);
        $this->assertStringStartsWith('mock_req_', $sendres['request_id']);

        // Check verification status with wrong code.
        $badcheck = $client->check_verification_status($sendres['request_id'], '000000');
        $this->assertFalse($badcheck['success']);
        $this->assertEquals('code_invalid', $badcheck['status']);

        // Check verification status with correct dummy code.
        $goodcheck = $client->check_verification_status($sendres['request_id'], '654321');
        $this->assertTrue($goodcheck['success']);
        $this->assertEquals('code_valid', $goodcheck['status']);
    }
}
