<?php
/**
 * دوال مشتركة للموقع
 */

require_once 'database.php';

/**
 * الحصول على اسم الموقع من قاعدة البيانات
 * @return string اسم الموقع
 */
function getSiteName() {
    static $siteName = null;
    
    if ($siteName === null) {
        $db = Database::getInstance();
        
        // التحقق من الاتصال بقاعدة البيانات
        if (!$db->isConnected()) {
            $siteName = 'طباق وإسناد'; // القيمة الافتراضية
            return $siteName;
        }
        
        try {
            $result = $db->select(
                "SELECT setting_value FROM ti_settings WHERE setting_key = 'site_name' LIMIT 1"
            );
            
            // التحقق من وجود خطأ في النتيجة
            if (isset($result['error'])) {
                error_log("خطأ في استخراج اسم الموقع: " . $result['error']);
                $siteName = 'طباق وإسناد'; // القيمة الافتراضية
            } elseif (!empty($result) && isset($result[0]['setting_value']) && !empty($result[0]['setting_value'])) {
                $siteName = trim($result[0]['setting_value']);
            } else {
                $siteName = 'طباق وإسناد'; // القيمة الافتراضية
            }
        } catch (Exception $e) {
            error_log("خطأ في استخراج اسم الموقع: " . $e->getMessage());
            $siteName = 'طباق وإسناد'; // القيمة الافتراضية في حالة الخطأ
        }
    }
    
    return $siteName;
}

/**
 * الحصول على البريد الإلكتروني للموقع من قاعدة البيانات
 * @return string البريد الإلكتروني للموقع
 */
function getSiteEmail() {
    static $siteEmail = null;
    
    if ($siteEmail === null) {
        $db = Database::getInstance();
        
        // التحقق من الاتصال بقاعدة البيانات
        if (!$db->isConnected()) {
            $siteEmail = 'info@tibaq.com'; // القيمة الافتراضية
            return $siteEmail;
        }
        
        try {
            $result = $db->select(
                "SELECT setting_value FROM ti_settings WHERE setting_key = 'site_email' LIMIT 1"
            );
            
            // التحقق من وجود خطأ في النتيجة
            if (isset($result['error'])) {
                error_log("خطأ في استخراج البريد الإلكتروني للموقع: " . $result['error']);
                $siteEmail = 'info@tibaq.com'; // القيمة الافتراضية
            } elseif (!empty($result) && isset($result[0]['setting_value']) && !empty($result[0]['setting_value'])) {
                $siteEmail = trim($result[0]['setting_value']);
            } else {
                $siteEmail = 'info@tibaq.com'; // القيمة الافتراضية
            }
        } catch (Exception $e) {
            error_log("خطأ في استخراج البريد الإلكتروني للموقع: " . $e->getMessage());
            $siteEmail = 'info@tibaq.com'; // القيمة الافتراضية في حالة الخطأ
        }
    }
    
    return $siteEmail;
}

/**
 * إنشاء عنوان الصفحة
 * @param string $pageTitle عنوان الصفحة (مثل "لوحة الإدارة" أو اسم العضو)
 * @return string العنوان الكامل
 */
function getPageTitle($pageTitle = '') {
    $siteName = getSiteName();
    
    if (empty($pageTitle)) {
        return $siteName;
    }
    
    return $siteName . ' | ' . $pageTitle;
}

/**
 * عنوان الموقع
 */
function getSiteLogoHTML() {
    $siteName = getSiteName();
    return '<span>📊</span><span>' . htmlspecialchars($siteName) . '</span>';
}

function getSiteLogo() {
    return '📊';
}
?>

