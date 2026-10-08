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
 * Public registration page with synchronous Telegram OTP verification.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Redirect already logged in users to their dashboard.
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/my/'));
}

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/telegramotp/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('register_title', 'local_telegramotp'));
$PAGE->set_heading(get_string('register_heading', 'local_telegramotp'));

$enabled = (bool) get_config('local_telegramotp', 'enabled');
$provider = (string) get_config('local_telegramotp', 'verification_provider') ?: 'hybrid';
$botusername = \local_telegramotp\manager::get_bot_username();
$defaultcountry = (string) get_config('local_telegramotp', 'default_country') ?: 'SA';
$codelength = (int) get_config('local_telegramotp', 'code_length') ?: 6;
$cooldown = (int) get_config('local_telegramotp', 'cooldown') ?: 60;

// Initialize AMD registration controller.
$jsconfig = [
    'ajaxurl'        => (new moodle_url('/local/telegramotp/ajax.php'))->out(false),
    'sesskey'        => sesskey(),
    'defaultcountry' => $defaultcountry,
    'codelength'     => $codelength,
    'cooldown'       => $cooldown,
    'loginurl'       => (new moodle_url('/login/index.php'))->out(false),
    'provider'       => $provider,
    'botusername'    => $botusername,
];
$PAGE->requires->js_call_amd('local_telegramotp/register', 'init', [$jsconfig]);

echo $OUTPUT->header();

if (!$enabled) {
    echo $OUTPUT->notification(get_string('error_registration_disabled', 'local_telegramotp'), 'warning');
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/login/index.php'),
            get_string('login_here', 'local_telegramotp'),
            ['class' => 'btn btn-primary']
        ),
        'text-center mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// Generate country select options.
$countryoptions = [
    '966' => ['label' => '🇸🇦 +966', 'selected' => ($defaultcountry === 'SA')],
    '20'  => ['label' => '🇪🇬 +20', 'selected' => ($defaultcountry === 'EG')],
    '971' => ['label' => '🇦🇪 +971', 'selected' => ($defaultcountry === 'AE')],
    '965' => ['label' => '🇰🇼 +965', 'selected' => ($defaultcountry === 'KW')],
    '974' => ['label' => '🇶🇦 +974', 'selected' => ($defaultcountry === 'QA')],
    '973' => ['label' => '🇧🇭 +973', 'selected' => ($defaultcountry === 'BH')],
    '968' => ['label' => '🇴🇲 +968', 'selected' => ($defaultcountry === 'OM')],
    '962' => ['label' => '🇯🇴 +962', 'selected' => ($defaultcountry === 'JO')],
    '1'   => ['label' => '🇺🇸 +1', 'selected' => ($defaultcountry === 'US')],
    '44'  => ['label' => '🇬🇧 +44', 'selected' => ($defaultcountry === 'GB')],
];

$countryselecthtml = '<select id="reg-country-code" class="custom-select form-control" style="max-width: 130px;">';
foreach ($countryoptions as $code => $opt) {
    $sel = $opt['selected'] ? ' selected="selected"' : '';
    $countryselecthtml .= '<option value="' . s($code) . '"' . $sel . '>' . s($opt['label']) . '</option>';
}
$countryselecthtml .= '</select>';

// Generate individual OTP digit boxes.
$digitshtml = '';
for ($i = 0; $i < $codelength; $i++) {
    $digitshtml .= '<input type="text" class="form-control otp-digit text-center mx-1 font-weight-bold" '
        . 'maxlength="1" inputmode="numeric" pattern="[0-9]*" data-index="' . $i . '" '
        . 'aria-label="OTP Digit ' . ($i + 1) . '" />';
}

$loginurl = (new moodle_url('/login/index.php'))->out();

$svgicon = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M21.5 2 L2 9.5 L9.5 13 L13 21.5 L21.5 2 Z" fill="#2AABEE" stroke="none"/>'
    . '<path d="M9.5 13 L13.5 9" stroke="#fff" stroke-width="1.8"/>'
    . '</svg>';

