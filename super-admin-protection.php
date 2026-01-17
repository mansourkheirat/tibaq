<?php
/**
 * حماية المدير العام من التعديل أو الحذف
 */

require_once 'database.php';

function protectSuperAdmin($userId, $action = 'modify') {
    // التحقق من أن المستخدم هو المدير العام
    if ($userId == 1) {
        $messages = [
            'modify' => '🔒 لا يمكن تعديل بيانات المدير العام',
            'delete' => '🔒 لا يمكن حذف المدير العام',
            'suspend' => '🔒 لا يمكن تجميد حساب المدير العام',
            'role_change' => '🔒 لا يمكن تغيير رتبة المدير العام',
            'deactivate' => '🔒 لا يمكن تعطيل حساب المدير العام'
        ];
        
        return [
            'allowed' => false,
            'message' => $messages[$action] ?? '🔒 عملية غير مسموحة على المدير العام'
        ];
    }
    
    // التحقق من قاعدة البيانات
    $db = Database::getInstance();
    $check = $db->select("SELECT locked FROM TI_super_admin_lock WHERE user_id = ?", [$userId]);
    
    if (!empty($check) && $check[0]['locked'] == 1) {
        return [
            'allowed' => false,
            'message' => '🔒 هذا الحساب محمي ولا يمكن التعديل عليه'
        ];
    }
    
    return ['allowed' => true];
}

function isSuperAdmin($userId) {
    return $userId == 1;
}

function canPromoteToSuperAdmin($currentUserRole) {
    // فقط المدير العام يمكنه ترقية شخص آخر لمدير عام
    return $currentUserRole === 'super_admin';
}

function preventRoleDowngrade($userId, $currentRole, $newRole) {
    // منع تخفيض رتبة المدير العام
    if ($userId == 1 && $newRole !== 'super_admin') {
        return [
            'allowed' => false,
            'message' => '🔒 لا يمكن تخفيض رتبة المدير العام'
        ];
    }
    
    return ['allowed' => true];
}
?>