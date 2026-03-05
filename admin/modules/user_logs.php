<?php
/**
 * 用户足迹模块 - 管理员后台
 * 功能：展示所有用户的访问足迹和操作记录
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_config.php';

$conn = db_connect();

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM user_logs WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: admin_dashboard.php?tab=user_logs");
    exit;
}

// 处理清空操作
if (isset($_GET['action']) && $_GET['action'] === 'clear_all') {
    $conn->query("TRUNCATE TABLE user_logs");
    header("Location: admin_dashboard.php?tab=user_logs");
    exit;
}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <?php
    // 统计总访问次数
    $total_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs")->fetch_assoc()['count'];
    // 统计独立访客数
    $unique_ips = $conn->query("SELECT COUNT(DISTINCT ip_address) as count FROM user_logs")->fetch_assoc()['count'];
    // 统计今日访问次数
    $today_logs = $conn->query("SELECT COUNT(*) as count FROM user_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
    ?>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                <i class="fas fa-eye text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">总访问次数</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $total_logs; ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">独立访客</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $unique_ips; ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                <i class="fas fa-calendar-day text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">今日访问</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $today_logs; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
    <form method="get" class="flex flex-wrap gap-4">
        <input type="hidden" name="tab" value="user_logs">
        <div class="flex items-center gap-2">
            <label for="username" class="text-sm text-gray-600">用户名:</label>
            <input type="text" name="username" id="username" placeholder="用户名" value="<?php echo isset($_GET['username']) ? $_GET['username'] : ''; ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="flex items-center gap-2">
            <label for="ip" class="text-sm text-gray-600">IP地址:</label>
            <input type="text" name="ip" id="ip" placeholder="IP地址" value="<?php echo isset($_GET['ip']) ? $_GET['ip'] : ''; ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="flex items-center gap-2">
            <label for="device" class="text-sm text-gray-600">设备:</label>
            <select name="device" id="device" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">所有设备</option>
                <option value="iPhone" <?php echo isset($_GET['device']) && $_GET['device'] == 'iPhone' ? 'selected' : ''; ?>>iPhone</option>
                <option value="Android" <?php echo isset($_GET['device']) && $_GET['device'] == 'Android' ? 'selected' : ''; ?>>Android</option>
                <option value="Windows PC" <?php echo isset($_GET['device']) && $_GET['device'] == 'Windows PC' ? 'selected' : ''; ?>>Windows PC</option>
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-search mr-2"></i>筛选
        </button>
    </form>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex justify-between items-center p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">用户访问日志</h2>
        <div class="flex gap-3">
            <form method="POST" onsubmit="return confirm('确定要清空所有日志吗？此操作不可恢复！')" class="inline">
                <input type="hidden" name="tab" value="user_logs">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm">
                    <i class="fas fa-trash mr-2"></i>清空所有
                </button>
            </form>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">用户名</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP地址</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">设备</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">页面URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">访问时间</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php
                // 构建查询语句
                $sql = "SELECT * FROM user_logs";
                $where = [];
                
                if (isset($_GET['username']) && !empty($_GET['username'])) {
                    $where[] = "username LIKE '%" . $conn->real_escape_string($_GET['username']) . "%'";
                }
                
                if (isset($_GET['ip']) && !empty($_GET['ip'])) {
                    $where[] = "ip_address LIKE '%" . $conn->real_escape_string($_GET['ip']) . "%'";
                }
                
                if (isset($_GET['device']) && !empty($_GET['device'])) {
                    $where[] = "device = '" . $conn->real_escape_string($_GET['device']) . "'";
                }
                
                if (!empty($where)) {
                    $sql .= " WHERE " . implode(" AND ", $where);
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT 100";
                
                $result = $conn->query($sql);
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$row['id']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>{$row['username']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$row['ip_address']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$row['device']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$row['action']}</td>";
                    echo "<td class='px-6 py-4 text-sm text-gray-500 max-w-xs truncate'>{$row['page_url']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>{$row['created_at']}</td>";
                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>";
                    echo "<a href='admin_dashboard.php?tab=user_logs&action=delete&id={$row['id']}' onclick='return confirm(\'确定要删除这条记录吗？\')' class='text-red-600 hover:text-red-900'>";
                    echo "<i class='fas fa-trash'></i> 删除";
                    echo "</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($result->num_rows === 0): ?>
        <div class="text-center p-12">
            <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">暂无访问日志记录</p>
        </div>
    <?php endif; ?>
</div>