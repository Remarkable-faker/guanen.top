<?php
/**
 * 书籍漂流系统初始化脚本
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);

// 权限验证
if (!isset($_SESSION['user_id'])) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array(
        'success' => false,
        'code' => 401,
        'msg' => 'Unauthorized: 请先登录'
    ));
    exit;
}

function init_book_crossing_database() {
    $conn = db_connect();
    $messages = array();

    // 1. 创建书籍基础信息表 (bc_books)
    // 强制更新 schema
    $sql_books = "CREATE TABLE IF NOT EXISTS `bc_books` (
        `book_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL COMMENT '书名',
        `author` VARCHAR(255) NOT NULL COMMENT '作者',
        `current_city` VARCHAR(100) DEFAULT NULL COMMENT '当前所在城市',
        `current_reader` VARCHAR(100) DEFAULT NULL COMMENT '当前阅读者',
        `status` VARCHAR(50) DEFAULT '阅读中' COMMENT '当前状态',
        `lat` DECIMAL(10, 7) DEFAULT NULL COMMENT '当前纬度',
        `lng` DECIMAL(10, 7) DEFAULT NULL COMMENT '当前经度',
        `isbn` VARCHAR(20) DEFAULT NULL COMMENT 'ISBN',
        `category` VARCHAR(100) DEFAULT NULL COMMENT '分类',
        `note` TEXT DEFAULT NULL COMMENT '书籍简介/初始寄语',
        `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql_books) === TRUE) {
        $messages[] = "表 bc_books 已就绪。";
    } else {
        $messages[] = "表 bc_books 操作失败: " . $conn->error;
    }

    // 2. 创建漂流轨迹记录表 (bc_drift_logs)
    $sql_logs = "CREATE TABLE IF NOT EXISTS `bc_drift_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `book_id` INT NOT NULL COMMENT '关联书籍ID',
        `city` VARCHAR(100) NOT NULL COMMENT '所在城市',
        `reader` VARCHAR(100) DEFAULT NULL COMMENT '阅读者',
        `lat` DECIMAL(10, 7) DEFAULT NULL COMMENT '纬度',
        `lng` DECIMAL(10, 7) DEFAULT NULL COMMENT '经度',
        `event_desc` VARCHAR(255) NOT NULL COMMENT '事件描述',
        `note` TEXT DEFAULT NULL COMMENT '备注',
        `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
        INDEX `idx_book_id` (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql_logs) === TRUE) {
        $messages[] = "表 bc_drift_logs 已就绪。";
    } else {
        $messages[] = "表 bc_drift_logs 操作失败: " . $conn->error;
    }

    // 检查并确保 book_id 有 AUTO_INCREMENT
    $result = $conn->query("SHOW COLUMNS FROM bc_books WHERE Field = 'book_id'");
    if (!$result) {
        $messages[] = "检查 bc_books 列失败: " . $conn->error;
    } else {
        $column = $result->fetch_assoc();
        if ($column && strpos($column['Extra'], 'auto_increment') === false) {
            if (!$conn->query("ALTER TABLE bc_books MODIFY COLUMN `book_id` INT AUTO_INCREMENT")) {
                $messages[] = "修复 bc_books AUTO_INCREMENT 失败: " . $conn->error;
            } else {
                $messages[] = "已修复 bc_books 的 book_id 为 AUTO_INCREMENT。";
            }
        }
    }

    // 检查并确保 bc_drift_logs 的 id 有 AUTO_INCREMENT
    $result = $conn->query("SHOW COLUMNS FROM bc_drift_logs WHERE Field = 'id'");
    if (!$result) {
        $messages[] = "检查 bc_drift_logs 列失败: " . $conn->error;
    } else {
        $column = $result->fetch_assoc();
        if ($column && strpos($column['Extra'], 'auto_increment') === false) {
            if (!$conn->query("ALTER TABLE bc_drift_logs MODIFY COLUMN `id` INT AUTO_INCREMENT")) {
                $messages[] = "修复 bc_drift_logs AUTO_INCREMENT 失败: " . $conn->error;
            } else {
                $messages[] = "已修复 bc_drift_logs 的 id 为 AUTO_INCREMENT。";
            }
        }
    }

    // 检查并添加缺失的列 (如果表已存在但结构不同)
    $result = $conn->query("SHOW COLUMNS FROM bc_books LIKE 'last_update'");
    if (!$result) {
        $messages[] = "检查 bc_books last_update 失败: " . $conn->error;
    } else {
        if ($result->num_rows == 0) {
            if (!$conn->query("ALTER TABLE bc_books ADD COLUMN `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP")) {
                $messages[] = "添加 last_update 列失败: " . $conn->error;
            } else {
                $messages[] = "已为 bc_books 添加 last_update 列。";
            }
        }
    }
    
    $result = $conn->query("SHOW COLUMNS FROM bc_books LIKE 'lat'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE bc_books ADD COLUMN `lat` DECIMAL(10, 7) DEFAULT NULL");
        $conn->query("ALTER TABLE bc_books ADD COLUMN `lng` DECIMAL(10, 7) DEFAULT NULL");
        $messages[] = "已为 bc_books 添加坐标列。";
    }

    $result = $conn->query("SHOW COLUMNS FROM bc_books LIKE 'isbn'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE bc_books ADD COLUMN `isbn` VARCHAR(20) DEFAULT NULL AFTER `lng` ");
        $conn->query("ALTER TABLE bc_books ADD COLUMN `category` VARCHAR(100) DEFAULT NULL AFTER `isbn` ");
        $messages[] = "已为 bc_books 添加 ISBN 和分类列。";
    }

    $result = $conn->query("SHOW COLUMNS FROM bc_drift_logs LIKE 'event_desc'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE bc_drift_logs ADD COLUMN `event_desc` VARCHAR(255) NOT NULL AFTER `city` ");
        $conn->query("ALTER TABLE bc_drift_logs ADD COLUMN `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $messages[] = "已为 bc_drift_logs 添加描述和日期列。";
    }

    // 3. 插入演示数据（如果表为空）
    $check_empty = $conn->query("SELECT book_id FROM bc_books LIMIT 1");
    if ($check_empty && $check_empty->num_rows == 0) {
        $conn->query("INSERT INTO bc_books (title, author, current_city, current_reader, status, lat, lng, note) 
                     VALUES ('瓦尔登湖', '亨利·戴维·梭罗', '宜昌', '冠恩超人', '已读待寄', 30.7026, 111.2908, '简单生活并不意味着贫乏。')");
        
        $new_id = $conn->insert_id;
        $conn->query("INSERT INTO bc_drift_logs (book_id, city, reader, lat, lng, event_desc, note) 
                     VALUES ($new_id, '杭州', '系统管理员', 30.2741, 120.1551, '书籍登记上线', '漂流开始')");
        
        $conn->query("INSERT INTO bc_drift_logs (book_id, city, reader, lat, lng, event_desc, note) 
                     VALUES ($new_id, '宜昌', '冠恩超人', 30.7026, 111.2908, '已读待寄', '在宜昌签收并阅读完毕')");
        
        $messages[] = "演示数据已成功插入。";
    }

    $conn->close();
    return $messages;
}

// 执行初始化
$results = init_book_crossing_database();

// 输出结果
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'success' => true,
    'messages' => $results
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
