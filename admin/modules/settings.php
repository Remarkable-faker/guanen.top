<?php
/**
 * 模块：系统设置与健康检测
 * 提供一个后台诊断工具，用于快速排查环境、配置和数据库连接问题。
 */
if (!defined('ADMIN_AUTH')) {
    die('Direct access not permitted');
}

// --- 辅助函数：用于渲染状态徽章 ---
function render_status_badge($is_ok, $ok_text = '正常', $fail_text = '失败') {
    if ($is_ok) {
        return '<span class="px-3 py-1 text-xs font-bold text-emerald-800 bg-emerald-100 rounded-full">' . $ok_text . '</span>';
    } else {
        return '<span class="px-3 py-1 text-xs font-bold text-rose-800 bg-rose-100 rounded-full">' . $fail_text . '</span>';
    }
}

// --- 1. PHP 环境检测 ---
$php_version = phpversion();
$mysqli_loaded = extension_loaded('mysqli');

// --- 2. 核心文件检测 ---
$db_core_file = dirname(__DIR__, 2) . '/core/db.php';
$db_config_file = dirname(__DIR__, 2) . '/includes/db_config.php'; // 假设 db.php 依赖此文件
$files_check = [
    '数据库核心 (core/db.php)' => file_exists($db_core_file) && is_readable($db_core_file),
    '数据库配置 (includes/db_config.php)' => file_exists($db_config_file) && is_readable($db_config_file),
];

// --- 3. 数据库连接检测 ---
$db_conn_status = false;
$db_error_msg = '未知错误';
$db_server_info = 'N/A';

if ($files_check['数据库核心 (core/db.php)']) {
    // 临时关闭错误报告，手动捕获连接错误
    $original_reporting = error_reporting();
    error_reporting(0);
    
    // 使用 @ 抑制符，并使用与 db_config.php 完全一致的常量
    $temp_conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($temp_conn) {
        $db_conn_status = true;
        $db_server_info = mysqli_get_server_info($temp_conn);
        mysqli_close($temp_conn);
    } else {
        $db_error_msg = mysqli_connect_error();
    }
    
    // 恢复原始的错误报告级别
    error_reporting($original_reporting);
}

// --- 4. 目录权限检测 ---
$sessions_path = dirname(__DIR__, 2) . '/sessions';
$logs_path = dirname(__DIR__, 2) . '/logs';
$perms_check = [
    '会话目录 (sessions/)' => is_dir($sessions_path) && is_writable($sessions_path),
    '日志目录 (logs/)' => is_dir($logs_path) && is_writable($logs_path),
];

?>

<div class="space-y-8">
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-4">系统健康检测中心</h2>
        
        <!-- PHP 环境 -->
        <div class="grid grid-cols-3 gap-4 py-4 border-b">
            <div class="font-semibold text-gray-600">PHP 环境</div>
            <div class="col-span-2 space-y-2">
                <div class="flex justify-between items-center">
                    <span>PHP 版本: <strong><?php echo $php_version; ?></strong></span>
                    <?php echo render_status_badge(version_compare($php_version, '7.4', '>='), 'OK', '过低'); ?>
                </div>
                <div class="flex justify-between items-center">
                    <span>MySQLi 扩展</span>
                    <?php echo render_status_badge($mysqli_loaded, '已加载', '未加载'); ?>
                </div>
            </div>
        </div>

        <!-- 核心文件 -->
        <div class="grid grid-cols-3 gap-4 py-4 border-b">
            <div class="font-semibold text-gray-600">核心文件</div>
            <div class="col-span-2 space-y-2">
                <?php foreach ($files_check as $label => $is_ok): ?>
                <div class="flex justify-between items-center">
                    <span><?php echo $label; ?></span>
                    <?php echo render_status_badge($is_ok, '可读', '错误'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 数据库连接 -->
        <div class="grid grid-cols-3 gap-4 py-4 border-b">
            <div class="font-semibold text-gray-600">数据库连接</div>
            <div class="col-span-2 space-y-2">
                <div class="flex justify-between items-center">
                    <span>连接状态</span>
                    <?php echo render_status_badge($db_conn_status, '成功', '失败'); ?>
                </div>
                <?php if ($db_conn_status): ?>
                <div class="flex justify-between items-center">
                    <span>服务器信息: <strong><?php echo $db_server_info; ?></strong></span>
                </div>
                <?php else: ?>
                <div class="p-4 bg-rose-50 text-rose-700 rounded-lg text-sm">
                    <strong>错误详情:</strong> <?php echo htmlspecialchars($db_error_msg); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 目录权限 -->
        <div class="grid grid-cols-3 gap-4 py-4">
            <div class="font-semibold text-gray-600">目录权限</div>
            <div class="col-span-2 space-y-2">
                <?php foreach ($perms_check as $label => $is_ok): ?>
                <div class="flex justify-between items-center">
                    <span><?php echo $label; ?></span>
                    <?php echo render_status_badge($is_ok, '可写', '不可写'); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
