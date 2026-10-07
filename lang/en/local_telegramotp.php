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
 * English language strings for Telegram OTP Registration.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Telegram OTP registration';

// Registration form and UI strings.
$string['register_title'] = 'Create your account';
$string['register_heading'] = 'Create an account';
$string['register_subheading'] = 'Fast and secure registration verified via Telegram';
$string['phone'] = 'Phone number';
$string['phone_help'] = 'Enter your phone number connected to Telegram';
$string['optional'] = 'Optional';
$string['username_placeholder'] = 'Leave blank to use email';
$string['username_help'] = 'Choose a custom username or leave blank to automatically use your email address.';
$string['send_code'] = 'Send code via Telegram';
$string['sending_code'] = 'Sending code...';
$string['enter_code'] = 'Verification code';
$string['enter_code_help'] = 'Please enter the 6-digit verification code sent to your Telegram account for';
$string['verify_and_register'] = 'Verify & create account';
$string['verifying'] = 'Verifying code...';
$string['resend_code'] = 'Resend code';
$string['resend_in'] = 'Resend available in';
$string['change_phone_number'] = 'Change phone number or edit details';
$string['already_have_account'] = 'Already have an account?';
$string['login_here'] = 'Log in here';

// Admin settings strings.
$string['setting_enabled'] = 'Enable Telegram registration';
$string['setting_enabled_desc'] = 'Enable phone-first registration with synchronous Telegram OTP verification.';
$string['setting_api_token'] = 'Telegram Gateway API token';
$string['setting_api_token_desc'] = 'Enter your API token generated from gateway.telegram.org';
$string['setting_code_length'] = 'Verification code length';
$string['setting_code_length_desc'] = 'Number of digits in the verification OTP sent to the user.';
$string['setting_ttl'] = 'Code validity (TTL)';
$string['setting_ttl_desc'] = 'Number of seconds the code remains valid (default: 300 seconds).';
$string['setting_cooldown'] = 'Resend cooldown';
$string['setting_cooldown_desc'] = 'Number of seconds before a user can request another code (default: 60 seconds).';
$string['setting_max_ip_attempts'] = 'Max requests per IP per hour';
$string['setting_max_ip_attempts_desc'] = 'Maximum number of OTP requests allowed from a single IP address per hour.';
$string['setting_max_phone_attempts'] = 'Max requests per phone per 15 min';
$string['setting_max_phone_attempts_desc'] = 'Maximum number of OTP requests allowed per phone number in 15 minutes.';
$string['setting_default_country'] = 'Default country';
$string['setting_default_country_desc'] = 'Default dialing code selected in the registration phone input.';

// Validation and error messages.
$string['error_missing_firstname'] = 'Please enter your first name.';
$string['error_missing_lastname'] = 'Please enter your last name.';
$string['error_missing_email'] = 'Please enter your email address.';
$string['error_invalid_email'] = 'Please enter a valid email address.';
$string['error_email_exists'] = 'An account with this email address already exists.';
$string['error_missing_password'] = 'Please enter a password.';
$string['error_invalid_username'] = 'The chosen username contains invalid characters.';
$string['error_username_exists'] = 'This username is already taken. Please choose another.';
$string['error_invalid_phone'] = 'Please enter a valid phone number with country dialing code.';
$string['error_phone_exists'] = 'This phone number is already registered.';
$string['error_spam_detected'] = 'Spam submission detected.';
$string['error_registration_disabled'] = 'Registration is currently disabled.';
$string['error_cooldown_active'] = 'Please wait {$a} seconds before requesting a new code.';
$string['error_rate_limit_phone'] = 'Too many requests for this phone number. Please try again later.';
$string['error_rate_limit_ip'] = 'Too many requests from your IP address. Please try again later.';
$string['error_telegram_not_configured'] = 'Telegram Gateway API is not configured. Please contact the administrator.';
$string['error_telegram_api'] = 'Unable to send Telegram verification code. Please check your number and try again.';
$string['error_phone_not_found_tg'] = 'This phone number is not registered on Telegram. Please open or install Telegram first.';
$string['error_invalid_request'] = 'Invalid or expired registration session. Please request a new code.';
$string['error_max_attempts'] = 'Maximum verification attempts exceeded. Please request a new code.';
$string['error_invalid_code_remaining'] = 'Incorrect verification code. You have {$a} attempt(s) remaining.';
$string['error_code_expired'] = 'Verification code has expired. Please request a new code.';
$string['error_missing_code'] = 'Please enter the verification code.';
$string['error_code_not_sent'] = 'Please request a verification code first.';
$string['error_invalid_action'] = 'Invalid request action.';

// Success notifications.
$string['success_code_sent'] = 'Verification code sent to your Telegram account.';
$string['success_registered'] = 'Account created successfully! Logging you in...';

// Privacy API strings.
$string['privacy:metadata:requests'] = 'Stores Telegram OTP verification requests, IP addresses, and verification state.';
$string['privacy:metadata:requests:phone'] = 'User phone number used for Telegram verification.';
$string['privacy:metadata:requests:email'] = 'User email address provided during registration.';
$string['privacy:metadata:requests:request_id'] = 'Telegram Gateway API unique request identifier.';
$string['privacy:metadata:requests:ip_address'] = 'Client IP address recorded for security and abuse rate limiting.';
$string['privacy:metadata:requests:status'] = 'Status of the OTP verification request.';
$string['privacy:metadata:requests:attempts'] = 'Number of failed verification attempts recorded.';
$string['privacy:metadata:requests:timecreated'] = 'Timestamp when the OTP request was initiated.';
$string['privacy:metadata:requests:timemodified'] = 'Timestamp when the OTP request was last updated.';
$string['privacy:metadata:telegram_gateway'] = 'Phone numbers are sent to Telegram Gateway API to deliver verification codes.';
$string['privacy:metadata:telegram_gateway:phone_number'] = 'The phone number sent to Telegram Gateway.';
