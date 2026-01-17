# 📊 طباق وإسناد - نظام الإدارة والمحاسبة

منصة متكاملة لإدارة الحسابات والطباق مع نظام أمان متقدم.

## 🚀 الميزات الرئيسية

### نظام المصادقة المتقدم
- ✅ تسجيل حسابات جديدة مع التحقق من صحة البيانات
- ✅ تسجيل دخول آمن مع CSRF Protection
- ✅ استعادة كلمة المرور عبر البريد الإلكتروني
- ✅ حماية من الهجمات (Brute Force Protection)
- ✅ قفل الحساب بعد محاولات فاشلة متعددة
- ✅ تشفير كلمات المرور باستخدام Argon2ID
- ✅ خيار "تذكرني" لمدة 30 يوم

### الأمان
- 🔒 CSRF Token Protection
- 🔒 SQL Injection Prevention (PDO Prepared Statements)
- 🔒 XSS Protection
- 🔒 Session Hijacking Prevention
- 🔒 Password Hashing (Argon2ID)
- 🔒 Audit Log لجميع العمليات
- 🔒 Rate Limiting لمحاولات تسجيل الدخول
- 🔒 Secure HTTP Headers

### قاعدة البيانات
- 📦 8 جداول محسّنة بالبادئة TI_
- 📦 Foreign Keys وعلاقات محكمة
- 📦 Indexes للأداء الأمثل
- 📦 Views للاستعلامات المعقدة
- 📦 Triggers لتسجيل التغييرات تلقائياً
- 📦 Events لتنظيف البيانات القديمة

### الإدارة
- 👥 نظام الأدوار (Admin, Moderator, User)
- 👥 نظام الصلاحيات المتقدم
- 👥 سجل التدقيق الشامل
- 👥 إدارة الجلسات النشطة
- 👥 تتبع محاولات تسجيل الدخول

## 📋 المتطلبات

### المتطلبات الأساسية
- PHP 7.4 أو أحدث
- MySQL 5.7+ أو MariaDB 10.2+
- Apache/Nginx مع mod_rewrite
- PDO Extension مفعّل

### المكتبات المطلوبة
- PDO MySQL Driver
- Session Support
- JSON Support

## 🔧 التثبيت

### 1. رفع الملفات
```bash
# ارفع جميع ملفات المشروع إلى المجلد Tibaq/
```

### 2. إعداد قاعدة البيانات
```bash
# افتح ملف config.php وعدّل الإعدادات
define('DB_HOST', 'localhost');
define('DB_NAME', 'tibaq');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. تشغيل التثبيت
```
# افتح المتصفح وانتقل إلى:
http://localhost/Tibaq/install.php

# أو استخدم:
http://localhost/Tibaq/test-connection.php
```

### 4. تسجيل الدخول
```
اسم المستخدم: admin
كلمة المرور: Admin@2025
البريد الإلكتروني: admin@tibaq.com
```

**⚠️ مهم جداً:** غيّر كلمة المرور فوراً بعد أول تسجيل دخول!

## 📁 هيكل المشروع

```
Tibaq/
├── index.php                 # الصفحة الرئيسية
├── login.php                 # تسجيل الدخول
├── register.php              # التسجيل
├── dashboard.php             # لوحة التحكم
├── profile.php               # الملف الشخصي
├── change-password.php       # تغيير كلمة المرور
├── forgot-password.php       # نسيت كلمة المرور
├── reset-password.php        # إعادة تعيين كلمة المرور
├── test-connection.php       # اختبار الاتصال
├── install.php               # صفحة التثبيت
├── .htaccess                 # إعدادات Apache
│
├── PHP Classes/
│   ├── config.php            # إعدادات قاعدة البيانات
│   ├── database.php          # كلاس الاتصال بقاعدة البيانات
│   ├── Auth.php              # نظام المصادقة الكامل
│   └── init.php              # التهيئة والتحقق
│
├── Styles/
│   ├── main.css              # التنسيقات الرئيسية
│   ├── auth.css              # تنسيقات صفحات المصادقة
│   ├── responsive.css        # التصميم المتجاوب
│   └── status.css            # تنسيقات الحالات
│
├── JS/
│   └── main.js               # السكريبتات الرئيسية
│
└── SQL/
    └── schema.sql            # هيكل قاعدة البيانات
```

## 🗄️ قاعدة البيانات

### الجداول الرئيسية

#### TI_users
جدول المستخدمين مع جميع البيانات والإعدادات

#### TI_sessions
إدارة جلسات المستخدمين النشطة

#### TI_login_attempts
تتبع محاولات تسجيل الدخول للحماية

#### TI_notifications
نظام الإشعارات للمستخدمين

#### TI_settings
إعدادات النظام العامة

#### TI_audit_log
سجل التدقيق لجميع العمليات الحساسة

#### TI_permissions
الصلاحيات المتاحة في النظام

#### TI_user_permissions
ربط الصلاحيات بالمستخدمين

## 🔐 الأمان

### الممارسات الأمنية المطبقة

1. **حماية من SQL Injection**
   - استخدام PDO Prepared Statements
   - تنظيف وتحقق من جميع المدخلات

2. **حماية من XSS**
   - تنظيف المخرجات باستخدام htmlspecialchars()
   - Content Security Policy Headers

3. **CSRF Protection**
   - توليد token لكل جلسة
   - التحقق من الـ token في كل نموذج

4. **حماية كلمات المرور**
   - تشفير باستخدام Argon2ID
   - متطلبات قوة كلمة المرور
   - منع استخدام كلمات مرور ضعيفة

5. **حماية من Brute Force**
   - حد أقصى 5 محاولات فاشلة
   - قفل الحساب لمدة 30 دقيقة
   - تسجيل جميع المحاولات

6. **أمان الجلسات**
   - Session Hijacking Prevention
   - Secure & HttpOnly Cookies
   - Session Timeout بعد 24 ساعة

7. **Audit Logging**
   - تسجيل جميع العمليات الحساسة
   - تتبع التغييرات على البيانات
   - تسجيل IP والـ User Agent

## 🎨 التصميم

- تصميم عصري ومتجاوب (Responsive)
- دعم الوضع المظلم (يمكن إضافته)
- متوافق مع جميع الأجهزة
- واجهة سهلة الاستخدام
- التاريخ الهجري والميلادي

## 🚀 الاستخدام

### تسجيل مستخدم جديد
```php
$auth = new Auth();
$result = $auth->register($fullname, $username, $email, $password, $confirm_password);