// Step 1 Submit buttons based on verification provider.
if ($provider === 'bot') {
    $profilebuttonshtml = '
        <button type="button" id="btn-start-bot" class="btn btn-primary btn-block btn-lg w-100 font-weight-bold shadow-sm">
            <span class="btn-text">🚀 ' . get_string('verify_with_bot', 'local_telegramotp') . '</span>
            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
        </button>
    ';
} else if ($provider === 'gateway') {
    $profilebuttonshtml = '
        <button type="button" id="btn-send-otp" class="btn btn-primary btn-block btn-lg w-100 font-weight-bold shadow-sm">
            <span class="btn-text">' . get_string('send_code', 'local_telegramotp') . '</span>
            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
        </button>
    ';
} else {
    // Hybrid mode.
    $profilebuttonshtml = '
        <button type="button" id="btn-send-otp" class="btn btn-primary btn-block btn-lg w-100 font-weight-bold shadow-sm mb-2">
            <span class="btn-text">' . get_string('send_code', 'local_telegramotp') . '</span>
            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
        </button>
        <div class="text-center my-2 text-muted small">
            <span>—— ' . get_string('or', 'moodle') . ' ——</span>
        </div>
        <button type="button" id="btn-choose-bot" class="btn btn-outline-info btn-block w-100 font-weight-bold">
            <span class="btn-text">🤖 ' . get_string('verify_with_bot', 'local_telegramotp') . '</span>
            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
        </button>
    ';
}

$fallbacktobothtml = '';
if ($provider === 'hybrid') {
    $fallbacktobothtml = '
        <div class="mt-3 pt-2 border-top text-center">
            <button type="button" id="link-fallback-to-bot" class="btn btn-link btn-sm text-info p-0 text-decoration-none font-weight-bold">
                ' . get_string('or_verify_with_bot', 'local_telegramotp') . ' →
            </button>
        </div>
    ';
}

$switchtocodehtml = '';
if ($provider === 'hybrid') {
    $switchtocodehtml = '
        <button type="button" id="btn-switch-to-code" class="btn btn-outline-secondary btn-block btn-sm mb-2">
            ' . get_string('or_use_code', 'local_telegramotp') . '
        </button>
    ';
}

