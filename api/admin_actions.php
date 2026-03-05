<?php
/**
 * 后台管理统一 API 入口
 *
 * 设计原则:
 * 1. 接收所有来自 admin.js 的异步请求。
 * 2. 根据 'action' 参数分发到不同的处理函数。
 * 3. 所有响应都必须是 JSON 格式。
 * 4. 严格的权限和参数校验。
 */

// 引入核心依赖
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/api_helpers.php';

// 强制响应类型为 JSON
header('Content-Type: application/json; charset=utf-8');

// 获取并校验 action 参数
$action = isset($_GET['action']) ? $_GET['action'] : ' ';

// 全局数据库连接
$conn = db_connect();
if (!$conn) {
    api_error('数据库连接失败', 500);
}

// 根据 action 分发请求
switch ($action) {
    case 'login':
        handle_admin_login($conn);
        break;
    
    // 可以在这里为其他异步操作（如用户状态切换）添加 case
    // case 'toggle_user_status':
    //     handle_toggle_user_status($conn);
    //     break;

    default:
        api_error('无效的请求操作', 400);
        break;
}

/**
 * 处理管理员登录
 * @param mysqli $conn 数据库连接实例
 */
function handle_admin_login($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('仅允许 POST 请求', 405);
    }

    // 从 php://input 读取 JSON 数据
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    // 从解码后的数据中获取用户名和密码
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if (empty($username) || empty($password)) {
        api_error('用户名和密码不能为空', 400);
    }

    // 使用预处理语句防止 SQL 注入
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ? AND is_admin = 1");
    if (!$stmt) {
        api_error('数据库查询准备失败: ' . $conn->error, 500);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        // 验证密码
        if (password_verify($password, $admin['password_hash'])) {
            // 登录成功，设置 Session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_id'] = $admin['id'];
            
            api_success(['message' => '登录成功，即将跳转...'], '登录成功');
        } else {
            // 密码错误
            api_error('用户名或密码错误', 401);
        }
    } else {
        // 用户不存在或不是管理员
        api_error('管理员账号不存在', 404);
    }

    $stmt->close();
    $conn->close();
}

?>