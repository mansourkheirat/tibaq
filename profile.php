<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';
require_once 'database.php';
require_once 'site-functions.php';
require_once 'roles-config.php';

$auth = new Auth();
$db = Database::getInstance();

// الحصول على اسم المستخدم من الرابط
$username = $_GET['username'] ?? null;

// إذا لم يكن هناك اسم مستخدم، أعد التوجيه
if (!$username) {
    header('Location: ./');
    exit;
}

// البحث عن المستخدم
$query = "SELECT id, fullname, username, email, role, profile_image, phone, address, bio, 
                 created_at, last_login, login_count, is_active 
          FROM TI_users 
          WHERE username = ? AND is_active = 1";
$result = $db->select($query, [$username]);

// إذا لم يُعثر على المستخدم
if (empty($result)) {
    $notFound = true;
    $pageTitle = "المستخدم غير موجود";
} else {
    $profileUser = $result[0];
    $notFound = false;
    $pageTitle = $profileUser['fullname'];
}

// التحقق من المستخدم الحالي
$currentUser = null;
$isOwnProfile = false;
$isLoggedIn = $auth->isLoggedIn();

if ($isLoggedIn) {
    $currentUser = $auth->getCurrentUser();
    $isOwnProfile = ($currentUser['username'] === $username);
}

// الحصول على إحصائيات إضافية
$stats = [];
if (!$notFound) {
    $sessionsQuery = "SELECT COUNT(*) as count FROM TI_sessions WHERE user_id = ? AND is_active = 1";
    $sessionsResult = $db->select($sessionsQuery, [$profileUser['id']]);
    $stats['active_sessions'] = $sessionsResult[0]['count'] ?? 0;
    $stats['last_activity'] = $profileUser['last_login'] ?? 'لم يسجل دخوله بعد';
    
    // حساب عدد الأيام في المنصة
    $joinDate = new DateTime($profileUser['created_at']);
    $now = new DateTime();
    $diff = $now->diff($joinDate);
    $stats['days_in_platform'] = $diff->days;
}

// معالجة تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: ./');
    exit;
}