if ($result['success']) {
    // التسجيل ناجح
} else {
    // عرض الأخطاء: $result['errors']
}
```

### تسجيل الدخول
```php
$auth = new Auth();
$result = $auth->login($username_or_email, $password, $remember);

if ($result['success']) {
    // إعادة توجيه للوحة التحكم
    header('Location: dashboard.php');
} else {
    // عرض الأخطاء: $result['errors']
}
```

### التحقق من تسجيل الدخول
```php
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
```

### التحقق من الصلاحيات
```php
$auth = new Auth();

if ($auth->hasPermission($user_id, 'manage_users')) {
    // المستخدم لديه الصلاحية
}

if ($auth->isAdmin()) {
    // المستخدم مدير
}
```

## 📊 الصيانة

### تنظيف قاعدة البيانات
```php
require_once 'init.php';
$result = cleanupDatabase();
```

يقوم بـ:
- حذف الجلسات المنتهية
- حذف محاولات تسجيل الدخول القديمة (90 يوم)
- حذف الرموز المنتهية
- حذف الإشعارات المقروءة القديمة (30 يوم)

### النسخ الاحتياطي
```bash
# نسخ احتياطي لقاعدة البيانات
mysqldump -u root -p tibaq > backup_$(date +%Y%m%d).sql

# نسخ احتياطي للملفات
tar -czf backup_files_$(date +%Y%m%d).tar.gz Tibaq/
```

## ⚙️ الإعدادات

### إعدادات الأمان (في جدول TI_settings)

| المفتاح | الوصف | القيمة الافتراضية |
|---------|-------|-------------------|
| max_login_attempts | عدد محاولات تسجيل الدخول | 5 |
| account_lockout_duration | مدة القفل بالدقائق | 30 |
| session_lifetime | مدة الجلسة بالدقائق | 1440 |
| password_min_length | الحد الأدنى لطول كلمة المرور | 8 |
| registration_enabled | تفعيل التسجيل | 1 |
| email_verification_required | طلب تأكيد البريد | 0 |

### تعديل الإعدادات
```php
$db = Database::getInstance();
$db->execute(
    "UPDATE TI_settings SET setting_value = ? WHERE setting_key = ?",
    ['10', 'max_login_attempts']
);
```

## 🐛 استكشاف الأخطاء

### خطأ في الاتصال بقاعدة البيانات
1. تحقق من إعدادات config.php
2. تأكد من تشغيل MySQL
3. تحقق من اسم المستخدم وكلمة المرور
4. استخدم test-connection.php للتشخيص

### الجداول غير موجودة
1. افتح install.php
2. اضغط على "بدء التثبيت"
3. أو قم بتشغيل schema.sql يدوياً

### مشاكل في .htaccess
1. تأكد من تفعيل mod_rewrite في Apache
2. تحقق من RewriteBase في .htaccess
3. تأكد من صلاحيات الملفات

### مشاكل في الجلسات
1. تحقق من صلاحيات مجلد الجلسات
2. تأكد من إعدادات session في php.ini
3. تحقق من الكوكيز في المتصفح

## 📝 التطوير المستقبلي

- [ ] نظام البريد الإلكتروني (PHPMailer)
- [ ] تحقق ثنائي (2FA)
- [ ] نظام الأذونات المتقدم
- [ ] لوحة إدارة متكاملة
- [ ] تقارير وإحصائيات
- [ ] نظام الإشعارات الفورية
- [ ] API RESTful
- [ ] تطبيق موبايل

## 🤝 المساهمة

المساهمات مرحب بها! يرجى:
1. عمل Fork للمشروع
2. إنشاء Branch جديد للميزة
3. Commit التغييرات
4. Push إلى الـ Branch
5. فتح Pull Request

## 📄 الترخيص

هذا المشروع مرخص تحت [MIT License](LICENSE)

## 📞 الدعم

للدعم والاستفسارات:
- البريد الإلكتروني: support@tibaq.com
- الموقع: https://tibaq.com

## 🙏 شكر وتقدير

شكراً لاستخدامك طباق وإسناد!

---

**📌 ملاحظة مهمة:** 
هذا المشروع في مرحلة التطوير. يرجى عدم استخدامه في بيئة إنتاج قبل إجراء اختبارات شاملة ومراجعة أمنية كاملة.

**🔒 تذكير أمني:**
- غيّر كلمة المرور الافتراضية فوراً
- فعّل HTTPS في بيئة الإنتاج
- قم بنسخ احتياطي دوري لقاعدة البيانات
- راجع سجل الأمان بشكل منتظم
- حدّث إعدادات الأمان حسب احتياجاتك