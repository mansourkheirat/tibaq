<?php
/**
 * لوحة الإدارة الكاملة - Administration Dashboard
 * الصلاحية: Admin فقط
 */

require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';
require_once 'database.php';
require_once 'roles-config.php';
require_once 'site-functions.php';

$auth = new Auth();
$db = Database::getInstance();

// التحقق من تسجيل الدخول وصلاحية الإدارة
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

$currentUser = $auth->getCurrentUser();

// السماح للمدير العام والمدير بالدخول
$allowedRoles = ['super_admin', 'admin'];

if (!in_array($currentUser['role'], $allowedRoles)) {
    header('Location: dashboard');
    exit;
}

// معالجة AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'رمز الأمان غير صالح']);
        exit;
    }
    
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'toggle_user_status':
            $userId = (int)$_POST['user_id'];
            $newStatus = (int)$_POST['status'];
            
            $result = $db->execute(
                "UPDATE TI_users SET is_active = ? WHERE id = ? AND id != ?",
                [$newStatus, $userId, $currentUser['id']]
            );
            
            if ($result) {
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, ip_address) VALUES (?, 'user_status_changed', 'TI_users', ?, ?)",
                    [$currentUser['id'], $userId, $_SERVER['REMOTE_ADDR'] ?? '']
                );
                
                echo json_encode(['success' => true, 'message' => 'تم تحديث حالة المستخدم']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل التحديث']);
            }
            exit;
            
        case 'delete_user':
            $userId = (int)$_POST['user_id'];
            
            if ($userId === $currentUser['id']) {
                echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الخاص']);
                exit;
            }
            
            $result = $db->execute("DELETE FROM TI_users WHERE id = ?", [$userId]);
            
            if ($result) {
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, ip_address) VALUES (?, 'user_deleted', 'TI_users', ?, ?)",
                    [$currentUser['id'], $userId, $_SERVER['REMOTE_ADDR'] ?? '']
                );
                
                echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل الحذف']);
            }
            exit;
            
        case 'change_user_role':
            $userId = (int)$_POST['user_id'];
            $newRole = isset($_POST['role']) ? trim($_POST['role']) : '';
            
            if (!in_array($newRole, ['user', 'moderator', 'supervisor', 'monitor', 'admin'])) {
                echo json_encode(['success' => false, 'message' => 'دور غير صالح']);
                exit;
            }
            
            $result = $db->execute(
                "UPDATE TI_users SET role = ? WHERE id = ?",
                [$newRole, $userId]
            );
            
            if ($result) {
                $roleInfo = getRoleInfo($newRole);
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) VALUES (?, 'role_changed', 'TI_users', ?, ?, ?, ?)",
                    [$currentUser['id'], $userId, 'old_role', $newRole, $_SERVER['REMOTE_ADDR'] ?? '']
                );
                
                echo json_encode(['success' => true, 'message' => 'تم تحديث دور المستخدم']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل التحديث']);
            }
            exit;
            
        case 'clear_login_attempts':
            $db->execute("DELETE FROM TI_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $db->execute(
                "INSERT INTO TI_audit_log (user_id, action, table_name, ip_address) VALUES (?, 'login_attempts_cleared', 'TI_login_attempts', ?)",
                [$currentUser['id'], $_SERVER['REMOTE_ADDR'] ?? '']
            );
            echo json_encode(['success' => true, 'message' => 'تم مسح السجلات القديمة']);
            exit;
            
        case 'search_users':
            $searchTerm = '%' . $_POST['search'] . '%';
            $users = $db->select(
                "SELECT id, fullname, username, email, role, is_active, created_at FROM TI_users 
                 WHERE fullname LIKE ? OR username LIKE ? OR email LIKE ? LIMIT 20",
                [$searchTerm, $searchTerm, $searchTerm]
            );
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
    }
}

// الحصول على الإحصائيات
$stats = [];

$usersCount = $db->select("SELECT COUNT(*) as count FROM TI_users");
$stats['total_users'] = $usersCount[0]['count'] ?? 0;

$activeUsers = $db->select("SELECT COUNT(*) as count FROM TI_users WHERE is_active = 1");
$stats['active_users'] = $activeUsers[0]['count'] ?? 0;

$admins = $db->select("SELECT COUNT(*) as count FROM TI_users WHERE role = 'admin'");
$stats['admins'] = $admins[0]['count'] ?? 0;

$monitors = $db->select("SELECT COUNT(*) as count FROM TI_users WHERE role = 'monitor'");
$stats['monitors'] = $monitors[0]['count'] ?? 0;

$moderators = $db->select("SELECT COUNT(*) as count FROM TI_users WHERE role = 'moderator'");
$stats['moderators'] = $moderators[0]['count'] ?? 0;

