<?php
/**
 * 奖品管理页面
 * 
 * 职责：
 * 1. 管理抽奖系统的奖品设置。
 * 2. 提供统计概览（中奖率、总抽奖次数等）。
 * 3. 规范化改造：逻辑/视图分离，使用预处理语句，PHP 5.6 兼容。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 权限检查：必须是管理员
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: user_login.php");
    exit();
}

$conn = db_connect();
$message = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. 添加奖品
    if (isset($_POST['add_prize'])) {
        $prize_name = isset($_POST['prize_name']) ? trim($_POST['prize_name']) : '';
        $prize_value = isset($_POST['prize_value']) ? trim($_POST['prize_value']) : '';
        $is_win = isset($_POST['is_win']) ? 1 : 0;
        
        if (empty($prize_name)) {
            $message = '<div class="alert error">❌ 奖品名称不能为空</div>';
        } else {
            // 检查是否已存在相同奖品
            $check = $conn->prepare("SELECT id FROM lottery_records WHERE prize_name = ? LIMIT 1");
            $check->bind_param("s", $prize_name);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = '<div class="alert error">❌ 奖品名称已存在</div>';
            } else {
                // 插入新奖品 (此处逻辑遵循原代码，将奖品定义存入 lottery_records)
                $stmt = $conn->prepare("INSERT INTO lottery_records (prize_name, prize_value, is_win, user_id) VALUES (?, ?, ?, 0)");
                $stmt->bind_param("ssi", $prize_name, $prize_value, $is_win);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert success">✅ 奖品添加成功</div>';
                } else {
                    $message = '<div class="alert error">❌ 添加失败: ' . htmlspecialchars($conn->error) . '</div>';
                }
                $stmt->close();
            }
            $check->close();
        }
    }
    
    // 2. 更新奖品
    if (isset($_POST['update_prize'])) {
        $prize_name_original = isset($_POST['prize_name_original']) ? $_POST['prize_name_original'] : '';
        $prize_name = isset($_POST['prize_name']) ? trim($_POST['prize_name']) : '';
        $prize_value = isset($_POST['prize_value']) ? trim($_POST['prize_value']) : '';
        $is_win = isset($_POST['is_win']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE lottery_records SET prize_name = ?, prize_value = ?, is_win = ? WHERE prize_name = ? AND user_id = 0");
        $stmt->bind_param("ssis", $prize_name, $prize_value, $is_win, $prize_name_original);
        
        if ($stmt->execute()) {
            $message = '<div class="alert success">✅ 奖品更新成功</div>';
        } else {
            $message = '<div class="alert error">❌ 更新失败: ' . htmlspecialchars($conn->error) . '</div>';
        }
        $stmt->close();
    }
}

// 获取所有奖品 (user_id = 0 的记录被视为奖品定义)
$prizes = array();
$result = $conn->query("SELECT prize_name, prize_value, is_win, 
                        (SELECT COUNT(*) FROM lottery_records WHERE prize_name = lr.prize_name AND user_id != 0) as total_count,
                        (SELECT COUNT(*) FROM lottery_records WHERE prize_name = lr.prize_name AND user_id != 0 AND is_win = 1) as win_count
                        FROM lottery_records lr
                        WHERE user_id = 0
                        ORDER BY prize_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $prizes[] = $row;
    }
}

// 获取奖品分布统计 (仅统计真实用户的抽奖记录)
$stats = array('total_wins' => 0, 'total_draws' => 0, 'win_rate' => 0);
$stats_result = $conn->query("
    SELECT 
        SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as total_wins,
        COUNT(*) as total_draws
    FROM lottery_records
    WHERE user_id != 0
");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    if ($row) {
        $stats['total_wins'] = $row['total_wins'] ? $row['total_wins'] : 0;
        $stats['total_draws'] = $row['total_draws'] ? $row['total_draws'] : 0;
        $stats['win_rate'] = $stats['total_draws'] > 0 ? ($stats['total_wins'] * 100.0 / $stats['total_draws']) : 0;
    }
}

$conn->close();

// --- 视图部分 ---

$page_title = '奖品管理';

// 样式注入
$extra_css = '
<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-icon { font-size: 24px; color: #3b82f6; margin-bottom: 10px; }
    .stat-number { font-size: 28px; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 14px; color: #64748b; }
    
    .content-box { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 30px; }
    .section-title { font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 5px; font-size: 14px; font-weight: 500; color: #475569; }
    .form-control { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
    
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px; background: #f8fafc; color: #64748b; font-weight: 600; font-size: 13px; border-bottom: 1px solid #e2e8f0; }
    td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-secondary { background: #f1f5f9; color: #475569; }
    
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 50; }
    .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; }
    
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    .alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
';

ob_start();
?>

<div class="max-w-6xl mx-auto py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-gray-800">奖品管理</h1>
        <div class="flex gap-4">
            <a href="lottery_records.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">抽奖记录</a>
            <a href="user_dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">返回仪表盘</a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-gift"></i></div>
            <div class="stat-number"><?php echo count($prizes); ?></div>
            <div class="stat-label">奖品种类</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-number"><?php echo $stats['total_wins']; ?></div>
            <div class="stat-label">中奖总数</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percentage"></i></div>
            <div class="stat-number"><?php echo round($stats['win_rate'], 2); ?>%</div>
            <div class="stat-label">总体中奖率</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-number"><?php echo $stats['total_draws']; ?></div>
            <div class="stat-label">累计抽奖</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 添加奖品表单 -->
        <div class="lg:col-span-1">
            <div class="content-box">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> 添加奖品</h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">奖品名称</label>
                        <input type="text" name="prize_name" class="form-control" placeholder="例如：幸运星" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">奖品描述</label>
                        <textarea name="prize_value" class="form-control" rows="3" placeholder="奖品的相关说明..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_win" value="1">
                            <span class="text-sm text-gray-700">标记为“中奖”</span>
                        </label>
                    </div>
                    <button type="submit" name="add_prize" class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 transition">
                        添加新奖品
                    </button>
                </form>
            </div>
        </div>

        <!-- 奖品列表 -->
        <div class="lg:col-span-2">
            <div class="content-box">
                <h2 class="section-title"><i class="fas fa-list"></i> 奖品列表</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>奖品名称</th>
                                <th>状态</th>
                                <th>抽中次数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prizes)): ?>
                                <tr><td colspan="4" class="text-center py-8 text-gray-500">暂无奖品数据</td></tr>
                            <?php else: ?>
                                <?php foreach ($prizes as $prize): ?>
                                    <tr>
                                        <td>
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($prize['prize_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($prize['prize_value']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $prize['is_win'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo $prize['is_win'] ? '中奖' : '未中奖'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $prize['total_count']; ?></td>
                                        <td>
                                            <button onclick="openEditModal('<?php echo addslashes($prize['prize_name']); ?>', '<?php echo addslashes($prize['prize_value']); ?>', <?php echo $prize['is_win']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                编辑
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑模态框 -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3 class="text-lg font-bold mb-4">编辑奖品</h3>
        <form method="POST">
            <input type="hidden" name="update_prize" value="1">
            <input type="hidden" id="edit_prize_name_original" name="prize_name_original">
            <div class="form-group">
                <label class="form-label">奖品名称</label>
                <input type="text" id="edit_prize_name" name="prize_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">奖品描述</label>
                <textarea id="edit_prize_value" name="prize_value" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="edit_is_win" name="is_win" value="1">
                    <span class="text-sm text-gray-700">标记为“中奖”</span>
                </label>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">保存修改</button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-100 text-gray-600 py-2 rounded-lg font-medium hover:bg-gray-200">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(name, value, isWin) {
    document.getElementById('edit_prize_name').value = name;
    document.getElementById('edit_prize_name_original').value = name;
    document.getElementById('edit_prize_value').value = value;
    document.getElementById('edit_is_win').checked = isWin == 1;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 点击模态框背景关闭
window.onclick = function(event) {
    let modal = document.getElementById('editModal');
    if (event.target == modal) closeEditModal();
}
</script>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
