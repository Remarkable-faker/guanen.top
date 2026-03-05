<?php
/**
 * 模块：文创小铺管理 (shop_manage.php)
 * 注意：此文件被 include 在 admin_dashboard.php 中运行，已共享 $conn
 */

// 防越权直接访问模块文件
if (!defined('ADMIN_AUTH')) {
    exit('Access Denied');
}

// 获取用户表名
$user_table = 'users';
$check_table = $conn->query("SHOW COLUMNS FROM `$user_table` ");
if (!$check_table) { $user_table = 'bc_users'; }

// ==========================================
// 接口逻辑处理 (POST 请求处理)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shop_action'])) {
    $shop_action = $_POST['shop_action'];
    
    try {
        // 1. 新增/编辑商品
        if ($shop_action === 'save_goods') {
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? 'physical';
            $price_cash = floatval($_POST['price_cash'] ?? 0);
            $price_tokens = intval($_POST['price_tokens'] ?? 0);
            $stock = intval($_POST['stock'] ?? 0);
            $status = intval($_POST['status'] ?? 1);
            $image_url = trim($_POST['image_url'] ?? '');

            if (empty($title)) throw new Exception('商品名称不能为空');

            if ($id > 0) {
                // 更新
                $sql = "UPDATE bc_shop_goods SET title=?, description=?, image_url=?, type=?, price_cash=?, price_tokens=?, stock=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssdiiii", $title, $desc, $image_url, $type, $price_cash, $price_tokens, $stock, $status, $id);
            } else {
                // 新增
                $sql = "INSERT INTO bc_shop_goods (title, description, image_url, type, price_cash, price_tokens, stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssdiii", $title, $desc, $image_url, $type, $price_cash, $price_tokens, $stock, $status);
            }
            if (!$stmt->execute()) throw new Exception('数据库保存失败');
            
            // 成功后刷新页面并带上参数
            echo "<script>window.location.href='?tab=shop_manage&view=goods&msg=success';</script>";
            exit;
        }

        // 2. 快捷上下架
        if ($shop_action === 'toggle_status') {
            $id = intval($_POST['id']);
            $status = intval($_POST['status']);
            $conn->query("UPDATE bc_shop_goods SET status = $status WHERE id = $id");
            echo "<script>window.location.href='?tab=shop_manage&view=goods';</script>";
            exit;
        }

        // 3. 订单发货处理
        if ($shop_action === 'ship_order') {
            $id = intval($_POST['id']);
            $conn->query("UPDATE bc_shop_orders SET status = 1 WHERE id = $id");
            echo "<script>window.location.href='?tab=shop_manage&view=orders&msg=shipped';</script>";
            exit;
        }

    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        echo "<script>alert('操作失败: {$error_msg}');</script>";
    }
}

// ==========================================
// 数据读取
// ==========================================
// 当前视图模式 (默认为 goods)
$current_view = $_GET['view'] ?? 'goods';

// 获取所有商品
$goods_res = $conn->query("SELECT * FROM bc_shop_goods ORDER BY id DESC");
$goods_list = [];
while ($row = $goods_res->fetch_assoc()) { $goods_list[] = $row; }

// 获取所有订单
$orders_sql = "
    SELECT o.*, g.title as goods_title, g.type as goods_type, u.username 
    FROM bc_shop_orders o
    LEFT JOIN bc_shop_goods g ON o.goods_id = g.id
    LEFT JOIN `$user_table` u ON o.user_id = u.id
    ORDER BY o.created_at DESC
";
$orders_res = $conn->query($orders_sql);
$orders_list = [];
while ($row = $orders_res->fetch_assoc()) { $orders_list[] = $row; }
?>

<style>
    /* 简易弹窗样式，兼容 Tailwind */
    .shop-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; }
    .shop-modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="mb-6 border-b border-gray-200">
    <nav class="-mb-px flex space-x-8">
        <a href="?tab=shop_manage&view=goods" 
           class="<?php echo $current_view === 'goods' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
            <i class="fas fa-box-open mr-2"></i> 货架管理
        </a>
        <a href="?tab=shop_manage&view=orders" 
           class="<?php echo $current_view === 'orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
            <i class="fas fa-receipt mr-2"></i> 账单与发货
        </a>
    </nav>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded relative" role="alert">
    <span class="block sm:inline">操作成功！</span>
</div>
<?php endif; ?>

