<?php
/**
 * صفحة إعدادات الموقع
 * يتم تحميلها ديناميكياً في لوحة التحكم
 */

require_once '../Auth.php';
require_once '../database.php';

$auth = new Auth();
$db = Database::getInstance();

// التحقق من تسجيل الدخول
if (!$auth->isLoggedIn()) {
    echo '<div class="error-message">يجب تسجيل الدخول أولاً</div>';
    exit;
}

$currentUser = $auth->getCurrentUser();

// السماح فقط للمدير العام والمدير بالدخول
$allowedRoles = ['super_admin', 'admin'];

if (!in_array($currentUser['role'], $allowedRoles)) {
    echo '<div class="error-message">غير مصرح لك بالوصول إلى هذه الصفحة</div>';
    exit;
}

// استخراج الإعدادات من قاعدة البيانات
$siteName = 'طباق وإسناد';
$siteDescription = '';
$siteEmail = 'info@tibaq.com';
$maintenanceMode = 'open'; // القيمة الافتراضية: مفتوح

try {
    $settings = $db->select(
        "SELECT setting_key, setting_value FROM ti_settings WHERE setting_key IN ('site_name', 'site_description', 'site_email', 'maintenance_mode', 'maintenance_allowed_users')"
    );
    
    foreach ($settings as $setting) {
        if (isset($setting['setting_key']) && isset($setting['setting_value'])) {
            switch ($setting['setting_key']) {
                case 'site_name':
                    $siteName = $setting['setting_value'];
                    break;
                case 'site_description':
                    $siteDescription = $setting['setting_value'];
                    break;
                case 'site_email':
                    $siteEmail = $setting['setting_value'];
                    break;
                case 'maintenance_mode':
                    // تحويل القيم القديمة (0, 1, 2) إلى القيم الجديدة
                    $value = $setting['setting_value'];
                    if ($value === '0' || $value === 0 || $value === 'open') {
                        $maintenanceMode = 'open';
                    } elseif ($value === '1' || $value === 1 || $value === 'locked') {
                        $maintenanceMode = 'locked';
                    } elseif ($value === '2' || $value === 2 || $value === 'closed') {
                        $maintenanceMode = 'closed';
                    } else {
                        $maintenanceMode = $value;
                    }
                    break;
            }
        }
    }
} catch (Exception $e) {
    error_log("خطأ في استخراج الإعدادات: " . $e->getMessage());
}

// التحقق من رتبة المستخدم
$isSuperAdmin = ($currentUser['role'] === 'super_admin');

// استخراج الأعضاء المصرح لهم في وضع الصيانة
$maintenanceAllowedUsers = [];
try {
    $allowedUsersSetting = $db->select(
        "SELECT setting_value FROM ti_settings WHERE setting_key = 'maintenance_allowed_users' LIMIT 1"
    );
    if (!empty($allowedUsersSetting) && isset($allowedUsersSetting[0]['setting_value'])) {
        $allowedUsersJson = $allowedUsersSetting[0]['setting_value'];
        $maintenanceAllowedUsers = json_decode($allowedUsersJson, true);
        if (!is_array($maintenanceAllowedUsers)) {
            $maintenanceAllowedUsers = [];
        }
    }
} catch (Exception $e) {
    error_log("خطأ في استخراج الأعضاء المصرح لهم: " . $e->getMessage());
    $maintenanceAllowedUsers = [];
}
?>

<div class="cp-page-content">
    <div class="page-header">
        <h1>⚙️ إعدادات الموقع</h1>
        <p>إدارة إعدادات الموقع العامة</p>
    </div>

    <div class="settings-container">
        <div class="settings-section">
            <h2>الإعدادات العامة</h2>
            <div class="settings-form" id="generalSettingsForm">
                <div class="form-group form-row">
                    <label for="siteName">اسم الموقع:</label>
                    <input type="text" id="siteName" class="form-control" value="<?php echo htmlspecialchars($siteName); ?>" placeholder="اسم الموقع">
                </div>
                <div class="form-group form-row description-row">
                    <label>وصف الموقع:</label>
                    <div class="text-actions">
                        <span class="action-text">قراءة النص</span>
                        <button class="icon-btn read-btn" type="button" title="قراءة النص" id="readDescriptionBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <span class="action-text">تحرير النص</span>
                        <button class="icon-btn edit-btn" type="button" title="تحرير النص" id="editDescriptionBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-group form-row">
                    <label for="siteEmail">البريد الإلكتروني:</label>
                    <input type="email" id="siteEmail" class="form-control email-ltr" value="<?php echo htmlspecialchars($siteEmail); ?>" placeholder="البريد الإلكتروني" dir="ltr">
                </div>
                <div class="form-group form-row">
                    <label>وضع الصيانة:</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="maintenance_open" name="maintenance_mode" value="open" <?php echo ($maintenanceMode === 'open' || $maintenanceMode === '0' || empty($maintenanceMode)) ? 'checked' : ''; ?>>
                            <label for="maintenance_open">مفتوح</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="maintenance_locked" name="maintenance_mode" value="locked" <?php echo ($maintenanceMode === 'locked' || $maintenanceMode === '1') ? 'checked' : ''; ?>>
                            <label for="maintenance_locked">مقفل</label>
                        </div>
                        <?php if ($isSuperAdmin): ?>
                        <div class="radio-option">
                            <input type="radio" id="maintenance_closed" name="maintenance_mode" value="closed" <?php echo ($maintenanceMode === 'closed' || $maintenanceMode === '2') ? 'checked' : ''; ?>>
                            <label for="maintenance_closed">مغلق</label>
                        </div>
                        <!-- زر إعطاء تصريح الدخول للأعضاء (يظهر فقط عند اختيار "مغلق") -->
                        <button type="button" class="btn-add-permission" id="openAllowedUsersModalBtn" style="display: none; margin-right: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" y1="8" x2="19" y2="14"></line>
                                <line x1="22" y1="11" x2="16" y2="11"></line>
                            </svg>
                            <span>إعطاء تصريح الدخول</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary btn-save" id="saveGeneralSettingsBtn">حفظ التغييرات</button>
            </div>
        </div>

        <div class="settings-section">
            <h2>إعدادات الأمان والتسجيل</h2>
            <div class="settings-form">
                <div class="form-group checkbox-line">
                    <input type="checkbox" id="twofaToggle" checked>
                    <span class="checkbox-text">تفعيل التحقق بخطوتين</span>
                </div>
                <div class="form-group checkbox-line">
                    <input type="checkbox" id="loginAttemptsToggle" checked>
                    <span class="checkbox-text">تفعيل تسجيل محاولات الدخول</span>
                </div>
                <div class="form-group form-row attempts-row">
                    <label for="loginAttemptsInput">عدد محاولات الدخول المسموحة:</label>
                    <input type="number" id="loginAttemptsInput" class="form-control attempts-input" value="5" min="2" max="5">
                </div>
                <button type="button" class="btn btn-primary btn-save">حفظ التغييرات</button>
            </div>
        </div>
    </div>
</div>

<!-- النافذة المنبثقة سيتم إنشاؤها ديناميكياً عبر JavaScript -->

