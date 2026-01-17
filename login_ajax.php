<?php
/**
 * ملف معالجة تسجيل الدخول عبر Ajax
 */

// بدء الجلسة قبل أي شيء مع إعدادات مناسبة
if (session_status() === PHP_SESSION_NONE) {
    // التأكد من استخدام نفس إعدادات الجلسة
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once 'Auth.php';
require_once 'database.php';
require_once 'maintenance-check.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();

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

$username_or_email = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$remember = isset($input['remember']) ? (bool)$input['remember'] : false;
$csrf_token = isset($input['csrf_token']) ? $input['csrf_token'] : '';

// التحقق من CSRF Token
if (empty($csrf_token)) {
    echo json_encode([
        'success' => false, 
        'message' => 'رمز الأمان مطلوب',
        'errors' => ['رمز الأمان مطلوب']
    ]);
    exit;
}

if (!$auth->verifyCsrfToken($csrf_token)) {
    // محاولة إنشاء token جديد إذا لم يكن موجوداً
    if (!isset($_SESSION['csrf_token'])) {
        $auth->generateCsrfToken();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'رمز الأمان غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
        'errors' => ['رمز الأمان غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.'],
        'csrf_error' => true
    ]);
    exit;
}

// التحقق من الحقول
if (empty($username_or_email) || empty($password)) {
    echo json_encode([
        'success' => false, 
        'message' => 'جميع الحقول مطلوبة',
        'errors' => ['جميع الحقول مطلوبة']
    ]);
    exit;
}

// محاولة تسجيل الدخول
$result = $auth->login($username_or_email, $password, $remember);

if ($result['success']) {
    // التحقق من وضع الصيانة بعد تسجيل الدخول
    $maintenanceMode = getMaintenanceMode();
    
    if ($maintenanceMode === 'closed') {
        $currentUser = $auth->getCurrentUser();
        if (!canAccessInMaintenance($currentUser['role'], $currentUser['id'])) {
            // المستخدم ليس من الرتب المسموحة ولا من الأعضاء المصرح لهم
            $auth->logout();
            
            // تسجيل محاولة تسجيل دخول فاشلة
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 0)",
                [$username_or_email, $_SERVER['REMOTE_ADDR']]
            );
            
            echo json_encode([
                'success' => false,
                'message' => 'ليس لديك صلاحية للدخول في وضع الصيانة. فقط المدير العام والمدير والمراقب والأعضاء المصرح لهم يمكنهم الدخول.',
                'errors' => ['ليس لديك صلاحية للدخول في وضع الصيانة. فقط المدير العام والمدير والمراقب والأعضاء المصرح لهم يمكنهم الدخول.'],
                'maintenance_unauthorized' => true
            ]);
            exit;
        }
    }
    
    // تسجيل محاولة تسجيل دخول ناجحة
    $db = Database::getInstance();
    $db->execute(
        "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 1)",
        [$username_or_email, $_SERVER['REMOTE_ADDR']]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'redirect' => './'
    ]);
    exit;
} else {
    // تسجيل محاولة تسجيل دخول فاشلة
    $db = Database::getInstance();
    $db->execute(
        "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 0)",
        [$username_or_email, $_SERVER['REMOTE_ADDR']]
    );
    
    echo json_encode([
        'success' => false,
        'message' => 'فشل تسجيل الدخول',
        'errors' => $result['errors'] ?? ['اسم المستخدم أو كلمة المرور غير صحيحة']
    ]);
    exit;
}
?>

