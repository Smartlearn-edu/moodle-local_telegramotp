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
 * Unit tests for business logic manager.
 *
 * @package     local_telegramotp
 * @category    test
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \local_telegramotp\manager
 */
class manager_test extends advanced_testcase {
    /**
     * Test Arabic digit normalization.
     */
    public function test_normalize_arabic_digits(): void {
        $eastern = '٠١٢٣٤٥٦٧٨٩';
        $normalized = manager::normalize_arabic_digits($eastern);
        $this->assertEquals('0123456789', $normalized);

        $persian = '۰۱۲۳۴۵۶۷۸۹';
        $normalizedpersian = manager::normalize_arabic_digits($persian);
        $this->assertEquals('0123456789', $normalizedpersian);

        $mixed = 'Code: ٤٨٢٩١٠';
        $this->assertEquals('Code: 482910', manager::normalize_arabic_digits($mixed));
    }

    /**
     * Test phone normalization to E.164.
     */
    public function test_normalize_phone(): void {
        // Standard E.164.
        $this->assertEquals('+966501234567', manager::normalize_phone('+966501234567', 'SA'));

        // Saudi national format with leading 0.
        $this->assertEquals('+966501234567', manager::normalize_phone('0501234567', 'SA'));

        // Saudi national format without leading 0.
        $this->assertEquals('+966501234567', manager::normalize_phone('501234567', 'SA'));

        // Arabic numeral input.
        $this->assertEquals('+966501234567', manager::normalize_phone('٠٥٠١٢٣٤٥٦٧', 'SA'));

        // Leading 00 international prefix.
        $this->assertEquals('+966501234567', manager::normalize_phone('00966501234567', 'SA'));

        // Formatted with spaces and hyphens.
        $this->assertEquals('+966501234567', manager::normalize_phone('+966 50-123-4567', 'SA'));

        // Other country: Egypt.
        $this->assertEquals('+201012345678', manager::normalize_phone('01012345678', 'EG'));

        // Invalid numbers.
        $this->assertEquals('', manager::normalize_phone('123', 'SA'));
        $this->assertEquals('', manager::normalize_phone('not-a-number', 'SA'));
    }

    /**
     * Test E.164 validation regex.
     */
    public function test_is_valid_e164(): void {
        $this->assertTrue(manager::is_valid_e164('+966501234567'));
        $this->assertTrue(manager::is_valid_e164('+15551234567'));
        $this->assertFalse(manager::is_valid_e164('966501234567'));
        $this->assertFalse(manager::is_valid_e164('+012345678'));
        $this->assertFalse(manager::is_valid_e164('+'));
    }

    /**
     * Test rate limiting mechanisms.
     */
    public function test_rate_limits(): void {
        $this->resetAfterTest(true);

        set_config('max_phone_attempts', 3, 'local_telegramotp');
        set_config('max_ip_attempts', 5, 'local_telegramotp');
        set_config('cooldown', 60, 'local_telegramotp');

        $phone = '+966501234567';
        $ip = '192.168.1.100';

        // Initially allowed.
        $phonecheck = manager::check_phone_rate_limit($phone);
        $this->assertTrue($phonecheck['allowed']);

        $ipcheck = manager::check_ip_rate_limit($ip);
        $this->assertTrue($ipcheck['allowed']);

        $cooldown = manager::check_cooldown($phone);
        $this->assertTrue($cooldown['allowed']);

        // Insert 3 requests for this phone.
        for ($i = 0; $i < 3; $i++) {
            manager::create_otp_request($phone, "user{$i}@example.com", "req_{$i}", $ip);
        }

        // Phone limit reached.
        $phonecheck = manager::check_phone_rate_limit($phone);
        $this->assertFalse($phonecheck['allowed']);

        // Cooldown active.
        $cooldown = manager::check_cooldown($phone);
        $this->assertFalse($cooldown['allowed']);
        $this->assertGreaterThan(0, $cooldown['remaining_seconds']);
    }

    /**
     * Test registration data validation.
     */
    public function test_validate_registration_data(): void {
        $this->resetAfterTest(true);

        // Valid data.
        $validdata = [
            'firstname' => 'Ahmed',
            'lastname'  => 'Ali',
            'email'     => 'ahmed.ali@example.com',
            'password'  => 'Pass1234!@#',
            'phone'     => '+966501234567',
            'username'  => 'ahmed_ali',
        ];
        $result = manager::validate_registration_data($validdata);
        $this->assertTrue($result['valid']);
        $this->assertEquals('+966501234567', $result['normalized_phone']);

        // Missing firstname.
        $invaliddata = $validdata;
        $invaliddata['firstname'] = '';
        $result = manager::validate_registration_data($invaliddata);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('firstname', $result['errors']);

        // Invalid email.
        $invaliddata = $validdata;
        $invaliddata['email'] = 'invalid-email';
        $result = manager::validate_registration_data($invaliddata);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('email', $result['errors']);
    }
}
