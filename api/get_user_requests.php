<?php
// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 解析请求信息
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 3. API 鉴权逻辑 (跳过初始化)
if ($action !== 'init' && $action !== 'get_config') {
    // 藏书阁需要验证漂流系统登录
    if (!isset($_SESSION['bc_user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '请先登录漂流系统'])); 
    }
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// 统一标准：获取初始化配置 and 登录状态
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => $user_id !== null,
        'user_id' => $user_id,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'limit' => 50
        )
    ));
}

// 4. 业务逻辑开始
$conn = db_connect();

/**
 * 确保申请记录表存在
 */
function ensure_requests_table($conn) {
    // 基础校验：确保 site_library 存在
    $conn->query("SHOW TABLES LIKE 'site_library'")->num_rows > 0 or die(json_encode(['code' => 500, 'msg' => '图书馆书籍主表缺失']));

    $create_sql = "CREATE TABLE IF NOT EXISTS `book_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `book_id` INT NOT NULL,
        `type` ENUM('borrow', 'return') NOT NULL,
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `admin_remark` TEXT,
        INDEX (`user_id`),
        INDEX (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_sql) or die($conn->error);
}

// 执行初始化
ensure_requests_table($conn);

try {
    // 联合查询书籍标题 (统一指向 site_library)
    $sql = "SELECT r.*, b.title as book_title 
            FROM book_requests r 
            JOIN site_library b ON r.book_id = b.id 
            WHERE r.user_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("数据库查询准备失败: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("数据库查询执行失败: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $requests = array();
    while ($row = $result->fetch_assoc()) {
        // 字段兼容性处理 (兼容旧版字段名)
        if (isset($row['request_date'])) $row['created_at'] = $row['request_date'];
        if (isset($row['process_date'])) $row['updated_at'] = $row['process_date'];
        $requests[] = $row;
    }
    
    // 返回成功响应
    api_success($requests);

} catch (Exception $e) {
    // 捕获异常并返回统一错误
    api_error('获取申请记录失败: ' . $e->getMessage(), 200);
}
