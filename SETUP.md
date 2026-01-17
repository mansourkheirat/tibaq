# 🚀 دليل الإعداد السريع - طباق وإسناد

## الخطوات الأساسية (5 دقائق)

### 1️⃣ التحضير

#### تثبيت المتطلبات
```bash
# تأكد من تثبيت:
- XAMPP أو WAMP أو LAMP
- PHP 7.4+
- MySQL 5.7+
```

#### تشغيل الخوادم
```bash
# في XAMPP
- شغّل Apache
- شغّل MySQL
```

### 2️⃣ إعداد المشروع

#### أ. رفع الملفات
```bash
# انسخ مجلد Tibaq إلى:
C:\xampp\htdocs\Tibaq    # Windows
/opt/lampp/htdocs/Tibaq  # Linux
/Applications/XAMPP/htdocs/Tibaq  # Mac
```

#### ب. إعداد قاعدة البيانات
```bash
# افتح config.php وعدّل:
define('DB_HOST', 'localhost');      # عنوان السيرفر
define('DB_NAME', 'tibaq');          # اسم قاعدة البيانات
define('DB_USER', 'root');           # اسم المستخدم
define('DB_PASS', '');               # كلمة المرور (فارغة افتراضياً)
```

### 3️⃣ التثبيت

#### الطريقة الأولى: تثبيت تلقائي (موصى به)
```
1. افتح المتصفح
2. اذهب إلى: http://localhost/Tibaq/install.php
3. اضغط "بدء التثبيت"
4. انتظر حتى يكتمل التثبيت
```

#### الطريقة الثانية: تثبيت يدوي
```
1. افتح phpMyAdmin
2. أنشئ قاعدة بيانات جديدة باسم "tibaq"
3. استورد ملف schema.sql
```

### 4️⃣ الاختبار

```
افتح: http://localhost/Tibaq/test-connection.php

يجب أن ترى:
✅ الاتصال ناجح!
✅ جميع الجداول موجودة
```

### 5️⃣ تسجيل الدخول

```
URL: http://localhost/Tibaq/login

بيانات المدير الافتراضي:
اسم المستخدم: admin
كلمة المرور: Admin@2025
```

---

## ⚠️ حل المشاكل الشائعة

### مشكلة: لا يمكن الاتصال بقاعدة البيانات

**الحل:**
```php
# تحقق من config.php
define('DB_HOST', 'localhost');  // جرب 127.0.0.1
define('DB_USER', 'root');       // تأكد من اسم المستخدم
define('DB_PASS', '');           // تأكد من كلمة المرور
```

### مشكلة: خطأ 404 في الصفحات

**الحل:**
```apache
# تحقق من RewriteBase في .htaccess
RewriteBase /Tibaq/    # إذا كان المشروع في مجلد فرعي
RewriteBase /          # إذا كان في الجذر
```

### مشكلة: الصفحات بيضاء فارغة

**الحل:**
```php
# فعّل عرض الأخطاء مؤقتاً في أول ملف PHP:
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### مشكلة: mod_rewrite غير مفعل

**الحل:**
```apache
# في Apache:
1. افتح httpd.conf
2. ابحث عن: LoadModule rewrite_module
3. احذف # من أول السطر
4. أعد تشغيل Apache
```

---

## 🔒 خطوات الأمان الإضافية

### بعد التثبيت مباشرة:

1. **غيّر كلمة مرور المدير**
```
dashboard.php → تغيير كلمة المرور
```

2. **احذف أو أمّن صفحات التثبيت**
```bash
# احذف أو أعد تسمية:
- install.php
- test-connection.php
```

3. **فعّل HTTPS في الإنتاج**
```apache
# في .htaccess احذف التعليق من:
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

4. **غيّر بيانات قاعدة البيانات**
```sql
-- أنشئ مستخدم جديد لقاعدة البيانات
CREATE USER 'tibaq_user'@'localhost' IDENTIFIED BY 'كلمة_مرور_قوية';
GRANT ALL PRIVILEGES ON tibaq.* TO 'tibaq_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 🎯 اختبار سريع

### تحقق من أن كل شيء يعمل:

```
✅ الصفحة الرئيسية: http://localhost/Tibaq/
✅ تسجيل الدخول: http://localhost/Tibaq/login
✅ لوحة التحكم: http://localhost/Tibaq/dashboard
✅ تسجيل مستخدم: http://localhost/Tibaq/register
```

---

## 📱 الاتصال بالمشروع من الموبايل

### في شبكة محلية واحدة:

```bash
# 1. احصل على IP الجهاز:
ipconfig           # Windows
ifconfig           # Linux/Mac

# 2. من الموبايل افتح:
http://192.168.1.X/Tibaq/
# استبدل X برقم IP جهازك
```

---

## 🔄 التحديثات المستقبلية

### لتحديث المشروع:

```bash
# 1. احفظ نسخة احتياطية
mysqldump -u root -p tibaq > backup.sql

# 2. احفظ ملف config.php

# 3. استبدل الملفات الجديدة

# 4. أعد config.php

# 5. قم بتشغيل أي ملفات تحديث إن وجدت
```

---

## 📞 المساعدة

إذا واجهت مشكلة:

1. **تحقق من السجلات**
```bash
# سجل PHP
error_log

# سجل Apache
error.log
access.log
```

2. **استخدم أدوات التشخيص**
```
http://localhost/Tibaq/test-connection.php
```

3. **تواصل للدعم**
```
البريد: support@tibaq.com
```

---

## ✅ قائمة التحقق النهائية

- [ ] Apache يعمل
- [ ] MySQL يعمل
- [ ] الملفات في المجلد الصحيح
- [ ] config.php معدّل بشكل صحيح
- [ ] install.php نجح
- [ ] test-connection.php يظهر نجاح
- [ ] تسجيل دخول المدير يعمل
- [ ] تم تغيير كلمة المرور الافتراضية

---

🎉 **تهانينا! المشروع جاهز للاستخدام**

الصفحة الرئيسية: http://localhost/Tibaq/
تسجيل الدخول: http://localhost/Tibaq/login

---

💡 **نصيحة:** احفظ نسخة احتياطية من قاعدة البيانات بشكل دوري!