<?php
require_once 'database.php';

function checkDatabaseConnection() {
    try {
        if (!databaseExists()) {
            return [
                'status' => 'error',
                'message' => 'قاعدة البيانات "' . DB_NAME . '" غير موجودة على السيرفر',
                'action' => 'create_database'
            ];
        }
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        if ($conn) {
            // التحقق من وجود الجداول المطلوبة
            $tables_check = checkRequiredTables();
            
            if (!$tables_check['success']) {
                return [
                    'status' => 'warning',
                    'message' => 'قاعدة البيانات موجودة لكن بعض الجداول مفقودة',
                    'missing_tables' => $tables_check['missing_tables'],
                    'action' => 'create_tables'
                ];
            }
            
            return [
                'status' => 'success',
                'message' => 'تم الاتصال بقاعدة البيانات بنجاح',
                'database' => DB_NAME,
                'host' => DB_HOST,
                'tables' => $tables_check['existing_tables']
            ];
        }
    } catch(Exception $e) {
        return [
            'status' => 'error',
            'message' => 'فشل الاتصال: ' . $e->getMessage()
        ];
    }
}

// التحقق من وجود قاعدة البيانات
function databaseExists() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $conn = new PDO($dsn, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([DB_NAME]);
        
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

// التحقق من الجداول المطلوبة
function checkRequiredTables() {
    $required_tables = [
        'TI_users',
        'TI_sessions',
        'TI_login_attempts',
        'TI_notifications',
        'TI_settings',
        'TI_audit_log',
        'TI_permissions',
        'TI_user_permissions'
    ];
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?");
        $stmt->execute([DB_NAME]);
        
        $existing_tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_tables[] = $row['TABLE_NAME'];
        }
        
        $missing_tables = array_diff($required_tables, $existing_tables);
        
        if (empty($missing_tables)) {
            return [
                'success' => true,
                'existing_tables' => $existing_tables
            ];
        } else {
            return [
                'success' => false,
                'existing_tables' => $existing_tables,
                'missing_tables' => array_values($missing_tables)
            ];
        }
    } catch(Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// إنشاء قاعدة البيانات والجداول
function initializeDatabase() {
    try {
        // الاتصال بدون تحديد قاعدة بيانات
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $conn = new PDO($dsn, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // إنشاء قاعدة البيانات
        $conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_COLLATION);
        
        // قراءة ملف schema.sql
        $schema_file = __DIR__ . '/schema.sql';
        
        if (!file_exists($schema_file)) {
            return [
                'status' => 'error',
                'message' => 'ملف schema.sql غير موجود'
            ];
        }
        
        $sql = file_get_contents($schema_file);
        
        // تنفيذ الاستعلامات
        $conn->exec("USE `" . DB_NAME . "`");
        $conn->exec($sql);
        
        return [
            'status' => 'success',
            'message' => 'تم إنشاء قاعدة البيانات والجداول بنجاح'
        ];
        
    } catch(PDOException $e) {
        return [
            'status' => 'error',
            'message' => 'فشل إنشاء قاعدة البيانات: ' . $e->getMessage()
        ];
    }
}

// الحصول على معلومات قاعدة البيانات
function getDatabaseInfo() {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // حجم قاعدة البيانات
        $stmt = $conn->prepare("
            SELECT 
                SUM(data_length + index_length) / 1024 / 1024 AS size_mb,
                COUNT(*) AS table_count
            FROM information_schema.TABLES 
            WHERE table_schema = ?
        ");
        $stmt->execute([DB_NAME]);
        $db_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // عدد المستخدمين
        $users_count = $db->select("SELECT COUNT(*) as count FROM TI_users");
        
        // عدد الجلسات النشطة
        $sessions_count = $db->select("SELECT COUNT(*) as count FROM TI_sessions WHERE is_active = 1");
        
        return [
            'database_size' => round($db_info['size_mb'], 2) . ' MB',
            'table_count' => $db_info['table_count'],
            'users_count' => $users_count[0]['count'],
            'active_sessions' => $sessions_count[0]['count']
        ];
        
    } catch(Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

// تنظيف قاعدة البيانات
function cleanupDatabase() {
    try {
        $db = Database::getInstance();
        
        // حذف الجلسات المنتهية
        $db->execute("DELETE FROM TI_sessions WHERE expires_at < NOW() OR (is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))");
        
        // حذف محاولات تسجيل الدخول القديمة
        $db->execute("DELETE FROM TI_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // حذف الرموز المنتهية
        $db->execute("UPDATE TI_users SET reset_token = NULL, reset_token_expiry = NULL WHERE reset_token_expiry < NOW()");
        
        // حذف الإشعارات القديمة المقروءة
        $db->execute("DELETE FROM TI_notifications WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        return [
            'status' => 'success',
            'message' => 'تم تنظيف قاعدة البيانات بنجاح'
        ];
        
    } catch(Exception $e) {
        return [
            'status' => 'error',
            'message' => 'فشل التنظيف: ' . $e->getMessage()
        ];
    }
}

// اختبار الاتصال
function testConnection() {
    $results = [];
    
    // اختبار الاتصال بالسيرفر
    try {
        $dsn = "mysql:host=" . DB_HOST;
        $conn = new PDO($dsn, DB_USER, DB_PASS);
        $results['server_connection'] = 'نجح';
    } catch(PDOException $e) {
        $results['server_connection'] = 'فشل: ' . $e->getMessage();
        return $results;
    }
    
    // اختبار وجود قاعدة البيانات
    $results['database_exists'] = databaseExists() ? 'نعم' : 'لا';
    
    // اختبار الجداول
    if (databaseExists()) {
        $tables_check = checkRequiredTables();
        $results['tables_status'] = $tables_check['success'] ? 'جميع الجداول موجودة' : 'بعض الجداول مفقودة';
        
        if (!$tables_check['success']) {
            $results['missing_tables'] = $tables_check['missing_tables'];
        }
    }
    
    return $results;
}
?>