<script>
// دالة تهيئة الإعدادات - يتم استدعاؤها مباشرة بعد تحميل HTML
(function initSettings() {
    // إعدادات الأمان والتسجيل
    const attemptsToggle = document.getElementById('loginAttemptsToggle');
    const attemptsInput = document.getElementById('loginAttemptsInput');

    function syncAttemptsState() {
        if (attemptsToggle && attemptsToggle.checked) {
            if (attemptsInput) {
            attemptsInput.disabled = false;
            if (attemptsInput.value < 2) attemptsInput.value = 2;
            if (attemptsInput.value > 5) attemptsInput.value = 5;
            attemptsInput.min = 2;
            attemptsInput.max = 5;
            }
        } else {
            if (attemptsInput) {
            attemptsInput.disabled = true;
            attemptsInput.value = 1;
            }
        }
    }

    if (attemptsToggle && attemptsInput) {
    attemptsToggle.addEventListener('change', syncAttemptsState);
    syncAttemptsState();
    }

    // ============================================
    // إدارة الأعضاء المصرح لهم - نظام كامل
    // ============================================
    
    const openAllowedUsersModalBtn = document.getElementById('openAllowedUsersModalBtn');
    const maintenanceModeInputs = document.querySelectorAll('input[name="maintenance_mode"]');
    let allowedUsersModal = null;
    let allowedUsersList = [];
    let searchTimeout = null;
    
    // عرض/إخفاء زر الأعضاء المصرح لهم حسب وضع الصيانة
    function toggleAllowedUsersButton() {
        if (!openAllowedUsersModalBtn) {
            console.error('openAllowedUsersModalBtn not found');
            return;
        }
        
        const selectedMode = document.querySelector('input[name="maintenance_mode"]:checked');
        console.log('Selected maintenance mode:', selectedMode ? selectedMode.value : 'none');
        
        if (selectedMode && selectedMode.value === 'closed') {
            openAllowedUsersModalBtn.style.display = 'inline-flex';
            openAllowedUsersModalBtn.style.visibility = 'visible';
            openAllowedUsersModalBtn.style.opacity = '1';
            console.log('Showing allowed users button');
        } else {
            openAllowedUsersModalBtn.style.display = 'none';
            openAllowedUsersModalBtn.style.visibility = 'hidden';
            openAllowedUsersModalBtn.style.opacity = '0';
            console.log('Hiding allowed users button');
        }
    }
    
    // إضافة مستمعين لتغيير وضع الصيانة
    if (maintenanceModeInputs && maintenanceModeInputs.length > 0) {
        console.log('Found', maintenanceModeInputs.length, 'maintenance mode inputs');
        maintenanceModeInputs.forEach(input => {
            if (input) {
                input.addEventListener('change', toggleAllowedUsersButton);
                console.log('Added change listener to input with value:', input.value);
            }
        });
    } else {
        console.error('No maintenance mode inputs found');
    }
    
    // تهيئة العرض الأولي
    setTimeout(toggleAllowedUsersButton, 100);
    
    // إنشاء النافذة المنبثقة ديناميكياً
    function createModal() {
        if (allowedUsersModal) return allowedUsersModal;
        
        // إنشاء overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.id = 'allowedUsersModal';
        overlay.style.cssText = 'display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 999999; align-items: center; justify-content: center; padding: 20px; will-change: auto;';
        
        // إنشاء content
        const content = document.createElement('div');
        content.className = 'modal-content';
        content.style.cssText = 'background: white; border-radius: 12px; max-width: 600px; width: 100%; max-height: 85vh; min-height: 500px; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); transform: none; will-change: auto;';
        
        // Header
        const header = document.createElement('div');
        header.className = 'modal-header';
        header.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid #E5E7EB; background: #000000; border-radius: 12px 12px 0 0;';
        
        const title = document.createElement('h3');
        title.textContent = 'الأعضاء المصرح لهم بالدخول';
        title.style.cssText = 'margin: 0; font-size: 20px; font-weight: 600; color: #FFFFFF;';
        
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'modal-close-btn';
        closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        closeBtn.style.cssText = 'background: none; border: none; cursor: pointer; padding: 8px; color: #FFFFFF; border-radius: 6px; transition: all 0.2s;';
        closeBtn.onmouseover = function() { this.style.background = '#FFFFFF'; this.style.color = '#000000'; };
        closeBtn.onmouseout = function() { this.style.background = 'none'; this.style.color = '#FFFFFF'; };
        closeBtn.onclick = closeModal;
        
        header.appendChild(title);
        header.appendChild(closeBtn);
        
        // Body
        const body = document.createElement('div');
        body.className = 'modal-body';
        body.style.cssText = 'padding: 25px; overflow-y: visible; flex: 1;';
        
        // Search section
        const searchGroup = document.createElement('div');
        searchGroup.className = 'form-group';
        searchGroup.style.cssText = 'margin-bottom: 20px;';
        
        const searchLabel = document.createElement('label');
        searchLabel.textContent = 'البحث عن عضو:';
        // إزالة for لمنع focus عند النقر على label
        searchLabel.style.cssText = 'display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px; cursor: default;';
        // منع focus عند النقر على label
        searchLabel.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'user-search-wrapper';
        searchWrapper.style.cssText = 'position: relative;';
        // منع focus عند النقر على wrapper (وليس على input)
        searchWrapper.addEventListener('click', function(e) {
            if (e.target === searchWrapper) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.id = 'userSearchInputModal';
        searchInput.className = 'form-control user-search-input';
        searchInput.placeholder = 'ابحث عن عضو بالاسم الكامل أو باسم المستخدم ...';
        searchInput.autocomplete = 'off';
        searchInput.style.cssText = 'width: 100%; padding: 10px 40px 10px 15px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; outline: none; box-shadow: none;';
        searchInput.addEventListener('focus', function() {
            this.style.borderColor = '#00A6FB';
        });
        searchInput.addEventListener('blur', function() {
            this.style.borderColor = '#D1D5DB';
        });
        
        // زر الإلغاء داخل input
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'search-clear-btn';
        clearBtn.innerHTML = '×';
        clearBtn.style.cssText = 'position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6B7280; font-size: 20px; font-weight: bold; cursor: pointer; padding: 0; width: 24px; height: 24px; display: none; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s; line-height: 1;';
        clearBtn.onmouseover = function() { this.style.background = '#F3F4F6'; this.style.color = '#1F2937'; };
        clearBtn.onmouseout = function() { this.style.background = 'none'; this.style.color = '#6B7280'; };
        clearBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            searchInput.value = '';
            searchInput.focus();
            searchResults.innerHTML = '';
            searchResults.style.display = 'none';
            clearBtn.style.display = 'none';
        };
        
        // إظهار/إخفاء زر الإلغاء عند الكتابة
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
            }
        });
        
        const searchResults = document.createElement('div');
        searchResults.id = 'userSearchResultsModal';
        searchResults.className = 'user-search-results';
        searchResults.style.cssText = 'position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); max-height: 300px; overflow-y: auto; z-index: 1000; display: none; margin-top: 4px;';
        
        searchWrapper.appendChild(searchInput);
        searchWrapper.appendChild(clearBtn);
        searchWrapper.appendChild(searchResults);
        searchGroup.appendChild(searchLabel);
        searchGroup.appendChild(searchWrapper);
        
        // Users list section
        const listGroup = document.createElement('div');
        listGroup.className = 'form-group';
        listGroup.id = 'allowedUsersListGroup';
        listGroup.style.cssText = 'display: block;'; // إظهار القسم دائماً
        
        const listLabel = document.createElement('label');
        listLabel.textContent = 'الأعضاء المضافة:';
        listLabel.style.cssText = 'display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;';
        
        const usersList = document.createElement('div');
        usersList.id = 'allowedUsersListModal';
        usersList.className = 'allowed-users-list';
        usersList.style.cssText = 'display: flex; flex-wrap: wrap; gap: 8px; min-height: 40px; padding: 12px; border: 1px solid #E5E7EB; border-radius: 8px; background: #F9FAFB; align-items: center;';
        
        // إضافة رسالة افتراضية عند عدم وجود أعضاء
        const emptyMessage = document.createElement('div');
        emptyMessage.id = 'allowedUsersEmptyMessage';
        emptyMessage.textContent = 'لا يوجد أي عضو تم إعطاؤه التصريح بعد.';
        emptyMessage.style.cssText = 'width: 100%; text-align: center; color: #6B7280; font-size: 14px; padding: 12px; display: block; font-weight: 500;';
        usersList.appendChild(emptyMessage);
        
        // زر إلغاء الجميع (يظهر عند 3 أعضاء أو أكثر)
        const clearAllBtn = document.createElement('button');
        clearAllBtn.type = 'button';
        clearAllBtn.id = 'clearAllUsersBtn';
        clearAllBtn.className = 'clear-all-users-btn';
        clearAllBtn.textContent = 'إلغاء جميع الأعضاء';
        clearAllBtn.style.cssText = 'display: none; margin-top: 12px; padding: 8px 16px; background: #EF4444; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;';
        clearAllBtn.onmouseover = function() { this.style.background = '#DC2626'; };
        clearAllBtn.onmouseout = function() { this.style.background = '#EF4444'; };
        clearAllBtn.onclick = function() {
            if (confirm('هل أنت متأكد من إلغاء جميع الأعضاء المصرح لهم؟')) {
                const usersList = document.getElementById('allowedUsersListModal');
                const userTags = usersList.querySelectorAll('.allowed-user-tag');
                userTags.forEach(tag => {
                    const userId = parseInt(tag.getAttribute('data-user-id'));
                    removeAllowedUser(userId);
                });
            }
        };
        
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'allowedUsersInput';
        hiddenInput.name = 'allowed_users';
        hiddenInput.value = '<?php echo htmlspecialchars(json_encode($maintenanceAllowedUsers)); ?>';
        
        listGroup.appendChild(listLabel);
        listGroup.appendChild(usersList);
        listGroup.appendChild(clearAllBtn);
        listGroup.appendChild(hiddenInput);
        
        body.appendChild(searchGroup);
        body.appendChild(listGroup);
        
        content.appendChild(header);
        content.appendChild(body);
        overlay.appendChild(content);
        
        document.body.appendChild(overlay);
        
        // منع إغلاق النافذة عند النقر خارجها - الإغلاق فقط من زر الإغلاق
        overlay.addEventListener('click', function(e) {
            // منع إغلاق النافذة عند النقر على overlay
            e.stopPropagation();
            // منع focus على input عند النقر خارج الفورم
            if (e.target === overlay) {
                const searchInput = document.getElementById('userSearchInputModal');
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            }
        });
        
        // منع focus على input عند النقر على content
        content.addEventListener('click', function(e) {
            if (e.target === content || e.target === body) {
                const searchInput = document.getElementById('userSearchInputModal');
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            }
        });
        
        // منع إغلاق النافذة عند الضغط على ESC - الإغلاق فقط من زر الإغلاق
        // (تم إزالة event listener للـ ESC)
        
        // البحث عن الأعضاء
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 1) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                fetch('search_users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ search: searchTerm })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users && data.users.length > 0) {
                        searchResults.innerHTML = '';
                        const availableUsers = [];
                        
                        // تصفية المستخدمين الموجودين بالفعل في القائمة
                        data.users.forEach(user => {
                            const existing = usersList.querySelector(`[data-user-id="${user.id}"]`);
                            if (!existing) {
                                availableUsers.push(user);
                            }
                        });
                        
                        // إذا كان هناك مستخدمون متاحون، عرضهم
                        if (availableUsers.length > 0) {
                            availableUsers.forEach(user => {
                                const item = document.createElement('div');
                                item.className = 'user-search-result-item';
                                item.textContent = user.display;
                                item.style.cssText = 'padding: 12px 16px; cursor: pointer; transition: all 0.2s ease; border-bottom: 1px solid #E5E7EB; font-size: 14px; color: #374151; background: white; font-weight: 500;';
                                item.onmouseover = function() { 
                                    this.style.background = '#E0F2FE'; 
                                    this.style.color = '#0369A1';
                                    this.style.paddingLeft = '20px';
                                };
                                item.onmouseout = function() { 
                                    this.style.background = 'white'; 
                                    this.style.color = '#374151';
                                    this.style.paddingLeft = '16px';
                                };
                                item.onclick = function() {
                                    addAllowedUser(user);
                                    searchInput.value = '';
                                    searchResults.innerHTML = '';
                                    searchResults.style.display = 'none';
                                    clearBtn.style.display = 'none'; // إخفاء زر الإلغاء
                                };
                                searchResults.appendChild(item);
                            });
                            searchResults.style.display = 'block';
                        } else {
                            // جميع النتائج موجودة بالفعل في القائمة
                            const searchTerm = searchInput.value.trim();
                            if (searchTerm.length === 1) {
                                // التحقق من نوع الحرف (عربي أو إنجليزي)
                                const isArabic = /[\u0600-\u06FF]/.test(searchTerm);
                                const message = isArabic 
                                    ? 'جميع الأعضاء الذين تبدأ أسماؤهم بهذا الحرف موجودون بالفعل في القائمة'
                                    : 'جميع الأعضاء الذين تبدأ أسماؤهم المستخدمة بهذا الحرف موجودون بالفعل في القائمة';
                                searchResults.innerHTML = '<div class="no-results" style="padding: 16px; text-align: center; color: #6B7280; font-size: 14px; font-weight: 500;">' + message + '</div>';
                            } else {
                                searchResults.innerHTML = '<div class="no-results" style="padding: 16px; text-align: center; color: #6B7280; font-size: 14px; font-weight: 500;">جميع النتائج موجودة بالفعل في القائمة</div>';
                            }
                            searchResults.style.display = 'block';
                        }
                    } else {
                        // إذا كان البحث بحرف واحد فقط ولا توجد نتائج
                        if (data.no_results && data.search_term && data.search_term.length === 1) {
                            // التحقق من نوع الحرف (عربي أو إنجليزي)
                            const isArabic = /[\u0600-\u06FF]/.test(data.search_term);
                            const message = isArabic 
                                ? 'لا يوجد أعضاء آخرون تبدأ أسماؤهم بهذا الحرف'
                                : 'لا يوجد أعضاء آخرون تبدأ أسماؤهم المستخدمة بهذا الحرف';
                            searchResults.innerHTML = '<div class="no-results" style="padding: 16px; text-align: center; color: #6B7280; font-size: 14px; font-weight: 500;">' + message + '</div>';
                        } else {
                            searchResults.innerHTML = '<div class="no-results" style="padding: 16px; text-align: center; color: #6B7280; font-size: 14px; font-weight: 500;">لا توجد نتائج</div>';
                        }
                        searchResults.style.display = 'block';
                    }
                })
                .catch(error => {
                    searchResults.innerHTML = '<div class="no-results" style="padding: 16px; text-align: center; color: #EF4444; font-size: 14px; font-weight: 500;">حدث خطأ في البحث</div>';
                    searchResults.style.display = 'block';
                });
            }, 300);
        });
        
        // تحميل الأعضاء المضافة مسبقاً
        loadAllowedUsers();
        
        // تحديث حالة الرسالة وزر إلغاء الجميع
        setTimeout(function() {
            const usersList = document.getElementById('allowedUsersListModal');
            const emptyMessage = document.getElementById('allowedUsersEmptyMessage');
            if (usersList) {
                const userTags = usersList.querySelectorAll('.allowed-user-tag');
                if (userTags.length > 0) {
                    if (emptyMessage) emptyMessage.style.display = 'none';
                } else {
                    if (emptyMessage) emptyMessage.style.display = 'block';
                }
                updateClearAllButton();
            }
        }, 100);
        
        allowedUsersModal = overlay;
        return overlay;
    }
    
    // فتح النافذة
    function openModal() {
        const modal = createModal();
        modal.style.display = 'flex';
        // لا نغير أي شيء في body - شريط التمرير يبقى كما هو
        
        // منع قائمة الزر الأيمن للفأرة عند فتح النافذة
        document.addEventListener('contextmenu', preventContextMenu, true);
    }
    
    // دالة لمنع قائمة الزر الأيمن
    function preventContextMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
    
    // إغلاق النافذة
    function closeModal() {
        if (allowedUsersModal) {
            allowedUsersModal.style.display = 'none';
        }
        
        // إعادة تفعيل قائمة الزر الأيمن عند إغلاق النافذة
        document.removeEventListener('contextmenu', preventContextMenu, true);
    }
    
    // تحميل الأعضاء المضافة
    function loadAllowedUsers() {
        const hiddenInput = document.getElementById('allowedUsersInput');
        if (!hiddenInput || !hiddenInput.value) return;
        
        try {
            const userIds = JSON.parse(hiddenInput.value);
            if (Array.isArray(userIds) && userIds.length > 0) {
                fetch('get_allowed_users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_ids: userIds })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users && data.users.length > 0) {
                        const emptyMessage = document.getElementById('allowedUsersEmptyMessage');
                        if (emptyMessage) {
                            emptyMessage.style.display = 'none';
                        }
                        data.users.forEach(user => {
                            addAllowedUser(user, false);
                        });
                        updateClearAllButton();
                    } else {
                        // إذا لم يكن هناك أعضاء، أظهر الرسالة
                        const emptyMessage = document.getElementById('allowedUsersEmptyMessage');
                        if (emptyMessage) {
                            emptyMessage.style.display = 'block';
                        }
                    }
                })
                .catch(error => console.error('خطأ في تحميل الأعضاء:', error));
            }
        } catch (e) {
            console.error('خطأ في تحليل بيانات الأعضاء:', e);
        }
    }
    
    // إضافة عضو
    function addAllowedUser(user, updateInput = true) {
        const usersList = document.getElementById('allowedUsersListModal');
        const hiddenInput = document.getElementById('allowedUsersInput');
        const listGroup = document.getElementById('allowedUsersListGroup');
        if (!usersList || !hiddenInput) return;
        
        const existing = usersList.querySelector(`[data-user-id="${user.id}"]`);
        if (existing) return;
        
        const tag = document.createElement('span');
        tag.className = 'allowed-user-tag';
        tag.setAttribute('data-user-id', user.id);
        tag.style.cssText = 'display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #E0F2FE; color: #0369A1; border-radius: 6px; font-size: 13px; font-weight: 500;';
        
        const text = document.createElement('span');
        text.textContent = user.display || (user.fullname + ' (' + user.username + ')');
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-user-btn';
        removeBtn.textContent = '×';
        removeBtn.style.cssText = 'background: none; border: none; color: #0369A1; font-size: 18px; font-weight: bold; cursor: pointer; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s; line-height: 1;';
        removeBtn.onmouseover = function() { this.style.background = '#0369A1'; this.style.color = 'white'; };
        removeBtn.onmouseout = function() { this.style.background = 'none'; this.style.color = '#0369A1'; };
        removeBtn.onclick = function() { removeAllowedUser(user.id); };
        
        tag.appendChild(text);
        tag.appendChild(removeBtn);
        usersList.appendChild(tag);
        
        // إخفاء رسالة "لا يوجد" عند إضافة أول عضو
        const emptyMessage = document.getElementById('allowedUsersEmptyMessage');
        if (emptyMessage) {
            emptyMessage.style.display = 'none';
        }
        
        // تحديث زر إلغاء الجميع
        updateClearAllButton();
        
        if (updateInput) {
            updateAllowedUsersInput();
            // حفظ في قاعدة البيانات
            saveAllowedUsers();
        }
    }
    
    // تحديث زر إلغاء الجميع
    function updateClearAllButton() {
        const usersList = document.getElementById('allowedUsersListModal');
        const clearAllBtn = document.getElementById('clearAllUsersBtn');
        if (!usersList || !clearAllBtn) return;
        
        const userTags = usersList.querySelectorAll('.allowed-user-tag');
        if (userTags.length >= 3) {
            clearAllBtn.style.display = 'block';
        } else {
            clearAllBtn.style.display = 'none';
        }
    }
    
    // إزالة عضو
    window.removeAllowedUser = function(userId) {
        const usersList = document.getElementById('allowedUsersListModal');
        const hiddenInput = document.getElementById('allowedUsersInput');
        const listGroup = document.getElementById('allowedUsersListGroup');
        if (!usersList || !hiddenInput) return;
        
        const tag = usersList.querySelector(`[data-user-id="${userId}"]`);
        if (tag) {
            tag.remove();
            updateAllowedUsersInput();
            
            // إظهار رسالة "لا يوجد" إذا لم يعد هناك أعضاء
            const emptyMessage = document.getElementById('allowedUsersEmptyMessage');
            const userTags = usersList.querySelectorAll('.allowed-user-tag');
            if (userTags.length === 0 && emptyMessage) {
                emptyMessage.style.display = 'block';
            }
            
            // تحديث زر إلغاء الجميع
            updateClearAllButton();
            
            // حفظ في قاعدة البيانات
            saveAllowedUsers();
        }
    };
    
    // تحديث القائمة المخفية
    function updateAllowedUsersInput() {
        const usersList = document.getElementById('allowedUsersListModal');
        const hiddenInput = document.getElementById('allowedUsersInput');
        if (!usersList || !hiddenInput) return;
        
        const tags = usersList.querySelectorAll('.allowed-user-tag');
        const userIds = Array.from(tags).map(tag => parseInt(tag.getAttribute('data-user-id')));
        hiddenInput.value = JSON.stringify(userIds);
    }
    
    // حفظ الأعضاء المصرح لهم في قاعدة البيانات
    function saveAllowedUsers() {
        const hiddenInput = document.getElementById('allowedUsersInput');
        if (!hiddenInput) return;
        
        const userIds = JSON.parse(hiddenInput.value || '[]');
        
        fetch('save_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                maintenance_allowed_users: userIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('خطأ في حفظ الأعضاء المصرح لهم:', data.message);
            }
        })
        .catch(error => {
            console.error('خطأ في حفظ الأعضاء المصرح لهم:', error);
        });
    }
    
    // ربط الزر بفتح النافذة
    if (openAllowedUsersModalBtn) {
        openAllowedUsersModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        });
    }

    // دالة لإظهار رسالة الحفظ في الزاوية السفلية اليمنى
    window.showSaveMessage = function(success, message) {
        // إزالة الرسالة السابقة إن وجدت
        const existingMessage = document.querySelector('.global-save-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // إنشاء رسالة جديدة
        const saveMessage = document.createElement('div');
        saveMessage.className = 'global-save-message ' + (success ? 'success' : 'error');
        saveMessage.textContent = message;
        document.body.appendChild(saveMessage);
        
        // إظهار الرسالة
        setTimeout(() => {
            saveMessage.classList.add('show');
        }, 10);
        
        // إخفاء الرسالة بعد 3 ثواني
        setTimeout(() => {
            saveMessage.classList.remove('show');
            setTimeout(() => {
                saveMessage.remove();
            }, 300);
        }, 3000);
    };

    // إعدادات الإعدادات العامة
    const siteNameInput = document.getElementById('siteName');
    const siteEmailInput = document.getElementById('siteEmail');
    const saveGeneralSettingsBtn = document.getElementById('saveGeneralSettingsBtn');
    const readDescriptionBtn = document.getElementById('readDescriptionBtn');
    const editDescriptionBtn = document.getElementById('editDescriptionBtn');
    
    console.log('عناصر الإعدادات:', {
        siteNameInput: !!siteNameInput,
        siteEmailInput: !!siteEmailInput,
        saveGeneralSettingsBtn: !!saveGeneralSettingsBtn
    });

    // نافذة وصف الموقع
    let descriptionModal = null;
    
    // بيانات وصف الموقع من PHP
    let siteDescriptionData = <?php echo json_encode($siteDescription, JSON_UNESCAPED_UNICODE); ?>;
    
    // إنشاء نافذة وصف الموقع
    function createDescriptionModal(mode) {
        // إزالة النافذة السابقة إن وجدت
        if (descriptionModal && document.body.contains(descriptionModal)) {
            descriptionModal.remove();
        }
        descriptionModal = null;
        
        // تحديد العنوان حسب النوع
        const title = (mode === 'read') ? 'وصف الموقع' : 'تحرير وصف الموقع';
        
        // Overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.id = 'descriptionModalOverlay';
        overlay.style.cssText = 'display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 999999; align-items: center; justify-content: center; padding: 20px;';
        
        // Content
        const content = document.createElement('div');
        content.className = 'modal-content';
        content.style.cssText = 'background: white; border-radius: 12px; max-width: 900px; width: 100%; max-height: 85vh; min-height: 600px; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); transform: none; will-change: auto;';
        
        // Header
        const header = document.createElement('div');
        header.className = 'modal-header';
        header.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid #E5E7EB; background: #000000; border-radius: 12px 12px 0 0;';
        
        const headerTitle = document.createElement('h3');
        headerTitle.textContent = title;
        headerTitle.style.cssText = 'margin: 0; font-size: 20px; font-weight: 600; color: #FFFFFF;';
        
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'modal-close-btn';
        closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        closeBtn.style.cssText = 'background: none; border: none; cursor: pointer; padding: 8px; color: #FFFFFF; border-radius: 6px; transition: all 0.2s;';
        closeBtn.onmouseover = function() { this.style.background = '#FFFFFF'; this.style.color = '#000000'; };
        closeBtn.onmouseout = function() { this.style.background = 'none'; this.style.color = '#FFFFFF'; };
        closeBtn.onclick = function() { closeDescriptionModal(); };
        
        header.appendChild(headerTitle);
        header.appendChild(closeBtn);
        
        // Body
        const body = document.createElement('div');
        body.className = 'modal-body';
        
        // دالة للتحقق من أن المحتوى فارغ (بما في ذلك HTML فارغ)
        function isEmptyContent(content) {
            if (!content) return true;
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            const textContent = tempDiv.textContent || tempDiv.innerText || '';
            return textContent.trim() === '';
        }
        
        // محتوى Body حسب وجود الوصف
        if (mode === 'read') {
            if (isEmptyContent(siteDescriptionData)) {
                // لا يوجد وصف - الرسالة في الوسط
                body.style.cssText = 'padding: 25px; overflow-y: visible; flex: 1; display: flex; align-items: center; justify-content: center;';
                const emptyMessage = document.createElement('div');
                emptyMessage.textContent = 'لم يكتب وصف للموقع بعد.';
                emptyMessage.style.cssText = 'text-align: center; color: #6B7280; font-size: 16px; font-weight: 500; padding: 40px;';
                body.appendChild(emptyMessage);
            } else {
                // يوجد وصف - يبدأ من الأعلى
                body.style.cssText = 'padding: 25px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start;';
                const descriptionContent = document.createElement('div');
                descriptionContent.id = 'descriptionReadContent';
                descriptionContent.style.cssText = 'width: 100%; color: #374151; font-size: 15px; line-height: 1.8; word-wrap: break-word; direction: rtl; text-align: right;';
                descriptionContent.innerHTML = siteDescriptionData; // استخدام innerHTML لعرض HTML
                body.appendChild(descriptionContent);
            }
        } else {
            // وضع التحرير
            const editForm = document.createElement('form');
            editForm.id = 'editDescriptionForm';
            editForm.style.cssText = 'width: 100%; height: 100%; display: flex; flex-direction: column; gap: 0;';
            
            // شريط الأدوات (Toolbar)
            const toolbar = document.createElement('div');
            toolbar.id = 'descriptionToolbar';
            toolbar.className = 'rich-text-toolbar';
            toolbar.style.cssText = 'display: flex; align-items: center; gap: 4px; padding: 10px 12px; background: #F9FAFB; border: 1px solid #D1D5DB; border-bottom: none; border-radius: 8px 8px 0 0; flex-wrap: wrap;';
            
            // دالة لإنشاء زر في شريط الأدوات
            function createToolbarButton(command, icon, title, hasDropdown = false) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'toolbar-btn';
                btn.title = title;
                btn.setAttribute('data-command', command);
                btn.innerHTML = icon;
                btn.style.cssText = 'width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: transparent; border: 1px solid transparent; border-radius: 6px; cursor: pointer; color: #374151; transition: all 0.2s ease; padding: 0;';
                btn.onmouseover = function() {
                    this.style.background = '#E5E7EB';
                    this.style.borderColor = '#D1D5DB';
                };
                btn.onmouseout = function() {
                    // إعادة تعيين الأنماط فقط إذا لم يكن الزر مفعلاً
                    if (!this.classList.contains('active')) {
                        this.style.background = 'transparent';
                        this.style.borderColor = 'transparent';
                    } else {
                        // إذا كان مفعلاً، إعادة تطبيق الأنماط المفعّلة
                        this.style.background = '#E0F2FE';
                        this.style.borderColor = '#0369A1';
                        this.style.color = '#0369A1';
                    }
                };
                
                // إضافة CSS للـ active state
                const style = document.createElement('style');
                style.textContent = `
                    .toolbar-btn.active {
                        background: #E0F2FE !important;
                        border-color: #0369A1 !important;
                        color: #0369A1 !important;
                    }
                `;
                if (!document.head.querySelector('style[data-toolbar-active]')) {
                    style.setAttribute('data-toolbar-active', 'true');
                    document.head.appendChild(style);
                }
                
                return btn;
            }
            
            // أيقونات SVG
            const icons = {
                bold: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>',
                italic: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>',
                underline: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path><line x1="4" y1="21" x2="20" y2="21"></line></svg>',
                strikethrough: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="20" y2="12"></line><path d="M14.5 4h-5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7h-5"></path></svg>',
                alignRight: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg>',
                alignCenter: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg>',
                alignLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="10" x2="15" y2="10"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="14" x2="15" y2="14"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>',
                alignJustify: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="10" x2="21" y2="10"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="14" x2="21" y2="14"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>',
                directionRTL: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 10 4 15 9 20"></polyline><path d="M20 4v7a4 4 0 0 1-4 4H4"></path></svg>',
                directionLTR: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 10 20 15 15 20"></polyline><path d="M4 4v7a4 4 0 0 0 4 4h12"></path></svg>',
                color: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
                fontSize: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><text x="6" y="18" font-size="10" font-weight="bold">Aa</text></svg>',
                heading: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"></path><path d="M4 10h12"></path><path d="M4 14h8"></path><path d="M4 18h4"></path><path d="M18 14l3 3-3 3"></path></svg>'
            };
            
            // أزرار التنسيق الأساسية
            const boldBtn = createToolbarButton('bold', icons.bold, 'عريض (Ctrl+B)');
            const italicBtn = createToolbarButton('italic', icons.italic, 'مائل (Ctrl+I)');
            const underlineBtn = createToolbarButton('underline', icons.underline, 'تحته سطر (Ctrl+U)');
            const strikethroughBtn = createToolbarButton('strikethrough', icons.strikethrough, 'في وسطه سطر');
            
            // فاصل
            const separator1 = document.createElement('div');
            separator1.style.cssText = 'width: 1px; height: 24px; background: #D1D5DB; margin: 0 4px;';
            
            // أزرار المحاذاة
            const alignRightBtn = createToolbarButton('justifyRight', icons.alignRight, 'محاذاة لليمين');
            const alignCenterBtn = createToolbarButton('justifyCenter', icons.alignCenter, 'محاذاة للوسط');
            const alignLeftBtn = createToolbarButton('justifyLeft', icons.alignLeft, 'محاذاة لليسار');
            const alignJustifyBtn = createToolbarButton('justifyFull', icons.alignJustify, 'موازاة');
            
            // فاصل
            const separator2 = document.createElement('div');
            separator2.style.cssText = 'width: 1px; height: 24px; background: #D1D5DB; margin: 0 4px;';
            
            // أزرار الاتجاه
            const directionRTLBtn = createToolbarButton('dirRTL', icons.directionRTL, 'اتجاه من اليمين لليسار');
            const directionLTRBtn = createToolbarButton('dirLTR', icons.directionLTR, 'اتجاه من اليسار لليمين');
            
            // فاصل
            const separator3 = document.createElement('div');
            separator3.style.cssText = 'width: 1px; height: 24px; background: #D1D5DB; margin: 0 4px;';
            
            // زر اللون
            const colorBtn = createToolbarButton('foreColor', icons.color, 'لون النص');
            const colorInput = document.createElement('input');
            colorInput.type = 'color';
            colorInput.id = 'textColorPicker';
            colorInput.style.cssText = 'position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;';
            colorInput.value = '#000000';
            
            // تعريف أحجام الخط المتاحة
            const fontSizes = [6, 8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 36];
            
            // زر حجم الخط
            const fontSizeBtn = document.createElement('button');
            fontSizeBtn.type = 'button';
            fontSizeBtn.className = 'toolbar-btn';
            fontSizeBtn.title = 'حجم الخط';
            fontSizeBtn.innerHTML = '16';
            fontSizeBtn.setAttribute('data-current-size', '16');
            fontSizeBtn.style.cssText = 'width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: transparent; border: 1px solid transparent; border-radius: 6px; cursor: pointer; color: #374151; transition: all 0.2s ease; padding: 0; font-size: 13px; font-weight: 600;';
            fontSizeBtn.onmouseover = function() {
                if (!this.classList.contains('active')) {
                    this.style.background = '#E5E7EB';
                    this.style.borderColor = '#D1D5DB';
                }
            };
            fontSizeBtn.onmouseout = function() {
                if (!this.classList.contains('active')) {
                    this.style.background = 'transparent';
                    this.style.borderColor = 'transparent';
                } else {
                    this.style.background = '#E0F2FE';
                    this.style.borderColor = '#0369A1';
                    this.style.color = '#0369A1';
                }
            };
            
            // قائمة منسدلة لحجم الخط
            const fontSizeDropdown = document.createElement('div');
            fontSizeDropdown.className = 'font-size-dropdown';
            fontSizeDropdown.style.cssText = 'display: none; position: fixed; background: white; border: 1px solid #D1D5DB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 8px; z-index: 1000001; min-width: 140px; direction: rtl;';
            
            // إنشاء container للصفوف
            const fontSizeContainer = document.createElement('div');
            fontSizeContainer.style.cssText = 'display: flex; flex-direction: column; gap: 4px;';
            
            let currentRow = null;
            
            fontSizes.forEach((size, index) => {
                // إنشاء صف جديد كل خيارين
                if (index % 2 === 0) {
                    currentRow = document.createElement('div');
                    currentRow.style.cssText = 'display: flex; gap: 4px; pointer-events: none;';
                    fontSizeContainer.appendChild(currentRow);
                }
                
                const option = document.createElement('div');
                option.textContent = size;
                option.setAttribute('data-size', size);
                option.setAttribute('data-active', 'false');
                option.style.cssText = 'flex: 1; padding: 8px 12px; cursor: pointer; border-radius: 4px; font-size: 14px; text-align: center; color: #374151; transition: background-color 0.2s ease, color 0.2s ease; pointer-events: auto; background: transparent;';
                
                option.addEventListener('mouseenter', function(e) {
                    e.stopPropagation();
                    if (this.getAttribute('data-active') !== 'true') {
                        this.style.background = '#E0F2FE';
                        this.style.color = '#0369A1';
                    }
                });
                
                option.addEventListener('mouseleave', function(e) {
                    e.stopPropagation();
                    if (this.getAttribute('data-active') !== 'true') {
                        this.style.background = 'transparent';
                        this.style.color = '#374151';
                    }
                });
                
                option.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (this.getAttribute('data-active') === 'true') {
                        return;
                    }
                    
                    const selectedSize = parseInt(this.getAttribute('data-size'));
                    
                    // إغلاق القائمة
                    fontSizeDropdown.style.display = 'none';
                    
                    // التأكد من أن المحرر في focus
                    editor.focus();
                    
                    // الحصول على التحديد الحالي
                    const selection = window.getSelection();
                    
                    if (selection.rangeCount === 0) {
                        updateEditorContent();
                        updateToolbarState();
                        return;
                    }
                    
                    const range = selection.getRangeAt(0);
                    
                    if (!range.collapsed) {
                        // إذا كان هناك نص محدد
                        const selectedText = range.toString();
                        if (!selectedText || selectedText.trim().length === 0) {
                            updateEditorContent();
                            updateToolbarState();
                            return;
                        }
                        
                        // تطبيق الحجم على النص المحدد
                        try {
                            // إنشاء span جديد مع الحجم المحدد
                            const span = document.createElement('span');
                            span.style.fontSize = selectedSize + 'px';
                            
                            // محاولة استخدام surroundContents أولاً
                            try {
                                range.surroundContents(span);
                                
                                // تحديد النص داخل span
                                const newRange = document.createRange();
                                newRange.selectNodeContents(span);
                                selection.removeAllRanges();
                                selection.addRange(newRange);
                                
                                // تحديث رقم الحجم في الزر
                                fontSizeBtn.innerHTML = selectedSize.toString();
                                fontSizeBtn.setAttribute('data-current-size', selectedSize.toString());
                                
                                updateEditorContent();
                                updateToolbarState();
                            } catch(surroundErr) {
                                // إذا فشل surroundContents، استخدم طريقة بديلة موثوقة
                                // حفظ المواضع الحالية
                                const startContainer = range.startContainer;
                                const startOffset = range.startOffset;
                                const endContainer = range.endContainer;
                                const endOffset = range.endOffset;
                                
                                // إنشاء نطاق جديد من المواضع المحفوظة
                                const workRange = document.createRange();
                                workRange.setStart(startContainer, startOffset);
                                workRange.setEnd(endContainer, endOffset);
                                
                                // استخراج المحتوى المحدد مباشرة
                                const contents = workRange.extractContents();
                                
                                // إضافة المحتوى المستخرج إلى span
                                if (contents && contents.childNodes.length > 0) {
                                    while (contents.firstChild) {
                                        span.appendChild(contents.firstChild);
                                    }
                                } else {
                                    span.textContent = selectedText;
                                }
                                
                                // إدراج span في الموضع الصحيح (النطاق الآن فارغ بعد extractContents)
                                workRange.insertNode(span);
                                
                                // تحديد النص داخل span
                                const finalRange = document.createRange();
                                finalRange.selectNodeContents(span);
                                selection.removeAllRanges();
                                selection.addRange(finalRange);
                                
                                // تحديث رقم الحجم في الزر
                                fontSizeBtn.innerHTML = selectedSize.toString();
                                fontSizeBtn.setAttribute('data-current-size', selectedSize.toString());
                                
                                updateEditorContent();
                                updateToolbarState();
                            }
                        } catch(err) {
                            console.error('خطأ في تطبيق حجم الخط:', err);
                            updateEditorContent();
                            updateToolbarState();
                        }
                    } else {
                        // إذا لم يكن هناك نص محدد
                        updateEditorContent();
                        updateToolbarState();
                    }
                };
                
                currentRow.appendChild(option);
            });
            
            fontSizeDropdown.appendChild(fontSizeContainer);
            
            // دالة لتحديث حالة قائمة حجم الخط
            function updateFontSizeDropdownState(currentSize) {
                const options = fontSizeDropdown.querySelectorAll('[data-size]');
                options.forEach(option => {
                    const size = parseInt(option.getAttribute('data-size'));
                    if (size === currentSize) {
                        option.style.background = '#E0F2FE';
                        option.style.color = '#0369A1';
                        option.style.fontWeight = 'bold';
                        option.style.opacity = '0.7';
                        option.style.cursor = 'default';
                        option.style.pointerEvents = 'none';
                        option.setAttribute('data-active', 'true');
                    } else {
                        option.style.background = 'transparent';
                        option.style.color = '#374151';
                        option.style.fontWeight = 'normal';
                        option.style.opacity = '1';
                        option.style.cursor = 'pointer';
                        option.style.pointerEvents = 'auto';
                        option.setAttribute('data-active', 'false');
                    }
                });
            }
            
            // تهيئة الحجم الافتراضي
            updateFontSizeDropdownState(16);
            
            // زر العناوين
            const headingBtn = document.createElement('button');
            headingBtn.type = 'button';
            headingBtn.className = 'toolbar-btn';
            headingBtn.title = 'العناوين';
            headingBtn.innerHTML = icons.heading;
            headingBtn.style.cssText = 'width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: transparent; border: 1px solid transparent; border-radius: 6px; cursor: pointer; color: #374151; transition: all 0.2s ease; padding: 0;';
            headingBtn.onmouseover = function() {
                this.style.background = '#E5E7EB';
                this.style.borderColor = '#D1D5DB';
            };
            headingBtn.onmouseout = function() {
                this.style.background = 'transparent';
                this.style.borderColor = 'transparent';
            };
            
            // قائمة منسدلة للعناوين
            const headingDropdown = document.createElement('div');
            headingDropdown.className = 'heading-dropdown';
            headingDropdown.style.cssText = 'display: none; position: fixed; background: white; border: 1px solid #D1D5DB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 4px; z-index: 1000001; min-width: 180px; direction: rtl; text-align: right;';
            
            // ترتيب العناوين حسب الطلب
            const headings = [
                { tag: 'h2', text: 'عنوان رئيسي', defaultText: 'عنوان رئيسي', fontSize: '20px', fontWeight: 'bold' },
                { tag: 'h3', text: 'عنوان فرعي', defaultText: 'عنوان فرعي', fontSize: '18px', fontWeight: 'bold' },
                { tag: 'h4', text: 'عنوان عادي', defaultText: 'عنوان عادي', fontSize: '16px', fontWeight: 'bold' },
                { tag: 'p', text: 'فقرة عادية', defaultText: 'فقرة عادية', fontSize: '14px', fontWeight: 'normal' }
            ];
            
            headings.forEach(heading => {
                const option = document.createElement('div');
                option.textContent = heading.text;
                option.setAttribute('data-tag', heading.tag);
                option.setAttribute('data-default-text', heading.defaultText);
                option.style.cssText = `padding: 8px 12px; cursor: pointer; border-radius: 4px; font-size: ${heading.fontSize}; font-weight: ${heading.fontWeight}; color: #374151; transition: all 0.2s ease; text-align: right; direction: rtl;`;
                option.onmouseover = function() {
                    // منع hover تماماً إذا كان الخيار محدداً ومفعلاً
                    const isActive = this.getAttribute('data-active') === 'true';
                    if (!isActive) {
                        this.style.background = '#E0F2FE'; 
                        this.style.color = '#0369A1';
                    }
                    // إذا كان active، لا نفعل أي شيء - نترك الأنماط كما هي
                };
                option.onmouseout = function() {
                    // منع تغيير الأنماط إذا كان الخيار محدداً ومفعلاً
                    const isActive = this.getAttribute('data-active') === 'true';
                    if (!isActive) {
                        this.style.background = 'transparent'; 
                        this.style.color = '#374151';
                    }
                    // إذا كان active، لا نفعل أي شيء - نترك الأنماط كما هي
                };
                option.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // منع الضغط على الخيار المحدد والمفعّل
                    if (this.getAttribute('data-active') === 'true') {
                        return;
                    }
                    
                    const tag = this.getAttribute('data-tag');
                    const defaultText = this.getAttribute('data-default-text');
                    
                    // إغلاق القائمة المنسدلة أولاً
                    closeHeadingDropdown();
                    
                    // التأكد من أن المحرر في focus
                    editor.focus();
                    
                    // الحصول على التحديد الحالي
                    const selection = window.getSelection();
                    
                    if (selection.rangeCount === 0) {
                        // إذا لم يكن هناك تحديد، أنشئ عنصر جديد في نهاية المحرر
                        const newElement = document.createElement(tag);
                        newElement.style.textAlign = 'center';
                        newElement.style.direction = 'rtl';
                        newElement.style.color = '#000000';
                        newElement.textContent = defaultText;
                        editor.appendChild(newElement);
                        
                        // تحديد النص بالكامل
                        const newRange = document.createRange();
                        newRange.selectNodeContents(newElement);
                        selection.removeAllRanges();
                        selection.addRange(newRange);
                        updateEditorContent();
                        updateToolbarState();
                        return;
                    }
                    
                    const range = selection.getRangeAt(0);
                    const isCollapsed = range.collapsed;
                    
                    // التحقق من العنصر الحالي
                    let container = range.commonAncestorContainer;
                    if (container.nodeType === 3) {
                        container = container.parentElement;
                    }
                    
                    // البحث عن عنصر كتلة
                    let blockElement = container;
                    while (blockElement && blockElement !== editor) {
                        const tagName = blockElement.tagName;
                        if (tagName && ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV'].includes(tagName)) {
                            break;
                        }
                        blockElement = blockElement.parentElement;
                    }
                    
                    // إذا كان المؤشر في مساحة فارغة (لا يوجد عنصر كتلة)
                    if (isCollapsed && (!blockElement || blockElement === editor)) {
                        // إنشاء عنصر جديد في موضع المؤشر مع النص الافتراضي
                        const newElement = document.createElement(tag);
                        newElement.style.textAlign = 'center';
                        newElement.style.direction = 'rtl';
                        newElement.style.color = '#000000';
                        newElement.textContent = defaultText;
                        
                        try {
                            range.insertNode(newElement);
                            
                            // تحديد النص بالكامل
                            const newRange = document.createRange();
                            newRange.selectNodeContents(newElement);
                            selection.removeAllRanges();
                            selection.addRange(newRange);
                            updateEditorContent();
                            updateToolbarState();
                            return;
                        } catch (err) {
                            // إذا فشل، أضف في نهاية المحرر
                            editor.appendChild(newElement);
                            const newRange = document.createRange();
                            newRange.selectNodeContents(newElement);
                            selection.removeAllRanges();
                            selection.addRange(newRange);
                            updateEditorContent();
                            updateToolbarState();
                            return;
                        }
                    }
                    
                    // إذا كان هناك نص محدد
                    if (!isCollapsed) {
                        // تطبيق العنوان مباشرة على النص المحدد
                        try {
                            // حفظ المواضع الحالية
                            const startContainer = range.startContainer;
                            const startOffset = range.startOffset;
                            const endContainer = range.endContainer;
                            const endOffset = range.endOffset;
                            
                            // إنشاء عنصر العنوان الجديد
                            const newElement = document.createElement(tag);
                            newElement.style.textAlign = 'center';
                            newElement.style.direction = 'rtl';
                            newElement.style.color = '#000000';
                            
                            // محاولة استخدام surroundContents أولاً
                            try {
                                range.surroundContents(newElement);
                                
                                // تحديد النص داخل العنصر
                                const newRange = document.createRange();
                                newRange.selectNodeContents(newElement);
                                selection.removeAllRanges();
                                selection.addRange(newRange);
                                
                                // تحديث الحالة
                                updateHeadingDropdownState(tag);
                                headingBtn.classList.add('active');
                                
                                updateEditorContent();
                                updateToolbarState();
                            } catch(surroundErr) {
                                // إذا فشل surroundContents، استخدم طريقة بديلة
                                // إنشاء نطاق جديد من المواضع المحفوظة
                                const workRange = document.createRange();
                                workRange.setStart(startContainer, startOffset);
                                workRange.setEnd(endContainer, endOffset);
                                
                                // استخراج المحتوى المحدد
                                const contents = workRange.extractContents();
                                
                                // إضافة المحتوى المستخرج إلى العنصر الجديد
                                if (contents && contents.childNodes.length > 0) {
                                    while (contents.firstChild) {
                                        newElement.appendChild(contents.firstChild);
                                    }
                                } else {
                                    newElement.textContent = range.toString();
                                }
                                
                                // إدراج العنصر في الموضع الصحيح
                                workRange.insertNode(newElement);
                                
                                // تحديد النص داخل العنصر
                                const finalRange = document.createRange();
                                finalRange.selectNodeContents(newElement);
                                selection.removeAllRanges();
                                selection.addRange(finalRange);
                                
                                // تحديث الحالة
                                updateHeadingDropdownState(tag);
                                headingBtn.classList.add('active');
                                
                                updateEditorContent();
                                updateToolbarState();
                            }
                        } catch(err) {
                            console.error('خطأ في تطبيق العنوان:', err);
                            updateEditorContent();
                            updateToolbarState();
                        }
                    } else {
                        // إذا كان المؤشر داخل عنصر كتلة موجود
                        if (blockElement && blockElement !== editor && blockElement.tagName) {
                            // إذا كان نفس النوع، لا تفعل شيء
                            if (blockElement.tagName.toLowerCase() === tag) {
                                updateToolbarState();
                                return;
                            }
                            
                            // التحقق من أن العنصر الحالي ليس له خصائص خاصة (مثل عناوين أخرى)
                            // إذا كان العنصر له خصائص خاصة، لا نستبدله إلا إذا كان المستخدم يريد ذلك
                            const currentTag = blockElement.tagName.toLowerCase();
                            const isHeading = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(currentTag);
                            
                            // إذا كان العنصر الحالي عنواناً مختلفاً، استبدله
                            // إذا كان العنصر فقرة عادية، استبدله
                            if (isHeading || currentTag === 'p') {
                                // استبدال العنصر الحالي
                                const newElement = document.createElement(tag);
                                newElement.innerHTML = blockElement.innerHTML;
                                newElement.style.textAlign = 'center';
                                newElement.style.direction = 'rtl';
                                newElement.style.color = '#000000';
                                blockElement.parentNode.replaceChild(newElement, blockElement);
                                
                                // وضع المؤشر داخل العنصر الجديد
                                const newRange = document.createRange();
                                newRange.selectNodeContents(newElement);
                                newRange.collapse(false);
                                selection.removeAllRanges();
                                selection.addRange(newRange);
                                updateEditorContent();
                                
                                // تحديث حالة العنوان مباشرة
                                updateHeadingDropdownState(tag);
                                headingBtn.classList.add('active');
                                updateToolbarState();
                            } else {
                                // إذا كان العنصر ليس عنواناً أو فقرة، لا نفعل شيء
                                updateToolbarState();
                            }
                        } else {
                            // إذا لم يكن هناك عنصر كتلة، لا نفعل شيء
                            updateToolbarState();
                        }
                    }
                };
                
                // تهيئة أولية للخيار
                option.setAttribute('data-active', 'false');
                
                headingDropdown.appendChild(option);
            });
            
            // إضافة الأزرار إلى شريط الأدوات
            toolbar.appendChild(boldBtn);
            toolbar.appendChild(italicBtn);
            toolbar.appendChild(underlineBtn);
            toolbar.appendChild(strikethroughBtn);
            toolbar.appendChild(separator1);
            toolbar.appendChild(alignRightBtn);
            toolbar.appendChild(alignCenterBtn);
            toolbar.appendChild(alignLeftBtn);
            toolbar.appendChild(alignJustifyBtn);
            toolbar.appendChild(separator2);
            toolbar.appendChild(directionRTLBtn);
            toolbar.appendChild(directionLTRBtn);
            toolbar.appendChild(separator3);
            toolbar.appendChild(colorBtn);
            toolbar.appendChild(colorInput);
            toolbar.appendChild(fontSizeBtn);
            toolbar.appendChild(headingBtn);
            
            // زر إضافة خط أفقي
            const horizontalRuleIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line></svg>';
            const horizontalRuleBtn = createToolbarButton('insertHorizontalRule', horizontalRuleIcon, 'إضافة خط أفقي');
            toolbar.appendChild(horizontalRuleBtn);
            
            // محرر النص (contentEditable)
            const editor = document.createElement('div');
            editor.id = 'descriptionEditor';
            editor.contentEditable = 'true';
            // حساب ارتفاع سطرين إضافيين (line-height: 1.6 * 2 = 3.2 * font-size: 15px = 48px تقريباً)
            const lineHeight = 1.6;
            const fontSize = 15;
            const twoLinesHeight = lineHeight * 2 * fontSize;
            editor.style.cssText = `width: 100%; flex: 1; min-height: ${300 + twoLinesHeight}px; max-height: ${300 + twoLinesHeight}px; padding: 15px; border: 1px solid #D1D5DB; border-top: none; border-radius: 0 0 8px 8px; font-size: ${fontSize}px; font-family: inherit; outline: none; line-height: ${lineHeight}; background: #FFFFFF; overflow-y: auto; overflow-x: hidden; direction: rtl; text-align: center; color: #000000;`;
            
            // إضافة CSS لجعل شريط التمرير على اليسار
            const scrollbarStyle = document.createElement('style');
            scrollbarStyle.textContent = `
                #descriptionEditor {
                    direction: rtl;
                }
                #descriptionEditor::-webkit-scrollbar {
                    width: 8px;
                }
                #descriptionEditor::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 4px;
                }
                #descriptionEditor::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 4px;
                }
                #descriptionEditor::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            `;
            document.head.appendChild(scrollbarStyle);
            
            // دالة للتحقق من أن المحتوى فارغ (بما في ذلك HTML فارغ) - في نطاق وضع التحرير
            function isEmptyContentEdit(content) {
                if (!content) return true;
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = content;
                const textContent = tempDiv.textContent || tempDiv.innerText || '';
                return textContent.trim() === '';
            }
            
            // تحويل HTML إلى نص عادي للعرض الأولي
            if (!isEmptyContentEdit(siteDescriptionData)) {
                editor.innerHTML = siteDescriptionData;
            } else {
                editor.innerHTML = '<p></p>';
            }
            
            // دالة لتحديث محتوى المحرر في hidden input
            function updateEditorContent() {
                const hiddenInput = document.getElementById('descriptionHiddenInput');
                if (hiddenInput) {
                    hiddenInput.value = editor.innerHTML;
                }
            }
            
            // دالة لتحديث حالة الأزرار بناءً على التحديد الحالي
            function updateToolbarState() {
                const selection = window.getSelection();
                if (selection.rangeCount === 0) {
                    // إعادة تعيين جميع الأزرار
                    boldBtn.classList.remove('active');
                    italicBtn.classList.remove('active');
                    underlineBtn.classList.remove('active');
                    strikethroughBtn.classList.remove('active');
                    colorBtn.classList.remove('active');
                    fontSizeBtn.classList.remove('active');
                    fontSizeBtn.innerHTML = '16';
                    fontSizeBtn.setAttribute('data-current-size', '16');
                    updateFontSizeDropdownState(16);
                    updateHeadingDropdownState(null);
                    return;
                }
                
                const range = selection.getRangeAt(0);
                if (range.collapsed && selection.rangeCount > 0) {
                    // إذا كان المؤشر في موضع فارغ، نبحث عن العنصر الحالي
                    let container = range.commonAncestorContainer;
                    if (container.nodeType === 3) {
                        container = container.parentElement;
                    }
                    
                    // البحث عن عنصر كتلة
                    let blockElement = container;
                    while (blockElement && blockElement !== editor) {
                        const tagName = blockElement.tagName;
                        if (tagName && ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV'].includes(tagName)) {
                            break;
                        }
                        blockElement = blockElement.parentElement;
                    }
                    
                    if (blockElement && blockElement !== editor && blockElement.tagName) {
                        updateHeadingDropdownState(blockElement.tagName.toLowerCase());
                    } else {
                        updateHeadingDropdownState(null);
                    }
                } else {
                    // إذا كان هناك نص محدد
                    let container = range.commonAncestorContainer;
                    if (container.nodeType === 3) {
                        container = container.parentElement;
                    }
                    
                    // البحث عن عنصر كتلة
                    let blockElement = container;
                    while (blockElement && blockElement !== editor) {
                        const tagName = blockElement.tagName;
                        if (tagName && ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV'].includes(tagName)) {
                            break;
                        }
                        blockElement = blockElement.parentElement;
                    }
                    
                    if (blockElement && blockElement !== editor && blockElement.tagName) {
                        updateHeadingDropdownState(blockElement.tagName.toLowerCase());
                    } else {
                        updateHeadingDropdownState(null);
                    }
                }
                
                // تحديث حالة الأزرار
                boldBtn.classList.toggle('active', document.queryCommandState('bold'));
                italicBtn.classList.toggle('active', document.queryCommandState('italic'));
                underlineBtn.classList.toggle('active', document.queryCommandState('underline'));
                strikethroughBtn.classList.toggle('active', document.queryCommandState('strikethrough'));
                
                // تحديث حالة اللون
                updateColorState();
                
                // تحديث حالة حجم الخط
                let container = range.commonAncestorContainer;
                if (container.nodeType === 3) {
                    container = container.parentElement;
                }
                
                let element = container;
                let currentSize = 16;
                let hasFontSize = false;
                
                while (element && element !== editor) {
                    if (element.style && element.style.fontSize) {
                        const sizeMatch = element.style.fontSize.match(/^(\d+)px$/);
                        if (sizeMatch) {
                            const size = parseInt(sizeMatch[1]);
                            if (size !== 15 && size !== 16 && fontSizes.includes(size)) {
                                currentSize = size;
                                hasFontSize = true;
                                break;
                            }
                        }
                    }
                    element = element.parentElement;
                }
                
                if (hasFontSize) {
                    fontSizeBtn.classList.add('active');
                    fontSizeBtn.innerHTML = currentSize.toString();
                    fontSizeBtn.setAttribute('data-current-size', currentSize.toString());
                    updateFontSizeDropdownState(currentSize);
                } else {
                    fontSizeBtn.classList.remove('active');
                    fontSizeBtn.innerHTML = '16';
                    fontSizeBtn.setAttribute('data-current-size', '16');
                    updateFontSizeDropdownState(16);
                }
            }
            
            // دالة لتحديث حالة قائمة العناوين وزر العناوين
            function updateHeadingDropdownState(currentTag) {
                const options = headingDropdown.querySelectorAll('[data-tag]');
                let hasActiveHeading = false;
                
                options.forEach(option => {
                    const tag = option.getAttribute('data-tag');
                    if (tag === currentTag) {
                        // تحديد الخيار الحالي - منع hover تماماً
                        option.style.background = '#E0F2FE';
                        option.style.color = '#0369A1';
                        option.style.fontWeight = 'bold';
                        option.style.cursor = 'default';
                        option.style.opacity = '0.7';
                        option.style.pointerEvents = 'none'; // منع hover والتفاعل تماماً
                        option.setAttribute('data-active', 'true');
                        hasActiveHeading = true;
                    } else {
                        // إعادة تعيين الخيارات الأخرى
                        option.style.background = 'transparent';
                        option.style.color = '#374151';
                        option.style.fontWeight = 'normal';
                        option.style.cursor = 'pointer';
                        option.style.opacity = '1';
                        option.style.pointerEvents = 'auto'; // السماح بالتفاعل
                        option.setAttribute('data-active', 'false');
                    }
                });
                
                // تحديث حالة زر العناوين
                if (hasActiveHeading && currentTag) {
                    headingBtn.classList.add('active');
                } else {
                    headingBtn.classList.remove('active');
                }
            }
            
            // دالة لتحديث حالة اللون
            function updateColorState() {
                const selection = window.getSelection();
                if (selection.rangeCount === 0) {
                    colorBtn.classList.remove('active');
                    colorInput.value = '#000000';
                    return;
                }
                
                const range = selection.getRangeAt(0);
                let container = range.commonAncestorContainer;
                if (container.nodeType === 3) {
                    container = container.parentElement;
                }
                
                // البحث عن اللون الحالي في العناصر المحددة
                let element = container;
                let currentColor = '#000000';
                let hasColor = false;
                
                // البحث في العنصر الحالي وأولاده
                const checkColor = function(el) {
                    if (!el || el === editor) return null;
                    
                    // التحقق من وجود span أو font مع color
                    if (el.tagName === 'SPAN' || el.tagName === 'FONT') {
                        const color = el.style.color || el.getAttribute('color');
                        if (color) {
                            // تحويل إلى hex إذا لزم الأمر
                            if (color.startsWith('#')) {
                                return color;
                            } else if (color.startsWith('rgb')) {
                                const rgbMatch = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                                if (rgbMatch) {
                                    return '#' + rgbMatch.slice(1).map(x => {
                                        const hex = parseInt(x).toString(16);
                                        return hex.length === 1 ? '0' + hex : hex;
                                    }).join('');
                                }
                            }
                        }
                    }
                    
                    // التحقق من computed style
                    const computedColor = window.getComputedStyle(el).color;
                    if (computedColor) {
                        const rgbMatch = computedColor.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                        if (rgbMatch) {
                            const r = parseInt(rgbMatch[1]);
                            const g = parseInt(rgbMatch[2]);
                            const b = parseInt(rgbMatch[3]);
                            // إذا لم يكن أسود
                            if (r !== 0 || g !== 0 || b !== 0) {
                                return '#' + [r, g, b].map(x => {
                                    const hex = x.toString(16);
                                    return hex.length === 1 ? '0' + hex : hex;
                                }).join('');
                            }
                        }
                    }
                    
                    return null;
                };
                
                // البحث في العنصر الحالي
                let foundColor = checkColor(element);
                if (foundColor) {
                    currentColor = foundColor;
                    hasColor = true;
                } else {
                    // البحث في العناصر الأب
                    while (element && element !== editor) {
                        foundColor = checkColor(element);
                        if (foundColor) {
                            currentColor = foundColor;
                            hasColor = true;
                            break;
                        }
                        element = element.parentElement;
                    }
                }
                
                if (hasColor && currentColor !== '#000000') {
                    colorBtn.classList.add('active');
                    colorInput.value = currentColor;
                } else {
                    colorBtn.classList.remove('active');
                    colorInput.value = '#000000';
                }
            }
            
            // إضافة hidden input لحفظ HTML
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.id = 'descriptionHiddenInput';
            hiddenInput.name = 'description';
            hiddenInput.value = editor.innerHTML;
            
            // Event listeners للأزرار
            [boldBtn, italicBtn, underlineBtn, strikethroughBtn].forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const command = this.getAttribute('data-command');
                    document.execCommand(command, false, null);
                    updateEditorContent();
                    updateToolbarState();
                });
            });
            
            [alignRightBtn, alignCenterBtn, alignLeftBtn, alignJustifyBtn].forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const command = this.getAttribute('data-command');
                    document.execCommand(command, false, null);
                    updateEditorContent();
                });
            });
            
            directionRTLBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    let container = range.commonAncestorContainer;
                    if (container.nodeType === 3) {
                        container = container.parentElement;
                    }
                    
                    // البحث عن عنصر كتلة
                    let blockElement = container;
                    while (blockElement && blockElement !== editor) {
                        const tagName = blockElement.tagName;
                        if (tagName && ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'SPAN'].includes(tagName)) {
                            break;
                        }
                        blockElement = blockElement.parentElement;
                    }
                    
                    if (blockElement && blockElement !== editor) {
                        blockElement.style.direction = 'rtl';
                        blockElement.style.textAlign = 'right';
                    } else {
                        // إذا لم يكن هناك عنصر، استخدم execCommand
                        document.execCommand('styleWithCSS', false, null);
                        document.execCommand('dirRTL', false, null);
                    }
                } else {
                    document.execCommand('styleWithCSS', false, null);
                    document.execCommand('dirRTL', false, null);
                }
                updateEditorContent();
                updateToolbarState();
            });
            
            directionLTRBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    let container = range.commonAncestorContainer;
                    if (container.nodeType === 3) {
                        container = container.parentElement;
                    }
                    
                    // البحث عن عنصر كتلة
                    let blockElement = container;
                    while (blockElement && blockElement !== editor) {
                        const tagName = blockElement.tagName;
                        if (tagName && ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV', 'SPAN'].includes(tagName)) {
                            break;
                        }
                        blockElement = blockElement.parentElement;
                    }
                    
                    if (blockElement && blockElement !== editor) {
                        blockElement.style.direction = 'ltr';
                        blockElement.style.textAlign = 'left';
                    } else {
                        // إذا لم يكن هناك عنصر، استخدم execCommand
                        document.execCommand('styleWithCSS', false, null);
                        document.execCommand('dirLTR', false, null);
                    }
                } else {
                    document.execCommand('styleWithCSS', false, null);
                    document.execCommand('dirLTR', false, null);
                }
                updateEditorContent();
                updateToolbarState();
            });
            
            // علبة الألوان القابلة للتحريك
            const colorPickerContainer = document.createElement('div');
            colorPickerContainer.id = 'colorPickerContainer';
            colorPickerContainer.style.cssText = 'display: none; position: fixed; background: white; border: 1px solid #D1D5DB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 16px; z-index: 1000002; cursor: move; min-width: 280px;';
            
            const colorPickerHeader = document.createElement('div');
            colorPickerHeader.style.cssText = 'padding: 10px 12px; background: #F9FAFB; border-bottom: 1px solid #E5E7EB; margin: -16px -16px 16px -16px; border-radius: 8px 8px 0 0; cursor: move; font-size: 14px; font-weight: 600; color: #374151; display: flex; align-items: center; justify-content: space-between;';
            
            const headerTitle = document.createElement('span');
            headerTitle.textContent = 'اختر اللون';
            
            const closeColorPickerBtn = document.createElement('button');
            closeColorPickerBtn.type = 'button';
            closeColorPickerBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            closeColorPickerBtn.style.cssText = 'background: none; border: none; cursor: pointer; padding: 4px; color: #6B7280; border-radius: 4px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px;';
            closeColorPickerBtn.onmouseover = function() { this.style.background = '#E5E7EB'; this.style.color = '#374151'; };
            closeColorPickerBtn.onmouseout = function() { this.style.background = 'none'; this.style.color = '#6B7280'; };
            closeColorPickerBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                colorPickerContainer.style.display = 'none';
            };
            
            colorPickerHeader.appendChild(headerTitle);
            colorPickerHeader.appendChild(closeColorPickerBtn);
            
            const colorInputWrapper = document.createElement('div');
            colorInputWrapper.style.cssText = 'position: relative;';
            colorInput.style.cssText = 'width: 100%; height: 60px; border: 1px solid #D1D5DB; border-radius: 6px; cursor: pointer;';
            colorInput.value = '#000000';
            
            colorInputWrapper.appendChild(colorInput);
            colorPickerContainer.appendChild(colorPickerHeader);
            colorPickerContainer.appendChild(colorInputWrapper);
            document.body.appendChild(colorPickerContainer);
            
            // جعل علبة الألوان قابلة للتحريك
            let isDragging = false;
            let currentX, currentY, initialX, initialY;
            
            colorPickerHeader.addEventListener('mousedown', function(e) {
                // منع السحب عند النقر على زر الإغلاق
                if (e.target === closeColorPickerBtn || closeColorPickerBtn.contains(e.target)) {
                    return;
                }
                isDragging = true;
                initialX = e.clientX - colorPickerContainer.offsetLeft;
                initialY = e.clientY - colorPickerContainer.offsetTop;
            });
            
            document.addEventListener('mousemove', function(e) {
                if (isDragging) {
                    e.preventDefault();
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;
                    colorPickerContainer.style.left = currentX + 'px';
                    colorPickerContainer.style.top = currentY + 'px';
                }
            });
            
            document.addEventListener('mouseup', function() {
                isDragging = false;
            });
            
            colorBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const rect = this.getBoundingClientRect();
                if (colorPickerContainer.style.display === 'none') {
                    // تحديث اللون الحالي قبل فتح العلبة
                    updateColorState();
                    colorPickerContainer.style.display = 'block';
                    colorPickerContainer.style.left = (rect.left + rect.width / 2 - 140) + 'px';
                    colorPickerContainer.style.top = (rect.bottom + 10) + 'px';
                } else {
                    colorPickerContainer.style.display = 'none';
                    // إعادة تعيين حالة الزر عند الإغلاق
                    updateColorState();
                }
            });
            
            colorInput.addEventListener('change', function() {
                document.execCommand('foreColor', false, this.value);
                updateEditorContent();
                updateToolbarState();
            });
            
            // إغلاق علبة الألوان عند النقر خارجها
            document.addEventListener('click', function(e) {
                if (!colorBtn.contains(e.target) && !colorPickerContainer.contains(e.target)) {
                    colorPickerContainer.style.display = 'none';
                    // تحديث حالة الزر عند الإغلاق
                    updateColorState();
                }
                // إغلاق قائمة حجم الخط
                if (fontSizeDropdown && fontSizeDropdown.style.display === 'block') {
                    if (!fontSizeBtn.contains(e.target) && !fontSizeDropdown.contains(e.target)) {
                        fontSizeDropdown.style.display = 'none';
                    }
                }
            });
            
            // دالة لإغلاق قائمة حجم الخط
            function closeFontSizeDropdown() {
                if (fontSizeDropdown) {
                    fontSizeDropdown.style.display = 'none';
                }
            }
            
            fontSizeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const rect = this.getBoundingClientRect();
                const isVisible = fontSizeDropdown.style.display === 'block';
                
                // إغلاق قائمة العناوين أولاً
                closeHeadingDropdown();
                
                if (isVisible) {
                    closeFontSizeDropdown();
                } else {
                    // تحديث حالة القائمة قبل فتحها
                    updateToolbarState();
                    fontSizeDropdown.style.display = 'block';
                    fontSizeDropdown.style.top = (rect.bottom + 4) + 'px';
                    fontSizeDropdown.style.left = rect.left + 'px';
                    
                    // التأكد من أن القائمة مرئية
                    setTimeout(function() {
                        if (fontSizeDropdown.style.display === 'block') {
                            const dropdownRect = fontSizeDropdown.getBoundingClientRect();
                            if (dropdownRect.right > window.innerWidth) {
                                fontSizeDropdown.style.left = (rect.right - dropdownRect.width) + 'px';
                            }
                            if (dropdownRect.bottom > window.innerHeight) {
                                fontSizeDropdown.style.top = (rect.top - dropdownRect.height - 4) + 'px';
                            }
                        }
                    }, 10);
                }
            });
            
            
            headingBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const rect = this.getBoundingClientRect();
                const currentDisplay = headingDropdown.style.display;
                const isVisible = currentDisplay === 'block';
                
                if (isVisible) {
                    headingDropdown.style.display = 'none';
                } else {
                    // تحديث حالة القائمة قبل فتحها
                    updateToolbarState();
                    headingDropdown.style.display = 'block';
                    headingDropdown.style.top = (rect.bottom + 4) + 'px';
                    headingDropdown.style.left = rect.left + 'px';
                    
                    // التأكد من أن القائمة مرئية
                    setTimeout(function() {
                        if (headingDropdown.style.display === 'block') {
                            const dropdownRect = headingDropdown.getBoundingClientRect();
                            // إذا كانت القائمة خارج الشاشة، اضبط موضعها
                            if (dropdownRect.right > window.innerWidth) {
                                headingDropdown.style.left = (rect.right - dropdownRect.width) + 'px';
                            }
                            if (dropdownRect.bottom > window.innerHeight) {
                                headingDropdown.style.top = (rect.top - dropdownRect.height - 4) + 'px';
                            }
                        }
                    }, 10);
                }
            });
            
            // تحديث المحتوى عند التعديل
            editor.addEventListener('input', function() {
                updateEditorContent();
                updateToolbarState();
            });
            editor.addEventListener('keyup', function(e) {
                updateEditorContent();
                updateToolbarState();
                
                // عند الضغط على Enter، إعادة تعيين الخصائص
                if (e.key === 'Enter') {
                    setTimeout(function() {
                        // التحقق من أن المؤشر في سطر جديد
                        const selection = window.getSelection();
                        if (selection.rangeCount > 0) {
                            const range = selection.getRangeAt(0);
                            const container = range.commonAncestorContainer;
                            if (container.nodeType === 3) {
                                const parent = container.parentElement;
                                // إذا كان العنصر الجديد عنصر كتلة جديد (P, DIV, etc.)
                                if (parent && parent !== editor && ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6'].includes(parent.tagName)) {
                                    // إعادة تعيين جميع الخصائص
                                    boldBtn.classList.remove('active');
                                    italicBtn.classList.remove('active');
                                    underlineBtn.classList.remove('active');
                                    strikethroughBtn.classList.remove('active');
                                    colorBtn.classList.remove('active');
                                    colorInput.value = '#000000';
                                    updateHeadingDropdownState(null);
                                    headingBtn.classList.remove('active');
                                }
                            }
                        }
                        updateToolbarState();
                    }, 10);
                }
            });
            editor.addEventListener('mouseup', function() {
                updateEditorContent();
                updateToolbarState();
            });
            editor.addEventListener('selectionchange', function() {
                updateToolbarState();
            });
            
            // إضافة event listener للتحديد
            document.addEventListener('selectionchange', function() {
                if (window.getSelection().rangeCount > 0) {
                    const anchorNode = window.getSelection().anchorNode;
                    if (anchorNode && editor.contains(anchorNode)) {
                        updateToolbarState();
                    }
                }
            });
            
            // دالة لإغلاق قائمة العناوين
            function closeHeadingDropdown() {
                if (headingDropdown) {
                    headingDropdown.style.display = 'none';
                }
            }
            
            // إخفاء القوائم المنسدلة عند الضغط في أي موضع - استخدام capture phase
            document.addEventListener('click', function(e) {
                // إغلاق قائمة العناوين
                if (headingDropdown && headingDropdown.style.display === 'block') {
                    if (!headingBtn.contains(e.target) && !headingDropdown.contains(e.target)) {
                        closeHeadingDropdown();
                    }
                }
            }, true); // استخدام capture phase
            
            // إضافة event listener إضافي على document.body للضمان
            if (document.body) {
                document.body.addEventListener('click', function(e) {
                    // إغلاق قائمة العناوين
                    if (headingDropdown && headingDropdown.style.display === 'block') {
                        if (!headingBtn.contains(e.target) && !headingDropdown.contains(e.target)) {
                            closeHeadingDropdown();
                        }
                    }
                });
            }
            
            // إضافة event listener على mousedown أيضاً للضمان
            document.addEventListener('mousedown', function(e) {
                // إغلاق قائمة العناوين
                if (headingDropdown && headingDropdown.style.display === 'block') {
                    if (!headingBtn.contains(e.target) && !headingDropdown.contains(e.target)) {
                        closeHeadingDropdown();
                    }
                }
            });
            
            // إضافة event listener على focus للضمان
            window.addEventListener('focus', function() {
                // إغلاق القوائم عند فقدان focus
                setTimeout(function() {
                    if (headingDropdown && headingDropdown.style.display === 'block') {
                        const activeElement = document.activeElement;
                        if (!headingBtn.contains(activeElement) && !headingDropdown.contains(activeElement)) {
                            closeHeadingDropdown();
                        }
                    }
                }, 100);
            });
            
            // إضافة event listener لزر الخط الأفقي بعد إنشاء editor
            horizontalRuleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const selection = window.getSelection();
                const currentColor = colorInput.value || '#000000';
                
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const hr = document.createElement('hr');
                    hr.style.cssText = `border: none; border-top: 1px solid ${currentColor}; margin: 10px 0; width: 100%; cursor: pointer;`;
                    range.insertNode(hr);
                    range.setStartAfter(hr);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                } else {
                    document.execCommand('insertHorizontalRule', false, null);
                    const hrs = editor.querySelectorAll('hr');
                    if (hrs.length > 0) {
                        const lastHr = hrs[hrs.length - 1];
                        lastHr.style.cssText = `border: none; border-top: 1px solid ${currentColor}; margin: 10px 0; width: 100%; cursor: pointer;`;
                    }
                }
                updateEditorContent();
            });
            
            // إضافة event listener لتحديد الخط الأفقي وتغيير لونه
            editor.addEventListener('click', function(e) {
                if (e.target.tagName === 'HR') {
                    const hr = e.target;
                    hr.classList.add('selected');
                    editor.querySelectorAll('hr').forEach(h => {
                        if (h !== hr) h.classList.remove('selected');
                    });
                }
            });
            
            // عند النقر المزدوج على الخط، فتح محدد الألوان
            editor.addEventListener('dblclick', function(e) {
                if (e.target.tagName === 'HR') {
                    const hr = e.target;
                    const colorInput = document.getElementById('textColorPicker');
                    if (colorInput) {
                        const currentColor = window.getComputedStyle(hr).borderTopColor;
                        const rgbToHex = (rgb) => {
                            const match = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                            if (!match) return '#000000';
                            return '#' + match.slice(1).map(x => {
                                const hex = parseInt(x).toString(16);
                                return hex.length === 1 ? '0' + hex : hex;
                            }).join('');
                        };
                        colorInput.value = rgbToHex(currentColor);
                        const colorPickerContainer = document.getElementById('colorPickerContainer');
                        if (colorPickerContainer) {
                            const rect = hr.getBoundingClientRect();
                            colorPickerContainer.style.display = 'block';
                            colorPickerContainer.style.left = (rect.left + rect.width / 2 - 140) + 'px';
                            colorPickerContainer.style.top = (rect.bottom + 10) + 'px';
                        }
                        colorInput.addEventListener('change', function() {
                            hr.style.borderTopColor = this.value;
                            colorInput.value = this.value;
                            updateEditorContent();
                            updateToolbarState();
                        }, { once: true });
                    }
                }
            });
            
            // منع إغلاق القائمة عند النقر على الخيارات فقط، وليس على القائمة نفسها
            // (سيتم إغلاق القائمة تلقائياً عند النقر على خيار)
            
            // Buttons container
            const buttonsContainer = document.createElement('div');
            buttonsContainer.style.cssText = 'display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;';
            
            // زر حفظ التغييرات
            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.id = 'saveDescriptionBtn';
            saveBtn.textContent = 'حفظ التغييرات';
            saveBtn.className = 'btn btn-primary';
            saveBtn.style.cssText = 'padding: 12px 24px; background: #000000; color: #FFFFFF; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;';
            saveBtn.onmouseover = function() { this.style.background = '#333333'; };
            saveBtn.onmouseout = function() { this.style.background = '#000000'; };
            
            buttonsContainer.appendChild(saveBtn);
            
            editForm.appendChild(toolbar);
            editForm.appendChild(editor);
            editForm.appendChild(hiddenInput);
            editForm.appendChild(buttonsContainer);
            body.appendChild(editForm);
            
            // إضافة القوائم المنسدلة إلى body
            document.body.appendChild(fontSizeDropdown);
            document.body.appendChild(headingDropdown);
            
            // تعديل body style ليكون مناسباً للفورم
            body.style.cssText = 'padding: 25px; overflow-y: visible; flex: 1; display: flex; flex-direction: column;';
        }
        
        content.appendChild(header);
        content.appendChild(body);
        overlay.appendChild(content);
        
        document.body.appendChild(overlay);
        
        // منع إغلاق النافذة عند النقر خارجها
        overlay.addEventListener('click', function(e) {
            e.stopPropagation();
            if (e.target === overlay) {
                const searchInput = document.getElementById('userSearchInputModal');
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            }
        });
        
        // منع focus على input عند النقر على content
        content.addEventListener('click', function(e) {
            if (e.target === content || e.target === body) {
                const searchInput = document.getElementById('userSearchInputModal');
                if (searchInput && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            }
        });
        
        descriptionModal = overlay;
        
        // إضافة event listener لزر حفظ التغييرات (إذا كان في وضع التحرير)
        if (mode === 'edit') {
            const saveBtn = document.getElementById('saveDescriptionBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    const hiddenInput = document.getElementById('descriptionHiddenInput');
                    const editor = document.getElementById('descriptionEditor');
                    if (!hiddenInput || !editor) return;
                    
                    // تحديث hidden input قبل الحفظ
                    hiddenInput.value = editor.innerHTML;
                    const newDescription = hiddenInput.value;
                    const btnText = saveBtn.textContent;
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'جار الحفظ';
                    
                    fetch('save_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json; charset=utf-8' },
                        body: JSON.stringify({
                            site_description: newDescription
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }
                        return response.text();
                    })
                    .then(text => {
                        console.log('الاستجابة الخام من الخادم:', text);
                        console.log('طول الاستجابة:', text.length);
                        console.log('أول 200 حرف:', text.substring(0, 200));
                        console.log('آخر 200 حرف:', text.substring(Math.max(0, text.length - 200)));
                        
                        // تنظيف النص من أي محتوى إضافي
                        text = text.trim();
                        
                        // إزالة أي محتوى قبل أو بعد JSON
                        const jsonMatch = text.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            text = jsonMatch[0];
                            console.log('JSON المستخرج:', text.substring(0, 200));
                        } else {
                            console.error('لم يتم العثور على JSON في الاستجابة');
                        }
                        
                        try {
                            const result = JSON.parse(text);
                            console.log('JSON تم تحليله بنجاح:', result);
                            if (result.success) {
                                showSaveMessage(result.success, result.message || 'تم حفظ وصف الموقع بنجاح');
                                // تحديث البيانات المحلية
                                if (typeof siteDescriptionData !== 'undefined') {
                                    siteDescriptionData = newDescription;
                                }
                                // إغلاق النافذة بعد الحفظ
                                setTimeout(() => {
                                    closeDescriptionModal();
                                }, 1000);
                            } else {
                                showSaveMessage(result.success, result.message || 'حدث خطأ أثناء حفظ وصف الموقع');
                            }
                        } catch (e) {
                            console.error('خطأ في تحليل JSON:', e);
                            console.error('اسم الخطأ:', e.name);
                            console.error('رسالة الخطأ:', e.message);
                            console.error('النص الكامل المستلم:', text);
                            console.error('طول النص:', text.length);
                            showSaveMessage(false, 'حدث خطأ في معالجة الاستجابة من الخادم. تحقق من Console للمزيد من التفاصيل.');
                        }
                    })
                    .catch(error => {
                        console.error('خطأ في الحفظ:', error);
                        showSaveMessage(false, 'حدث خطأ في الاتصال بالخادم: ' + error.message);
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = btnText;
                    });
                });
            }
        }
        
        console.log('createDescriptionModal returning overlay:', overlay);
        return overlay;
    }
    
    // فتح نافذة وصف الموقع
    function openDescriptionModal(mode) {
        console.log('openDescriptionModal called with mode:', mode);
        try {
            const modal = createDescriptionModal(mode);
            console.log('Modal created:', modal);
            if (modal) {
                // التأكد من أن overlay موجود في document.body
                if (!document.body.contains(modal)) {
                    document.body.appendChild(modal);
                }
                modal.style.display = 'flex';
                descriptionModal = modal;
                console.log('Modal displayed, descriptionModal set to:', descriptionModal);
                
                // منع قائمة الزر الأيمن للفأرة عند فتح النافذة
                document.addEventListener('contextmenu', preventContextMenu, true);
            } else {
                console.error('Modal is null');
            }
        } catch (error) {
            console.error('Error in openDescriptionModal:', error);
            console.error('Stack trace:', error.stack);
        }
    }
    
    // إغلاق نافذة وصف الموقع
    function closeDescriptionModal() {
        if (descriptionModal) {
            descriptionModal.style.display = 'none';
        }
        
        // إعادة تفعيل قائمة الزر الأيمن عند إغلاق النافذة
        document.removeEventListener('contextmenu', preventContextMenu, true);
    }
    
    // تبديل بين وضع القراءة والتحرير لوصف الموقع
    if (readDescriptionBtn && editDescriptionBtn) {
        readDescriptionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openDescriptionModal('read');
        });

        editDescriptionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('تم الضغط على زر تحرير النص');
            try {
                openDescriptionModal('edit');
                console.log('تم استدعاء openDescriptionModal');
            } catch (error) {
                console.error('خطأ في فتح نافذة التحرير:', error);
            }
        });
    }

    // حفظ الإعدادات العامة
    if (saveGeneralSettingsBtn) {
        console.log('تم العثور على زر الحفظ');
        saveGeneralSettingsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('تم الضغط على زر الحفظ');
            
            const btnText = saveGeneralSettingsBtn.textContent;
            saveGeneralSettingsBtn.disabled = true;
            saveGeneralSettingsBtn.textContent = 'جار الحفظ';

            if (!siteNameInput || !siteEmailInput) {
                console.error('عناصر الإدخال غير موجودة');
                saveGeneralSettingsBtn.disabled = false;
                saveGeneralSettingsBtn.textContent = btnText;
                return;
            }

            // الحصول على قيمة وضع الصيانة
            const maintenanceModeInput = document.querySelector('input[name="maintenance_mode"]:checked');
            const maintenanceMode = maintenanceModeInput ? maintenanceModeInput.value : 'open';

            // الحصول على الأعضاء المصرح لهم
            const allowedUsersInput = document.getElementById('allowedUsersInput');
            let allowedUsers = [];
            if (allowedUsersInput && allowedUsersInput.value) {
                try {
                    allowedUsers = JSON.parse(allowedUsersInput.value);
                } catch (e) {
                    allowedUsers = [];
                }
            }

            const data = {
                site_name: siteNameInput.value.trim(),
                site_email: siteEmailInput.value.trim(),
                maintenance_mode: maintenanceMode,
                maintenance_allowed_users: allowedUsers
            };

            console.log('إرسال البيانات:', data);

            fetch('save_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('استجابة الخادم:', response);
                if (!response.ok) {
                    throw new Error('خطأ في الاستجابة: ' + response.status);
                }
                return response.json();
            })
            .then(result => {
                console.log('نتيجة الحفظ:', result);
                showSaveMessage(result.success, result.message || (result.success ? 'تم حفظ الإعدادات بنجاح' : 'حدث خطأ أثناء حفظ الإعدادات'));
            })
            .catch(error => {
                console.error('خطأ في الحفظ:', error);
                showSaveMessage(false, 'حدث خطأ في الاتصال بالخادم');
            })
            .finally(() => {
                saveGeneralSettingsBtn.disabled = false;
                saveGeneralSettingsBtn.textContent = btnText;
            });
        });
    }
})();
</script>

