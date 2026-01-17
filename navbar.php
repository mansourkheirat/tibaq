<?php
/**
 * شريط القائمة المتحرك - مكون مستقل (محدث)
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
            <span id="navbar-day-name" class="day-name">جاري التحميل...</span>
            <div class="dates-column">
                <div class="date-row">
                    <span id="navbar-hijri-date" class="date-text">جاري التحميل...</span>
                    <span class="date-label">هجري</span>
                </div>
                <div class="date-row">
                    <span id="navbar-gregorian-date" class="date-text">جاري التحميل...</span>
                    <span class="date-label">نصراني</span>
                </div>
            </div>
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
                <a href="./" class="nav-link">الرئيسة</a>
                <a href="members" class="nav-link">الأعضاء</a>
                
                <?php if ($isLoggedIn): ?>
                    <!-- بعد تسجيل الدخول -->
                    <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                        <a href="CP" class="nav-link">الإدارة</a>
                    <?php endif; ?>
                    
                    <div class="nav-user-section">
                        <!-- أيقونة التطبيقات -->
                        <div class="apps-wrapper">
                            <button class="apps-toggle" id="appsToggle" onclick="toggleAppsMenu(event)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <!-- شبكة التطبيقات 3x3 -->
                                    <rect x="3" y="3" width="3" height="3"></rect>
                                    <rect x="10.5" y="3" width="3" height="3"></rect>
                                    <rect x="18" y="3" width="3" height="3"></rect>
                                    <rect x="3" y="10.5" width="3" height="3"></rect>
                                    <rect x="10.5" y="10.5" width="3" height="3"></rect>
                                    <rect x="18" y="10.5" width="3" height="3"></rect>
                                    <rect x="3" y="18" width="3" height="3"></rect>
                                    <rect x="10.5" y="18" width="3" height="3"></rect>
                                    <rect x="18" y="18" width="3" height="3"></rect>
                                </svg>
                            </button>
                            
                            <!-- قائمة التطبيقات المنسدلة -->
                            <div class="apps-dropdown" id="appsDropdown">
                                <div class="apps-container">
                                    <!-- صف أول -->
                                    <a href="dashboard" class="app-item" title="لوحتي">
                                        <span class="app-icon">📊</span>
                                        <span class="app-name">لوحتي</span>
                                    </a>
                                    
                                    <a href="change-password" class="app-item" title="تغيير كلمة المرور">
                                        <span class="app-icon">🔐</span>
                                        <span class="app-name">الأمان</span>
                                    </a>
                                    
                                    <a href="edit-user" class="app-item" title="تعديل البيانات">
                                        <span class="app-icon">✏️</span>
                                        <span class="app-name">بيانات</span>
                                    </a>
                                    
                                    <!-- صف ثاني - للمسؤولين فقط -->
                                    <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                                    <a href="administration.php" class="app-item" title="لوحة الإدارة المتقدمة">
                                        <span class="app-icon">👑</span>
                                        <span class="app-name">متقدم</span>
                                    </a>
                                    
                                    <div style="flex: 1;"></div>
                                    <div style="flex: 1;"></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- زر تسجيل الخروج -->
                                <a href="logout" class="app-logout" 
                                   onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                                    <span class="app-logout-icon">🚪</span>
                                    <span class="app-logout-text">تسجيل الخروج</span>
                                </a>
                            </div>
                        </div>
                        
                        <a href="<?php echo htmlspecialchars($user['username']); ?>" class="nav-link user-profile" 
                           style="background: <?php echo htmlspecialchars($userRoleColor); ?>; color: #FFFFFF;">
                            <span><?php echo htmlspecialchars($user['fullname']); ?></span>
                        </a>
                    </div>
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
        <a href="./" class="mobile-link">الرئيسة</a>
        <a href="members" class="mobile-link">الأعضاء</a>
        
        <?php if ($isLoggedIn): ?>
            <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                <a href="CP" class="mobile-link">الإدارة</a>
            <?php endif; ?>
            
            <a href="dashboard" class="mobile-link">لوحتي</a>
            
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
    gap: 12px;
    font-size: 13px;
    color: #6B7280;
    white-space: nowrap;
    flex-shrink: 0;
}

.day-name {
    font-weight: 600;
    color: #374151;
    min-width: auto;
}

.dates-column {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.date-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.date-text {
    font-weight: 500;
}

.date-label {
    font-size: 11px;
    color: #9CA3AF;
    font-weight: 500;
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
    flex-shrink: 0;
    margin: 0 auto;
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
    gap: 0;
    margin-right: auto;
    flex-shrink: 0;
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
    background: #00A6FB;
    color: #FFFFFF;
}

/* ========================================
   قسم المستخدم
   ======================================== */
