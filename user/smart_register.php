<?php
/**
 * 智能用户注册系统 (兼容模式)
 * 
 * 职责：
 * 1. 自动适应数据库表结构的注册逻辑。
 * 2. 规范化改造：集成核心库，使用预处理语句，逻辑/视图分离。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 如果已登录，跳转到用户中心
if (isset($_SESSION['user_id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$conn = db_connect();
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $birthdate = isset($_POST['birthdate']) ? $_POST['birthdate'] : '';
    $hobbies = isset($_POST['hobbies']) ? $_POST['hobbies'] : array();
    $motto = isset($_POST['motto']) ? trim($_POST['motto']) : '';
    
    // 验证输入
    if (empty($username) || empty($email) || empty($password) || empty($phone)) {
        $error = '带*的字段必须填写';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (strlen($password) < 6) {
        $error = '密码至少需要6位';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]{2,20}$/u', $username)) {
        $error = '用户名只能包含中文、字母、数字和下划线（2-20位）';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error = '请输入有效的中国大陆手机号';
    } else {
        // 检查用户名、邮箱和手机号是否已存在
        $stmt = $conn->prepare("SELECT id FROM bc_users WHERE username = ? OR email = ? OR phone = ?");
        $stmt->bind_param("sss", $username, $email, $phone);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = '用户名、邮箱或手机号已被注册';
        } else {
            // 处理爱好数组（转换为字符串）
            $hobbies_str = !empty($hobbies) ? implode(',', $hobbies) : '';
            
            // 加密密码
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // 智能插入
            $user_data = array(
                'username' => $username,
                'email' => $email,
                'password' => $password_hash, // 修改键名为 password 以对齐 bc_users 表
                'phone' => $phone,
                'gender' => $gender,
                'birthdate' => $birthdate,
                'hobbies' => $hobbies_str,
                'motto' => $motto
            );
            
            $insert_result = smart_insert_user($conn, $user_data);
            
            if ($insert_result['success']) {
                $success = '注册成功！正在进入仪表盘...';
                $user_id = $insert_result['user_id'];
                
                // 自动登录
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_logged_in'] = true;
                
                // 检查是否是第一个用户并自动设为管理员
                $res = $conn->query("SELECT MIN(id) as first_id FROM bc_users");
                if ($res) {
                    $first_user = $res->fetch_assoc();
                    if ($user_id == $first_user['first_id']) {
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $user_id;
                        $_SESSION['is_admin'] = true;
                        $_SESSION['admin_user_id'] = $user_id;
                        $_SESSION['admin_username'] = $username;
                        $conn->query("UPDATE bc_users SET is_admin = 1 WHERE id = $user_id");
                    }
                }
                
                $extra_js_footer = '<script>setTimeout(function() { window.location.href = "user_dashboard.php?from=register"; }, 2000);</script>';
            } else {
                $error = $insert_result['error'];
            }
        }
        $stmt->close();
    }
}

/**
 * 智能插入用户数据
 */
function smart_insert_user($conn, $user_data) {
    // 获取表结构信息
    $fields = array();
    $res = $conn->query("DESCRIBE bc_users");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $fields[] = $row['Field'];
        }
    }
    
    if (empty($fields)) {
        return array('success' => false, 'error' => '无法读取用户表结构');
    }
    
    // 构建动态 SQL
    $insert_fields = array();
    $placeholders = array();
    $values = array();
    $types = '';
    
    foreach ($user_data as $field => $value) {
        if (in_array($field, $fields)) {
            $insert_fields[] = $field;
            $placeholders[] = '?';
            $values[] = $value;
            $types .= 's';
        }
    }
    
    if (empty($insert_fields)) {
        return array('success' => false, 'error' => '没有有效的字段可插入');
    }
    
    $sql = "INSERT INTO bc_users (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return array('success' => false, 'error' => 'SQL 准备失败: ' . $conn->error);
    }
    
    // 动态绑定
    $bind_names = array($types);
    for ($i = 0; $i < count($values); $i++) {
        $bind_names[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();
        return array('success' => true, 'user_id' => $user_id);
    } else {
        $err = $stmt->error;
        $stmt->close();
        return array('success' => false, 'error' => '插入失败: ' . $err);
    }
}

// --- 视图部分 ---

$page_title = '智能注册';
$hide_header = true;
$hide_footer = true;
$full_width = true;

$extra_css = '
<style>
    body { background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .smart-card { background: white; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 500px; padding: 40px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; color: #64748b; margin-bottom: 8px; font-weight: 500; }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.2s; }
    .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .btn-submit { width: 100%; padding: 14px; background: #3b82f6; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; margin-top: 10px; }
    .btn-submit:hover { background: #2563eb; }
    .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
    .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
</style>
';

ob_start();
?>

<div class="smart-card">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">智能注册</h1>
        <p class="text-sm text-gray-500 mt-2">自动适配表结构的快速注册方式</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label>用户名 *</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars(isset($username) ? $username : ''); ?>" required>
            </div>
            <div class="form-group">
                <label>邮箱 *</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars(isset($email) ? $email : ''); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>手机号 *</label>
            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars(isset($phone) ? $phone : ''); ?>" required>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label>设置密码 *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>确认密码 *</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label>个人签名</label>
            <textarea name="motto" class="form-control" rows="2"><?php echo htmlspecialchars(isset($motto) ? $motto : ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">立即注册</button>
        
        <div class="text-center mt-6 text-sm text-gray-500">
            已有账号？ <a href="user_login.php" class="text-blue-600 hover:underline">去登录</a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
