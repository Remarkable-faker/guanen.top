<?php
/**
 * 聊天系统初始化脚本
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/includes/db_config.php';
error_reporting(0);

function setup_chat_tables() {
    global $conn;
    
    // 1. 好友请求表
    $sql_requests = "CREATE TABLE IF NOT EXISTS `friend_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // 2. 好友关系表
    $sql_friends = "CREATE TABLE IF NOT EXISTS `friends` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `friend_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_friendship` (`user_id`, `friend_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // 3. 聊天消息表
    $sql_messages = "CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `message` TEXT NOT NULL,
        `is_read` TINYINT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_sender` (`sender_id`),
        INDEX `idx_receiver` (`receiver_id`),
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $results = array();
    $results['friend_requests'] = $conn->query($sql_requests);
    if (!$results['friend_requests']) echo "Create friend_requests failed: " . $conn->error . "<br>";
    
    $results['friends'] = $conn->query($sql_friends);
    if (!$results['friends']) echo "Create friends failed: " . $conn->error . "<br>";
    
    $results['chat_messages'] = $conn->query($sql_messages);
    if (!$results['chat_messages']) echo "Create chat_messages failed: " . $conn->error . "<br>";

    foreach ($results as $table => $success) {
        if ($success) {
            echo "<p style='color:green'>✅ 表 `$table` 创建成功或已存在</p>";
        } else {
            echo "<p style='color:red'>❌ 表 `$table` 创建失败</p>";
        }
    }

    $conn->close();
}

// 执行安装
echo "<h2>聊天系统数据库初始化</h2>";
setup_chat_tables();
echo "<br><a href='user_dashboard.php'>返回个人中心</a>";
?>
