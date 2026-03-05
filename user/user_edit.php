<?php
/**
 * 用户资料编辑页面
 * 
 * 允许用户修改个人信息，如邮箱、手机号、性别、生日、爱好和座右铭。
 * 采用了逻辑与视图分离的设计模式，并确保 PHP 5.6 兼容性。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 初始化变量
$error = '';
$success = '';

// 检查并确定查询表名 (强制使用核心 users 表)
$user_table = 'users';

// 获取当前用户信息
$stmt = $conn->prepare("SELECT username, email, phone, gender, birthdate, hobbies, motto FROM `$user_table` WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    // 降级：如果 users 表没找到，尝试 bc_users 并同步
    $stmt_fallback = $conn->prepare("SELECT username, email, phone, gender, birthdate, hobbies, motto FROM bc_users WHERE id = ?");
    $stmt_fallback->bind_param("i", $user_id);
    $stmt_fallback->execute();
    $res_fallback = $stmt_fallback->get_result();
    if ($res_fallback->num_rows > 0) {
        $user = $res_fallback->fetch_assoc();
        // 自动同步到 users 表
        $sync_stmt = $conn->prepare("INSERT IGNORE INTO users (id, username, email, phone, gender, birthdate, hobbies, motto, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $sync_stmt->bind_param("isssssss", $user_id, $user['username'], $user['email'], $user['phone'], $user['gender'], $user['birthdate'], $user['hobbies'], $user['motto']);
        $sync_stmt->execute();
    } else {
        die("用户不存在");
    }
} else {
    $user = $res->fetch_assoc();
}

$username = $user['username'];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_val = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone_val = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $gender_val = isset($_POST['gender']) ? $_POST['gender'] : '';
    $birthdate_val = isset($_POST['birthdate']) ? $_POST['birthdate'] : '';
    $selected_hobbies = isset($_POST['hobbies']) ? $_POST['hobbies'] : array();
    $motto_val = isset($_POST['motto']) ? trim($_POST['motto']) : '';

    // 验证输入
    if (empty($email_val) || empty($phone_val)) {
        $error = '邮箱和手机号不能为空';
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone_val)) {
        $error = '请输入有效的手机号';
    } else {
        // 检查邮箱和手机号是否被其他用户占用 (统一使用核心 users 表)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $email_val, $phone_val, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = '邮箱或手机号已被其他账号使用';
        } else {
            // 处理爱好数组
            $hobbies_str = !empty($selected_hobbies) ? implode(',', $selected_hobbies) : '';
            
            // 更新用户信息 (统一更新 users 表)
            $update_stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, gender = ?, birthdate = ?, hobbies = ?, motto = ? WHERE id = ?");
            $update_stmt->bind_param("ssssssi", $email_val, $phone_val, $gender_val, $birthdate_val, $hobbies_str, $motto_val, $user_id);
            
            if ($update_stmt->execute()) {
                $success = '个人资料已成功更新';
                // 同时同步到 bc_users (如果存在)
                $conn->query("UPDATE bc_users SET email = '$email_val', phone = '$phone_val', gender = '$gender_val', birthdate = '$birthdate_val', hobbies = '$hobbies_str', motto = '$motto_val' WHERE id = $user_id");
                
                // 更新当前显示的数据
                $user['email'] = $email_val;
                $user['phone'] = $phone_val;
                $user['gender'] = $gender_val;
                $user['birthdate'] = $birthdate_val;
                $user['hobbies'] = $hobbies_str;
                $user['motto'] = $motto_val;
            } else {
                $error = '更新失败，请稍后重试';
                error_log("资料更新失败: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

$stmt->close();
$conn->close();

// --- 视图部分 ---

$page_title = '编辑资料';

// 注入样式 (复用注册页面的部分样式)
$extra_css = '
<style>
    .edit-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 24px;
        border-bottom: 1px solid #f1f5f9;
    }
    .form-section:last-child {
        border-bottom: none;
    }
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
    }
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 15px;
        transition: all 0.2s;
        background: #f8fafc;
    }
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    .form-input[readonly] {
        background-color: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
    }
    .hobby-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
    }
    .hobby-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .hobby-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .hobby-checkbox {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .hobby-checkbox:checked + .hobby-label {
        color: #3b82f6;
        font-weight: 700;
    }
    .btn-save {
        padding: 14px 28px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        max-width: 200px;
    }
    .btn-save:hover {
        background: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    @media (max-width: 768px) {
        .edit-card {
            padding: 24px 20px;
        }
        .btn-save {
            max-width: 100%;
        }
        .hobby-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 480px) {
        .hobby-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
';

// 开始捕获内容
ob_start();
?>

<div class="max-w-4xl mx-auto py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-800">编辑个人资料</h1>
        <a href="user_dashboard.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> 返回仪表盘
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="edit-card">
        <!-- 基本账户信息 -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-id-card"></i> 账户信息</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">用户名 (不可修改)</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($username); ?>" readonly>
                </div>
                <div>
                    <label for="email" class="form-label">电子邮箱</label>
                    <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
        </div>

        <!-- 详细信息 -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-user-tag"></i> 个人信息</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="phone" class="form-label">手机号码</label>
                    <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>
                <div>
                    <label for="birthdate" class="form-label">出生日期</label>
                    <input type="date" id="birthdate" name="birthdate" class="form-input" value="<?php echo htmlspecialchars($user['birthdate']); ?>">
                </div>
                <div>
                    <label class="form-label">性别</label>
                    <select name="gender" class="form-input">
                        <option value="保密" <?php echo $user['gender'] == '保密' ? 'selected' : ''; ?>>保密</option>
                        <option value="male" <?php echo $user['gender'] == 'male' ? 'selected' : ''; ?>>男</option>
                        <option value="female" <?php echo $user['gender'] == 'female' ? 'selected' : ''; ?>>女</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 兴趣爱好 -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-star"></i> 兴趣爱好</h2>
            <div class="hobby-grid">
                <?php 
                $hobby_list = array('阅读', '音乐', '电影', '运动', '旅游', '美食', '游戏', '摄影', '编程', '绘画', '写作', '手工');
                $current_hobbies = explode(',', $user['hobbies']);
                foreach ($hobby_list as $hobby): 
                    $is_checked = in_array($hobby, $current_hobbies);
                ?>
                <label class="hobby-item">
                    <input type="checkbox" name="hobbies[]" value="<?php echo $hobby; ?>" class="hobby-checkbox" <?php echo $is_checked ? 'checked' : ''; ?>>
                    <span class="hobby-label text-sm"><?php echo $hobby; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 个性签名 -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-quote-left"></i> 个性签名</h2>
            <textarea name="motto" rows="3" class="form-textarea" placeholder="写点什么来展示自己..."><?php echo htmlspecialchars($user['motto']); ?></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> 保存修改
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
