<?php
/**
 * ملف معالجة حفظ إعدادات الموقع عبر Ajax
 */

require_once '../Auth.php';
require_once '../database.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
$db = Database::getInstance();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = $auth->getCurrentUser();

// السماح فقط للمدير العام والمدير بالدخول
$allowedRoles = ['super_admin', 'admin'];

if (!in_array($currentUser['role'], $allowedRoles)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه الصفحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

// الجدول موجود مسبقاً: ti_settings

// استقبال البيانات
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

// تسجيل البيانات المستلمة للتشخيص
error_log("البيانات المستلمة: " . print_r($input, true));

$siteName = isset($input['site_name']) ? trim($input['site_name']) : '';
$siteDescription = isset($input['site_description']) ? $input['site_description'] : ''; // لا نستخدم trim هنا لأن HTML قد يحتوي على مسافات مهمة
$siteEmail = isset($input['site_email']) ? trim($input['site_email']) : '';

// تسجيل معلومات site_description
if (isset($input['site_description'])) {
    error_log("site_description موجود - الطول: " . strlen($siteDescription));
    error_log("site_description البداية: " . substr($siteDescription, 0, 100));
    error_log("site_description النهاية: " . substr($siteDescription, -100));
    
    // تنظيف HTML من الأحرف التي قد تسبب مشاكل في JSON
    $siteDescription = str_replace(["\r\n", "\r", "\n"], " ", $siteDescription);
    $siteDescription = preg_replace('/\s+/', ' ', $siteDescription);
    $siteDescription = trim($siteDescription);
}
$maintenanceMode = isset($input['maintenance_mode']) ? trim($input['maintenance_mode']) : 'open';
$maintenanceAllowedUsers = isset($input['maintenance_allowed_users']) ? $input['maintenance_allowed_users'] : [];

// التحقق من صحة البيانات
$errors = [];

// إذا كان الطلب يحتوي على maintenance_allowed_users فقط، نتخطى التحقق من الحقول الأخرى
$isOnlyAllowedUsers = isset($input['maintenance_allowed_users']) && !isset($input['site_name']) && !isset($input['site_email']) && !isset($input['site_description']);

// إذا كان الطلب يحتوي على site_description فقط، نتخطى التحقق من الحقول الأخرى
$isOnlyDescription = isset($input['site_description']) && !isset($input['site_name']) && !isset($input['site_email']) && !isset($input['maintenance_allowed_users']);

if (!$isOnlyAllowedUsers && !$isOnlyDescription) {
    if (empty($siteName)) {
        $errors[] = 'اسم الموقع مطلوب';
    }

    if (empty($siteEmail)) {
        $errors[] = 'البريد الإلكتروني مطلوب';
    } elseif (!filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
}

if (!empty($errors)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)], JSON_UNESCAPED_UNICODE);
    exit;
}

