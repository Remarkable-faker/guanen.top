<?php
/**
 * 书籍漂流系统数据库升级脚本 V2
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);

// 权限验证
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => false, 'error' => 'Unauthorized', 'code' => 401));
    exit;
}

function upgrade_database_v2() {
    $conn = db_connect();
    $messages = array();

    // 1. 为 bc_drift_logs 添加新列
    $columns_to_add = array(
        'user_id' => "INT DEFAULT NULL COMMENT '用户ID'",
        'image_url' => "TEXT DEFAULT NULL COMMENT '节点图片URL'",
        'status' => "VARCHAR(50) DEFAULT NULL COMMENT '节点状态'",
        'likes' => "INT DEFAULT 0 COMMENT '点赞数'",
        'emotion' => "VARCHAR(50) DEFAULT NULL COMMENT '情绪标签'"
    );

    foreach ($columns_to_add as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM `bc_drift_logs` LIKE '$col'");
        if ($result->num_rows == 0) {
            if ($conn->query("ALTER TABLE `bc_drift_logs` ADD COLUMN `$col` $definition")) {
                $messages[] = "已为 bc_drift_logs 添加 $col 列。";
            } else {
                $messages[] = "添加 $col 列失败: " . $conn->error;
            }
        } else {
            $messages[] = "列 $col 已存在。";
        }
    }

    // 2. 确保 bc_books 也有一些扩展字段
    $book_columns = array(
        'owner_id' => "INT DEFAULT NULL COMMENT '原始拥有者ID'",
        'total_distance' => "DECIMAL(10, 2) DEFAULT 0 COMMENT '总漂流距离'",
        'total_days' => "INT DEFAULT 0 COMMENT '总漂流天数'",
        'reader_count' => "INT DEFAULT 0 COMMENT '参与人数'",
        'city_count' => "INT DEFAULT 0 COMMENT '经过城市数'"
    );

    foreach ($book_columns as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM `bc_books` LIKE '$col'");
        if ($result->num_rows == 0) {
            if ($conn->query("ALTER TABLE `bc_books` ADD COLUMN `$col` $definition")) {
                $messages[] = "已为 bc_books 添加 $col 列。";
            } else {
                $messages[] = "添加 $col 列失败: " . $conn->error;
            }
        } else {
            $messages[] = "列 $col 已存在。";
        }
    }

    // 3. 创建 bc_picked_stars 表用于夜间模式收藏
    $create_picked_stars = "CREATE TABLE IF NOT EXISTS `bc_picked_stars` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL COMMENT '用户ID',
        `node_id` INT NOT NULL COMMENT '日志节点ID',
        `pick_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '收藏时间',
        UNIQUE KEY `unique_pick` (`user_id`, `node_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏的星点';";

    if ($conn->query($create_picked_stars)) {
        $messages[] = "bc_picked_stars 表已准备就绪。";
    } else {
        $messages[] = "创建 bc_picked_stars 表失败: " . $conn->error;
    }

    $conn->close();
    return $messages;
}

header('Content-Type: application/json; charset=utf-8');
$results = upgrade_database_v2();
echo json_encode(array(
    'success' => true,
    'messages' => $results
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
