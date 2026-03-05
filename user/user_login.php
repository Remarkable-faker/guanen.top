<?php
/**
 * 用户登录页面
 * 
 * 处理用户登录逻辑，包括验证凭据、设置会话以及自动管理员提权（首位用户）。
 * 采用了逻辑与视图分离的设计模式，并确保 PHP 5.6 兼容性。
 */

require_once dirname(__DIR__) . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 如果已登录，直接跳转到仪表盘或指定的重定向地址
if (isset($_SESSION['user_id'])) {
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'user_dashboard.php';
    header("Location: " . $redirect);
    exit();
}

// 初始化变量
$error = '';
$username_val = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_val = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username_val) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // --- 核心修复：严格使用 users 表进行登录验证 ---
        $found_user = null;

        // 仅从系统核心 users 表查询
        $stmt_users = $conn->prepare("SELECT id, username, password_hash as password, status, is_admin FROM users WHERE username = ? OR email = ?");
        $stmt_users->bind_param("ss", $username_val, $username_val);
        $stmt_users->execute();
        $res_users = $stmt_users->get_result();
        if ($res_users && $res_users->num_rows > 0) {
            $found_user = $res_users->fetch_assoc();
        }
        $stmt_users->close();

        if ($found_user) {
            // 验证密码
            $db_password = $found_user['password'];
            $is_password_correct = false;
            
            if (password_verify($password, $db_password)) {
                $is_password_correct = true;
            } elseif (md5($password) === $db_password) {
                $is_password_correct = true;
            } elseif ($password === $db_password) {
                $is_password_correct = true;
            }

            if ($is_password_correct) {
                // 检查用户状态
                if (isset($found_user['status']) && $found_user['status'] != 1) {
                    $error = '您的账户已被禁用，请联系管理员';
                } else {
                    // 登录成功 - 强制写入数字 ID 到 Session
                    $_SESSION['user_id'] = (int)$found_user['id']; // 必须存数字ID
                    $_SESSION['username'] = $found_user['username'];
                    $_SESSION['is_logged_in'] = true;
                    
                    // 检查管理员权限
                    if (isset($found_user['is_admin']) && $found_user['is_admin'] == 1) {
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['is_admin'] = true;
                        $_SESSION['admin_user_id'] = (int)$found_user['id'];
                    }
                    
                    // 更新最后登录时间
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $found_user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // 获取重定向地址 (优先从 POST 获取，其次从 GET 获取)
                    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : 'user_dashboard.php');
                    // 如果重定向地址为空，则跳转到仪表盘
                    if (empty($redirect)) {
                        $redirect = 'user_dashboard.php';
                    }
                    
                    // 简单安全校验：防止跨站重定向
                    if (strpos($redirect, 'http') === 0 && strpos($redirect, $_SERVER['HTTP_HOST']) === false) {
                        $redirect = 'user_dashboard.php';
                    }
                    
                    // 确保 Session 写入并退出
                    session_write_close();
                    
                    // 执行跳转
                    if (!headers_sent()) {
                        header("Location: " . $redirect);
                    } else {
                        echo '<script>window.location.href="' . addslashes($redirect) . '";</script>';
                        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect) . '"></noscript>';
                    }
                    exit();
                }
            } else {
                $error = '密码错误';
            }
        } else {
            $error = '用户不存在';
        }
        $conn->close();
    }
}

// --- 视图部分 ---

// 定义页面标题
$page_title = '登录';

// 隐藏布局中的头部和页脚，因为登录页有独立设计
$hide_header = true;
$hide_footer = true;
$full_width = true;

