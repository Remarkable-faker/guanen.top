<?php
/**
 * 管理后台 - 书籍申请处理 API
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 解析请求信息
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 3. API 鉴权逻辑 (跳过初始化)
if ($action !== 'init' && $action !== 'get_config') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        api_error('权限不足，请先登录管理员账号', 401);
    }
}

/**
 * 确保书籍管理相关的表结构正确 (统一使用 site_library)
 */
function ensure_admin_book_tables($conn) {
    // 基础校验：确保 site_library 和 bc_users 存在
    $conn->query("SHOW TABLES LIKE 'site_library'")->num_rows > 0 or die(json_encode(['code' => 500, 'msg' => '图书馆书籍主表缺失']));
    
    // 2. 确保 book_requests 表存在
    $sql_requests = "CREATE TABLE IF NOT EXISTS `book_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL COMMENT '申请人ID',
        `book_id` INT NOT NULL COMMENT '书籍ID',
        `type` ENUM('borrow', 'return') NOT NULL COMMENT '申请类型: borrow, return',
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT '处理状态',
        `address` TEXT COMMENT '收货地址',
        `tracking_number` VARCHAR(100) DEFAULT NULL COMMENT '快递单号',
        `admin_remark` TEXT COMMENT '管理员备注',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='图书借还申请表';";
    $conn->query($sql_requests) or die($conn->error);
}

// 执行结构检查
ensure_admin_book_tables($conn);

// 4. 基础配置获取
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_admin' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true,
        'server_time' => date('Y-m-d H:i:s')
    ));
}

// 5. A. 获取待处理申请列表 (GET)
if ($method === 'GET' && $action === 'get_pending') {
    // 联合查询书籍信息和用户信息 (改用 site_library 和 bc_users)
    $sql = "SELECT r.*, b.title as book_title, u.username as username 
            FROM book_requests r 
            JOIN site_library b ON r.book_id = b.id 
            JOIN bc_users u ON r.user_id = u.id 
            WHERE r.status = 'pending' 
            ORDER BY r.created_at DESC";
    
    $res = $conn->query($sql) or die($conn->error);
    $requests = [];
    while ($row = $res->fetch_assoc()) {
        $row['status_text'] = '待处理';
        $requests[] = $row;
    }
    api_success($requests);
}

// B. 审批操作 (处理批准或拒绝)
$request_id = (int)($_REQUEST['id'] ?? $_REQUEST['request_id'] ?? 0);
$tracking_number = trim($_REQUEST['tracking_number'] ?? '');

if ($request_id > 0 && in_array($action, ['approve', 'reject'])) {
    try {
        // 开启事务，确保书籍状态和申请状态同步更新
        $conn->begin_transaction();

        // 1. 获取申请详情并锁定记录
        $stmt = $conn->prepare("SELECT * FROM book_requests WHERE id = ? FOR UPDATE") or die($conn->error);
        $stmt->bind_param("i", $request_id);
        $stmt->execute() or die($stmt->error);
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) throw new Exception("未找到该申请记录");
        if ($request['status'] !== 'pending') throw new Exception("该申请已在处理中或已结束");

        $book_id = $request['book_id'];
        $type = $request['type']; // borrow (借) 或 return (还)

        if ($action === 'approve') {
            // --- 批准逻辑 ---
            if ($type === 'borrow') {
                // 借书批准：检查并更新书籍状态为“已借出”
                $book_stmt = $conn->prepare("SELECT status FROM site_library WHERE id = ? FOR UPDATE") or die($conn->error);
                $book_stmt->bind_param("i", $book_id);
                $book_stmt->execute() or die($book_stmt->error);
                $book = $book_stmt->get_result()->fetch_assoc();
                $book_stmt->close();

                if ($book['status'] !== 'available' && $book['status'] !== 'pending') {
                    throw new Exception("书籍当前不可借阅（状态：{$book['status']}）");
                }

                $conn->query("UPDATE site_library SET status = 'borrowed' WHERE id = $book_id") or die($conn->error);
            } else if ($type === 'return') {
                // 还书批准：书籍状态恢复为“可用”
                $conn->query("UPDATE site_library SET status = 'available' WHERE id = $book_id") or die($conn->error);
            }

            // 更新申请状态
            if (!empty($tracking_number)) {
                $stmt = $conn->prepare("UPDATE book_requests SET status = 'approved', tracking_number = ? WHERE id = ?") or die($conn->error);
                $stmt->bind_param("si", $tracking_number, $request_id);
            } else {
                $stmt = $conn->prepare("UPDATE book_requests SET status = 'approved' WHERE id = ?") or die($conn->error);
                $stmt->bind_param("i", $request_id);
            }
            $stmt->execute() or die($stmt->error);
            $stmt->close();
            $msg = '申请已批准成功';

        } else {
            // --- 拒绝逻辑 ---
            if ($type === 'borrow') {
                // 借书拒绝：释放书籍状态回“可用”
                $conn->query("UPDATE site_library SET status = 'available' WHERE id = $book_id") or die($conn->error);
            }
            
            $stmt = $conn->prepare("UPDATE book_requests SET status = 'rejected' WHERE id = ?") or die($conn->error);
            $stmt->bind_param("i", $request_id);
            $stmt->execute() or die($stmt->error);
            $stmt->close();
            $msg = '申请已拒绝处理';
        }

        $conn->commit();
        api_success(null, $msg);

    } catch (Exception $e) {
        $conn->rollback();
        api_error($e->getMessage());
    } finally {
        $conn->close();
    }
}

// 默认兜底
api_error('无效的操作请求');
