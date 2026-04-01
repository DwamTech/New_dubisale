# دليل اختبار الاستعادة الكامل (Restore Testing Guide)

## ⚠️ تحذير مهم جداً
**لا تختبر الاستعادة على قاعدة البيانات الحقيقية!**
- الاستعادة ستحذف جميع البيانات الحالية
- اختبر على نسخة تجريبية أولاً
- احتفظ بنسخة احتياطية قبل الاختبار

---

## الإعداد للاختبار

### الخطوة 1: إنشاء قاعدة بيانات تجريبية

```sql
-- افتح phpMyAdmin أو MySQL Command Line
-- أنشئ قاعدة بيانات جديدة للاختبار

CREATE DATABASE nas_dubisale_test;
```

### الخطوة 2: نسخ قاعدة البيانات الحالية

```bash
# في Command Prompt (Windows)
cd C:\xampp\mysql\bin

# انسخ قاعدة البيانات الحالية إلى قاعدة البيانات التجريبية
mysqldump.exe --user=root --host=127.0.0.1 nas_dubisale > backup_original.sql
mysql.exe --user=root --host=127.0.0.1 nas_dubisale_test < backup_original.sql
```

### الخطوة 3: تعديل ملف .env للاختبار

```env
# احفظ نسخة من .env الأصلي أولاً
# ثم عدل اسم قاعدة البيانات

DB_DATABASE=nas_dubisale_test
```

### الخطوة 4: مسح الكاش

```bash
php artisan config:clear
php artisan cache:clear
```

---

## سيناريو الاختبار الكامل

### السيناريو 1: اختبار استعادة قاعدة البيانات فقط

#### الخطوة 1: تسجيل البيانات الحالية

```bash
# افتح Tinker
php artisan tinker

# سجل عدد المستخدمين الحاليين
>>> $userCount = \App\Models\User::count();
>>> echo "Users: $userCount";

# سجل آخر مستخدم
>>> $lastUser = \App\Models\User::latest()->first();
>>> echo "Last user: " . $lastUser->name;

# اخرج من Tinker
>>> exit
```

#### الخطوة 2: إنشاء نسخة احتياطية

```bash
POST http://127.0.0.1:8000/api/admin/backups
Content-Type: application/json
Authorization: Bearer {your_token}

{
    "type": "db"
}
```

**النتيجة المتوقعة**:
```json
{
    "ok": true,
    "message": "Backup created successfully",
    "data": {
        "id": 1,
        "type": "db",
        "status": "success"
    }
}
```

#### الخطوة 3: تعديل البيانات

```bash
php artisan tinker

# أضف مستخدم جديد للاختبار
>>> $testUser = \App\Models\User::create([
...     'name' => 'Test User for Restore',
...     'email' => 'restore_test@example.com',
...     'password' => bcrypt('password123'),
...     'phone' => '01234567890'
... ]);

# تحقق من إضافة المستخدم
>>> $newCount = \App\Models\User::count();
>>> echo "New count: $newCount";

>>> exit
```

#### الخطوة 4: استعادة النسخة الاحتياطية

```bash
POST http://127.0.0.1:8000/api/admin/backups/1/restore
Authorization: Bearer {your_token}
```

**النتيجة المتوقعة**:
```json
{
    "ok": true,
    "message": "Backup restored successfully"
}
```

#### الخطوة 5: التحقق من الاستعادة

```bash
php artisan tinker

# تحقق من عدد المستخدمين (يجب أن يعود للعدد القديم)
>>> $restoredCount = \App\Models\User::count();
>>> echo "Restored count: $restoredCount";

# تحقق من أن المستخدم التجريبي تم حذفه
>>> $testUser = \App\Models\User::where('email', 'restore_test@example.com')->first();
>>> echo $testUser ? "FAILED: User still exists" : "SUCCESS: User was removed";

>>> exit
```

**✅ النجاح**: إذا عاد عدد المستخدمين للعدد القديم وتم حذف المستخدم التجريبي

---

### السيناريو 2: اختبار استعادة الملفات فقط

#### الخطوة 1: تسجيل الملفات الحالية

```bash
# في Command Prompt
cd storage\app\public

# عد الملفات الموجودة
dir /s /b | find /c /v ""
```

#### الخطوة 2: إنشاء نسخة احتياطية للملفات

```bash
POST http://127.0.0.1:8000/api/admin/backups
Content-Type: application/json
Authorization: Bearer {your_token}

{
    "type": "files"
}
```

#### الخطوة 3: إضافة ملف تجريبي

```bash
# أنشئ ملف تجريبي
cd storage\app\public
echo "Test file for restore" > test_restore.txt

# تحقق من وجود الملف
dir test_restore.txt
```

#### الخطوة 4: استعادة النسخة الاحتياطية

```bash
POST http://127.0.0.1:8000/api/admin/backups/2/restore
Authorization: Bearer {your_token}
```

