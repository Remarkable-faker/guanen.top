<?php
// --- 核心会话初始化 ---
require_once dirname(__DIR__) . '/includes/db_config.php';

$session_save_path = __DIR__ . '/../sessions';
if (!is_dir($session_save_path)) {
    @mkdir($session_save_path, 0777, true);
}
ini_set('session.save_path', $session_save_path);

if (ob_get_level() === 0) ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name('GUANEN_SESSION');
    ini_set('session.cookie_path', '/');
    $lifetime = 86400 * 7;
    session_set_cookie_params($lifetime, '/'); 
    session_start();
}

// 1. 判断是否登录
if (!function_exists('core_is_logged_in')) {
    function core_is_logged_in() {
        return (isset($_SESSION['user_id']) || isset($_SESSION['admin_user_id']) || (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true));
    }
}

// 2. 获取当前用户ID
if (!function_exists('core_get_user_id')) {
    function core_get_user_id() {
        return $_SESSION['user_id'] ?? $_SESSION['admin_user_id'] ?? $_SESSION['bc_user_id'] ?? null;
    }
}

// 3. 核心权限检查：判断是否能进入后台
if (!function_exists('core_is_admin')) {
    function core_is_admin() {
        $uid = core_get_user_id();
        // 原有管理员标志 OR ID在白名单(2, 3)中
        $is_admin_flag = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == true);
        $is_white_list = ($uid == 2 || $uid == 3);
        
        return ($is_admin_flag || $is_white_list);
    }
}

// 4. 新增：判断是否为“只读管理员” (ID为3的用户)
if (!function_exists('core_is_readonly_admin')) {
    function core_is_readonly_admin() {
        $uid = core_get_user_id();
        // 如果 ID 是 3，则判定为只读
        return ($uid == 3);
    }
}

if (!function_exists('core_current_username')) {
    function core_current_username() {
        return $_SESSION['username'] ?? $_SESSION['admin_username'] ?? '游客';
    }
}

// 访问记录函数
if (!function_exists('record_user_log')) {
    function record_user_log($action = '访问页面') {
        global $conn;
        if (!isset($conn)) $conn = db_connect();
        if (!$conn) return false;
        $user = core_current_username();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $page = $_SERVER['REQUEST_URI'] ?? '';
        $stmt = $conn->prepare("INSERT INTO user_logs (username, ip_address, action, page_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $user, $ip, $action, $page);
        $stmt->execute();
    }
}
record_user_log('访问页面');