// حفظ البيانات في قاعدة البيانات
try {
    if (!$db->isConnected()) {
        throw new Exception('لا يوجد اتصال بقاعدة البيانات');
    }
    
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('فشل الحصول على اتصال قاعدة البيانات');
    }
    
    error_log("بدء حفظ الإعدادات: site_name=$siteName, site_email=$siteEmail");
    
    // حفظ اسم الموقع (فقط إذا كان موجوداً في الطلب)
    if (isset($input['site_name'])) {
        $saveSiteName = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                         VALUES ('site_name', :value, 'string', 'general')
                         ON DUPLICATE KEY UPDATE setting_value = :value2";
        $stmt1 = $conn->prepare($saveSiteName);
        $result1 = $stmt1->execute([
            ':value' => $siteName,
            ':value2' => $siteName
        ]);
        
        if (!$result1) {
            throw new Exception('فشل حفظ اسم الموقع');
        }
    }
    
    // حفظ وصف الموقع (فقط إذا كان موجوداً في الطلب)
    if (isset($input['site_description'])) {
        // التحقق من أن المحتوى ليس فارغاً (بما في ذلك HTML فارغ)
        $tempDescription = strip_tags($siteDescription);
        $tempDescription = trim($tempDescription);
        
        // إذا كان المحتوى فارغاً، احفظه كسلسلة فارغة
        if (empty($tempDescription)) {
            $siteDescription = '';
        }
        
        $saveSiteDescription = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                                VALUES ('site_description', :value, 'string', 'general')
                                ON DUPLICATE KEY UPDATE setting_value = :value2";
        $stmt2 = $conn->prepare($saveSiteDescription);
        $result2 = $stmt2->execute([
            ':value' => $siteDescription,
            ':value2' => $siteDescription
        ]);
        
        if (!$result2) {
            $errorInfo = $stmt2->errorInfo();
            error_log("خطأ في حفظ وصف الموقع: " . print_r($errorInfo, true));
            throw new Exception('فشل حفظ وصف الموقع: ' . ($errorInfo[2] ?? 'خطأ غير معروف'));
        }
    }
    
    // حفظ البريد الإلكتروني (فقط إذا كان موجوداً في الطلب)
    if (isset($input['site_email'])) {
        $saveSiteEmail = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                          VALUES ('site_email', :value, 'string', 'general')
                          ON DUPLICATE KEY UPDATE setting_value = :value2";
        $stmt3 = $conn->prepare($saveSiteEmail);
        $result3 = $stmt3->execute([
            ':value' => $siteEmail,
            ':value2' => $siteEmail
        ]);
        
        if (!$result3) {
            throw new Exception('فشل حفظ البريد الإلكتروني');
        }
    }
    
    // حفظ وضع الصيانة (فقط إذا كان موجوداً في الطلب)
    if (isset($input['maintenance_mode'])) {
        $saveMaintenanceMode = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                               VALUES ('maintenance_mode', :value, 'string', 'general')
                               ON DUPLICATE KEY UPDATE setting_value = :value2";
        $stmt4 = $conn->prepare($saveMaintenanceMode);
        $result4 = $stmt4->execute([
            ':value' => $maintenanceMode,
            ':value2' => $maintenanceMode
        ]);
        
        if (!$result4) {
            throw new Exception('فشل حفظ وضع الصيانة');
        }
    }
    
    // حفظ الأعضاء المصرح لهم (فقط للمدير العام)
    if ($currentUser['role'] === 'super_admin') {
        // التأكد من أن maintenanceAllowedUsers هو array
        if (!is_array($maintenanceAllowedUsers)) {
            $maintenanceAllowedUsers = [];
        }
        
        // تحويل array إلى JSON
        $allowedUsersJson = json_encode($maintenanceAllowedUsers);
        
        $saveAllowedUsers = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                             VALUES ('maintenance_allowed_users', :value, 'string', 'general')
                             ON DUPLICATE KEY UPDATE setting_value = :value2";
        $stmt5 = $conn->prepare($saveAllowedUsers);
        $result5 = $stmt5->execute([
            ':value' => $allowedUsersJson,
            ':value2' => $allowedUsersJson
        ]);
        
        if (!$result5) {
            throw new Exception('فشل حفظ الأعضاء المصرح لهم');
        }
    }
    
    error_log("تم حفظ جميع الإعدادات بنجاح");
    
    // إرسال الاستجابة
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    $response = [
        'success' => true, 
        'message' => 'تم حفظ الإعدادات بنجاح'
    ];
    
    $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($jsonResponse === false) {
        error_log("خطأ في json_encode: " . json_last_error_msg());
        $response = [
            'success' => false,
            'message' => 'خطأ في ترميز الاستجابة: ' . json_last_error_msg()
        ];
        $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
    error_log("الاستجابة المرسلة: " . substr($jsonResponse, 0, 200));
    echo $jsonResponse;
    
} catch (PDOException $e) {
    error_log("خطأ PDO في حفظ الإعدادات: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    error_log("خطأ في حفظ الإعدادات: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

