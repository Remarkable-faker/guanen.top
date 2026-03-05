<?php
/**
 * 问卷建议模块 - “大厂风格”重构版
 * 优化了状态筛选的交互，并统一了整体UI风格。
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 (真实数据查询) ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// 统计数据
$stats = [
    'total_suggestions' => $conn->query("SELECT COUNT(*) FROM wenjuan_suggestions")->fetch_row()[0] ?? 0,
    'pending_count' => $conn->query("SELECT COUNT(*) FROM wenjuan_suggestions WHERE status = 'pending'")->fetch_row()[0] ?? 0,
    'replied_count' => $conn->query("SELECT COUNT(*) FROM wenjuan_suggestions WHERE status = 'replied'")->fetch_row()[0] ?? 0,
];

// 构建查询
$where = '';
if (!empty($status_filter)) {
    $where = "WHERE status = ?";
}

$stmt_total = $conn->prepare("SELECT COUNT(*) FROM wenjuan_suggestions " . $where);
if (!empty($status_filter)) {
    $stmt_total->bind_param('s', $status_filter);
}
$stmt_total->execute();
$total_count = $stmt_total->get_result()->fetch_row()[0] ?? 0;
$total_pages = max(1, ceil($total_count / $limit));

$stmt_suggestions = $conn->prepare("SELECT * FROM wenjuan_suggestions " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
if (!empty($status_filter)) {
    $stmt_suggestions->bind_param('sii', $status_filter, $limit, $offset);
} else {
    $stmt_suggestions->bind_param('ii', $limit, $offset);
}
$stmt_suggestions->execute();
$suggestions = $stmt_suggestions->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<div class="animate-fade-in space-y-8">
    <!-- 数据统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center space-x-4">
            <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-comments"></i></div>
            <div><p class="text-slate-400 text-sm font-medium">累计收到建议</p><h3 class="text-2xl font-bold text-slate-800"><?php echo number_format($stats['total_suggestions']); ?></h3></div>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center space-x-4">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-clock"></i></div>
            <div><p class="text-slate-400 text-sm font-medium">待处理建议</p><h3 class="text-2xl font-bold text-slate-800"><?php echo number_format($stats['pending_count']); ?></h3></div>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center space-x-4">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-check-double"></i></div>
            <div><p class="text-slate-400 text-sm font-medium">已回复建议</p><h3 class="text-2xl font-bold text-slate-800"><?php echo number_format($stats['replied_count']); ?></h3></div>
        </div>
    </div>

    <!-- 建议列表 -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <!-- 筛选区 -->
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center space-x-2 bg-slate-100 p-1.5 rounded-2xl w-full md:w-auto">
                <a href="?tab=wenjuan" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all <?php echo !$status_filter ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:bg-white/50'; ?>">全部</a>
                <a href="?tab=wenjuan&status=pending" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all <?php echo $status_filter == 'pending' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:bg-white/50'; ?>">待处理</a>
                <a href="?tab=wenjuan&status=read" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all <?php echo $status_filter == 'read' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:bg-white/50'; ?>">已查阅</a>
                <a href="?tab=wenjuan&status=replied" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all <?php echo $status_filter == 'replied' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:bg-white/50'; ?>">已回复</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/50 text-slate-400 text-xs font-bold uppercase tracking-widest border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-6">建议内容</th>
                        <th class="px-6 py-6 text-center">状态</th>
                        <th class="px-6 py-6">提交时间</th>
                        <th class="px-8 py-6 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($suggestions)): ?>
                        <tr><td colspan="4" class="px-8 py-20 text-center text-slate-300 italic font-medium"><i class="fas fa-ghost mb-4 text-4xl block"></i>暂无建议记录...</td></tr>
                    <?php else: ?>
                        <?php foreach ($suggestions as $s): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-8 py-6 max-w-md">
                                <p class="font-medium text-slate-700 truncate group-hover:whitespace-normal"><?php echo htmlspecialchars($s['suggestion']); ?></p>
                            </td>
                            <td class="px-6 py-6 text-center">
                                <?php 
                                    $status_map = [
                                        'pending' => 'bg-rose-100 text-rose-600',
                                        'read' => 'bg-blue-100 text-blue-600',
                                        'replied' => 'bg-emerald-100 text-emerald-600',
                                    ];
                                    $status_class = $status_map[$s['status']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <span class="px-3 py-1 <?php echo $status_class; ?> rounded-lg text-xs font-bold"><?php echo htmlspecialchars($s['status']); ?></span>
                            </td>
                            <td class="px-6 py-6 text-sm font-medium text-slate-500"><?php echo date('Y/m/d H:i', strtotime($s['created_at'])); ?></td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end space-x-2">
                                    <button onclick="openSuggestionModal(<?php echo htmlspecialchars(json_encode($s)); ?>)" class="w-10 h-10 bg-white border border-slate-200 text-slate-400 hover:text-blue-500 hover:border-blue-500 rounded-xl transition-all shadow-sm"><i class="fas fa-eye"></i></button>
                                    <a href="?tab=wenjuan&action=delete&id=<?php echo $s['id']; ?>" onclick="return confirm('确定要删除这条建议吗？')" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-500 rounded-xl transition-all shadow-sm"><i class="fas fa-trash-alt"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- 分页 -->
        <?php if($total_pages > 1): ?>
        <div class="px-10 py-6 bg-slate-50/30 border-t border-slate-100 flex justify-between items-center">...</div>
        <?php endif; ?>
    </div>
</div>
