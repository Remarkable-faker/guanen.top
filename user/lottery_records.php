<?php
/**
 * 抽奖记录查询页面
 * 
 * 职责：
 * 1. 展示所有用户的抽奖历史。
 * 2. 提供今日统计与累计统计。
 * 3. 规范化改造：逻辑/视图分离，使用预处理语句，支持分页和基础筛选。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 权限检查：必须是管理员
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: user_login.php");
    exit();
}

$conn = db_connect();

// 1. 处理统计数据
$stats = array(
    'today_draws' => 0,
    'today_wins' => 0,
    'total_draws' => 0,
    'total_wins' => 0
);

// 今日统计
$today_sql = "SELECT 
                COUNT(*) as today_draws,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as today_wins 
              FROM lottery_records 
              WHERE DATE(draw_time) = CURDATE() AND user_id != 0";
$today_res = $conn->query($today_sql);
if ($today_res && $row = $today_res->fetch_assoc()) {
    $stats['today_draws'] = (int)$row['today_draws'];
    $stats['today_wins'] = (int)$row['today_wins'];
}

// 累计统计
$total_sql = "SELECT 
                COUNT(*) as total_draws,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as total_wins 
              FROM lottery_records 
              WHERE user_id != 0";
$total_res = $conn->query($total_sql);
if ($total_res && $row = $total_res->fetch_assoc()) {
    $stats['total_draws'] = (int)$row['total_draws'];
    $stats['total_wins'] = (int)$row['total_wins'];
}

// 2. 分页处理
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 3. 获取记录列表
$records = array();
$list_sql = "SELECT lr.*, u.username as user_name 
             FROM lottery_records lr 
             LEFT JOIN users u ON lr.user_id = u.id 
             WHERE lr.user_id != 0 
             ORDER BY lr.draw_time DESC 
             LIMIT ? OFFSET ?";
$stmt = $conn->prepare($list_sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// 获取总数用于分页
$total_records = $stats['total_draws'];
$total_pages = ceil($total_records / $limit);

$conn->close();

// --- 视图部分 ---

$page_title = '抽奖记录';

$extra_css = '
<style>
    .stats-header { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-mini-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; display: flex; align-items: center; gap: 15px; }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .icon-blue { background: #eff6ff; color: #3b82f6; }
    .icon-green { background: #f0fdf4; color: #22c55e; }
    .stat-mini-info div:first-child { font-size: 12px; color: #64748b; margin-bottom: 2px; }
    .stat-mini-info div:last-child { font-size: 18px; font-weight: 700; color: #1e293b; }

    .record-table-box { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 16px; background: #f8fafc; color: #64748b; font-weight: 600; font-size: 13px; border-bottom: 1px solid #e2e8f0; }
    td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #475569; }
    
    .win-badge { background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; border: 1px solid #fee2e2; }
    .no-win-badge { color: #94a3b8; font-size: 12px; }
    
    .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
    .page-link { padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; color: #475569; text-decoration: none; }
    .page-link.active { background: #3b82f6; color: white; border-color: #3b82f6; }
    .page-link:hover:not(.active) { background: #f8fafc; }
</style>
';

ob_start();
?>

<div class="max-w-6xl mx-auto py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">抽奖记录</h1>
            <p class="text-gray-500 text-sm mt-1">查看系统所有抽奖活动的历史数据</p>
        </div>
        <div class="flex gap-3">
            <div class="dropdown relative group">
                <button class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center gap-2">
                    <i class="fas fa-download"></i> 导出数据
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white border border-gray-100 rounded-xl shadow-xl hidden group-hover:block z-10 overflow-hidden">
                    <a href="lottery_export.php?type=records&format=csv" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-bottom border-gray-100">导出记录 (CSV)</a>
                    <a href="lottery_export.php?type=prizes&format=csv" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-bottom border-gray-100">奖品统计 (CSV)</a>
                    <a href="lottery_export.php?type=summary&format=csv" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">运行汇总 (CSV)</a>
                </div>
            </div>
            <a href="lottery_config.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200">
                <i class="fas fa-cog"></i> 设置
            </a>
        </div>
    </div>

    <!-- 统计概览 -->
    <div class="stats-header">
        <div class="stat-mini-card">
            <div class="stat-mini-icon icon-blue"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-mini-info">
                <div>今日抽奖</div>
                <div><?php echo $stats['today_draws']; ?> 次</div>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon icon-green"><i class="fas fa-gift"></i></div>
            <div class="stat-mini-info">
                <div>今日中奖</div>
                <div><?php echo $stats['today_wins']; ?> 次</div>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon icon-blue"><i class="fas fa-database"></i></div>
            <div class="stat-mini-info">
                <div>累计抽奖</div>
                <div><?php echo $stats['total_draws']; ?> 次</div>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon icon-green"><i class="fas fa-trophy"></i></div>
            <div class="stat-mini-info">
                <div>累计中奖</div>
                <div><?php echo $stats['total_wins']; ?> 次</div>
            </div>
        </div>
    </div>

    <!-- 记录列表 -->
    <div class="record-table-box">
        <table>
            <thead>
                <tr>
                    <th>时间</th>
                    <th>用户名</th>
                    <th>所获结果</th>
                    <th>状态</th>
                    <th>IP地址</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="5" class="text-center py-12 text-gray-500">暂无任何抽奖记录</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="whitespace-nowrap"><?php echo date('Y-m-d H:i', strtotime($record['draw_time'])); ?></td>
                            <td class="font-medium text-gray-700"><?php echo htmlspecialchars($record['user_name'] ? $record['user_name'] : '未知用户'); ?></td>
                            <td><?php echo htmlspecialchars($record['prize_name']); ?></td>
                            <td>
                                <?php if ($record['is_win']): ?>
                                    <span class="win-badge"><i class="fas fa-check mr-1"></i>中奖</span>
                                <?php else: ?>
                                    <span class="no-win-badge">未中奖</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs font-mono text-gray-400"><?php echo htmlspecialchars($record['ip_address'] ? $record['ip_address'] : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页导航 -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
