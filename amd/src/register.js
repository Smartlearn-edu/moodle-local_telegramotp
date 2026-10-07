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
 * Frontend registration controller with Telegram OTP verification.
 *
 * @module      local_telegramotp/register
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Convert Arabic/Eastern digits to ASCII 0-9.
     *
     * @param {string} str Input string.
     * @returns {string} Clean string.
     */
    function normalizeDigits(str) {
        if (!str) {
            return '';
        }
        var eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        var persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        for (var i = 0; i < 10; i++) {
            str = str.split(eastern[i]).join(String(i));
            str = str.split(persian[i]).join(String(i));
        }
        return str;
    }

    /**
     * Show notification alert on top of form.
     *
     * @param {string} message Text message.
     * @param {string} type Alert type ('danger', 'success', 'warning').
     */
    function showAlert(message, type) {
        var alertBox = document.getElementById('local-telegramotp-alert');
        if (!alertBox) {
            return;
        }
        alertBox.className = 'alert alert-' + type + ' mb-4';
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    /**
     * Hide notification alert.
     */
    function hideAlert() {
        var alertBox = document.getElementById('local-telegramotp-alert');
        if (alertBox) {
            alertBox.classList.add('d-none');
        }
    }

    /**
     * Set button loading state.
     *
     * @param {HTMLButtonElement} btn Button element.
     * @param {boolean} loading True if loading.
     */
    function setBtnLoading(btn, loading) {
        if (!btn) {
            return;
        }
        var spinner = btn.querySelector('.spinner-border');
        var text = btn.querySelector('.btn-text');

        btn.disabled = loading;
        if (spinner) {
            if (loading) {
                spinner.classList.remove('d-none');
            } else {
                spinner.classList.add('d-none');
            }
        }
        if (text) {
            text.style.opacity = loading ? '0.6' : '1';
        }
    }

    /**
     * Registration controller object.
     */
    var registerController = {
        config: null,
        cooldownInterval: null,
        cooldownRemaining: 0,

        /**
         * Initialize registration controller.
         *
         * @param {Object} config Configuration settings passed from PHP.
         */
        init: function(config) {
            this.config = config;
            this.bindEvents();
            this.setupOtpDigitInputs();
        },

        /**
         * Bind UI click and submit events.
         */
        bindEvents: function() {
            var self = this;
            var btnSendOtp = document.getElementById('btn-send-otp');
            var btnVerify = document.getElementById('btn-verify-register');
            var btnResend = document.getElementById('btn-resend-otp');
            var btnBack = document.getElementById('btn-back-to-edit');

            if (btnSendOtp) {
                btnSendOtp.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.sendOtp();
                });
            }

            if (btnVerify) {
                btnVerify.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.verifyAndRegister();
                });
            }

            if (btnResend) {
                btnResend.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!btnResend.disabled) {
                        self.sendOtp();
                    }
                });
            }

            if (btnBack) {
                btnBack.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.showProfileStep();
                });
            }
        },

        /**
         * Setup digit input navigation (auto-focus next, backspace handling).
         */
        setupOtpDigitInputs: function() {
            var self = this;
            var inputs = document.querySelectorAll('.local-telegramotp-digits .otp-digit');

            inputs.forEach(function(input, index) {
                input.addEventListener('input', function(e) {
                    var val = normalizeDigits(e.target.value);
                    val = val.replace(/\D/g, '');
                    input.value = val ? val.charAt(0) : '';

                    if (input.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }

                    // Check if all filled -> auto submit.
                    var fullCode = self.getEnteredCode();
                    if (fullCode.length === inputs.length) {
                        self.verifyAndRegister();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    pasteData = normalizeDigits(pasteData).replace(/\D/g, '');

                    for (var i = 0; i < inputs.length; i++) {
                        if (i < pasteData.length) {
                            inputs[i].value = pasteData.charAt(i);
                        }
                    }

                    var lastIndex = Math.min(pasteData.length, inputs.length) - 1;
                    if (lastIndex >= 0 && lastIndex < inputs.length) {
                        inputs[lastIndex].focus();
                    }

                    if (pasteData.length >= inputs.length) {
                        self.verifyAndRegister();
                    }
                });
            });
        },

        /**
         * Collect currently entered digits.
         *
         * @returns {string} 6-digit code.
         */
        getEnteredCode: function() {
            var inputs = document.querySelectorAll('.local-telegramotp-digits .otp-digit');
            var code = '';
            inputs.forEach(function(inp) {
                code += inp.value;
            });
            return code;
        },

        /**
         * Clear all digit boxes.
         */
        clearCodeInputs: function() {
            var inputs = document.querySelectorAll('.local-telegramotp-digits .otp-digit');
            inputs.forEach(function(inp) {
                inp.value = '';
            });
            if (inputs.length > 0) {
                inputs[0].focus();
            }
        },

        /**
         * Get full international phone number.
         *
         * @returns {string} E.164 phone string.
         */
        getFullPhone: function() {
            var countrySelect = document.getElementById('reg-country-code');
            var phoneInput = document.getElementById('reg-phone');
            var countryCode = countrySelect ? countrySelect.value : '966';
            var rawPhone = phoneInput ? normalizeDigits(phoneInput.value).replace(/\D/g, '') : '';

            if (rawPhone.startsWith('0')) {
                rawPhone = rawPhone.substring(1);
            }

            return '+' + countryCode + rawPhone;
        },

        /**
         * Send OTP request via AJAX.
         */
        sendOtp: function() {
            var self = this;
            hideAlert();

            var firstname = (document.getElementById('reg-firstname').value || '').trim();
            var lastname = (document.getElementById('reg-lastname').value || '').trim();
            var email = (document.getElementById('reg-email').value || '').trim();
            var password = document.getElementById('reg-password').value || '';
            var phoneInput = (document.getElementById('reg-phone').value || '').trim();
            var username = (document.getElementById('reg-username').value || '').trim();
            var honeypot = (document.getElementById('website').value || '').trim();

            if (!firstname || !lastname || !email || !password || !phoneInput) {
                showAlert('Please fill in all required fields.', 'danger');
                return;
            }

            var fullPhone = this.getFullPhone();
            var btnSend = document.getElementById('btn-send-otp');
            setBtnLoading(btnSend, true);

            var formData = new FormData();
            formData.append('sesskey', this.config.sesskey);
            formData.append('action', 'send_otp');
            formData.append('firstname', firstname);
            formData.append('lastname', lastname);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('phone', fullPhone);
            formData.append('username', username);
            formData.append('website', honeypot);

            fetch(this.config.ajaxurl, {
                method: 'POST',
                body: formData,
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                setBtnLoading(btnSend, false);
                if (data.success) {
                    document.getElementById('telegram-request-id').value = data.request_id;
                    self.showOtpStep(fullPhone, data.cooldown || self.config.cooldown);
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message || 'Error sending code', 'danger');
                }
            })
            .catch(function(err) {
                setBtnLoading(btnSend, false);
                showAlert('Connection error: ' + err.message, 'danger');
            });
        },

        /**
         * Switch view to OTP step.
         *
         * @param {string} fullPhone Formatted phone number.
         * @param {number} cooldown Cooldown seconds.
         */
        showOtpStep: function(fullPhone, cooldown) {
            document.getElementById('step-profile-container').classList.add('d-none');
            document.getElementById('step-otp-container').classList.remove('d-none');

            var displayPhone = document.getElementById('display-target-phone');
            if (displayPhone) {
                displayPhone.textContent = fullPhone;
            }

            this.clearCodeInputs();
            this.startCooldown(cooldown);
        },

        /**
         * Switch view back to profile editing step.
         */
        showProfileStep: function() {
            document.getElementById('step-otp-container').classList.add('d-none');
            document.getElementById('step-profile-container').classList.remove('d-none');
            hideAlert();
        },

        /**
         * Start cooldown countdown timer for code resend.
         *
         * @param {number} seconds Seconds remaining.
         */
        startCooldown: function(seconds) {
            var self = this;
            if (this.cooldownInterval) {
                clearInterval(this.cooldownInterval);
            }

            this.cooldownRemaining = seconds;
            var btnResend = document.getElementById('btn-resend-otp');
            var displaySpan = document.getElementById('cooldown-seconds');
            var timerBox = document.getElementById('cooldown-timer-display');

            if (btnResend) {
                btnResend.disabled = true;
            }
            if (timerBox) {
                timerBox.classList.remove('d-none');
            }
            if (displaySpan) {
                displaySpan.textContent = String(this.cooldownRemaining);
            }

            this.cooldownInterval = setInterval(function() {
                self.cooldownRemaining--;
                if (displaySpan) {
                    displaySpan.textContent = String(self.cooldownRemaining);
                }

                if (self.cooldownRemaining <= 0) {
                    clearInterval(self.cooldownInterval);
                    if (btnResend) {
                        btnResend.disabled = false;
                    }
                    if (timerBox) {
                        timerBox.classList.add('d-none');
                    }
                }
            }, 1000);
        },

        /**
         * Verify code and submit registration.
         */
        verifyAndRegister: function() {
            var self = this;
            hideAlert();

            var code = this.getEnteredCode();
            var requestId = document.getElementById('telegram-request-id').value;

            if (!code || code.length < (this.config.codelength || 6)) {
                showAlert('Please enter the complete verification code.', 'danger');
                return;
            }

            var firstname = (document.getElementById('reg-firstname').value || '').trim();
            var lastname = (document.getElementById('reg-lastname').value || '').trim();
            var email = (document.getElementById('reg-email').value || '').trim();
            var password = document.getElementById('reg-password').value || '';
            var username = (document.getElementById('reg-username').value || '').trim();
            var fullPhone = this.getFullPhone();

            var btnVerify = document.getElementById('btn-verify-register');
            setBtnLoading(btnVerify, true);

            var formData = new FormData();
            formData.append('sesskey', this.config.sesskey);
            formData.append('action', 'verify_and_register');
            formData.append('request_id', requestId);
            formData.append('otp', code);
            formData.append('firstname', firstname);
            formData.append('lastname', lastname);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('phone', fullPhone);
            formData.append('username', username);

            fetch(this.config.ajaxurl, {
                method: 'POST',
                body: formData,
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                setBtnLoading(btnVerify, false);
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(function() {
                        window.location.href = data.redirect_url || (M.cfg.wwwroot + '/my/');
                    }, 1000);
                } else {
                    showAlert(data.message || 'Verification failed', 'danger');
                    self.clearCodeInputs();
                }
            })
            .catch(function(err) {
                setBtnLoading(btnVerify, false);
                showAlert('Verification error: ' + err.message, 'danger');
            });
        },
    };

    return registerController;
});
