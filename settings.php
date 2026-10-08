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
 * Admin settings definition for local_telegramotp.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_telegramotp', get_string('pluginname', 'local_telegramotp'));

    // Enable/disable registration.
    $settings->add(new admin_setting_configcheckbox(
        'local_telegramotp/enabled',
        get_string('setting_enabled', 'local_telegramotp'),
        get_string('setting_enabled_desc', 'local_telegramotp'),
        1
    ));

    // Verification provider: hybrid, bot, gateway.
    $provideroptions = [
        'hybrid'  => get_string('provider_hybrid', 'local_telegramotp'),
        'bot'     => get_string('provider_bot', 'local_telegramotp'),
        'gateway' => get_string('provider_gateway', 'local_telegramotp'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_telegramotp/verification_provider',
        get_string('setting_verification_provider', 'local_telegramotp'),
        get_string('setting_verification_provider_desc', 'local_telegramotp'),
        'hybrid',
        $provideroptions
    ));

    // Telegram Bot Username override (optional).
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/bot_username',
        get_string('setting_bot_username', 'local_telegramotp'),
        get_string('setting_bot_username_desc', 'local_telegramotp'),
        '',
        PARAM_NOTAGS
    ));

    // Bot verification security level: fast vs strict.
    $securityoptions = [
        'fast'   => get_string('security_fast', 'local_telegramotp'),
        'strict' => get_string('security_strict', 'local_telegramotp'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_telegramotp/bot_verification_security',
        get_string('setting_bot_security', 'local_telegramotp'),
        get_string('setting_bot_security_desc', 'local_telegramotp'),
        'fast',
        $securityoptions
    ));

    // Development / test simulation mode.
    $settings->add(new admin_setting_configcheckbox(
        'local_telegramotp/test_mode',
        get_string('setting_test_mode', 'local_telegramotp'),
        get_string('setting_test_mode_desc', 'local_telegramotp'),
        0
    ));

    // Test verification code.
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/test_dummy_code',
        get_string('setting_test_dummy_code', 'local_telegramotp'),
        get_string('setting_test_dummy_code_desc', 'local_telegramotp'),
        '123456',
        PARAM_NOTAGS
    ));

    // Telegram Gateway API Token.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_telegramotp/api_token',
        get_string('setting_api_token', 'local_telegramotp'),
        get_string('setting_api_token_desc', 'local_telegramotp'),
        ''
    ));

    // Code length.
    $codelengthoptions = [
        4 => '4 digits',
        6 => '6 digits (Recommended)',
        8 => '8 digits',
    ];
    $settings->add(new admin_setting_configselect(
        'local_telegramotp/code_length',
        get_string('setting_code_length', 'local_telegramotp'),
        get_string('setting_code_length_desc', 'local_telegramotp'),
        6,
        $codelengthoptions
    ));

    // Time to live (TTL) in seconds.
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/ttl',
        get_string('setting_ttl', 'local_telegramotp'),
        get_string('setting_ttl_desc', 'local_telegramotp'),
        300,
        PARAM_INT
    ));

    // Resend cooldown in seconds.
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/cooldown',
        get_string('setting_cooldown', 'local_telegramotp'),
        get_string('setting_cooldown_desc', 'local_telegramotp'),
        60,
        PARAM_INT
    ));

    // Max attempts per IP per hour.
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/max_ip_attempts',
        get_string('setting_max_ip_attempts', 'local_telegramotp'),
        get_string('setting_max_ip_attempts_desc', 'local_telegramotp'),
        5,
        PARAM_INT
    ));

    // Max attempts per phone per 15 minutes.
    $settings->add(new admin_setting_configtext(
        'local_telegramotp/max_phone_attempts',
        get_string('setting_max_phone_attempts', 'local_telegramotp'),
        get_string('setting_max_phone_attempts_desc', 'local_telegramotp'),
        3,
        PARAM_INT
    ));

    // Default country dial code.
    $countryoptions = [
        'SA' => 'Saudi Arabia (+966)',
        'EG' => 'Egypt (+20)',
        'AE' => 'United Arab Emirates (+971)',
        'KW' => 'Kuwait (+965)',
        'QA' => 'Qatar (+974)',
        'BH' => 'Bahrain (+973)',
        'OM' => 'Oman (+968)',
        'JO' => 'Jordan (+962)',
        'US' => 'United States / Canada (+1)',
        'GB' => 'United Kingdom (+44)',
    ];
    $settings->add(new admin_setting_configselect(
        'local_telegramotp/default_country',
        get_string('setting_default_country', 'local_telegramotp'),
        get_string('setting_default_country_desc', 'local_telegramotp'),
        'SA',
        $countryoptions
    ));

    $ADMIN->add('localplugins', $settings);
}
