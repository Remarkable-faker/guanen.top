<?php
/**
 * 书籍漂流模块 - 全能合一精装版
 * 功能：合并查看/编辑、状态管理、分页跳转、全字段覆盖
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 搜索逻辑
$where_clause = " WHERE 1=1";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where_clause .= " AND (title LIKE '%$search_safe%' OR author LIKE '%$search_safe%' OR current_reader LIKE '%$search_safe%')";
}

// 获取统计数据
$total_res = $conn->query("SELECT COUNT(*) FROM bc_books" . $where_clause);
$total_count = $total_res->fetch_row()[0] ?? 0;
$total_pages = max(1, ceil($total_count / $limit));

// 获取书籍列表 (已重写为兼容 MySQL 5.7 的子查询)
$query = "
    SELECT 
        b.*, 
        l.event_desc AS last_event, 
        l.log_date AS last_log_date
    FROM 
        bc_books b
    LEFT JOIN 
        bc_drift_logs l ON l.id = (
            SELECT id 
            FROM bc_drift_logs 
            WHERE book_id = b.book_id 
            ORDER BY log_date DESC 
            LIMIT 1
        )
    $where_clause 
    ORDER BY 
        b.last_update DESC 
    LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

// 顶部统计块数据
$stats = [
    'total' => @$conn->query("SELECT COUNT(*) FROM bc_books")->fetch_row()[0] ?? 0,
    'reading' => @$conn->query("SELECT COUNT(*) FROM bc_books WHERE status = '阅读中'")->fetch_row()[0] ?? 0,
    'shipping' => @$conn->query("SELECT COUNT(*) FROM bc_books WHERE status = '寄送中'")->fetch_row()[0] ?? 0,
];
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="animate-fade-in space-y-8 pb-10">
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-6">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-3xl shadow-inner"><i class="fas fa-book"></i></div>
            <div><p class="text-slate-400 text-xs font-black uppercase tracking-widest">书籍总数</p><h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats['total']; ?></h3></div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-6 text-emerald-600">
            <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center text-3xl shadow-inner"><i class="fas fa-book-reader"></i></div>
            <div><p class="text-slate-400 text-xs font-black uppercase tracking-widest text-emerald-600/60">正在阅读</p><h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats['reading']; ?></h3></div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 flex items-center space-x-6 text-amber-600">
            <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center text-3xl shadow-inner"><i class="fas fa-shipping-fast"></i></div>
            <div><p class="text-slate-400 text-xs font-black uppercase tracking-widest text-amber-600/60">漂流途中</p><h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $stats['shipping']; ?></h3></div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
        <form class="relative w-full md:w-96" method="GET">
            <input type="hidden" name="tab" value="book_crossing">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索书名、作者、读者..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 outline-none transition-all shadow-sm">
        </form>
        <button onclick="openBookModal('add')" class="w-full md:w-auto px-8 py-3.5 bg-slate-900 text-white rounded-2xl font-black hover:bg-black transition-all active:scale-95 flex items-center justify-center space-x-2 shadow-lg">
            <i class="fas fa-plus"></i><span>登记漂流新书</span>
        </button>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-8 py-6">书籍信息</th>
                        <th class="px-6 py-6 text-center">当前读者</th>
                        <th class="px-6 py-6 text-center">状态</th>
                        <th class="px-6 py-6">最新动态</th>
                        <th class="px-6 py-6 text-center">动态日期</th>
                        <th class="px-8 py-6 text-right">操作管理</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if ($result && $result->num_rows > 0): while($book = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-8 py-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-14 bg-slate-100 rounded-lg flex items-center justify-center text-slate-300 border border-slate-200 overflow-hidden shadow-sm">
                                    <i class="fas fa-book text-xl"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($book['title']); ?></p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mt-1 tracking-wider"><?php echo htmlspecialchars($book['author']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <span class="px-3 py-1 bg-slate-100 rounded-lg text-xs font-bold text-slate-600 border border-slate-200"><?php echo htmlspecialchars($book['current_reader']); ?></span>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <?php 
                                $status_colors = [
                                    '阅读中' => 'bg-emerald-50 text-emerald-600',
                                    '寄送中' => 'bg-amber-50 text-amber-600',
                                    '已送达' => 'bg-blue-50 text-blue-600',
                                    '已读待寄' => 'bg-rose-50 text-rose-600',
                                ];
                                $cls = $status_colors[$book['status']] ?? 'bg-slate-50 text-slate-500';
                            ?>
                            <span class="px-3 py-1 <?php echo $cls; ?> rounded-lg text-[10px] font-black uppercase border border-current opacity-70"><?php echo $book['status']; ?></span>
                        </td>
                        <td class="px-6 py-6 text-sm font-medium text-slate-500"><?php echo htmlspecialchars($book['last_event'] ?? '暂无记录'); ?></td>
                        <td class="px-6 py-6 text-center text-xs text-slate-400 font-mono"><?php echo isset($book['last_log_date']) ? date('Y-m-d', strtotime($book['last_log_date'])) : '--'; ?></td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end space-x-2">
                                <button onclick='openBookModal("edit", <?php echo json_encode($book); ?>)' 
                                        class="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-600 rounded-xl transition-all shadow-sm text-xs font-bold">
                                    <i class="fas fa-edit mr-1.5"></i> 查看/修改
                                </button>
                                <button onclick="openDriftLogModal(<?php echo $book['book_id']; ?>)" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:text-teal-600 hover:border-teal-600 rounded-xl transition-all shadow-sm text-xs font-bold">
                                    <i class="fas fa-route mr-1.5"></i> 查看轨迹
                                </button>
                                <button onclick="confirmDelete(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>')" 
                                        class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-500 rounded-xl transition-all shadow-sm">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="py-20 text-center text-slate-300 font-bold italic">未检索到漂流记录...</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                总计 <?php echo $total_count; ?> 本书 | 第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页
            </div>
            
            <div class="flex items-center space-x-3">
                <a href="admin_dashboard.php?tab=book_crossing&page=<?php echo max(1, $page-1); ?>&search=<?php echo urlencode($search); ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-blue-600 transition-all shadow-sm <?php echo $page <= 1 ? 'opacity-30 pointer-events-none' : ''; ?>">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>

                <div class="flex items-center space-x-2 bg-white border border-slate-200 rounded-xl px-2 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase pl-1">跳转至</span>
                    <input type="number" id="jumpPageInput" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>"
                           class="w-12 py-2 text-center text-xs font-black text-slate-700 focus:outline-none bg-transparent">
                    <button onclick="jumpToPage()" class="text-blue-600 hover:text-blue-800 p-2 transition-colors">
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                </div>

                <a href="admin_dashboard.php?tab=book_crossing&page=<?php echo min($total_pages, $page+1); ?>&search=<?php echo urlencode($search); ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-blue-600 transition-all shadow-sm <?php echo $page >= $total_pages ? 'opacity-30 pointer-events-none' : ''; ?>">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div id="bookModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity" onclick="closeBookModal()"></div>
    
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-3xl max-h-[90vh] relative z-10 flex flex-col overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="modalUI">
        
        <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
            <div>
                <h3 id="modalTitle" class="text-2xl font-black text-slate-800 tracking-tight">修订书籍档案</h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">管理漂流状态及书籍核心信息</p>
            </div>
            <button onclick="closeBookModal()" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white shadow-sm text-slate-400 hover:text-rose-500 transition-all">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <form action="admin_dashboard.php?tab=book_crossing" method="POST" class="overflow-y-auto p-10 space-y-8 flex-grow custom-scrollbar">
            <input type="hidden" name="action" id="actionType" value="update">
            <input type="hidden" name="book_id" id="field_book_id">
            
            <div class="space-y-4">
                <div class="flex items-center text-blue-500 space-x-2">
                    <i class="fas fa-info-circle text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">核心图书信息 (Core Info)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">书名 (Title)</label>
                        <input type="text" name="title" id="field_title" required class="w-full px-5 py-3.5 bg-slate-50 border-none rounded-2xl focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-700">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">作者 (Author)</label>
                        <input type="text" name="author" id="field_author" required class="w-full px-5 py-3.5 bg-slate-50 border-none rounded-2xl focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-700">
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center text-amber-500 space-x-2">
                    <i class="fas fa-map-marker-alt text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">实时状态与读者 (Status)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-amber-50/30 rounded-3xl border border-amber-100/50">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 text-amber-600/70">当前漂流状态</label>
                        <select name="status" id="field_status" class="w-full px-5 py-3.5 bg-white border-none rounded-2xl focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-700 appearance-none shadow-sm">
                            <option value="阅读中">📖 阅读中</option>
                            <option value="寄送中">🚚 寄送中</option>
                            <option value="已送达">✅ 已送达</option>
                            <option value="已读待寄">⌛ 已读待寄</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 text-amber-600/70">当前读者昵称</label>
                        <input type="text" name="current_reader" id="field_current_reader" class="w-full px-5 py-3.5 bg-white border-none rounded-2xl focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-700 shadow-sm">
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center text-slate-500 space-x-2">
                    <i class="fas fa-history text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">漂流心得与历史轨迹</span>
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">详细心得/备注 (Note)</label>
                    <textarea name="note" id="field_note" rows="4" class="w-full px-6 py-5 bg-slate-50 border-none rounded-[1.5rem] focus:ring-4 focus:ring-blue-500/10 font-medium text-slate-600 resize-none" placeholder="读者留下的足迹和感悟..."></textarea>
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" name="save_book" class="w-full py-4.5 bg-slate-900 text-white rounded-2xl font-black hover:bg-black transition-all shadow-xl shadow-slate-100 flex items-center justify-center space-x-3">
                    <i class="fas fa-save"></i>
                    <span class="uppercase tracking-widest">确认并更新书籍数据 (Update Database)</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 漂流轨迹弹窗 -->
<div id="driftLogModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity" onclick="closeDriftLogModal()"></div>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] relative z-10 flex flex-col overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-800">书籍漂流轨迹</h3>
        </div>
        <div id="driftLogContent" class="overflow-y-auto p-8 space-y-4"></div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>

<script>
// --- 分页跳转逻辑 ---
function jumpToPage() {
    const page = document.getElementById('jumpPageInput').value;
    const maxPage = <?php echo $total_pages; ?>;
    const search = "<?php echo urlencode($search); ?>";
    if(page >= 1 && page <= maxPage) {
        window.location.href = `admin_dashboard.php?tab=book_crossing&page=${page}&search=${search}`;
    } else {
        Swal.fire('页码错误', `页码需在 1 到 ${maxPage} 之间`, 'warning');
    }
}

// --- 弹窗逻辑 ---
const modal = document.getElementById('bookModal');
const modalUI = document.getElementById('modalUI');

function openBookModal(mode, data = null) {
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalUI.classList.remove('scale-95', 'opacity-0');
        modalUI.classList.add('scale-100', 'opacity-100');
    }, 10);

    if(mode === 'edit' && data) {
        document.getElementById('modalTitle').innerText = '修订书籍档案';
        document.getElementById('actionType').value = 'update';
        // 映射数据
        document.getElementById('field_book_id').value = data.book_id;
        document.getElementById('field_title').value = data.title;
        document.getElementById('field_author').value = data.author;
        document.getElementById('field_status').value = data.status;
        document.getElementById('field_current_reader').value = data.current_reader;
        document.getElementById('field_note').value = data.note || '';
    } else {
        document.getElementById('modalTitle').innerText = '登记新书入库';
        document.getElementById('actionType').value = 'add';
        const inputs = modal.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(i => i.value = '');
    }
}

function closeBookModal() {
    modalUI.classList.remove('scale-100', 'opacity-100');
    modalUI.classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function confirmDelete(id, title) {
    Swal.fire({
        title: '删除记录',
        text: `确定要永久删除《${title}》的漂流记录吗？`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        customClass: { popup: 'rounded-[2.5rem]' }
    }).then((r) => { if(r.isConfirmed) window.location.href=`admin_dashboard.php?tab=book_crossing&delete_id=${id}`; });
}

// --- 新增：漂流轨迹弹窗逻辑 ---
const driftLogModal = document.getElementById('driftLogModal');
const driftLogContent = document.getElementById('driftLogContent');

async function openDriftLogModal(bookId) {
    driftLogContent.innerHTML = '<p class="text-center text-slate-400">正在加载轨迹...</p>';
    driftLogModal.classList.remove('hidden');

    try {
        const response = await fetch(`admin_dashboard.php?tab=book_crossing&action=get_drift_logs&book_id=${bookId}`);
        const result = await response.json();

        if (result.success && result.data.length > 0) {
            let html = '';
            result.data.forEach(log => {
                html += `
                    <div class="p-4 border rounded-lg">
                        <p><strong>城市:</strong> ${log.city}</p>
                        <p><strong>读者:</strong> ${log.reader}</p>
                        <p><strong>事件:</strong> ${log.event_desc}</p>
                        <p><strong>时间:</strong> ${new Date(log.log_date).toLocaleString()}</p>
                    </div>
                `;
            });
            driftLogContent.innerHTML = html;
        } else {
            driftLogContent.innerHTML = '<p class="text-center text-slate-400">暂无漂流轨迹记录。</p>';
        }
    } catch (error) {
        driftLogContent.innerHTML = '<p class="text-center text-red-500">加载失败，请重试。</p>';
    }
}

function closeDriftLogModal() {
    driftLogModal.classList.add('hidden');
}
</script>