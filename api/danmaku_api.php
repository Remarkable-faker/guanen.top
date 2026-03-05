<?php
/**
 * 弹幕系统 API
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 启动输出缓冲并设置严格的错误控制
ob_start();
error_reporting(E_ALL); // 开启错误报告以便调试
ini_set('display_errors', 1);

// 3. 设置响应头
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 4. 解析请求路由
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 5. API 鉴权逻辑 (跳过初始化与公开获取)
$public_actions = ['init', 'get_config', 'get', 'get_recent'];
if (!in_array($action, $public_actions)) {
    if (!core_is_logged_in()) { 
        api_error('未登录或会话已过期', 401);
    }
}

/**
 * 确保弹幕数据表存在
 */
function ensure_danmaku_table($conn) {
    // 1. 创建弹幕表
    $create_sql = "CREATE TABLE IF NOT EXISTS `bc_danmaku` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL COMMENT '用户ID',
        `nickname` VARCHAR(50) NOT NULL COMMENT '显示昵称',
        `content` TEXT NOT NULL COMMENT '留言内容',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='弹幕留言表';";
    
    $conn->query($create_sql) or die($conn->error);
}

// 执行结构检查
ensure_danmaku_table($conn);

// 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    $is_logged_in = core_is_logged_in();
    $data = array(
        'is_logged_in' => $is_logged_in,
        'user_id' => $is_logged_in ? core_get_user_id() : null,
        'username' => $is_logged_in ? (isset($_SESSION['username']) ? $_SESSION['username'] : '') : '',
        'nickname' => $is_logged_in ? (isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '')) : '',
        'config' => array(
            'max_length' => 100,
            'refresh_interval' => 30000
        )
    );
    api_success($data, '初始化成功', 200, $data);
}

// 5. 核心业务逻辑处理
switch ($action) {
    case 'send':
        // --- 发送弹幕逻辑 ---
        if (!core_is_logged_in()) {
            api_error('请先登录系统后发表留言', 401);
        }

        // 解析输入数据
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true) ?: $_POST;
        
        $content = isset($data['content']) ? trim($data['content']) : '';

        // 基础参数校验
        if (empty($content)) {
            api_error('留言内容不能为空');
        }
        if (mb_strlen($content) > 100) {
            api_error('留言内容不能超过100字');
        }

        $user_id = core_get_user_id();
        $nickname = isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '匿名读者');

        // 插入弹幕记录
        $stmt = $conn->prepare("INSERT INTO bc_danmaku (user_id, nickname, content) VALUES (?, ?, ?)") or die($conn->error);
        $stmt->bind_param("iss", $user_id, $nickname, $content);
        
        if ($stmt->execute()) {
            $stmt->close();
            api_success(array(
                'nickname' => $nickname,
                'content' => $content,
                'time' => date('Y-m-d H:i:s')
            ), '留言成功！');
        } else {
            die($stmt->error);
        }
        break;

    case 'get_recent':
        // --- 获取最近弹幕逻辑 ---
        // 获取最近 50 条弹幕，按发布时间倒序
        $sql = "SELECT nickname, content, created_at FROM bc_danmaku ORDER BY created_at DESC LIMIT 50";
        $res = $conn->query($sql) or die($conn->error);
        
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
        
        // 返回按时间正序排列的数据（方便前端按时间轴滚动显示）
        api_success(array_reverse($list));
        break;

    default:
        api_error('未知的操作指令: ' . $action);
        break;
}

// 释放连接资源
if (isset($conn)) $conn->close();
