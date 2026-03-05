<?php
/**
 * 管理员查看用户访问日志页面
 * 功能：展示所有用户的访问足迹和操作记录
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 检查管理员权限
if (!core_is_admin()) {
    die("你没有权限访问此页面！");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_config.php';

$conn = db_connect();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户访问日志 - 冠恩先生官网</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078ff;
            color: white;
        }
        tr:hover {
            background-color: #f2f2f2;
        }
        .filter {
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .filter input, .filter select {
            padding: 8px;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter button {
            padding: 8px 20px;
            background-color: #0078ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter button:hover {
            background-color: #0056b3;
        }
        .stats {
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .stat-item {
            display: inline-block;
            margin-right: 30px;
            font-size: 18px;
        }
        .stat-value {
            font-weight: bold;
            color: #0078ff;
        }
    </style>
</head>
<body>
    <h1>用户访问日志</h1>
    
    <div class="stats">
        <?php
        // 统计总访问次数
        $total_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs")->fetch_assoc()['count'];
        // 统计独立访客数
        $unique_ips = $conn->query("SELECT COUNT(DISTINCT ip_address) as count FROM user_logs")->fetch_assoc()['count'];
        // 统计今日访问次数
        $today_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
        ?>
        <div class="stat-item">总访问次数: <span class="stat-value"><?php echo $total_logs; ?></span></div>
        <div class="stat-item">独立访客: <span class="stat-value"><?php echo $unique_ips; ?></span></div>
        <div class="stat-item">今日访问: <span class="stat-value"><?php echo $today_logs; ?></span></div>
    </div>
    
    <div class="filter">
        <form method="get">
            <input type="text" name="username" placeholder="用户名" value="<?php echo isset($_GET['username']) ? $_GET['username'] : ''; ?>">
            <input type="text" name="ip" placeholder="IP地址" value="<?php echo isset($_GET['ip']) ? $_GET['ip'] : ''; ?>">
            <select name="device">
                <option value="">所有设备</option>
                <option value="iPhone" <?php echo isset($_GET['device']) && $_GET['device'] == 'iPhone' ? 'selected' : ''; ?>>iPhone</option>
                <option value="Android" <?php echo isset($_GET['device']) && $_GET['device'] == 'Android' ? 'selected' : ''; ?>>Android</option>
                <option value="Windows PC" <?php echo isset($_GET['device']) && $_GET['device'] == 'Windows PC' ? 'selected' : ''; ?>>Windows PC</option>
            </select>
            <button type="submit">筛选</button>
        </form>
    </div>
    
    <table>
        <tr>
            <th>ID</th>
            <th>用户名</th>
            <th>IP地址</th>
            <th>设备</th>
            <th>操作</th>
            <th>页面URL</th>
            <th>访问时间</th>
        </tr>
        <?php
        // 构建查询语句
        $sql = "SELECT * FROM user_logs";
        $where = [];
        
        if (isset($_GET['username']) && !empty($_GET['username'])) {
            $where[] = "username LIKE '%" . $conn->real_escape_string($_GET['username']) . "%'";
        }
        
        if (isset($_GET['ip']) && !empty($_GET['ip'])) {
            $where[] = "ip_address LIKE '%" . $conn->real_escape_string($_GET['ip']) . "%'";
        }
        
        if (isset($_GET['device']) && !empty($_GET['device'])) {
            $where[] = "device = '" . $conn->real_escape_string($_GET['device']) . "'";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 100";
        
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['ip_address']}</td>";
            echo "<td>{$row['device']}</td>";
            echo "<td>{$row['action']}</td>";
            echo "<td>{$row['page_url']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>