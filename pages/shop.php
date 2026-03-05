<?php
/**
 * 冠恩书屋 · 文创小铺 (动态渲染版)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 注意：如果你的 db.php 路径不同，请根据实际情况修改下面这行
require_once dirname(__DIR__) . '/core/db.php'; 

// 1. 获取用户登录状态与秋葵余额
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$qiuqiao_balance = 0;

if ($is_logged_in) {
    // 💡 修改点：直接从 users 表读取余额
    $token_sql = "SELECT qiuqiao_balance FROM users WHERE id = ?";
    $token_stmt = $conn->prepare($token_sql);
    if ($token_stmt) {
        $token_stmt->bind_param("i", $user_id);
        $token_stmt->execute();
        $token_res = $token_stmt->get_result();
        if ($token_row = $token_res->fetch_assoc()) { 
            $qiuqiao_balance = $token_row['qiuqiao_balance']; 
        }
        $token_stmt->close();
    }
}

// 2. 从数据库获取所有上架的商品 (status = 1)
$goods_list = [];
$goods_sql = "SELECT * FROM bc_shop_goods WHERE status = 1 ORDER BY id ASC";
$goods_res = $conn->query($goods_sql);
if ($goods_res && $goods_res->num_rows > 0) {
    while ($row = $goods_res->fetch_assoc()) {
        $goods_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <title>冠恩书屋 · 文创小铺</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

  <link href="https://fonts.googleapis.com/css2?family=Cormorant+SC:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* ===== 全局样式基调 ===== */
    * { box-sizing: border-box; margin:0; padding:0; }
    body { background-color: #f6f8fa; font-family: "Source Han Serif SC", "Noto Serif SC", serif; -webkit-font-smoothing: antialiased; color: #333; }
    
    :root {
      --morandi-blue: #3A5F6F;
      --morandi-red: #B86F52; 
      --text-main: #1e293b;
      --text-sub: #64748b;
      --card-bg: rgba(255,255,255,0.98);
    }

    .shop-container { max-width: 1100px; margin: 30px auto; padding: 0 20px 60px; }

    /* ===== 顶部商城欢迎页 ===== */
    .shop-header {
      background: var(--card-bg); border-radius: 20px; padding: 60px 40px; margin-bottom: 30px;
      border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
      text-align: center;
    }
    .shop-title { font-size: 38px; color: var(--text-main); font-weight: 800; letter-spacing: 1.5px; margin-bottom: 20px; }
    .shop-subtitle { color: var(--text-sub); font-size: 16px; line-height: 1.8; }
    .shop-en { font-family: "Cormorant SC", serif; font-size: 14px; letter-spacing: 0.3em; margin-top: 10px; color: #a5a58d; }

    /* ===== 余额/导航区 ===== */
    .balance-section { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; gap: 20px; }
    
    .balance-card { 
      background: #B86F52; color: white; padding: 16px 25px; border-radius: 50px; 
      display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600;
      box-shadow: 0 5px 15px rgba(184, 111, 82, 0.25);
    }
    .btn-back-home { 
      background: white; color: var(--text-sub); border: 1px solid #e2e8f0; 
      padding: 12px 20px; border-radius: 50px; text-decoration: none; 
      font-size: 14px; font-weight: 600; transition: all 0.3s;
      display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-back-home:hover { background: #f1f5f9; color: var(--morandi-blue); border-color: #cbd5e1; transform: translateY(-1px); }

    /* ===== 分类过滤器栏 ===== */
    .filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; overflow-x: auto; white-space: nowrap; }
    .filter-bar::-webkit-scrollbar { display: none; }
    .filter-tag { 
      font-size: 14px; color: var(--text-sub); padding: 8px 18px; 
      border-radius: 30px; cursor: pointer; transition: 0.3s; 
      font-family: -apple-system, sans-serif; font-weight: 600;
    }
    .filter-tag:hover { color: var(--morandi-blue); }
    .filter-tag.active { background: var(--morandi-blue); color: white; box-shadow: 0 4px 10px rgba(58, 95, 111, 0.2); }

    /* ===== 商品瀑布流网格 ===== */
    .goods-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }

    /* ===== 优雅的商品卡片 ===== */
    .goods-card { 
      background: var(--card-bg); border-radius: 18px; overflow: hidden; 
      border: 1px solid rgba(226, 232, 240, 0.8); 
      transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.1); 
      display: flex; flex-direction: column; position: relative;
    }
    .goods-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); }

    .goods-image-box { 
      width: 100%; aspect-ratio: 4/3; overflow: hidden; 
      background: #faf9f7; display: flex; align-items: center; justify-content: center; position: relative;
    }
    .goods-image-placeholder { font-size: 60px; color: #cbd5e1; font-family: "Cormorant SC", serif; transition: 0.5s; }
    .goods-card:hover .goods-image-placeholder { transform: scale(1.1); color: #a5a58d; }

    .goods-badge { 
      position: absolute; top: 15px; right: 15px; font-size: 10px; font-weight: bold; 
      text-transform: uppercase; color: white; padding: 4px 8px; border-radius: 4px; letter-spacing: 1px; z-index: 2;
    }
    .badge-digital { background: rgba(58, 95, 111, 0.85); } 
    .badge-physical { background: rgba(184, 111, 82, 0.85); } 

    .goods-info { padding: 20px; flex: 1; display: flex; flex-direction: column; }
    .goods-title { font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; line-height: 1.4; }
    .goods-desc { font-size: 13px; color: var(--text-sub); line-height: 1.6; margin-bottom: 20px; flex: 1; }

    .goods-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; border-top: 1px dashed #e2e8f0; padding-top: 15px; }
    .goods-price-box { font-family: -apple-system, sans-serif; }
    .goods-price { font-size: 18px; font-weight: 800; color: #1e293b; display: block; }
    .goods-tokens { font-size: 12px; color: #B86F52; font-weight: 600; }

    .btn-buy { 
      background: var(--morandi-blue); color: white; border: none; 
      padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; 
      transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; font-family: -apple-system, sans-serif;
    }
    .btn-buy:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(58, 95, 111, 0.25); }

    /* 手机端深度适配 */
    @media (max-width: 768px) {
      .shop-container { padding: 0 12px; margin-top: 15px; }
      .shop-header { padding: 35px 20px; border-radius: 16px; margin-bottom: 20px; }
      .shop-title { font-size: 26px; margin-bottom: 15px; }
      .balance-section { flex-direction: column-reverse; align-items: flex-start; gap: 12px; }
      .btn-back-home { width: 100%; justify-content: center; }
      .goods-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } 
      .goods-info { padding: 15px; }
      .goods-title { font-size: 14px; margin-bottom: 5px; }
      .goods-desc { font-size: 12px; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; } 
      .goods-footer { flex-direction: column; align-items: flex-start; gap: 8px; border: none; padding-top: 0; margin-top: 0; }
      .btn-buy { width: 100%; text-align: center; justify-content: center; padding: 8px; font-size: 12px; }
    }
  </style>
</head>
<body>

<div class="shop-container">
  
  <div class="shop-header">
    <div class="shop-title">拾光文创小铺</div>
    <p class="shop-subtitle">不仅仅是漂流，更是志同道合者的光影印记。</p>
    <p class="shop-subtitle">在此处，用“秋葵”兑换岁月，用文字搜奇引逸。</p>
    <div class="shop-en">GUANEN CURIO & BOOK DRIFT</div>
  </div>

  <div class="balance-section">
    <a href="../index.php" class="btn-back-home">
      <i class="fas fa-home"></i> 返回书屋主页
    </a>
    
    <?php if($is_logged_in): ?>
      <div class="balance-card">
        <div class="bal-label">秋葵余量：</div>
        <div class="bal-value" id="current-balance"><?php echo $qiuqiao_balance; ?> <span style="font-size:16px;">✵</span></div>
      </div>
    <?php else: ?>
      <div class="balance-card" style="background: #cbd5e1; cursor: pointer;" onclick="alert('请先登录书屋！'); location.href='user_login.php';">
        <div class="bal-label">未登录</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="filter-bar">
    <div class="filter-tag active" onclick="filterGoods('all', this)">全库</div>
    <div class="filter-tag" onclick="filterGoods('physical', this)">实体文创</div>
    <div class="filter-tag" onclick="filterGoods('digital', this)">虚拟数字</div>
  </div>

  <div class="goods-grid" id="goods-container">
    
    <?php foreach ($goods_list as $item): ?>
      <?php 
        $is_physical = ($item['type'] === 'physical');
        $badge_class = $is_physical ? 'badge-physical' : 'badge-digital';
        $badge_text  = $is_physical ? '实体文创' : '虚拟资产';
        $btn_text    = $is_physical ? '加入行囊' : '立即兑换';
        $btn_icon    = $is_physical ? 'fa-shopping-bag' : 'fa-hand-holding-usd';
        // 如果没有图片，取标题第一个字作为大写字母占位
        $placeholder = mb_substr($item['title'], 0, 1, 'UTF-8');
      ?>
      <div class="goods-card" data-type="<?php echo $item['type']; ?>">
        <div class="goods-image-box">
          <span class="goods-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
          
          <?php if (!empty($item['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" style="width:100%; height:100%; object-fit:cover;">
          <?php else: ?>
            <div class="goods-image-placeholder"><?php echo htmlspecialchars($placeholder); ?></div>
          <?php endif; ?>

        </div>
        <div class="goods-info">
          <div class="goods-title"><?php echo htmlspecialchars($item['title']); ?></div>
          <p class="goods-desc"><?php echo htmlspecialchars($item['description']); ?></p>
          <div class="goods-footer">
            <div class="goods-price-box">
              <?php if ($item['price_cash'] > 0): ?>
                <span class="goods-price">¥<?php echo number_format($item['price_cash'], 2); ?></span>
                <span class="goods-tokens">或 <?php echo $item['price_tokens']; ?> ✵</span>
              <?php else: ?>
                <span class="goods-tokens" style="font-size:16px;">全额 <?php echo $item['price_tokens']; ?> ✵</span>
              <?php endif; ?>
            </div>
            
            <button class="btn-buy" 
                    style="<?php echo !$is_physical ? 'background: var(--morandi-red);' : ''; ?>"
                    onclick="handleExchange(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>', <?php echo $item['price_tokens']; ?>, '<?php echo $item['type']; ?>')">
              <i class="fas <?php echo $btn_icon; ?>"></i> <?php echo $btn_text; ?>
            </button>
            
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if(empty($goods_list)): ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: 50px 0; color: #a5a58d;">
        <i class="fas fa-box-open" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
        <p>掌柜外出云游，铺子暂未上新...</p>
      </div>
    <?php endif; ?>

  </div> </div> <script>
  // 简单的分类过滤逻辑
  function filterGoods(type, element) {
    // 切换按钮激活状态
    document.querySelectorAll('.filter-tag').forEach(tag => tag.classList.remove('active'));
    element.classList.add('active');

    // 筛选卡片
    const cards = document.querySelectorAll('.goods-card');
    cards.forEach(card => {
      if (type === 'all' || card.getAttribute('data-type') === type) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }
// 真实的兑换/购买点击事件
  async function handleExchange(goodsId, title, priceTokens, type) {
    // 1. 检查是否登录
    const balanceEl = document.getElementById('current-balance');
    if (!balanceEl) {
      alert("请先登录冠恩书屋，方可进行兑换！");
      window.location.href = 'user_login.php';
      return;
    }

    // 2. 检查余额防呆
    const currentBalance = parseInt(balanceEl.innerText);
    if (currentBalance < priceTokens) {
      alert(`哎呀，秋葵不足！\n【${title}】需要 ${priceTokens} ✵，你当前只有 ${currentBalance} ✵。多去写写手帐赚取吧！`);
      return;
    }

    // 3. 弹窗二次确认
    const confirmMsg = type === 'physical' 
      ? `即将花费 ${priceTokens} ✵ 兑换实体文创：\n《${title}》\n\n确认将其收入行囊吗？(稍后将在个人中心填写收件地址)`
      : `即将花费 ${priceTokens} ✵ 点亮数字资产：\n《${title}》\n\n确认立即兑换吗？`;

    if (!confirm(confirmMsg)) return;

    // 4. 发起真实的交易请求
    try {
      const formData = new FormData();
      formData.append('action', 'buy_with_tokens');
      formData.append('goods_id', goodsId);

      const response = await fetch('../api/shop_api.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();

      if (result.success) {
        if (type === 'physical') {
          alert(`🎉 恭喜！兑换成功！\n订单号：${result.order_no}\n商品已放入你的行囊，请留意近期物流。`);
        } else {
          alert(`✨ 数字资产兑换成功！\n你的个人中心已亮起新的光芒。`);
        }
        // 刷新页面，以更新最新的秋葵余额和库存
        window.location.reload(); 
      } else {
        alert('兑换失败：' + result.message);
      }
    } catch (error) {
      console.error(error);
      alert('网络开小差了，请稍后再试。');
    }
  }
</script>

</body>
</html>