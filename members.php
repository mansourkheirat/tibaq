<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';
require_once 'database.php';
require_once 'roles-config.php';

$auth = new Auth();
$db = Database::getInstance();

// معالجة طلب الحصول على معلومات المستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'get_user_info') {
    header('Content-Type: application/json');
    
    $userId = (int)$_POST['user_id'];
    
    $query = "SELECT id, fullname, username, role FROM TI_users WHERE id = ? LIMIT 1";
    $result = $db->select($query, [$userId]);
    
    if (!empty($result)) {
        echo json_encode([
            'success' => true,
            'user' => $result[0]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ]);
    }
    exit;
}

// معالجة طلبات AJAX للبحث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_search'])) {
    header('Content-Type: application/json');
    
    $searchTerm = trim($_POST['search_term'] ?? '');
    $searchType = $_POST['search_type'] ?? 'all';
    $letter = $_POST['letter'] ?? null;
    $sortType = $_POST['sort_type'] ?? 'alphabetical';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $perPage = 50;
    $offset = ($page - 1) * $perPage;
    
    $whereConditions = ["is_active = 1"];
    $params = [];
    
    if ($letter) {
        $whereConditions[] = "(fullname LIKE ? OR username LIKE ?)";
        $params[] = $letter . '%';
        $params[] = $letter . '%';
    }
    
    if (!empty($searchTerm)) {
        if ($searchType === 'first_letter') {
            $whereConditions[] = "(fullname LIKE ? OR username LIKE ?)";
            $params[] = $searchTerm . '%';
            $params[] = $searchTerm . '%';
        } else {
            $searchParam = '%' . $searchTerm . '%';
            $whereConditions[] = "(fullname LIKE ? OR username LIKE ?)";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $orderBy = "fullname ASC";
    
    switch ($sortType) {
        case 'username':
            $orderBy = "username ASC";
            break;
        case 'role':
            $orderBy = "FIELD(role, 'super_admin', 'admin', 'monitor', 'supervisor', 'moderator', 'user'), fullname ASC";
            break;
        case 'date_asc':
            $orderBy = "created_at ASC";
            break;
        case 'date_desc':
            $orderBy = "created_at DESC";
            break;
        default:
            $orderBy = "fullname ASC";
    }
    
    $countQuery = "SELECT COUNT(*) as total FROM TI_users WHERE $whereClause";
    $countResult = $db->select($countQuery, $params);
    $totalMembers = $countResult[0]['total'];
    $totalPages = ceil($totalMembers / $perPage);
    
    $query = "SELECT id, fullname, username, role 
              FROM TI_users 
              WHERE $whereClause
              ORDER BY $orderBy
              LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $members = $db->select($query, $params);
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'count' => $totalMembers,
        'page' => $page,
        'totalPages' => $totalPages,
        'perPage' => $perPage
    ]);
    exit;
}

