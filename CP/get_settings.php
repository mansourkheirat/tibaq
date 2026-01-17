<?php
/**
 * ملف استخراج إعدادات الموقع
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

// السماح فقط للمدير العام والمدير بالدخول
$allowedRoles = ['super_admin', 'admin'];

if (!in_array($currentUser['role'], $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه الصفحة']);
    exit;
}

try {
    // استخراج الإعدادات من قاعدة البيانات
    $settings = $db->select(
        "SELECT setting_key, setting_value FROM ti_settings WHERE setting_key IN ('site_name', 'site_description', 'site_email')"
    );
    
    // تحويل النتائج إلى مصفوفة مفاتيح
    $settingsArray = [];
    foreach ($settings as $setting) {
        if (isset($setting['setting_key']) && isset($setting['setting_value'])) {
            $settingsArray[$setting['setting_key']] = $setting['setting_value'];
        }
    }
    
    // القيم الافتراضية
    $result = [
        'site_name' => $settingsArray['site_name'] ?? 'طباق وإسناد',
        'site_description' => $settingsArray['site_description'] ?? '',
        'site_email' => $settingsArray['site_email'] ?? 'info@tibaq.com'
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    error_log("خطأ في استخراج الإعدادات: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء استخراج الإعدادات',
        'data' => [
            'site_name' => 'طباق وإسناد',
            'site_description' => '',
            'site_email' => 'info@tibaq.com'
        ]
    ]);
}
?>

