<?php
require_once 'Auth.php';
require_once 'database.php';

$auth = new Auth();

// إعادة توجيه إذا كان المستخدم مسجل دخوله
if ($auth->isLoggedIn()) {
    header('Location: dashboard');
    exit;
}

$errors = [];
$success = '';
$step = 'request'; // request, sent

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "رمز الأمان غير صالح";
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "البريد الإلكتروني غير صالح";
        } else {
            $db = Database::getInstance();
            
            // التحقق من وجود البريد الإلكتروني
            $query = "SELECT id, fullname, email FROM users WHERE email = ? AND is_active = 1";
            $result = $db->select($query, [$email]);
            
            if (!empty($result)) {
                $user = $result[0];
                
                // إنشاء رمز إعادة تعيين
                $reset_token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // حفظ الرمز في قاعدة البيانات
                $update_query = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
                
                if ($db->execute($update_query, [$reset_token, $expiry, $user['id']])) {
                    // في الواقع، يجب إرسال بريد إلكتروني هنا
                    // لكن للتطوير، سنعرض الرابط مباشرة
                    
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;
                    
                    $success = "تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني";
                    $step = 'sent';
                    
                    // في بيئة الإنتاج، استخدم PHPMailer أو مكتبة بريد إلكتروني
                    /*
                    $email_body = "
                    مرحباً {$user['fullname']},
                    
                    تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك.
                    
                    يرجى النقر على الرابط التالي لإعادة تعيين كلمة المرور:
                    $reset_link
                    
                    هذا الرابط صالح لمدة ساعة واحدة فقط.
                    
                    إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذه الرسالة.
                    
                    مع تحيات فريق طباق
                    ";
                    
                    mail($email, 'إعادة تعيين كلمة المرور - طباق', $email_body);
                    */
                }
            } else {
                // لأسباب أمنية، نعرض نفس الرسالة حتى لو لم يكن البريد موجوداً
                $success = "إذا كان البريد الإلكتروني موجوداً، سيتم إرسال رابط إعادة التعيين إليه";
                $step = 'sent';
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
    <title><?php echo htmlspecialchars(getPageTitle('استعادة كلمة المرور')); ?></title>
    
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
        
        .alert-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1976d2;
        }
        
        .icon-large {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #00A6FB;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    
    <!-- الشريط العلوي البسيط -->
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

    <!-- محتوى استعادة كلمة المرور -->
    <div class="auth-container">
        <div class="auth-box">
            
            <?php if ($step === 'request'): ?>
                <div class="auth-box-header">
                    <div class="icon-large">🔐</div>
                    <h1>استعادة كلمة المرور</h1>
                    <p>أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة تعيين كلمة المرور</p>
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

                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="أدخل بريدك الإلكتروني"
                               autocomplete="email">
                    </div>

                    <button type="submit" class="btn-submit">إرسال رابط إعادة التعيين</button>

                    <div class="back-to-login">
                        <a href="login">← العودة لتسجيل الدخول</a>
                    </div>
                </form>
            
            <?php else: ?>
                <!-- رسالة نجاح الإرسال -->
                <div class="icon-large">✅</div>
                <div class="auth-box-header">
                    <h1>تم إرسال الرابط!</h1>
                    <p>تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني</p>
                </div>

                <div class="alert alert-info">
                    <strong>📧 يرجى التحقق من بريدك الإلكتروني</strong>
                    <p>إذا لم تجد الرسالة، تحقق من مجلد البريد المزعج (Spam)</p>
                    <p style="margin-top: 10px; font-size: 13px;">الرابط صالح لمدة ساعة واحدة فقط</p>
                </div>

                <div class="back-to-login">
                    <a href="login">← العودة لتسجيل الدخول</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="JS/main.js"></script>
</body>
</html>