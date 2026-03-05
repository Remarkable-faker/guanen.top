<?php
// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 解析请求信息
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'get_stats';

// 3. API 鉴权逻辑 (该 API 为公开追踪，不强制登录，仅对管理操作鉴权)
if ($action === 'admin_clear') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        api_error('权限不足', 401);
    }
}

/**
 * 确保访客足迹表存在
 */
function ensure_visitor_trace_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS visit_trace (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_id VARCHAR(64) NOT NULL,
        visit_time DATETIME NOT NULL,
        INDEX idx_visit_time (visit_time),
        INDEX idx_visitor (visitor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql) or die($conn->error);
}

// 执行表检查
ensure_visitor_trace_table($conn);

/**
 * 时间文学化格式化
 * 将时间转换为“刚刚”、“X分钟前”等描述性文字
 */
function format_literary_time($time) {
    if (!$time) return null;
    $timestamp = is_numeric($time) ? $time : strtotime($time);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return "刚刚";
    if ($diff < 3600) return floor($diff / 60) . " 分钟前";
    if ($diff < 86400) return floor($diff / 3600) . " 小时前";
    if ($diff < 259200) return floor($diff / 86400) . " 天前";
    return date('Y-m-d', $timestamp);
}

// 2. 访客标识处理
// 从 Cookie 获取访客 ID，如果没有则生成一个新的并保存 30 天
$visitor_id = isset($_COOKIE['visitor_id']) ? $_COOKIE['visitor_id'] : '';

if (empty($visitor_id)) {
    $visitor_id = md5(uniqid(mt_rand(), true));
    setcookie('visitor_id', $visitor_id, time() + 30 * 24 * 3600, '/');
}

// 3. 记录访问记录
// 检查最近 30 分钟内是否有该访客记录，避免短时间内重复记录
$stmt = $conn->prepare("SELECT id FROM visit_trace WHERE visitor_id = ? AND visit_time >= NOW() - INTERVAL 30 MINUTE LIMIT 1");
if (!$stmt) die($conn->error);

$stmt->bind_param("s", $visitor_id);
$stmt->execute() or die($stmt->error);
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // 如果 30 分钟内没有记录，则插入新的一条
    $insert_stmt = $conn->prepare("INSERT INTO visit_trace (visitor_id, visit_time) VALUES (?, NOW())");
    if (!$insert_stmt) die($conn->error);
    $insert_stmt->bind_param("s", $visitor_id);
    $insert_stmt->execute() or die($insert_stmt->error);
    $insert_stmt->close();
}
$stmt->close();

// 4. 定期清理旧数据 (1% 概率触发，清理 7 天前的数据)
if (mt_rand(1, 100) === 1) {
    $conn->query("DELETE FROM visit_trace WHERE visit_time < NOW() - INTERVAL 7 DAY") or die($conn->error);
}

// 5. 初始化或获取配置
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'visitor_id' => $visitor_id,
        'config' => array(
            'trace_interval' => 1800000 // 30 分钟
        )
    ));
}

// 6. 获取统计数据
if ($action === 'get_stats') {
    // 统计今日（过去 24 小时）独立访客数
    $visitor_count = 0;
    $count_res = $conn->query("SELECT COUNT(DISTINCT visitor_id) as visitor_count FROM visit_trace WHERE visit_time >= NOW() - INTERVAL 1 DAY");
    if (!$count_res) die($conn->error);
    
    $row = $count_res->fetch_assoc();
    $visitor_count = (int)$row['visitor_count'];

    // 7. 获取全站最后活动时间
    // 遍历各个业务表，寻找最新的提交记录
    $last_msg_time = null;
    $check_tables = array(
        'bc_drift_logs' => 'log_date',
        'chat_messages' => 'created_at',
        'suggestions' => 'submitted_at',
        'bc_danmaku' => 'created_at',
        'wenjuan_suggestions' => 'created_at',
        'messages' => 'created_at'
    );

    foreach ($check_tables as $table => $column) {
        // 检查表是否存在，避免报错
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check_table && $check_table->num_rows > 0) {
            $res = $conn->query("SELECT $column FROM $table ORDER BY $column DESC LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $current_time = $row[$column];
                if (!$last_msg_time || strtotime($current_time) > strtotime($last_msg_time)) {
                    $last_msg_time = $current_time;
                }
            }
        }
    }

    // 格式化输出文本
    $last_msg_text = format_literary_time($last_msg_time);
    $last_msg_display = $last_msg_text ? "最后一行字写于 " . $last_msg_text : "尚未写下第一行字";
    $visitor_display = ($visitor_count > 0) ? "今日有 {$visitor_count} 位读者经过" : "今日尚无人经过";

    api_success(array(
        'visitor_text' => $visitor_display,
        'last_msg_text' => $last_msg_display,
        'visitor_count' => $visitor_count,
        'last_update' => $last_msg_time
    ));
} else {
    api_error('未知的 API 动作: ' . $action, 200, array('action' => $action));
}