$content = '
<div class="local-telegramotp-container">
    <div class="card local-telegramotp-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="local-telegramotp-icon-badge mb-3">' . $svgicon . '</div>
                <h2 class="card-title font-weight-bold mb-1">' . get_string('register_heading', 'local_telegramotp') . '</h2>
                <p class="text-muted small">' . get_string('register_subheading', 'local_telegramotp') . '</p>
            </div>

            <div id="local-telegramotp-alert" class="alert d-none mb-4" role="alert"></div>

            <form id="local-telegramotp-form" autocomplete="off" onsubmit="return false;">
                <div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true">
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" />
                </div>

                <input type="hidden" id="telegram-request-id" name="request_id" value="" />
                <input type="hidden" id="telegram-bot-token" name="bot_token" value="" />

                <div id="step-profile-container">
                    <div class="form-row row">
                        <div class="form-group col-md-6 mb-3">
                            <label for="reg-firstname" class="form-label font-weight-bold">'
                                . get_string('firstname') . ' <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="reg-firstname" required="required" '
                                . 'placeholder="' . s(get_string('firstname')) . '" />
                        </div>
                        <div class="form-group col-md-6 mb-3">
                            <label for="reg-lastname" class="form-label font-weight-bold">'
                                . get_string('lastname') . ' <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="reg-lastname" required="required" '
                                . 'placeholder="' . s(get_string('lastname')) . '" />
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="reg-email" class="form-label font-weight-bold">'
                            . get_string('email') . ' <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="reg-email" required="required" '
                            . 'placeholder="name@example.com" />
                    </div>

                    <div class="form-group mb-3">
                        <label for="reg-username" class="form-label font-weight-bold">'
                            . get_string('username') . ' <span class="text-muted font-weight-normal small">('
                            . get_string('optional', 'local_telegramotp') . ')</span>
                        </label>
                        <input type="text" class="form-control" id="reg-username" '
                            . 'placeholder="' . s(get_string('username_placeholder', 'local_telegramotp')) . '" />
                    </div>

                    <div class="form-group mb-3">
                        <label for="reg-password" class="form-label font-weight-bold">'
                            . get_string('password') . ' <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="reg-password" required="required" '
                            . 'placeholder="••••••••" />
                    </div>

                    <div class="form-group mb-4">
                        <label for="reg-phone" class="form-label font-weight-bold">'
                            . get_string('phone', 'local_telegramotp') . ' <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">' . $countryselecthtml . '</div>
                            <input type="tel" class="form-control" id="reg-phone" required="required" '
                                . 'placeholder="50 123 4567" />
                        </div>
                        <small class="form-text text-muted">'
                            . get_string('phone_help', 'local_telegramotp') . '</small>
                    </div>

                    ' . $profilebuttonshtml . '
                </div>

                <div id="step-otp-container" class="d-none mt-2">
                    <div class="local-telegramotp-otp-box p-4 rounded mb-4 text-center">
                        <div class="mb-2 font-weight-bold">'
                            . get_string('enter_code', 'local_telegramotp') . '</div>
                        <p class="text-muted small mb-3">'
                            . get_string('enter_code_help', 'local_telegramotp') . '<br>'
                            . '<span id="display-target-phone" class="font-weight-bold text-dark dir-ltr"></span>
                        </p>

                        <div class="local-telegramotp-digits d-flex justify-content-center mb-3" dir="ltr">'
                            . $digitshtml . '
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 text-muted small">
                            <button type="button" id="btn-resend-otp" class="btn btn-link btn-sm p-0 '
                                . 'text-decoration-none" disabled="disabled">'
                                . get_string('resend_code', 'local_telegramotp') . '
                            </button>
                            <span id="cooldown-timer-display" class="badge badge-light p-2">'
                                . get_string('resend_in', 'local_telegramotp') . ' '
                                . '<strong id="cooldown-seconds">' . $cooldown . '</strong>s
                            </span>
                        </div>

                        ' . $fallbacktobothtml . '
                    </div>

                    <button type="button" id="btn-verify-register" class="btn btn-success btn-block btn-lg '
                        . 'w-100 font-weight-bold shadow-sm">
                        <span class="btn-text">' . get_string('verify_and_register', 'local_telegramotp') . '</span>
                        <span class="spinner-border spinner-border-sm d-none ml-2" role="status" '
                            . 'aria-hidden="true"></span>
                    </button>

                    <button type="button" id="btn-back-to-edit" class="btn btn-link btn-block btn-sm '
                        . 'text-muted mt-2">
                        ← ' . get_string('change_phone_number', 'local_telegramotp') . '
                    </button>
                </div>

                <div id="step-bot-container" class="d-none mt-2">
                    <div class="local-telegramotp-otp-box p-4 rounded mb-4 text-center border">
                        <div class="mb-2 font-weight-bold text-primary h5">
                            ' . get_string('verify_with_bot', 'local_telegramotp') . '
                        </div>
                        <p class="text-muted small mb-3">
                            ' . get_string('bot_verification_instructions', 'local_telegramotp') . '
                        </p>

                        <a id="btn-open-telegram-bot" href="#" target="_blank" rel="noopener noreferrer"
                           class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm text-white text-decoration-none d-flex align-items-center justify-content-center">
                            <svg class="mr-2" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.37.74-.56 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06-.01.19-.03.32z"/>
                            </svg>
                            ' . get_string('open_telegram_bot', 'local_telegramotp') . '
                        </a>

                        <div class="mt-4 pt-2 d-flex align-items-center justify-content-center text-muted small">
                            <div class="spinner-grow spinner-grow-sm text-primary mr-2" role="status"></div>
                            <span id="bot-wait-status">' . get_string('waiting_for_bot_verification', 'local_telegramotp') . '</span>
                        </div>
                    </div>

                    ' . $switchtocodehtml . '

                    <button type="button" id="btn-bot-back" class="btn btn-link btn-block btn-sm text-muted">
                        ← ' . get_string('change_phone_number', 'local_telegramotp') . '
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 pt-3 border-top">
                <span class="text-muted small">' . get_string('already_have_account', 'local_telegramotp') . '</span>
                <a href="' . $loginurl . '" class="font-weight-bold text-primary ml-1">'
                    . get_string('login_here', 'local_telegramotp') . '
                </a>
            </div>
        </div>
    </div>
</div>';

echo $content;
echo $OUTPUT->footer();
