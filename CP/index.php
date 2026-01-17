<?php
/**
 * لوحة الإدارة - Control Panel
 * الصلاحية: Admin و Super Admin فقط
 */

require_once '../Auth.php';
require_once '../database.php';

/**
 * عرض رسالة خطأ وتحويل للصفحة الرئيسية
 */
function showErrorAndRedirect($title, $message) {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطأ - غير مصرح</title>
        <link rel="stylesheet" href="cp_style.css">
        <link rel="stylesheet" href="cp_btn_msg.css">
    </head>
    <body class="error-page-body">
        <div class="error-container">
            <div class="error-icon">🚫</div>
            <h1 class="error-title"><?php echo htmlspecialchars($title); ?></h1>
            <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <p class="redirect-info">سيتم تحويلك إلى الصفحة الرئيسية تلقائياً...</p>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '../';
            }, 3000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

$auth = new Auth();
$db = Database::getInstance();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    // عرض رسالة خطأ وتحويل للصفحة الرئيسية
    showErrorAndRedirect('غير مصرح لك بالوصول إلى هذه الصفحة', 'يجب تسجيل الدخول أولاً');
    exit;
}

$currentUser = $auth->getCurrentUser();

// السماح فقط للمدير العام والمدير بالدخول
$allowedRoles = ['super_admin', 'admin'];

if (!in_array($currentUser['role'], $allowedRoles)) {
    // عرض رسالة خطأ وتحويل للصفحة الرئيسية
    showErrorAndRedirect('غير مصرح لك بالوصول إلى هذه الصفحة', 'هذه الصفحة مخصصة للمديرين فقط');
    exit;
}

// إذا وصلنا هنا، المستخدم لديه الصلاحيات المطلوبة
require_once '../site-functions.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('لوحة الإدارة')); ?></title>
    <link rel="stylesheet" href="cp_style.css">
    <link rel="stylesheet" href="cp_btn_msg.css">
</head>
<body>
    <!-- الشريط الجانبي -->
    <aside class="cp-sidebar">
        <div class="sidebar-header">
            <a href="../<?php echo htmlspecialchars($currentUser['username']); ?>" class="user-greeting">
                <?php echo htmlspecialchars($currentUser['fullname']); ?>
            </a>
            <p class="sidebar-subtitle">مرحبا بك في لوحة التحكم</p>
        </div>
        
        <div class="sidebar-icons">
            <a href="../" class="sidebar-icon" title="الصفحة الرئيسية">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </a>
            <a href="../members" class="sidebar-icon" title="الأعضاء">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
            </a>
            <a href="../logout" class="sidebar-icon" title="تسجيل الخروج" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
            </a>
        </div>

        <!-- قائمة الأزرار -->
        <nav class="sidebar-menu">
            <a href="#" class="sidebar-menu-item" data-page="home" data-item="home">الصفحة الرئيسة</a>
            <a href="#" class="sidebar-menu-item" data-page="settings.php" data-item="settings">إعدادات الموقع</a>
            <a href="#" class="sidebar-menu-item" data-page="members.php" data-item="members">أعضاء الموقع</a>
            <a href="#" class="sidebar-menu-item" data-page="authors_books.php" data-item="authors">المؤلفون والمؤلفات</a>
            <a href="#" class="sidebar-menu-item" data-page="categories_specialties.php" data-item="categories">الفئات والتخصصات</a>
            <a href="#" class="sidebar-menu-item" data-page="narrators.php" data-item="narrators">المسندون</a>
            <a href="#" class="sidebar-menu-item" data-page="tibaq.php" data-item="tibaq">الطباق</a>
        </nav>
    </aside>

    <!-- المحتوى الرئيسي -->
    <main class="cp-main-content" id="cpContent">
        <div class="welcome-message">
            <h1>مرحباً بك في لوحة التحكم</h1>
            <p>اختر أحد الخيارات من القائمة الجانبية للبدء</p>
        </div>
    </main>

    <script>
        // إدارة حالة الأزرار النشطة وتحميل المحتوى
        document.addEventListener('DOMContentLoaded', function() {
            const menuItems = document.querySelectorAll('.sidebar-menu-item');
            const contentArea = document.getElementById('cpContent');
            
            // تعيين الصفحة الرئيسة كخيار افتراضي نشط
            const homeItem = document.querySelector('.sidebar-menu-item[data-page="home"]');
            if (homeItem) {
                homeItem.classList.add('active');
            }
            
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // منع الضغط على الزر النشط
                    if (this.classList.contains('active')) {
                        return false;
                    }
                    
                    const page = this.getAttribute('data-page');
                    
                    // إزالة الحالة النشطة من جميع الأزرار
                    menuItems.forEach(menuItem => {
                        menuItem.classList.remove('active');
                    });
                    
                    // إضافة الحالة النشطة للزر المضغوط
                    this.classList.add('active');
                    
                    // تحميل المحتوى
                    if (page && page !== 'home') {
                        loadPage(page);
                    } else {
                        // عرض رسالة الترحيب
                        contentArea.innerHTML = `
                            <div class="welcome-message">
                                <h1>مرحباً بك في لوحة التحكم</h1>
                                <p>اختر أحد الخيارات من القائمة الجانبية للبدء</p>
                            </div>
                        `;
                    }
                });
            });
            
            // دالة تحميل الصفحة
            function loadPage(page) {
                contentArea.innerHTML = '<div class="loading-spinner">جاري التحميل...</div>';
                
                fetch(page)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('فشل تحميل الصفحة');
                        }
                        return response.text();
                    })
                    .then(html => {
                        contentArea.innerHTML = html;
                        
                        // تنفيذ الـ scripts بعد تحميل HTML
                        const scripts = contentArea.querySelectorAll('script');
                        scripts.forEach(oldScript => {
                            const newScript = document.createElement('script');
                            Array.from(oldScript.attributes).forEach(attr => {
                                newScript.setAttribute(attr.name, attr.value);
                            });
                            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });
                    })
                    .catch(error => {
                        contentArea.innerHTML = `
                            <div class="error-message">
                                <h2>حدث خطأ</h2>
                                <p>فشل تحميل الصفحة: ${error.message}</p>
                            </div>
                        `;
                    });
            }
        });
    </script>
</body>
</html>

