<?php
/**
 * 创建秋葵代币数据库表
 * 初始化用户代币余额为0
 */

require_once dirname(__DIR__) . '/core/db.php';

// 创建秋葵代币表
$create_table_sql = "
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    qiuqiao_balance INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// 执行创建表语句
if (mysqli_query($conn, $create_table_sql)) {
    echo "秋葵代币表创建成功<br>";
    
    // 初始化所有现有用户的代币余额为0
    $init_users_sql = "
    INSERT INTO user_tokens (user_id, qiuqiao_balance)
    SELECT id, 0 FROM users
    ON DUPLICATE KEY UPDATE qiuqiao_balance = qiuqiao_balance;
    ";
    
    if (mysqli_query($conn, $init_users_sql)) {
        echo "所有用户代币余额初始化完成<br>";
        echo "每人初始0个秋葵代币";
    } else {
        echo "初始化用户代币余额失败: " . mysqli_error($conn);
    }
} else {
    echo "创建秋葵代币表失败: " . mysqli_error($conn);
}

// 关闭连接
mysqli_close($conn);
?>