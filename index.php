<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';
require_once 'site-functions.php';

$auth = new Auth();

// التحقق من وضع الصيانة لإخفاء رابط التسجيل
$maintenanceMode = getMaintenanceMode();
$isMaintenanceClosed = ($maintenanceMode === 'closed');
$isMaintenanceLocked = ($maintenanceMode === 'locked');
$showRegister = (!$isMaintenanceClosed && !$isMaintenanceLocked);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle()); ?></title>
    
    <!-- ربط ملفات CSS -->
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
</head>
<body>
    
    <!-- استدعاء شريط القائمة الموحد -->
    <?php include 'navbar.php'; ?>

    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        
        <section style="padding: 100px 0; min-height: calc(100vh - 61px);">
            <div class="container">
                <h2 style="text-align: center; color: var(--text-primary); margin-bottom: 50px; font-size: 32px;">
                    <?php if ($auth->isLoggedIn()): ?>
                        <?php $user = $auth->getCurrentUser(); ?>
                        مرحباً بك، <?php echo htmlspecialchars($user['fullname']); ?>! 👋
                    <?php else: ?>
                        مرحباً بك في طباق وإسناد
                    <?php endif; ?>
                </h2>
                <p style="text-align: center; color: var(--text-secondary); font-size: 18px;">
                    منصة متكاملة للإدارة والمحاسبة
                </p>
                
                <?php if ($auth->isLoggedIn()): ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="dashboard" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 18px; transition: all 0.3s;">
                            انتقل إلى الإعدادات
                        </a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <?php if ($showRegister): ?>
                        <a href="register" style="display: inline-block; background: #10B981; color: white; padding: 15px 40px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 18px; transition: all 0.3s;">
                            ابدأ الآن
                        </a>
                        <?php endif; ?>
                        <a href="login" style="display: inline-block; background: #00A6FB; color: white; padding: 15px 40px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 18px; transition: all 0.3s;">
                            تسجيل الدخول
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- ربط ملفات JavaScript -->
    <script src="JS/main.js"></script>
    
</body>
</html>