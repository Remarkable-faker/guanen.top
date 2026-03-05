<?php
/**
 * 获取奖品详情 API
 * 
 * 规范化后的版本：使用 api_helpers.php，预处理语句，中文注释。
 */

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/api_helpers.php';

$conn = db_connect();

// 校验参数
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $prize_id = intval($_GET['id']);
    
    // 使用预处理语句查询
    $stmt = $conn->prepare("SELECT * FROM lottery_prizes WHERE id = ?");
    if (!$stmt) {
        api_error('数据库查询准备失败: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $prize_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($prize = $result->fetch_assoc()) {
        // 成功获取奖品
        api_success($prize);
    } else {
        // 奖品不存在
        api_error('奖品不存在');
    }
    
    $stmt->close();
} else {
    // 参数不合法
    api_error('无效的请求参数');
}

$conn->close();
?>
