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
                        // المستخدم ليس من الرتب المسموحة ولا من الأعضاء المصرح لهم
                        $auth->logout();
                        $errors[] = 'ليس لديك صلاحية للدخول في وضع الصيانة.';
                    } else {
                        // تسجيل محاولة تسجيل دخول ناجحة
                        require_once 'database.php';
                        $db = Database::getInstance();
                        $db->execute(
                            "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 1)",
                            [$username_or_email, $_SERVER['REMOTE_ADDR']]
                        );
                        
                        // إعادة التوجيه إلى الصفحة الرئيسية
                        header('Location: ./');
                        exit;
                    }
                } else {
                    // تسجيل محاولة تسجيل دخول ناجحة
                    require_once 'database.php';
                    $db = Database::getInstance();
                    $db->execute(
                        "INSERT INTO login_attempts (username_or_email, ip_address, success) VALUES (?, ?, 1)",
                        [$username_or_email, $_SERVER['REMOTE_ADDR']]
                    );
                    
                    // إعادة التوجيه إلى الصفحة الرئيسية
                    header('Location: ./');
                    exit;
                }
            } else {
                // تسجيل محاولة تسجيل دخول فاشلة
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
        
        /* شعار الموقع في وضع الصيانة - إزالة جميع الخلفيات */
        .maintenance-logo-container {
            text-align: center;
            padding: 30px 20px;
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
            border: none !important;
            margin: 0 !important;
            border-bottom: none !important;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            /* إزالة أي تأثير من simple-header */
            position: static !important;
        }
        
        /* التأكد من عدم وجود خلفية من أي CSS آخر */
        .maintenance-logo-container *,
        .maintenance-logo-container *::before,
        .maintenance-logo-container *::after {
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
        }
        
        /* منع أي CSS من simple-header من التأثير على الشعار */
        .maintenance-logo-container:not(.simple-header) {
            background: transparent !important;
        }
        
        .maintenance-logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            pointer-events: none;
            user-select: none;
        }
        
        .maintenance-logo span:first-child {
            font-size: 32px;
        }
    </style>
</head>
<body>
    
    <?php if ($maintenanceMode === 'closed'): ?>
    <!-- شعار الموقع في الوسط (عند وضع الصيانة مغلق) -->
    <div class="maintenance-logo-container">
        <div class="maintenance-logo">
            <span>📊</span>
            <span><?php echo htmlspecialchars(getSiteName()); ?></span>
        </div>
    </div>
    <?php else: ?>
    <!-- الشريط العلوي البسيط -->
    <div class="simple-header">
        <div class="simple-header-content">
            <a href="./" class="logo">
                <span>📊</span>
                <span>طباق وإسناد</span>
            </a>
            
            <div class="header-buttons">
                <a href="./" class="btn-home">← الرئيسية</a>
                <?php if ($maintenanceMode !== 'locked'): ?>
                <a href="register" class="btn-switch-register">التسجيل</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- محتوى تسجيل الدخول -->
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-box-header">
                <h1>مرحباً بعودتك</h1>
                <p>سجل دخولك للوصول إلى حسابك</p>
            </div>

            <?php if ($maintenanceMessage): ?>
                <div class="alert alert-warning" style="background: #fff3cd; border: 2px solid #ffc107; color: #856404; margin-bottom: 20px;">
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
            
            <!-- رسالة الخطأ عبر Ajax (لوضع الصيانة) -->
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

    <script src="JS/main.js"></script>
    <script>
        // إظهار/إخفاء كلمة المرور
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
        
        // معالجة تسجيل الدخول عبر Ajax
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const ajaxError = document.getElementById('ajaxError');
            const ajaxErrorList = document.getElementById('ajaxErrorList');
            const maintenanceMode = '<?php echo $maintenanceMode; ?>';
            
            // التركيز على حقل اسم المستخدم عند تحميل الصفحة
            document.getElementById('username').focus();
            
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // إخفاء رسالة الخطأ السابقة
                    ajaxError.style.display = 'none';
                    ajaxErrorList.innerHTML = '';
                    
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value;
                    const remember = document.getElementById('remember').checked;
                    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                    const submitBtn = document.querySelector('.btn-submit');
                    const originalBtnText = submitBtn.textContent;
                    
                    // تعطيل الزر أثناء المعالجة
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'جاري تسجيل الدخول...';
                    
                    // إرسال البيانات عبر Ajax
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
                            // تسجيل الدخول ناجح - إخفاء رسالة الخطأ أولاً
                            ajaxError.style.display = 'none';
                            ajaxErrorList.innerHTML = '';
                            
                            // إعادة التوجيه - استخدام setTimeout لضمان معالجة الاستجابة
                            const redirectUrl = data.redirect || './';
                            setTimeout(function() {
                                // محاولة استخدام replace أولاً، ثم href كبديل
                                try {
                                    window.location.replace(redirectUrl);
                                } catch(e) {
                                    window.location.href = redirectUrl;
                                }
                                // إذا لم تعمل أي منهما، استخدم reload
                                setTimeout(function() {
                                    if (window.location.pathname === '/login' || window.location.pathname.includes('login')) {
                                        window.location.reload(true);
                                    }
                                }, 100);
                            }, 100);
                        } else {
                            // فشل تسجيل الدخول
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                            
                            // إذا كان خطأ CSRF، قم بتحديث الصفحة
                            if (data.csrf_error) {
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            }
                            
                            // عرض رسالة الخطأ
                            if (data.errors && data.errors.length > 0) {
                                ajaxErrorList.innerHTML = '';
                                data.errors.forEach(error => {
                                    const li = document.createElement('li');
                                    li.textContent = error;
                                    ajaxErrorList.appendChild(li);
                                });
                                ajaxError.style.display = 'block';
                                
                                // تمرير للأسفل لرؤية الرسالة
                                ajaxError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            } else if (data.message) {
                                // إذا لم تكن هناك errors array، استخدم message
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