// معالجة إجراءات الإدارة (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    header('Content-Type: application/json');
    
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
        exit;
    }

    $currentUser = $auth->getCurrentUser();
    $allowedRoles = ['super_admin', 'admin', 'monitor'];

    if (!in_array($currentUser['role'], $allowedRoles)) {
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للقيام بهذا الإجراء']);
        exit;
    }
    
    $action = $_POST['admin_action'];
    $targetUserId = (int)$_POST['user_id'];
    
    // حماية المدير العام من أي إجراء
    if ($targetUserId === 1) {
        echo json_encode(['success' => false, 'message' => '🔒 لا يمكن تنفيذ أي إجراء على المدير العام']);
        exit;
    }
    
    if ($targetUserId === $currentUser['id']) {
        echo json_encode(['success' => false, 'message' => 'لا يمكنك تنفيذ هذا الإجراء على حسابك']);
        exit;
    }
    
    switch ($action) {
        case 'edit':
            // فقط المدير العام والمدير يمكنهم التعديل
            if ($currentUser['role'] !== 'super_admin' && $currentUser['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل العضويات']);
                exit;
            }
            
            echo json_encode(['success' => true, 'redirect' => "edit-member?id=$targetUserId"]);
            break;
            
        case 'change_role':
            // الحصول على الرتبة الجديدة
            $newRole = isset($_POST['new_role']) ? trim($_POST['new_role']) : '';
            
            // التحقق من وجود الرتبة
            if (empty($newRole) || $newRole === '') {
                echo json_encode([
                    'success' => false, 
                    'message' => 'يرجى اختيار رتبة أولاً',
                    'debug' => [
                        'post_data' => $_POST,
                        'new_role_received' => $_POST['new_role'] ?? 'NOT_SET'
                    ]
                ]);
                exit;
            }
            
            // قائمة الرتب الصحيحة
            $validRoles = ['user', 'moderator', 'supervisor', 'monitor', 'admin'];
            
            if (!in_array($newRole, $validRoles)) {
                echo json_encode([
                    'success' => false, 
                    'message' => "رتبة غير صالحة: " . ($newRole ?: 'فارغة'),
                    'debug' => [
                        'received' => $newRole,
                        'received_type' => gettype($newRole),
                        'valid_roles' => $validRoles,
                        'post_data' => $_POST
                    ]
                ]);
                exit;
            }
            
            // الحصول على رتبة المستخدم المستهدف
            $targetUserData = $db->select("SELECT role FROM TI_users WHERE id = ?", [$targetUserId]);
            if (empty($targetUserData)) {
                echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
                exit;
            }
            
            $targetCurrentRole = $targetUserData[0]['role'];
            
            // تحديد مستويات الرتب
            $roleLevels = [
                'user' => 1,
                'moderator' => 2,
                'supervisor' => 3,
                'monitor' => 4,
                'admin' => 5,
                'super_admin' => 6
            ];
            
            // التحقق من وجود الرتب في المصفوفة
            if (!isset($roleLevels[$currentUser['role']])) {
                echo json_encode(['success' => false, 'message' => 'خطأ في تحديد رتبة المستخدم الحالي']);
                exit;
            }
            
            if (!isset($roleLevels[$targetCurrentRole])) {
                echo json_encode(['success' => false, 'message' => 'خطأ في تحديد رتبة المستخدم المستهدف']);
                exit;
            }
            
            if (!isset($roleLevels[$newRole])) {
                echo json_encode(['success' => false, 'message' => 'خطأ في تحديد الرتبة الجديدة']);
                exit;
            }
            
            $currentUserLevel = $roleLevels[$currentUser['role']];
            $targetCurrentLevel = $roleLevels[$targetCurrentRole];
            $newRoleLevel = $roleLevels[$newRole];
            
            // التحقق من الصلاحيات حسب الدور
            if ($currentUser['role'] === 'monitor') {
                // المراقب: يمكنه فقط تعديل (user, moderator, supervisor)
                if ($targetCurrentLevel >= 4) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك تعديل رتبة من هو في مستواك أو أعلى']);
                    exit;
                }
                
                // المراقب: لا يمكنه الترقية إلى مراقب أو أعلى
                if ($newRoleLevel >= 4) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك الترقية إلى رتبة مراقب أو أعلى']);
                    exit;
                }
            } elseif ($currentUser['role'] === 'admin') {
                // المدير: يمكنه تعديل من هو أقل منه فقط (حتى monitor)
                if ($targetCurrentLevel >= 5) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك تعديل رتبة من هو في مستواك أو أعلى']);
                    exit;
                }
                
                // المدير: لا يمكنه الترقية إلى مدير أو أعلى
                if ($newRoleLevel >= 5) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك الترقية إلى رتبة مدير أو أعلى']);
                    exit;
                }
            }
            // المدير العام: لا قيود عليه (ما عدا super_admin نفسها)
            
            // منع الترقية إلى super_admin
            if ($newRole === 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'لا يمكن ترقية أي شخص إلى مدير عام']);
                exit;
            }
            
            // تنفيذ التغيير
            $result = $db->execute("UPDATE TI_users SET role = ? WHERE id = ?", [$newRole, $targetUserId]);
            
            if ($result) {
                // تسجيل في Audit Log
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) 
                     VALUES (?, 'role_changed', 'TI_users', ?, ?, ?, ?)",
                    [$currentUser['id'], $targetUserId, $targetCurrentRole, $newRole, $_SERVER['REMOTE_ADDR']]
                );
                
                require_once 'roles-config.php';
                $roleInfo = getRoleInfo($newRole);
                echo json_encode(['success' => true, 'message' => "تمت الترقية إلى {$roleInfo['name']} بنجاح"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل تحديث الرتبة في قاعدة البيانات']);
            }
            break;
            
        case 'suspend':
            // التحقق من الصلاحيات حسب الرتبة
            $targetUserData = $db->select("SELECT role FROM TI_users WHERE id = ?", [$targetUserId]);
            
            if (!empty($targetUserData)) {
                $targetRole = $targetUserData[0]['role'];
                
                $roleLevels = [
                    'user' => 1,
                    'moderator' => 2,
                    'supervisor' => 3,
                    'monitor' => 4,
                    'admin' => 5,
                    'super_admin' => 6
                ];
                
                // التحقق من وجود الرتب في المصفوفة
                if (!isset($roleLevels[$currentUser['role']])) {
                    echo json_encode(['success' => false, 'message' => 'خطأ في تحديد رتبة المستخدم الحالي']);
                    exit;
                }
                
                if (!isset($roleLevels[$targetRole])) {
                    echo json_encode(['success' => false, 'message' => 'خطأ في تحديد رتبة المستخدم المستهدف']);
                    exit;
                }
                
                $currentUserLevel = $roleLevels[$currentUser['role']];
                $targetLevel = $roleLevels[$targetRole];
                
                // المراقب: يمكنه فقط تجميد من هو أقل منه (user, moderator, supervisor)
                if ($currentUser['role'] === 'monitor' && $targetLevel >= 4) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك تجميد من هو في رتبتك أو أعلى']);
                    exit;
                }
                
                // المدير: يمكنه تجميد من هو أقل منه (حتى monitor)
                if ($currentUser['role'] === 'admin' && $targetLevel >= 5) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك تجميد من هو في رتبتك أو أعلى']);
                    exit;
                }
            }
            
            $result = $db->execute("UPDATE TI_users SET is_active = 0 WHERE id = ?", [$targetUserId]);
            
            if ($result) {
                // تسجيل في Audit Log
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, ip_address) 
                     VALUES (?, 'user_suspended', 'TI_users', ?, ?)",
                    [$currentUser['id'], $targetUserId, $_SERVER['REMOTE_ADDR']]
                );
                
                // إلغاء جميع الجلسات
                $db->execute("UPDATE TI_sessions SET is_active = 0 WHERE user_id = ?", [$targetUserId]);
                
                echo json_encode(['success' => true, 'message' => 'تم تجميد العضوية بنجاح']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل تجميد العضوية']);
            }
            break;
            
        case 'delete':
            // فقط المدير العام والمدير يمكنهم الحذف
            if ($currentUser['role'] !== 'super_admin' && $currentUser['role'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لحذف العضويات']);
                exit;
            }
            
            // التحقق من رتبة المستخدم المستهدف
            $targetUserData = $db->select("SELECT role FROM TI_users WHERE id = ?", [$targetUserId]);
            
            if (!empty($targetUserData)) {
                $targetRole = $targetUserData[0]['role'];
                
                // المدير لا يمكنه حذف مدير آخر
                if ($currentUser['role'] === 'admin' && in_array($targetRole, ['admin', 'super_admin'])) {
                    echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف مدير']);
                    exit;
                }
            }
            
            $result = $db->execute("DELETE FROM TI_users WHERE id = ?", [$targetUserId]);
            
            if ($result) {
                // تسجيل في Audit Log
                $db->execute(
                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, ip_address) 
                     VALUES (?, 'user_deleted', 'TI_users', ?, ?)",
                    [$currentUser['id'], $targetUserId, $_SERVER['REMOTE_ADDR']]
                );
                
                echo json_encode(['success' => true, 'message' => 'تم حذف العضوية بنجاح']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل حذف العضوية']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'إجراء غير معروف: ' . $action]);
    }
    exit;
}

require_once 'site-functions.php';
$siteName = getSiteName();

$arabicLetters = [
    'آ', 'أ', 'إ', 'ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 
    'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 
    'ن', 'ه', 'و', 'ي'
];

$query = "SELECT DISTINCT SUBSTRING(fullname, 1, 1) as first_letter 
          FROM TI_users 
          WHERE is_active = 1 
          ORDER BY fullname";
$result = $db->select($query);
$availableLetters = array_column($result, 'first_letter');

$firstRow = array_slice($arabicLetters, 0, 16);
$secondRow = array_slice($arabicLetters, 16);

$isAdmin = false;
$isSuperAdmin = false;
$isMonitor = false;
$currentUserRole = null;

if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    $currentUserRole = $currentUser['role'];
    $isSuperAdmin = $currentUser['role'] === 'super_admin';
    $isAdmin = $currentUser['role'] === 'admin';
    $isMonitor = $currentUser['role'] === 'monitor';
}

// القائمة الكاملة: المدير العام والمدير
$hasFullAccess = $isSuperAdmin || $isAdmin;

// قائمة محدودة: المراقب
$hasLimitedAccess = $isMonitor;

