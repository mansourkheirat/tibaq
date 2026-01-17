<?php
require_once 'Auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit;
}

$errors = [];
$success = '';
$token = $_GET['token'] ?? '';

// التحقق من صلاحية الرمز
$valid_token = false;
if (!empty($token)) {
    $db = Database::getInstance();
    $query = "SELECT id, username FROM TI_users WHERE reset_token = ? AND reset_token_expiry > NOW()";
    $result = $db->select($query, [$token]);
    
    if (!empty($result)) {
        $valid_token = true;
        $user = $result[0];
    }
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $result = $auth->resetPassword($token, $new_password, $confirm_password);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors = $result['errors'];
        }
    }
}

$csrf_token = $auth->generateCsrfToken();
require_once 'site-functions.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getPageTitle('إعادة تعيين كلمة المرور')); ?></title>
    
    <link rel="stylesheet" href="Styles/main.css">
    <link rel="stylesheet" href="Styles/auth.css">
    <link rel="stylesheet" href="Styles/responsive.css">
    
    <style>
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
        
        .icon-large {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
        
        .strength-weak { width: 33%; background: #f44336; }
        .strength-medium { width: 66%; background: #ff9800; }
        .strength-strong { width: 100%; background: #4caf50; }
    </style>
</head>
<body>
    
    <div class="simple-header">
        <div class="simple-header-content">
            <a href="./" class="logo">
                <span>📊</span>
                <span>طباق وإسناد</span>
            </a>
            
            <div class="header-buttons">
                <a href="./" class="btn-home">← الرئيسية</a>
                <a href="login" class="btn-switch-login">الدخول</a>
            </div>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-box">
            
            <?php if ($success): ?>
                <!-- نجاح إعادة التعيين -->
                <div class="icon-large">✅</div>
                <div class="auth-box-header">
                    <h1>تم بنجاح!</h1>
                    <p>تم إعادة تعيين كلمة المرور بنجاح</p>
                </div>

                <div class="alert alert-success">
                    <strong><?php echo htmlspecialchars($success); ?></strong>
                    <p style="margin-top: 10px;">يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة</p>
                </div>

                <a href="login" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">
                    تسجيل الدخول
                </a>
                
            <?php elseif (!$valid_token): ?>
                <!-- رمز غير صالح -->
                <div class="icon-large">❌</div>
                <div class="auth-box-header">
                    <h1>رابط غير صالح</h1>
                    <p>الرابط غير صالح أو منتهي الصلاحية</p>
                </div>

                <div class="alert alert-error">
                    <strong>⚠️ خطأ</strong>
                    <p>رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية</p>
                    <p style="margin-top: 10px;">يرجى طلب رابط جديد</p>
                </div>

                <a href="forgot-password" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">
                    طلب رابط جديد
                </a>
                
            <?php else: ?>
                <!-- نموذج إعادة التعيين -->
                <div class="auth-box-header">
                    <div class="icon-large">🔐</div>
                    <h1>إعادة تعيين كلمة المرور</h1>
                    <p>أدخل كلمة المرور الجديدة لحسابك</p>
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

                <form class="auth-form" method="POST" action="" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
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
                        <label for="confirm_password">تأكيد كلمة المرور</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="أعد إدخال كلمة المرور" minlength="8">
                    </div>

                    <button type="submit" class="btn-submit">تعيين كلمة المرور</button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script src="JS/main.js"></script>
    <script>
        // التحقق من قوة كلمة المرور
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        if (passwordInput) {
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
        }
        
        // التحقق من تطابق كلمات المرور
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.getElementById('resetForm');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('كلمتا المرور غير متطابقتين');
                    confirmPassword.focus();
                }
            });
        }
    </script>
</body>
</html>