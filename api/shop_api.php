<?php
/**
 * 冠恩书屋 · 文创小铺交易接口
 * 功能：处理秋葵购买、扣除余额、扣减库存、生成订单
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php'; 

header('Content-Type: application/json; charset=utf-8');

// 1. 登录与参数安全检查
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录书屋']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$goods_id = isset($_POST['goods_id']) ? intval($_POST['goods_id']) : 0;

if ($action !== 'buy_with_tokens' || $goods_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的请求']);
    exit();
}

// 开启数据库事务 (核心安全机制)
$conn->begin_transaction();

try {
    // 2. 查询商品信息 (并锁定该行数据，防止并发超卖)
    $goods_sql = "SELECT title, price_tokens, stock, type FROM bc_shop_goods WHERE id = ? AND status = 1 FOR UPDATE";
    $stmt = $conn->prepare($goods_sql);
    $stmt->bind_param("i", $goods_id);
    $stmt->execute();
    $goods_res = $stmt->get_result();
    
    if ($goods_res->num_rows === 0) {
        throw new Exception("该商品已下架或不存在");
    }
    $goods = $goods_res->fetch_assoc();
    
    if ($goods['stock'] <= 0) {
        throw new Exception("来晚一步，该物品已售罄");
    }

   // 3. 查询用户秋葵余额 (💡 修改点：改为锁定 users 表)
    $token_sql = "SELECT qiuqiao_balance FROM users WHERE id = ? FOR UPDATE";
    $stmt = $conn->prepare($token_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $token_res = $stmt->get_result();
    
    if ($token_res->num_rows === 0) {
        throw new Exception("未找到你的账户异常，请联系掌柜");
    }
    $user_token = $token_res->fetch_assoc();
    $current_balance = intval($user_token['qiuqiao_balance']);
    $price = intval($goods['price_tokens']);

    if ($current_balance < $price) {
        throw new Exception("秋葵余量不足，还差 " . ($price - $current_balance) . " ✵");
    }

    // 4. 扣除秋葵 (💡 修改点：改为更新 users 表)
    $new_balance = $current_balance - $price;
    $update_token_sql = "UPDATE users SET qiuqiao_balance = ? WHERE id = ?";
    $stmt = $conn->prepare($update_token_sql);
    $stmt->bind_param("ii", $new_balance, $user_id);
    $stmt->execute();
    
    
    // 5. 扣减商品库存
    $update_stock_sql = "UPDATE bc_shop_goods SET stock = stock - 1 WHERE id = ?";
    $stmt = $conn->prepare($update_stock_sql);
    $stmt->bind_param("i", $goods_id);
    $stmt->execute();

    // 6. 生成唯一订单号并写入订单表
    $order_no = 'SP' . date('YmdHis') . rand(1000, 9999);
    $pay_type = 'tokens';
    // 实物默认为 0(待发货)，虚拟物品默认为 1(已发放)
    $order_status = ($goods['type'] === 'digital') ? 1 : 0; 
    
    $order_sql = "INSERT INTO bc_shop_orders (order_no, user_id, goods_id, pay_type, pay_amount, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($order_sql);
    $stmt->bind_param("siisii", $order_no, $user_id, $goods_id, $pay_type, $price, $order_status);
    $stmt->execute();

    // 7. 所有操作成功，提交事务！
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '兑换成功！', 
        'new_balance' => $new_balance,
        'order_no' => $order_no
    ]);

} catch (Exception $e) {
    // 如果中间任何一步出错，回滚（撤销）所有操作，保证数据安全
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>