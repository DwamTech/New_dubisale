# تحليل نظام OTP - Joly Taxi

---

## 1. الملفات المتعلقة بالنظام

| الملف | الدور |
|---|---|
| `app/Http/Controllers/Api/AuthController.php` | المنطق الرئيسي لكل عمليات OTP |
| `app/Http/Controllers/Api/OtpController.php` | Controller مساعد (debug/test فقط) |
| `app/Models/OtpVerification.php` | Model جدول التتبع المحلي |
| `database/migrations/2026_03_11_create_otp_verifications_table.php` | إنشاء الجدول الأساسي |
| `database/migrations/2026_03_12_add_payload_to_otp_verifications_table.php` | إضافة حقل payload |
| `database/migrations/2026_03_12_make_otp_code_nullable.php` | جعل code nullable |
| `database/migrations/2026_03_12_add_last_sent_at_to_otp_verifications_table.php` | إضافة last_sent_at |
| `database/migrations/2026_03_12_add_security_fields_to_otp_verifications.php` | إضافة ip_address وindex |
| `database/migrations/2026_03_13_remove_unique_phone_from_otp_verifications.php` | إزالة unique على phone |
| `database/migrations/2026_03_13_add_type_to_otp_verifications_table.php` | إضافة type (registration/password_reset) |

---

## 2. بنية جدول otp_verifications

```
id
phone          - رقم الهاتف
type           - enum: registration | password_reset
code           - nullable (لأن Twilio يتحقق من الكود بنفسه)
payload        - json (بيانات مؤقتة: password مشفر، agent_code، locale، role...)
status         - enum: pending | verified | expired
attempts       - عدد محاولات التحقق الفاشلة
expires_at     - وقت انتهاء الصلاحية (10 دقائق)
verified_at    - وقت التحقق الناجح
last_sent_at   - وقت آخر إرسال (للـ cooldown)
ip_address     - IP المستخدم
created_at
updated_at
```

---

## 3. الـ Endpoints

### 3.1 تسجيل الدخول / التسجيل
```
POST /api/auth/login-or-register
Body: { phone, password, agent_code? }
```

### 3.2 التحقق من OTP وإتمام التسجيل
```
POST /api/auth/verify-otp-register
Body: { phone, code }
Throttle: auth-verify
```

### 3.3 إعادة إرسال OTP
```
POST /api/auth/resend-otp
Body: { phone, type: registration|password_reset }
Throttle: auth-resend
```

### 3.4 نسيان كلمة المرور (إرسال OTP)
```
POST /api/auth/reset-password
Body: { phone }
Throttle: auth-resend
```

### 3.5 التحقق وإعادة تعيين كلمة المرور
```
POST /api/auth/verify-password-reset
Body: { phone, code, new_password, new_password_confirmation }
Throttle: auth-verify
```

### 3.6 OTP Controller (مساعد - debug)
```
POST /api/otp/send     - إرسال OTP مباشر (بدون تسجيل محلي)
POST /api/otp/verify   - تحقق مباشر من Twilio
POST /api/otp/debug    - جلب حالة verification بالـ SID
```

---

## 4. تدفق تسجيل الدخول / التسجيل

```
المستخدم يرسل phone + password
        │
        ▼
هل الرقم موجود في users؟
        │
   ┌────┴────┐
  نعم       لا
   │         │
   ▼         ▼
تحقق من   أرسل OTP عبر Twilio WhatsApp
password   واحفظ record في otp_verifications
   │       بـ type=registration وpayload يحتوي
   │       على password مشفر + agent_code + locale
   ▼         │
إرجع token  ▼
           أرجع status=otp_required
                │
                ▼
        المستخدم يرسل phone + code
        POST /api/auth/verify-otp-register
                │
                ▼
        تحقق من otp_verifications (pending, not expired)
                │
                ▼
        أرسل الكود لـ Twilio للتحقق
                │
           ┌────┴────┐
         approved   rejected
           │         │
           ▼         ▼
    هل الرقم موجود  زود attempts
    في users الآن؟  لو تجاوز الحد → expired
           │
      ┌────┴────┐
     نعم       لا
      │         │
      ▼         ▼
   سجل دخول  أنشئ user جديد
   وأرجع     من payload
   token      وأرجع token
```

