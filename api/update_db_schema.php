<?php
/**
 * 数据库结构更新脚本：添加借阅功能所需的表和字段
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 权限验证
if (!isset($_SESSION['user_id'])) {
    api_error('Unauthorized', 401);
}

// 设置 JSON 响应头
header('Content-Type: application/json; charset=utf-8');

// 用于存储执行日志
$logs = array();

/**
 * 记录日志信息
 * @param string $msg 日志内容
 */
function log_msg(&$logs, $msg) {
    $logs[] = $msg;
}

try {
    $conn = db_connect();
    if (!$conn) {
        send_error("数据库连接失败");
    }

    // 1. 为 site_library 表添加 status 字段（如果不存在）
    $check_column = $conn->query("SHOW COLUMNS FROM `site_library` LIKE 'status'");
    if ($check_column && $check_column->num_rows == 0) {
        $add_status_sql = "ALTER TABLE `site_library` ADD COLUMN `status` VARCHAR(20) DEFAULT 'available' AFTER `rating`";
        if (!$conn->query($add_status_sql)) {
            throw new Exception("为 site_library 添加 status 字段失败: " . $conn->error);
        }
        log_msg($logs, "已为 `site_library` 添加 `status` 字段");
    } else {
        log_msg($logs, "`site_library` 已存在 `status` 字段或表不存在");
    }

    // 2. 创建 book_requests 表
    $create_requests_table_sql = "CREATE TABLE IF NOT EXISTS `book_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `book_id` INT NOT NULL,
        `type` ENUM('borrow', 'return') NOT NULL,
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        `admin_remark` TEXT,
        FOREIGN KEY (`book_id`) REFERENCES `site_library`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($create_requests_table_sql)) {
        throw new Exception("创建 book_requests 表失败: " . $conn->error);
    }
    
    // 检查并更新旧字段名 (如果是从旧版本迁移)
    $check_req_date = $conn->query("SHOW COLUMNS FROM `book_requests` LIKE 'request_date'");
    if ($check_req_date && $check_req_date->num_rows > 0) {
        if (!$conn->query("ALTER TABLE `book_requests` CHANGE `request_date` `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP")) {
            log_msg($logs, "重命名 request_date 失败: " . $conn->error);
        } else {
            log_msg($logs, "已将 `request_date` 重命名为 `created_at`项目");
        }
    }
    
    $check_proc_date = $conn->query("SHOW COLUMNS FROM `book_requests` LIKE 'process_date'");
    if ($check_proc_date && $check_proc_date->num_rows > 0) {
        if (!$conn->query("ALTER TABLE `book_requests` CHANGE `process_date` `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP")) {
            log_msg($logs, "重命名 process_date 失败: " . $conn->error);
        } else {
            log_msg($logs, "已将 `process_date` 重命名为 `updated_at`项目");
        }
    }

    log_msg($logs, "表 `book_requests` 已就绪");
    log_msg($logs, "数据库结构更新成功！");

    send_success("数据库更新完成", array("logs" => $logs));

} catch (Exception $e) {
    send_error("更新过程中出错: " . $e->getMessage(), array("logs" => $logs));
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
