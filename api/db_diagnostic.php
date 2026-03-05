<?php
/**
 * 数据库全量诊断工具
 * 目的：检测数据库连接状态，并自动扫描所有数据表及其字段结构
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 权限验证：仅允许已登录用户（或管理员）访问，保护数据库结构信息
if (!isset($_SESSION['user_id'])) {
    api_error('Unauthorized: 请先登录后执行诊断', 401);
}

try {
    $report = array(
        'status' => 'success',
        'database' => array(
            'host' => 'localhost',
            'db_name' => 'db_guanen',
            'server_info' => $conn->server_info,
            'connection_status' => 'Connected'
        ),
        'tables' => array()
    );

    // 2. 获取数据库中所有的表名
    $tables_res = $conn->query("SHOW TABLES");
    if (!$tables_res) {
        throw new Exception("无法获取表列表: " . $conn->error);
    }

    while ($table_row = $tables_res->fetch_array()) {
        $tableName = $table_row[0];
        
        // 获取该表的字段结构
        $columns = array();
        $col_res = $conn->query("SHOW COLUMNS FROM `$tableName`") or die($conn->error);
        
        while ($col = $col_res->fetch_assoc()) {
            $columns[] = array(
                'field'   => $col['Field'],
                'type'    => $col['Type'],
                'null'    => $col['Null'],
                'key'     => $col['Key'],
                'default' => $col['Default'],
                'extra'   => $col['Extra']
            );
        }

        // 获取该表的行数统计
        $count_res = $conn->query("SELECT COUNT(*) as cnt FROM `$tableName`") or die($conn->error);
        $rowCount = $count_res->fetch_assoc()['cnt'];

        // 组装表信息
        $report['tables'][$tableName] = array(
            'row_count' => (int)$rowCount,
            'column_count' => count($columns),
            'structure' => $columns
        );
    }

    // 3. 返回诊断报告
    api_success($report, '数据库全量诊断完成');

} catch (Exception $e) {
    // 捕获异常并返回错误
    api_error('数据库诊断失败: ' . $e->getMessage(), 500);
}

