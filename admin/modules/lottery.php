<?php
/**
 * 抽奖管理模块 - 视觉&交互增强版 (全量显示收货信息版)
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 获取基础数据 ---
$stmt_cfg = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'lottery_config'");
$stmt_cfg->execute();
$result_cfg = $stmt_cfg->get_result();
$lottery_cfg_json = $result_cfg->fetch_assoc()['setting_value'] ?? '{}';
$lottery_cfg = json_decode($lottery_cfg_json, true);

$default_cfg = ['max_daily_draws' => 3, 'book_probability' => 0.01, 'max_total_wins' => 10, 'reward_pool_enabled' => false, 'last_updated' => 'N/A', 'updated_by' => 'N/A', 'prizes' => []];
$lottery_cfg = array_merge($default_cfg, $lottery_cfg);

// ==================== 核心：让预览页的概率计算与后端100%保持一致 ====================
if (isset($lottery_cfg['book_probability'])) {
    $raw_prob = (float)$lottery_cfg['book_probability'];
    $win_prob = ($raw_prob <= 1) ? ($raw_prob * 100) : $raw_prob;
    
    $lose_count = 0;
    foreach ($lottery_cfg['prizes'] as $p) {
        if (empty($p['is_win']) && empty($p['isWin'])) $lose_count++;
    }
    
    $lose_prob = $lose_count > 0 ? (100 - $win_prob) / $lose_count : 0;
    
    foreach ($lottery_cfg['prizes'] as &$p) {
        $is_win = !empty($p['is_win']) || !empty($p['isWin']);
        $p['probability'] = round($is_win ? $win_prob : $lose_prob, 2); 
    }
    unset($p);
}
// ====================================================================================

$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

$stmt_draws = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE draw_time >= ? AND draw_time < ?");
$stmt_draws->bind_param('ss', $today_start, $today_end);
$stmt_draws->execute();
$draws_count = $stmt_draws->get_result()->fetch_row()[0] ?? 0;

$stmt_wins = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE is_win = 1 AND draw_time >= ? AND draw_time < ?");
$stmt_wins->bind_param('ss', $today_start, $today_end);
$stmt_wins->execute();
$wins_count = $stmt_wins->get_result()->fetch_row()[0] ?? 0;

$stmt_pending = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE is_win = 1 AND is_delivered = 0");
$stmt_pending->execute();
$pending_count = $stmt_pending->get_result()->fetch_row()[0] ?? 0;

// 分页逻辑
$rec_page = isset($_GET['rec_page']) ? max(1, (int)$_GET['rec_page']) : 1;
$rec_limit = 10;
$rec_offset = ($rec_page - 1) * $rec_limit;
$total_records_res = $conn->query("SELECT COUNT(*) FROM lottery_records");
$total_records = $total_records_res->fetch_row()[0] ?? 0;
$total_rec_pages = max(1, ceil($total_records / $rec_limit));
$records_res = $conn->query("SELECT lr.*, u.username FROM lottery_records lr LEFT JOIN users u ON lr.user_id = u.id ORDER BY lr.draw_time DESC LIMIT $rec_limit OFFSET $rec_offset");

function getRelativeDateLabel($datetimeStr) {
    $draw_date = date('Y-m-d', strtotime($datetimeStr));
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $before_yesterday = date('Y-m-d', strtotime('-2 days'));

    if ($draw_date === $today) {
        return '<span class="text-indigo-500 font-bold mr-1.5 border border-indigo-200 bg-indigo-50 px-1.5 py-0.5 rounded">今天</span>';
    } elseif ($draw_date === $yesterday) {
        return '<span class="text-amber-500 font-bold mr-1.5 border border-amber-200 bg-amber-50 px-1.5 py-0.5 rounded">昨天</span>';
    } elseif ($draw_date === $before_yesterday) {
        return '<span class="text-slate-500 font-bold mr-1.5 border border-slate-200 bg-slate-50 px-1.5 py-0.5 rounded">前天</span>';
    }
    return '';
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="space-y-8 animate-fade-in">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-5">
            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl">
                <i class="fas fa-dice"></i>
            </div>
            <div>
                <div class="text-slate-400 text-xs font-black uppercase tracking-widest">今日抽奖总计</div>
                <div class="text-3xl font-black text-slate-800 mt-0.5"><?php echo $draws_count; ?> <span class="text-sm text-slate-300 font-medium">Times</span></div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-5">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl">
                <i class="fas fa-trophy"></i>
            </div>
            <div>
                <div class="text-emerald-500 text-xs font-black uppercase tracking-widest">今日产生幸运儿</div>
                <div class="text-3xl font-black text-slate-800 mt-0.5"><?php echo $wins_count; ?> <span class="text-sm text-slate-300 font-medium">Winners</span></div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-rose-500 to-rose-600 p-6 rounded-[2rem] shadow-lg shadow-rose-100 flex items-center space-x-5 text-white">
            <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-xl">
                <i class="fas fa-truck-loading animate-pulse"></i>
            </div>
            <div>
                <div class="text-rose-100 text-xs font-black uppercase tracking-widest">待处理发货单</div>
                <div class="text-3xl font-black mt-0.5"><?php echo $pending_count; ?> <span class="text-sm text-rose-200 font-medium">Pending</span></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex bg-slate-50/50 p-2 border-b border-slate-100">
            <button onclick="switchTab('config')" id="btn-config" class="tab-btn flex-1 py-4 text-sm font-black rounded-2xl transition-all">
                <i class="fas fa-cog mr-2"></i>策略配置
            </button>
            <button onclick="switchTab('prizes')" id="btn-prizes" class="tab-btn flex-1 py-4 text-sm font-black rounded-2xl transition-all">
                <i class="fas fa-gift mr-2"></i>精准奖项预览
            </button>
            <button onclick="switchTab('records')" id="btn-records" class="tab-btn flex-1 py-4 text-sm font-black rounded-2xl transition-all">
                <i class="fas fa-history mr-2"></i>实时记录
            </button>
        </div>

        <div id="tab-config" class="p-10">
            <form id="lotteryConfigForm" class="max-w-3xl space-y-8">
                <input type="hidden" name="action" value="save_lottery_config">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">单人日抽奖限额</label>
                        <div class="relative">
                            <i class="fas fa-user-clock absolute left-5 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            <input type="number" name="max_daily_draws" value="<?php echo $lottery_cfg['max_daily_draws']; ?>" 
                                   class="w-full bg-slate-50 border-2 border-transparent focus:border-indigo-500/20 focus:bg-white rounded-2xl pl-12 pr-4 py-4 font-bold text-slate-700 transition-all outline-none">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">全局核心中奖概率 (0-1)</label>
                        <div class="relative">
                            <i class="fas fa-percentage absolute left-5 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            <input type="text" name="book_probability" value="<?php echo $lottery_cfg['book_probability']; ?>" 
                                   class="w-full bg-slate-50 border-2 border-transparent focus:border-indigo-500/20 focus:bg-white rounded-2xl pl-12 pr-4 py-4 font-bold text-slate-700 transition-all outline-none">
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">奖池最大溢出数 (熔断阀值)</label>
                    <input type="number" name="max_total_wins" value="<?php echo $lottery_cfg['max_total_wins']; ?>" 
                           class="w-full bg-slate-50 border-2 border-transparent focus:border-indigo-500/20 focus:bg-white rounded-2xl px-6 py-4 font-bold text-slate-700 transition-all outline-none">
                </div>

                <div class="flex items-center p-6 bg-slate-50 rounded-[1.5rem] border-2 border-dashed border-slate-200 hover:border-indigo-200 transition-all cursor-pointer group">
                    <div class="relative flex items-center justify-center">
                        <input type="checkbox" name="reward_pool_enabled" id="rpe" <?php echo ($lottery_cfg['reward_pool_enabled'] ?? false) ? 'checked' : ''; ?> 
                               class="w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    </div>
                    <div class="ml-4 flex-1">
                        <label for="rpe" class="block text-sm font-black text-slate-700 cursor-pointer">自动熔断保护机制</label>
                        <p class="text-xs text-slate-400 mt-0.5">当今日发货量达到阀值，系统将自动下架所有实物奖项，仅保留文化项</p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-slate-50">
                    <div class="text-[10px] text-slate-400 font-bold uppercase tracking-widest" id="last-updated-text">
                        <i class="fas fa-shield-alt mr-1"></i> 最后加固: <?php echo $lottery_cfg['last_updated']; ?>
                    </div>
                    <button type="submit" class="bg-slate-900 text-white px-10 py-4 rounded-2xl font-black text-sm hover:bg-indigo-600 hover:shadow-xl hover:shadow-indigo-100 transition-all active:scale-95">
                        同步至核心数据库
                    </button>
                </div>
            </form>
        </div>

        <div id="tab-prizes" class="p-10 hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($lottery_cfg['prizes'] as $p): ?>
                    <div class="group p-5 bg-white border border-slate-100 rounded-2xl hover:shadow-md transition-all relative overflow-hidden">
                        <?php if($p['is_win']): ?>
                            <div class="absolute -right-8 -top-8 w-16 h-16 bg-rose-500 rotate-45 flex items-end justify-center pb-1">
                                <i class="fas fa-star text-[10px] text-white"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-tighter <?php echo $p['is_win'] ? 'text-rose-500' : 'text-slate-400'; ?>">
                                    <?php echo $p['is_win'] ? '中奖项 (Win)' : '文案激励 (Bonus)'; ?>
                                </span>
                                <h4 class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($p['name']); ?></h4>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-mono font-black text-indigo-600"><?php echo $p['probability']; ?>%</div>
                                <div class="text-[9px] text-slate-300 uppercase font-bold">精准结算概率</div>
                            </div>
                        </div>
                        
                        <div class="w-full h-1.5 bg-slate-50 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $p['is_win'] ? 'bg-rose-400' : 'bg-indigo-400'; ?> transition-all duration-1000" style="width: <?php echo $p['probability']; ?>%"></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-3 font-medium italic">“<?php echo htmlspecialchars($p['value']); ?>”</p>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-400 mt-6 text-center"><i class="fas fa-info-circle"></i> 以上概率已根据你在“策略配置”中填写的全局中奖率自动重算，精确无误。</p>
        </div>

        <div id="tab-records" class="p-0 hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/80 text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-4">抽奖参与者</th>
                            <th class="px-4 py-4">最终结果</th>
                            <th class="px-4 py-4 text-center">系统判定</th>
                            <th class="px-4 py-4">配送/收货信息</th>
                            <th class="px-6 py-4 text-right">发生时间</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php while($row = $records_res->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3 font-bold text-slate-700">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-xs text-indigo-500 font-black shrink-0">
                                        <?php echo mb_substr($row['username'] ?? '游', 0, 1); ?>
                                    </div>
                                    <span class="text-base truncate"><?php echo htmlspecialchars($row['username'] ?? '游客用户'); ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-base font-bold text-slate-600"><?php echo htmlspecialchars($row['prize_name']); ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php if($row['is_win']): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-md bg-emerald-50 text-emerald-600 text-xs font-black border border-emerald-100">
                                        <i class="fas fa-check-circle mr-1"></i> 中奖
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-300 font-bold text-sm">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="px-4 py-3">
                                <?php if(!empty($row['receiver_name'])): ?>
                                    <div class="flex flex-col text-sm">
                                        <div class="font-bold text-slate-700 flex items-center gap-2">
                                            <i class="fas fa-user text-slate-400 text-xs"></i> 
                                            <?php echo htmlspecialchars($row['receiver_name']); ?>
                                            <span class="text-xs text-slate-500 font-normal ml-1"><?php echo htmlspecialchars($row['receiver_phone']); ?></span>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1 flex items-start gap-2 max-w-[220px]" title="<?php echo htmlspecialchars($row['receiver_address']); ?>">
                                            <i class="fas fa-map-marker-alt text-slate-300 mt-0.5"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($row['receiver_address']); ?></span>
                                        </div>
                                    </div>
                                <?php elseif($row['is_win']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-amber-50 text-amber-500 text-xs font-bold border border-amber-100">
                                        <i class="fas fa-exclamation-circle mr-1"></i> 待用户填写
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-3 text-right text-sm text-slate-500 whitespace-nowrap">
                                <?php echo getRelativeDateLabel($row['draw_time']); ?>
                                <span class="font-mono"><?php echo date('Y-m-d H:i', strtotime($row['draw_time'])); ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-6 flex flex-col md:flex-row justify-between items-center gap-6 bg-slate-50/30">
                <div class="text-xs font-black text-slate-400 uppercase tracking-widest">
                    Showing <span class="text-slate-900"><?php echo $total_records > 0 ? ($rec_limit * ($rec_page - 1) + 1) : 0; ?> - <?php echo min($total_records, $rec_limit * $rec_page); ?></span> of <?php echo $total_records; ?>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="?tab=lottery&view=records&rec_page=<?php echo max(1, $rec_page - 1); ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-indigo-600 shadow-sm transition-all <?php echo $rec_page <= 1 ? 'opacity-30 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>
                    <div class="flex items-center space-x-2 bg-white border border-slate-200 rounded-xl px-3 py-2 shadow-sm">
                        <input type="number" id="recJumpPageInput" min="1" max="<?php echo $total_rec_pages; ?>" value="<?php echo $rec_page; ?>" 
                               class="w-10 text-center text-sm font-black text-indigo-600 focus:outline-none bg-transparent border-none p-0">
                        <span class="text-slate-200">/</span>
                        <span class="text-sm font-bold text-slate-400"><?php echo $total_rec_pages; ?></span>
                        <button onclick="jumpToRecPage()" class="ml-1 text-indigo-500 hover:text-indigo-700 transition-colors">
                            <i class="fas fa-arrow-right text-sm"></i>
                        </button>
                    </div>
                    <a href="?tab=lottery&view=records&rec_page=<?php echo min($total_rec_pages, $rec_page + 1); ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-indigo-600 shadow-sm transition-all <?php echo $rec_page >= $total_rec_pages ? 'opacity-30 pointer-events-none' : ''; ?>">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                </div>
            </div>

            <div class="px-6 pb-6 text-right">
                <button onclick="confirmClear()" class="group text-xs font-black uppercase text-slate-300 hover:text-rose-500 transition-colors">
                    <i class="fas fa-trash-alt mr-1 group-hover:shake"></i> 清空所有历史抽奖快照
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.tab-btn.active {
    background-color: white;
    color: #4f46e5;
    box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
}
.tab-btn:not(.active) {
    color: #94a3b8;
}
.tab-btn:not(.active):hover {
    color: #64748b;
    background-color: rgba(255,255,255,0.5);
}

@keyframes shake {
    0%, 100% { transform: rotate(0); }
    25% { transform: rotate(10deg); }
    75% { transform: rotate(-10deg); }
}
.group:hover .group-hover\:shake { animation: shake 0.2s infinite; }
</style>

<script>
function switchTab(tab) {
    const url = new URL(window.location);
    url.searchParams.set('view', tab);
    window.history.pushState({}, '', url);

    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#tab-config, #tab-prizes, #tab-records').forEach(el => el.classList.add('hidden'));
    
    document.getElementById('tab-' + tab).classList.remove('hidden');
    document.getElementById('btn-' + tab).classList.add('active');
}

function confirmClear() {
    Swal.fire({
        title: '确定要清空吗？',
        text: "所有抽奖记录将被永久销毁，无法撤销！",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#cbd5e1',
        confirmButtonText: '是的，彻底清空',
        cancelButtonText: '取消操作',
        customClass: { popup: 'rounded-[2rem]' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?tab=lottery&action=clear_records';
        }
    });
}

function jumpToRecPage() {
    const page = document.getElementById('recJumpPageInput').value;
    window.location.href = `?tab=lottery&view=records&rec_page=${page}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const currentView = params.get('view') || 'config';
    switchTab(currentView);

    const configForm = document.getElementById('lotteryConfigForm');

    configForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: '正在同步',
            text: '正在写入数据库并重算概率模型...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        const formData = new FormData(configForm);
        if (!document.getElementById('rpe').checked) formData.delete('reward_pool_enabled'); 

        fetch('/api/admin_lottery_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '配置已同步',
                    text: '页面即将刷新以展示最新的精准概率...',
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-[2rem]' }
                });
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                Swal.fire('保存失败', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('系统错误', '无法连接到 API 接口', 'error');
        });
    });
});
</script>