<?php
/**
 * 书籍交互操作 API
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

// 3. 解析请求路由与参数
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_REQUEST;
}
$action = isset($data['action']) ? $data['action'] : '';

// 4. API 鉴权逻辑 (跳过初始化)
if ($action !== 'init' && $action !== 'get_config') {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '未登录或会话已过期'])); 
    }
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// 提取通用参数
$book_id = (int)(isset($data['book_id']) ? $data['book_id'] : 0);
$address = isset($data['address']) ? trim($data['address']) : '';
$tracking_number = isset($data['tracking_number']) ? trim($data['tracking_number']) : '';

// 统一系统初始化配置接口
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => $user_id !== null,
        'user_id' => $user_id,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'can_borrow' => true,
            'max_books' => 3
        )
    ));
}

// 参数校验：除了初始化接口，其他接口必须提供 book_id
if (!$book_id && !in_array($action, ['init', 'get_config'])) {
    api_error('无效的书籍 ID');
}

/**
 * 自动维护表结构 (兼容性检查)
 * 确保 site_library 和 book_requests 表及其字段存在
 */
function ensure_request_tables($conn) {
    // 基础校验：确保 site_library 存在
    $conn->query("SHOW TABLES LIKE 'site_library'")->num_rows > 0 or die(json_encode(['code' => 500, 'msg' => '图书馆书籍主表缺失']));

    // 确保 book_requests 表存在
    $create_sql = "CREATE TABLE IF NOT EXISTS `book_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL COMMENT '用户ID',
        `book_id` INT NOT NULL COMMENT '书籍ID',
        `type` ENUM('borrow', 'return') NOT NULL COMMENT '类型',
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT '状态',
        `address` TEXT COMMENT '收货地址',
        `tracking_number` VARCHAR(100) DEFAULT NULL COMMENT '快递单号',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `admin_remark` TEXT,
        INDEX (`user_id`),
        INDEX (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='书籍借阅申请表';";
    
    $conn->query($create_sql) or die($conn->error);
}

// 执行结构检查
ensure_request_tables($conn);

// 5. 核心业务处理
try {
    if ($action !== 'borrow' && $action !== 'return') {
        api_error('未知的操作指令: ' . $action);
    }
    
    // 开启事务处理，确保数据一致性
    $conn->begin_transaction();

    // 1. 获取书籍当前状态 (从 site_library 获取) 并锁定行 (FOR UPDATE)
    $stmt = $conn->prepare("SELECT title, status FROM site_library WHERE id = ? FOR UPDATE") or die($conn->error);
    $stmt->bind_param("i", $book_id);
    $stmt->execute() or die($stmt->error);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception("书籍不存在或已被移除");
    }
    
    $book = $result->fetch_assoc();
    $title = $book['title'];
    $current_status = $book['status'];
    $stmt->close();

    if ($action === 'borrow') {
        // --- 借阅申请逻辑 ---
        if ($current_status !== 'available') {
            throw new Exception("该书籍当前不可借阅（状态：{$current_status}）");
        }
        
        // 检查重复申请
        $check_stmt = $conn->prepare("SELECT id FROM book_requests WHERE user_id = ? AND book_id = ? AND type = 'borrow' AND status = 'pending'") or die($conn->error);
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute() or die($check_stmt->error);
        if ($check_stmt->get_result()->num_rows > 0) {
            $check_stmt->close();
            throw new Exception("您已提交过该书的借阅申请，请耐心等待审核");
        }
        $check_stmt->close();

        // 插入借阅申请记录
        $insert_stmt = $conn->prepare("INSERT INTO book_requests (user_id, book_id, type, status, address) VALUES (?, ?, 'borrow', 'pending', ?)") or die($conn->error);
        $insert_stmt->bind_param("iis", $user_id, $book_id, $address);
        $insert_stmt->execute() or die($insert_stmt->error);
        $insert_stmt->close();

        // 锁定书籍状态为 pending (申请中)，防止其他人再次发起申请 (指向 site_library)
        $update_book = $conn->prepare("UPDATE site_library SET status = 'pending' WHERE id = ?") or die($conn->error);
        $update_book->bind_param("i", $book_id);
        $update_book->execute() or die($update_book->error);
        $update_book->close();
        
        $conn->commit();
        api_success(null, "《{$title}》借阅申请已提交，请等待管理员审核");

    } elseif ($action === 'return') {
        // --- 归还申请逻辑 ---
        if ($current_status !== 'borrowed') {
             throw new Exception("该书籍未处于借出状态，无需归还");
        }
        
        // 插入归还申请记录
        $insert_stmt = $conn->prepare("INSERT INTO book_requests (user_id, book_id, type, status, tracking_number) VALUES (?, ?, 'return', 'pending', ?)") or die($conn->error);
        $insert_stmt->bind_param("iis", $user_id, $book_id, $tracking_number);
        $insert_stmt->execute() or die($insert_stmt->error);
        $insert_stmt->close();

        $conn->commit();
        api_success(null, "《{$title}》归还申请已提交，请将书籍归还至指定地点并上传快递信息");
    }

} catch (Exception $e) {
    // 发生任何异常，回滚数据库操作
    if (isset($conn)) $conn->rollback();
    api_error($e->getMessage());
} finally {
    // 最终关闭连接资源
    if (isset($conn)) $conn->close();
}
