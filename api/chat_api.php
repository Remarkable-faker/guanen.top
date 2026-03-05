<?php
/**
 * 实时聊天 API
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once dirname(__DIR__) . '/core/session.php';

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

// 3. 解析请求路由
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 4. API 鉴权逻辑 (跳过初始化)
if ($action !== 'init' && $action !== 'get_config') {
    if (!core_is_logged_in()) {
        // 记录未登录状态以便调试
        error_log("Chat API Auth Failed: Action=$action, SessionID=" . session_id() . ", UserID=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NONE'));
        api_error('未登录或会话已过期', 401);
    }
}

/**
 * 确保聊天系统相关的表结构存在
 */
function ensure_chat_tables($conn) {
    // 0. 核心用户表 (根据数据字典，必须使用 users 表)
    $sql0 = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `gender` VARCHAR(10) DEFAULT NULL,
        `birthdate` DATE DEFAULT NULL,
        `hobbies` TEXT DEFAULT NULL,
        `motto` VARCHAR(255) DEFAULT NULL,
        `status` TINYINT DEFAULT 1,
        `is_admin` TINYINT DEFAULT 0,
        `role_label` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_login` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='核心用户表';";
    $conn->query($sql0) or api_error("数据库错误(users): " . $conn->error);

    // 1. 好友关系表 (核心逻辑：user_id 是申请人, friend_id 是被申请人, status: 0=申请中, 1=已同意)
    $sql2 = "CREATE TABLE IF NOT EXISTS `friends` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL COMMENT '申请人ID',
        `friend_id` INT NOT NULL COMMENT '被申请人ID',
        `status` TINYINT DEFAULT 0 COMMENT '0=申请中, 1=已同意',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_friendship` (`user_id`, `friend_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友关系表';";
    $conn->query($sql2) or api_error("数据库错误: " . $conn->error);

    // 检查并添加 status 字段（如果旧表存在但没这个字段）
    $check_status = $conn->query("SHOW COLUMNS FROM `friends` LIKE 'status'") or api_error("数据库错误: " . $conn->error);
    if ($check_status->num_rows == 0) {
        $conn->query("ALTER TABLE `friends` ADD COLUMN `status` TINYINT DEFAULT 0 COMMENT '0=申请中, 1=已同意' AFTER `friend_id`") or api_error("数据库错误: " . $conn->error);
    }

    // 2. 私聊消息表
    $sql3 = "CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `message` TEXT NOT NULL,
        `is_read` TINYINT DEFAULT 0 COMMENT '是否已读:0未读,1已读',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_sender` (`sender_id`),
        INDEX `idx_receiver` (`receiver_id`),
        INDEX `idx_is_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊消息记录';";
    $conn->query($sql3) or api_error("数据库错误: " . $conn->error);

    // 3. 群聊消息表
    $sql4 = "CREATE TABLE IF NOT EXISTS `group_messages` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `message` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_sender` (`sender_id`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群聊/公共频道消息记录';";
    $conn->query($sql4) or api_error("数据库错误: " . $conn->error);

    // 4. AI 聊天记录表 (关联 users 表的 id)
    $sql5 = "CREATE TABLE IF NOT EXISTS `ai_chat_records` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL COMMENT '用户ID，外键关联 users.id',
        `user_message` TEXT NOT NULL COMMENT '用户发送的消息',
        `ai_response` TEXT NOT NULL COMMENT 'AI 回复的消息',
        `model_used` VARCHAR(50) DEFAULT 'deepseek-chat' COMMENT '使用的模型',
        `temperature` DECIMAL(3,1) DEFAULT 0.7 COMMENT '温度参数',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        PRIMARY KEY (`id`),
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_created_at` (`created_at`),
        CONSTRAINT `fk_ai_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI 聊天记录表';";
    $conn->query($sql5) or api_error("数据库错误(ai_chat_records): " . $conn->error);

    // 4. 确保 users 表具备扩展字段 (主表)
    $check_role = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role_label'") or api_error("数据库错误: " . $conn->error);
    if ($check_role->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `role_label` VARCHAR(50) DEFAULT NULL COMMENT '角色标签';") or api_error("数据库错误: " . $conn->error);
    }
    
    // 5. 核心数据同步：如果 users 表为空，尝试从 bc_users 同步数据
    $count_res = $conn->query("SELECT COUNT(*) as total FROM `users` ");
    $count = ($count_res) ? $count_res->fetch_assoc()['total'] : 0;
    if ($count == 0) {
        // 检查 bc_users 是否存在
        $check_bc = $conn->query("SHOW TABLES LIKE 'bc_users'");
        if ($check_bc && $check_bc->num_rows > 0) {
            // 同步核心字段
            $conn->query("INSERT INTO `users` (id, username, password_hash, email, phone, gender, birthdate, hobbies, motto, status, is_admin, created_at) 
                          SELECT id, username, password, email, phone, gender, birthdate, hobbies, motto, 1, is_admin, created_at 
                          FROM `bc_users` ON DUPLICATE KEY UPDATE username=VALUES(username)");
        }
    }
}

// 执行结构检查
ensure_chat_tables($conn);

$method = $_SERVER['REQUEST_METHOD'];

// 统一标准：获取初始化配置和登录状态 (无需登录即可访问)
if ($action === 'init' || $action === 'get_config') {
    $is_logged_in = core_is_logged_in();
    // 3. 获取用户信息 (严格使用 users 表)
    $user_data = null;
    if ($is_logged_in) {
        $uid = core_get_user_id();
        // 仅从主 users 表获取详细信息
        $stmt = $conn->prepare("SELECT id, username, motto, role_label FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // 如果还是没找到，构造基础信息 (降级处理)
        if (!$user_data) {
            $user_data = [
                'id' => $uid,
                'username' => core_current_username(),
                'motto' => '',
                'role_label' => core_is_admin() ? '管理员' : ''
            ];
        }
    }
    
    $resp = array(
        'is_logged_in' => $is_logged_in,
        'user' => $user_data,
        'config' => array(
            'emojis' => ['😊', '😂', '🤣', '😍', '😒', '😭', '😘', '😩', '😔', '👌', '👍', '🙌', '🙏', '🔥', '✨', '💖', '🤔', '😎', '😜', '😢', '😡', '😴', '👋', '🎉']
        )
    );
    api_success($resp, '初始化成功', 200, $resp);
}

// 4. 鉴权逻辑
if (!core_is_logged_in()) {
    api_error('请先登录后操作', 401);
}
$user_id = core_get_user_id();

// 5. 检查用户状态（防止被封禁用户继续聊天）
$stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_status);
$stmt->fetch();
$stmt->close();

if ($user_status !== null && $user_status != 1) {
    // 清除登录相关的 session
    $_SESSION = array();
    session_destroy();
    api_error('您的账号已被禁用，请联系管理员', 403);
}

// 6. 核心业务处理
// 解析 JSON 输入
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

switch ($action) {
    // --- 用户搜索与推荐 ---
    case 'search_user':
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        if (empty($query)) api_error('请输入搜索关键词');
        
        $sql = "SELECT 
                    u.id, u.username, u.gender, u.motto, u.role_label,
                    (SELECT status FROM friends WHERE (user_id = ? AND friend_id = u.id) OR (user_id = u.id AND friend_id = ?) LIMIT 1) as relation_status
                FROM users u 
                WHERE u.username LIKE ? AND u.id != ? 
                LIMIT 20";
        $stmt = $conn->prepare($sql) or api_error('数据库准备失败: ' . $conn->error);
        $search = "%$query%";
        $stmt->bind_param("iisi", $user_id, $user_id, $search, $user_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $status = $row['relation_status'];
            if ($status === null) {
                $row['relation_status'] = 'none';
            } elseif ($status == 1) {
                $row['relation_status'] = 'friend';
            } else {
                $row['relation_status'] = 'pending';
            }
            $users[] = $row;
        }
        $resp = ['users' => $users];
        api_success($resp, '搜索成功', 200, $resp);
        break;

    case 'get_recommendations':
        // 发现/推荐逻辑：显示 users 表所有用户，排除自己，按 ID 排序，站长（ID=2）置顶
        $sql = "SELECT 
                    u.id, u.username, u.gender, u.motto, u.role_label,
                    (SELECT status FROM friends WHERE (user_id = ? AND friend_id = u.id) OR (user_id = u.id AND friend_id = ?) LIMIT 1) as relation_status
                FROM users u 
                WHERE u.id != ?
                ORDER BY CASE WHEN u.id = 2 THEN 0 ELSE 1 END, u.id ASC 
                LIMIT 100";
        $stmt = $conn->prepare($sql) or api_error('数据库准备失败: ' . $conn->error);
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $status = $row['relation_status'];
            if ($status === null) {
                $row['relation_status'] = 'none';
            } elseif ($status == 1) {
                $row['relation_status'] = 'friend';
            } else {
                $row['relation_status'] = 'pending';
            }
            $users[] = $row;
        }
        $resp = ['users' => $users];
        api_success($resp, '获取推荐成功', 200, $resp);
        break;

    // --- 好友申请处理 ---
    case 'send_request':
        $receiver_id = (int)($data['receiver_id'] ?? 0);
        if ($receiver_id <= 0 || $receiver_id === $user_id) api_error('无效的请求对象');

        // 检查是否已经是好友或已有申请 (使用 status: 0=申请中, 1=已同意)
        $stmt = $conn->prepare("SELECT status, user_id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['status'] == 1) api_error('你们已经是好友了');
            if ($row['user_id'] == $user_id) api_error('申请已发送，请耐心等待');
            else api_error('对方已向你发送申请，请在申请列表中处理');
        }
        $stmt->close();

        // 插入申请 (user_id 是申请人, status=0)
        $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 0)");
        $stmt->bind_param("ii", $user_id, $receiver_id);
        if ($stmt->execute()) {
            api_success([], '好友申请已发送');
        } else {
            api_error('申请发送失败');
        }
        break;

    case 'handle_request':
        $request_id = (int)($data['request_id'] ?? 0); // 注意：这里的 request_id 实际上是 friends 表的 id
        $status = $data['status'] ?? ''; // accepted or rejected
        if ($request_id <= 0 || !in_array($status, ['accepted', 'rejected'])) api_error('无效的操作');

        if ($status === 'accepted') {
            // 更新状态为 1 (已同意)
            $stmt = $conn->prepare("UPDATE friends SET status = 1 WHERE id = ? AND friend_id = ? AND status = 0");
            $stmt->bind_param("ii", $request_id, $user_id);
            if ($stmt->execute() && $conn->affected_rows > 0) {
                api_success([], '已接受好友申请');
            } else {
                api_error('申请不存在或已处理');
            }
        } else {
            // 拒绝则删除记录
            $stmt = $conn->prepare("DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 0");
            $stmt->bind_param("ii", $request_id, $user_id);
            if ($stmt->execute() && $conn->affected_rows > 0) {
                api_success([], '已拒绝好友申请');
            } else {
                api_error('申请不存在或已处理');
            }
        }
        break;

    case 'get_requests':
        // 获取当前用户收到的待处理申请 (friend_id 是被申请人, status=0)
        $stmt = $conn->prepare("SELECT f.id, f.user_id as sender_id, u.username, u.role_label, f.created_at 
                FROM friends f JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 0") or api_error('数据库准备失败: ' . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) $requests[] = $row;
        $resp = ['requests' => $requests];
        api_success($resp, '获取申请成功', 200, $resp);
        break;

    // --- 聊天功能 ---
    case 'send_message':
        $receiver_id = (int)($data['receiver_id'] ?? 0);
        $message = trim($data['message'] ?? '');
        if ($receiver_id <= 0 || empty($message)) api_error('消息内容不能为空');

        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $receiver_id, $message);
        if ($stmt->execute()) {
            $resp = ['id' => $conn->insert_id];
            api_success($resp, '发送成功', 200, $resp);
        } else {
            api_error('发送失败');
        }
        break;

    case 'get_messages':
        $friend_id = (int)($_GET['friend_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);
        if ($friend_id <= 0) api_error('无效的好友ID');

        // 获取消息
        $sql = "SELECT id, sender_id, receiver_id, message, created_at FROM chat_messages 
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
                AND id > ? ORDER BY created_at ASC LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $friend_id, $friend_id, $user_id, $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) $messages[] = $row;
        
        // 标记为已读
        $stmt_read = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt_read->bind_param("ii", $friend_id, $user_id);
        $stmt_read->execute();

        $resp = ['messages' => $messages];
        api_success($resp, '获取消息成功', 200, $resp);
        break;

    case 'send_group_msg':
        $message = trim($data['message'] ?? '');
        if (empty($message)) api_error('消息内容不能为空');

        // 使用 chat_messages 表，receiver_id = 0 表示公共聊天室
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, 0, ?)");
        $stmt->bind_param("is", $user_id, $message);
        if ($stmt->execute()) {
            $resp = ['id' => $conn->insert_id];
            api_success($resp, '发送成功', 200, $resp);
        } else {
            api_error('发送失败');
        }
        break;

    case 'get_unread_total':
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total = (int)$res['total'];
        $resp = ['unread_total' => $total, 'total' => $total]; // 兼容前端不同写法
        api_success($resp, '获取未读总数成功', 200, $resp);
        break;

    case 'get_friends':
        // 查询好友：必须是 status = 1，且当前用户是 user_id 或 friend_id
        // 增加 DISTINCT 确保同一个好友不会因为数据库异常记录而重复出现
        $sql = "SELECT DISTINCT
                    CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END as friend_id,
                    u.username, u.gender, u.motto, u.role_label,
                    (SELECT message FROM chat_messages 
                     WHERE (sender_id = ? AND receiver_id = (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END)) 
                        OR (sender_id = (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) AND receiver_id = ?) 
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT COUNT(*) FROM chat_messages 
                     WHERE sender_id = (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) 
                       AND receiver_id = ? AND is_read = 0) as unread_count
                FROM friends f 
                JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) = u.id 
                WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 1";
        
        $stmt = $conn->prepare($sql) or api_error('数据库准备失败: ' . $conn->error);
        // 参数绑定：1:uid, 2:uid, 3:uid, 4:uid, 5:uid, 6:uid, 7:uid, 8:uid, 9:uid, 10:uid
        $stmt->bind_param("iiiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        $friends = [];
        while ($row = $result->fetch_assoc()) $friends[] = $row;
        $resp = ['friends' => $friends];
        api_success($resp, '获取好友列表成功', 200, $resp);
        break;

    case 'get_group_messages':
        $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        // 使用 chat_messages 表，receiver_id = 0 表示公共聊天室
        $stmt = $conn->prepare("SELECT m.id, m.sender_id, m.message, m.created_at, u.username, u.role_label
                FROM chat_messages m JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = 0 AND m.id > ? ORDER BY m.created_at ASC LIMIT 100") or api_error('数据库准备失败: ' . $conn->error);
        $stmt->bind_param("i", $last_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) $messages[] = $row;
        $resp = ['messages' => $messages];
        api_success($resp, '获取群聊消息成功', 200, $resp);
        break;

    case 'get_current_user':
        $stmt = $conn->prepare("SELECT id, username, motto, role_label FROM users WHERE id = ?") or api_error('数据库准备失败: ' . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) api_error('未找到当前用户信息');
        api_success($user, '获取当前用户成功', 200, ['user' => $user]);
        break;

    case 'get_user_info':
        $target_id = (int)($_GET['user_id'] ?? 0);
        if ($target_id <= 0) api_error('参数错误');
        
        $stmt = $conn->prepare("SELECT id, username, motto, role_label FROM users WHERE id = ?") or api_error('数据库准备失败: ' . $conn->error);
        $stmt->bind_param("i", $target_id);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) api_error('用户不存在');
        api_success($user, '获取用户信息成功', 200, ['user' => $user]);
        break;

    case 'save_ai_chat':
        $user_message = trim($data['user_message'] ?? '');
        $ai_response = trim($data['ai_response'] ?? '');
        $model_used = trim($data['model_used'] ?? 'deepseek-chat');
        $temperature = isset($data['temperature']) ? (float)$data['temperature'] : 0.7;
        
        if (empty($user_message) || empty($ai_response)) api_error('消息内容不能为空');
        
        $stmt = $conn->prepare("INSERT INTO ai_chat_records (user_id, user_message, ai_response, model_used, temperature) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $user_message, $ai_response, $model_used, $temperature);
        if ($stmt->execute()) {
            $resp = ['id' => $conn->insert_id];
            api_success($resp, '记录保存成功', 200, $resp);
        } else {
            api_error('记录保存失败');
        }
        break;

    case 'get_ai_chat_history':
        $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
        if ($limit <= 0 || $limit > 100) $limit = 20;
        if ($offset < 0) $offset = 0;
        
        $stmt = $conn->prepare("SELECT id, user_message, ai_response, model_used, temperature, created_at 
                FROM ai_chat_records 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute() or api_error('查询执行失败: ' . $stmt->error);
        $result = $stmt->get_result();
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $resp = ['records' => $records];
        api_success($resp, '获取聊天记录成功', 200, $resp);
        break;

    default:
        api_error('未知的 API 指令: ' . $action);
        break;
}

// 释放连接资源
if (isset($conn)) $conn->close();
