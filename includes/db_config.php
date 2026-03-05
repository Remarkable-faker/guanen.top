<?php
// 以后全站只准改这一个地方！
define('DB_HOST', 'localhost');
define('DB_USER', 'db_guanen');
define('DB_NAME', 'db_guanen');
define('DB_PASS', 'db_guanen'); 

// 同时也提供变量版本，兼容旧代码
$db_host = DB_HOST;
$db_user = DB_USER;
$db_pass = DB_PASS;
$db_name = DB_NAME;

// 全局连接变量
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("连接失败: " . mysqli_connect_error());
}
// 设置字符集，防止中文乱码
mysqli_set_charset($conn, "utf8mb4");

/**
 * 核心数据库连接函数
 * 确保全站复用同一个 $conn 实例
 */
if (!function_exists('db_connect')) {
    function db_connect() {
        global $conn;
        
        // 如果连接已存在且有效，直接返回
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            return $conn;
        }

        // 否则重新连接
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            return null; // 让调用者处理错误
        }
        mysqli_set_charset($conn, "utf8mb4");
        return $conn;
    }
}