<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';

$auth = new Auth();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
require_once 'site-functions.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('الإعدادات')); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
        .dashboard-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #00A6FB 0%, #0582CA 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0, 166, 251, 0.3);
        }
        
        .welcome-section h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .user-info-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
        }
        
        .user-info-section h2 {
            color: var(--dark-color);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .info-value {
            color: var(--text-primary);
        }
        
        .user-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-user {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-admin {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .badge-moderator {
            background: #fff3e0;
            color: #e65100;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        
        .btn-primary {
            background: #00A6FB;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0891E6;
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
            color: #00A6FB;
        }
    </style>
</head>
<body>
    
    <!-- استدعاء شريط القائمة الموحد -->
    <?php include 'navbar.php'; ?>

    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        <div class="dashboard-container">
            
            <!-- قسم الترحيب -->
            <div class="welcome-section">
                <h1>أهلاً بك، <?php echo htmlspecialchars($user['fullname']); ?>! 👋</h1>
                <p>نتمنى لك يوماً مثمراً في منصة طباق وإسناد</p>
            </div>

            <!-- بطاقات الإحصائيات - للمدير فقط -->
            <?php if ($user['role'] === 'admin'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">📁</div>
                    <h3>المشاريع</h3>
                    <div class="value">0</div>
                </div>

                <div class="stat-card">
                    <div class="icon">📊</div>
                    <h3>التقارير</h3>
                    <div class="value">0</div>
                </div>

                <div class="stat-card">
                    <div class="icon">✅</div>
                    <h3>المهام المكتملة</h3>
                    <div class="value">0</div>
                </div>

                <div class="stat-card">
                    <div class="icon">⏱️</div>
                    <h3>المهام النشطة</h3>
                    <div class="value">0</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- معلومات المستخدم -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                
                <!-- بيانات الحساب -->
                <div class="user-info-section">
                    <h2>بيانات الحساب</h2>
                    
                    <div class="info-row">
                        <span class="info-label">الاسم الكامل:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['fullname']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">اسم المستخدم:</span>
                        <span class="info-value" style="direction: ltr; text-align: right;">@<?php echo htmlspecialchars($user['username']); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">البريد الإلكتروني:</span>
                        <span class="info-value" style="direction: ltr; text-align: right;"><?php 
                            // إذا كان المدير العام، استخدم بريد الموقع من قاعدة البيانات
                            $displayEmail = ($user['role'] === 'super_admin') ? getSiteEmail() : $user['email'];
                            echo htmlspecialchars($displayEmail); 
                        ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">نوع الحساب:</span>
                        <span class="info-value">
                            <?php
                            $badge_class = '';
                            $badge_icon = '';
                            $badge_text = '';
                            
                            require_once 'roles-config.php';
                            $roleInfo = getRoleInfo($user['role']);
                            $badge_class = 'badge-' . str_replace('_', '-', $user['role']);
                            $badge_icon = $roleInfo['icon'];
                            $badge_text = $roleInfo['name'];
                            ?>
                            <span class="user-badge <?php echo $badge_class; ?>">
                                <?php echo $badge_icon . ' ' . $badge_text; ?>
                            </span>
                        </span>
                    </div>

                    <div class="action-buttons">
                        <a href="edit-user" class="btn btn-primary">تعديل بيانات الحساب</a>
                        <a href="change-password" class="btn btn-secondary">تغيير كلمة المرور</a>
                    </div>
                </div>

                <!-- قسم إضافي -->
                <div class="user-info-section">
                    <h2>قسم إضافي</h2>
                    
                    <div style="padding: 40px; text-align: center; color: #999;">
                        <p style="font-size: 48px; margin-bottom: 15px;">📋</p>
                        <p>هذا القسم جاهز للمحتوى</p>
                        <p style="font-size: 14px; margin-top: 10px;">سيتم إضافة المعلومات لاحقاً</p>
                    </div>
                </div>
                
            </div>

        </div>
    </main>

    <script src="JS/main.js"></script>
</body>
</html>