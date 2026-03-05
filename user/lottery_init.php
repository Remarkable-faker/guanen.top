<?php
// /user/config/lottery_init.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 检查管理员权限
if (!isset($_SESSION['user_id']) || $_SESSION['username'] != 'admin') {
    die("没有权限执行此操作");
}

require_once dirname(__DIR__) . '/includes/user_config.php';
$conn = db_connect();

// 创建抽奖相关表
$sql = [];

// 1. 奖品表
$sql[] = "CREATE TABLE IF NOT EXISTS lottery_prizes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prize_name VARCHAR(100) NOT NULL,
    prize_level INT NOT NULL COMMENT '奖品等级：1-特等奖，2-一等奖，3-二等奖，4-三等奖，5-参与奖',
    probability DECIMAL(5,4) NOT NULL COMMENT '中奖概率，0-1之间',
    daily_limit INT DEFAULT 0,
    total_limit INT DEFAULT 0,
    stock INT DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// 2. 抽奖记录表
$sql[] = "CREATE TABLE IF NOT EXISTS lottery_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    username VARCHAR(50),
    prize_id INT,
    prize_name VARCHAR(100) NOT NULL,
    prize_level INT NOT NULL,
    draw_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT
)";

// 3. 抽奖配置表
$sql[] = "CREATE TABLE IF NOT EXISTS lottery_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value TEXT,
    description VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// 执行SQL
$results = [];
foreach ($sql as $query) {
    if ($conn->query($query) === TRUE) {
        $results[] = "✅ 表创建成功";
    } else {
        $results[] = "❌ 错误: " . $conn->error;
    }
}

// 插入默认配置
$default_config = [
    ['daily_draw_limit', '3', '每日抽奖次数限制'],
    ['total_draw_limit', '99', '总抽奖次数限制'],
    ['enable_lottery', '1', '是否启用抽奖'],
    ['announcement', '欢迎参与幸运抽奖！', '抽奖公告']
];

foreach ($default_config as $config) {
    $stmt = $conn->prepare("INSERT IGNORE INTO lottery_config (config_key, config_value, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $config[0], $config[1], $config[2]);
    $stmt->execute();
    $results[] = "✅ 配置添加: $config[0]";
}

// 插入默认奖品
$default_prizes = [
    ['iPhone 15 Pro', 1, 0.001, 1, '特等奖：最新款苹果手机'],
    ['iPad Air', 2, 0.005, 3, '一等奖：苹果平板电脑'],
    ['AirPods Pro', 3, 0.01, 10, '二等奖：无线降噪耳机'],
    ['小米手环', 4, 0.05, 50, '三等奖：智能手环'],
    ['谢谢参与', 5, 0.934, 1000, '参与奖：感谢参与']
];

foreach ($default_prizes as $prize) {
    $stmt = $conn->prepare("INSERT IGNORE INTO lottery_prizes (prize_name, prize_level, probability, stock, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sidis", $prize[0], $prize[1], $prize[2], $prize[3], $prize[4]);
    $stmt->execute();
    $results[] = "✅ 奖品添加: $prize[0]";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>抽奖系统初始化完成 - guanen.top</title>
    <style>
        body { font-family: Arial; padding: 50px; background: #f5f1e8; }
        .result-box { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; border: 3px solid #DAA520; }
        .success { color: green; }
        .error { color: red; }
        .btn { display: inline-block; padding: 12px 24px; background: #8B4513; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="result-box">
        <h1><i class="fas fa-check-circle"></i> 抽奖系统初始化完成</h1>
        <h3>执行结果：</h3>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
<?php foreach ($results as $result) {
    echo $result . "\n";
} ?>
        </pre>
        <p>抽奖系统已成功初始化！现在可以开始管理抽奖活动了。</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="lottery_prizes.php" class="btn">奖品管理</a>
            <a href="lottery_records.php" class="btn">中奖记录</a>
            <a href="lottery_config.php" class="btn">系统配置</a>
            <a href="user_dashboard.php" class="btn" style="background: #6c757d;">返回仪表盘</a>
        </div>
    </div>
</body>
</html>