.nav-user-section {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* زر الملف الشخصي */
.nav-link.user-profile {
    color: #FFFFFF;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 8px;
}

.nav-link.user-profile:hover {
    background: none;
    opacity: 0.9;
}

/* ========================================
   أيقونة التطبيقات والقائمة المنسدلة
   ======================================== */
.apps-wrapper {
    position: relative;
}

.apps-toggle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #374151;
    transition: all 0.3s;
    position: relative;
}

.apps-toggle:hover {
    background: #00A6FB;
    color: #FFFFFF;
}

.apps-toggle.active {
    background: #00A6FB;
    color: #FFFFFF;
}

.apps-toggle svg {
    width: 24px;
    height: 24px;
    stroke-width: 1.5;
}

/* ========================================
   قائمة التطبيقات المنسدلة
   ======================================== */
.apps-dropdown {
    position: absolute;
    top: 100%;
    right: 50%;
    transform: translateX(50%);
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    padding: 16px;
    min-width: 280px;
    margin-top: 8px;
    display: none;
    z-index: 2000;
    border: 1px solid #E5E7EB;
    animation: dropdownSlide 0.3s ease;
}

.apps-dropdown.active {
    display: flex;
    flex-direction: column;
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateX(50%) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(50%) translateY(0);
    }
}

.apps-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #E5E7EB;
}

.app-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 12px;
    border-radius: 12px;
    text-decoration: none;
    color:rgb(0, 0, 0);
    transition: all 0.3s;
    background:white;
    /*border: 1px solid #E5E7EB;*/
}

.app-item:hover {
    background: #e3f2fd;
    color:rgb(0, 0, 0);
    /*transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: #0891E6;*/
}

.app-icon {
    font-size: 28px;
}

.app-name {
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    word-break: break-word;
}

/* زر تسجيل الخروج داخل القائمة */
.app-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 12px;
    text-decoration: none;
    background: #DC2626;
    color: #FFFFFF;
    font-weight: 600;
    font-size: 14px;
    /*transition: all 0.3s;
    border: 1px solid #FECACA;*/
}

.app-logout:hover {
    background:rgb(238, 43, 43);
    color: #FFFFFF;
    /*box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);*/
}

.app-logout-icon {
    font-size: 18px;
}

.app-logout-text {
    font-size: 14px;
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
    background: #DC2626;
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
    
    .apps-dropdown {
        min-width: 240px;
    }
}

@media (max-width: 480px) {
    .navbar-date {
        flex-direction: column;
        gap: 2px;
        align-items: flex-end;
        font-size: 10px;
    }
    
    .apps-container {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .apps-dropdown {
        min-width: 200px;
    }
}
</style>

<script>
// تحديث التاريخ
function updateNavbarDate() {
    const now = new Date();
    
    // اسم اليوم بالعربية
    const days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const dayName = days[now.getDay()];
    
    // التاريخ الميلادي مع أسماء الشهور
    const gregorianDate = now.toLocaleDateString('ar-DZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        calendar: 'gregory'
    });
    
    // التاريخ الهجري مع أسماء الشهور
    const hijriDate = now.toLocaleDateString('ar-DZ-u-ca-islamic-umalqura', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // تحويل الأرقام للعربية
    function arabicNumbers(str) {
        const arabicNums = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return str.replace(/\d/g, d => arabicNums[d]);
    }
    
    const dayEl = document.getElementById('navbar-day-name');
    const hijriEl = document.getElementById('navbar-hijri-date');
    const gregorianEl = document.getElementById('navbar-gregorian-date');
    
    if (dayEl) dayEl.textContent = dayName;
    if (hijriEl) hijriEl.textContent = arabicNumbers(hijriDate);
    if (gregorianEl) gregorianEl.textContent = arabicNumbers(gregorianDate);
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

// تبديل قائمة التطبيقات
function toggleAppsMenu(event) {
    event.stopPropagation();
    const appsToggle = document.getElementById('appsToggle');
    const appsDropdown = document.getElementById('appsDropdown');
    
    appsToggle.classList.toggle('active');
    appsDropdown.classList.toggle('active');
}

// إغلاق قائمة التطبيقات عند الضغط خارجها
document.addEventListener('click', function(event) {
    const appsToggle = document.getElementById('appsToggle');
    const appsDropdown = document.getElementById('appsDropdown');
    
    if (!appsToggle.contains(event.target) && !appsDropdown.contains(event.target)) {
        appsToggle.classList.remove('active');
        appsDropdown.classList.remove('active');
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