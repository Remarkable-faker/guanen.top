<?php
/**
 * 秋葵代币充值页面
 * 预留充值功能接口
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// 获取用户信息
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 获取用户当前代币余额 (已更新：直接查询 users 表)
$token_sql = "SELECT qiuqiao_balance FROM users WHERE id = ?";
$token_stmt = $conn->prepare($token_sql);
$token_stmt->bind_param("i", $user_id);
$token_stmt->execute();
$token_res = $token_stmt->get_result();

if ($token_res->num_rows > 0) {
    $token_row = $token_res->fetch_assoc();
    $qiuqiao_balance = (int)$token_row['qiuqiao_balance'];
} else {
    $qiuqiao_balance = 0;
}
$token_stmt->close();
// 注意：不要在这里 close($conn)，底部还有 user_layout.php 可能会用到

// 页面标题
$page_title = "秋葵代币充值";

// 开始捕获视图内容
ob_start();
?>

<style>
    .recharge-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .recharge-header {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .balance-info {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 16px;
        padding: 24px;
        color: white;
        margin-bottom: 20px;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.15);
    }
    .recharge-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .recharge-option {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .recharge-option:hover {
        border-color: #10b981;
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.15);
    }
    .recharge-option.active {
        border-color: #10b981;
        background: #f0fdf4;
    }
    .recharge-amount {
        font-size: 24px;
        font-weight: 700;
        color: #10b981;
        margin-bottom: 8px;
    }
    .recharge-price {
        font-size: 16px;
        color: #64748b;
        margin-bottom: 12px;
    }
    .recharge-qiuqiao {
        font-size: 14px;
        color: #059669;
        font-weight: 600;
    }
    .recharge-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.15);
    }
    .recharge-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(16, 185, 129, 0.2);
    }
    .recharge-btn:active {
        transform: translateY(0);
    }
    .note-section {
        background: #fef3c7;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 20px;
        margin-top: 30px;
    }
    .note-title {
        font-size: 16px;
        font-weight: 700;
        color: #92400e;
        margin-bottom: 12px;
    }
    .note-content {
        font-size: 14px;
        color: #b45309;
        line-height: 1.6;
    }
</style>

<div class="recharge-container">
    <div class="recharge-header">
        <h2 style="font-size: 28px; color: #1e293b; margin-bottom: 15px;">秋葵代币充值</h2>
        <p style="color: #64748b; font-size: 16px;">1元 = 10秋葵 ✵</p>
    </div>
    
    <div class="balance-info">
        <div style="font-size: 14px; opacity: 0.8; margin-bottom: 5px;">当前余额</div>
        <div style="font-size: 32px; font-weight: 700;"><?php echo $qiuqiao_balance; ?> ✵</div>
    </div>
    
    <div class="recharge-options">
        <div class="recharge-option" data-amount="1" data-qiuqiao="10">
            <div class="recharge-amount">¥1</div>
            <div class="recharge-price">= 10 秋葵</div>
            <div class="recharge-qiuqiao">10 ✵</div>
        </div>
        
        <div class="recharge-option" data-amount="10" data-qiuqiao="100">
            <div class="recharge-amount">¥10</div>
            <div class="recharge-price">= 100 秋葵</div>
            <div class="recharge-qiuqiao">100 ✵</div>
        </div>
        
        <div class="recharge-option" data-amount="20" data-qiuqiao="200">
            <div class="recharge-amount">¥20</div>
            <div class="recharge-price">= 200 秋葵</div>
            <div class="recharge-qiuqiao">200 ✵</div>
        </div>
        
        <div class="recharge-option" data-amount="50" data-qiuqiao="500">
            <div class="recharge-amount">¥50</div>
            <div class="recharge-price">= 500 秋葵</div>
            <div class="recharge-qiuqiao">500 ✵</div>
        </div>
        
        <div class="recharge-option" data-amount="100" data-qiuqiao="1000">
            <div class="recharge-amount">¥100</div>
            <div class="recharge-price">= 1000 秋葵</div>
            <div class="recharge-qiuqiao">1000 ✵</div>
        </div>
    </div>
    
    <button class="recharge-btn" id="rechargeBtn">立即充值</button>
    
    <div id="wechatPay" style="display: none; text-align: center; margin: 20px 0;">
        <div style="font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 15px;">
            微信扫码支付
        </div>
        <div style="display: inline-block; padding: 10px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <img src="../assets/images/wechat_qr.webp" alt="微信支付二维码" style="width: 250px; height: 250px;" />
        </div>
        <div style="margin-top: 15px; font-size: 16px; color: #64748b;">
            支付金额：¥<span id="payAmount">1</span> = <span id="payQiuqiao">10</span> 秋葵 ✵
        </div>
    </div>
    
    <div class="note-section">
        <div class="note-title">充值说明</div>
        <div class="note-content">
            <p>• 扫码支付后，请点击“已支付”按钮</p>
            <p>• 我们会在1-5分钟内为您充值到账</p>
            <p>• 1元人民币可兑换10个秋葵代币</p>
            <p>• 如有问题，请联系客服</p>
        </div>
    </div>
</div>

<script>
    // 选择充值选项
    const rechargeOptions = document.querySelectorAll('.recharge-option');
    let selectedAmount = 10;
    let selectedQiuqiao = 100;
    
    rechargeOptions.forEach(option => {
        option.addEventListener('click', () => {
            // 移除所有选中状态
            rechargeOptions.forEach(opt => opt.classList.remove('active'));
            // 添加当前选中状态
            option.classList.add('active');
            // 获取选中的金额和秋葵数量
            selectedAmount = option.dataset.amount;
            selectedQiuqiao = option.dataset.qiuqiao;
        });
    });
    
    // 默认选中第一个选项
    if (rechargeOptions.length > 0) {
        rechargeOptions[0].classList.add('active');
        selectedAmount = rechargeOptions[0].dataset.amount;
        selectedQiuqiao = rechargeOptions[0].dataset.qiuqiao;
    }
    
    // 充值按钮点击事件
    document.getElementById('rechargeBtn').addEventListener('click', () => {
        // 显示微信支付二维码
        document.getElementById('wechatPay').style.display = 'block';
        document.getElementById('payAmount').textContent = selectedAmount;
        document.getElementById('payQiuqiao').textContent = selectedQiuqiao;
        
        // 滚动到二维码位置
        document.getElementById('wechatPay').scrollIntoView({ behavior: 'smooth' });
    });
</script>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>