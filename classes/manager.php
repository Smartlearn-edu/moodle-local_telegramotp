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

use stdClass;

/**
 * Business logic manager for Telegram OTP verification and user registration.
 *
 * Handles phone normalization, rate limiting, request lifecycles, and user account creation.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Table name for OTP request logs. */
    public const TABLE_REQUESTS = 'local_telegramotp_requests';

    /** @var int Default cooldown in seconds. */
    public const DEFAULT_COOLDOWN = 60;

    /** @var int Default maximum attempts per request. */
    public const MAX_ATTEMPTS_PER_REQUEST = 5;

    /** @var string Profile field shortname for verified Telegram phone numbers. */
    public const PROFILE_FIELD_SHORTNAME = 'telegram_phone';

    /** @var string Profile category name for Telegram fields. */
    public const PROFILE_CATEGORY_NAME = 'Telegram';

    /**
     * Normalize Eastern / Arabic-Indic digits to Western ASCII digits.
     *
     * @param string $input String containing potential Eastern digits.
     * @return string Normalized string with ASCII digits 0-9.
     */
    public static function normalize_arabic_digits(string $input): string {
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $output = str_replace($eastern, $western, $input);
        return str_replace($persian, $western, $output);
    }

    /**
     * Normalize a phone number to standard E.164 format.
     *
     * @param string $phone Raw phone number.
     * @param string $defaultcountry Country code (e.g. 'SA', 'EG', 'AE', 'US').
     * @return string Normalized E.164 phone number, or empty string if invalid.
     */
    public static function normalize_phone(string $phone, string $defaultcountry = 'SA'): string {
        $clean = self::normalize_arabic_digits(trim($phone));

        // Remove spaces, hyphens, brackets, dots.
        $clean = preg_replace('/[\s\-\(\)\.]/', '', $clean);

        // Replace leading 00 with +.
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        // If it already starts with +, remove any other non-digit characters.
        if (str_starts_with($clean, '+')) {
            $digits = preg_replace('/[^\d]/', '', substr($clean, 1));
            $clean = '+' . $digits;
        } else {
            // Strip any remaining non-digits.
            $digits = preg_replace('/[^\d]/', '', $clean);

            // Apply country prefix based on default country.
            $countryprefixes = [
                'SA' => '966',
                'EG' => '20',
                'AE' => '971',
                'KW' => '965',
                'QA' => '974',
                'BH' => '973',
                'OM' => '968',
                'JO' => '962',
                'US' => '1',
                'CA' => '1',
                'GB' => '44',
            ];

            $prefix = $countryprefixes[strtoupper($defaultcountry)] ?? '966';

            // If number starts with 0 (national trunk prefix, e.g. 0501234567 in Saudi), strip it.
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }

            // If digits don't already start with the country prefix, prepend it.
            if (!str_starts_with($digits, $prefix)) {
                $digits = $prefix . $digits;
            }

            $clean = '+' . $digits;
        }

        if (self::is_valid_e164($clean)) {
            return $clean;
        }

        return '';
    }

    /**
     * Check if a phone string matches strict E.164 pattern.
     *
     * @param string $phone Phone string to check.
     * @return bool True if valid E.164.
     */
    public static function is_valid_e164(string $phone): bool {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }

    /**
     * Get client IP address.
     *
     * @return string Client IP address.
     */
    public static function get_client_ip(): string {
        return getremoteaddr();
    }

    /**
     * Check rate limiting for a phone number.
     *
     * @param string $phone E.164 phone number.
     * @return array [allowed => bool, count => int, max => int]
     */
    public static function check_phone_rate_limit(string $phone): array {
        global $DB;

        $maxattempts = (int) get_config('local_telegramotp', 'max_phone_attempts');
        if ($maxattempts <= 0) {
            $maxattempts = 3;
        }

        $timespan = 15 * MINSECS; // 15 minutes.
        $since = time() - $timespan;

        $count = $DB->count_records_select(
            self::TABLE_REQUESTS,
            'phone = :phone AND timecreated > :since',
            ['phone' => $phone, 'since' => $since]
        );

        return [
            'allowed' => $count < $maxattempts,
            'count'   => $count,
            'max'     => $maxattempts,
        ];
    }

    /**
     * Check rate limiting for an IP address.
     *
     * @param string $ip Client IP.
     * @return array [allowed => bool, count => int, max => int]
     */
    public static function check_ip_rate_limit(string $ip): array {
        global $DB;

        $maxattempts = (int) get_config('local_telegramotp', 'max_ip_attempts');
        if ($maxattempts <= 0) {
            $maxattempts = 5;
        }

        $timespan = HOURSECS; // 1 hour.
        $since = time() - $timespan;

        $count = $DB->count_records_select(
            self::TABLE_REQUESTS,
            'ip_address = :ip AND timecreated > :since',
            ['ip' => $ip, 'since' => $since]
        );

        return [
            'allowed' => $count < $maxattempts,
            'count'   => $count,
            'max'     => $maxattempts,
        ];
    }

    /**
     * Check resend cooldown for a phone number.
     *
     * @param string $phone E.164 phone number.
     * @return array [allowed => bool, remaining_seconds => int]
     */
    public static function check_cooldown(string $phone): array {
        global $DB;

        $cooldown = (int) get_config('local_telegramotp', 'cooldown');
        if ($cooldown <= 0) {
            $cooldown = self::DEFAULT_COOLDOWN;
        }

        $latest = $DB->get_records_select(
            self::TABLE_REQUESTS,
            'phone = :phone',
            ['phone' => $phone],
            'timecreated DESC',
            'id, timecreated',
            0,
            1
        );

        if (empty($latest)) {
            return [
                'allowed'           => true,
                'remaining_seconds' => 0,
            ];
        }

        $record = reset($latest);
        $elapsed = time() - (int) $record->timecreated;

        if ($elapsed < $cooldown) {
            return [
                'allowed'           => false,
                'remaining_seconds' => $cooldown - $elapsed,
            ];
        }

        return [
            'allowed'           => true,
            'remaining_seconds' => 0,
        ];
    }

    /**
     * Validate user registration fields before initiating OTP.
     *
     * @param array $data Input form data.
     * @return array [valid => bool, errors => array, normalized_phone => string]
     */
    public static function validate_registration_data(array $data): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/moodlelib.php');

        $errors = [];

        $firstname = trim($data['firstname'] ?? '');
        $lastname  = trim($data['lastname'] ?? '');
        $email     = trim($data['email'] ?? '');
        $password  = $data['password'] ?? '';
        $rawphone  = trim($data['phone'] ?? '');
        $username  = trim($data['username'] ?? '');

        if ($firstname === '') {
            $errors['firstname'] = get_string('error_missing_firstname', 'local_telegramotp');
        }
        if ($lastname === '') {
            $errors['lastname'] = get_string('error_missing_lastname', 'local_telegramotp');
        }

        // Email validation.
        if ($email === '') {
            $errors['email'] = get_string('error_missing_email', 'local_telegramotp');
        } else if (!validate_email($email)) {
            $errors['email'] = get_string('error_invalid_email', 'local_telegramotp');
        } else if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
            $errors['email'] = get_string('error_email_exists', 'local_telegramotp');
        }

        // Password validation.
        if ($password === '') {
            $errors['password'] = get_string('error_missing_password', 'local_telegramotp');
        } else if (!empty($CFG->passwordpolicy)) {
            $errmsg = '';
            if (!check_password_policy($password, $errmsg)) {
                $errors['password'] = $errmsg;
            }
        }

        // Phone normalization and validation.
        $defaultcountry = (string) get_config('local_telegramotp', 'default_country');
        if ($defaultcountry === '') {
            $defaultcountry = 'SA';
        }

        $phone = self::normalize_phone($rawphone, $defaultcountry);
        if ($phone === '') {
            $errors['phone'] = get_string('error_invalid_phone', 'local_telegramotp');
        } else if (self::is_phone_registered($phone)) {
            $errors['phone'] = get_string('error_phone_exists', 'local_telegramotp');
        }

        // Username validation (if specified).
        if ($username !== '') {
            $cleanusername = clean_param($username, PARAM_USERNAME);
            if ($cleanusername !== $username) {
                $errors['username'] = get_string('error_invalid_username', 'local_telegramotp');
            } else if ($DB->record_exists('user', ['username' => $username, 'deleted' => 0])) {
                $errors['username'] = get_string('error_username_exists', 'local_telegramotp');
            }
        }

        return [
            'valid'            => empty($errors),
            'errors'           => $errors,
            'normalized_phone' => $phone,
        ];
    }

    /**
     * Create an OTP request log record.
     *
     * @param string $phone Normalized phone number.
     * @param string $email User email.
     * @param string $requestid Telegram Gateway request ID.
     * @param string $ip Client IP.
     * @return int Inserted record ID.
     */
    public static function create_otp_request(string $phone, string $email, string $requestid, string $ip): int {
        global $DB;

        $record = new stdClass();
        $record->phone        = $phone;
        $record->email        = $email;
        $record->request_id   = $requestid;
        $record->ip_address   = $ip;
        $record->status       = 'pending';
        $record->attempts     = 0;
        $record->timecreated  = time();
        $record->timemodified = time();

        return (int) $DB->insert_record(self::TABLE_REQUESTS, $record);
    }

    /**
     * Get OTP request record by Telegram request_id.
     *
     * @param string $requestid Telegram Gateway request ID.
     * @return stdClass|null Request record, or null if not found.
     */
    public static function get_request_by_id(string $requestid): ?stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE_REQUESTS, ['request_id' => $requestid]);
        return $record ?: null;
    }

    /**
     * Record a failed verification attempt.
     *
     * @param int $id Record ID in local_telegramotp_requests.
     * @return int Current attempt count.
     */
    public static function record_failed_attempt(int $id): int {
        global $DB;

        $record = $DB->get_record(self::TABLE_REQUESTS, ['id' => $id]);
        if (!$record) {
            return 0;
        }

        $attempts = ((int) $record->attempts) + 1;
        $update = new stdClass();
        $update->id           = $id;
        $update->attempts     = $attempts;
        $update->timemodified = time();

        if ($attempts >= self::MAX_ATTEMPTS_PER_REQUEST) {
            $update->status = 'failed';
        }

        $DB->update_record(self::TABLE_REQUESTS, $update);
        return $attempts;
    }

    /**
     * Mark an OTP request as successfully verified.
     *
     * @param int $id Record ID in local_telegramotp_requests.
     * @return bool True on success.
     */
    public static function mark_verified(int $id): bool {
        global $DB;

        $update = new stdClass();
        $update->id           = $id;
        $update->status       = 'verified';
        $update->timemodified = time();

        return $DB->update_record(self::TABLE_REQUESTS, $update);
    }

    /**
     * Ensure that the custom user profile field for Telegram phone numbers exists.
     *
     * @return int The profile field ID.
     */
    public static function ensure_profile_field(): int {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => self::PROFILE_FIELD_SHORTNAME]);
        if ($field) {
            return (int) $field->id;
        }

        // Find or create category.
        $category = $DB->get_record('user_info_category', ['name' => self::PROFILE_CATEGORY_NAME]);
        if (!$category) {
            $maxsort = $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') ?: 0;
            $newcategory = new stdClass();
            $newcategory->name = self::PROFILE_CATEGORY_NAME;
            $newcategory->sortorder = ((int)$maxsort) + 1;
            $categoryid = (int)$DB->insert_record('user_info_category', $newcategory);
        } else {
            $categoryid = (int)$category->id;
        }

        $maxfieldsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = :catid',
            ['catid' => $categoryid]
        ) ?: 0;

        $newfield = new stdClass();
        $newfield->shortname = self::PROFILE_FIELD_SHORTNAME;
        $newfield->name = get_string('profile_field_name', 'local_telegramotp');
        $newfield->datatype = 'text';
        $newfield->description = get_string('profile_field_desc', 'local_telegramotp');
        $newfield->descriptionformat = 1;
        $newfield->categoryid = $categoryid;
        $newfield->sortorder = ((int)$maxfieldsort) + 1;
        $newfield->required = 0;
        $newfield->locked = 0;
        $newfield->visible = 2; // PROFILE_VISIBLE_ALL
        $newfield->forceunique = 0;
        $newfield->signup = 0;
        $newfield->defaultdata = '';
        $newfield->defaultdataformat = 0;
        $newfield->param1 = 30; // Display size.
        $newfield->param2 = 50; // Max length.
        $newfield->param3 = 0;
        $newfield->param4 = null;
        $newfield->param5 = null;

        return (int) $DB->insert_record('user_info_field', $newfield);
    }

    /**
     * Save the user's verified phone number into their custom profile field.
     *
     * @param int $userid Moodle user ID.
     * @param string $phone Verified E.164 phone number.
     * @return void
     */
    public static function save_user_phone(int $userid, string $phone): void {
        global $DB;

        $fieldid = self::ensure_profile_field();

        $record = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($record) {
            $record->data = $phone;
            $DB->update_record('user_info_data', $record);
        } else {
            $record = new stdClass();
            $record->userid = $userid;
            $record->fieldid = $fieldid;
            $record->data = $phone;
            $record->dataformat = 0;
            $DB->insert_record('user_info_data', $record);
        }
    }

    /**
     * Check whether a phone number is already registered in Moodle.
     * Checks both the custom profile field and standard phone1 field.
     *
     * @param string $phone E.164 phone number.
     * @return bool True if already registered to an active user.
     */
    public static function is_phone_registered(string $phone): bool {
        global $DB;

        // 1. Check custom profile field if present.
        $sql = "SELECT d.id, d.data
                  FROM {user_info_data} d
                  JOIN {user_info_field} f ON d.fieldid = f.id
                  JOIN {user} u ON d.userid = u.id
                 WHERE f.shortname = :shortname AND u.deleted = 0";
        $records = $DB->get_records_sql($sql, ['shortname' => self::PROFILE_FIELD_SHORTNAME]);
        foreach ($records as $record) {
            if (trim((string)$record->data) === $phone) {
                return true;
            }
        }

        // 2. Check standard phone1 field as fallback.
        if ($DB->record_exists('user', ['phone1' => $phone, 'deleted' => 0])) {
            return true;
        }

        return false;
    }

    /**
     * Create Moodle user account and immediately log them in.
     *
     * @param array $data User profile details.
     * @return stdClass Created user record.
     */
    public static function create_and_login_user(array $data): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/authlib.php');

        $email = trim($data['email']);
        $phone = trim($data['phone']);

        // Determine username: use provided username or default to sanitized email.
        $username = trim($data['username'] ?? '');
        if ($username === '') {
            $username = clean_param($email, PARAM_USERNAME);
            if ($DB->record_exists('user', ['username' => $username, 'deleted' => 0])) {
                $username = clean_param(str_replace('+', '', $phone), PARAM_USERNAME);
            }
        }

        $user = new stdClass();
        $user->auth         = 'manual';
        $user->confirmed    = 1;
        $user->mnethostid   = $CFG->mnet_localhost_id;
        $user->username     = $username;
        $user->password     = hash_internal_user_password($data['password']);
        $user->firstname    = trim($data['firstname']);
        $user->lastname     = trim($data['lastname']);
        $user->email        = $email;
        $user->phone1       = $phone;
        $user->country      = (string) get_config('local_telegramotp', 'default_country') ?: 'SA';
        $user->lang         = current_language();
        $user->timecreated  = time();
        $user->timemodified = time();

        $userid = user_create_user($user, false, false);
        $user->id = $userid;

        // Save verified phone to custom profile field.
        self::save_user_phone($userid, $phone);

        // Perform login.
        $userrecord = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        complete_user_login($userrecord);

        return $userrecord;
    }

    /**
     * Get configured Telegram bot username.
     *
     * @return string Bot username without @ symbol.
     */
    public static function get_bot_username(): string {
        $botname = (string) get_config('local_telegramotp', 'bot_username');
        if (trim($botname) === '') {
            $botname = (string) get_config('message_telegram', 'sitebotusername');
        }
        return ltrim(trim($botname), '@');
    }

    /**
     * Create a pending bot verification session and generate deep-link.
     *
     * @param array $formdata User registration inputs.
     * @return array [success => bool, token => string, deeplink => string, message => string]
     */
    public static function create_bot_verification_token(array $formdata): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/authlib.php');

        $validation = self::validate_registration_data($formdata);
        if (!$validation['valid']) {
            $firsterror = reset($validation['errors']);
            return [
                'success'  => false,
                'token'    => '',
                'deeplink' => '',
                'message'  => $firsterror,
            ];
        }

        $botusername = self::get_bot_username();
        if ($botusername === '') {
            return [
                'success'  => false,
                'token'    => '',
                'deeplink' => '',
                'message'  => get_string('bot_not_configured', 'local_telegramotp'),
            ];
        }

        $normalizedphone = $validation['normalized_phone'];
        $clientip = self::get_client_ip();

        // Check rate limits.
        $ipcheck = self::check_ip_rate_limit($clientip);
        if (!$ipcheck['allowed']) {
            return [
                'success'  => false,
                'token'    => '',
                'deeplink' => '',
                'message'  => get_string('error_rate_limit_ip', 'local_telegramotp'),
            ];
        }

        $phonecheck = self::check_phone_rate_limit($normalizedphone);
        if (!$phonecheck['allowed']) {
            return [
                'success'  => false,
                'token'    => '',
                'deeplink' => '',
                'message'  => get_string('error_rate_limit_phone', 'local_telegramotp'),
            ];
        }

        // Generate token: 32 hex chars prefixed with reg_.
        $token = 'reg_' . bin2hex(random_bytes(16));

        $payload = [
            'firstname' => trim($formdata['firstname']),
            'lastname'  => trim($formdata['lastname']),
            'email'     => trim($formdata['email']),
            'phone'     => $normalizedphone,
            'username'  => trim($formdata['username'] ?? ''),
            'password'  => hash_internal_user_password($formdata['password']),
            'country'   => (string) get_config('local_telegramotp', 'default_country') ?: 'SA',
        ];

        $record = new stdClass();
        $record->phone        = $normalizedphone;
        $record->email        = trim($formdata['email']);
        $record->request_id   = $token;
        $record->ip_address   = $clientip;
        $record->status       = 'pending';
        $record->attempts     = 0;
        $record->reg_data     = json_encode($payload);
        $record->timecreated  = time();
        $record->timemodified = time();

        $DB->insert_record(self::TABLE_REQUESTS, $record);

        $deeplink = 'https://t.me/' . $botusername . '?start=' . $token;

        return [
            'success'      => true,
            'token'        => $token,
            'bot_username' => $botusername,
            'deeplink'     => $deeplink,
            'message'      => get_string('verify_with_bot_help', 'local_telegramotp'),
        ];
    }

    /**
     * Verify a bot token received via Telegram webhook and provision user.
     *
     * @param string $token Token from /start command (reg_...).
     * @param int $chatid Telegram chat ID.
     * @param string|null $sharedphone Shared contact phone if available.
     * @return array [success => bool, user => ?stdClass, need_contact => ?bool, error => ?string]
     */
    public static function verify_bot_token(string $token, int $chatid, ?string $sharedphone = null): array {
        global $DB;

        $record = $DB->get_record(self::TABLE_REQUESTS, ['request_id' => $token]);
        if (!$record) {
            return [
                'success' => false,
                'error'   => 'not_found',
                'message' => get_string('error_invalid_request', 'local_telegramotp'),
            ];
        }

        if ($record->status === 'verified') {
            $regdata = json_decode((string)$record->reg_data, true);
            $userid = $regdata['userid'] ?? null;
            $user = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]) : null;
            return [
                'success' => true,
                'user'    => $user,
            ];
        }

        if ($record->status !== 'pending') {
            return [
                'success' => false,
                'error'   => 'invalid_status',
                'message' => get_string('error_invalid_request', 'local_telegramotp'),
            ];
        }

        // Check expiration (15 minutes).
        if ((time() - (int)$record->timecreated) > (15 * MINSECS)) {
            $record->status = 'expired';
            $record->timemodified = time();
            $DB->update_record(self::TABLE_REQUESTS, $record);
            return [
                'success' => false,
                'error'   => 'expired',
                'message' => get_string('error_code_expired', 'local_telegramotp'),
            ];
        }

        $regdata = json_decode((string)$record->reg_data, true);
        if (empty($regdata)) {
            return [
                'success' => false,
                'error'   => 'invalid_payload',
                'message' => get_string('error_invalid_request', 'local_telegramotp'),
            ];
        }

        // Check strict security mode if phone sharing is required.
        $security = (string) get_config('local_telegramotp', 'bot_verification_security');
        if ($security === 'strict') {
            if ($sharedphone === null) {
                return [
                    'success'      => false,
                    'need_contact' => true,
                    'phone'        => $record->phone,
                    'name'         => $regdata['firstname'] . ' ' . $regdata['lastname'],
                ];
            }

            // Normalize and verify match.
            $cleanrecordphone = preg_replace('/[^\d]/', '', $record->phone);
            $cleansharedphone = preg_replace('/[^\d]/', '', $sharedphone);

            if ($cleanrecordphone !== $cleansharedphone) {
                return [
                    'success' => false,
                    'error'   => 'phone_mismatch',
                    'message' => get_string('error_bot_phone_mismatch', 'local_telegramotp'),
                ];
            }
        }

        // Create the user account and link telegram chat id.
        $user = self::create_user_from_bot($regdata, $chatid);

        $regdata['userid'] = (int)$user->id;
        $record->status = 'verified';
        $record->reg_data = json_encode($regdata);
        $record->timemodified = time();
        $DB->update_record(self::TABLE_REQUESTS, $record);

        return [
            'success' => true,
            'user'    => $user,
        ];
    }

    /**
     * Provision Moodle user from bot registration data and link Telegram chat ID.
     *
     * @param array $data Registration payload.
     * @param int $chatid Telegram chat ID.
     * @return stdClass Created or matched user record.
     */
    public static function create_user_from_bot(array $data, int $chatid): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $email = trim($data['email']);
        $phone = trim($data['phone']);

        // Check if user already exists (e.g. concurrent webhook call).
        $existing = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if ($existing) {
            self::save_user_phone((int)$existing->id, $phone);
            set_user_preference('message_processor_telegram_chatid', (string)$chatid, (int)$existing->id);
            return $existing;
        }

        $username = trim($data['username'] ?? '');
        if ($username === '') {
            $username = clean_param($email, PARAM_USERNAME);
            if ($DB->record_exists('user', ['username' => $username, 'deleted' => 0])) {
                $username = clean_param(str_replace('+', '', $phone), PARAM_USERNAME);
            }
        }

        $user = new stdClass();
        $user->auth         = 'manual';
        $user->confirmed    = 1;
        $user->mnethostid   = $CFG->mnet_localhost_id;
        $user->username     = $username;
        $user->password     = $data['password']; // Already hashed during token creation.
        $user->firstname    = trim($data['firstname']);
        $user->lastname     = trim($data['lastname']);
        $user->email        = $email;
        $user->phone1       = $phone;
        $user->country      = $data['country'] ?? 'SA';
        $user->lang         = current_language();
        $user->timecreated  = time();
        $user->timemodified = time();

        $userid = user_create_user($user, false, false);
        $user->id = $userid;

        // Save verified phone to custom profile field.
        self::save_user_phone($userid, $phone);

        // Auto-link chat ID in message_telegram preference so notifications work immediately.
        set_user_preference('message_processor_telegram_chatid', (string)$chatid, $userid);

        return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    /**
     * Check if a bot verification token has been confirmed via Telegram webhook.
     * Logs in the user session if verified.
     *
     * @param string $token Bot verification token (reg_...).
     * @return array [success => bool, verified => bool, redirect_url => string]
     */
    public static function check_bot_verification_status(string $token): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/authlib.php');

        $record = $DB->get_record(self::TABLE_REQUESTS, ['request_id' => $token]);
        if (!$record) {
            return [
                'success'  => false,
                'verified' => false,
                'message'  => get_string('error_invalid_request', 'local_telegramotp'),
            ];
        }

        if ($record->status === 'verified') {
            $regdata = json_decode((string)$record->reg_data, true);
            $userid = $regdata['userid'] ?? null;
            if ($userid) {
                $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
                if ($user) {
                    complete_user_login($user);
                    return [
                        'success'      => true,
                        'verified'     => true,
                        'redirect_url' => (new \moodle_url('/my/'))->out(false),
                        'message'      => get_string('success_registered', 'local_telegramotp'),
                    ];
                }
            }
        }

        if ($record->status === 'pending') {
            if ((time() - (int)$record->timecreated) > (15 * MINSECS)) {
                return [
                    'success'  => false,
                    'verified' => false,
                    'expired'  => true,
                    'message'  => get_string('error_code_expired', 'local_telegramotp'),
                ];
            }

            return [
                'success'  => true,
                'verified' => false,
            ];
        }

        return [
            'success'  => false,
            'verified' => false,
            'message'  => get_string('error_invalid_request', 'local_telegramotp'),
        ];
    }
}
