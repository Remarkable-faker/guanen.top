<?php
/**
 * 书籍漂流系统核心 API
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
error_reporting(E_ALL); 
ini_set('display_errors', 1);

// 3. 设置 JSON 响应头
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * 计算两点间的距离 (Haversine 公式)
 * 用于统计书籍漂流的总里程
 * 
 * @param float $lat1 起点纬度
 * @param float $lng1 起点经度
 * @param float $lat2 终点纬度
 * @param float $lng2 终点经度
 * @return float 距离 (公里)
 */
function calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/**
 * 自动检查并更新数据库结构 (兼容性检查)
 * 确保新数据库包含必要的扩展字段
 */
function auto_migrate($conn) {
    $columns = array(
        'bc_drift_logs' => array(
            'user_id' => "INT DEFAULT NULL COMMENT '用户ID'",
            'image_url' => "TEXT DEFAULT NULL COMMENT '节点图片'",
            'status' => "VARCHAR(50) DEFAULT NULL COMMENT '状态'",
            'likes' => "INT DEFAULT 0 COMMENT '点赞数'",
            'emotion' => "VARCHAR(50) DEFAULT NULL COMMENT '心情标签'"
        ),
        'bc_books' => array(
            'owner_id' => "INT DEFAULT NULL COMMENT '主有人ID'",
            'total_distance' => "DECIMAL(10, 2) DEFAULT 0 COMMENT '总里程'",
            'total_days' => "INT DEFAULT 0 COMMENT '总天数'",
            'reader_count' => "INT DEFAULT 0 COMMENT '读者总数'",
            'city_count' => "INT DEFAULT 0 COMMENT '城市总数'"
        )
    );

    foreach ($columns as $table => $cols) {
        foreach ($cols as $col => $def) {
            $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'") or die($conn->error);
            if ($check->num_rows == 0) {
                $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $def") or die($conn->error);
            }
        }
    }
}

/**
 * 确保书籍漂流相关表结构完整
 */