// أي صلاحية
$hasAnyAccess = $hasFullAccess || $hasLimitedAccess;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('الأعضاء')); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
        .members-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 36px;
            color: #1565C0;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 25px;
        }
        
        .search-container {
            max-width: 700px;
            margin: 0 auto 30px;
        }
        
        .search-options {
            display: flex;
            gap: 0;
            justify-content: center;
            margin-bottom: 15px;
            background: #f5f5f5;
            padding: 4px;
            border-radius: 12px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-option {
            position: relative;
        }
        
        .search-option input[type="radio"] {
            display: none;
        }
        
        .search-option label {
            display: block;
            padding: 10px 24px;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            user-select: none;
        }
        
        .search-option input[type="radio"]:checked + label {
            background: #00A6FB;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(0, 166, 251, 0.3);
            cursor: default;
        }
        
        .search-box {
            display: flex;
            gap: 12px;
            background: white;
            padding: 8px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: border-color 0.3s;
        }
        
        .search-box.active {
            border-color: #1565C0;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            font-size: 16px;
            color: var(--text-primary);
            outline: none;
        }
        
        .search-btn {
            padding: 12px 30px;
            background: #00A6FB;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            white-space: nowrap;
        }
        
        .search-btn:hover {
            background: #0891E6;
        }
        
        .clear-search {
            padding: 12px 25px;
            background: #f5f5f5;
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: none;
        }
        
        .clear-search.show {
            display: block;
        }
        
        .clear-search:hover {
            background: #e0e0e0;
        }
        
        .letters-bar {
            background: #f8f8f8;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .letters-row {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-bottom: 6px;
        }
        
        .letters-row:last-child {
            margin-bottom: 0;
        }
        
        .letter-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
            flex-shrink: 0;
            color: #424242;
            background: transparent;
            cursor: pointer;
        }
        
        .letter-btn.active {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .letter-btn.active:hover {
            background: #bbdefb;
        }
        
        .letter-btn.active.selected {
            background: #1976d2 !important;
            color: white !important;
        }
        
        .letter-btn.inactive {
            color: #bdbdbd;
            cursor: default;
        }
        
        .sort-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .sort-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }

        .custom-select-wrapper {
            position: relative;
            min-width: 300px;
            user-select: none;
        }

        .custom-select-trigger {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px 45px 10px 20px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }

        .custom-select-trigger:hover {
            border-color: #00A6FB;
        }

        .custom-select-trigger.active {
            border-color: #00A6FB;
        }

        .custom-select-trigger.open-down {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            border-bottom-color: transparent;
        }

        .custom-select-trigger.open-up {
            border-top-left-radius: 0;
            border-top-right-radius: 0;
            border-top-color: transparent;
        }

        .custom-select-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .custom-select-text {
            flex: 1;
        }

        .custom-select-arrow {
            position: absolute;
            left: 15px;
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #666;
            transition: transform 0.3s;
            pointer-events: none;
        }

        .custom-select-trigger.active .custom-select-arrow {
            transform: rotate(180deg);
        }

        .custom-select-dropdown {
            position: absolute;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #00A6FB;
            border-radius: 10px;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: all 0.3s;
            z-index: 1000;
        }

        .custom-select-dropdown.show {
            max-height: 400px;
            opacity: 1;
        }

        .custom-select-dropdown.open-up {
            bottom: 100%;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            border-bottom: none;
            margin-bottom: -2px;
        }

        .custom-select-dropdown.open-down {
            top: 100%;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
            border-top: none;
            margin-top: -2px;
        }

        .custom-select-options {
            max-height: 350px;
            overflow-y: auto;
        }

        .custom-select-options::-webkit-scrollbar {
            width: 8px;
        }

        .custom-select-options::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-select-options::-webkit-scrollbar-thumb {
            background: #00A6FB;
            border-radius: 10px;
        }

        .custom-select-options::-webkit-scrollbar-thumb:hover {
            background: #0891E6;
        }

        .custom-select-option {
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .custom-select-option:hover {
            background: #00A6FB;
            color: #ffffff;
        }

        .custom-select-option-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .custom-select-option-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            transition: all 0.3s ease;
        }

        .custom-select-option:hover .custom-select-option-icon svg {
            stroke-width: 2.5;
        }

        .custom-select-option.selected .custom-select-option-icon svg {
            stroke-width: 2.5;
            filter: drop-shadow(0 0 4px rgba(0, 166, 251, 0.4));
        }

        .custom-select-trigger .custom-select-icon svg {
            transition: all 0.3s ease;
        }

        .custom-select-trigger:hover .custom-select-icon svg,
        .custom-select-trigger.active .custom-select-icon svg {
            stroke-width: 2.5;
        }

        .custom-select-option-text {
            flex: 1;
        }

        .custom-select-option-check {
            font-size: 16px;
            color: #00A6FB;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s;
        }

        .custom-select-option.selected {
            background: #f0f0f0;
            color: #333;
            font-weight: 600;
            cursor: not-allowed;
        }

        .custom-select-option.selected:hover {
            background: #f0f0f0;
            color: #333;
        }

        .custom-select-option.selected .custom-select-option-check {
            opacity: 1;
            transform: scale(1);
            color: #666;
        }
        
        .search-results-info {
            text-align: center;
            padding: 12px;
            background: #e3f2fd;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1976d2;
            font-weight: 600;
            display: none;
        }
        
        .search-results-info.show {
            display: block;
        }
        
        .loading-overlay {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .loading-overlay.show {
            display: block;
        }
        
        .spinner {
            border: 4px solid var(--light-bg);
            border-top: 4px solid #00A6FB;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .role-separator {
            grid-column: 1 / -1;
            height: 0;
            margin: 0;
            padding: 0;
            border: none;
            display: block;
            width: 100%;
        }
        
        .member-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        
        .member-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #00A6FB;
        }
        
        .member-actions {
            position: absolute;
            top: 10px;
            left: 10px;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .member-card:hover .member-actions {
            opacity: 1;
            pointer-events: auto;
        }

        .member-actions.menu-open {
            opacity: 1;
            pointer-events: auto;
        }
        
        .actions-toggle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #666;
            transition: all 0.3s;
        }
        
        .actions-toggle:hover,
        .actions-toggle.active {
            background: #00A6FB;
            color: white;
        }
        
        .actions-menu {
            position: absolute;
            top: 40px;
            left: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 8px 0;
            min-width: 160px;
            display: none;
            z-index: 100;
        }
        
        .actions-menu.show {
            display: block;
        }
        
        .action-item {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .action-item:hover {
            background: #f5f5f5;
        }

        .action-item.general {
            color: #1976d2;
        }
        
        .action-item.general:hover {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .action-item.danger {
            color: #dc3545;
        }
        
        .action-item.danger:hover {
            background: #fee;
        }

        .action-item.warning {
            color: #f59e0b;
        }

        .action-item.warning:hover {
            background: #fef3c7;
            color: #d97706;
        }

        .action-item.success {
            color: #10b981;
        }

        .action-item.success:hover {
            background: #d1fae5;
            color: #059669;
        }
        
        .member-name {
            font-size: 17px;
            font-weight: 700;
            color: #000000 !important;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .member-username {
            font-size: 14px;
            margin-bottom: 12px;
            direction: ltr;
            font-weight: 600;
        }
        
        .member-username.admin-color {
            color: #c2185b;
        }
        
        .member-username.moderator-color {
            color: #e65100;
        }
        
        .member-username.user-color {
            color: #1976d2;
        }
        
        .member-role {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .role-admin {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .role-moderator {
            background: #fff3e0;
            color: #e65100;
        }
        
        .role-user {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .view-profile-btn {
            display: inline-block;
            padding: 8px 20px;
            background: #00A6FB;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.3s;
        }
        
        .view-profile-btn:hover {
            background: #0891E6;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination-btn {
            padding: 10px 16px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #00A6FB;
            color: white;
            border-color: #00A6FB;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-info {
            padding: 10px 20px;
            background: #f5f5f5;
            border-radius: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .no-members {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .no-members-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        .toast.show {
            display: flex;
        }
        
        .toast.success {
            border-left: 4px solid #28a745;
        }
        
        .toast.error {
            border-left: 4px solid #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .members-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }
            
            .member-card {
                padding: 15px;
            }
            
            .search-box {
                flex-direction: column;
                gap: 8px;
            }
            
            .search-btn,
            .clear-search {
                width: 100%;
            }
            
            .search-options {
                flex-direction: column;
                width: 100%;
                padding: 6px;
            }
            
            .search-option label {
                text-align: center;
                width: 100%;
            }
            
            .letters-row {
                gap: 4px;
                flex-wrap: wrap;
            }
            
            .letter-btn {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
            
            .sort-container {
                flex-direction: column;
            }
            
            .custom-select-wrapper {
                width: 100%;
                min-width: auto;
            }
        }

        /* صندوق ترقية العضوية */
        .promotion-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .promotion-modal.show {
            display: block;
        }

        .promotion-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .promotion-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translate(-50%, -40%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        .promotion-modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .promotion-modal-header h3 {
            font-size: 24px;
            color: #1a1a1a;
            margin: 0;
        }

        .promotion-modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: #f5f5f5;
            color: #666;
            font-size: 20px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .promotion-modal-close:hover {
            background: #ef4444;
            color: white;
            transform: rotate(90deg);
        }

        .promotion-modal-body {
            padding: 30px;
        }

        .member-info-box {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            margin-bottom: 20px;
        }

        .member-details {
            flex: 1;
        }

        .member-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #FFFFFF;
        }

        .member-current-role {
            font-size: 14px;
            opacity: 0.9;
        }

        .member-current-role span {
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .promotion-arrow {
            text-align: center;
            font-size: 32px;
            margin: 10px 0;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .selection-label {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 15px;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }

        .role-option {
            padding: 18px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .role-option:not(.disabled):hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .role-option.selected {
            box-shadow: 0 4px 15px rgba(0, 166, 251, 0.3);
        }

        .role-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f5f5f5;
            border-color: #ccc;
        }

        .role-option.role-user {
            border-color: #1976d2;
        }
        
        .role-option.role-user:not(.disabled):hover {
            border-color: #1976d2;
        }

        .role-option.role-user.selected {
            background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(25, 118, 210, 0.05) 100%);
            border-color: #1976d2;
        }
        
        .role-option.role-moderator {
            border-color: #2563eb;
        }

        .role-option.role-moderator:not(.disabled):hover {
            border-color: #2563eb;
        }

        .role-option.role-moderator.selected {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
            border-color: #2563eb;
        }
        
        .role-option.role-supervisor {
            border-color: #ea580c;
        }

        .role-option.role-supervisor:not(.disabled):hover {
            border-color: #ea580c;
        }

        .role-option.role-supervisor.selected {
            background: linear-gradient(135deg, rgba(234, 88, 12, 0.1) 0%, rgba(234, 88, 12, 0.05) 100%);
            border-color: #ea580c;
        }
        
        .role-option.role-monitor {
            border-color: #92400e;
        }

        .role-option.role-monitor:not(.disabled):hover {
            border-color: #92400e;
        }

        .role-option.role-monitor.selected {
            background: linear-gradient(135deg, rgba(146, 64, 14, 0.1) 0%, rgba(146, 64, 14, 0.05) 100%);
            border-color: #92400e;
        }
        
        .role-option.role-admin {
            border-color: #dc2626;
        }

        .role-option.role-admin:not(.disabled):hover {
            border-color: #dc2626;
        }

        .role-option.role-admin.selected {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
            border-color: #dc2626;
        }

        .promotion-modal-footer {
            padding: 20px 30px;
            border-top: 2px solid #e9ecef;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel,
        .btn-confirm {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel {
            background: #f5f5f5;
            color: #666;
        }

        .btn-cancel:hover {
            background: #e5e5e5;
        }

        .btn-confirm {
            background: #00A6FB;
            color: white;
        }

        .btn-confirm:hover:not(:disabled) {
            background: #0891E6;
        }

        .btn-confirm:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .promotion-modal-content {
                width: 95%;
                max-height: 95vh;
            }
            
            .promotion-modal-header {
                padding: 20px;
            }
            
            .promotion-modal-body {
                padding: 20px;
            }
            
            .roles-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .member-info-box {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    
<?php include 'navbar.php'; ?>

    <main class="main-content">
        <div class="members-container">
            
            <div class="page-header">
                <h1>أعضاء المنصة</h1>
                <p>مرحباً، تعرف على جميع أعضاء منصة: <?php echo htmlspecialchars($siteName); ?></p>
            </div>

            <div class="search-container">
                <div class="search-options">
                    <div class="search-option">
                        <input type="radio" id="searchAll" name="searchType" value="all" checked>
                        <label for="searchAll">بحث مطلق</label>
                    </div>
                    <div class="search-option">
                        <input type="radio" id="searchFirst" name="searchType" value="first_letter">
                        <label for="searchFirst">بحث من الحرف الأول فقط</label>
                    </div>
                </div>
                
                <div class="search-box" id="searchBox">
                    <input type="text" 
                           id="searchInput" 
                           class="search-input" 
                           placeholder="ابحث عن عضو بالاسم الكامل أو اسم المستخدم ..."
                           autocomplete="off">
                    <button class="search-btn" onclick="searchMembers()">بحث</button>
                    <button class="clear-search" id="clearBtn" onclick="clearSearch()">إلغاء</button>
                </div>
            </div>

            <div class="letters-bar">
                <div class="letters-row">
                    <?php foreach ($firstRow as $letter): ?>
                        <?php 
                        $isActive = in_array($letter, $availableLetters);
                        $class = $isActive ? 'active' : 'inactive';
                        ?>
                        <div class="letter-btn <?php echo $class; ?>" 
                             data-letter="<?php echo htmlspecialchars($letter); ?>"
                             <?php if ($isActive): ?>
                                onclick="filterByLetter('<?php echo htmlspecialchars($letter); ?>')"
                             <?php endif; ?>>
                            <?php echo htmlspecialchars($letter); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="letters-row">
                    <?php foreach ($secondRow as $letter): ?>
                        <?php 
                        $isActive = in_array($letter, $availableLetters);
                        $class = $isActive ? 'active' : 'inactive';
                        ?>
                        <div class="letter-btn <?php echo $class; ?>" 
                             data-letter="<?php echo htmlspecialchars($letter); ?>"
                             <?php if ($isActive): ?>
                                onclick="filterByLetter('<?php echo htmlspecialchars($letter); ?>')"
                             <?php endif; ?>>
                            <?php echo htmlspecialchars($letter); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sort-container">
                <label class="sort-label">عرض الأعضاء حسب:</label>
                
                <div class="custom-select-wrapper" id="customSelect">
                    <div class="custom-select-trigger" tabindex="0">
                        <span class="custom-select-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                        </span>
                        <span class="custom-select-text">اختر ما يناسبك:</span>
                        <span class="custom-select-arrow"></span>
                    </div>
                    
                    <div class="custom-select-dropdown">
                        <div class="custom-select-options">
                            <div class="custom-select-option" data-value="alphabetical" data-icon="sortAlphabetical">
                                <span class="custom-select-option-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18"></path>
                                        <path d="M3 12h18"></path>
                                        <path d="M3 18h18"></path>
                                    </svg>
                                </span>
                                <span class="custom-select-option-text">الاسم الكامل</span>
                                <span class="custom-select-option-check">✓</span>
                            </div>
                            
                            <div class="custom-select-option" data-value="username" data-icon="sortUsername">
                                <span class="custom-select-option-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="8" r="4"></circle>
                                        <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"></path>
                                    </svg>
                                </span>
                                <span class="custom-select-option-text">اسم المستخدم</span>
                                <span class="custom-select-option-check">✓</span>
                            </div>
                            
                            <div class="custom-select-option" data-value="role" data-icon="sortRole">
                                <span class="custom-select-option-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8m3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"></path>
                                    </svg>
                                </span>
                                <span class="custom-select-option-text">رتبة العضوية</span>
                                <span class="custom-select-option-check">✓</span>
                            </div>
                            
                            <div class="custom-select-option" data-value="date_asc" data-icon="sortDateAsc">
                                <span class="custom-select-option-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                </span>
                                <span class="custom-select-option-text">الأقدم أولا</span>
                                <span class="custom-select-option-check">✓</span>
                            </div>
                            
                            <div class="custom-select-option" data-value="date_desc" data-icon="sortDateDesc">
                                <span class="custom-select-option-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </span>
                                <span class="custom-select-option-text">الأحدث أولا</span>
                                <span class="custom-select-option-check">✓</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="search-results-info" id="searchResults"></div>
            
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p>جاري التحميل...</p>
            </div>

            <div id="membersContent">
                <div class="members-grid" id="membersGrid"></div>
                <div class="pagination" id="pagination"></div>
            </div>

        </div>
    </main>

    <div class="toast" id="toast">
        <span id="toastIcon"></span>
        <span id="toastMessage"></span>
    </div>

    <script src="JS/main.js"></script>
    <script>
        const searchInput = document.getElementById('searchInput');
        const searchBox = document.getElementById('searchBox');
        const clearBtn = document.getElementById('clearBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const searchResults = document.getElementById('searchResults');
        const membersGrid = document.getElementById('membersGrid');
        const pagination = document.getElementById('pagination');
        
        let currentPage = 1;
        let currentLetter = null;
        let currentSearch = '';
        let totalPages = 1;
        let isSearching = false;
        let searchTimeout;
        let currentSortType = 'alphabetical';
        
        const hasFullAccess = <?php echo $hasFullAccess ? 'true' : 'false'; ?>;
        const hasLimitedAccess = <?php echo $hasLimitedAccess ? 'true' : 'false'; ?>;
        const hasAnyAccess = <?php echo $hasAnyAccess ? 'true' : 'false'; ?>;
        const currentUserRole = '<?php echo $currentUserRole ?? ''; ?>';
        const isFullAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        class CustomSelect {
            constructor(element) {
                this.wrapper = element;
                this.trigger = this.wrapper.querySelector('.custom-select-trigger');
                this.dropdown = this.wrapper.querySelector('.custom-select-dropdown');
                this.options = this.wrapper.querySelectorAll('.custom-select-option');
                this.selectedValue = null;
                this.focusedIndex = -1;
                this.defaultText = 'اختر ما يناسبك';
                
                this.init();
            }
            
            init() {
                this.trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggle();
                });
                
                this.trigger.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.toggle();
                    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.open();
                    }
                });
                
                this.options.forEach((option, index) => {
                    option.addEventListener('click', () => {
                        if (!option.classList.contains('selected')) {
                            this.selectOption(option);
                        }
                    });
                    
                    option.addEventListener('mouseenter', () => {
                        this.focusedIndex = index;
                        this.updateFocus();
                    });
                });
                
                this.dropdown.addEventListener('keydown', (e) => {
                    this.handleKeyboard(e);
                });
                
                document.addEventListener('click', (e) => {
                    if (!this.wrapper.contains(e.target)) {
                        this.close();
                    }
                });
            }
            
            toggle() {
                if (this.dropdown.classList.contains('show')) {
                    this.close();
                } else {
                    this.open();
                }
            }
            
            open() {
                const rect = this.wrapper.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;
                const dropdownHeight = 400;
                
                this.trigger.classList.remove('open-up', 'open-down');
                this.dropdown.classList.remove('open-up', 'open-down');
                
                if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                    this.trigger.classList.add('open-up');
                    this.dropdown.classList.add('open-up');
                } else {
                    this.trigger.classList.add('open-down');
                    this.dropdown.classList.add('open-down');
                }
                
                this.trigger.classList.add('active');
                this.dropdown.classList.add('show');
                
                const selectedIndex = Array.from(this.options).findIndex(opt => 
                    opt.classList.contains('selected')
                );
                this.focusedIndex = selectedIndex >= 0 ? selectedIndex : 0;
                this.updateFocus();
            }
            
            close() {
                this.trigger.classList.remove('active', 'open-up', 'open-down');
                this.dropdown.classList.remove('show', 'open-up', 'open-down');
                this.focusedIndex = -1;
                this.updateFocus();
            }
            
            selectOption(option) {
                this.options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                
                const text = option.querySelector('.custom-select-option-text').textContent;
                const icon = option.getAttribute('data-icon');
                const value = option.getAttribute('data-value');
                
                this.trigger.querySelector('.custom-select-text').textContent = text;
                this.trigger.querySelector('.custom-select-icon').textContent = icon;
                
                this.selectedValue = value;
                this.close();
                
                currentSortType = value;
                currentPage = 1;
                loadMembers();
            }
            
            handleKeyboard(e) {
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.focusedIndex = Math.min(this.focusedIndex + 1, this.options.length - 1);
                        this.updateFocus();
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        this.focusedIndex = Math.max(this.focusedIndex - 1, 0);
                        this.updateFocus();
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (this.focusedIndex >= 0) {
                            const option = this.options[this.focusedIndex];
                            if (!option.classList.contains('selected')) {
                                this.selectOption(option);
                            }
                        }
                        break;
                        
                    case 'Escape':
                        e.preventDefault();
                        this.close();
                        this.trigger.focus();
                        break;
                        
                    case 'Tab':
                        this.close();
                        break;
                }
            }
            
            updateFocus() {
                this.options.forEach((opt, index) => {
                    if (index === this.focusedIndex) {
                        opt.classList.add('keyboard-focus');
                        opt.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    } else {
                        opt.classList.remove('keyboard-focus');
                    }
                });
            }
        }
        
        const customSelect = new CustomSelect(document.getElementById('customSelect'));

        window.addEventListener('DOMContentLoaded', function() {
            loadMembers();
        });

        searchInput.addEventListener('focus', function() {
            searchBox.classList.add('active');
        });

        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (!isSearching) {
                    searchBox.classList.remove('active');
                }
            }, 200);
        });

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length > 0) {
                clearBtn.classList.add('show');
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(() => {
                    currentSearch = searchTerm;
                    currentPage = 1;
                    currentLetter = null;
                    clearLetterSelection();
                    loadMembers();
                }, 300);
            } else {
                clearBtn.classList.remove('show');
                currentSearch = '';
                currentPage = 1;
                loadMembers();
            }
        });

        function searchMembers() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm.length > 0) {
                currentSearch = searchTerm;
                currentPage = 1;
                currentLetter = null;
                clearLetterSelection();
                loadMembers();
            }
            searchBox.classList.remove('active');
        }

        function clearSearch() {
            searchInput.value = '';
            clearBtn.classList.remove('show');
            searchBox.classList.remove('active');
            currentSearch = '';
            currentPage = 1;
            currentLetter = null;
            clearLetterSelection();
            searchResults.classList.remove('show');
            loadMembers();
        }

        function filterByLetter(letter) {
            if (currentLetter === letter) {
                clearLetterSelection();
                currentLetter = null;
                currentPage = 1;
                searchResults.classList.remove('show');
                loadMembers();
                return;
            }
            
            clearLetterSelection();
            
            const letterBtn = document.querySelector(`.letter-btn[data-letter="${letter}"]`);
            if (letterBtn) {
                letterBtn.classList.add('selected');
            }
            
            currentLetter = letter;
            currentPage = 1;
            currentSearch = '';
            searchInput.value = '';
            clearBtn.classList.remove('show');
            loadMembers();
        }

        function clearLetterSelection() {
            document.querySelectorAll('.letter-btn.selected').forEach(btn => {
                btn.classList.remove('selected');
            });
        }

        function loadMembers() {
            const searchType = document.querySelector('input[name="searchType"]:checked').value;
            isSearching = true;
            loadingOverlay.classList.add('show');
            membersGrid.innerHTML = '';
            pagination.innerHTML = '';

            const formData = new FormData();
            formData.append('ajax_search', '1');
            formData.append('search_term', currentSearch);
            formData.append('search_type', searchType);
            formData.append('sort_type', currentSortType);
            formData.append('page', currentPage);
            if (currentLetter) {
                formData.append('letter', currentLetter);
            }

            fetch('members.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingOverlay.classList.remove('show');
                
                if (data.success) {
                    displayMembers(data.members);
                    displayPagination(data.page, data.totalPages, data.count);
                    
                    if (currentSearch || currentLetter) {
                        let searchInfo = '';
                        if (currentLetter) {
                            searchInfo = `الأعضاء الذين تبدأ أسماؤهم بحرف "<strong>${currentLetter}</strong>"`;
                        } else {
                            const searchTypeText = searchType === 'first_letter' ? '(بحث من الحرف الأول)' : '(بحث مطلق)';
                            searchInfo = `نتائج البحث ${searchTypeText} عن: "<strong>${currentSearch}</strong>"`;
                        }
                        searchResults.innerHTML = `${searchInfo} - تم العثور على <strong>${data.count}</strong> نتيجة`;
                        searchResults.classList.add('show');
                    } else {
                        searchResults.classList.remove('show');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingOverlay.classList.remove('show');
                membersGrid.innerHTML = '<div class="no-members" style="grid-column: 1 / -1;"><div class="no-members-icon">😕</div><h2>حدث خطأ</h2><p>يرجى المحاولة مرة أخرى</p></div>';
            })
            .finally(() => {
                isSearching = false;
            });
        }

        function displayMembers(members) {
            if (members.length === 0) {
                membersGrid.innerHTML = `
                    <div class="no-members" style="grid-column: 1 / -1;">
                        <div class="no-members-icon">😔</div>
                        <h2>لم يتم العثور على نتائج</h2>
                        <p>جرب البحث بكلمات مختلفة</p>
                    </div>
                `;
                return;
            }

            membersGrid.innerHTML = '';
            
            // إذا كان الترتيب حسب الرتبة، نجمع الأعضاء حسب الرتبة
            if (currentSortType === 'role') {
                // ترتيب الرتب المطلوب
                const roleOrder = ['super_admin', 'admin', 'monitor', 'supervisor', 'moderator', 'user'];
                
                // تجميع الأعضاء حسب الرتبة
                const membersByRole = {};
                members.forEach(member => {
                    if (!membersByRole[member.role]) {
                        membersByRole[member.role] = [];
                    }
                    membersByRole[member.role].push(member);
                });
                
                // عرض الأعضاء حسب ترتيب الرتب
                let isFirstRole = true;
                roleOrder.forEach(role => {
                    if (membersByRole[role] && membersByRole[role].length > 0) {
                        // بدء سطر جديد لكل رتبة (ما عدا الأولى)
                        if (!isFirstRole) {
                            const roleSeparator = document.createElement('div');
                            roleSeparator.className = 'role-separator';
                            membersGrid.appendChild(roleSeparator);
                        }
                        
                        membersByRole[role].forEach(member => {
                            createMemberCard(member);
                        });
                        
                        isFirstRole = false;
                    }
                });
            } else {
                // عرض عادي بدون تجميع حسب الرتبة
                members.forEach(member => {
                    createMemberCard(member);
                });
            }
        }
        
        function createMemberCard(member) {
                const roleLabels = {
                    super_admin: '👑 المدير العام',
                    admin: '🔴 مدير',
                    monitor: '🟤 مراقب',
                    supervisor: '🟠 مشرف',
                    moderator: '🔵 مشرف مساعد',
                    user: '👤 عضو'
                };
                
                const card = document.createElement('div');
                card.className = 'member-card';
                
                // تحديد من يمكنه رؤية زر القائمة
                let showActions = false;
                
                if (member.id === 1) {
                    // المدير العام: لا يظهر له زر القائمة أبداً
                    showActions = false;
                } else if (currentUserRole === 'super_admin') {
                    // المدير العام: يرى الزر لجميع العضويات ما عدا نفسه
                    showActions = true;
                } else if (currentUserRole === 'admin') {
                    // المدير: يرى الزر فقط للرتب الأقل منه (monitor, supervisor, moderator, user)
                    showActions = ['monitor', 'supervisor', 'moderator', 'user'].includes(member.role);
                } else if (currentUserRole === 'monitor') {
                    // المراقب: يرى الزر فقط لـ (supervisor, moderator, user)
                    showActions = ['supervisor', 'moderator', 'user'].includes(member.role);
                } else {
                    showActions = false;
                }
                
                card.innerHTML = `
                    ${showActions ? `
                    <div class="member-actions">
                        <button class="actions-toggle" onclick="toggleActions(${member.id}, event)">
                            ⋮
                        </button>
                        <div class="actions-menu" id="actions-${member.id}">
                            ${hasFullAccess ? `
                                <div class="action-item" onclick="performAction(${member.id}, 'edit')">
                                    ✏️ تعديل العضوية
                                </div>
                            ` : ''}
                            
                            <div class="action-item success" onclick="performAction(${member.id}, 'promote')">
                                ⬆️ ترقية العضوية
                            </div>
                            
                            <div class="action-item warning" onclick="performAction(${member.id}, 'suspend')">
                                ⸕️ تجميد العضوية
                            </div>
                            
                            ${hasFullAccess ? `
                                <div class="action-item danger" onclick="performAction(${member.id}, 'delete')">
                                    🗑️ حذف العضوية
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}
                    <div class="member-name">${escapeHtml(member.fullname)}</div>
                    <div class="member-username ${member.role}-color">@${escapeHtml(member.username)}</div>
                    <span class="member-role role-${member.role}">${roleLabels[member.role]}</span>
                    <div>
                        <a href="${escapeHtml(member.username)}" class="view-profile-btn">عرض الملف</a>
                    </div>
                `;
                
                membersGrid.appendChild(card);
        }

        function displayPagination(page, total, count) {
            if (total <= 1) {
                pagination.innerHTML = '';
                return;
            }

            totalPages = total;
            
            let html = `
                <button class="pagination-btn" onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>
                    ← السابق
                </button>
                <div class="pagination-info">
                    صفحة ${page} من ${total}
                </div>
                <button class="pagination-btn" onclick="goToPage(${page + 1})" ${page === total ? 'disabled' : ''}>
                    التالي →
                </button>
            `;
            
            pagination.innerHTML = html;
        }

        function goToPage(page) {
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            loadMembers();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchMembers();
            }
        });

        function toggleActions(memberId, event) {
            event.stopPropagation();
            const menu = document.getElementById(`actions-${memberId}`);
            const toggle = event.currentTarget;
            const memberActions = toggle.closest('.member-actions');
            
            document.querySelectorAll('.actions-menu').forEach(m => {
                if (m.id !== `actions-${memberId}`) {
                    m.classList.remove('show');
                }
            });
            
            document.querySelectorAll('.actions-toggle').forEach(t => {
                if (t !== toggle) {
                    t.classList.remove('active');
                }
            });
            
            document.querySelectorAll('.member-actions').forEach(ma => {
                if (ma !== memberActions) {
                    ma.classList.remove('menu-open');
                }
            });
            
            const isOpen = menu.classList.toggle('show');
            
            if (isOpen) {
                toggle.classList.add('active');
                memberActions.classList.add('menu-open');
            } else {
                toggle.classList.remove('active');
                memberActions.classList.remove('menu-open');
            }
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.member-actions')) {
                document.querySelectorAll('.actions-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
                document.querySelectorAll('.actions-toggle').forEach(toggle => {
                    toggle.classList.remove('active');
                });
                document.querySelectorAll('.member-actions').forEach(ma => {
                    ma.classList.remove('menu-open');
                });
            }
        });

        function performAction(userId, action) {
            document.getElementById(`actions-${userId}`).classList.remove('show');
            
            let confirmMessage = '';
            switch(action) {
                case 'edit':
                    window.location.href = `edit-member?id=${userId}`;
                    return;
                    
                case 'promote':
                    openPromotionModal(userId);
                    return;
                    
                case 'suspend':
                    confirmMessage = 'هل تريد تجميد هذا العضو؟ سيتم إلغاء جميع جلساته.';
                    break;
                    
                case 'delete':
                    confirmMessage = 'هل أنت متأكد من حذف هذا العضو؟ هذا الإجراء لا يمكن التراجع عنه!';
                    break;
                    
                default:
                    showToast('إجراء غير معروف', 'error');
                    return;
            }
            
            if (!confirm(confirmMessage)) return;
            
            const formData = new FormData();
            formData.append('admin_action', action);
            formData.append('user_id', userId);
            
            fetch('members.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    if (action === 'delete' || action === 'suspend') {
                        setTimeout(() => loadMembers(), 1500);
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        loadMembers();
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('حدث خطأ في الاتصال', 'error');
            });
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            toastIcon.textContent = type === 'success' ? '✅' : '❌';
            toastMessage.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // متغيرات الترقية
        let currentPromotionUserId = null;
        let selectedNewRole = null;

        // قائمة الرتب مع معلوماتها (بدون super_admin)
        const rolesData = {
            'user': {
                name: 'عضو',
                icon: '👤',
                level: 1,
                color: '#1976d2'
            },
            'moderator': {
                name: 'مشرف مساعد',
                icon: '🔵',
                level: 2,
                color: '#2563eb'
            },
            'supervisor': {
                name: 'مشرف',
                icon: '🟠',
                level: 3,
                color: '#ea580c'
            },
            'monitor': {
                name: 'مراقب',
                icon: '🟤',
                level: 4,
                color: '#92400e'
            },
            'admin': {
                name: 'مدير',
                icon: '🔴',
                level: 5,
                color: '#dc2626'
            }
        };

        // فتح صندوق الترقية
        function openPromotionModal(userId) {
            currentPromotionUserId = userId;
            selectedNewRole = null;
            
            fetch('members.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=get_user_info&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const member = data.user;
                    
                    document.getElementById('promotionMemberName').textContent = member.fullname;
                    const currentRoleInfo = rolesData[member.role] || rolesData['user'];
                    document.getElementById('promotionCurrentRole').innerHTML = 
                        `${currentRoleInfo.icon} ${currentRoleInfo.name}`;
                    
                    buildRolesGrid(member.role);
                    
                    document.getElementById('promotionModal').classList.add('show');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast('فشل تحميل بيانات العضو', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('حدث خطأ في تحميل البيانات', 'error');
            });
        }

        // بناء شبكة الرتب
        function buildRolesGrid(currentRole) {
            const rolesGrid = document.getElementById('rolesGrid');
            rolesGrid.innerHTML = '';
            
            const currentLevel = rolesData[currentRole]?.level || 1;
            
            // تحديد الرتب المتاحة حسب دور المستخدم الحالي
            let availableRoles = [];
            
            if (currentUserRole === 'super_admin') {
                // المدير العام: جميع الرتب (بدون super_admin)
                availableRoles = ['user', 'moderator', 'supervisor', 'monitor', 'admin'];
            } else if (currentUserRole === 'admin') {
                // المدير: فقط الرتب الأقل منه
                availableRoles = ['user', 'moderator', 'supervisor', 'monitor'];
            } else if (currentUserRole === 'monitor') {
                // المراقب: فقط user, moderator, supervisor
                availableRoles = ['user', 'moderator', 'supervisor'];
            }
            
            availableRoles.forEach(roleKey => {
                const role = rolesData[roleKey];
                const isCurrentRole = roleKey === currentRole;
                
                let isDisabled = isCurrentRole;
                let disabledReason = '';
                
                if (isCurrentRole) {
                    disabledReason = 'الرتبة الحالية';
                }
                
                const roleDiv = document.createElement('div');
                roleDiv.className = `role-option role-${roleKey.replace('_', '-')}`;
                
                if (isDisabled) {
                    roleDiv.classList.add('disabled');
                    roleDiv.title = disabledReason;
                } else {
                    roleDiv.onclick = () => selectRole(roleKey, roleDiv);
                }
                
                roleDiv.innerHTML = `
                    <div class="role-icon">${role.icon}</div>
                    <div class="role-name">${role.name}</div>
                    ${isCurrentRole ? '<div style="font-size: 11px; color: #666; margin-top: 5px;">الحالية</div>' : ''}
                `;
                
                rolesGrid.appendChild(roleDiv);
            });
            
            document.getElementById('confirmPromotionBtn').disabled = true;
        }

        // اختيار رتبة
        function selectRole(roleKey, element) {
            selectedNewRole = roleKey;
            
            document.querySelectorAll('.role-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            element.classList.add('selected');
            
            document.getElementById('confirmPromotionBtn').disabled = false;
        }

        // تأكيد الترقية
        function confirmPromotion() {
            if (!selectedNewRole || !currentPromotionUserId) {
                showToast('يرجى اختيار رتبة أولاً', 'error');
                return;
            }
            
            const roleInfo = rolesData[selectedNewRole];
            
            if (!confirm(`هل أنت متأكد من ترقية هذا العضو إلى رتبة "${roleInfo.name}"؟`)) {
                return;
            }
            
            // حفظ القيم قبل إغلاق النافذة
            const userIdToPromote = currentPromotionUserId;
            const roleToPromote = selectedNewRole;
            
            // إغلاق النافذة
            closePromotionModal();
            
            // عرض رسالة تحميل
            showToast('جاري معالجة الترقية...', 'success');
            
            // إنشاء FormData
            const formData = new FormData();
            formData.append('admin_action', 'change_role');
            formData.append('user_id', userIdToPromote);
            formData.append('new_role', roleToPromote);
            
            // تسجيل للتشخيص
            console.log('=== إرسال طلب الترقية ===');
            console.log('admin_action:', 'change_role');
            console.log('user_id:', userIdToPromote);
            console.log('new_role:', roleToPromote);
            
            fetch('members.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('=== استجابة السيرفر ===');
                console.log('Status:', response.status);
                return response.text(); // نستخدم text أولاً للتشخيص
            })
            .then(text => {
                console.log('Response Text:', text);
                
                // محاولة تحويله إلى JSON
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    
                    if (data.success) {
                        showToast(data.message || 'تمت الترقية بنجاح', 'success');
                        setTimeout(() => loadMembers(), 1500);
                    } else {
                        showToast(data.message || 'فشلت العملية', 'error');
                        
                        // إذا كان هناك debug info
                        if (data.debug) {
                            console.error('Debug Info:', data.debug);
                        }
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Raw Response:', text);
                    showToast('خطأ في معالجة الاستجابة من السيرفر', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showToast('حدث خطأ في الاتصال', 'error');
            });
        }

        // إغلاق صندوق الترقية
        function closePromotionModal() {
            document.getElementById('promotionModal').classList.remove('show');
            document.body.style.overflow = '';
            currentPromotionUserId = null;
            selectedNewRole = null;
        }

        // إغلاق عند الضغط على ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('promotionModal');
                if (modal && modal.classList.contains('show')) {
                    closePromotionModal();
                }
            }
        });
    </script>

    <!-- صندوق ترقية العضوية -->
    <div class="promotion-modal" id="promotionModal">
        <div class="promotion-modal-overlay" onclick="closePromotionModal()"></div>
        <div class="promotion-modal-content">
            <div class="promotion-modal-header">
                <h3>⚙️ خيارات الترقية</h3>
                <button class="promotion-modal-close" onclick="closePromotionModal()">✕</button>
            </div>
            
            <div class="promotion-modal-body">
                <div class="member-info-box">
                    <div class="member-details">
                        <div class="member-name" id="promotionMemberName">اسم العضو</div>
                        <div class="member-current-role">
                            الرتبة الحالية: <span id="promotionCurrentRole">عضو</span>
                        </div>
                    </div>
                </div>
                
                <div class="promotion-arrow">⬇️</div>
                
                <div class="roles-selection">
                    <p class="selection-label">اختر الرتبة الجديدة:</p>
                    <div class="roles-grid" id="rolesGrid">
                        <!-- سيتم ملؤها ديناميكياً -->
                    </div>
                </div>
            </div>
            
            <div class="promotion-modal-footer">
                <button class="btn-cancel" onclick="closePromotionModal()">إلغاء</button>
                <button class="btn-confirm" id="confirmPromotionBtn" onclick="confirmPromotion()">تأكيد الترقية</button>
            </div>
        </div>
    </div>
</body>
</html>