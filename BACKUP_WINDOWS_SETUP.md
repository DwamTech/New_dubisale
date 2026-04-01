# إعداد نظام النسخ الاحتياطي على Windows

## المشكلة التي تم حلها

كان النظام يفشل في إنشاء النسخ الاحتياطية على Windows بسبب:
1. عدم العثور على `mysqldump`
2. مشاكل مع كلمة المرور الفارغة في XAMPP/WAMP
3. مسارات Windows المختلفة

## الحل المطبق

تم تحديث `BackupService` ليدعم:
- ✅ الكشف التلقائي عن نظام التشغيل (Windows/Linux)
- ✅ البحث عن `mysqldump` في المسارات الشائعة
- ✅ دعم كلمة المرور الفارغة (XAMPP/WAMP)
- ✅ إنشاء المجلدات تلقائياً
- ✅ التحقق من إنشاء الملف بنجاح

## المسارات المدعومة تلقائياً

### XAMPP
```
C:\xampp\mysql\bin\mysqldump.exe
C:\xampp\mysql\bin\mysql.exe
```

### WAMP
```
C:\wamp64\bin\mysql\mysql8.0.27\bin\mysqldump.exe
C:\wamp64\bin\mysql\mysql8.0.27\bin\mysql.exe
```

### Laragon
```
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe
```

## إذا استمرت المشكلة

### الخطوة 1: تحقق من وجود mysqldump

افتح Command Prompt وجرب:

```cmd
where mysqldump
```

إذا لم يظهر أي نتيجة، أضف MySQL إلى PATH:

### الخطوة 2: إضافة MySQL إلى PATH

#### لـ XAMPP:
1. افتح **System Properties** → **Environment Variables**
2. في **System Variables**، اختر **Path** → **Edit**
3. أضف: `C:\xampp\mysql\bin`
4. اضغط **OK** وأعد تشغيل الـ terminal

#### لـ WAMP:
1. نفس الخطوات أعلاه
2. أضف: `C:\wamp64\bin\mysql\mysql8.0.27\bin`
   (غير الإصدار حسب نسختك)

### الخطوة 3: تحقق من صلاحيات المجلد

تأكد من أن مجلد `storage/app/backups` قابل للكتابة:

```cmd
cd your-project-path
mkdir storage\app\backups
icacls storage\app\backups /grant Everyone:F
```

### الخطوة 4: اختبر mysqldump يدوياً

```cmd
cd C:\xampp\mysql\bin
mysqldump --user=root --host=127.0.0.1 nas_dubisale > test.sql
```

إذا نجح، فالمشكلة في المسار فقط.

## اختبار النظام

بعد التحديث، جرب:

```bash
POST http://127.0.0.1:8000/api/admin/backups
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "type": "db"
}
```

يجب أن يعمل الآن! ✅

## رسائل الخطأ الشائعة

### "mysqldump not found"
**الحل:** أضف MySQL إلى PATH (انظر الخطوة 2 أعلاه)

### "Database backup file was not created"
**الحل:** تحقق من صلاحيات المجلد (الخطوة 3)

### "Access denied for user"
**الحل:** تحقق من بيانات الاتصال في `.env`:
```env
DB_USERNAME=root
DB_PASSWORD=
DB_HOST=127.0.0.1
DB_DATABASE=nas_dubisale
```

## ملاحظات مهمة

1. **النسخ الاحتياطية الكاملة** قد تستغرق وقتاً طويلاً على Windows
2. **تأكد من وجود مساحة كافية** في القرص
3. **لا تغلق الـ terminal** أثناء إنشاء النسخة الاحتياطية
4. **النسخ الاحتياطية تُحفظ في:** `storage/app/backups/YYYY/MM/`

## الدعم

إذا استمرت المشاكل، تحقق من:
- `storage/logs/laravel.log` - سجل الأخطاء
- `php artisan backup:diagnose` - فحص النظام
- `GET /api/admin/backups/diagnostics` - فحص عبر API

---

**تم التحديث:** 1 أبريل 2026  
**النظام:** Windows + XAMPP/WAMP/Laragon
