<?php
/**
 * شريط القائمة المتحرك - مكون مستقل
 * يتم استدعاؤه في جميع الصفحات ماعدا صفحات المصادقة
 */

// التحقق من وجود Auth
if (!isset($auth)) {
    require_once 'Auth.php';
    $auth = new Auth();
}

// التحقق من وضع الصيانة
require_once 'maintenance-check.php';
$maintenanceMode = getMaintenanceMode();
$isMaintenanceClosed = ($maintenanceMode === 'closed');
$isMaintenanceLocked = ($maintenanceMode === 'locked');
$showRegister = (!$isMaintenanceClosed && !$isMaintenanceLocked);

// تحميل إعدادات الرتب
if (!function_exists('getRoleColor')) {
    require_once 'roles-config.php';
}

// الحصول على معلومات المستخدم
$isLoggedIn = $auth->isLoggedIn();
$user = null;
$userRoleColor = null;
if ($isLoggedIn) {
    $user = $auth->getCurrentUser();
    $userRoleColor = getRoleColor($user['role']);
}
?>

<!-- شريط القائمة المتحرك الموحد -->
<nav class="unified-navbar" id="unifiedNavbar">
    <div class="navbar-container">
        
        <!-- التاريخ الهجري والميلادي - أقصى اليمين -->
        <div class="navbar-date">
            <span id="navbar-hijri-date" class="date-text">جاري التحميل...</span>
            <span class="date-separator">|</span>
            <span id="navbar-gregorian-date" class="date-text">جاري التحميل...</span>
        </div>

        <!-- الشعار - الوسط -->
        <a href="./" class="navbar-logo">
            <span class="logo-icon">📊</span>
            <span class="logo-text">طباق</span>
        </a>

        <!-- الأزرار - أقصى اليسار -->
        <div class="navbar-actions">
            
            <!-- القائمة الرئيسية للشاشات الكبيرة -->
            <div class="navbar-menu">
                <a href="./" class="nav-link">الرئيسية</a>
                <a href="members" class="nav-link">الأعضاء</a>
                
                <?php if ($isLoggedIn): ?>
                    <!-- بعد تسجيل الدخول -->
                    <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                        <a href="CP" class="nav-link">لوحة الإدارة</a>
                    <?php endif; ?>
                    
                    <a href="dashboard" class="nav-link">الإعدادات</a>
                    
                    <a href="<?php echo htmlspecialchars($user['username']); ?>" class="nav-link user-profile" 
                       style="background: <?php echo htmlspecialchars($userRoleColor); ?>; color: #FFFFFF;">
                        <span><?php echo htmlspecialchars($user['fullname']); ?></span>
                    </a>
                    
                    <a href="logout" class="nav-link nav-logout" 
                       onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                        تسجيل الخروج
                    </a>
                <?php else: ?>
                    <!-- قبل تسجيل الدخول -->
                    <a href="login" class="nav-link nav-login">الدخول</a>
                    <?php if ($showRegister): ?>
                    <a href="register" class="nav-link nav-register">التسجيل</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- أيقونة القائمة للموبايل -->
            <button class="navbar-toggle" id="navbarToggle" onclick="toggleNavbarMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

    </div>
</nav>

<!-- القائمة المنسدلة للموبايل -->
<div class="navbar-mobile-menu" id="navbarMobileMenu">
    <div class="mobile-menu-content">
        <a href="./" class="mobile-link">الرئيسية</a>
        <a href="members" class="mobile-link">الأعضاء</a>
        
        <?php if ($isLoggedIn): ?>
            <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                <a href="CP" class="mobile-link">لوحة الإدارة</a>
            <?php endif; ?>
            
            <a href="dashboard" class="mobile-link">الإعدادات</a>
            
            <a href="<?php echo htmlspecialchars($user['username']); ?>" class="mobile-link mobile-profile"
               style="background: <?php echo htmlspecialchars($userRoleColor); ?>; color: #FFFFFF;">
                <span><?php echo htmlspecialchars($user['fullname']); ?></span>
            </a>
            
            <a href="logout" class="mobile-link mobile-logout" 
               onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                تسجيل الخروج
            </a>
        <?php else: ?>
            <a href="login" class="mobile-link mobile-login">الدخول</a>
            <?php if ($showRegister): ?>
            <a href="register" class="mobile-link mobile-register">التسجيل</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* ========================================
   شريط القائمة الموحد
   ======================================== */
.unified-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: #FFFFFF;
    transition: all 0.3s ease;
    padding: 12px 0;
}

/* إضافة الحد عند التمرير */
.unified-navbar.scrolled {
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
    border-bottom: 1px solid #E5E7EB;
}

.navbar-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

/* ========================================
   التاريخ - أقصى اليمين
   ======================================== */
.navbar-date {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #6B7280;
    white-space: nowrap;
}

.date-text {
    font-weight: 500;
}

.date-separator {
    color: #D1D5DB;
}

/* ========================================
   الشعار - الوسط
   ======================================== */
.navbar-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 20px;
    font-weight: 700;
    color: #0891E6;
    transition: all 0.3s;
}

