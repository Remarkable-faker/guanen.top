<?php
/**
 * 设置用户ID为1、2、3为管理员
 * 执行后请删除此文件以确保安全
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_config.php';

// 连接数据库
$conn = db_connect();
if (!$conn) {
    die("数据库连接失败");
}

// 检查用户是否存在
$user_ids = [1, 2, 3];
$placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
$stmt = $conn->prepare("SELECT id, username, email, is_admin FROM users WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
$stmt->execute();
$result = $stmt->get_result();

$existing_users = [];
while ($row = $result->fetch_assoc()) {
    $existing_users[$row['id']] = $row;
}

// 显示当前用户信息
echo "<h2>当前用户信息</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>用户名</th><th>邮箱</th><th>当前管理员状态</th></tr>";

foreach ($user_ids as $user_id) {
    if (isset($existing_users[$user_id])) {
        $user = $existing_users[$user_id];
        $is_admin_text = $user['is_admin'] ? '<span style="color: green;">是</span>' : '<span style="color: red;">否</span>';
        echo "<tr><td>{$user['id']}</td><td>{$user['username']}</td><td>{$user['email']}</td><td>{$is_admin_text}</td></tr>";
    } else {
        echo "<tr><td>{$user_id}</td><td colspan='3' style='color: red;'>用户不存在</td></tr>";
    }
}
echo "</table>";

// 执行更新操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $update_stmt = $conn->prepare("UPDATE users SET is_admin = 1 WHERE id IN ($placeholders)");
    $update_stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->affected_rows;
        echo "<h3 style='color: green;'>更新成功！共更新了 {$affected_rows} 个用户的管理员权限。</h3>";
        
        // 显示更新后的状态
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h3>更新后状态</h3>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>用户名</th><th>邮箱</th><th>管理员状态</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $is_admin_text = $row['is_admin'] ? '<span style="color: green;">是</span>' : '<span style="color: red;">否</span>';
            echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['email']}</td><td>{$is_admin_text}</td></tr>";
        }
        echo "</table>";
        
        echo "<p><strong>注意：请立即删除此文件以确保安全！</strong></p>";
    } else {
        echo "<h3 style='color: red;'>更新失败：" . $conn->error . "</h3>";
    }
    
    $update_stmt->close();
} else {
    // 显示确认表单
    echo '<form method="POST" style="margin-top: 20px;">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" style="background-color: #4F46E5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">确认将ID为1、2、3的用户设置为管理员</button>';
    echo '</form>';
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设置管理员用户</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { margin: 20px 0; }
        th, td { padding: 10px; text-align: left; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="warning">
        <strong>安全警告：</strong>此文件用于一次性设置管理员权限。执行完成后请立即删除此文件！
    </div>
</body>
</html>