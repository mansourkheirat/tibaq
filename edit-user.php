<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';
require_once 'database.php';
require_once 'site-functions.php';

$auth = new Auth();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

$errors = [];
$success = '';
$warnings = [];

// الحصول على بيانات المستخدم الكاملة
$query = "SELECT * FROM TI_users WHERE id = ?";
$userData = $db->select($query, [$user['id']]);

if (empty($userData)) {
    header('Location: dashboard');
    exit;
}

$currentUser = $userData[0];

// التحقق من إمكانية تغيير اسم المستخدم
$canChangeUsername = false;
$daysUntilChange = 0;

if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin' || $currentUser['role'] === 'moderator') {
    // المدير والمشرف يمكنهم التغيير في أي وقت
    $canChangeUsername = true;
} else {
    // المستخدم العادي: مرة كل 3 أشهر
    if (!empty($currentUser['username_last_changed'])) {
        $lastChanged = new DateTime($currentUser['username_last_changed']);
        $now = new DateTime();
        $threeMonthsAgo = (clone $now)->modify('-3 months');
        
        if ($lastChanged <= $threeMonthsAgo) {
            $canChangeUsername = true;
        } else {
            // حساب الأيام المتبقية
            $nextChangeDate = (clone $lastChanged)->modify('+3 months');
            $interval = $now->diff($nextChangeDate);
            $daysUntilChange = $interval->days;
        }
    } else {
        // لم يتم التغيير من قبل
        $canChangeUsername = true;
    }
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $new_username = trim($_POST['username'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // التحقق من كلمة المرور
        if (empty($password)) {
            $errors[] = "يجب إدخال كلمة المرور لتأكيد التغييرات";
        } elseif (!password_verify($password, $currentUser['password'])) {
            $errors[] = "كلمة المرور غير صحيحة";
        } else {
            $updated = false;
            
            // التحقق من تغيير اسم المستخدم
            if ($new_username !== $currentUser['username']) {
                if (!$canChangeUsername) {
                    $errors[] = "لا يمكنك تغيير اسم المستخدم إلا مرة واحدة كل 3 أشهر. المتبقي: {$daysUntilChange} يوم";
                } else {
                    // التحقق من صحة اسم المستخدم
                    if (strlen($new_username) < 4) {
                        $errors[] = "اسم المستخدم يجب أن يكون 4 أحرف على الأقل";
                    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $new_username)) {
                        $errors[] = "اسم المستخدم يجب أن يحتوي على حروف إنجليزية وأرقام والرموز: _ - . فقط";
                    } else {
                        // التحقق من عدم وجود اسم المستخدم
                        $checkQuery = "SELECT id FROM TI_users WHERE username = ? AND id != ?";
                        $existingUser = $db->select($checkQuery, [$new_username, $user['id']]);
                        
                        if (!empty($existingUser)) {
                            $errors[] = "اسم المستخدم مستخدم بالفعل";
                        } else {
                            // تحديث اسم المستخدم
                            $updateQuery = "UPDATE TI_users SET username = ?, username_last_changed = NOW() WHERE id = ?";
                            if ($db->execute($updateQuery, [$new_username, $user['id']])) {
                                $updated = true;
                                $_SESSION['username'] = $new_username;
                                
                                // تسجيل في Audit Log
                                $logResult = $db->execute(
                                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) 
                                     VALUES (?, 'username_changed', 'TI_users', ?, ?, ?, ?)",
                                    [
                                        $user['id'],
                                        $user['id'],
                                        $currentUser['username'],
                                        $new_username,
                                        $_SERVER['REMOTE_ADDR'] ?? ''
                                    ]
                                );
                            }
                        }
                    }
                }
            }
            
            // التحقق من تغيير البريد الإلكتروني
            $currentEmail = ($currentUser['role'] === 'super_admin') ? getSiteEmail() : $currentUser['email'];
            if ($new_email !== $currentEmail) {
                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "البريد الإلكتروني غير صالح";
                } else {
                    // إذا كان المدير العام، يجب تحديث بريد الموقع
                    if ($currentUser['role'] === 'super_admin') {
                        // تحديث بريد الموقع في ti_settings
                        $updateSiteEmail = "INSERT INTO ti_settings (setting_key, setting_value, setting_type, category) 
                                           VALUES ('site_email', ?, 'string', 'general')
                                           ON DUPLICATE KEY UPDATE setting_value = ?";
                        if ($db->execute($updateSiteEmail, [$new_email, $new_email])) {
                            $updated = true;
                            $_SESSION['email'] = $new_email;
                            
                            // تسجيل في Audit Log
                            $logResult = $db->execute(
                                "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) 
                                 VALUES (?, 'site_email_changed', 'ti_settings', 0, ?, ?, ?)",
                                [
                                    $user['id'],
                                    $currentEmail,
                                    $new_email,
                                    $_SERVER['REMOTE_ADDR'] ?? ''
                                ]
                            );
                        } else {
                            $errors[] = "فشل تحديث البريد الإلكتروني";
                        }
                    } else {
                        // التحقق من عدم وجود البريد
                        $checkQuery = "SELECT id FROM TI_users WHERE email = ? AND id != ?";
                        $existingEmail = $db->select($checkQuery, [$new_email, $user['id']]);
                        
                        if (!empty($existingEmail)) {
                            $errors[] = "البريد الإلكتروني مستخدم بالفعل";
                        } else {
                            // تحديث البريد الإلكتروني
                            $updateQuery = "UPDATE TI_users SET email = ? WHERE id = ?";
                            if ($db->execute($updateQuery, [$new_email, $user['id']])) {
                                $updated = true;
                                $_SESSION['email'] = $new_email;
                                
                                // تسجيل في Audit Log
                                $logResult = $db->execute(
                                    "INSERT INTO TI_audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) 
                                     VALUES (?, 'email_changed', 'TI_users', ?, ?, ?, ?)",
                                    [
                                        $user['id'],
                                        $user['id'],
                                        $currentUser['email'],
                                        $new_email,
                                        $_SERVER['REMOTE_ADDR'] ?? ''
                                    ]
                                );
                            }
                        }
                    }
                }
            }
            
            if (empty($errors) && $updated) {
                $success = "تم تحديث البيانات بنجاح!";
                // إعادة تحميل البيانات
                $userData = $db->select($query, [$user['id']]);
                $currentUser = $userData[0];
            } elseif (empty($errors) && !$updated) {
                $warnings[] = "لم يتم إجراء أي تغييرات";
            }
        }
    }
}