---

## 5. تدفق نسيان كلمة المرور

```
المستخدم يرسل phone
POST /api/auth/reset-password
        │
        ▼
هل الرقم موجود في users؟
        │
   ┌────┴────┐
  لا        نعم
   │         │
   ▼         ▼
404        أرسل OTP عبر Twilio WhatsApp
           واحفظ record في otp_verifications
           بـ type=password_reset
           وpayload = { type, user_id }
                │
                ▼
        المستخدم يرسل phone + code + new_password
        POST /api/auth/verify-password-reset
                │
                ▼
        تحقق من otp_verifications (type=password_reset)
                │
                ▼
        أرسل الكود لـ Twilio للتحقق
                │
           ┌────┴────┐
         approved   rejected
           │         │
           ▼         ▼
    حدّث password    زود attempts
    في users         لو تجاوز الحد → expired
    وأرجع token
```

---

## 6. آلية الحماية

| الحماية | التفاصيل |
|---|---|
| Throttle | `auth-verify` و `auth-resend` على مستوى الـ routes |
| Max Attempts | قابل للضبط من `app_settings` (key: `otp_max_attempts`، default: 5) |
| Cooldown | 60 ثانية بين كل إرسال وإعادة إرسال (`last_sent_at`) |
| Expiry | 10 دقائق لكل OTP (`expires_at`) |
| Type Separation | `registration` و `password_reset` منفصلان تماماً |
| Cleanup | حذف الـ records القديمة (pending/expired) قبل إنشاء جديد |

---

## 7. OtpVerification Model - الـ Methods

| Method | الوظيفة |
|---|---|
| `isExpired()` | هل انتهت صلاحية الـ OTP؟ |
| `hasExceededAttempts(int $max)` | هل تجاوز عدد المحاولات؟ |
| `markAsVerified()` | تحديث status=verified + verified_at |
| `markAsExpired()` | تحديث status=expired |
| `incrementAttempts()` | زيادة عداد المحاولات |
| `canResend(int $cooldown)` | هل مر وقت الـ cooldown؟ |
| `remainingCooldown(int $cooldown)` | كم ثانية متبقية للـ cooldown؟ |

---

## 8. الـ Payload المحفوظ في otp_verifications

### نوع registration:
```json
{
  "password": "$2y$10$...",
  "agent_code": 5,
  "locale": "ar",
  "name": "user_1234",
  "role": "user",
  "status": "active"
}
```

### نوع password_reset:
```json
{
  "type": "password_reset",
  "user_id": 42
}
```

---

## 9. OtpController (مساعد)

هذا الـ controller **منفصل** عن AuthController ويُستخدم للـ debug والاختبار المباشر:
- `POST /api/otp/send` - إرسال OTP مباشر بدون تسجيل محلي
- `POST /api/otp/verify` - تحقق مباشر من Twilio بدون أي منطق تسجيل
- `POST /api/otp/debug` - جلب حالة verification بالـ SID

> **ملاحظة:** هذا الـ controller لا يُستخدم في التدفق الرئيسي. يُنصح بحذفه أو تقييده في production.

---

## 10. ملاحظات وتوصيات

| # | الملاحظة |
|---|---|
| 1 | `sendOtpForRegistration` موجود في الكود لكن **معلّق (commented out)** - لا يُستخدم |
| 2 | `OtpController` مكشوف بدون auth middleware - يُنصح بتقييده في production |
| 3 | الـ `code` في الجدول دائماً `null` لأن Twilio يتحقق بنفسه - الجدول للتتبع المحلي فقط |
| 4 | `ip_address` موجود في الجدول لكن **لا يُحفظ** في أي من الـ methods الحالية |
| 5 | لو Twilio غير مهيأ، التسجيل يفشل كلياً - لا يوجد fallback لـ SMS |
| 6 | `otp_max_attempts` يُقرأ من `app_settings` في كل request - يمكن cache هذه القيمة |
