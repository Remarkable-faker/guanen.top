<?php
/**
 * 问卷系统 API
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 启动输出缓冲并设置严格的错误控制
ob_start();
error_reporting(E_ALL); 
ini_set('display_errors', 1);

// 3. 设置响应头
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 4. 解析请求路由
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 5. API 鉴权逻辑 (跳过初始化与提交)
$public_actions = ['init', 'get_config', 'submit'];
if (!in_array($action, $public_actions)) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '未登录或会话已过期'])); 
    }
}

/**
 * 确保问卷建议表存在 (兼容性检查)
 */
function ensure_wenjuan_table($conn) {
    // 1. 创建问卷系统建议表
    $create_sql = "CREATE TABLE IF NOT EXISTS `wenjuan_suggestions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nickname` VARCHAR(100) NOT NULL COMMENT '用户昵称',
        `email` VARCHAR(255) NOT NULL COMMENT '联系邮箱',
        `suggestion` TEXT NOT NULL COMMENT '建议内容',
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
        `user_agent` TEXT DEFAULT NULL COMMENT '环境信息',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `status` ENUM('pending', 'read', 'replied') DEFAULT 'pending' COMMENT '处理状态',
        INDEX (`email`),
        INDEX (`created_at`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='问卷系统建议表';";
    
    $conn->query($create_sql) or die($conn->error);
}

// 执行结构检查
ensure_wenjuan_table($conn);
$method = $_SERVER['REQUEST_METHOD'];

// 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'max_suggestion_length' => 1000,
            'min_suggestion_length' => 5
        )
    ));
}

// 5. 核心业务处理
try {
    if ($method === 'POST') {
        // --- 提交建议逻辑 ---
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        
        // 参数校验
        $nickname   = trim(isset($data['nickname']) ? $data['nickname'] : '');
        $email      = trim(isset($data['email']) ? $data['email'] : '');
        $suggestion = trim(isset($data['suggestion']) ? $data['suggestion'] : '');

        if ($nickname === '' || $email === '' || $suggestion === '') {
            api_error('请填写所有必填字段');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_error('请输入有效的邮箱地址');
        }
        if (mb_strlen($suggestion) < 5) {
            api_error('建议内容至少需要 5 个字符');
        }
        
        // 获取客户端信息
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        
        // 插入建议数据
        $stmt = $conn->prepare("INSERT INTO wenjuan_suggestions (nickname, email, suggestion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)") or die($conn->error);
        $stmt->bind_param("sssss", $nickname, $email, $suggestion, $ip_address, $user_agent);
        
        if ($stmt->execute()) {
            $suggestion_id = $stmt->insert_id;
            $stmt->close();
            api_success(array(
                'suggestion_id' => $suggestion_id,
                'timestamp' => date('Y-m-d H:i:s')
            ), '提交成功！感谢您的反馈');
        } else {
            die($stmt->error);
        }
        
    } elseif ($method === 'GET') {
        // --- 获取统计数据逻辑 ---
        $stats = array(
            'total_suggestions' => 0,
            'today_suggestions' => 0,
            'server_time' => date('Y-m-d H:i:s')
        );

        // 总数统计
        $total_res = $conn->query("SELECT COUNT(*) as total FROM wenjuan_suggestions") or die($conn->error);
        if ($row = $total_res->fetch_assoc()) {
            $stats['total_suggestions'] = (int)$row['total'];
        }
        
        // 今日新增统计
        $today_res = $conn->query("SELECT COUNT(*) as today FROM wenjuan_suggestions WHERE DATE(created_at) = CURDATE()") or die($conn->error);
        if ($row = $today_res->fetch_assoc()) {
            $stats['today_suggestions'] = (int)$row['today'];
        }
        
        api_success($stats);
    } else {
        api_error('不支持的请求方式: ' . $method);
    }
} catch (Exception $e) {
    api_error('系统错误: ' . $e->getMessage());
}

// 释放连接资源
if (isset($conn)) $conn->close();