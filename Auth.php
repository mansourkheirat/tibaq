<?php
require_once 'database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_duration = 30; // بالدقائق
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadSecuritySettings();
    }
    
    // تحميل إعدادات الأمان من قاعدة البيانات
    private function loadSecuritySettings() {
        try {
            $settings = $this->db->select("SELECT setting_key, setting_value FROM TI_settings WHERE category = 'security'");
            
            // التحقق من أن النتيجة array وليس string (في حالة الخطأ)
            if (is_array($settings) && !isset($settings['error'])) {
                foreach ($settings as $setting) {
                    if (isset($setting['setting_key']) && $setting['setting_key'] === 'max_login_attempts') {
                        $this->max_login_attempts = (int)$setting['setting_value'];
                    }
                    if (isset($setting['setting_key']) && $setting['setting_key'] === 'account_lockout_duration') {
                        $this->lockout_duration = (int)$setting['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // استخدم القيم الافتراضية في حالة الخطأ
        }
    }
    
    // تسجيل مستخدم جديد
    public function register($fullname, $username, $email, $password, $confirm_password) {
        $errors = [];
        
        // التحقق من المدخلات
        if (empty($fullname) || strlen($fullname) < 3) {
            $errors[] = "الاسم الكامل يجب أن يكون 3 أحرف على الأقل";
        }
        
        if (empty($username) || strlen($username) < 4) {
            $errors[] = "اسم المستخدم يجب أن يكون 4 أحرف على الأقل";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "اسم المستخدم يجب أن يحتوي على أحرف وأرقام وشرطة سفلية فقط";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "البريد الإلكتروني غير صالح";
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "كلمتا المرور غير متطابقتين";
        }
        
        // التحقق من عدم وجود المستخدم مسبقاً
        if (empty($errors)) {
            $query = "SELECT id FROM TI_users WHERE username = ? OR email = ?";
            $result = $this->db->select($query, [$username, $email]);
            
            if (!empty($result)) {
                $errors[] = "اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل";
            }
        }
        
        // إذا لم تكن هناك أخطاء، قم بالتسجيل
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
            $verification_token = bin2hex(random_bytes(32));
            
            $query = "INSERT INTO TI_users (fullname, username, email, password, verification_token, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            
            if ($this->db->execute($query, [$fullname, $username, $email, $hashed_password, $verification_token])) {
                $user_id = $this->db->lastInsertId();
                
                // تسجيل في Audit Log
                $this->logAudit($user_id, 'user_registered', 'TI_users', $user_id);
                
                // إنشاء إشعار ترحيبي
                $this->createNotification($user_id, 'مرحباً بك', 'تم إنشاء حسابك بنجاح. نتمنى لك تجربة ممتعة!', 'success');
                
                return [
                    'success' => true,
                    'message' => 'تم إنشاء الحساب بنجاح',
                    'user_id' => $user_id
                ];
            } else {
                $errors[] = "حدث خطأ أثناء إنشاء الحساب";
            }
        }
        
        return [
            'success' => false,
            'errors' => $errors
        ];
    }
    
    // تسجيل الدخول
    public function login($username_or_email, $password, $remember = false) {
        $errors = [];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($username_or_email) || empty($password)) {
            $errors[] = "جميع الحقول مطلوبة";
            return ['success' => false, 'errors' => $errors];
        }
        
        // التحقق من محاولات تسجيل الدخول الفاشلة
        $query = "SELECT COUNT(*) as attempts FROM TI_login_attempts 
                  WHERE username_or_email = ? AND success = 0 
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $result = $this->db->select($query, [$username_or_email]);
        
        if (!empty($result) && $result[0]['attempts'] >= $this->max_login_attempts) {
            $errors[] = "تم تجاوز عدد محاولات تسجيل الدخول المسموحة. يرجى المحاولة بعد ساعة";
            $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, false, 'too_many_attempts');
            return ['success' => false, 'errors' => $errors];
        }
        
        // البحث عن المستخدم
        $query = "SELECT * FROM TI_users WHERE (username = ? OR email = ?) LIMIT 1";
        $result = $this->db->select($query, [$username_or_email, $username_or_email]);
        
        if (empty($result)) {
            $errors[] = "اسم المستخدم أو كلمة المرور غير صحيحة";
            $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, false, 'invalid_credentials');
            return ['success' => false, 'errors' => $errors];
        }
        
        $user = $result[0];
        
        // التحقق من حالة القفل
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $errors[] = "حسابك مقفل مؤقتاً. يرجى المحاولة لاحقاً";
            $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, false, 'account_locked');
            return ['success' => false, 'errors' => $errors];
        }
        
        // التحقق من كلمة المرور
        if (!password_verify($password, $user['password'])) {
            // زيادة عدد المحاولات الفاشلة
            $failed_attempts = $user['failed_login_attempts'] + 1;
            
            if ($failed_attempts >= $this->max_login_attempts) {
                $locked_until = date('Y-m-d H:i:s', strtotime("+{$this->lockout_duration} minutes"));
                $this->db->execute(
                    "UPDATE TI_users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?",
                    [$failed_attempts, $locked_until, $user['id']]
                );
                $errors[] = "تم قفل حسابك لمدة {$this->lockout_duration} دقيقة بسبب المحاولات الفاشلة المتكررة";
            } else {
                $this->db->execute(
                    "UPDATE TI_users SET failed_login_attempts = ? WHERE id = ?",
                    [$failed_attempts, $user['id']]
                );
                $remaining = $this->max_login_attempts - $failed_attempts;
                $errors[] = "اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: {$remaining}";
            }
            
            $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, false, 'wrong_password');
            return ['success' => false, 'errors' => $errors];
        }
        
        // التحقق من حالة الحساب
        if ($user['is_active'] == 0) {
            $errors[] = "حسابك غير مفعل. يرجى التواصل مع الإدارة";
            $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, false, 'account_inactive');
            return ['success' => false, 'errors' => $errors];
        }
        
        // إعادة تعيين المحاولات الفاشلة
        $this->db->execute(
            "UPDATE TI_users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW(), login_count = login_count + 1 WHERE id = ?",
            [$user['id']]
        );
        
        // إنشاء الجلسة
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $ip_address;
        
        // حفظ الجلسة في قاعدة البيانات
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $this->db->execute(
            "INSERT INTO TI_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$user['id'], $session_token, $ip_address, substr($user_agent, 0, 255), $expires_at]
        );
        
        $_SESSION['session_token'] = $session_token;
        
        // خيار "تذكرني"
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 يوم
            
            setcookie('remember_token', $token, [
                'expires' => $expiry,
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            $this->db->execute("UPDATE TI_users SET remember_token = ? WHERE id = ?", [$token, $user['id']]);
        }
        
        // تسجيل محاولة ناجحة
        $this->logLoginAttempt($username_or_email, $ip_address, $user_agent, true);
        
        // تسجيل في Audit Log
        $this->logAudit($user['id'], 'user_login', 'TI_users', $user['id']);
        
        return [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ]
        ];
    }
    
    // تسجيل الخروج
    public function logout() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            // تسجيل الخروج في Audit Log
            $this->logAudit($_SESSION['user_id'], 'user_logout', 'TI_users', $_SESSION['user_id']);
            
            // إلغاء تفعيل الجلسة
            $this->db->execute(
                "UPDATE TI_sessions SET is_active = 0 WHERE session_token = ?",
                [$_SESSION['session_token']]
            );
        }
        
        // حذف الكوكيز
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // حذف الجلسة
        session_unset();
        session_destroy();
        
        return true;
    }
    
    // التحقق من تسجيل الدخول
    public function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // التحقق من صلاحية الجلسة
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    // الحصول على المستخدم الحالي
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'fullname' => $_SESSION['fullname'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    
    // توليد وتحقق CSRF Token
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // تغيير كلمة المرور
    public function changePassword($user_id, $old_password, $new_password, $confirm_password) {
        $errors = [];
        
        if ($new_password !== $confirm_password) {
            $errors[] = "كلمتا المرور الجديدتان غير متطابقتين";
            return ['success' => false, 'errors' => $errors];
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
            return ['success' => false, 'errors' => $errors];
        }
        
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام";
            return ['success' => false, 'errors' => $errors];
        }
        
        // التحقق من كلمة المرور القديمة
        $query = "SELECT password FROM TI_users WHERE id = ?";
        $result = $this->db->select($query, [$user_id]);
        
        if (empty($result)) {
            $errors[] = "المستخدم غير موجود";
            return ['success' => false, 'errors' => $errors];
        }
        
        if (!password_verify($old_password, $result[0]['password'])) {
            $errors[] = "كلمة المرور القديمة غير صحيحة";
            return ['success' => false, 'errors' => $errors];
        }
        
        // تحديث كلمة المرور
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        
        if ($this->db->execute("UPDATE TI_users SET password = ? WHERE id = ?", [$hashed_password, $user_id])) {
            // إلغاء جميع الجلسات الأخرى
            $this->db->execute("UPDATE TI_sessions SET is_active = 0 WHERE user_id = ? AND session_token != ?", 
                              [$user_id, $_SESSION['session_token'] ?? '']);
            
            // إنشاء إشعار
            $this->createNotification($user_id, 'تم تغيير كلمة المرور', 'تم تغيير كلمة المرور الخاصة بك بنجاح', 'success');
            
            return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح'];
        }
        
        $errors[] = "حدث خطأ أثناء تغيير كلمة المرور";
        return ['success' => false, 'errors' => $errors];
    }
    
    // طلب إعادة تعيين كلمة المرور
    public function requestPasswordReset($email) {
        $errors = [];
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "البريد الإلكتروني غير صالح";
            return ['success' => false, 'errors' => $errors];
        }
        
        $query = "SELECT id, fullname, email FROM TI_users WHERE email = ? AND is_active = 1";
        $result = $this->db->select($query, [$email]);
        
        if (!empty($result)) {
            $user = $result[0];
            
            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $update_query = "UPDATE TI_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
            
            if ($this->db->execute($update_query, [$reset_token, $expiry, $user['id']])) {
                return [
                    'success' => true,
                    'message' => 'تم إرسال رابط إعادة تعيين كلمة المرور',
                    'reset_token' => $reset_token,
                    'user' => $user
                ];
            }
        }
        
        // لأسباب أمنية، نعيد نفس الرسالة
        return [
            'success' => true,
            'message' => 'إذا كان البريد موجوداً، سيتم إرسال رابط إعادة التعيين'
        ];
    }
    
    // إعادة تعيين كلمة المرور
    public function resetPassword($token, $new_password, $confirm_password) {
        $errors = [];
        
        if (empty($token)) {
            $errors[] = "رمز إعادة التعيين غير صالح";
            return ['success' => false, 'errors' => $errors];
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "كلمتا المرور غير متطابقتين";
            return ['success' => false, 'errors' => $errors];
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = "كلمة المرور يجب أن تكون 8 أحرف على الأقل";
            return ['success' => false, 'errors' => $errors];
        }
        
        // التحقق من الرمز
        $query = "SELECT id FROM TI_users WHERE reset_token = ? AND reset_token_expiry > NOW()";
        $result = $this->db->select($query, [$token]);
        
        if (empty($result)) {
            $errors[] = "رمز إعادة التعيين غير صالح أو منتهي الصلاحية";
            return ['success' => false, 'errors' => $errors];
        }
        
        $user_id = $result[0]['id'];
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        
        // تحديث كلمة المرور وإلغاء الرمز
        $update_query = "UPDATE TI_users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, 
                        failed_login_attempts = 0, locked_until = NULL WHERE id = ?";
        
        if ($this->db->execute($update_query, [$hashed_password, $user_id])) {
            // إلغاء جميع الجلسات
            $this->db->execute("UPDATE TI_sessions SET is_active = 0 WHERE user_id = ?", [$user_id]);
            
            // تسجيل في Audit Log
            $this->logAudit($user_id, 'password_reset', 'TI_users', $user_id);
            
            // إنشاء إشعار
            $this->createNotification($user_id, 'تم إعادة تعيين كلمة المرور', 'تم إعادة تعيين كلمة المرور بنجاح', 'success');
            
            return ['success' => true, 'message' => 'تم إعادة تعيين كلمة المرور بنجاح'];
        }
        
        $errors[] = "حدث خطأ أثناء إعادة تعيين كلمة المرور";
        return ['success' => false, 'errors' => $errors];
    }
    
    // التحقق من الصلاحية
    public function hasPermission($user_id, $permission_name) {
        $query = "SELECT COUNT(*) as count FROM TI_user_permissions up
                  JOIN TI_permissions p ON up.permission_id = p.id
                  WHERE up.user_id = ? AND p.name = ?";
        
        $result = $this->db->select($query, [$user_id, $permission_name]);
        
        return !empty($result) && $result[0]['count'] > 0;
    }
    
    // التحقق من الدور
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    // تسجيل محاولة تسجيل الدخول
    private function logLoginAttempt($username_or_email, $ip_address, $user_agent, $success, $failure_reason = null) {
        $query = "INSERT INTO TI_login_attempts (username_or_email, ip_address, user_agent, success, failure_reason) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $this->db->execute($query, [
            $username_or_email,
            $ip_address,
            substr($user_agent, 0, 255),
            $success ? 1 : 0,
            $failure_reason
        ]);
    }
    
    // تسجيل في Audit Log
    private function logAudit($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
        $query = "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($query, [
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'],
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    }
    
    // إنشاء إشعار
    private function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
        $query = "INSERT INTO TI_notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)";
        return $this->db->execute($query, [$user_id, $title, $message, $type, $link]);
    }
    
    // الحصول على إحصائيات الأمان للمستخدم
    public function getSecurityStats($user_id) {
        $stats = [];
        
        // عدد الجلسات النشطة
        $sessions = $this->db->select("SELECT COUNT(*) as count FROM TI_sessions WHERE user_id = ? AND is_active = 1", [$user_id]);
        $stats['active_sessions'] = $sessions[0]['count'] ?? 0;
        
        // آخر محاولة تسجيل دخول فاشلة
        $last_failed = $this->db->select(
            "SELECT attempted_at, ip_address FROM TI_login_attempts 
             WHERE username_or_email IN (SELECT username FROM TI_users WHERE id = ? UNION SELECT email FROM TI_users WHERE id = ?) 
             AND success = 0 ORDER BY attempted_at DESC LIMIT 1",
            [$user_id, $user_id]
        );
        $stats['last_failed_login'] = $last_failed[0] ?? null;
        
        // عدد محاولات تسجيل الدخول الفاشلة في آخر 24 ساعة
        $failed_count = $this->db->select(
            "SELECT COUNT(*) as count FROM TI_login_attempts 
             WHERE username_or_email IN (SELECT username FROM TI_users WHERE id = ? UNION SELECT email FROM TI_users WHERE id = ?) 
             AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$user_id, $user_id]
        );
        $stats['failed_logins_24h'] = $failed_count[0]['count'] ?? 0;
        
        return $stats;
    }

    // التحقق من المدير العام
    public function isSuperAdmin() {
        return $this->hasRole('super_admin');
    }

    // التحقق من صلاحية تعديل مستخدم
    public function canModifyUser($targetUserId) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $currentUser = $this->getCurrentUser();
        
        // المدير العام لا يمكن تعديله إلا من نفسه
        if ($targetUserId == 1 && $currentUser['id'] != 1) {
            return false;
        }
        
        // المستخدم يمكنه تعديل نفسه فقط
        if ($currentUser['role'] == 'user') {
            return $currentUser['id'] == $targetUserId;
        }
        
        return true;
    }

    // الحصول على قائمة الأدوار حسب الصلاحية
    public function getAvailableRoles() {
        if (!$this->isLoggedIn()) {
            return [];
        }
        
        $currentUser = $this->getCurrentUser();
        
        switch($currentUser['role']) {
            case 'super_admin':
                return ['user', 'moderator', 'supervisor', 'monitor', 'admin', 'super_admin'];
            case 'admin':
                return ['user', 'moderator', 'supervisor', 'monitor', 'admin'];
            case 'monitor':
                return ['user', 'moderator', 'supervisor'];
            case 'supervisor':
                return ['user', 'moderator'];
            case 'moderator':
                return ['user'];
            default:
                return [];
        }
    }
}
?>