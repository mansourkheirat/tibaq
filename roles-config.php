<?php
/**
 * إعدادات الرتب والألوان
 */

// تعريف الرتب
define('ROLES', [
    'super_admin' => [
        'name' => 'المدير العام',
        'icon' => '👑',
        'color' => '#1a1a1a',
        'bg_color' => '#e5e5e5',
        'level' => 6
    ],
    'admin' => [
        'name' => 'مدير',
        'icon' => '🔴',
        'color' => '#dc2626',
        'bg_color' => '#fee2e2',
        'level' => 5
    ],
    'monitor' => [
        'name' => 'مراقب',
        'icon' => '🟤',
        'color' => '#92400e',
        'bg_color' => '#fef3c7',
        'level' => 4
    ],
    'supervisor' => [
        'name' => 'مشرف',
        'icon' => '🟠',
        'color' => '#ea580c',
        'bg_color' => '#ffedd5',
        'level' => 3
    ],
    'moderator' => [
        'name' => 'مشرف مساعد',
        'icon' => '🔵',
        'color' => '#2563eb',
        'bg_color' => '#dbeafe',
        'level' => 2
    ],
    'user' => [
        'name' => 'عضو',
        'icon' => '👤',
        'color' => '#1976d2',
        'bg_color' => '#e3f2fd',
        'level' => 1
    ]
]);

/**
 * الحصول على معلومات الرتبة
 */
function getRoleInfo($role) {
    return ROLES[$role] ?? ROLES['user'];
}

/**
 * الحصول على اسم الرتبة
 */
function getRoleName($role) {
    $info = getRoleInfo($role);
    return $info['name'];
}

/**
 * الحصول على أيقونة الرتبة
 */
function getRoleIcon($role) {
    $info = getRoleInfo($role);
    return $info['icon'];
}

/**
 * الحصول على لون الرتبة
 */
function getRoleColor($role) {
    $info = getRoleInfo($role);
    return $info['color'];
}

/**
 * الحصول على لون خلفية الرتبة
 */
function getRoleBgColor($role) {
    $info = getRoleInfo($role);
    return $info['bg_color'];
}

/**
 * مقارنة مستوى الرتب
 */
function compareRoles($role1, $role2) {
    $info1 = getRoleInfo($role1);
    $info2 = getRoleInfo($role2);
    return $info1['level'] - $info2['level'];
}
?>