// 注入玻璃拟态样式的 CSS
$extra_css = '
    <!-- 引入 FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.42);
        --glass-border: rgba(255, 255, 255, 0.55);
        --text-color: #1e293b;
        --primary: #3b82f6;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    body {
        height: 100vh;
        overflow: hidden;
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
    }

    @keyframes gradientFlow {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .frosted-layer {
        position: absolute;
        inset: 0;
        backdrop-filter: blur(32px) saturate(135%) brightness(1.05);
        -webkit-backdrop-filter: blur(32px) saturate(135%) brightness(1.05);
        z-index: 0;
    }

    .login-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        padding: 48px 40px;
        width: 100%;
        max-width: 420px;
        position: relative;
        z-index: 10;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        margin: auto;
    }
    
    .logo-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        background: rgba(255, 255, 255, 0.6);
        color: #3b82f6;
        border-radius: 20px;
        margin-bottom: 24px;
        font-size: 28px;
        border: 1px solid var(--glass-border);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .login-title {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }
    
    .subtitle {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 32px;
    }
    
    .form-control {
        width: 100%;
        padding: 16px 16px 16px 48px;
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        font-size: 15px;
        color: #1e293b;
        background: rgba(255, 255, 255, 0.4);
        transition: var(--transition);
    }
    
    .form-control:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.7);
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    
    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 18px;
        transition: var(--transition);
    }
    
    .form-control:focus + .input-icon {
        color: #3b82f6;
    }
    
    .btn-login {
        display: block;
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
        margin-top: 12px;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    
    .btn-login:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(59, 130, 246, 0.3);
    }
    
    .alert-box {
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(254, 226, 226, 0.6);
        color: #dc2626;
        border: 1px solid rgba(252, 165, 165, 0.5);
        backdrop-filter: blur(10px);
        font-size: 14px;
    }
    
    .auth-link {
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .auth-link:hover {
        color: #3b82f6;
        transform: translateY(-1px);
    }
    
    .password-toggle {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 18px;
        padding: 8px;
        z-index: 2;
        transition: var(--transition);
    }
    
    .password-toggle:hover {
        color: #3b82f6;
    }
    
    @media (max-width: 480px) {
        .login-card { 
            padding: 40px 24px; 
            margin: 16px;
            border-radius: 24px;
        }
        .login-title { font-size: 24px; }
        .logo-box { width: 56px; height: 56px; font-size: 24px; margin-bottom: 20px; }
    }
    
    /* 覆盖布局容器样式以适应登录页 */
    .fade-in { animation: none !important; }
    </style>
';

// 注入 JS (本地化加载)
$extra_js = '
<script src="../assets/vendor/react.min.js"></script>
<script src="../assets/vendor/react-dom.min.js"></script>
<script>
    // 确保 React 全局变量可用
    window.React = React;
    window.ReactDOM = ReactDOM;
</script>
<script src="../assets/vendor/framer-motion.js"></script>
<script>
    // 密码显示/隐藏切换
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");
    if (togglePassword && passwordInput) {
        const eyeIcon = togglePassword.querySelector("i");
        togglePassword.addEventListener("click", function() {
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            eyeIcon.classList.toggle("fa-eye");
            eyeIcon.classList.toggle("fa-eye-slash");
        });
    }
    
    // 表单提交动画
    const loginForm = document.getElementById("loginForm");
    const loginBtn = document.getElementById("loginBtn");
    const btnText = document.getElementById("btnText");
    
    if (loginForm && loginBtn && btnText) {
        loginForm.addEventListener("submit", function() {
            loginBtn.classList.add("btn-loading");
            btnText.style.visibility = "hidden";
        });
    }
    
    // 输入框聚焦效果增强
    const inputs = document.querySelectorAll(".form-control");
    inputs.forEach(input => {
        input.addEventListener("focus", function() {
            this.parentElement.style.transform = "translateY(-2px)";
            this.parentElement.style.transition = "transform 0.3s ease";
        });
        
        input.addEventListener("blur", function() {
            this.parentElement.style.transform = "translateY(0)";
        });
    });
    
    // 自动聚焦到用户名输入框
    const usernameInput = document.getElementById("username");
    if (usernameInput) usernameInput.focus();
</script>
';

// 开始捕获 HTML 内容
ob_start();
?>

<div class="frosted-layer"></div>

<div class="login-card">
    <div class="text-center mb-8">
        <div class="logo-box">
            <i class="fas fa-user-circle"></i>
        </div>
        <h1 class="login-title">欢迎回来</h1>
        <p class="subtitle">正是江南好风景，落花时节又逢君</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert-box">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="loginForm">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(isset($_GET['redirect']) ? $_GET['redirect'] : ''); ?>">
        <div class="mb-6 relative">
            <i class="fas fa-user input-icon"></i>
            <input 
                type="text" 
                class="form-control" 
                id="username" 
                name="username" 
                placeholder="用户名或邮箱"
                value="<?php echo htmlspecialchars($username_val); ?>"
                required
                autocomplete="username"
            >
        </div>
        
        <div class="mb-6 relative">
            <i class="fas fa-lock input-icon"></i>
            <input 
                type="password" 
                class="form-control" 
                id="password" 
                name="password" 
                placeholder="密码"
                required
                autocomplete="current-password"
            >
            <button type="button" class="password-toggle" id="togglePassword">
                <i class="far fa-eye"></i>
            </button>
        </div>
        
        <button type="submit" class="btn-login" id="loginBtn">
            <span id="btnText">登入账户</span>
        </button>
    </form>
    
    <div class="mt-8 pt-6 border-t border-white/30">
        <div class="flex justify-between">
            <a href="user_register.php" class="auth-link">
                <i class="fas fa-user-plus"></i>
                <span>创建新账户</span>
            </a>
            <a href="../index.php" class="auth-link">
                <i class="fas fa-home"></i>
                <span>返回首页</span>
            </a>
        </div>
        
        <div class="text-center mt-6 text-xs opacity-50 flex items-center justify-center gap-2">
            <i class="far fa-copyright"></i>
            <span><?php echo date('Y'); ?> guanen.top</span>
        </div>
    </div>
</div>

<?php
// 获取捕获的内容并应用布局
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