#### الخطوة 5: التحقق من الاستعادة

```bash
# تحقق من حذف الملف التجريبي
cd storage\app\public
dir test_restore.txt
```

**✅ النجاح**: إذا لم يعد الملف التجريبي موجوداً

---

### السيناريو 3: اختبار استعادة كاملة (Full Restore)

#### الخطوة 1: تسجيل الحالة الحالية

```bash
# سجل عدد المستخدمين
php artisan tinker
>>> $userCount = \App\Models\User::count();
>>> echo "Users: $userCount";
>>> exit

# سجل عدد الملفات
cd storage\app\public
dir /s /b | find /c /v ""
```

#### الخطوة 2: إنشاء نسخة احتياطية كاملة

```bash
POST http://127.0.0.1:8000/api/admin/backups
Content-Type: application/json
Authorization: Bearer {your_token}

{
    "type": "full"
}
```

**انتظر حتى تكتمل العملية** (قد تستغرق دقائق حسب حجم البيانات)

#### الخطوة 3: تعديل البيانات والملفات

```bash
# أضف مستخدم تجريبي
php artisan tinker
>>> \App\Models\User::create([
...     'name' => 'Full Restore Test',
...     'email' => 'fullrestore@example.com',
...     'password' => bcrypt('password'),
...     'phone' => '01111111111'
... ]);
>>> exit

# أضف ملف تجريبي
cd storage\app\public
echo "Full restore test file" > full_restore_test.txt
```

#### الخطوة 4: استعادة النسخة الكاملة

```bash
POST http://127.0.0.1:8000/api/admin/backups/3/restore
Authorization: Bearer {your_token}
```

**⏱️ انتظر**: قد تستغرق الاستعادة الكاملة عدة دقائق

#### الخطوة 5: التحقق الشامل

```bash
# 1. تحقق من قاعدة البيانات
php artisan tinker
>>> $count = \App\Models\User::count();
>>> echo "User count: $count (should match original)";
>>> $testUser = \App\Models\User::where('email', 'fullrestore@example.com')->first();
>>> echo $testUser ? "FAILED" : "SUCCESS";
>>> exit

# 2. تحقق من الملفات
cd storage\app\public
dir full_restore_test.txt
# يجب أن يظهر "File Not Found"
```

**✅ النجاح**: إذا عادت البيانات والملفات للحالة القديمة

---

## اختبار متقدم: التحقق من سلامة البيانات

### اختبار 1: التحقق من العلاقات (Relations)

```bash
php artisan tinker

# تحقق من علاقة المستخدمين بالإعلانات
>>> $user = \App\Models\User::with('listings')->first();
>>> echo "User: " . $user->name;
>>> echo "Listings count: " . $user->listings->count();

# تحقق من علاقة الفئات بالحقول
>>> $category = \App\Models\Category::with('fields')->first();
>>> echo "Category: " . $category->name;
>>> echo "Fields count: " . $category->fields->count();

>>> exit
```

### اختبار 2: التحقق من الفهارس (Indexes)

```sql
-- في phpMyAdmin أو MySQL Command Line
USE nas_dubisale_test;

-- تحقق من الفهارس
SHOW INDEX FROM users;
SHOW INDEX FROM listings;
SHOW INDEX FROM categories;
```

### اختبار 3: التحقق من المفاتيح الأجنبية (Foreign Keys)

```sql
-- تحقق من المفاتيح الأجنبية
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = 'nas_dubisale_test'
    AND REFERENCED_TABLE_NAME IS NOT NULL;
```

---

## اختبار الأداء

### قياس وقت الاستعادة

```bash
# قبل الاستعادة، سجل الوقت
# ثم نفذ الاستعادة
POST http://127.0.0.1:8000/api/admin/backups/1/restore

# راقب الوقت في الـ Response
```

**الأوقات المتوقعة**:
- قاعدة بيانات صغيرة (<100MB): 5-30 ثانية
- قاعدة بيانات متوسطة (100-500MB): 30-120 ثانية
- قاعدة بيانات كبيرة (>500MB): 2-10 دقائق

---

## اختبار معالجة الأخطاء

### اختبار 1: استعادة نسخة غير موجودة

```bash
POST http://127.0.0.1:8000/api/admin/backups/999/restore
```

**النتيجة المتوقعة**:
```json
{
    "ok": false,
    "message": "Backup not found"
}
```

### اختبار 2: استعادة نسخة فاشلة

```bash
# أولاً، احصل على ID نسخة فاشلة
GET http://127.0.0.1:8000/api/admin/backups?status=failed

# ثم حاول استعادتها
POST http://127.0.0.1:8000/api/admin/backups/{failed_id}/restore
```

**النتيجة المتوقعة**:
```json
{
    "ok": false,
    "message": "Cannot restore a backup that did not complete successfully"
}
```

### اختبار 3: استعادة مع ملف محذوف

