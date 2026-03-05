<?php
/**
 * 秋葵代币API接口
 * 提供代币查询、充值、消费等功能
 */
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/session.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'code' => 401,
        'msg' => '未登录'
    ), JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    // 查询代币余额
    case 'get_balance':
        $sql = "SELECT qiuqiao_balance FROM user_tokens WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            echo json_encode(array(
                'success' => true,
                'balance' => $row['qiuqiao_balance']
            ), JSON_UNESCAPED_UNICODE);
        } else {
            // 如果没有记录，初始化0个
            $insert_sql = "INSERT INTO user_tokens (user_id, qiuqiao_balance) VALUES (?, 0)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("i", $user_id);
            $insert_stmt->execute();
            
            echo json_encode(array(
                'success' => true,
                'balance' => 0
            ), JSON_UNESCAPED_UNICODE);
        }
        break;
    
    // 充值代币
    case 'recharge':
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        $qiuqiao = $amount * 10; // 1元=10秋葵
        
        if ($amount <= 0) {
            echo json_encode(array(
                'success' => false,
                'msg' => '充值金额必须大于0'
            ), JSON_UNESCAPED_UNICODE);
            break;
        }
        
        // 更新代币余额
        $sql = "UPDATE user_tokens SET qiuqiao_balance = qiuqiao_balance + ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $qiuqiao, $user_id);
        
        if ($stmt->execute()) {
            // 记录充值日志
            $log_sql = "INSERT INTO recharge_logs (user_id, amount, qiuqiao, created_at) VALUES (?, ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("iii", $user_id, $amount, $qiuqiao);
            $log_stmt->execute();
            
            echo json_encode(array(
                'success' => true,
                'msg' => '充值成功',
                'qiuqiao' => $qiuqiao
            ), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(array(
                'success' => false,
                'msg' => '充值失败'
            ), JSON_UNESCAPED_UNICODE);
        }
        break;
    
    // 消费代币
    case 'consume':
        $qiuqiao = isset($_POST['qiuqiao']) ? intval($_POST['qiuqiao']) : 0;
        
        if ($qiuqiao <= 0) {
            echo json_encode(array(
                'success' => false,
                'msg' => '消费金额必须大于0'
            ), JSON_UNESCAPED_UNICODE);
            break;
        }
        
        // 检查余额是否足够
        $check_sql = "SELECT qiuqiao_balance FROM user_tokens WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res->num_rows > 0) {
            $row = $check_res->fetch_assoc();
            if ($row['qiuqiao_balance'] >= $qiuqiao) {
                // 扣除代币
                $sql = "UPDATE user_tokens SET qiuqiao_balance = qiuqiao_balance - ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $qiuqiao, $user_id);
                
                if ($stmt->execute()) {
                    // 记录消费日志
                    $log_sql = "INSERT INTO consume_logs (user_id, qiuqiao, created_at) VALUES (?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param("ii", $user_id, $qiuqiao);
                    $log_stmt->execute();
                    
                    echo json_encode(array(
                        'success' => true,
                        'msg' => '消费成功'
                    ), JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(array(
                        'success' => false,
                        'msg' => '消费失败'
                    ), JSON_UNESCAPED_UNICODE);
                }
            } else {
                echo json_encode(array(
                    'success' => false,
                    'msg' => '余额不足'
                ), JSON_UNESCAPED_UNICODE);
            }
        } else {
            echo json_encode(array(
                'success' => false,
                'msg' => '用户不存在'
            ), JSON_UNESCAPED_UNICODE);
        }
        break;
    
    default:
        echo json_encode(array(
            'success' => false,
            'msg' => '无效的操作'
        ), JSON_UNESCAPED_UNICODE);
        break;
}

$conn->close();
?>