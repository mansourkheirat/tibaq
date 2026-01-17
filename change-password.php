<?php
require_once 'maintenance-check.php';
checkMaintenanceMode();

require_once 'Auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $result = $auth->changePassword($user['id'], $old_password, $new_password, $confirm_password);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors = $result['errors'];
        }
    }
}

$csrf_token = $auth->generateCsrfToken();
$security_stats = $auth->getSecurityStats($user['id']);
require_once 'site-functions.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('تغيير كلمة المرور')); ?></title>
    
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
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00A6FB;
            box-shadow: 0 0 0 3px rgba(0, 166, 251, 0.1);
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
        
        .strength-weak { width: 33%; background: #f44336; }
        .strength-medium { width: 66%; background: #ff9800; }
        .strength-strong { width: 100%; background: #4caf50; }
        
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 166, 251, 0.3);
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
        }
        
        .security-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .security-info h3 {
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
    </style>
</head>
<body>
    
<?php include 'navbar.php'; ?>

    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        <div class="page-container">
            
            <div class="page-header">
                <h1>🔐 تغيير كلمة المرور</h1>
                <p>قم بتحديث كلمة المرور الخاصة بك بشكل دوري للحفاظ على أمان حسابك</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ خطأ:</strong>
                    <ul style="margin: 10px 0 0 0; padding-right: 20px;">
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

            <div class="content-box">
                <form method="POST" action="" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="old_password">كلمة المرور الحالية</label>
                        <input type="password" id="old_password" name="old_password" required 
                               placeholder="أدخل كلمة المرور الحالية">
                    </div>

                    <div class="form-group">
                        <label for="new_password">كلمة المرور الجديدة</label>
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="أدخل كلمة المرور الجديدة" minlength="8">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small id="strengthText" style="color: #666; font-size: 12px;"></small>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            يجب أن تحتوي على 8 أحرف على الأقل، أحرف كبيرة وصغيرة، وأرقام
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="أعد إدخال كلمة المرور الجديدة" minlength="8">
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">حفظ كلمة المرور الجديدة</button>
                        <a href="./" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>

            <!-- معلومات الأمان -->
            <div class="content-box">
                <div class="security-info">
                    <h3>📊 معلومات الأمان</h3>
                    
                    <div class="info-item">
                        <span class="info-label">الجلسات النشطة:</span>
                        <span class="info-value"><?php echo $security_stats['active_sessions']; ?> جلسة</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">محاولات تسجيل فاشلة (24 ساعة):</span>
                        <span class="info-value"><?php echo $security_stats['failed_logins_24h']; ?> محاولة</span>
                    </div>

                    <?php if ($security_stats['last_failed_login']): ?>
                    <div class="info-item">
                        <span class="info-label">آخر محاولة فاشلة:</span>
                        <span class="info-value">
                            <?php 
                            echo date('Y-m-d H:i', strtotime($security_stats['last_failed_login']['attempted_at']));
                            echo ' من ' . htmlspecialchars($security_stats['last_failed_login']['ip_address']);
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script src="JS/main.js"></script>
    <script>
        // التحقق من قوة كلمة المرور
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0 || strength === 1) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'كلمة مرور ضعيفة';
                strengthText.style.color = '#f44336';
            } else if (strength === 2 || strength === 3) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'كلمة مرور متوسطة';
                strengthText.style.color = '#ff9800';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'كلمة مرور قوية';
                strengthText.style.color = '#4caf50';
            }
        });
        
        // التحقق من تطابق كلمات المرور
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.getElementById('changePasswordForm');
        
        form.addEventListener('submit', function(e) {
            if (passwordInput.value !== confirmPassword.value) {
                e.preventDefault();
                alert('كلمتا المرور غير متطابقتين');
                confirmPassword.focus();
            }
        });
    </script>
</body>
</html>