<?php
/**
 * 建议系统 API
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 启动输出缓冲并设置严格的错误控制
ob_start();
error_reporting(E_ALL); // 开启错误报告以便调试
ini_set('display_errors', 1);

// 3. 设置响应头与跨域配置
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * 确保建议表存在 (兼容性检查)
 */
function ensure_suggestion_table($conn) {
    // 1. 创建用户建议反馈表
    $create_sql = "CREATE TABLE IF NOT EXISTS `suggestions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nickname` VARCHAR(100) NOT NULL COMMENT '用户昵称',
        `email` VARCHAR(255) NOT NULL COMMENT '联系邮箱',
        `suggestion` TEXT NOT NULL COMMENT '建议内容',
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
        `user_agent` TEXT DEFAULT NULL COMMENT '环境信息',
        `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
        `is_read` TINYINT(1) DEFAULT 0 COMMENT '是否已读'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户建议反馈表';";
    
    $conn->query($create_sql) or die($conn->error);
}

/**
 * 获取客户端真实 IP
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

// 执行结构检查
ensure_suggestion_table($conn);

// 4. 解析请求信息
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 5. API 鉴权逻辑 (跳过初始化与提交)
$public_actions = ['init', 'get_config', 'submit'];
if (!in_array($action, $public_actions)) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '未登录或会话已过期'])); 
    }
}

// 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'min_length' => 5,
            'max_length' => 1000
        )
    ));
}

// 解析 JSON 输入数据 (针对 POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

// 兼容 action 在 JSON 中的情况
if (empty($action) && isset($data['action'])) {
    $action = $data['action'];
}

// 5. 核心业务逻辑
try {
    switch ($method) {
        case 'POST':
            if ($action === '' || $action === 'submit') {
                // --- 提交新建议 ---
                $nickname   = trim(isset($data['nickname']) ? $data['nickname'] : '');
                $email      = trim(isset($data['email']) ? $data['email'] : '');
                $suggestion = trim(isset($data['suggestion']) ? $data['suggestion'] : '');

                // 参数校验
                if ($nickname === '' || $email === '' || $suggestion === '') {
                    api_error('请填写所有必填字段');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    api_error('邮箱格式不正确');
                }
                if (mb_strlen($suggestion, 'UTF-8') < 5) {
                    api_error('建议内容至少需要 5 个字符');
                }

                // 执行插入
                $stmt = $conn->prepare("INSERT INTO suggestions (nickname, email, suggestion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)") or die($conn->error);
                $ip = getClientIP();
                $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                $stmt->bind_param("sssss", $nickname, $email, $suggestion, $ip, $ua);

                if ($stmt->execute()) {
                    $insertId = $stmt->insert_id;
                    $stmt->close();
                    api_success(array('id' => $insertId), '感谢您的宝贵建议！我们已收到并会尽快查阅');
                } else {
                    die($stmt->error);
                }
            } elseif ($action === 'mark_read') {
                // --- 标记建议为已读 (管理后台) ---
                if (!isset($_SESSION['user_id'])) api_error('您无权执行此操作', 401);
                
                $id = isset($data['id']) ? (int)$data['id'] : 0;
                if ($id <= 0) api_error('无效的建议ID');

                $stmt = $conn->prepare("UPDATE suggestions SET is_read = 1 WHERE id = ?") or die($conn->error);
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    api_success(null, '已标记为已处理');
                } else {
                    die($stmt->error);
                }
            }
            break;

        case 'GET':
            if ($action === 'get_suggestions') {
                // --- 获取建议列表 (管理后台) ---
                if (!isset($_SESSION['user_id'])) api_error('您无权执行此操作', 401);
                
                $limit  = isset($_GET['limit'])  ? max(1, min(200, (int)$_GET['limit']))  : 100;
                $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

                $stmt = $conn->prepare("SELECT * FROM suggestions ORDER BY submitted_at DESC LIMIT ? OFFSET ?") or die($conn->error);
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute() or die($stmt->error);
                $result = $stmt->get_result();

                $suggestions = array();
                while ($row = $result->fetch_assoc()) {
                    $row['is_read'] = (int)$row['is_read'];
                    $suggestions[]  = $row;
                }
                $stmt->close();
                api_success(array('suggestions' => $suggestions));
            }
            break;

        default:
            api_error('不支持的请求方式: ' . $method);
            break;
    }
} catch (Exception $e) {
    api_error('系统错误: ' . $e->getMessage());
}

// 释放连接资源
if (isset($conn)) $conn->close();
