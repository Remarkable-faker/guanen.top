<?php
/**
 * 模块：漂流认证用户审核
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 默认优先显示待审核用户
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '0';

$where = "WHERE status = ?";

// 获取统计数据
$stmt_total = $conn->prepare("SELECT COUNT(*) FROM bc_users " . $where);
$stmt_total->bind_param('s', $filter_status);
$stmt_total->execute();
$total_count = $stmt_total->get_result()->fetch_row()[0] ?? 0;
$total_pages = max(1, ceil($total_count / $limit));

// 获取用户列表
$stmt_users = $conn->prepare("SELECT * FROM bc_users " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt_users->bind_param('sii', $filter_status, $limit, $offset);
$stmt_users->execute();
$result = $stmt_users->get_result();
?>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <h2 class="text-lg font-bold text-slate-800 mb-4">漂流认证审核中心</h2>
        <div class="flex space-x-2 border-b border-slate-100">
            <a href="?tab=auth_users&filter_status=0" class="px-4 py-2 text-sm font-bold <?php echo $filter_status == '0' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-500'; ?>">待审核</a>
            <a href="?tab=auth_users&filter_status=1" class="px-4 py-2 text-sm font-bold <?php echo $filter_status == '1' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-500'; ?>">已认证</a>
            <a href="?tab=auth_users&filter_status=2" class="px-4 py-2 text-sm font-bold <?php echo $filter_status == '2' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-slate-500'; ?>">已拒绝</a>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <th class="px-6 py-4">用户昵称</th>
                        <th class="px-6 py-4">真实姓名</th>
                        <th class="px-6 py-4">身份证号</th>
                        <th class="px-6 py-4">申请时间</th>
                        <th class="px-6 py-4 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if ($result && $result->num_rows > 0): while($user = $result->fetch_assoc()): ?>
                    <tr class="text-sm font-bold text-slate-600 hover:bg-slate-50/50">
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['nickname']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['real_name']); ?></td>
                        <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($user['id_card']); ?></td>
                        <td class="px-6 py-4 text-xs text-slate-400"><?php echo $user['created_at']; ?></td>
                        <td class="px-6 py-4 text-right">
                            <?php if($filter_status == '0'): ?>
                            <a href="?tab=auth_users&action=approve&user_id=<?php echo $user['id']; ?>" class="px-3 py-1 bg-emerald-100 text-emerald-600 rounded text-xs">通过</a>
                            <a href="?tab=auth_users&action=reject&user_id=<?php echo $user['id']; ?>" class="px-3 py-1 bg-rose-100 text-rose-600 rounded text-xs ml-2">拒绝</a>
                            <?php else: ?>
                            <span class="text-xs text-slate-400 italic">已处理</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="py-20 text-center text-slate-300 font-bold italic">暂无相关用户...</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- 分页组件将添加在这里 -->
    </div>
</div>
