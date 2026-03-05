<?php
/**
 * 全局用户系统数据库配置文件
 * 
 * 包含：
 * 1. 数据库连接配置与函数
 * 2. 调试模式开关
 * 3. 基础路径常量定义
 */

// --- 调试模式开启 ---
// 在开发/修复阶段临时开启错误显示，以便定位问题
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 数据库连接配置 ---
// 资产保护：从核心配置文件引入，确保全局同步 (2026.2.13)
require_once __DIR__ . '/db_config.php';

$db_config = [
    'host' => $db_host,
    'user' => $db_user,
    'pass' => $db_pass,
    'name' => $db_name
];

// 网站基础URL
define('BASE_URL', 'https://guanen.top/user/config/');
define('SITE_URL', 'https://guanen.top/');

/**
 * 获取数据库连接对象
 * 采用 mysqli 预处理模式防护 SQL 注入
 * 
 * 说明：此函数现在仅作为 db_config.php 中 db_connect 的包装或兜底，
 * 确保向下兼容性。
 * 
 * @return mysqli
 */
// 实际定义已移动至 db_config.php 以确保全局统一
if (!function_exists('db_connect_fallback')) {
    function db_connect_fallback() {
        return db_connect();
    }
}

// 自动初始化全局数据库连接变量 $conn
// 这样在引入 user_config.php 后，可以直接使用 $conn 而无需手动调用 db_connect()
$conn = db_connect();

/**
 * 页面跳转助手
 * @param string $url 目标路径
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// --- 调试模式开启 (根据用户要求放在末尾) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>