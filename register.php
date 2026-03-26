<?php
require_once 'maintenance-check.php';
require_once 'Auth.php';

$auth = new Auth();

$maintenanceMode = getMaintenanceMode();
$isMaintenanceClosed = ($maintenanceMode === 'closed');
$isMaintenanceLocked = ($maintenanceMode === 'locked');

if ($isMaintenanceClosed) {
    header('Location: login?maintenance=closed');
    exit;
}

if ($isMaintenanceLocked) {
    header('Location: login?maintenance=locked');
    exit;
}

if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            } else {
                $errors = $result['errors'];
            }
        }
    }
}

$csrf_token = $auth->generateCsrfToken();
require_once 'site-functions.php';
$siteName = getSiteName();
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

        /* ===== الأزرار العلوية ===== */
        .header-buttons-container {
            position: fixed;
            top: 20px;
            left: 30px;
            display: flex;
            gap: 12px;
            z-index: 100;
        }

        .btn-home, .btn-login {
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 2px solid transparent;
        }

        .btn-home {
            color: #374151;
            background: transparent;
            border: 2px solid #E5E7EB;
        }

        .btn-home:hover {
            background: #F3F4F6;
            border-color: #D1D5DB;
            color: #0891E6;
        }

        .btn-login {
            background: #0891E6;
            color: #FFFFFF;
            border: 2px solid #0891E6;
        }

        .btn-login:hover {
            background: #0284C7;
            border-color: #0284C7;
        }

        /* ===== الشعار الكبير في المنتصف ===== */
        .auth-navbar-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 700;
            color: #000000;
            transition: all 0.3s ease;
            justify-content: center;
            margin-bottom: 40px;
        }

        .auth-navbar-logo.auth-logo-large {
            gap: 20px;
            font-size: 28px;
            margin-bottom: 50px;
        }

        .auth-navbar-logo:hover {
            color: #0891E6;
        }

        .auth-navbar-logo:hover .auth-logo-icon {
            animation: logoRotate 0.6s ease-in-out;
        }

        .auth-logo-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .auth-logo-icon.auth-logo-large-icon {
            width: 70px;
            height: 70px;
        }

        .auth-logo-text {
            font-size: inherit;
            transition: color 0.3s ease;
        }

        @keyframes logoRotate {
            0% {
                transform: rotateY(0deg) scale(1);
            }
            50% {
                transform: rotateY(180deg) scale(1.1);
            }
            100% {
                transform: rotateY(360deg) scale(1);
            }
        }

        /* ===== التوافق مع الشاشات الصغيرة ===== */
        @media (max-width: 768px) {
            .header-buttons-container {
                left: 15px;
                top: 15px;
                gap: 8px;
            }

            .btn-home, .btn-login {
                padding: 8px 14px;
                font-size: 13px;
            }

            .auth-navbar-logo.auth-logo-large {
                gap: 12px;
                font-size: 22px;
                margin-bottom: 35px;
            }

            .auth-logo-icon.auth-logo-large-icon {
                width: 55px;
                height: 55px;
            }
        }

        @media (max-width: 480px) {
            .header-buttons-container {
                left: 10px;
                top: 10px;
                gap: 6px;
            }

            .btn-home, .btn-login {
                padding: 7px 12px;
                font-size: 12px;
            }

            .auth-navbar-logo.auth-logo-large {
                gap: 10px;
                font-size: 20px;
                margin-bottom: 30px;
            }

            .auth-logo-icon.auth-logo-large-icon {
                width: 48px;
                height: 48px;
            }
        }
    </style>
</head>
<body>
    
    <!-- ===== الأزرار العلوية فقط ===== -->
    <div class="header-buttons-container">
        <a href="./" class="btn-home">← الرئيسية</a>
        <a href="login" class="btn-login">الدخول</a>
    </div>

    <!-- محتوى التسجيل -->
    <div class="auth-container">
        <div class="auth-box">
            <!-- الشعار الكبير في المنتصف -->
            <a href="./" class="auth-navbar-logo auth-logo-large">
                <svg class="auth-logo-icon auth-logo-large-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#0891E6;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#0284C7;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    
                    <path d="M 15 25 Q 50 20 85 25 L 85 75 Q 50 80 15 75 Z" fill="#F3F4F6" stroke="#0891E6" stroke-width="2"/>
                    <path d="M 15 28 L 50 26 L 50 72 L 15 75 Z" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="1.5"/>
                    <path d="M 50 26 L 85 28 L 85 75 L 50 72 Z" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="1.5"/>
                    
                    <line x1="22" y1="36" x2="42" y2="36" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="22" y1="44" x2="42" y2="44" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="22" y1="52" x2="42" y2="52" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="22" y1="60" x2="36" y2="60" stroke="#0891E6" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    
                    <line x1="58" y1="36" x2="78" y2="36" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="58" y1="44" x2="78" y2="44" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="58" y1="52" x2="78" y2="52" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    <line x1="58" y1="60" x2="72" y2="60" stroke="#0284C7" stroke-width="1.5" stroke-linecap="round" opacity="0.8"/>
                    
                    <circle cx="50" cy="50" r="6" fill="url(#logoGradient)"/>
                    <circle cx="50" cy="50" r="4" fill="none" stroke="#FFFFFF" stroke-width="1.5"/>
                    <line x1="46" y1="50" x2="54" y2="50" stroke="#FFFFFF" stroke-width="1"/>
                    <line x1="50" y1="46" x2="50" y2="54" stroke="#FFFFFF" stroke-width="1"/>
                </svg>
                <span class="auth-logo-text"><?php echo htmlspecialchars($siteName); ?></span>
            </a>

            <div class="auth-box-header">
                <h1>إنشاء حساب جديد</h1>
                <p>انضم إلينا وابدأ رحلتك مع: <span><?php echo htmlspecialchars($siteName); ?></span>.</p>
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