.navbar-logo:hover {
    transform: scale(1.05);
}

.logo-icon {
    font-size: 24px;
}

.logo-text {
    font-size: 20px;
}

/* ========================================
   الأزرار - أقصى اليسار
   ======================================== */
.navbar-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.navbar-menu {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-link {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: #374151;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.nav-link:hover {
    background: #F3F4F6;
    color: #0891E6;
}

/* زر الملف الشخصي */
.nav-link.user-profile {
    color: #FFFFFF;
    font-weight: 600;
}

.nav-link.user-profile:hover {
    opacity: 0.9;
}

/* زر تسجيل الخروج */
.nav-link.nav-logout {
    background: #EF4444;
    color: #FFFFFF;
    font-weight: 600;
}

.nav-link.nav-logout:hover {
    background: #DC2626;
    color: #FFFFFF;
}

/* زر الدخول */
.nav-link.nav-login {
    background: #00A6FB;
    color: #FFFFFF;
    font-weight: 600;
}

.nav-link.nav-login:hover {
    background: #0891E6;
    color: #FFFFFF;
}

/* زر التسجيل */
.nav-link.nav-register {
    background: #10B981;
    color: #FFFFFF;
    font-weight: 600;
}

.nav-link.nav-register:hover {
    background: #0EA872;
    color: #FFFFFF;
}

/* ========================================
   زر القائمة للموبايل
   ======================================== */
.navbar-toggle {
    display: none;
    flex-direction: column;
    gap: 4px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
}

.navbar-toggle span {
    width: 24px;
    height: 2.5px;
    background: #374151;
    border-radius: 2px;
    transition: all 0.3s;
}

.navbar-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.navbar-toggle.active span:nth-child(2) {
    opacity: 0;
}

.navbar-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* ========================================
   القائمة المنسدلة للموبايل
   ======================================== */
.navbar-mobile-menu {
    position: fixed;
    top: 61px;
    left: 0;
    right: 0;
    background: #FFFFFF;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    z-index: 999;
}

.navbar-mobile-menu.active {
    max-height: 500px;
    border-bottom: 1px solid #E5E7EB;
}

.mobile-menu-content {
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mobile-link {
    padding: 12px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: #374151;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mobile-link:hover {
    background: #F3F4F6;
    color: #00A6FB;
}

.mobile-link.mobile-profile {
    color: #FFFFFF;
    font-weight: 600;
}

.mobile-link.mobile-profile:hover {
    opacity: 0.9;
}

.mobile-link.mobile-logout {
    background: #EF4444;
    color: #FFFFFF;
    text-align: center;
    justify-content: center;
}

.mobile-link.mobile-login {
    background: #00A6FB;
    color: #FFFFFF;
    text-align: center;
    justify-content: center;
}

.mobile-link.mobile-register {
    background: #10B981;
    color: #FFFFFF;
    text-align: center;
    justify-content: center;
}

/* ========================================
   المحتوى الرئيسي
   ======================================== */
.main-content {
    margin-top: 61px;
    min-height: calc(100vh - 61px);
}

/* ========================================
   استجابة للشاشات الصغيرة
   ======================================== */
@media (max-width: 992px) {
    .navbar-date .date-text {
        font-size: 12px;
    }
    
    .logo-text {
        display: none;
    }
    
    .navbar-menu {
        gap: 5px;
    }
    
    .nav-link {
        padding: 6px 12px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .navbar-container {
        padding: 0 15px;
    }
    
    .navbar-date {
        font-size: 11px;
        gap: 5px;
    }
    
    .navbar-menu {
        display: none;
    }
    
    .navbar-toggle {
        display: flex;
    }
}

@media (max-width: 480px) {
    .navbar-date {
        flex-direction: column;
        gap: 2px;
        align-items: flex-start;
        font-size: 10px;
    }
    
    .date-separator {
        display: none;
    }
}
</style>

<script>
// تحديث التاريخ
function updateNavbarDate() {
    const now = new Date();
    
    // التاريخ الميلادي
    const gregorianDate = now.toLocaleDateString('ar-DZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        calendar: 'gregory'
    });
    
    // التاريخ الهجري
    const hijriDate = now.toLocaleDateString('ar-DZ-u-ca-islamic-umalqura', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).replace(' هـ', '').replace('،', '');
    
    const hijriEl = document.getElementById('navbar-hijri-date');
    const gregorianEl = document.getElementById('navbar-gregorian-date');
    
    if (hijriEl) hijriEl.textContent = hijriDate + ' هـ';
    if (gregorianEl) gregorianEl.textContent = gregorianDate;
}

// إضافة الحد عند التمرير
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('unifiedNavbar');
    if (window.scrollY > 10) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// تبديل القائمة في الموبايل
function toggleNavbarMenu() {
    const toggle = document.getElementById('navbarToggle');
    const menu = document.getElementById('navbarMobileMenu');
    
    toggle.classList.toggle('active');
    menu.classList.toggle('active');
}

// تهيئة عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    updateNavbarDate();
    setInterval(updateNavbarDate, 60000); // تحديث كل دقيقة
});
</script>