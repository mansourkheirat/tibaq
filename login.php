<?php
require_once 'Auth.php';

$auth = new Auth();

// التحقق من أن المستخدم غير مسجل دخوله بالفعل
if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit;
}

$errors = [];
$success = '';
$maintenanceMessage = '';

// التحقق من وضع الصيانة
require_once 'maintenance-check.php';
$maintenanceMode = getMaintenanceMode();

// إذا كان الموقع مغلق، عرض رسالة
if (isset($_GET['maintenance']) && $_GET['maintenance'] === 'closed') {
    $maintenanceMessage = 'الموقع مغلق حاليًا للصيانة، فلا يمكن تسجيل الدخول إلا للرتب المخول لها ذلك.';
    
    if (isset($_GET['unauthorized']) && $_GET['unauthorized'] == '1') {
        $errors[] = 'ليس لديك صلاحية للدخول في وضع الصيانة.';
    }
}

// إذا كان الموقع مقفل، عرض رسالة
if (isset($_GET['maintenance']) && $_GET['maintenance'] === 'locked') {
    $maintenanceMessage = 'التسجيل مغلق حاليًا. يمكنك تسجيل الدخول فقط.';
}

// رسالة نجاح التسجيل
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول';
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $username_or_email = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($username_or_email) || empty($password)) {
            $errors[] = "جميع الحقول مطلوبة";
        } else {
            $result = $auth->login($username_or_email, $password, $remember);
            
            if ($result['success']) {
                // التحقق من وضع الصيانة بعد تسجيل الدخول
                if ($maintenanceMode === 'closed') {
                    $currentUser = $auth->getCurrentUser();
                    if (!canAccessInMaintenance($currentUser['role'], $currentUser['id'])) {
                        $auth->logout();
                        $errors[] = 'ليس لديك صلاحية للدخول في وضع الصيانة.';
                    } else {
                        require_once 'database.php';
                        $db = Database::getInstance();
                        $db->execute(
                            "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 1)",
                            [$username_or_email, $_SERVER['REMOTE_ADDR']]
                        );
                        
                        header('Location: ./');
                        exit;
                    }
                } else {
                    require_once 'database.php';
                    $db = Database::getInstance();
                    $db->execute(
                        "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 1)",
                        [$username_or_email, $_SERVER['REMOTE_ADDR']]
                    );
                    
                    header('Location: ./');
                    exit;
                }
            } else {
                $db = Database::getInstance();
                $db->execute(
                    "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 0)",
                    [$username_or_email, $_SERVER['REMOTE_ADDR']]
                );
                
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
    <title><?php echo htmlspecialchars(getPageTitle('تسجيل الدخول')); ?></title>
    
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

        .alert-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        
        .alert ul {
            margin: 10px 0 0 0;
            padding-right: 20px;
        }
        
        .alert ul li {
            margin: 5px 0;
        }
        
        .show-password {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            user-select: none;
        }
        
        .form-group {
            position: relative;
        }

        /* ===== الأزرار العلوية ===== */
        .header-buttons-container {
            position: fixed;
            top: 20px;
            left: 30px;
            display: flex;
            gap: 12px;
            z-index: 100;
        }

        .btn-home, .btn-register {
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

        .btn-register {
            background: #10B981;
            color: #FFFFFF;
            border: 2px solid #10B981;
        }

        .btn-register:hover {
            background: #0EA872;
            border-color: #0EA872;
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

        /* شعار الموقع في وضع الصيانة */
        .maintenance-mode-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* ===== التوافق مع الشاشات الصغيرة ===== */
        @media (max-width: 768px) {
            .header-buttons-container {
                left: 15px;
                top: 15px;
                gap: 8px;
            }

            .btn-home, .btn-register {
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

            .btn-home, .btn-register {
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
    
    <?php if ($maintenanceMode === 'closed'): ?>
    <!-- وضع الصيانة المغلق -->
    <div class="maintenance-mode-container">
        <!-- الأزرار العلوية -->
        <div class="header-buttons-container">
            <a href="./" class="btn-home">← الرئيسية</a>
        </div>

        <!-- محتوى تسجيل الدخول -->
        <div class="auth-container">
            <div class="auth-box">
                <!-- الشعار الكبير -->
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
                    <h1>مرحباً بعودتك</h1>
                    <p>سجل دخولك للوصول إلى حسابك</p>
                </div>

                <?php if ($maintenanceMessage): ?>
                    <div class="alert alert-warning">
                        <strong>🔒 الموقع مغلق للصيانة:</strong>
                        <p style="margin: 10px 0 0 0;"><?php echo htmlspecialchars($maintenanceMessage); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>⚠️ خطأ في تسجيل الدخول:</strong>
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

                <form class="auth-form" method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="username">البريد الإلكتروني أو اسم المستخدم</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="أدخل بريدك الإلكتروني أو اسم المستخدم"
                               autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="أدخل كلمة المرور"
                               autocomplete="current-password">
                        <span class="show-password" onclick="togglePassword()" title="إظهار/إخفاء كلمة المرور">👁️</span>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember">
                            <span>تذكرني لمدة 30 يوم</span>
                        </label>
                        <a href="forgot-password" class="forgot-link">هل نسيت كلمة المرور؟</a>
                    </div>

                    <button type="submit" class="btn-submit">تسجيل الدخول</button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- الوضع العادي -->
    <!-- ===== الأزرار العلوية ===== -->
    <div class="header-buttons-container">
        <a href="./" class="btn-home">← الرئيسية</a>
        <?php if ($maintenanceMode !== 'locked'): ?>
        <a href="register" class="btn-register">التسجيل</a>
        <?php endif; ?>
    </div>

    <!-- محتوى تسجيل الدخول -->
    <div class="auth-container">
        <div class="auth-box">
            <!-- الشعار الكبير في المنتصف -->
            <a href="./" class="auth-navbar-logo auth-logo-large">
                <svg class="auth-logo-icon auth-logo-large-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="logoGradient2" x1="0%" y1="0%" x2="100%" y2="100%">
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
                    
                    <circle cx="50" cy="50" r="6" fill="url(#logoGradient2)"/>
                    <circle cx="50" cy="50" r="4" fill="none" stroke="#FFFFFF" stroke-width="1.5"/>
                    <line x1="46" y1="50" x2="54" y2="50" stroke="#FFFFFF" stroke-width="1"/>
                    <line x1="50" y1="46" x2="50" y2="54" stroke="#FFFFFF" stroke-width="1"/>
                </svg>
                <span class="auth-logo-text"><?php echo htmlspecialchars($siteName); ?></span>
            </a>

            <div class="auth-box-header">
                <h1>مرحباً بعودتك</h1>
                <p>سجل دخولك للوصول إلى حسابك</p>
            </div>

            <?php if ($maintenanceMessage): ?>
                <div class="alert alert-warning">
                    <strong>🔒 الموقع مغلق للصيانة:</strong>
                    <p style="margin: 10px 0 0 0;"><?php echo htmlspecialchars($maintenanceMessage); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors) && !isset($_GET['maintenance'])): ?>
                <div class="alert alert-error">
                    <strong>⚠️ خطأ في تسجيل الدخول:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div id="ajaxError" class="alert alert-error" style="display: none;">
                <strong>⚠️ خطأ في تسجيل الدخول:</strong>
                <ul id="ajaxErrorList"></ul>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>✅ <?php echo htmlspecialchars($success); ?></strong>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label for="username">البريد الإلكتروني أو اسم المستخدم</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="أدخل بريدك الإلكتروني أو اسم المستخدم"
                           autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="أدخل كلمة المرور"
                           autocomplete="current-password">
                    <span class="show-password" onclick="togglePassword()" title="إظهار/إخفاء كلمة المرور">👁️</span>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span>تذكرني لمدة 30 يوم</span>
                    </label>
                    <a href="forgot-password" class="forgot-link">هل نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="btn-submit">تسجيل الدخول</button>

                <?php if ($maintenanceMode !== 'closed' && $maintenanceMode !== 'locked'): ?>
                <div class="auth-footer">
                    <p>ليس لديك حساب؟ <a href="register">سجل الآن</a></p>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="JS/main.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const showIcon = document.querySelector('.show-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                showIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                showIcon.textContent = '👁️';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const ajaxError = document.getElementById('ajaxError');
            const ajaxErrorList = document.getElementById('ajaxErrorList');
            
            document.getElementById('username').focus();
            
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    ajaxError.style.display = 'none';
                    ajaxErrorList.innerHTML = '';
                    
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;
                    const remember = document.getElementById('remember').checked;
                    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                    const submitBtn = document.querySelector('.btn-submit');
                    const originalBtnText = submitBtn.textContent;
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'جاري تسجيل الدخول...';
                    
                    fetch('login_ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            username: username,
                            password: password,
                            remember: remember,
                            csrf_token: csrfToken
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('خطأ في الاستجابة من الخادم');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            ajaxError.style.display = 'none';
                            ajaxErrorList.innerHTML = '';
                            
                            const redirectUrl = data.redirect || './';
                            setTimeout(function() {
                                try {
                                    window.location.replace(redirectUrl);
                                } catch(e) {
                                    window.location.href = redirectUrl;
                                }
                                setTimeout(function() {
                                    if (window.location.pathname === '/login' || window.location.pathname.includes('login')) {
                                        window.location.reload(true);
                                    }
                                }, 100);
                            }, 100);
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                            
                            if (data.csrf_error) {
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            }
                            
                            if (data.errors && data.errors.length > 0) {
                                ajaxErrorList.innerHTML = '';
                                data.errors.forEach(error => {
                                    const li = document.createElement('li');
                                    li.textContent = error;
                                    ajaxErrorList.appendChild(li);
                                });
                                ajaxError.style.display = 'block';
                                ajaxError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            } else if (data.message) {
                                ajaxErrorList.innerHTML = '<li>' + data.message + '</li>';
                                ajaxError.style.display = 'block';
                                ajaxError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('خطأ في الاتصال:', error);
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                        
                        ajaxErrorList.innerHTML = '<li>حدث خطأ في الاتصال بالخادم. يرجى المحاولة مرة أخرى.</li>';
                        ajaxError.style.display = 'block';
                        ajaxError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                });
            }
        });
    </script>
</body>
</html>