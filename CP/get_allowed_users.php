<?php
/**
 * ملف جلب بيانات الأعضاء المصرح لهم
 */

require_once '../Auth.php';
require_once '../database.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
$db = Database::getInstance();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$currentUser = $auth->getCurrentUser();

// السماح فقط للمدير العام
if ($currentUser['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه الصفحة']);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

// استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$userIds = isset($input['user_ids']) && is_array($input['user_ids']) ? $input['user_ids'] : [];

if (empty($userIds)) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

try {
    // تنظيف IDs
    $userIds = array_map('intval', $userIds);
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    
    $query = "SELECT id, fullname, username, email, role 
              FROM TI_users 
              WHERE id IN ($placeholders)";
    
    $users = $db->select($query, $userIds);
    
    if (!is_array($users)) {
        $users = [];
    }
    
    // تنسيق النتائج
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => $user['id'],
            'fullname' => $user['fullname'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'display' => $user['fullname'] . ' (' . $user['username'] . ')'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $formattedUsers
    ]);
    
} catch (Exception $e) {
    error_log("خطأ في جلب بيانات الأعضاء: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في جلب البيانات',
        'users' => []
    ]);
}
?>