```bash
# احذف ملف النسخة الاحتياطية يدوياً
cd storage\app\private\backups\2026\04
del backup_db_*.sql.gz

# ثم حاول الاستعادة
POST http://127.0.0.1:8000/api/admin/backups/1/restore
```

**النتيجة المتوقعة**:
```json
{
    "ok": false,
    "message": "Backup file not found on disk"
}
```

---

## التحقق من السجلات (Logs)

### أثناء الاستعادة، راقب السجلات

```bash
# في نافذة Command Prompt منفصلة
cd G:\Dwam_Project\backend-nas-dubi
type storage\logs\laravel.log | findstr "restore"
```

**ما يجب أن تراه**:
```
[2026-04-01 12:00:00] local.INFO: restore_started {"backup_id":1}
[2026-04-01 12:00:30] local.INFO: restore_completed {"backup_id":1}
```

**إذا فشلت الاستعادة**:
```
[2026-04-01 12:00:00] local.INFO: restore_started {"backup_id":1}
[2026-04-01 12:00:05] local.ERROR: restore_failed {"backup_id":1,"error":"..."}
```

---

## قائمة التحقق النهائية

### قبل الاختبار
- [ ] إنشاء قاعدة بيانات تجريبية
- [ ] نسخ البيانات الحالية
- [ ] تعديل .env للإشارة لقاعدة البيانات التجريبية
- [ ] مسح الكاش
- [ ] التأكد من تشغيل XAMPP MySQL

### أثناء الاختبار
- [ ] إنشاء نسخة احتياطية ناجحة
- [ ] التحقق من وجود ملف النسخة الاحتياطية
- [ ] تعديل البيانات/الملفات
- [ ] تنفيذ الاستعادة
- [ ] مراقبة السجلات

### بعد الاختبار
- [ ] التحقق من عودة البيانات للحالة القديمة
- [ ] التحقق من حذف التعديلات التجريبية
- [ ] التحقق من سلامة العلاقات
- [ ] التحقق من عمل التطبيق بشكل طبيعي
- [ ] إعادة .env للإعدادات الأصلية

---

## استعادة الإعدادات الأصلية

### بعد انتهاء الاختبار

```bash
# 1. أعد .env للإعدادات الأصلية
DB_DATABASE=nas_dubisale

# 2. امسح الكاش
php artisan config:clear
php artisan cache:clear

# 3. تحقق من الاتصال بقاعدة البيانات الأصلية
php artisan tinker
>>> DB::connection()->getPdo();
>>> \App\Models\User::count();
>>> exit

# 4. (اختياري) احذف قاعدة البيانات التجريبية
# في phpMyAdmin أو MySQL:
# DROP DATABASE nas_dubisale_test;
```

---

## نصائح مهمة

### 1. الأمان
- **لا تختبر على البيانات الحقيقية أبداً**
- احتفظ بنسخة احتياطية قبل أي اختبار
- اختبر في بيئة تطوير منفصلة

### 2. الأداء
- النسخ الاحتياطية الكبيرة تستغرق وقتاً أطول
- تأكد من وجود مساحة كافية على القرص
- راقب استخدام الذاكرة أثناء الاستعادة

### 3. استكشاف الأخطاء
- دائماً راجع `storage/logs/laravel.log`
- استخدم Diagnostics للتحقق من صحة النظام
- تحقق من أن XAMPP MySQL يعمل

### 4. الاختبار الدوري
- اختبر الاستعادة مرة شهرياً على الأقل
- تحقق من سلامة النسخ الاحتياطية القديمة
- وثق أي مشاكل تواجهها

---

## أوامر سريعة للنسخ واللصق

### إنشاء قاعدة بيانات تجريبية
```sql
CREATE DATABASE nas_dubisale_test;
```

### نسخ البيانات
```bash
cd C:\xampp\mysql\bin
mysqldump.exe --user=root --host=127.0.0.1 nas_dubisale > backup_test.sql
mysql.exe --user=root --host=127.0.0.1 nas_dubisale_test < backup_test.sql
```

### إنشاء نسخة احتياطية
```bash
POST http://127.0.0.1:8000/api/admin/backups
{"type": "full"}
```

### استعادة نسخة احتياطية
```bash
POST http://127.0.0.1:8000/api/admin/backups/1/restore
```

### التحقق من البيانات
```bash
php artisan tinker
>>> \App\Models\User::count();
>>> exit
```

---

## الخلاصة

اتبع هذه الخطوات بالترتيب:

1. **الإعداد**: أنشئ قاعدة بيانات تجريبية
2. **النسخ**: انسخ البيانات الحالية
3. **الاختبار**: نفذ سيناريوهات الاختبار الثلاثة
4. **التحقق**: تأكد من نجاح الاستعادة
5. **الاستعادة**: أعد الإعدادات الأصلية

**مدة الاختبار الكامل**: 30-60 دقيقة

**تكرار الاختبار**: مرة شهرياً على الأقل

حظاً موفقاً! 🚀
