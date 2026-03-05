<?php
/**
 * 核心数据库封装 - 基于现有 includes/user_config.php
 *
 * 设计原则：
 * 1. 不修改、不删除现有的数据库配置与连接逻辑，仅做包装与集中入口。
 * 2. 尽量保证向下兼容，现有使用 db_connect() 的代码全部保持可用。
 * 3. 为后续重构 /api、/user、/admin 提供统一引入点。
 */

// 引入最新的数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';

// 全局数据库连接变量 - 强制使用 db_config.php 中的变量，避免重复连接
// 注意：db_config.php 已经创建了 $conn 变量
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $error_msg = "数据库连接失败 (" . $conn->connect_errno . "): " . $conn->connect_error;
        // 如果是 API 请求，返回 JSON 格式的错误
        $is_api = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'));
        
        if ($is_api) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'code' => 500,
                'msg' => $error_msg,
                'message' => $error_msg
            ), JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            die($error_msg);
        }
    }
    $conn->set_charset("utf8mb4");
}

// 复用现有的数据库函数实现（如果需要兼容旧的 db_connect）
require_once dirname(__DIR__) . '/includes/user_config.php';

/**
 * 获取数据库连接（对 db_connect 的别名封装）
 *
 * 说明：
 * - 目前直接调用现有的 db_connect()，不改变其行为。
 * - 未来如果需要在这里增加日志、性能监控等逻辑，可以集中在此实现。
 *
 * @return mysqli
 */
if (!function_exists('core_db_connect')) {
    function core_db_connect()
    {
        return db_connect();
    }
}

