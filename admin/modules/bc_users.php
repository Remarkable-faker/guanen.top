<?php
/**
 * 漂流实名用户管理模块 - 大厂精装版
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 数据准备逻辑 ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询
$where = " WHERE 1=1 ";
if (!empty($search)) {
    $where .= " AND (nickname LIKE '%$search%' OR real_name LIKE '%$search%' OR phone LIKE '%$search%' OR id_card LIKE '%$search%') ";
}

// 获取统计数据
$total_records = $conn->query("SELECT COUNT(*) FROM bc_users $where")->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// 实名统计看板数据
$total_bc = $conn->query("SELECT COUNT(*) FROM bc_users")->fetch_row()[0];
$verified_count = $conn->query("SELECT COUNT(*) FROM bc_users WHERE real_name != ''")->fetch_row()[0];
$today_new_bc = $conn->query("SELECT COUNT(*) FROM bc_users WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];
?>

<div class="animate-fade-in">
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-id-card"></i>
            </div>
            <div>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">漂流总人数</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($total_bc); ?></p>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-user-check"></i>
            </div>
            <div>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">已实名认证</p>
                <p class="text-2xl font-black text-slate-800"><?php echo number_format($verified_count); ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">今日活跃新增</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $today_new_bc; ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-slate-50 text-slate-500 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-percent"></i>
            </div>
            <div>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">实名转换率</p>
                <p class="text-2xl font-black text-slate-800"><?php echo $total_bc > 0 ? round(($verified_count/$total_bc)*100, 1) : 0; ?>%</p>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <form class="relative w-full md:w-96" method="GET">
            <input type="hidden" name="tab" value="bc_users">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索昵称、姓名、身份证、手机..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all shadow-sm">
        </form>
        <div class="flex space-x-3 w-full md:w-auto">
            <button onclick="window.location.reload()" class="flex-1 md:flex-none px-6 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-2xl font-bold hover:bg-slate-50 transition-all flex items-center justify-center">
                <i class="fas fa-sync-alt mr-2"></i> 刷新
            </button>
            <button onclick="showToast('导出功能开发中', 'info')" class="flex-1 md:flex-none px-6 py-3.5 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center justify-center">
                <i class="fas fa-download mr-2"></i> 导出名单
            </button>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[11px] font-black uppercase tracking-[0.2em] border-b border-slate-100">
                        <th class="px-8 py-6 uppercase">User Profile / 用户画像</th>
                        <th class="px-6 py-6">ID Status / 身份信息</th>
                        <th class="px-6 py-6">Contact / 联络信息</th>
                        <th class="px-6 py-6">Address / 收货信息</th>
                        <th class="px-8 py-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM bc_users $where ORDER BY id DESC LIMIT ? OFFSET ?");
                    $stmt->bind_param("ii", $limit, $offset);
                    $stmt->execute();
                    $bcu_result = $stmt->get_result();

                    if ($bcu_result->num_rows > 0):
                        while($bcu = $bcu_result->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-blue-50/20 transition-all group">
                        <td class="px-8 py-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 font-black text-lg border-2 border-white shadow-sm group-hover:scale-110 transition-transform">
                                    <?php echo mb_substr($bcu['nickname'] ?: '?', 0, 1); ?>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800"><?php echo htmlspecialchars($bcu['nickname'] ?: '匿名参与者'); ?></p>
                                    <p class="text-[10px] text-slate-400 font-black tracking-widest mt-0.5 uppercase">Registered User</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6">
                            <?php if(!empty($bcu['real_name'])): ?>
                                <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($bcu['real_name']); ?></p>
                                <p class="text-xs text-slate-400 mt-1 font-mono tracking-tighter"><?php echo substr_replace($bcu['id_card'], '****', 6, 8); ?></p>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-slate-100 text-slate-400 rounded-md text-[10px] font-bold italic">未实名</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-6 text-sm">
                            <div class="flex flex-col space-y-1">
                                <span class="text-slate-700 font-medium"><i class="fas fa-phone-alt mr-2 text-slate-300 w-4"></i><?php echo $bcu['phone'] ?: '---'; ?></span>
                                <span class="text-slate-400 text-xs"><i class="fas fa-envelope mr-2 text-slate-300 w-4"></i><?php echo $bcu['email'] ?: '---'; ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="max-w-[200px] text-xs text-slate-500 leading-relaxed line-clamp-2" title="<?php echo htmlspecialchars($bcu['address']); ?>">
                                <?php echo htmlspecialchars($bcu['address'] ?: '未填写地址'); ?>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end space-x-2">
                                <button onclick="showToast('正在调阅 ID#<?php echo $bcu['id'];?>', 'info')" class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-600 rounded-xl transition-all shadow-sm">
                                    <i class="fas fa-fingerprint text-sm"></i>
                                </button>
                                <a href="?delete_bc_user=<?php echo $bcu['id']; ?>&tab=bc_users" 
                                   onclick="return confirm('警告：确定要彻底删除该实名记录吗？')"
                                   class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-600 rounded-xl transition-all shadow-sm">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="px-8 py-24 text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-50 rounded-[2rem] text-slate-200 text-3xl mb-4">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <p class="text-slate-400 font-bold italic">No matching verified members found.</p>
                        </td>
                    </tr>
                    <?php endif; $stmt->close(); ?>
                </tbody>
            </table>
        </div>

        <div class="px-10 py-8 bg-slate-50/30 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="text-xs font-black text-slate-400 uppercase tracking-widest">
                Showing <?php echo $bcu_result->num_rows; ?> of <?php echo $total_records; ?> Verified Members
            </p>
            <div class="flex items-center space-x-2">
                <a href="?tab=bc_users&page=<?php echo max(1, $page-1); ?>&search=<?php echo urlencode($search); ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>
                
                <?php for($i=1; $i<=$total_pages; $i++): if($i==1 || $i==$total_pages || ($i >= $page-1 && $i <= $page+1)): ?>
                    <a href="?tab=bc_users&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-all <?php echo $i==$page ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; endfor; ?>

                <a href="?tab=bc_users&page=<?php echo min($total_pages, $page+1); ?>&search=<?php echo urlencode($search); ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</div>