function ensure_bc_tables($conn) {
    // 检查 bc_books 表
    $conn->query("CREATE TABLE IF NOT EXISTS `bc_books` (
        `book_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `author` VARCHAR(255),
        `current_city` VARCHAR(100),
        `current_reader` VARCHAR(100),
        `status` VARCHAR(50) DEFAULT '阅读中',
        `lat` DECIMAL(10, 8),
        `lng` DECIMAL(11, 8),
        `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 检查并添加缺失列
    $columns = [
        'isbn' => "VARCHAR(20)",
        'category' => "VARCHAR(100)",
        'note' => "TEXT",
        'owner_id' => "INT COMMENT '所有者ID'",
        'cover_url' => "VARCHAR(255)"
    ];
    foreach ($columns as $col => $type) {
        $check = $conn->query("SHOW COLUMNS FROM `bc_books` LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `bc_books` ADD COLUMN `$col` $type");
        }
    }

    // 检查 bc_drift_logs 表
    $conn->query("CREATE TABLE IF NOT EXISTS `bc_drift_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `book_id` INT NOT NULL,
        `city` VARCHAR(100),
        `reader` VARCHAR(100),
        `lat` DECIMAL(10, 8),
        `lng` DECIMAL(11, 8),
        `event_desc` VARCHAR(255),
        `note` TEXT,
        `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $log_columns = [
        'image_url' => "VARCHAR(255)",
        'user_id' => "INT COMMENT '操作者ID'",
        'status' => "VARCHAR(50)",
        'emotion' => "VARCHAR(50)",
        'likes' => "INT DEFAULT 0"
    ];
    foreach ($log_columns as $col => $type) {
        $check = $conn->query("SHOW COLUMNS FROM `bc_drift_logs` LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `bc_drift_logs` ADD COLUMN `$col` $type");
        }
    }
}

// 执行结构检查
ensure_bc_tables($conn);

// 4. 解析客户端输入数据
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_REQUEST;
}

$action = isset($data['action']) ? $data['action'] : '';

// 5. API 鉴权逻辑 (跳过初始化与非敏感操作)
$public_actions = ['init', 'get_config', 'get_books', 'get_drift_logs', 'get_stats', 'get_book_stats', 'get_history'];
if (!in_array($action, $public_actions)) {
    // 使用核心 session.php 提供的统一鉴权函数
    if (!core_is_logged_in()) { 
        api_error('未登录或会话已过期', 401);
    }
}

// 执行数据库自动迁移/结构检查
auto_migrate($conn);

// 统一系统初始化接口
if ($action === 'init' || $action === 'get_config') {
    $is_logged_in = isset($_SESSION['user_id']);
    $data = array(
        'is_logged_in' => $is_logged_in,
        'user_id' => $is_logged_in ? $_SESSION['user_id'] : null,
        'username' => $is_logged_in ? $_SESSION['username'] : '',
        'nickname' => $is_logged_in ? (isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : $_SESSION['username']) : '',
        'config' => array(
            'allow_registration' => true
        )
    );
    api_success($data, '初始化成功', 200, $data);
}

// 核心业务处理
switch ($action) {
    case 'get_books':
        // --- 获取所有书籍列表 ---
        // 逻辑：查询 bc_books 表，按最后更新时间倒序排列
        $result = $conn->query("SELECT * FROM bc_books ORDER BY last_update DESC") or die($conn->error);
        $books = array();
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        api_success($books);
        break;

    case 'get_history':
        // --- 获取指定书籍的流转轨迹 ---
        // 逻辑：根据 book_id 查询 bc_drift_logs 表，并按时间正序排列展示流转过程
        $book_id = isset($data['book_id']) ? (int)$data['book_id'] : 0;
        if (!$book_id) api_error('缺少参数 book_id');
        
        $stmt = $conn->prepare("SELECT * FROM bc_drift_logs WHERE book_id = ? ORDER BY log_date ASC") or die($conn->error);
        $stmt->bind_param("i", $book_id);
        $stmt->execute() or die($stmt->error);
        $result = $stmt->get_result();
        
        $history = array();
        while ($row = $result->fetch_assoc()) {
            $history[] = array(
                'id' => $row['id'],
                'date' => date('Y-m-d', strtotime($row['log_date'])),
                'log_date' => $row['log_date'],
                'event_desc' => $row['event_desc'],
                'city' => $row['city'],
                'lng' => (float)$row['lng'],
                'lat' => (float)$row['lat'],
                'lnglat' => array((float)$row['lng'], (float)$row['lat']),
                'reader' => $row['reader'],
                'note' => $row['note'],
                'image_url' => $row['image_url'],
                'status' => $row['status'],
                'likes' => (int)$row['likes'],
                'emotion' => $row['emotion']
            );
        }
        $stmt->close();
        api_success($history);
        break;

    case 'get_book_stats':
        // --- 实时计算书籍统计数据 ---
        // 逻辑：拉取该书所有日志，通过经纬度计算累计里程，并统计独立城市和读者
        $book_id = isset($data['book_id']) ? (int)$data['book_id'] : 0;
        if (!$book_id) api_error('缺少参数 book_id');

        $stmt = $conn->prepare("SELECT * FROM bc_drift_logs WHERE book_id = ? ORDER BY log_date ASC") or die($conn->error);
        $stmt->bind_param("i", $book_id);
        $stmt->execute() or die($stmt->error);
        $result = $stmt->get_result();
        
        $logs = array();
        while ($row = $result->fetch_assoc()) { $logs[] = $row; }
        $stmt->close();

        $city_list = array();
        $reader_list = array();
        $total_distance = 0;
        $start_date = null;
        $end_date = null;

        for ($i = 0; $i < count($logs); $i++) {
            $city_list[] = $logs[$i]['city'];
            $reader_list[] = $logs[$i]['reader'];
            
            if ($i > 0) {
                $total_distance += calculate_distance(
                    $logs[$i-1]['lat'], $logs[$i-1]['lng'],
                    $logs[$i]['lat'], $logs[$i]['lng']
                );
            }
            
            $date = strtotime($logs[$i]['log_date']);
            if ($start_date === null || $date < $start_date) $start_date = $date;
            if ($end_date === null || $date > $end_date) $end_date = $date;
        }

        $unique_cities = array_unique(array_filter($city_list));
        $unique_readers = array_unique(array_filter($reader_list));
        $total_days = $start_date ? ceil(($end_date - $start_date) / 86400) : 0;

        api_success(array(
            'city_count' => count($unique_cities),
            'reader_count' => count($unique_readers),
            'total_distance' => round($total_distance, 2),
            'total_days' => (int)$total_days,
            'start_date' => $start_date ? date('Y-m-d H:i:s', $start_date) : null,
            'cities' => array_values($unique_cities)
        ));
        break;

    case 'register':
        // --- 登记新书上线 ---
        // 逻辑：开启事务，同时在 bc_books 和 bc_drift_logs 中创建初始记录
        $title = isset($data['title']) ? trim($data['title']) : '';
        $author = isset($data['author']) ? trim($data['author']) : '';
        $city = isset($data['city']) ? trim($data['city']) : '';
        // 优先使用 Session 中的昵称，确保与登录身份一致
        $reader = isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : (isset($data['reader']) ? trim($data['reader']) : '匿名用户');
        $isbn = isset($data['isbn']) ? trim($data['isbn']) : '';
        $category = isset($data['category']) ? trim($data['category']) : '';
        $lng = isset($data['lng']) ? (float)$data['lng'] : 0;
        $lat = isset($data['lat']) ? (float)$data['lat'] : 0;
        $note = isset($data['note']) ? trim($data['note']) : '开启一段新的旅程。';
        $owner_id = core_get_user_id();

        if (empty($title) || empty($author) || empty($city)) {
            api_error('书名、作者和初始城市不能为空');
        }

        $conn->begin_transaction();
        try {
            // 插入书籍主表
            $stmt = $conn->prepare("INSERT INTO bc_books (title, author, current_city, current_reader, status, lat, lng, isbn, category, note, owner_id) VALUES (?, ?, ?, ?, '阅读中', ?, ?, ?, ?, ?, ?)") or die($conn->error);
            $stmt->bind_param("ssssddssssi", $title, $author, $city, $reader, $lat, $lng, $isbn, $category, $note, $owner_id);
            $stmt->execute() or die($stmt->error);
            $new_book_id = $conn->insert_id;
            $stmt->close();

            // 插入首条流转日志
            $event_desc = '书籍登记上线';
            $stmt_log = $conn->prepare("INSERT INTO bc_drift_logs (book_id, city, reader, lat, lng, event_desc, note, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)") or die($conn->error);
            $log_note = '初始点位';
            $stmt_log->bind_param("issddssi", $new_book_id, $city, $reader, $lat, $lng, $event_desc, $log_note, $owner_id);
            $stmt_log->execute() or die($stmt_log->error);
            $stmt_log->close();

            $conn->commit();
            api_success(array('book_id' => $new_book_id), '书籍登记成功！');
        } catch (Exception $e) {
            $conn->rollback();
            api_error('登记失败: ' . $e->getMessage());
        }
        break;

    case 'update_status':
        // --- 更新书籍流转状态 ---
        // 逻辑：更新 bc_books 的当前位置和读者，并增加一条流转记录到 bc_drift_logs
        $book_id = isset($data['book_id']) ? (int)$data['book_id'] : 0;
        // 强制使用当前登录用户的身份，防止伪造
        $reader = isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : (isset($data['reader']) ? trim($data['reader']) : '');
        $city = isset($data['city']) ? trim($data['city']) : '';
        $status = isset($data['status']) ? trim($data['status']) : '阅读中';
        $lng = isset($data['lng']) ? (float)$data['lng'] : 0;
        $lat = isset($data['lat']) ? (float)$data['lat'] : 0;
        $note = isset($data['note']) ? trim($data['note']) : '';
        $image_url = isset($data['image_url']) ? trim($data['image_url']) : '';
        $user_id = core_get_user_id();
        $emotion = isset($data['emotion']) ? trim($data['emotion']) : '';

        if (!$book_id || empty($reader) || empty($city)) {
            api_error('参数不完整，书籍ID、读者和城市为必填项');
        }

        $conn->begin_transaction();
        try {
            // 更新书籍状态
            $stmt = $conn->prepare("UPDATE bc_books SET current_city = ?, current_reader = ?, status = ?, lat = ?, lng = ?, last_update = CURRENT_TIMESTAMP WHERE book_id = ?") or die($conn->error);
            $stmt->bind_param("sssddi", $city, $reader, $status, $lat, $lng, $book_id);
            $stmt->execute() or die($stmt->error);
            $stmt->close();

            // 记录流转日志
            $event_desc = "状态变更为: " . $status;
            $stmt_log = $conn->prepare("INSERT INTO bc_drift_logs (book_id, city, reader, lat, lng, event_desc, note, image_url, user_id, status, emotion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)") or die($conn->error);
            $stmt_log->bind_param("issddssssis", $book_id, $city, $reader, $lat, $lng, $event_desc, $note, $image_url, $user_id, $status, $emotion);
            $stmt_log->execute() or die($stmt_log->error);
            $stmt_log->close();

            $conn->commit();
            api_success(null, '流转记录更新成功！');
        } catch (Exception $e) {
            $conn->rollback();
            api_error('更新失败: ' . $e->getMessage());
        }
        break;

    case 'upload_image':
        // 上传节点图片
        if (empty($_FILES['image'])) api_error('无效的文件上传请求');

        $target_dir = dirname(__DIR__) . "/uploads/bc_nodes/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $public_url = "uploads/bc_nodes/" . $new_filename;
            api_success(array('url' => $public_url), '图片上传成功');
        } else {
            api_error('图片保存失败，请检查目录权限');
        }
        break;

    case 'get_all_nodes':
        // --- 获取所有漂流节点 (地图星点展示) ---
        $result = $conn->query("SELECT * FROM bc_drift_logs ORDER BY log_date DESC") or die($conn->error);
        $nodes = array();
        while ($row = $result->fetch_assoc()) {
            $nodes[] = $row;
        }
        api_success($nodes);
        break;

    case 'pick_star':
        // --- 拾星收藏功能 ---
        if (!isset($_SESSION['user_id'])) api_error('请先登录后操作', 401);
        $user_id = $_SESSION['user_id'];
        $node_id = isset($data['node_id']) ? (int)$data['node_id'] : 0;
        
        // 确保收藏表存在
        $conn->query("CREATE TABLE IF NOT EXISTS `bc_picked_stars` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `node_id` INT NOT NULL,
            `pick_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_pick` (`user_id`, `node_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;") or die($conn->error);

        $stmt = $conn->prepare("INSERT IGNORE INTO bc_picked_stars (user_id, node_id) VALUES (?, ?)") or die($conn->error);
        $stmt->bind_param("ii", $user_id, $node_id);
        $stmt->execute() or die($stmt->error);
        
        if ($stmt->affected_rows > 0) {
            api_success(null, '拾星成功，已收入你的星图');
        } else {
            api_success(null, '这颗星已在你的星图中');
        }
        $stmt->close();
        break;

    case 'like_node':
        // --- 节点点赞功能 ---
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) api_error('缺少节点 ID');
        
        $stmt = $conn->prepare("UPDATE bc_drift_logs SET likes = likes + 1 WHERE id = ?") or die($conn->error);
        $stmt->bind_param("i", $id);
        $stmt->execute() or die($stmt->error);
        $stmt->close();
        api_success(null, '点赞成功');
        break;

    default:
        api_error('未知的操作指令: ' . $action);
        break;
}

// 释放连接资源
if (isset($conn)) $conn->close();
