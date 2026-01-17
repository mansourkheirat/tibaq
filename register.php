<?php
require_once 'maintenance-check.php';
require_once 'Auth.php';

$auth = new Auth();

// التحقق من وضع الصيانة
$maintenanceMode = getMaintenanceMode();
$isMaintenanceClosed = ($maintenanceMode === 'closed');
$isMaintenanceLocked = ($maintenanceMode === 'locked');

// إذا كان الموقع مغلقاً أو مقفلاً، أعد التوجيه لصفحة الدخول
if ($isMaintenanceClosed) {
    header('Location: login?maintenance=closed');
    exit;
}

// إذا كان الموقع مقفلاً، منع التسجيل
if ($isMaintenanceLocked) {
    header('Location: login?maintenance=locked');
    exit;
}

// التحقق من أن المستخدم غير مسجل دخوله بالفعل
if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit;
}

$errors = [];
$success = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);
        
        if (!$terms) {
            $errors[] = "يجب الموافقة على شروط الاستخدام";
        } else {
            $result = $auth->register($fullname, $username, $email, $password, $confirm_password);
            
            if ($result['success']) {
                $success = $result['message'];
                // يمكنك تسجيل الدخول تلقائياً أو إعادة التوجيه لصفحة الدخول
                // header('Location: login.php?registered=1');
                // exit;
            } else {
                $errors = $result['errors'];
            }
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
    <title><?php echo htmlspecialchars(getPageTitle('التسجيل')); ?></title>
    
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
        
        .alert ul {
            margin: 10px 0 0 0;
            padding-right: 20px;
        }
        
        .alert ul li {
            margin: 5px 0;
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
    
    <!-- الشريط العلوي البسيط -->
    <?php if (!$isMaintenanceClosed): ?>
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
    <?php endif; ?>

    <!-- محتوى التسجيل -->
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-box-header">
                <h1>إنشاء حساب جديد</h1>
                <p>انضم إلينا وابدأ رحلتك مع طباق</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ هناك أخطاء في النموذج:</strong>
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
                    <p>يمكنك الآن <a href="login">تسجيل الدخول</a></p>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="fullname">الاسم واللقب *</label>
                        <input type="text" id="fullname" name="fullname" required 
                               value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>"
                               placeholder="أدخل اسمك الكامل" minlength="3">
                    </div>

                    <div class="form-group">
                        <label for="username">اسم المستخدم *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="اختر اسم مستخدم" minlength="4" pattern="[a-zA-Z0-9_]+">
                        <small style="color: #666; font-size: 12px;">أحرف وأرقام فقط</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">البريد الإلكتروني *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="أدخل بريدك الإلكتروني">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">كلمة المرور *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="أدخل كلمة المرور" minlength="8">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small id="strengthText" style="color: #666; font-size: 12px;"></small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">تأكيد كلمة المرور *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="أعد إدخال كلمة المرور" minlength="8">
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span>لقد قرأت ووافقت على <a href="terms.php" target="_blank">شروط الاستخدام</a></span>
                    </label>
                </div>

                <button type="submit" class="btn-submit">إنشاء الحساب</button>

                <div class="auth-footer">
                    <p>لديك حساب بالفعل؟ <a href="login">سجل دخولك</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="JS/main.js"></script>
    <script>
        // التحقق من قوة كلمة المرور
        const passwordInput = document.getElementById('password');
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
        const form = document.getElementById('registerForm');
        
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