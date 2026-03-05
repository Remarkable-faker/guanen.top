<?php
/**
 * 用户注册页面
 * 
 * 处理新用户注册，包括基础信息、详细信息、兴趣爱好以及账户安全设置。
 * 采用了逻辑与视图分离的设计模式，并确保 PHP 5.6 兼容性。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 如果已登录，跳转到用户中心
if (isset($_SESSION['user_id'])) {
    header("Location: user_dashboard.php");
    exit();
}

// 初始化变量
$error = '';
$success = '';

// 获取已提交的值，用于回填
$username_val = isset($_POST['username']) ? trim($_POST['username']) : '';
$email_val = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone_val = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$gender_val = isset($_POST['gender']) ? $_POST['gender'] : '';
$birthdate_val = isset($_POST['birthdate']) ? $_POST['birthdate'] : '';
$selected_hobbies = isset($_POST['hobbies']) ? $_POST['hobbies'] : array();
$motto_val = isset($_POST['motto']) ? trim($_POST['motto']) : '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // 验证输入
    if (empty($username_val) || empty($email_val) || empty($password) || empty($phone_val)) {
        $error = '带*的字段必须填写';
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (strlen($password) < 6) {
        $error = '密码至少需要6位';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]{2,20}$/u', $username_val)) {
        $error = '用户名只能包含中文、字母、数字和下划线（2-20位）';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone_val)) {
        $error = '请输入有效的中国大陆手机号';
    } elseif (!empty($birthdate_val) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate_val)) {
        $error = '出生日期格式不正确';
    } else {
        // 检查用户名、邮箱或手机号是否已存在 (统一使用核心 users 表)
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?");
            $stmt->bind_param("sss", $username_val, $email_val, $phone_val);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = '用户名、邮箱或手机号已被注册';
            } else {
                // 处理爱好数组（转换为字符串）
                $hobbies_str = !empty($selected_hobbies) ? implode(',', $selected_hobbies) : '';
                
                // 加密密码
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // 插入新用户 (统一使用核心 users 表)
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, phone, gender, birthdate, hobbies, motto, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("ssssssss", $username_val, $email_val, $password_hash, $phone_val, $gender_val, $birthdate_val, $hobbies_str, $motto_val);
                
                if ($stmt->execute()) {
                    $success = '注册成功！正在为您跳转...';
                    $user_id = $stmt->insert_id;
                    
                    // 同时也尝试同步到 bc_users (兼容旧功能)
                    $conn->query("INSERT IGNORE INTO bc_users (id, username, email, password, phone, gender, birthdate, hobbies, motto) 
                                  VALUES ($user_id, '$username_val', '$email_val', '$password_hash', '$phone_val', '$gender_val', '$birthdate_val', '$hobbies_str', '$motto_val')");
                    
                    // 自动登录
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username_val;
                    $_SESSION['is_logged_in'] = true;
                    
                    // 检查是否是第一个用户并自动设为管理员
                    $res = $conn->query("SELECT MIN(id) as first_id FROM users");
                    if ($res) {
                        $first_user = $res->fetch_assoc();
                        if ($user_id == $first_user['first_id']) {
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $user_id;
                            $_SESSION['is_admin'] = true;
                            $_SESSION['admin_user_id'] = $user_id;
                            $_SESSION['admin_username'] = $username_val;
                            
                            $admin_stmt = $conn->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                            $admin_stmt->bind_param("i", $user_id);
                            $admin_stmt->execute();
                            $admin_stmt->close();
                            
                            // 同时也更新 bc_users
                            $conn->query("UPDATE bc_users SET is_admin = 1 WHERE id = $user_id");
                        }
                    }
                    
                    // 设置跳转脚本
                    $extra_js_footer = '<script>setTimeout(function() { window.location.href = "user_dashboard.php?from=register"; }, 2000);</script>';
                } else {
                    $error_msg = $stmt->error;
                    if (strpos($error_msg, 'phone') !== false) {
                        $error = '手机号已被使用';
                    } elseif (strpos($error_msg, 'email') !== false) {
                        $error = '邮箱已被使用';
                    } elseif (strpos($error_msg, 'username') !== false) {
                        $error = '用户名已被使用';
                    } else {
                        $error = '注册失败，请稍后重试';
                    }
                    error_log("注册失败 - 用户: $username_val, 错误: $error_msg");
                }
            }
            $stmt->close();
        }
    }

// --- 视图部分 ---

$page_title = '用户注册';
$hide_header = true;
$hide_footer = true;
$full_width = true;

// 注入样式
$extra_css = '
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.42);
        --glass-border: rgba(255, 255, 255, 0.55);
        --text-color: #1e293b;
        --primary: #3b82f6;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    body {
        min-height: 100vh;
        background: linear-gradient(
            135deg,
            #f7d9c4,
            #f3c6c6,
            #f6e2b3,
            #f2d7e8,
            #fdebd0
        );
        background-size: 400% 400%;
        animation: gradientFlow 18s ease infinite;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
    }

    @keyframes gradientFlow {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .frosted-layer {
        position: fixed;
        inset: 0;
        backdrop-filter: blur(32px) saturate(135%) brightness(1.05);
        -webkit-backdrop-filter: blur(32px) saturate(135%) brightness(1.05);
        z-index: 0;
    }

    .register-container {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        padding: 48px 40px;
        width: 100%;
        max-width: 680px;
        position: relative;
        z-index: 10;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        margin: auto;
    }
    
    .logo-text {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }
    
    .tagline {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 40px;
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 24px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--glass-border);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .section-title::before {
        content: "";
        width: 4px;
        height: 18px;
        background: #3b82f6;
        border-radius: 2px;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        color: #475569;
        font-weight: 600;
        font-size: 14px;
    }
    
    label.required::after {
        content: "*";
        color: #ef4444;
        margin-left: 4px;
    }
    
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--glass-border);
        border-radius: 14px;
        font-size: 15px;
        transition: var(--transition);
        background: rgba(255, 255, 255, 0.4);
        color: #1e293b;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.7);
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    
    .hobby-label, .gender-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 14px;
        background: rgba(255, 255, 255, 0.3);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        font-size: 13px;
        color: #475569;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
    }
    
    .hobby-checkbox:checked + .hobby-label, .gender-radio:checked + .gender-label {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
    }
    
    .btn-register {
        width: 100%;
        padding: 16px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 16px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        margin-top: 32px;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    
    .btn-register:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(59, 130, 246, 0.3);
    }
    
    .msg-box {
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 24px;
        text-align: center;
        font-weight: 600;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        font-size: 14px;
    }
    
    .msg-error { background: rgba(254, 226, 226, 0.6); color: #dc2626; }
    .msg-success { background: rgba(220, 252, 231, 0.6); color: #16a34a; }
    
    .login-link-box {
        text-align: center;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--glass-border);
        color: #64748b;
        font-size: 14px;
    }
    
    .login-link-box a {
        color: #3b82f6;
        font-weight: 700;
        text-decoration: none;
    }

    .login-link-box a:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .register-container { padding: 40px 24px; margin: 16px; }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 480px) {
        .logo-text { font-size: 24px; }
        .grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
        .section-title { font-size: 16px; }
    }
</style>
';

// 注入脚本
$extra_js = '
<script>
    // 验证用户名
    function validateUsername() {
        const username = document.getElementById("username").value;
        const hint = document.getElementById("usernameHint");
        
        if (username.length === 0) {
            hint.textContent = "";
            return false;
        } else if (username.length < 2 || username.length > 20) {
            hint.textContent = "长度需在2-20位之间";
            hint.style.color = "#ef4444";
            return false;
        } else if (!/^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username)) {
            hint.textContent = "仅限中文、字母、数字和下划线";
            hint.style.color = "#ef4444";
            return false;
        } else {
            hint.textContent = "用户名格式正确";
            hint.style.color = "#2d8a4e";
            return true;
        }
    }

    // 检查密码强度
    function checkStrength() {
        const password = document.getElementById("password").value;
        const bar = document.getElementById("strengthBar");
        
        let strength = 0;
        if (password.length >= 6) strength += 20;
        if (password.length >= 10) strength += 20;
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[0-9]/.test(password)) strength += 20;
        if (/[^A-Za-z0-9]/.test(password)) strength += 20;
        
        bar.style.width = strength + "%";
        
        if (strength < 40) bar.style.background = "#ef4444";
        else if (strength < 70) bar.style.background = "#f59e0b";
        else bar.style.background = "#10b981";
    }

    // 表单提交验证
    function validateForm() {
        const password = document.getElementById("password").value;
        const confirm = document.getElementById("confirm_password").value;
        const phone = document.getElementById("phone").value;
        
        if (!validateUsername()) {
            document.getElementById("username").focus();
            return false;
        }
        
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            alert("请输入有效的手机号");
            document.getElementById("phone").focus();
            return false;
        }
        
        if (password.length < 6) {
            alert("密码至少需要6位");
            document.getElementById("password").focus();
            return false;
        }
        
        if (password !== confirm) {
            alert("两次输入的密码不一致");
            document.getElementById("confirm_password").focus();
            return false;
        }
        
        return true;
    }
</script>
' . (isset($extra_js_footer) ? $extra_js_footer : '');

// 开始捕获内容
ob_start();
?>

<div class="frosted-layer"></div>

<div class="register-container">
    <div class="text-center mb-8">
        <div class="logo-text">
            <a href="../index.php">Guanen.top</a>
        </div>
        <div class="tagline">山高路远，我们花开处见</div>
    </div>
    
    <h2 class="text-center text-2xl font-semibold mb-8 text-gray-700 tracking-widest">创建账户</h2>
    
    <?php if (!empty($error)): ?>
        <div class="msg-box msg-error">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="msg-box msg-success">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="registerForm" onsubmit="return validateForm()">
        <!-- 基础信息部分 -->
        <div class="mb-8">
            <div class="section-title">基础信息</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="required">用户名</label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input"
                            placeholder="2-20位字符"
                            value="<?php echo htmlspecialchars($username_val); ?>"
                            required
                            oninput="validateUsername()"
                        >
                        <i class="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 opacity-40"></i>
                    </div>
                    <div id="usernameHint" class="text-xs mt-1 opacity-60"></div>
                </div>
                
                <div>
                    <label for="email" class="required">邮箱地址</label>
                    <div class="relative">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input"
                            placeholder="example@email.com"
                            value="<?php echo htmlspecialchars($email_val); ?>"
                            required
                        >
                        <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 更多信息部分 -->
        <div class="mb-8">
            <div class="section-title">更多信息</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="phone" class="required">手机号码</label>
                    <div class="relative">
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder="11位手机号" required value="<?php echo htmlspecialchars($phone_val); ?>">
                        <i class="fas fa-mobile-alt absolute right-4 top-1/2 -translate-y-1/2 opacity-40"></i>
                    </div>
                </div>
                <div>
                    <label for="birthdate">出生日期</label>
                    <input type="date" id="birthdate" name="birthdate" class="form-input" value="<?php echo htmlspecialchars($birthdate_val); ?>">
                </div>
            </div>
            
            <div class="mt-6">
                <label>性别</label>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <input type="radio" id="male" name="gender" value="male" class="gender-radio hidden" <?php echo ($gender_val == 'male') ? 'checked' : ''; ?>>
                        <label for="male" class="gender-label w-full text-center"><i class="fas fa-mars mr-2"></i>男</label>
                    </div>
                    <div class="flex-1">
                        <input type="radio" id="female" name="gender" value="female" class="gender-radio hidden" <?php echo ($gender_val == 'female') ? 'checked' : ''; ?>>
                        <label for="female" class="gender-label w-full text-center"><i class="fas fa-venus mr-2"></i>女</label>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <label>兴趣爱好</label>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                    <?php 
                    $hobby_list = array('阅读', '音乐', '电影', '运动', '旅游', '美食', '游戏', '摄影', '编程', '绘画', '写作', '手工');
                    foreach ($hobby_list as $hobby): 
                    ?>
                    <div class="relative">
                        <input type="checkbox" name="hobbies[]" value="<?php echo $hobby; ?>" id="hobby-<?php echo $hobby; ?>" class="hobby-checkbox hidden" <?php echo in_array($hobby, $selected_hobbies) ? 'checked' : ''; ?>>
                        <label for="hobby-<?php echo $hobby; ?>" class="hobby-label w-full text-center text-xs px-2"><?php echo $hobby; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-6">
                <label for="motto">个性签名</label>
                <textarea id="motto" name="motto" rows="2" placeholder="写下一句你喜欢的话..." class="form-textarea"><?php echo htmlspecialchars($motto_val); ?></textarea>
            </div>
        </div>

        <!-- 账户安全部分 -->
        <div class="mb-8">
            <div class="section-title">账户安全</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="password" class="required">设置密码</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="form-input" required oninput="checkStrength()">
                        <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 opacity-40"></i>
                    </div>
                    <div class="h-1 bg-white/20 mt-2 rounded-full overflow-hidden">
                        <div id="strengthBar" class="h-full w-0 transition-all duration-300"></div>
                    </div>
                </div>
                <div>
                    <label for="confirm_password" class="required">确认密码</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        <i class="fas fa-check-double absolute right-4 top-1/2 -translate-y-1/2 opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-register">
            立即注册
        </button>
    </form>
    
    <div class="login-link-box">
        已有账号？<a href="user_login.php">立即登录</a>
    </div>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
