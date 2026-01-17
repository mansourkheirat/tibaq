<?php
/**
 * معالج تسجيل الخروج المركزي
 * يمكن استدعاؤه من أي صفحة
 */

require_once 'Auth.php';

$auth = new Auth();

// التحقق من تسجيل الدخول
if ($auth->isLoggedIn()) {
    // تسجيل الخروج
    $auth->logout();
}

// إعادة التوجيه للصفحة الرئيسية
header('Location: ./');
exit;
?>