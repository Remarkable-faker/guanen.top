<?php
/**
 * 大富翁游戏数据 API
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

// 3. 解析请求路由
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;
$action = isset($data['action']) ? $data['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// 4. API 鉴权逻辑 (跳过初始化)
if ($action !== 'init' && $action !== 'get_config') {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '未登录或会话已过期'])); 
    }
}

/**
 * 确保游戏相关的表结构存在
 */
function ensure_monopoly_tables($conn) {
    // 1. 房间表
    $sql_rooms = "CREATE TABLE IF NOT EXISTS `monopoly_rooms` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `room_name` VARCHAR(50) NOT NULL COMMENT '房间名称',
        `status` ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting' COMMENT '房间状态',
        `current_turn` VARCHAR(50) DEFAULT NULL COMMENT '当前回合玩家名',
        `chat` LONGTEXT COMMENT '房间聊天记录(JSON)',
        `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` VARCHAR(50) NOT NULL COMMENT '房主用户名'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大富翁房间表';";
    $conn->query($sql_rooms) or die($conn->error);

    // 2. 玩家游戏状态表
    $sql_users = "CREATE TABLE IF NOT EXISTS `monopoly_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL COMMENT '用户名',
        `room_id` INT DEFAULT NULL COMMENT '所属房间ID',
        `coins` INT DEFAULT 15000 COMMENT '金币数',
        `position` INT DEFAULT 0 COMMENT '当前位置索引',
        `properties` TEXT COMMENT '拥有的房产(JSON)',
        `in_jail` INT DEFAULT 0 COMMENT '入狱剩余回合',
        `is_ready` BOOLEAN DEFAULT FALSE COMMENT '是否已准备',
        `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后心跳时间',
        UNIQUE KEY `username_unique` (`username`),
        INDEX (`room_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大富翁玩家状态表';";
    $conn->query($sql_users) or die($conn->error);

    // 3. 动态补全字段 (针对旧表升级)
    $columns_to_check = [
        ['monopoly_rooms', 'chat', "LONGTEXT AFTER `current_turn`"],
        ['monopoly_users', 'room_id', "INT DEFAULT NULL AFTER `username`"],
        ['monopoly_users', 'in_jail', "INT DEFAULT 0 AFTER `properties`"],
        ['monopoly_users', 'is_ready', "BOOLEAN DEFAULT FALSE AFTER `in_jail`"],
        ['monopoly_users', 'last_ping', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"]
    ];

    foreach ($columns_to_check as $col) {
        $res = $conn->query("SHOW COLUMNS FROM `{$col[0]}` LIKE '{$col[1]}'") or die($conn->error);
        if ($res->num_rows == 0) {
            $conn->query("ALTER TABLE `{$col[0]}` ADD COLUMN `{$col[1]}` {$col[2]}") or die($conn->error);
        }
    }
}

// 执行结构检查
ensure_monopoly_tables($conn);

// 5. 定时清理：超时玩家（超过 30 秒无响应视为离线，强制退出房间）
$conn->query("UPDATE monopoly_users SET room_id = NULL WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)") or die($conn->error);

// 6. 路由分发逻辑
$method = $_SERVER['REQUEST_METHOD'];

// 初始化配置
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'initial_coins' => 15000,
            'ping_interval' => 5000
        )
    ));
}

