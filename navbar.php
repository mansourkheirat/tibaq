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

// الحصول على عنوان الموقع من قاعدة البيانات
require_once 'site-functions.php';
$siteName = getSiteName(); // استخراج عنوان الموقع من قاعدة البيانات
?>

<!-- شريط القائمة المتحرك الموحد -->
<nav class="unified-navbar" id="unifiedNavbar">
    <div class="navbar-container">
        
        <!-- الشعار - الوسط -->
        <a href="./" class="navbar-logo">
            <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="perspective: 1000px;">
                <!-- شعار كتاب مفتوح مع ورقة تحتوي على كتابة - يرمز للإسناد الحديثي والعلم -->
                <defs>
                    <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#0891E6;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#0284C7;stop-opacity:1" />
                    </linearGradient>
                </defs>
                
                <!-- الكتاب الخارجي -->
                <path d="M 15 25 Q 50 20 85 25 L 85 75 Q 50 80 15 75 Z" fill="#F3F4F6" stroke="#0891E6" stroke-width="2"/>
                
                <!-- الصفحة اليسرى من الكتاب -->
                <path d="M 15 28 L 50 26 L 50 72 L 15 75 Z" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="1.5"/>
                
                <!-- الصفحة اليمنى من الكتاب -->
                <path d="M 50 26 L 85 28 L 85 75 L 50 72 Z" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="1.5"/>
                
                <!-- خطوط النص على الصفحة اليسرى (ترمز للكتابة والسند) -->
                <line x1="22" y1="36" x2="42" y2="36" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="22" y1="44" x2="42" y2="44" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="22" y1="52" x2="42" y2="52" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="22" y1="60" x2="36" y2="60" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                
                <!-- خطوط النص على الصفحة اليمنى -->
                <line x1="58" y1="36" x2="78" y2="36" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="58" y1="44" x2="78" y2="44" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="58" y1="52" x2="78" y2="52" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                <line x1="58" y1="60" x2="72" y2="60" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                
                <!-- ختم أو شارة في منتصف الكتاب (ترمز للإجازة والتصديق) -->
                <circle cx="50" cy="50" r="6" fill="url(#logoGradient)"/>
                <circle cx="50" cy="50" r="4" fill="none" stroke="#FFFFFF" stroke-width="1.5"/>
                
                <!-- خط معبر في الختم -->
                <line x1="46" y1="50" x2="54" y2="50" stroke="#FFFFFF" stroke-width="1"/>
                <line x1="50" y1="46" x2="50" y2="54" stroke="#FFFFFF" stroke-width="1"/>
            </svg>
            <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
        </a>

        <!-- التاريخ الهجري والميلادي - أقصى اليمين -->
        <div class="navbar-date">
            <span id="navbar-day-name" class="day-name">جاري التحميل...</span>
            <span class="date-separator">|</span>
            <span id="navbar-hijri-date" class="date-text">جاري التحميل...</span>
            <span class="date-label">هجري</span>
            <span class="date-separator">-</span>
            <span id="navbar-gregorian-date" class="date-text">جاري التحميل...</span>
            <span class="date-label">نصراني</span>
        </div>

        <!-- الأزرار - أقصى اليسار -->
        <div class="navbar-actions">
            
            <!-- القائمة الرئيسية للشاشات الكبيرة -->
            <div class="navbar-menu">
                <a href="./" class="nav-link">الرئيسية</a>
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
                                    <rect x="3" y="3" width="3" height="3"></rect>
                                    <rect x="10" y="3" width="3" height="3"></rect>
                                    <rect x="17" y="3" width="3" height="3"></rect>
                                    <rect x="3" y="10" width="3" height="3"></rect>
                                    <rect x="10" y="10" width="3" height="3"></rect>
                                    <rect x="17" y="10" width="3" height="3"></rect>
                                    <rect x="3" y="17" width="3" height="3"></rect>
                                    <rect x="10" y="17" width="3" height="3"></rect>
                                    <rect x="17" y="17" width="3" height="3"></rect>
                                </svg>
                            </button>
                            
                            <!-- قائمة التطبيقات المنسدلة -->
                            <div class="apps-dropdown" id="appsDropdown">
                                <div class="apps-container">
                                    <!-- صف أول -->
                                    <a href="dashboard" class="app-item" title="الإعدادات">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M12 1v6m0 6v6"></path>
                                            <path d="M4.22 4.22l4.24 4.24m3.08 3.08l4.24 4.24"></path>
                                            <path d="M1 12h6m6 0h6"></path>
                                            <path d="M4.22 19.78l4.24-4.24m3.08-3.08l4.24-4.24"></path>
                                        </svg>
                                        <span class="app-name">الإعدادات</span>
                                    </a>
                                    
                                    <a href="edit-user" class="app-item" title="تعديل البيانات">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        <span class="app-name">البيانات</span>
                                    </a>
                                    
                                    <a href="change-password" class="app-item" title="تغيير كلمة المرور">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        <span class="app-name">الأمان</span>
                                    </a>
                                    
                                    <!-- صف ثاني -->
                                    <a href="tabaaq" class="app-item" title="الطباق">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                        </svg>
                                        <span class="app-name">الطباق</span>
                                    </a>
                                    
                                    <a href="asaneed" class="app-item" title="الأسانيد">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"></path>
                                            <polyline points="10 17 14 13 10 9"></polyline>
                                        </svg>
                                        <span class="app-name">الأسانيد</span>
                                    </a>
                                    
                                    <a href="narrators" class="app-item" title="أسماء المسندين">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <span class="app-name">المسندون</span>
                                    </a>
                                    
                                    <!-- صف ثالث -->
                                    <a href="books" class="app-item" title="أسماء الكتب">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                        </svg>
                                        <span class="app-name">الكتب</span>
                                    </a>
                                    
                                    <a href="authors" class="app-item" title="أسماء المؤلفين">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-7l-5-5"></path>
                                            <polyline points="9 9 4 9 4 20 20 20 20 9"></polyline>
                                        </svg>
                                        <span class="app-name">المؤلفون</span>
                                    </a>
                                    
                                    <a href="licenses" class="app-item" title="الإجازات">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                        <span class="app-name">الإجازات</span>
                                    </a>
                                    
                                    <!-- صف رابع - للمسؤولين فقط -->
                                    <?php if (in_array($user['role'], ['super_admin', 'admin'])): ?>
                                    <a href="administration.php" class="app-item" title="لوحة الإدارة المتقدمة">
                                        <svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <path d="M12 1v6m0 6v6"></path>
                                            <path d="M4.22 4.22l4.24 4.24m3.08 3.08l4.24 4.24"></path>
                                            <path d="M1 12h6m6 0h6"></path>
                                            <path d="M4.22 19.78l4.24-4.24m3.08-3.08l4.24-4.24"></path>
                                        </svg>
                                        <span class="app-name">متقدم</span>
                                    </a>
                                    
                                    <div style="flex: 1;"></div>
                                    <div style="flex: 1;"></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- زر تسجيل الخروج -->
                                <a href="logout" class="app-logout" 
                                   onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
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
        <a href="./" class="mobile-link">الرئيسية</a>
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
    gap: 40px;
    position: relative;
}