$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('تعديل بيانات الحساب')); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            list-style-position: inside;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1976d2;
        }
        
        .alert ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        
        .alert li {
            margin: 5px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00A6FB;
            box-shadow: 0 0 0 3px rgba(0, 166, 251, 0.1);
        }
        
        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .username-status {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-right: 4px solid #ffc107;
        }
        
        .username-status.allowed {
            border-right-color: #28a745;
            background: #d4edda;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #00A6FB;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0891E6;
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-admin {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .badge-moderator {
            background: #fff3e0;
            color: #e65100;
        }
    </style>
</head>
<body>
    
<?php include 'navbar.php'; ?>

    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        <div class="page-container">
            
            <div class="page-header">
                <h1>✏️ تعديل بيانات الحساب</h1>
                <p>قم بتحديث اسم المستخدم أو البريد الإلكتروني الخاص بك</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ خطأ:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>✅ <?php echo htmlspecialchars($success); ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ <?php echo htmlspecialchars($warnings[0]); ?></strong>
                </div>
            <?php endif; ?>

            <!-- معلومات الحساب الحالية -->
            <div class="content-box">
                <div class="info-box">
                    <h3>📋 البيانات الحالية</h3>
                    
                    <div class="info-item">
                        <span class="info-label">اسم المستخدم الحالي:</span>
                        <span class="info-value" style="direction: ltr; text-align: right;">@<?php echo htmlspecialchars($currentUser['username']); ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">البريد الإلكتروني الحالي:</span>
                        <span class="info-value" style="direction: ltr; text-align: right;"><?php 
                            $displayEmail = ($currentUser['role'] === 'super_admin') ? getSiteEmail() : $currentUser['email'];
                            echo htmlspecialchars($displayEmail); 
                        ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">نوع الحساب:</span>
                        <span class="info-value">
                            <?php
                            if ($currentUser['role'] === 'super_admin' || $currentUser['role'] === 'admin') {
                                echo '<span class="badge badge-admin">👑 مدير</span>';
                            } elseif ($currentUser['role'] === 'moderator') {
                                echo '<span class="badge badge-moderator">🛡️ مشرف</span>';
                            } else {
                                echo '<span class="badge badge-success">👤 مستخدم</span>';
                            }
                            ?>
                        </span>
                    </div>

                    <?php if (!empty($currentUser['username_last_changed'])): ?>
                    <div class="info-item">
                        <span class="info-label">آخر تغيير لاسم المستخدم:</span>
                        <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($currentUser['username_last_changed'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- حالة تغيير اسم المستخدم -->
                <?php if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin' && $currentUser['role'] !== 'moderator'): ?>
                <div class="username-status <?php echo $canChangeUsername ? 'allowed' : ''; ?>">
                    <?php if ($canChangeUsername): ?>
                        <strong>✅ يمكنك تغيير اسم المستخدم الآن</strong>
                    <?php else: ?>
                        <strong>⏳ لا يمكنك تغيير اسم المستخدم حالياً</strong>
                        <p style="margin-top: 8px; font-size: 14px;">
                            يمكنك تغيير اسم المستخدم بعد <strong><?php echo $daysUntilChange; ?> يوم</strong>
                        </p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <strong>👑 صلاحيات خاصة:</strong> بصفتك <?php echo in_array($currentUser['role'], ['admin', 'super_admin']) ? 'مديراً' : 'مشرفاً'; ?>، يمكنك تغيير اسم المستخدم في أي وقت
                </div>
                <?php endif; ?>
            </div>

            <!-- نموذج التعديل -->
            <div class="content-box">
                <h2 style="margin-bottom: 20px; color: var(--dark-color);">تعديل البيانات</h2>
                
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="username">اسم المستخدم الجديد</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($currentUser['username']); ?>"
                               <?php echo !$canChangeUsername ? 'disabled' : ''; ?>
                               pattern="[a-zA-Z0-9._-]+"
                               minlength="4"
                               maxlength="50"
                               placeholder="أدخل اسم المستخدم الجديد">
                        <small>
                            يُسمح فقط بالحروف الإنجليزية (A-Z)، الأرقام (0-9)، والرموز: _ - .
                            <?php if (!$canChangeUsername): ?>
                                <br><strong style="color: #dc3545;">⚠️ لا يمكن تغيير اسم المستخدم حالياً</strong>
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="email">البريد الإلكتروني الجديد</label>
                        <input type="email" id="email" name="email" 
                               value="<?php 
                                   $displayEmail = ($currentUser['role'] === 'super_admin') ? getSiteEmail() : $currentUser['email'];
                                   echo htmlspecialchars($displayEmail); 
                               ?>"
                               required
                               placeholder="أدخل البريد الإلكتروني الجديد">
                        <small>يمكنك تغيير البريد الإلكتروني في أي وقت</small>
                    </div>

                    <div class="form-group">
                        <label for="password">كلمة المرور (للتأكيد) <span style="color: #dc3545;">*</span></label>
                        <input type="password" id="password" name="password" 
                               required
                               placeholder="أدخل كلمة المرور الحالية للتأكيد">
                        <small>يجب إدخال كلمة المرور لتأكيد أي تغييرات</small>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        <a href="./" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>

            <!-- ملاحظات أمنية -->
            <div class="content-box">
                <h3 style="color: var(--dark-color); margin-bottom: 15px;">🔒 ملاحظات أمنية</h3>
                <ul style="line-height: 2; color: var(--text-secondary); padding-right: 20px;">
                    <li>سيتم تسجيل جميع التغييرات في سجل التدقيق</li>
                    <li>عند تغيير البريد الإلكتروني، تأكد من إمكانية الوصول إليه</li>
                    <li>اسم المستخدم يجب أن يكون فريداً ولم يُستخدم من قبل</li>
                    <li>المستخدمون العاديون يمكنهم تغيير اسم المستخدم مرة واحدة كل 3 أشهر</li>
                </ul>
            </div>

        </div>
    </main>

    <script src="JS/main.js"></script>
    <script>
        // التحقق من نموذج اسم المستخدم
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!password) {
                e.preventDefault();
                alert('يجب إدخال كلمة المرور للتأكيد');
                return false;
            }
            
            // التحقق من اسم المستخدم
            const usernameRegex = /^[a-zA-Z0-9._-]+$/;
            if (!usernameRegex.test(username)) {
                e.preventDefault();
                alert('اسم المستخدم يجب أن يحتوي على حروف إنجليزية وأرقام والرموز: _ - . فقط');
                return false;
            }
            
            if (username.length < 4) {
                e.preventDefault();
                alert('اسم المستخدم يجب أن يكون 4 أحرف على الأقل');
                return false;
            }
            
            return true;
        });
        
        // تلميح عند كتابة اسم المستخدم
        document.getElementById('username').addEventListener('input', function() {
            const value = this.value;
            const regex = /^[a-zA-Z0-9._-]*$/;
            
            if (!regex.test(value)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });
    </script>
</body>
</html>