// 7. 核心业务处理
try {
    if ($method === 'GET') {
        // --- GET 请求处理 ---
        
        if ($action === 'list_rooms') {
            // 获取待加入或正在进行的房间列表
            $sql = "SELECT r.*, (SELECT COUNT(*) FROM monopoly_users u WHERE u.room_id = r.id) as player_count 
                    FROM monopoly_rooms r WHERE r.status != 'finished'";
            $res = $conn->query($sql) or die($conn->error);
            $rooms = [];
            while ($row = $res->fetch_assoc()) $rooms[] = $row;
            api_success(['rooms' => $rooms]);
        }
        
        if ($action === 'get_room_state') {
            // 获取特定房间的实时状态（包含玩家列表和聊天记录）
            $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
            $username = isset($_GET['username']) ? $_GET['username'] : '';
            if (!$room_id || !$username) api_error('缺少必要参数');

            // 1. 更新当前玩家的心跳时间
            $stmt = $conn->prepare("UPDATE monopoly_users SET last_ping = CURRENT_TIMESTAMP WHERE username = ?") or die($conn->error);
            $stmt->bind_param("s", $username);
            $stmt->execute() or die($stmt->error);
            $stmt->close();
            
            // 2. 获取房间信息
            $stmt = $conn->prepare("SELECT * FROM monopoly_rooms WHERE id = ?") or die($conn->error);
            $stmt->bind_param("i", $room_id);
            $stmt->execute() or die($stmt->error);
            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($room) {
                $room['chat'] = json_decode($room['chat'] ?: '[]', true);
            }
            
            // 3. 获取房间内所有活跃玩家状态
            $stmt = $conn->prepare("SELECT username, coins, position, properties, in_jail, is_ready FROM monopoly_users WHERE room_id = ?") or die($conn->error);
            $stmt->bind_param("i", $room_id);
            $stmt->execute() or die($stmt->error);
            $res = $stmt->get_result();
            $players = [];
            while ($p = $res->fetch_assoc()) {
                $p['properties'] = json_decode($p['properties'] ?: '[]', true);
                $p['in_jail'] = (int)$p['in_jail'];
                $players[] = $p;
            }
            $stmt->close();
            
            api_success(['room' => $room, 'players' => $players]);
        }

    } elseif ($method === 'POST') {
        // --- POST 请求处理 ---

        if ($action === 'create_room') {
            // 创建新游戏房间
            $room_name = trim($data['room_name'] ?? '未命名房间');
            $username = trim($data['username'] ?? '');
            if (!$username) api_error('用户名无效');

            // 1. 确保玩家记录存在
            $stmt = $conn->prepare("INSERT INTO monopoly_users (username, coins, position, properties) VALUES (?, 15000, 0, '[]') ON DUPLICATE KEY UPDATE last_ping = CURRENT_TIMESTAMP") or die($conn->error);
            $stmt->bind_param("s", $username);
            $stmt->execute() or die($stmt->error);
            $stmt->close();

            // 2. 插入房间记录
            $stmt = $conn->prepare("INSERT INTO monopoly_rooms (room_name, created_by, status) VALUES (?, ?, 'waiting')") or die($conn->error);
            $stmt->bind_param("ss", $room_name, $username);
            if ($stmt->execute()) {
                $room_id = $conn->insert_id;
                $stmt->close();
                
                // 3. 房主自动加入房间并设置为准备状态
                $stmt = $conn->prepare("UPDATE monopoly_users SET room_id = ?, is_ready = 1, coins = 15000, position = 0, properties = '[]' WHERE username = ?") or die($conn->error);
                $stmt->bind_param("is", $room_id, $username);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
                
                api_success(['room_id' => $room_id]);
            } else {
                die($stmt->error);
            }
        }

        if ($action === 'join_room') {
            // 加入已存在的等待中房间
            $room_id = (int)($data['room_id'] ?? 0);
            $username = trim($data['username'] ?? '');
            if (!$room_id || !$username) api_error('参数缺失');

            // 检查房间是否存在且处于等待加入状态
            $stmt = $conn->prepare("SELECT status FROM monopoly_rooms WHERE id = ?") or die($conn->error);
            $stmt->bind_param("i", $room_id);
            $stmt->execute() or die($stmt->error);
            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($room && $room['status'] === 'waiting') {
                // 更新玩家状态，加入房间
                $stmt = $conn->prepare("INSERT INTO monopoly_users (username, room_id, is_ready, coins, position, properties) 
                        VALUES (?, ?, 1, 15000, 0, '[]') 
                        ON DUPLICATE KEY UPDATE room_id = ?, is_ready = 1, coins = 15000, position = 0, properties = '[]'") or die($conn->error);
                $stmt->bind_param("sii", $username, $room_id, $room_id);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
                api_success(null, '已成功加入房间');
            } else {
                api_error('房间已满、已开始或不存在');
            }
        }

        if ($action === 'start_game') {
            // 房主发起：正式开始游戏
            $room_id = (int)($data['room_id'] ?? 0);
            $username = trim($data['username'] ?? '');
            
            // 验证房主身份
            $stmt = $conn->prepare("SELECT created_by FROM monopoly_rooms WHERE id = ?") or die($conn->error);
            $stmt->bind_param("i", $room_id);
            $stmt->execute() or die($stmt->error);
            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($room && $room['created_by'] === $username) {
                // 检查玩家人数是否达标
                $res = $conn->query("SELECT username FROM monopoly_users WHERE room_id = $room_id ORDER BY id ASC") or die($conn->error);
                if ($res->num_rows < 2) api_error('至少需要 2 名玩家才能开始');
                
                $players = [];
                while ($p = $res->fetch_assoc()) $players[] = $p['username'];
                $first_player = $players[0];

                // 更新房间状态为“游戏中”，并设置初始回合玩家
                $stmt = $conn->prepare("UPDATE monopoly_rooms SET status = 'playing', current_turn = ? WHERE id = ?") or die($conn->error);
                $stmt->bind_param("si", $first_player, $room_id);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
                api_success(null, '游戏已开始，好运！');
            } else {
                api_error('只有房主可以开始游戏');
            }
        }

        if ($action === 'sync_state') {
            // 玩家行动后的状态同步逻辑
            $username   = $data['username'] ?? '';
            $room_id    = (int)($data['room_id'] ?? 0);
            $coins      = (int)($data['coins'] ?? 0);
            $position   = (int)($data['position'] ?? 0);
            $properties = json_encode($data['properties'] ?? []);
            $in_jail    = (int)($data['in_jail'] ?? 0);
            $pass_turn  = !empty($data['pass_turn']);
            $pay_to     = $data['pay_to'] ?? ''; // 租金支付对象
            $amount     = (int)($data['amount'] ?? 0);
            
            // 1. 更新当前活跃玩家的基础属性
            $stmt = $conn->prepare("UPDATE monopoly_users SET coins = ?, position = ?, properties = ?, in_jail = ?, last_ping = CURRENT_TIMESTAMP WHERE username = ?") or die($conn->error);
            $stmt->bind_param("iisss", $coins, $position, $properties, $in_jail, $username);
            $stmt->execute() or die($stmt->error);
            $stmt->close();
            
            // 2. 如果涉及租金支付，更新收款人金币
            if (!empty($pay_to) && $amount > 0) {
                $stmt = $conn->prepare("UPDATE monopoly_users SET coins = coins + ? WHERE username = ? AND room_id = ?") or die($conn->error);
                $stmt->bind_param("isi", $amount, $pay_to, $room_id);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
            }
            
            // 3. 处理回合切换
            if ($pass_turn) {
                // 获取房间内所有在线玩家，确定下一个出手的人
                $res = $conn->query("SELECT username FROM monopoly_users WHERE room_id = $room_id AND last_ping > DATE_SUB(NOW(), INTERVAL 30 SECOND) ORDER BY id ASC") or die($conn->error);
                $players = [];
                while ($p = $res->fetch_assoc()) $players[] = $p['username'];
                
                if (count($players) > 0) {
                    $current_idx = array_search($username, $players);
                    $next_idx = ($current_idx === false) ? 0 : ($current_idx + 1) % count($players);
                    $next_player = $players[$next_idx];
                    
                    $stmt = $conn->prepare("UPDATE monopoly_rooms SET current_turn = ? WHERE id = ?") or die($conn->error);
                    $stmt->bind_param("si", $next_player, $room_id);
                    $stmt->execute() or die($stmt->error);
                    $stmt->close();
                }
            }
            api_success(null);
        }

        if ($action === 'send_chat') {
            // 发送房间聊天消息
            $room_id = (int)($data['room_id'] ?? 0);
            $username = $data['username'] ?? '';
            $msg = $data['message'] ?? '';
            if (!$room_id || !$msg) api_error('发送内容无效');
            
            // 读取现有聊天记录
            $stmt = $conn->prepare("SELECT chat FROM monopoly_rooms WHERE id = ?") or die($conn->error);
            $stmt->bind_param("i", $room_id);
            $stmt->execute() or die($stmt->error);
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $chat = json_decode($row['chat'] ?: '[]', true);
                $chat[] = ['u' => $username, 'm' => $msg, 't' => time()];
                
                // 只保留最近 50 条记录
                if (count($chat) > 50) $chat = array_slice($chat, -50);
                
                $chat_json = json_encode($chat);
                $stmt = $conn->prepare("UPDATE monopoly_rooms SET chat = ? WHERE id = ?") or die($conn->error);
                $stmt->bind_param("si", $chat_json, $room_id);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
            }
            api_success(null);
        }

        if ($action === 'save_user') {
            // 兼容性接口：手动保存玩家单机/持久化数据
            $username = $data['username'] ?? '';
            $coins = (int)($data['coins'] ?? 0);
            $position = (int)($data['position'] ?? 0);
            $properties = json_encode($data['properties'] ?? []);
            
            $stmt = $conn->prepare("INSERT INTO monopoly_users (username, coins, position, properties) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE coins = ?, position = ?, properties = ?") or die($conn->error);
            $stmt->bind_param("siisiis", $username, $coins, $position, $properties, $coins, $position, $properties);
            $stmt->execute() or die($stmt->error);
            $stmt->close();
            api_success(null, '数据已云同步');
        }

    } else {
        api_error('不支持的请求方法: ' . $method);
    }
} catch (Exception $e) {
    api_error('游戏逻辑执行异常: ' . $e->getMessage());
}

// 释放连接资源
if (isset($conn)) $conn->close();
