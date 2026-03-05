<?php
/**
 * 抽奖系统设置页面
 * 
 * 职责：
 * 1. 管理抽奖系统的全局配置（次数限制、音效、动画等）。
 * 2. 提供系统运行状态的简要统计。
 * 3. 规范化改造：逻辑/视图分离，使用预处理语句，集成 Master Layout。
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

// 默认配置
$default_config = array(
    'daily_draw_limit' => '3',
    'total_draw_limit' => '99',
    'enable_lottery' => '1',
    'announcement' => '欢迎参与幸运抽奖！',
    'enable_prize_sound' => '1',
    'enable_animation' => '1',
    'prize_rotation_speed' => '50',
    'minimum_interval' => '3000'
);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. 保存配置
    if (isset($_POST['save_config'])) {
        $updates = array(
            'daily_draw_limit' => isset($_POST['daily_draw_limit']) ? intval($_POST['daily_draw_limit']) : 3,
            'total_draw_limit' => isset($_POST['total_draw_limit']) ? intval($_POST['total_draw_limit']) : 99,
            'enable_lottery' => isset($_POST['enable_lottery']) ? '1' : '0',
            'announcement' => isset($_POST['announcement']) ? trim($_POST['announcement']) : '',
            'enable_prize_sound' => isset($_POST['enable_prize_sound']) ? '1' : '0',
            'enable_animation' => isset($_POST['enable_animation']) ? '1' : '0',
            'prize_rotation_speed' => isset($_POST['prize_rotation_speed']) ? intval($_POST['prize_rotation_speed']) : 50,
            'minimum_interval' => isset($_POST['minimum_interval']) ? intval($_POST['minimum_interval']) : 3000
        );

        // 验证数据
        $valid = true;
        if ($updates['daily_draw_limit'] < 1) { $message = '<div class="alert error">❌ 每日抽奖次数必须大于0</div>'; $valid = false; }
        
        if ($valid) {
            $success = true;
            foreach ($updates as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO lottery_config (config_key, config_value, updated_at) 
                                       VALUES (?, ?, NOW()) 
                                       ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()");
                $stmt->bind_param("sss", $key, $value, $value);
                if (!$stmt->execute()) {
                    $success = false;
                    break;
                }
                $stmt->close();
            }
            
            if ($success) {
                $message = '<div class="alert success">✅ 配置保存成功</div>';
            } else {
                $message = '<div class="alert error">❌ 保存失败: ' . htmlspecialchars($conn->error) . '</div>';
            }
        }
    }
    
    // 2. 重置默认值
    if (isset($_POST['reset_defaults'])) {
        foreach ($default_config as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO lottery_config (config_key, config_value, updated_at) 
                                   VALUES (?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        $message = '<div class="alert success">✅ 已重置为默认配置</div>';
    }
}

// 加载当前配置
$config = $default_config;
$result = $conn->query("SELECT config_key, config_value FROM lottery_config");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $config[$row['config_key']] = $row['config_value'];
    }
}

// 获取系统简要统计
$today = date('Y-m-d');
$stats = array(
    'today_draws' => 0,
    'total_draws' => 0,
    'total_users' => 0,
    'total_wins' => 0
);

$res = $conn->query("SELECT COUNT(*) as count FROM lottery_records WHERE DATE(draw_time) = '$today'");
if ($res) $stats['today_draws'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(*) as count FROM lottery_records");
if ($res) $stats['total_draws'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM lottery_records");
if ($res) $stats['total_users'] = $res->fetch_assoc()['count'];

$res = $conn->query("SELECT SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as count FROM lottery_records");
if ($res) $stats['total_wins'] = $res->fetch_assoc()['count'];

$conn->close();

// --- 视图部分 ---

$page_title = '系统设置';

$extra_css = '
<style>
    .config-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 25px; }
    .card-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #1e293b; }
    .card-body { padding: 25px; }
    
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; color: #475569; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; }
    .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    .switch-group { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9; }
    .switch-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
    .switch-label { font-size: 14px; color: #475569; cursor: pointer; flex: 1; }
    
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-box { background: #f0f9ff; border: 1px solid #e0f2fe; padding: 15px; border-radius: 10px; text-align: center; }
    .stat-val { font-size: 20px; font-weight: 700; color: #0369a1; }
    .stat-lab { font-size: 12px; color: #0ea5e9; margin-top: 4px; }
    
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    .alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
';

ob_start();
?>

<div class="max-w-5xl mx-auto py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">系统设置</h1>
            <p class="text-gray-500 text-sm mt-1">配置抽奖系统的核心参数与运行表现</p>
        </div>
        <div class="flex gap-4">
            <a href="lottery_prizes.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">奖品管理</a>
            <a href="user_dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">返回仪表盘</a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- 运行状态 -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-val"><?php echo $stats['today_draws']; ?></div>
            <div class="stat-lab">今日抽奖</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?php echo $stats['total_draws']; ?></div>
            <div class="stat-lab">累计抽奖</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?php echo $stats['total_users']; ?></div>
            <div class="stat-lab">参与用户</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?php echo $stats['total_wins']; ?></div>
            <div class="stat-lab">中奖总数</div>
        </div>
    </div>

    <form method="POST">
        <!-- 抽奖规则 -->
        <div class="config-card">
            <div class="card-header"><i class="fas fa-gavel"></i> 抽奖规则配置</div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>每日次数限制</label>
                        <input type="number" name="daily_draw_limit" class="form-control" value="<?php echo htmlspecialchars($config['daily_draw_limit']); ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>累计次数限制</label>
                        <input type="number" name="total_draw_limit" class="form-control" value="<?php echo htmlspecialchars($config['total_draw_limit']); ?>" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>系统公告</label>
                    <textarea name="announcement" class="form-control" rows="2"><?php echo htmlspecialchars($config['announcement']); ?></textarea>
                </div>
                <div class="mt-4">
                    <div class="switch-group">
                        <input type="checkbox" id="enable_lottery" name="enable_lottery" value="1" <?php echo $config['enable_lottery'] == '1' ? 'checked' : ''; ?>>
                        <label for="enable_lottery" class="switch-label">启用抽奖系统 (关闭后用户将无法进入抽奖页面)</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 视觉与音效 -->
        <div class="config-card">
            <div class="card-header"><i class="fas fa-magic"></i> 交互与表现</div>
            <div class="card-body">
                <div class="form-row">
                    <div class="switch-group">
                        <input type="checkbox" id="enable_prize_sound" name="enable_prize_sound" value="1" <?php echo $config['enable_prize_sound'] == '1' ? 'checked' : ''; ?>>
                        <label for="enable_prize_sound" class="switch-label">中奖音效</label>
                    </div>
                    <div class="switch-group">
                        <input type="checkbox" id="enable_animation" name="enable_animation" value="1" <?php echo $config['enable_animation'] == '1' ? 'checked' : ''; ?>>
                        <label for="enable_animation" class="switch-label">转盘动画</label>
                    </div>
                </div>
                <div class="form-row mt-4">
                    <div class="form-group">
                        <label>动画速度 (10-100)</label>
                        <input type="range" name="prize_rotation_speed" class="w-full" min="10" max="100" value="<?php echo htmlspecialchars($config['prize_rotation_speed']); ?>">
                    </div>
                    <div class="form-group">
                        <label>抽奖最小间隔 (毫秒)</label>
                        <input type="number" name="minimum_interval" class="form-control" value="<?php echo htmlspecialchars($config['minimum_interval']); ?>" step="500" min="1000">
                    </div>
                </div>
            </div>
        </div>

        <!-- 操作按钮 -->
        <div class="flex gap-4">
            <button type="submit" name="save_config" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                <i class="fas fa-save mr-2"></i> 保存当前配置
            </button>
            <button type="submit" name="reset_defaults" onclick="return confirm('确定要重置所有配置为默认值吗？')" class="px-6 bg-gray-100 text-gray-600 py-3 rounded-xl font-medium hover:bg-gray-200 transition">
                <i class="fas fa-undo mr-2"></i> 重置默认
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>
