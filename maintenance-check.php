<?php
/**
 * ملف التحقق من وضع الصيانة
 */

require_once 'database.php';

/**
 * الحصول على وضع الصيانة من قاعدة البيانات
 * @return string وضع الصيانة: 'open', 'locked', 'closed'
 */
function getMaintenanceMode() {
    static $mode = null;
    
    if ($mode === null) {
        $db = Database::getInstance();
        
        if (!$db->isConnected()) {
            $mode = 'open'; // في حالة عدم الاتصال، نعتبر الموقع مفتوح
            return $mode;
        }
        
        try {
            $result = $db->select(
                "SELECT setting_value FROM ti_settings WHERE setting_key = 'maintenance_mode' LIMIT 1"
            );
            
            if (!empty($result) && isset($result[0]['setting_value'])) {
                $value = $result[0]['setting_value'];
                
                // تحويل القيم القديمة (0, 1, 2) إلى القيم الجديدة
                if ($value === '0' || $value === 0 || $value === 'open') {
                    $mode = 'open';
                } elseif ($value === '1' || $value === 1 || $value === 'locked') {
                    $mode = 'locked';
                } elseif ($value === '2' || $value === 2 || $value === 'closed') {
                    $mode = 'closed';
                } else {
                    $mode = $value;
                }
            } else {
                $mode = 'open'; // القيمة الافتراضية
            }
        } catch (Exception $e) {
            error_log("خطأ في استخراج وضع الصيانة: " . $e->getMessage());
            $mode = 'open'; // في حالة الخطأ، نعتبر الموقع مفتوح
        }
    }
    
    return $mode;
}

/**
 * الحصول على قائمة الأعضاء المصرح لهم بالدخول في وضع الصيانة
 * @return array قائمة بمعرفات المستخدمين المصرح لهم
 */
function getMaintenanceAllowedUsers() {
    static $allowedUsers = null;
    
    if ($allowedUsers === null) {
        $db = Database::getInstance();
        
        if (!$db->isConnected()) {
            $allowedUsers = [];
            return $allowedUsers;
        }
        
        try {
            $result = $db->select(
                "SELECT setting_value FROM ti_settings WHERE setting_key = 'maintenance_allowed_users' LIMIT 1"
            );
            
            if (!empty($result) && isset($result[0]['setting_value'])) {
                $value = $result[0]['setting_value'];
                $decoded = json_decode($value, true);
                
                if (is_array($decoded)) {
                    // تحويل جميع القيم إلى أعداد صحيحة
                    $allowedUsers = array_map('intval', $decoded);
                } else {
                    $allowedUsers = [];
                }
            } else {
                $allowedUsers = [];
            }
        } catch (Exception $e) {
            error_log("خطأ في استخراج الأعضاء المصرح لهم: " . $e->getMessage());
            $allowedUsers = [];
        }
    }
    
    return $allowedUsers;
}

/**
 * التحقق من صلاحية المستخدم للدخول في وضع الصيانة
 * @param string $userRole رتبة المستخدم
 * @param int|null $userId معرف المستخدم (اختياري)
 * @return bool true إذا كان مسموحاً له بالدخول
 */
function canAccessInMaintenance($userRole, $userId = null) {
    $allowedRoles = ['super_admin', 'admin', 'monitor'];
    
    // إذا كانت الرتبة مسموحة، يمكنه الدخول
    if (in_array($userRole, $allowedRoles)) {
        return true;
    }
    
    // إذا تم توفير معرف المستخدم، التحقق من القائمة المصرح لهم
    if ($userId !== null) {
        $allowedUsers = getMaintenanceAllowedUsers();
        if (in_array((int)$userId, $allowedUsers)) {
            return true;
        }
    }
    
    return false;
}

/**
 * التحقق من وضع الصيانة وإعادة التوجيه إذا لزم الأمر
 * يجب استدعاء هذه الدالة في بداية الملفات الرئيسية
 */
function checkMaintenanceMode() {
    $maintenanceMode = getMaintenanceMode();
    
    // إذا كان الموقع مفتوح، لا حاجة للتحقق
    if ($maintenanceMode === 'open') {
        return;
    }
    
    // إذا كان الموقع مغلق، نحتاج للتحقق من تسجيل الدخول والرتبة
    if ($maintenanceMode === 'closed') {
        require_once 'Auth.php';
        $auth = new Auth();
        
        // إذا لم يكن مسجل دخول، أعد التوجيه لصفحة الدخول
        if (!$auth->isLoggedIn()) {
            header('Location: login?maintenance=closed');
            exit;
        }
        
        // التحقق من رتبة المستخدم والأعضاء المصرح لهم
        $currentUser = $auth->getCurrentUser();
        if (!canAccessInMaintenance($currentUser['role'], $currentUser['id'])) {
            // المستخدم مسجل دخول لكن ليس من الرتب المسموحة ولا من الأعضاء المصرح لهم
            $auth->logout();
            header('Location: login?maintenance=closed&unauthorized=1');
            exit;
        }
        
        // المستخدم مسموح له بالدخول، يمكنه المتابعة
        return;
    }
    
    // إذا كان الموقع مقفل (locked)، يمكن للأعضاء الدخول لكن التسجيل مغلق
    // سيتم تطبيق هذا لاحقاً في ملفات أخرى
    if ($maintenanceMode === 'locked') {
        // يمكن للأعضاء المسجلين الدخول
        // لكن التسجيل سيُمنع في register.php
        return;
    }
}
?>

