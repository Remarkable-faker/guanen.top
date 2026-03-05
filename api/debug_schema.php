<?php
/**
 * 数据库结构调试工具
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
        'msg' => 'Unauthorized: 请先登录'
    ));
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = db_connect();
    
    $tables = array('bc_books', 'bc_drift_logs');
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        $result = $conn->query("DESCRIBE $table");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                print_r($row);
            }
        } else {
            echo "Error describing $table: " . $conn->error . "\n";
        }
        echo "\n";
    }
    
    echo "Sample data from bc_books:\n";
    $result = $conn->query("SELECT * FROM bc_books LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            print_r($row);
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
