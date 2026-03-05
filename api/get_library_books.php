<?php
/**
 * 获取图书馆书籍列表 API
 * 用于展示图书馆内所有书籍，并根据分类排序
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 设置响应头
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 2. API 鉴权逻辑 (跳过初始化和列表获取)
if ($action !== 'init' && $action !== 'get_config' && $action !== '' && $action !== 'list') {
    // 藏书阁需要验证漂流系统登录
    if (!isset($_SESSION['bc_user_id']) && !isset($_SESSION['admin_id'])) { 
        die(json_encode(['code' => 401, 'msg' => '请先登录漂流系统'])); 
    }
}

// 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    api_success(array(
        'is_logged_in' => isset($_SESSION['user_id']),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'config' => array(
            'has_status' => true
        )
    ));
}

/**
 * 确保图书馆表存在 (强制指向 site_library)
 */
function ensure_library_table($conn) {
    // 检查 site_library 是否存在，如果不存在则报错
    $res = $conn->query("SHOW TABLES LIKE 'site_library'");
    if ($res->num_rows == 0) {
        throw new Exception("数据库中缺失 site_library 表，请联系管理员。");
    }
}

// 执行初始化
try {
    ensure_library_table($conn);
} catch (Exception $e) {
    api_error($e->getMessage());
}

try {
    // 查询所有馆藏书籍，按分类和标题排序
    $result = $conn->query("SELECT * FROM site_library ORDER BY category, title");
    if (!$result) {
        throw new Exception("查询书籍失败: " . $conn->error);
    }
    
    $books = array();
    while ($row = $result->fetch_assoc()) {
        // 格式化数据以匹配前端需求 (对齐 site_library 字段)
        $books[] = array(
            'id' => (int)$row['id'], // site_library 使用 id 而非 book_id
            'title' => $row['title'],
            'author' => $row['author'],
            'publisher' => isset($row['publisher']) ? $row['publisher'] : '未知出版社',
            'isbn' => isset($row['isbn']) ? $row['isbn'] : '',
            'price' => isset($row['price']) ? (float)$row['price'] : 0.00,
            'rating' => isset($row['rating']) ? (float)$row['rating'] : 0.0,
            'category' => isset($row['category']) ? $row['category'] : '默认分类',
            'status' => isset($row['status']) ? $row['status'] : 'available'
        );
    }
    
    // 返回成功响应
    api_success($books);

} catch (Exception $e) {
    // 捕获异常并返回统一错误
    api_error('获取图书馆书籍列表失败: ' . $e->getMessage(), 200);
}
