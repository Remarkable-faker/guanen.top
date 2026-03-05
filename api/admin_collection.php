<?php
/**
 * 藏书阁管理API
 * 
 * 处理藏书的增删改查操作
 */

header('Content-Type: application/json; charset=utf-8');

// 统一使用绝对路径引用配置文件
require_once dirname(__DIR__) . '/includes/user_config.php';
require_once dirname(__DIR__) . '/core/db.php';

// 检查管理员权限
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 处理GET请求 - 获取藏书列表
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 分页参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        // 搜索条件
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
        
        // 构建查询语句
        $sql = "SELECT id, title, author, publisher, category FROM site_library";
        if ($search) {
            $sql .= " WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR publisher LIKE '%$search%' OR category LIKE '%$search%'";
        }
        $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($sql);
        $books = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $books[] = $row;
            }
        }
        
        // 获取总记录数
        $count_sql = "SELECT COUNT(*) as total FROM site_library";
        if ($search) {
            $count_sql .= " WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR publisher LIKE '%$search%' OR category LIKE '%$search%'";
        }
        $count_result = $conn->query($count_sql);
        $total = $count_result->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $books,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()]);
    }
}

// 处理POST请求 - 操作藏书
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        switch ($action) {
            case 'delete':
                // 删除藏书
                $sql = "DELETE FROM site_library WHERE id = $id";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['success' => true, 'message' => '删除成功']);
                } else {
                    echo json_encode(['success' => false, 'message' => '删除失败: ' . $conn->error]);
                }
                break;
                
            case 'add':
                // 添加藏书
                $title = $conn->real_escape_string($_POST['title']);
                $author = $conn->real_escape_string($_POST['author']);
                $publisher = $conn->real_escape_string($_POST['publisher']);
                $category = $conn->real_escape_string($_POST['category']);
                
                $sql = "INSERT INTO site_library (title, author, publisher, category) VALUES ('$title', '$author', '$publisher', '$category')";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['success' => true, 'message' => '添加成功']);
                } else {
                    echo json_encode(['success' => false, 'message' => '添加失败: ' . $conn->error]);
                }
                break;
                
            case 'update':
                // 更新藏书
                $title = $conn->real_escape_string($_POST['title']);
                $author = $conn->real_escape_string($_POST['author']);
                $publisher = $conn->real_escape_string($_POST['publisher']);
                $category = $conn->real_escape_string($_POST['category']);
                
                $sql = "UPDATE site_library SET title='$title', author='$author', publisher='$publisher', category='$category' WHERE id=$id";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['success' => true, 'message' => '更新成功']);
                } else {
                    echo json_encode(['success' => false, 'message' => '更新失败: ' . $conn->error]);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '无效操作']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()]);
    }
}

$conn->close();
?>