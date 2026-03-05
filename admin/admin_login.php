<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_config.php';

// 如果已经登录，直接跳转到管理后台
if (core_is_admin()) {
    header("Location: admin_dashboard.php");
    exit();
}

$error = ''; // 初始化错误变量

// --- 登录验证处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 连接数据库
    $conn = db_connect();
    
    if ($conn) {
        // 查询管理员用户（is_admin = 1）
       // 修改后的 SQL，允许 ID 为 2 或 3 的用户登录
$stmt = $conn->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE (username = ? OR email = ?) AND id IN (2, 3)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // 验证密码
            if (password_verify($password, $user['password_hash'])) {
                // 登录成功，设置会话
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_logged_in'] = true;
                
                // 跳转到管理后台
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "用户名或密码错误";
            }
        } else {
            $error = "用户名不存在或没有管理员权限";
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = "数据库连接失败，请联系系统管理员";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - guanen.top</title>
    <!-- 引入公共库 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5; /* 现代靛蓝 */
            --primary-light: #EEF2FF;
            --bg-main: #F8FAFC;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --radius-lg: 24px;
            --radius-md: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Noto Serif SC', 'Microsoft YaHei', sans-serif;
        }
        
        body {
            background-color: var(--bg-main);
            background-image: 
                radial-gradient(at 0% 0%, hsla(225, 100%, 94%, 1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(250, 100%, 96%, 1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(210, 100%, 95%, 1) 0, transparent 50%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(79, 70, 229, 0.08);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .login-header {
            padding: 50px 40px 20px;
            text-align: center;
        }
        
        .login-header .logo-icon {
            width: 72px;
            height: 72px;
            background: var(--primary-color);
            color: white;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 25px;
            box-shadow: 0 12px 24px rgba(79, 70, 229, 0.25);
        }

        .login-header h1 {
            font-size: 24px;
            color: var(--text-main);
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .login-header p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .login-form {
            padding: 20px 40px 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-weight: 600;
            font-size: 13px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all 0.2s;
            color: var(--text-main);
            background: #fcfdfe;
        }
        
        .input-with-icon input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: white;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }
        
        .error-message {
            background: #fef2f2;
            color: #ef4444;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin: 0 40px 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-size: 13px;
            display: <?php echo $error ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-footer {
            text-align: center;
            padding: 24px;
            background: rgba(248, 250, 252, 0.5);
            border-top: 1px solid #e2e8f0;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .back-to-site {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-to-site:hover {
            opacity: 0.8;
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 0;
                height: 100vh;
                max-width: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>管理员登录</h1>
            <p>冠恩管理后台 - 统一登录中心</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> 
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form" id="loginForm">
            <div class="form-group">
                <label for="username">账号</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" 
                           placeholder="请输入用户名" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="请输入密码" required>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                进入管理后台
            </button>
        </form>
        
        <div class="login-footer">
            <p>© <?php echo date('Y'); ?> GUANEN.TOP</p>
            <a href="../index.php" class="back-to-site">
                <i class="fas fa-arrow-left"></i> 返回首页
            </a>
        </div>
    </div>
    
    <script src="../lib/utils.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>