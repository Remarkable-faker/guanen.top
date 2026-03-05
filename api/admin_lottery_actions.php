<?php
/**
 * API: 后台抽奖管理动作 - 纯净 JSON 修复版
 */

// 1. 立即拦截任何非 JSON 输出的可能性
ob_start(); 
@ini_set('display_errors', 0);
error_reporting(0);

// 设置标准的 API 响应头
header('Content-Type: application/json; charset=utf-8');

// 2. 致命错误捕获器（增强版）
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_level() > 0) ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'PHP 致命错误: ' . $error['message'] . ' 在文件 ' . basename($error['file']) . ' 第 ' . $error['line'] . ' 行'
        ]);
        exit;
    }
});

// 3. 路径修复：优先使用 __DIR__ 相对定位，比 $_SERVER['DOCUMENT_ROOT'] 更稳健
$root_path = dirname(__DIR__); // 假设此文件在 /admin/ 目录下，向上退一级到根目录

// 检查核心文件是否存在，防止 require_once 直接抛出 HTML 错误
$session_file = $root_path . '/core/session.php';
$helpers_file = $root_path . '/core/api_helpers.php';

if (!file_exists($session_file) || !file_exists($helpers_file)) {
    echo json_encode(['success' => false, 'message' => '核心依赖文件丢失，检查路径: ' . $root_path]);
    exit;
}

require_once $session_file;
require_once $helpers_file;

// --- 最终修复：补全数据库连接的建立过程 ---
require_once $root_path . '/includes/db_config.php';
require_once $root_path . '/core/db.php';
$conn = db_connect();

// 4. 安全校验
if (!function_exists('core_is_admin') || !core_is_admin()) {
    echo json_encode(['success' => false, 'message' => '权限不足或登录过期']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅限 POST 请求']);
    exit;
}

// 5. 动作路由
$action = $_POST['action'] ?? '';

if ($action === 'save_lottery_config') {
    save_lottery_config($root_path);
} else {
    echo json_encode(['success' => false, 'message' => '未知的操作: ' . $action]);
    exit;
}

/**
 * 核心功能：保存抽奖配置到数据库
 */
function save_lottery_config($root_path) {
    // 假设数据库连接在 core/api_helpers.php 中通过 $conn 全局变量提供
    global $conn; 

    if (!isset($conn) || (property_exists($conn, 'connect_error') && $conn->connect_error)) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . ($conn->connect_error ?? '未知错误')]);
        exit;
    }

    // 1. 从数据库读取现有配置
    $current_data = [];
    $stmt_read = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'lottery_config'");
    if (!$stmt_read) {
        echo json_encode(['success' => false, 'message' => '数据库查询准备失败: ' . $conn->error]);
        exit;
    }
    $stmt_read->execute();
    $result = $stmt_read->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_data = json_decode($row['setting_value'], true) ?: [];
    }
    $stmt_read->close();

    // 2. 更新字段 (使用原有的健壮逻辑和默认值)
    $current_data['max_daily_draws']    = isset($_POST['max_daily_draws']) ? (int)$_POST['max_daily_draws'] : ($current_data['max_daily_draws'] ?? 5);
    $current_data['book_probability']   = isset($_POST['book_probability']) ? (float)$_POST['book_probability'] : ($current_data['book_probability'] ?? 0.1);
    $current_data['max_total_wins']     = isset($_POST['max_total_wins']) ? (int)$_POST['max_total_wins'] : ($current_data['max_total_wins'] ?? 1);
    $current_data['reward_pool_enabled'] = isset($_POST['reward_pool_enabled']) && ($_POST['reward_pool_enabled'] === 'true' || $_POST['reward_pool_enabled'] === '1' || $_POST['reward_pool_enabled'] === 'on');
    
    // 3. 元数据
    $current_data['last_updated'] = date('Y-m-d H:i:s');
    $current_data['updated_by']   = $_SESSION['admin_user'] ?? 'Admin';

    // 4. 将更新后的配置转为 JSON 字符串
    $new_config_json = json_encode($current_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // 5. 写入数据库 (使用 INSERT ... ON DUPLICATE KEY UPDATE 确保原子性)
    $stmt_write = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('lottery_config', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if (!$stmt_write) {
        echo json_encode(['success' => false, 'message' => '数据库写入准备失败: ' . $conn->error]);
        exit;
    }
    $stmt_write->bind_param('ss', $new_config_json, $new_config_json);
    
    if ($stmt_write->execute()) {
        if (ob_get_level() > 0) ob_clean();
        echo json_encode(['success' => true, 'message' => '配置已成功同步至数据库', 'data' => $current_data]);
    } else {
        echo json_encode(['success' => false, 'message' => '数据库写入失败: ' . $stmt_write->error]);
    }
    $stmt_write->close();
    exit;
}