<?php if ($current_view === 'goods'): ?>
<div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
        <h3 class="text-lg leading-6 font-medium text-gray-900">共 <?php echo count($goods_list); ?> 件商品</h3>
        <button onclick="openGoodsModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded shadow-sm text-sm transition flex items-center">
            <i class="fas fa-plus mr-2"></i> 上架新物
        </button>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品名称</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">类型</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">定价</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">库存</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($goods_list as $g): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($g['title']); ?></div>
                        <div class="text-xs text-gray-500">ID: #<?php echo $g['id']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if($g['type'] == 'physical'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">实体文创</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">虚拟数字</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-orange-600"><?php echo $g['price_tokens']; ?> ✵</div>
                        <?php if($g['price_cash'] > 0): ?>
                            <div class="text-xs text-gray-400">¥<?php echo $g['price_cash']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $g['stock']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if($g['status'] == 1): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">营业中</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-600">已下架</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick='openGoodsModal(<?php echo json_encode($g, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i> 编辑</button>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="shop_action" value="toggle_status">
                            <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                            <?php if($g['status'] == 1): ?>
                                <input type="hidden" name="status" value="0">
                                <button type="submit" class="text-red-600 hover:text-red-900" title="下架"><i class="fas fa-arrow-down"></i> 下架</button>
                            <?php else: ?>
                                <input type="hidden" name="status" value="1">
                                <button type="submit" class="text-green-600 hover:text-green-900" title="上架"><i class="fas fa-arrow-up"></i> 上架</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($goods_list)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">暂无商品数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($current_view === 'orders'): ?>
<div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg leading-6 font-medium text-gray-900">共 <?php echo count($orders_list); ?> 笔订单</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">订单号/时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">买家</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">兑换物品</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">实付</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orders_list as $o): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-mono text-gray-900"><?php echo $o['order_no']; ?></div>
                        <div class="text-xs text-gray-500"><?php echo $o['created_at']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($o['username'] ?? '未知'); ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars($o['goods_title']); ?></div>
                        <?php if($o['goods_type'] == 'physical'): ?>
                            <div class="text-xs text-orange-600 mt-1">需发货实物</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-orange-600">
                        <?php echo $o['pay_amount']; ?> <?php echo $o['pay_type'] == 'tokens' ? '✵' : '元'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                            if($o['status'] == 0) echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">待处理/待发货</span>';
                            else if($o['status'] == 1) echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已完成/已发货</span>';
                            else echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">已取消</span>';
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <?php if($o['status'] == 0 && $o['goods_type'] == 'physical'): ?>
                            <form method="POST" onsubmit="return confirm('确定已经将物品寄出了吗？操作不可逆。');">
                                <input type="hidden" name="shop_action" value="ship_order">
                                <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                <button type="submit" class="text-white bg-green-600 hover:bg-green-700 px-3 py-1 rounded shadow-sm text-xs"><i class="fas fa-truck"></i> 标记发货</button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs">无可操作</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($orders_list)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">暂无订单数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


<div class="shop-modal-overlay" id="goodsModal">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden transform transition-all">
        <div class="bg-slate-900 px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white" id="modal-title">上架新物</h3>
            <button onclick="closeGoodsModal()" class="text-gray-300 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form method="POST" action="?tab=shop_manage&view=goods" class="p-6">
            <input type="hidden" name="shop_action" value="save_goods">
            <input type="hidden" name="id" id="g_id" value="0">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">物品名称</label>
                    <input type="text" name="title" id="g_title" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">文艺简介</label>
                    <textarea name="description" id="g_desc" rows="2" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm" placeholder="一句打动人心的话..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">物品类型</label>
                        <select name="type" id="g_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                            <option value="physical">实体文创 (需发货)</option>
                            <option value="digital">虚拟数字 (秒到账)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">库存数量</label>
                        <input type="number" name="stock" id="g_stock" value="99" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">秋葵定价 (✵)</label>
                        <input type="number" name="price_tokens" id="g_tokens" value="100" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">法币定价 (¥)</label>
                        <input type="number" step="0.01" name="price_cash" id="g_cash" value="0.00" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">上下架状态</label>
                    <select name="status" id="g_status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 border p-2 text-sm">
                        <option value="1">立即上架营业</option>
                        <option value="0">放入仓库隐藏</option>
                    </select>
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-3">
                <button type="button" onclick="closeGoodsModal()" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">取消</button>
                <button type="submit" class="bg-blue-600 border border-transparent rounded-md shadow-sm py-2 px-4 flex justify-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none">保存入库</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGoodsModal(data = null) {
        document.getElementById('goodsModal').classList.add('active');
        if (data) {
            document.getElementById('modal-title').innerText = '修缮物品信息';
            document.getElementById('g_id').value = data.id;
            document.getElementById('g_title').value = data.title;
            document.getElementById('g_desc').value = data.description;
            document.getElementById('g_type').value = data.type;
            document.getElementById('g_stock').value = data.stock;
            document.getElementById('g_tokens').value = data.price_tokens;
            document.getElementById('g_cash').value = data.price_cash;
            document.getElementById('g_status').value = data.status;
        } else {
            document.getElementById('modal-title').innerText = '上架新物';
            document.getElementById('g_id').value = '0';
            document.getElementById('g_title').value = '';
            document.getElementById('g_desc').value = '';
            document.getElementById('g_type').value = 'physical';
            document.getElementById('g_stock').value = '99';
            document.getElementById('g_tokens').value = '100';
            document.getElementById('g_cash').value = '0.00';
            document.getElementById('g_status').value = '1';
        }
    }

    function closeGoodsModal() {
        document.getElementById('goodsModal').classList.remove('active');
    }
</script>