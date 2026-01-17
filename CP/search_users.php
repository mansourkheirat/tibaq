<?php
/**
 * ملف البحث عن الأعضاء للسماح لهم بالدخول في وضع الصيانة
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

$searchTerm = isset($input['search']) ? trim($input['search']) : '';

if (empty($searchTerm) || strlen($searchTerm) < 1) {
    echo json_encode(['success' => true, 'users' => [], 'no_results' => false]);
    exit;
}

try {
    // البحث عن الأعضاء بالاسم الكامل أو اسم المستخدم
    // البحث يبدأ بالحرف المكتوب فقط (LIKE 'term%' بدلاً من LIKE '%term%')
    // استثناء المدير العام والمدير والمراقب (لأنهم مسموحون تلقائياً)
    $query = "SELECT id, fullname, username, email, role 
              FROM TI_users 
              WHERE (fullname LIKE ? OR username LIKE ?) 
              AND role NOT IN ('super_admin', 'admin', 'monitor')
              AND is_active = 1
              LIMIT 10";
    
    // البحث يبدأ بالحرف المكتوب فقط
    $searchPattern = $searchTerm . '%';
    $users = $db->select($query, [$searchPattern, $searchPattern]);
    
    // إذا كان البحث بحرف واحد فقط ولا توجد نتائج
    $noResults = false;
    if (strlen($searchTerm) === 1 && (empty($users) || !is_array($users) || count($users) === 0)) {
        $noResults = true;
    }
    
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
        'users' => $formattedUsers,
        'no_results' => $noResults ?? false,
        'search_term' => $searchTerm
    ]);
    
} catch (Exception $e) {
    error_log("خطأ في البحث عن الأعضاء: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في البحث',
        'users' => []
    ]);
}
?>

