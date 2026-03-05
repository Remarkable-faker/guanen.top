<?php
/**
 * 书籍漂流系统高级修复与检测工具 (v2.0)
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
        header('HTTP/1.1 401 Unauthorized');
    }
    echo json_encode(array(
        'success' => false,
        'code' => 401,
        'msg' => 'Unauthorized: 请先登录后台'
    ));
    exit;
}

// 禁用自动抛出异常，我们要手动处理并展示友好错误
mysqli_report(MYSQLI_REPORT_OFF);

$conn = db_connect();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$messages = array();

function add_log($msg, $type = 'info') {
    global $messages;
    $messages[] = array('text' => $msg, 'type' => $type);
}

if ($action === 'deep_repair') {
    add_log("开始深度修复过程...", 'warning');
    
    // 1. 检查 bc_books 表
    $res = $conn->query("SHOW TABLES LIKE 'bc_books'");
    if ($res->num_rows == 0) {
        add_log("表 bc_books 不存在，正在创建...", 'info');
        $sql = "CREATE TABLE `bc_books` (
            `book_id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `author` VARCHAR(255) NOT NULL,
            `current_city` VARCHAR(100) DEFAULT NULL,
            `current_reader` VARCHAR(100) DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT '阅读中',
            `lat` DECIMAL(10, 7) DEFAULT NULL,
            `lng` DECIMAL(10, 7) DEFAULT NULL,
            `isbn` VARCHAR(20) DEFAULT NULL,
            `category` VARCHAR(100) DEFAULT NULL,
            `note` TEXT DEFAULT NULL,
            `last_update` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        if ($conn->query($sql)) {
            add_log("表 bc_books 创建成功。", 'success');
        } else {
            add_log("创建 bc_books 失败: " . $conn->error, 'error');
        }
    } else {
        add_log("表 bc_books 已存在，检查结构...", 'info');
        
        // 确保 book_id 是主键且自增
        $res = $conn->query("SHOW COLUMNS FROM bc_books WHERE Field = 'book_id'");
        if (!$res) {
            add_log("检查 bc_books book_id 失败: " . $conn->error, 'error');
        } else if ($row = $res->fetch_assoc()) {
            $is_auto = (strpos($row['Extra'], 'auto_increment') !== false);
            $type = $row['Type'];
            
            if (!$is_auto || strpos(strtolower($type), 'int') === false) {
                add_log("正在修复 book_id 属性 (当前类型: $type, 自增: " . ($is_auto ? '是' : '否') . ")...", 'warning');
                
                // 如果存在非数字数据，先尝试清理或转换
                $check_data = $conn->query("SELECT book_id FROM bc_books WHERE book_id NOT REGEXP '^[0-9]+$'");
                if (!$check_data) {
                    add_log("检查非数字 ID 失败: " . $conn->error, 'error');
                } else if ($check_data->num_rows > 0) {
                    add_log("检测到非数字 ID 数据（如 'BK002'），正在尝试重置 ID 以匹配 INT 类型...", 'warning');
                }

                // 尝试直接修改
                $alter_sql = "ALTER TABLE bc_books MODIFY COLUMN `book_id` INT AUTO_INCREMENT";
                if ($conn->query($alter_sql)) {
                    add_log("book_id 已成功修复为 INT AUTO_INCREMENT。", 'success');
                } else {
                    $err = $conn->error;
                    add_log("直接修复失败: $err", 'error');
                    
                    if (strpos($err, "Multiple primary key") !== false) {
                        add_log("检测到主键冲突，尝试重新定义主键...", 'info');
                        if (!$conn->query("ALTER TABLE bc_books DROP PRIMARY KEY")) {
                            add_log("删除主键失败: " . $conn->error, 'error');
                        }
                        if (!$conn->query("ALTER TABLE bc_books ADD PRIMARY KEY (book_id)")) {
                            add_log("添加主键失败: " . $conn->error, 'error');
                        }
                        if ($conn->query("ALTER TABLE bc_books MODIFY COLUMN `book_id` INT AUTO_INCREMENT")) {
                            add_log("通过重建主键修复成功！", 'success');
                        } else {
                            add_log("重建主键后修改列失败: " . $conn->error, 'error');
                        }
                    } else if (strpos($err, "Incorrect integer value") !== false || strpos($err, "truncated") !== false || strpos($err, "Data truncated") !== false) {
                        add_log("数据中存在无法转换的字符（如 'BK002'），正在尝试重置数据 ID...", 'warning');
                        
                        // 1. 去掉外键约束（如果存在）或相关表的关联
                        // 2. 将 bc_books 的 book_id 改为临时名称，并清空或转换
                        // 这里的最快办法是：备份数据到临时表，清空原表，修改结构，再插回
                        add_log("建议：由于存在不兼容数据，请点击下方的【清空并重置所有数据】按钮。", 'danger');
                    }
                }
            } else {
                add_log("book_id 结构正常。", 'success');
            }
        }
        
        // 验证修复结果
        $res_verify = $conn->query("SHOW COLUMNS FROM bc_books WHERE Field = 'book_id'");
        $row_verify = $res_verify->fetch_assoc();
        if (strpos($row_verify['Extra'], 'auto_increment') === false) {
            add_log("❌ 警告：AUTO_INCREMENT 属性仍未生效！请尝试【清空并重置】。", 'error');
        } else {
            add_log("✅ 验证通过：AUTO_INCREMENT 已激活。", 'success');
        }
        
        // 检查缺失列
        $cols_to_check = array(
            'lat' => "DECIMAL(10, 7) DEFAULT NULL",
            'lng' => "DECIMAL(10, 7) DEFAULT NULL",
            'isbn' => "VARCHAR(20) DEFAULT NULL",
            'category' => "VARCHAR(100) DEFAULT NULL",
            'note' => "TEXT DEFAULT NULL",
            'last_update' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        );
        foreach ($cols_to_check as $col => $def) {
            $c_res = $conn->query("SHOW COLUMNS FROM bc_books LIKE '$col'");
            if ($c_res->num_rows == 0) {
                add_log("缺失列 $col，正在添加...", 'info');
                if ($conn->query("ALTER TABLE bc_books ADD COLUMN `$col` $def")) {
                    add_log("列 $col 添加成功。", 'success');
                } else {
                    add_log("添加列 $col 失败: " . $conn->error, 'error');
                }
            }
        }
    }

    // 2. 检查 bc_drift_logs 表
    $res = $conn->query("SHOW TABLES LIKE 'bc_drift_logs'");
    if (!$res) {
        add_log("检查 bc_drift_logs 失败: " . $conn->error, 'error');
    } else if ($res->num_rows == 0) {
        add_log("表 bc_drift_logs 不存在，正在创建...", 'info');
        $sql = "CREATE TABLE `bc_drift_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `book_id` INT NOT NULL,
            `city` VARCHAR(100) NOT NULL,
            `reader` VARCHAR(100) DEFAULT NULL,
            `lat` DECIMAL(10, 7) DEFAULT NULL,
            `lng` DECIMAL(10, 7) DEFAULT NULL,
            `event_desc` VARCHAR(255) NOT NULL,
            `note` TEXT DEFAULT NULL,
            `log_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_book_id` (`book_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        if ($conn->query($sql)) {
            add_log("表 bc_drift_logs 创建成功。", 'success');
        } else {
            add_log("创建 bc_drift_logs 失败: " . $conn->error, 'error');
        }
    } else {
        // 确保 id 是自增的
        $res = $conn->query("SHOW COLUMNS FROM bc_drift_logs WHERE Field = 'id'");
        if (!$res) {
            add_log("检查 bc_drift_logs id 失败: " . $conn->error, 'error');
        } else if ($row = $res->fetch_assoc()) {
            if (strpos($row['Extra'], 'auto_increment') === false) {
                add_log("正在修复 bc_drift_logs 的 id 自增属性...", 'warning');
                if ($conn->query("ALTER TABLE bc_drift_logs MODIFY COLUMN `id` INT AUTO_INCREMENT")) {
                    add_log("bc_drift_logs id 修复成功。", 'success');
                } else {
                    add_log("bc_drift_logs id 修复失败: " . $conn->error, 'error');
                }
            }
        }
    }
    
    // 3. 检查 bc_users 表 (漂流系统专用用户)
    $res = $conn->query("SHOW TABLES LIKE 'bc_users'");
    if ($res->num_rows == 0) {
        add_log("表 bc_users 不存在，正在创建...", 'info');
        $sql = "CREATE TABLE `bc_users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '登录账号',
            `password` VARCHAR(255) NOT NULL COMMENT '加密密码',
            `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
            `real_name` VARCHAR(50) DEFAULT NULL COMMENT '真实姓名',
            `id_card` VARCHAR(20) DEFAULT NULL COMMENT '身份证号',
            `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
            `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
            `address` TEXT DEFAULT NULL COMMENT '收货地址',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_login` TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        if ($conn->query($sql)) {
            add_log("表 bc_users 创建成功。", 'success');
        } else {
            add_log("创建 bc_users 失败: " . $conn->error, 'error');
        }
    } else {
        add_log("表 bc_users 已存在，检查字段名...", 'info');
        
        // 检查是否存在 quiz_data 字段
        $check_quiz = $conn->query("SHOW COLUMNS FROM bc_users LIKE 'quiz_data'");
        if ($check_quiz && $check_quiz->num_rows == 0) {
            add_log("缺失 quiz_data 字段，正在添加...", 'warning');
            if ($conn->query("ALTER TABLE bc_users ADD COLUMN `quiz_data` LONGTEXT DEFAULT NULL COMMENT '准入测试数据'")) {
                add_log("quiz_data 字段添加成功。", 'success');
            } else {
                add_log("添加 quiz_data 失败: " . $conn->error, 'error');
            }
        }

        // 检查是否存在 address 字段
        $check_address = $conn->query("SHOW COLUMNS FROM bc_users LIKE 'address'");
        if ($check_address && $check_address->num_rows == 0) {
            add_log("缺失 address 字段，正在添加...", 'warning');
            if ($conn->query("ALTER TABLE bc_users ADD COLUMN `address` TEXT DEFAULT NULL COMMENT '收货地址'")) {
                add_log("address 字段添加成功。", 'success');
            } else {
                add_log("添加 address 失败: " . $conn->error, 'error');
            }
        }

        // 检查是否存在旧的 uid 字段，如果有则重命名为 id
        $check_uid = $conn->query("SHOW COLUMNS FROM bc_users LIKE 'uid'");
        if ($check_uid && $check_uid->num_rows > 0) {
            add_log("检测到旧的 uid 字段，正在迁移为 id...", 'warning');
            if ($conn->query("ALTER TABLE bc_users CHANGE COLUMN `uid` `id` INT AUTO_INCREMENT")) {
                add_log("字段 uid 已成功重命名为 id。", 'success');
            } else {
                add_log("重命名失败: " . $conn->error, 'error');
            }
        } else {
            add_log("bc_users 结构符合规范。", 'success');
        }
    }
    
    add_log("修复操作完成。", 'info');
}

if ($action === 'reset_all') {
    // 极端修复：删除并重建
    add_log("执行极端修复：删除并重建所有表...", 'danger');
    $conn->query("DROP TABLE IF EXISTS bc_drift_logs");
    $conn->query("DROP TABLE IF EXISTS bc_books");
    $conn->query("DROP TABLE IF EXISTS bc_users");
    header("Location: book_crossing_repair.php?action=deep_repair");
    exit();
}

// 检查当前状态
$status = [];
$tables = ['bc_books', 'bc_drift_logs', 'bc_users'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $res->num_rows > 0;
    $details = [];
    if ($exists) {
        $col_res = $conn->query("SHOW COLUMNS FROM $table");
        while ($c = $col_res->fetch_assoc()) {
            $details[] = $c;
        }
    }
    $status[$table] = ['exists' => $exists, 'cols' => $details];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>书籍漂流系统 - 数据库深度修复工具</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; max-width: 900px; margin: 40px auto; padding: 20px; background: #f0f2f5; color: #1c1e21; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 25px; }
        h1 { color: #1877f2; text-align: center; margin-bottom: 30px; }
        h3 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        .log-entry { padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid #ccc; font-size: 14px; }
        .log-info { background: #e7f3ff; border-left-color: #1877f2; }
        .log-success { background: #e7f4e4; border-left-color: #42b72a; color: #2b5329; }
        .log-warning { background: #fff9e6; border-left-color: #f5c33b; color: #664d03; }
        .log-error { background: #ffebe8; border-left-color: #f02849; color: #721c24; }
        .btn { display: inline-block; padding: 12px 24px; background: #1877f2; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: all 0.2s; border: none; cursor: pointer; }
        .btn:hover { background: #166fe5; transform: translateY(-1px); }
        .btn-danger { background: #f02849; }
        .btn-danger:hover { background: #d72241; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        code { background: #f1f1f1; padding: 2px 4px; border-radius: 4px; font-family: monospace; }
        .status-tag { padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; }
        .tag-ok { background: #42b72a; color: white; }
        .tag-missing { background: #f02849; color: white; }
    </style>
</head>
<body>
    <h1>🛠️ 书籍漂流系统数据库修复</h1>

    <?php if (!empty($messages)): ?>
    <div class="card">
        <h3>修复日志</h3>
        <?php foreach ($messages as $msg): ?>
            <div class="log-entry log-<?php echo $msg['type']; ?>">
                <?php echo ($msg['type'] === 'success' ? '✅ ' : ($msg['type'] === 'error' ? '❌ ' : 'ℹ️ ')) . $msg['text']; ?>
            </div>
        <?php endforeach; ?>
        <div style="margin-top: 15px;">
            <a href="book_crossing_repair.php" class="btn">刷新状态</a>
            <a href="../admin/admin_dashboard.php?tab=book_crossing" class="btn" style="background: #673ab7;">返回后台</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>当前数据库状态</h3>
        <p>数据库: <code><?php echo htmlspecialchars($db_config['dbname']); ?></code></p>
        
        <?php foreach ($status as $table => $info): ?>
            <div style="margin-top: 20px;">
                <strong>表: <code><?php echo $table; ?></code></strong>
                <?php if ($info['exists']): ?>
                    <span class="status-tag tag-ok">已存在</span>
                <?php
                // 获取表状态（包含 AUTO_INCREMENT 值）
                $table_status_res = $conn->query("SHOW TABLE STATUS LIKE '$table'");
                $table_status = $table_status_res ? $table_status_res->fetch_assoc() : null;
                $next_id = isset($table_status['Auto_increment']) ? $table_status['Auto_increment'] : 'N/A';
                echo "<span style='font-size:12px; margin-left:10px; color:#666;'>[下次自增 ID: $next_id]</span>";
                ?>
                    <table>
                        <tr>
                            <th>字段</th>
                            <th>类型</th>
                            <th>自增/额外</th>
                        </tr>
                        <?php foreach ($info['cols'] as $col): ?>
                        <tr <?php if ($col['Field'] === 'book_id' && strpos($col['Extra'], 'auto_increment') === false) echo 'style="background:#fff3cd"'; ?>>
                            <td><?php echo $col['Field']; ?></td>
                            <td><?php echo $col['Type']; ?></td>
                            <td><?php echo $col['Extra']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <span class="status-tag tag-missing">不存在</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3>当前数据概览 (冲突检测)</h3>
        <?php
        $check_conflict = $conn->query("SELECT book_id, title FROM bc_books LIMIT 10");
        if ($check_conflict && $check_conflict->num_rows > 0) {
            echo "<table><tr><th>ID (book_id)</th><th>书名 (title)</th><th>状态</th></tr>";
            while($r = $check_conflict->fetch_assoc()) {
                $is_num = is_numeric($r['book_id']);
                echo "<tr>
                    <td>" . ($is_num ? $r['book_id'] : "<b style='color:red'>{$r['book_id']} (非数字)</b>") . "</td>
                    <td>{$r['title']}</td>
                    <td>" . ($is_num ? "✅ 正常" : "❌ 格式冲突 (导致修复失败)") . "</td>
                </tr>";
            }
            echo "</table>";
            echo "<p style='font-size:12px; color:#666; margin-top:10px;'>注：如果 ID 包含字母（如 'BK002'），MySQL 无法开启 AUTO_INCREMENT。请使用下方的【彻底重置】。</p>";
        } else {
            echo "<p>暂无书籍数据。</p>";
        }
        ?>
    </div>

    <div class="card">
        <h3>修复操作</h3>
        <p>如果您的后台登记提示 <code>Field 'book_id' doesn't have a default value</code>，请点击下方按钮：</p>
        <a href="?action=deep_repair" class="btn">🚀 执行深度修复 (常规修复)</a>
        
        <div style="margin-top: 40px; border-top: 2px solid #ffebe8; padding-top: 20px;">
            <h3 style="color: #f02849;">☢️ 终极方案 (彻底重置)</h3>
            <p style="color: #666; font-size: 14px;">如果【深度修复】后仍然报错，通常是因为数据库中残留了 <strong>'BK001'</strong> 这种旧格式的数据，导致系统无法切换到数字自增模式。</p>
            <p style="color: #d63031; font-weight: bold;">该操作将清空所有书籍和轨迹，并强制重置数据库结构！</p>
            <a href="javascript:void(0)" onclick="if(confirm('警告：此操作将删除所有书籍数据！\n如果修复一直失败，这是唯一的解决办法。\n确定要重置吗？')) window.location.href='?action=reset_all'" class="btn btn-danger">🔥 彻底重置数据库结构</a>
        </div>
    </div>

    <p style="text-align: center; color: #999; font-size: 12px;">书籍漂流系统维护工具 &copy; <?php echo date('Y'); ?></p>
</body>
</html>
