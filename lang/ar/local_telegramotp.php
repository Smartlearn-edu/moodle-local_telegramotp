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
 * Arabic language strings for Telegram OTP Registration.
 *
 * @package     local_telegramotp
 * @copyright   2026 Mohammad Nabil <mohammad@smartlearn.education>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'التسجيل عبر رمز تحقق تيليجرام';

// Registration form and UI strings.
$string['register_title'] = 'إنشاء حساب جديد';
$string['register_heading'] = 'إنشاء حساب';
$string['register_subheading'] = 'تسجيل سريع وآمن مع التحقق الفوري عبر تطبيق تيليجرام';
$string['phone'] = 'رقم الجوال';
$string['phone_help'] = 'أدخل رقم جوالك المسجل في تطبيق تيليجرام';
$string['optional'] = 'اختياري';
$string['username_placeholder'] = 'اتركه فارغاً لاستخدام البريد الإلكتروني';
$string['username_help'] = 'اختر اسم مستخدم مخصص أو اتركه فارغاً ليتم استخدام بريدك الإلكتروني تلقائياً.';
$string['send_code'] = 'إرسال رمز التحقق عبر تيليجرام';
$string['sending_code'] = 'جارٍ إرسال الرمز...';
$string['enter_code'] = 'رمز التحقق';
$string['enter_code_help'] = 'يرجى إدخال رمز التحقق المكون من 6 أرقام والمُرسل إلى حسابك في تيليجرام للرقم';
$string['verify_and_register'] = 'تأكيد الرمز وإنشاء الحساب';
$string['verifying'] = 'جارٍ التحقق من الرمز...';
$string['resend_code'] = 'إعادة إرسال الرمز';
$string['resend_in'] = 'إعادة الإرسال متاحة خلال';
$string['change_phone_number'] = 'تغيير رقم الجوال أو تعديل البيانات';
$string['already_have_account'] = 'لديك حساب بالفعل؟';
$string['login_here'] = 'سجل الدخول من هنا';

// Admin settings strings.
$string['setting_enabled'] = 'تفعيل التسجيل عبر تيليجرام';
$string['setting_enabled_desc'] = 'تفعيل تسجيل المستخدمين الجدد مع التحقق الفوري المتزامن عبر بوابة تيليجرام.';
$string['setting_test_mode'] = 'وضع التجربة والتطوير (Test mode)';
$string['setting_test_mode_desc'] = 'محاكاة إرسال الرمز بدون الاتصال ببوابة تيليجرام أو استهلاك الرصيد. مفيد للاختبار والتجربة.';
$string['setting_test_dummy_code'] = 'رمز التحقق التجريبي';
$string['setting_test_dummy_code_desc'] = 'الرمز التجريبي الثابت المقبول في وضع الاختبار (الافتراضي: 123456).';
$string['setting_api_token'] = 'رمز الوصول لبوابة تيليجرام (API Token)';
$string['setting_api_token_desc'] = 'أدخل رمز الـ API الذي حصلت عليه من موقع gateway.telegram.org';
$string['setting_code_length'] = 'طول رمز التحقق';
$string['setting_code_length_desc'] = 'عدد أرقام رمز التحقق المرسل للمستخدم.';
$string['setting_ttl'] = 'صلاحية الرمز بالثواني (TTL)';
$string['setting_ttl_desc'] = 'المدة الزمنية لصلاحية الرمز بالثواني (الافتراضي: 300 ثانية / 5 دقائق).';
$string['setting_cooldown'] = 'فترة الانتظار لإعادة الإرسال';
$string['setting_cooldown_desc'] = 'عدد الثواني قبل السماح للمستخدم بطلب رمز جديد (الافتراضي: 60 ثانية).';
$string['setting_max_ip_attempts'] = 'الحد الأقصى للطلبات لكل عنوان IP بالساعة';
$string['setting_max_ip_attempts_desc'] = 'الحد الأقصى لطلبات التحقق المسموح بها من عنوان IP واحد خلال ساعة واحدة.';
$string['setting_max_phone_attempts'] = 'الحد الأقصى للطلبات لكل رقم خلال 15 دقيقة';
$string['setting_max_phone_attempts_desc'] = 'الحد الأقصى لطلبات التحقق المسموح بها لرقم هاتف واحد خلال 15 دقيقة.';
$string['setting_default_country'] = 'الدولة الافتراضية';
$string['setting_default_country_desc'] = 'مفتاح الدولة الافتراضي المحدد في حقل إدخال رقم الهاتف.';