/* ========================================
   التاريخ - أقصى اليمين
   ======================================== */
.navbar-date {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #6B7280;
    white-space: nowrap;
    flex-shrink: 0;
}

.day-name {
    font-weight: 600;
    color: #374151;
}

.date-text {
    font-weight: 500;
}

.date-label {
    font-size: 11px;
    color: #9CA3AF;
    font-weight: 500;
}

.date-separator {
    color: #D1D5DB;
    font-weight: 400;
}

/* ========================================
   الشعار - الوسط
   ======================================== */
.navbar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 700;
    color: #000000;
    transition: all 0.3s ease;
    flex-shrink: 0;
    order: 2;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
}

.navbar-logo:hover {
    color: #0891E6;
}

.navbar-logo:hover .logo-icon {
    animation: logoRotate 0.6s ease-in-out;
}

.logo-icon {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.logo-text {
    font-size: 16px;
    transition: color 0.3s ease;
}

@keyframes logoRotate {
    0% {
        transform: rotateY(0deg) scale(1);
    }
    50% {
        transform: rotateY(180deg) scale(1.1);
    }
    100% {
        transform: rotateY(360deg) scale(1);
    }
}

/* ========================================
   الأزرار - أقصى اليسار
   ======================================== */
.navbar-actions {
    display: flex;
    align-items: center;
    gap: 0;
    flex-shrink: 0;
    order: 3;
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
   أيقونة التطبيقات
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
    top: calc(100% + 24px);
    right: 50%;
    transform: translateX(50%);
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    padding: 16px;
    min-width: 330px;
    display: none;
    z-index: 2000;
    border: 1px solid #F0F0F0;
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
    border-bottom: 1px solid #F0F0F0;
}

.app-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 10px;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    transition: all 0.3s;
    background: #FAFAFA;
}

.app-item:hover {
    background: #E3F2FD;
    color: #0891E6;
    transform: translateY(-2px);
}

.app-icon {
    width: 28px;
    height: 28px;
    stroke: currentColor;
}

.app-name {
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    word-break: break-word;
}

/* زر تسجيل الخروج */
.app-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 16px;
    border-radius: 12px;
    text-decoration: none;
    background: #DC2626;
    color: #FFFFFF;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.app-logout:hover {
    background: rgb(190, 24, 24);
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
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
        min-width: 280px;
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
        min-width: 240px;
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
    }).replace(/\s*هـ\s*$/, ''); // حذف حرف هـ من النهاية
    
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