if (!$notFound) {
    $roleInfo = getRoleInfo($profileUser['role']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle($pageTitle)); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        .profile-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            padding-top: 60px;
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 50px;
        }
        
        /* رأس الملف الشخصي */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 40px;
            margin-top: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .profile-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            text-align: center;
        }
        
        .profile-role-badge {
            margin-bottom: 6px;
        }

        .profile-role-badge .badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 18px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .profile-name-section {
            text-align: center;
            width: 100%;
        }

        .profile-name-section h1 {
            font-size: 48px;
            font-weight: 800;
            margin: 0;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            line-height: 1.2;
        }

        .profile-username {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 8px;
            direction: ltr;
            text-align: center;
            font-weight: 600;
        }

        .profile-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 10px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        /* بطاقات المحتوى */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .content-card:hover {
            transform: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-color: #e0e0e0;
        }
        
        .content-card.full-width {
            grid-column: 1 / -1;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header-icon {
            font-size: 28px;
        }
        
        .card-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
        }
        
        /* معلومات */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .info-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            word-break: break-word;
        }
        
        .info-value.ltr {
            direction: ltr;
            text-align: right;
        }
        
        /* الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: white;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid transparent;
        }
        
        .stat-item:hover {
            transform: none;
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .stat-label {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.95;
        }
        
        /* الأزرار */
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #00A6FB 0%, #0891E6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: none;
            box-shadow: none;
        }

        .btn-outline {
            background: #00A6FB;
            color: white;
        }

        .btn-outline:hover {
            background: #0891E6;

        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid var(--primary-light);
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
            color: #667eea;
            transform: none;
        }

        /* مسافة إضافية تحت شريط الأزرار */
        .actions-card {
            margin-bottom: 35px;
        }
        
        /* السيرة الذاتية */
        .bio-text {
            line-height: 1.8;
            color: #333;
            font-size: 16px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        /* صفحة 404 */
        .not-found-container {
            text-align: center;
            padding: 100px 20px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        .not-found-icon {
            font-size: 120px;
            margin-bottom: 30px;
        }
        
        .not-found-title {
            font-size: 36px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .not-found-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
        }
        
        /* Badge الرتبة */
        .role-badge {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-page {
                padding: 10px;
                padding-top: 50px;
            }
            
            .profile-container {
                margin-top: 30px;
            }
            
            .profile-header {
                padding: 30px 20px;
                border-radius: 20px;
                margin-top: 30px;
            }
            
            .profile-name-section h1 {
                font-size: 32px;
            }
            
            .profile-username {
                font-size: 16px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .content-card {
                padding: 20px;
            }
            
            .card-header h2 {
                font-size: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .profile-name-section h1 {
                font-size: 28px;
            }
            
            .stat-number {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    
<?php include 'navbar.php'; ?>

    <div class="profile-page">
        <div class="profile-container">
            
            <?php if ($notFound): ?>
                <!-- المستخدم غير موجود -->
                <div class="not-found-container">
                    <div class="not-found-icon">😕</div>
                    <h1 class="not-found-title">المستخدم غير موجود</h1>
                    <p class="not-found-message">
                        اسم المستخدم "<strong><?php echo htmlspecialchars($username); ?></strong>" غير موجود أو تم تعطيل الحساب
                    </p>
                    <div class="action-buttons" style="justify-content: center;">
                        <a href="./" class="btn btn-primary">← العودة للرئيسية</a>
                        <?php if ($isLoggedIn): ?>
                            <a href="dashboard" class="btn btn-secondary">لوحة التحكم</a>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- رأس الملف الشخصي -->
                <div class="profile-header">
                    <div class="profile-header-content">
                        <?php if ($isOwnProfile): ?>
                            <div class="badge" style="background: rgba(255,255,255,0.25); margin-bottom: 6px;">✨ أنت</div>
                        <?php endif; ?>

                        <div class="profile-name-section">
                            <div class="profile-role-badge">
                                <?php
                                $badgeStyle = "background-color: {$roleInfo['bg_color']}; color: {$roleInfo['color']};";
                                ?>
                                <span class="badge" style="<?php echo $badgeStyle; ?>">
                                    <?php echo htmlspecialchars($roleInfo['name']); ?>
                                </span>
                            </div>
                            <h1><?php echo htmlspecialchars($profileUser['fullname']); ?></h1>
                            <div class="profile-username">@<?php echo htmlspecialchars($profileUser['username']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- أزرار التحكم -->
                <?php if ($isOwnProfile): ?>
                <div class="content-card full-width actions-card">
                    <div class="action-buttons">
                        <a href="edit-user" class="btn btn-primary btn-outline">
                            <span>✏️</span>
                            <span>تعديل البيانات</span>
                        </a>
                        <a href="change-password" class="btn btn-secondary">
                            <span>🔐</span>
                            <span>تغيير كلمة المرور</span>
                        </a>
                        <a href="dashboard" class="btn btn-secondary">
                            <span>📊</span>
                            <span>لوحة التحكم</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- المحتوى الرئيسي -->
                <div class="content-grid">
                    <!-- المعلومات الأساسية -->
                    <div class="content-card">
                        <div class="card-header">
                            <span class="card-header-icon">📋</span>
                            <h2>المعلومات الأساسية</h2>
                        </div>
                        
                        <div class="info-list">
                            <div class="info-row">
                                <div class="info-label">الاسم الكامل</div>
                                <div class="info-value"><?php echo htmlspecialchars($profileUser['fullname']); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">اسم المستخدم</div>
                                <div class="info-value ltr">@<?php echo htmlspecialchars($profileUser['username']); ?></div>
                            </div>

                            <div class="info-row">
                                <div class="info-label">نوع الحساب</div>
                                <div class="info-value">
                                    <?php
                                    $badgeStyle = "background-color: {$roleInfo['bg_color']}; color: {$roleInfo['color']};";
                                    ?>
                                    <span class="role-badge" style="<?php echo $badgeStyle; ?>">
                                        <?php echo htmlspecialchars($roleInfo['name']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($isOwnProfile || ($isLoggedIn && $currentUser['role'] === 'admin')): ?>
                            <div class="info-row">
                                <div class="info-label">البريد الإلكتروني</div>
                                <div class="info-value ltr"><?php 
                                    $displayEmail = ($profileUser['role'] === 'super_admin') ? getSiteEmail() : $profileUser['email'];
                                    echo htmlspecialchars($displayEmail); 
                                ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($profileUser['phone'] && ($isOwnProfile || ($isLoggedIn && $currentUser['role'] === 'admin'))): ?>
                            <div class="info-row">
                                <div class="info-label">رقم الهاتف</div>
                                <div class="info-value"><?php echo htmlspecialchars($profileUser['phone']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-row">
                                <div class="info-label">تاريخ التسجيل</div>
                                <div class="info-value"><?php echo date('Y-m-d', strtotime($profileUser['created_at'])); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">آخر نشاط</div>
                                <div class="info-value">
                                    <?php 
                                    if ($profileUser['last_login']) {
                                        echo date('Y-m-d H:i', strtotime($profileUser['last_login']));
                                    } else {
                                        echo 'لم يسجل دخوله بعد';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الإحصائيات -->
                    <div class="content-card">
                        <div class="card-header">
                            <span class="card-header-icon">📊</span>
                            <h2>الإحصائيات</h2>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $profileUser['login_count']; ?></div>
                                <div class="stat-label">مرات الدخول</div>
                            </div>
                            
                            <?php if ($isOwnProfile): ?>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['active_sessions']; ?></div>
                                <div class="stat-label">جلسات نشطة</div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['days_in_platform']; ?></div>
                                <div class="stat-label">يوم في المنصة</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- السيرة الذاتية -->
                <?php if ($profileUser['bio']): ?>
                <div class="content-card full-width">
                    <div class="card-header">
                        <span class="card-header-icon">📝</span>
                        <h2>السيرة الذاتية</h2>
                    </div>
                    <div class="bio-text"><?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- العنوان -->
                <?php if ($profileUser['address'] && ($isOwnProfile || ($isLoggedIn && $currentUser['role'] === 'admin'))): ?>
                <div class="content-card full-width">
                    <div class="card-header">
                        <span class="card-header-icon">📍</span>
                        <h2>العنوان</h2>
                    </div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($profileUser['address'])); ?></div>
                </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

    <script src="JS/main.js"></script>
</body>
</html>