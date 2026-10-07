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

namespace local_telegramotp\form;

use local_telegramotp\manager;
use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Registration form definition for Telegram OTP registration.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_form extends moodleform {
    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'userinfo_hdr', get_string('register_heading', 'local_telegramotp'));

        // First name.
        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required', null, 'client');

        // Last name.
        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required', null, 'client');

        // Email.
        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');

        // Username (optional).
        $mform->addElement('text', 'username', get_string('username'));
        $mform->setType('username', PARAM_RAW);
        $mform->addHelpButton('username', 'username_help', 'local_telegramotp');

        // Password.
        $mform->addElement('passwordunmask', 'password', get_string('password'));
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');

        // Phone number.
        $mform->addElement('text', 'phone', get_string('phone', 'local_telegramotp'));
        $mform->setType('phone', PARAM_NOTAGS);
        $mform->addRule('phone', null, 'required', null, 'client');
        $mform->addHelpButton('phone', 'phone_help', 'local_telegramotp');

        // Honeypot field (hidden from view, trap for bots).
        $mform->addElement('hidden', 'website', '');
        $mform->setType('website', PARAM_RAW);

        // Telegram request ID.
        $mform->addElement('hidden', 'request_id', '');
        $mform->setType('request_id', PARAM_RAW);

        // Verification OTP code.
        $mform->addElement('text', 'otp', get_string('enter_code', 'local_telegramotp'));
        $mform->setType('otp', PARAM_NOTAGS);

        // Submit button.
        $this->add_action_buttons(true, get_string('verify_and_register', 'local_telegramotp'));
    }

    /**
     * Server-side validation of submitted form data.
     *
     * @param array $data Form data array.
     * @param array $files Uploaded files.
     * @return array Validation errors array.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Anti-spam check.
        if (!empty($data['website'])) {
            $errors['firstname'] = get_string('error_spam_detected', 'local_telegramotp');
            return $errors;
        }

        $res = manager::validate_registration_data($data);
        if (!$res['valid']) {
            foreach ($res['errors'] as $field => $err) {
                $errors[$field] = $err;
            }
        }

        if (empty($data['request_id'])) {
            $errors['phone'] = get_string('error_code_not_sent', 'local_telegramotp');
        } else if (empty($data['otp'])) {
            $errors['otp'] = get_string('error_missing_code', 'local_telegramotp');
        }

        return $errors;
    }
}