// Validation and error messages.
$string['error_missing_firstname'] = 'يرجى إدخال الاسم الأول.';
$string['error_missing_lastname'] = 'يرجى إدخال اسم العائلة.';
$string['error_missing_email'] = 'يرجى إدخال البريد الإلكتروني.';
$string['error_invalid_email'] = 'يرجى إدخال بريد إلكتروني صحيح.';
$string['error_email_exists'] = 'يوجد حساب مسجل بالفعل بهذا البريد الإلكتروني.';
$string['error_missing_password'] = 'يرجى إدخال كلمة المرور.';
$string['error_invalid_username'] = 'اسم المستخدم المختار يحتوي على أحرف غير صالحة.';
$string['error_username_exists'] = 'اسم المستخدم هذا مستخدم بالفعل، يرجى اختيار اسم آخر.';
$string['error_invalid_phone'] = 'يرجى إدخال رقم جوال صحيح مع مفتاح الدولة.';
$string['error_phone_exists'] = 'رقم الجوال هذا مسجل بحساب آخر بالفعل.';
$string['error_spam_detected'] = 'تم اكتشاف محاولة إرسال غير مرغوب فيها.';
$string['error_registration_disabled'] = 'التسجيل معطل حالياً.';
$string['error_cooldown_active'] = 'يرجى الانتظار {$a} ثانية قبل طلب رمز جديد.';
$string['error_rate_limit_phone'] = 'تم تجاوز الحد المسموح من الطلبات لهذا الرقم، يرجى المحاولة لاحقاً.';
$string['error_rate_limit_ip'] = 'تم تجاوز الحد المسموح من الطلبات لعنوان الإنترنت الخاص بك، يرجى المحاولة لاحقاً.';
$string['error_telegram_not_configured'] = 'خدمة بوابة تيليجرام غير مهيأة بعد، يرجى التواصل مع إدارة الموقع.';
$string['error_telegram_api'] = 'تعذر إرسال رمز التحقق عبر تيليجرام، يرجى التأكد من الرقم والمحاولة لاحقاً.';
$string['error_phone_not_found_tg'] = 'رقم الجوال هذا غير مسجل في تطبيق تيليجرام. يرجى فتح أو تثبيت تيليجرام أولاً.';
$string['error_invalid_request'] = 'جلسة التحقق غير صالحة أو منتهية، يرجى طلب رمز جديد.';
$string['error_max_attempts'] = 'تم تجاوز الحد الأقصى للمحاولات الخاطئة، يرجى طلب رمز جديد.';
$string['error_invalid_code_remaining'] = 'رمز التحقق غير صحيح. تبقى لك {$a} محاولة.';
$string['error_code_expired'] = 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.';
$string['error_missing_code'] = 'يرجى إدخال رمز التحقق.';
$string['error_code_not_sent'] = 'يرجى طلب رمز التحقق أولاً.';
$string['error_invalid_action'] = 'إجراء غير صالح.';

// Success notifications.
$string['success_code_sent'] = 'تم إرسال رمز التحقق إلى حسابك في تطبيق تيليجرام.';
$string['success_code_sent_test'] = 'تم إرسال الرمز (وضع التجربة مفعّل: استخدم الرمز {$a}).';
$string['success_registered'] = 'تم إنشاء الحساب بنجاح! جارٍ تسجيل الدخول...';

// Privacy API strings.
$string['privacy:metadata:requests'] = 'تخزين طلبات التحقق من الرموز وعناوين IP وحالة التحقق.';
$string['privacy:metadata:requests:phone'] = 'رقم هاتف المستخدم المستخدم للتحقق عبر تيليجرام.';
$string['privacy:metadata:requests:email'] = 'البريد الإلكتروني المدخل أثناء التسجيل.';
$string['privacy:metadata:requests:request_id'] = 'المعرف الفريد لطلب التحقق من بوابة تيليجرام.';
$string['privacy:metadata:requests:ip_address'] = 'عنوان IP الخاص بالعميل لأغراض الأمان والحد من الاستخدام الخاطئ.';
$string['privacy:metadata:requests:status'] = 'حالة طلب التحقق من الرمز.';
$string['privacy:metadata:requests:attempts'] = 'عدد المحاولات غير الصحيحة للتحقق.';
$string['privacy:metadata:requests:timecreated'] = 'تاريخ ووقت إنشاء طلب التحقق.';
$string['privacy:metadata:requests:timemodified'] = 'تاريخ ووقت آخر تحديث لطلب التحقق.';
$string['privacy:metadata:telegram_gateway'] = 'يتم إرسال أرقام الهواتف إلى بوابة تيليجرام لتسليم رموز التحقق.';
$string['privacy:metadata:telegram_gateway:phone_number'] = 'رقم الهاتف المرسل إلى بوابة تيليجرام.';

// Custom user profile field strings.
$string['profile_category_name'] = 'تيليجرام';
$string['profile_field_name'] = 'رقم هاتف تيليجرام';
$string['profile_field_desc'] = 'رقم الهاتف المعتمد لإشعارات تيليجرام والتحقق السريع.';
