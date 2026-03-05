<?php
/**
 * 修改密码页面
 * 
 * 允许已登录用户修改其登录密码。
 * 遵循 Stage 3 规范：逻辑与视图分离，使用预处理语句，PHP 5.6 兼容
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // 基础验证
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = '所有字段均为必填项';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码长度至少为 6 位';
    } elseif ($new_password !== $confirm_password) {
        $error = '两次输入的新密码不一致';
    } else {
        // 验证旧密码
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if (password_verify($current_password, $user['password_hash'])) {
                // 更新新密码
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $user_id);
                
                if ($update_stmt->execute()) {
                    $success = '密码已成功修改';
                    // 如果存在 bc_users，同步更新
                    $conn->query("UPDATE bc_users SET password_hash = '$new_hash' WHERE id = $user_id");
                } else {
                    $error = '更新失败，请稍后重试';
                }
                $update_stmt->close();
            } else {
                $error = '当前密码不正确';
            }
        } else {
            $error = '用户不存在';
        }
        $stmt->close();
    }
}

$conn->close();

// --- 视图部分 ---
$page_title = '修改密码';

// 注入样式
$extra_css = '
<style>
    .password-card {
        background: white;
        border-radius: 24px;
        padding: 40px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        max-width: 500px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 24px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
    }
    .form-input {
        width: 100%;
        padding: 14px 16px;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        font-size: 15px;
        transition: all 0.2s;
        background: #f8fafc;
    }
    .form-input:focus {
        outline: none;
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    .btn-submit {
        width: 100%;
        padding: 14px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 10px;
    }
    .btn-submit:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    }
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-error {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .alert-success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #dcfce7;
    }
    
    @media (max-width: 640px) {
        .password-card {
            padding: 30px 20px;
            border-radius: 20px;
        }
    }
</style>
';

ob_start();
?>

<div class="password-card">
    <div style="text-align: center; margin-bottom: 32px;">
        <div style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h2 style="font-size: 24px; color: #1e293b; font-weight: 700;">修改账户密码</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 8px;">为了您的账户安全，请定期更换密码</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">当前密码</label>
            <input type="password" name="current_password" class="form-input" placeholder="请输入当前使用的密码" required>
        </div>

        <div class="form-group">
            <label class="form-label">新密码</label>
            <input type="password" name="new_password" class="form-input" placeholder="至少 6 位字符" required>
        </div>

        <div class="form-group">
            <label class="form-label">确认新密码</label>
            <input type="password" name="confirm_password" class="form-input" placeholder="请再次输入新密码" required>
        </div>

        <button type="submit" class="btn-submit">
            保存新密码
        </button>
        
        <div style="text-align: center; margin-top: 24px;">
            <a href="user_dashboard.php" style="color: #64748b; font-size: 14px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="fas fa-arrow-left"></i> 返回控制台
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
require_once 'user_layout.php';
?>