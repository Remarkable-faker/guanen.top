<?php
// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 解析请求信息
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 3. 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'config' => array(
            'refresh_rate' => 'once_per_session'
        )
    ));
}

/**
 * 确保统计表存在并初始化
 */
function ensure_statistics_table($conn) {
    // 1. 创建 site_statistics 表 (如果不存在)
    $sql_create = "CREATE TABLE IF NOT EXISTS site_statistics (
        id INT PRIMARY KEY,
        total_visits INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_create) or die($conn->error);

    // 2. 确保初始记录存在，并将起始值设为 1500 (网站迁移前的初始基数)
    $sql_init = "INSERT INTO site_statistics (id, total_visits) VALUES (1, 1500)
                  ON DUPLICATE KEY UPDATE total_visits = IF(total_visits < 1500, 1500, total_visits);";
    $conn->query($sql_init) or die($conn->error);
}

// 执行初始化
ensure_statistics_table($conn);

try {
    // 3. 使用 Session 标记，避免同一会话反复刷新页面导致访问量虚高
    if (!isset($_SESSION['visited_site_total'])) {
        $conn->query("UPDATE site_statistics SET total_visits = total_visits + 1 WHERE id = 1") or die($conn->error);
        $_SESSION['visited_site_total'] = true;
    }

    // 4. 获取最新访问量
    $result = $conn->query("SELECT total_visits FROM site_statistics WHERE id = 1");
    if (!$result) {
        throw new Exception("查询访问量失败: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_visits = $row ? (int)$row['total_visits'] : 0;

    // 5. 返回结果，同时兼容旧版字段名
    api_success(
        array('total_visits' => $total_visits),
        '获取访问总数成功',
        0,
        array('total_visits' => $total_visits)
    );
} catch (Exception $e) {
    // 捕获异常并返回错误信息
    api_error('获取访问总数失败: ' . $e->getMessage(), 'DB_ERROR');
}