$sessions = $db->select("SELECT COUNT(*) as count FROM TI_sessions WHERE is_active = 1");
$stats['active_sessions'] = $sessions[0]['count'] ?? 0;

$failedLogins = $db->select(
    "SELECT COUNT(*) as count FROM TI_login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$stats['failed_logins_24h'] = $failedLogins[0]['count'] ?? 0;

$newUsers = $db->select(
    "SELECT COUNT(*) as count FROM TI_users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$stats['new_users_7d'] = $newUsers[0]['count'] ?? 0;

$unreadNotifications = $db->select("SELECT COUNT(*) as count FROM TI_notifications WHERE is_read = 0");
$stats['unread_notifications'] = $unreadNotifications[0]['count'] ?? 0;

// الحصول على قائمة المستخدمين
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$users = $db->select(
    "SELECT id, fullname, username, email, role, is_active, created_at, last_login, login_count 
     FROM TI_users ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

if (!is_array($users)) {
    $users = [];
}

// الحصول على آخر محاولات تسجيل الدخول
$recentAttempts = $db->select(
    "SELECT username_or_email, ip_address, success, failure_reason, attempted_at 
     FROM TI_login_attempts ORDER BY attempted_at DESC LIMIT 10"
);

if (!is_array($recentAttempts)) {
    $recentAttempts = [];
}

// الحصول على آخر أنشطة التدقيق
$recentAudits = $db->select(
    "SELECT al.*, u.username 
     FROM TI_audit_log al 
     LEFT JOIN TI_users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 15"
);

if (!is_array($recentAudits)) {
    $recentAudits = [];
}

// الحصول على الجلسات النشطة
$activeSessions = $db->select(
    "SELECT s.*, u.username, u.fullname, u.role
     FROM TI_sessions s 
     JOIN TI_users u ON s.user_id = u.id 
     WHERE s.is_active = 1 
     ORDER BY s.last_activity DESC"
);

if (!is_array($activeSessions)) {
    $activeSessions = [];
}

// الحصول على الإعدادات
$settings = $db->select("SELECT * FROM TI_settings WHERE category = 'security' ORDER BY setting_key");

if (!is_array($settings)) {
    $settings = [];
}

$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('لوحة الإدارة')); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
        :root {
            --admin-primary: #667eea;
            --admin-secondary: #764ba2;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --info-color: #3B82F6;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .admin-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .admin-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.primary {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
        }
        
        .stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .tabs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .tabs-header {
            display: flex;
            background: var(--light-bg);
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 18px 30px;
            background: none;
            border: none;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            position: relative;
        }
        
        .tab-button:hover {
            background: rgba(102, 126, 234, 0.05);
            color: var(--admin-primary);
        }
        
        .tab-button.active {
            color: var(--admin-primary);
            background: white;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--admin-primary);
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 20px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--admin-primary);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-bottom: 20px;
        }
        
        .data-table thead {
            background: var(--light-bg);
        }
        
        .data-table th {
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .data-table tbody tr:hover {
            background: var(--light-bg);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: var(--admin-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--admin-secondary);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #0EA872;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid var(--light-bg);
            border-top: 4px solid var(--admin-primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .admin-header h1 {
                font-size: 24px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs-header {
                flex-wrap: wrap;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 10px 8px;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-input {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    
<?php include 'navbar.php'; ?>

    <main class="main-content">
        <div class="admin-container">
            
            <div class="admin-header">
                <h1>👑 لوحة الإدارة المتقدمة</h1>
                <p>إدارة كاملة للنظام والمستخدمين • مرحباً <?php echo htmlspecialchars($currentUser['fullname']); ?></p>
            </div>

            <div id="alertContainer"></div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                            <div class="stat-label">إجمالي المستخدمين</div>
                        </div>
                        <div class="stat-icon primary">👥</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                            <div class="stat-label">المستخدمون النشطون</div>
                        </div>
                        <div class="stat-icon success">✅</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $stats['active_sessions']; ?></div>
                            <div class="stat-label">الجلسات النشطة</div>
                        </div>
                        <div class="stat-icon warning">🔐</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $stats['failed_logins_24h']; ?></div>
                            <div class="stat-label">محاولات فاشلة (24س)</div>
                        </div>
                        <div class="stat-icon danger">⚠️</div>
                    </div>
                </div>
            </div>

            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-button active" onclick="switchTab('users')">👥 إدارة المستخدمين</button>
                    <button class="tab-button" onclick="switchTab('security')">🔒 الأمان والتدقيق</button>
                    <button class="tab-button" onclick="switchTab('sessions')">🔑 الجلسات النشطة</button>
                    <button class="tab-button" onclick="switchTab('statistics')">📊 الإحصائيات</button>
                </div>

                <!-- تبويب إدارة المستخدمين -->
                <div id="users-tab" class="tab-content active">
                    <div class="search-box">
                        <input type="text" id="userSearch" class="search-input" placeholder="🔍 ابحث عن مستخدم...">
                        <button class="btn btn-primary" onclick="searchUsers()">بحث</button>
                        <button class="btn btn-secondary" onclick="resetSearch()">إعادة تعيين</button>
                    </div>

                    <div class="loading" id="usersLoading">
                        <div class="spinner"></div>
                        <p>جاري التحميل...</p>
                    </div>

                    <div id="usersTableContainer">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>الاسم الكامل</th>
                                    <th>اسم المستخدم</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>الدور</th>
                                    <th>الحالة</th>
                                    <th>تاريخ التسجيل</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr id="user-row-<?php echo $user['id']; ?>">
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                        <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                                        <td style="direction: ltr; text-align: right;">
                                            <?php 
                                            $displayEmail = ($user['role'] === 'super_admin') ? getSiteEmail() : $user['email'];
                                            echo htmlspecialchars($displayEmail); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $roleInfo = getRoleInfo($user['role']);
                                            $badgeStyle = "background-color: {$roleInfo['bg_color']}; color: {$roleInfo['color']};";
                                            ?>
                                            <span class="badge" style="<?php echo $badgeStyle; ?>">
                                                <?php echo htmlspecialchars($roleInfo['name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $user['is_active'] ? '✅ نشط' : '❌ معطل'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($user['id'] !== $currentUser['id'] && $user['id'] !== 1): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                                        <?php echo $user['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                        حذف
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: <?php echo getRoleBgColor($user['role']); ?>; color: <?php echo getRoleColor($user['role']); ?>;">محمي</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="no-data">لا توجد بيانات</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- تبويب الأمان والتدقيق -->
                <div id="security-tab" class="tab-content">
                    <h3 style="margin-bottom: 20px;">📋 آخر محاولات تسجيل الدخول</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>IP Address</th>
                                <th>الحالة</th>
                                <th>سبب الفشل</th>
                                <th>التاريخ والوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAttempts)): ?>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['username_or_email']); ?></td>
                                    <td style="direction: ltr; text-align: right;"><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $attempt['success'] ? 'success' : 'failed'; ?>">
                                            <?php echo $attempt['success'] ? '✅ نجح' : '❌ فشل'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($attempt['failure_reason'] ?? '-'); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($attempt['attempted_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">لا توجد محاولات تسجيل دخول</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 30px;">
                        <button class="btn btn-danger" onclick="clearLoginAttempts()">
                            🗑️ مسح السجلات القديمة (أكثر من 7 أيام)
                        </button>
                    </div>

                    <h3 style="margin: 40px 0 20px;">📜 سجل التدقيق الأخير</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الإجراء</th>
                                <th>الجدول</th>
                                <th>IP Address</th>
                                <th>التاريخ والوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentAudits)): ?>
                                <?php foreach ($recentAudits as $audit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($audit['username'] ?? 'نظام'); ?></td>
                                    <td><?php echo htmlspecialchars($audit['action']); ?></td>
                                    <td><?php echo htmlspecialchars($audit['table_name'] ?? '-'); ?></td>
                                    <td style="direction: ltr; text-align: right;"><?php echo htmlspecialchars($audit['ip_address'] ?? '-'); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($audit['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">لا توجد سجلات تدقيق</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- تبويب الجلسات النشطة -->
                <div id="sessions-tab" class="tab-content">
                    <h3 style="margin-bottom: 20px;">🔑 الجلسات النشطة حالياً</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المستخدم</th>
                                <th>الرتبة</th>
                                <th>IP Address</th>
                                <th>آخر نشاط</th>
                                <th>تاريخ البدء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activeSessions)): ?>
                                <?php foreach ($activeSessions as $session): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($session['fullname']); ?></strong>
                                        <br>
                                        <small style="color: #666;">@<?php echo htmlspecialchars($session['username']); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $roleInfo = getRoleInfo($session['role']);
                                        $badgeStyle = "background-color: {$roleInfo['bg_color']}; color: {$roleInfo['color']};";
                                        ?>
                                        <span class="badge" style="<?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($roleInfo['name']); ?>
                                        </span>
                                    </td>
                                    <td style="direction: ltr; text-align: right;"><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($session['last_activity'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($session['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" onclick="terminateSession(<?php echo $session['id']; ?>)">
                                            إنهاء
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">لا توجد جلسات نشطة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- تبويب الإحصائيات -->
                <div id="statistics-tab" class="tab-content">
                    <h3 style="margin-bottom: 20px;">📊 إحصائيات مفصلة</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-value"><?php echo $stats['admins']; ?></div>
                                    <div class="stat-label">المديرون</div>
                                </div>
                                <div class="stat-icon danger">👑</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-value"><?php echo $stats['monitors']; ?></div>
                                    <div class="stat-label">المراقبون</div>
                                </div>
                                <div class="stat-icon warning">🔍</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-value"><?php echo $stats['moderators']; ?></div>
                                    <div class="stat-label">المشرفون</div>
                                </div>
                                <div class="stat-icon info">🛡️</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div>
                                    <div class="stat-value"><?php echo $stats['new_users_7d']; ?></div>
                                    <div class="stat-label">مستخدمون جدد (7 أيام)</div>
                                </div>
                                <div class="stat-icon success">📈</div>
                            </div>
                        </div>
                    </div>

                    <h3 style="margin: 40px 0 20px;">📈 تحليل النشاط</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>المؤشر</th>
                                <th>القيمة</th>
                                <th>الوصف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalLogins = $db->select("SELECT SUM(login_count) as total FROM TI_users");
                            $avgLoginCount = $db->select("SELECT AVG(login_count) as avg FROM TI_users WHERE login_count > 0");
                            $totalAttempts = $db->select("SELECT COUNT(*) as count FROM TI_login_attempts");
                            $successAttempts = $db->select("SELECT COUNT(*) as count FROM TI_login_attempts WHERE success = 1");
                            $successRate = ($totalAttempts[0]['count'] ?? 0) > 0 
                                ? (($successAttempts[0]['count'] ?? 0) / ($totalAttempts[0]['count'] ?? 1)) * 100 
                                : 0;
                            ?>
                            <tr>
                                <td>إجمالي عمليات تسجيل الدخول</td>
                                <td><strong><?php echo number_format($totalLogins[0]['total'] ?? 0); ?></strong></td>
                                <td>منذ بداية النظام</td>
                            </tr>
                            <tr>
                                <td>متوسط عمليات الدخول للمستخدم</td>
                                <td><strong><?php echo number_format($avgLoginCount[0]['avg'] ?? 0, 1); ?></strong></td>
                                <td>معدل نشاط المستخدمين</td>
                            </tr>
                            <tr>
                                <td>معدل النجاح في تسجيل الدخول</td>
                                <td><strong><?php echo number_format($successRate, 1); ?>%</strong></td>
                                <td>نسبة المحاولات الناجحة</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="JS/main.js"></script>
    <script>
        const csrfToken = '<?php echo $csrf_token; ?>';

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} show`;
            alertDiv.textContent = message;
            
            const container = document.getElementById('alertContainer');
            container.innerHTML = '';
            container.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }

        function toggleUserStatus(userId, newStatus) {
            if (!confirm('هل أنت متأكد من تغيير حالة هذا المستخدم؟')) return;

            fetch('administration.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=toggle_user_status&user_id=${userId}&status=${newStatus}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('حدث خطأ في الاتصال', 'error');
            });
        }

        function deleteUser(userId, username) {
            if (!confirm(`هل أنت متأكد من حذف المستخدم "${username}"؟`)) return;
            if (!confirm('تأكيد نهائي: سيتم حذف جميع بيانات المستخدم!')) return;

            fetch('administration.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=delete_user&user_id=${userId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById(`user-row-${userId}`).remove();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('حدث خطأ في الاتصال', 'error');
            });
        }

        function searchUsers() {
            const searchTerm = document.getElementById('userSearch').value.trim();
            
            if (searchTerm.length < 2) {
                showAlert('يرجى إدخال حرفين على الأقل للبحث', 'error');
                return;
            }

            document.getElementById('usersLoading').style.display = 'block';
            document.getElementById('usersTableContainer').style.display = 'none';

            fetch('administration.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=search_users&search=${encodeURIComponent(searchTerm)}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('usersLoading').style.display = 'none';
                document.getElementById('usersTableContainer').style.display = 'block';

                if (data.success && data.users && data.users.length > 0) {
                    showAlert(`تم العثور على ${data.users.length} نتيجة`, 'success');
                    location.reload();
                } else {
                    showAlert('لم يتم العثور على نتائج', 'error');
                }
            })
            .catch(error => {
                document.getElementById('usersLoading').style.display = 'none';
                showAlert('حدث خطأ في البحث', 'error');
            });
        }

        function resetSearch() {
            document.getElementById('userSearch').value = '';
            location.reload();
        }

        function clearLoginAttempts() {
            if (!confirm('هل تريد مسح محاولات تسجيل الدخول الأقدم من 7 أيام؟')) return;

            fetch('administration.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=clear_login_attempts&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('فشلت العملية', 'error');
                }
            });
        }

        function terminateSession(sessionId) {
            if (!confirm('هل تريد إنهاء هذه الجلسة؟')) return;
            showAlert('الميزة قيد التطوير', 'error');
        }

        document.getElementById('userSearch')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchUsers();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ لوحة الإدارة جاهزة');
        });
    </script>
</body>
</html>