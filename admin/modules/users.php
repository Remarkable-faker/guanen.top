<?php
/**
 * 用户管理模块 - 交互增强版 (含资产管理)
 * 功能：合并查看编辑、全字段管理、资产修改、可输入翻页
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; 

// 搜索逻辑
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (username LIKE '%$search_safe%' OR email LIKE '%$search_safe%' OR phone LIKE '%$search_safe%')";
}

// 获取统计数据
$total_res = $conn->query("SELECT COUNT(*) as count FROM users $where");
$total_count = $total_res->fetch_assoc()['count'];
$total_pages = max(1, ceil($total_count / $limit));

// 获取用户列表
$query = "SELECT * FROM users $where ORDER BY id $sort_order LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="animate-fade-in pb-10">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <form class="relative w-full md:w-96" method="GET" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="users">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索昵称、邮件、手机..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all shadow-sm">
        </form>
        
        <div class="flex items-center space-x-3">
            <div class="flex items-center bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <a href="admin_dashboard.php?tab=users&sort=asc&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $sort_order === 'ASC' ? 'bg-indigo-50 text-indigo-600 border-r border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fas fa-sort-amount-up-alt text-xs"></i>
                    <span class="text-xs font-black">正向</span>
                </a>
                <a href="admin_dashboard.php?tab=users&sort=desc&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $sort_order === 'DESC' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fas fa-sort-amount-down-alt text-xs"></i>
                    <span class="text-xs font-black">反向</span>
                </a>
            </div>
            
            <button onclick="openUserModal('add')" class="px-8 py-3.5 bg-slate-900 text-white rounded-2xl font-black hover:bg-black transition-all active:scale-95 flex items-center justify-center space-x-2 shadow-md">
                <i class="fas fa-plus"></i>
                <span>手动录入成员</span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-8 py-6">成员详情</th>
                        <th class="px-6 py-6">资产与名分</th>
                        <th class="px-6 py-6">联络信息</th>
                        <th class="px-6 py-6 text-center">状态</th>
                        <th class="px-8 py-6 text-right">操作管理</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-8 py-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-11 h-11 rounded-2xl <?php echo $row['is_admin'] ? 'bg-amber-100 text-amber-600 border-amber-200' : 'bg-indigo-50 text-indigo-600 border-indigo-100'; ?> flex items-center justify-center font-black border relative">
                                    <?php echo mb_substr($row['username'], 0, 1, 'utf-8'); ?>
                                    <?php if($row['is_admin']): ?>
                                        <i class="fas fa-crown absolute -top-1 -right-1 text-[10px] text-amber-500"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <div class="font-bold text-slate-800 tracking-tight"><?php echo htmlspecialchars($row['username']); ?></div>
                                        <i class="fas <?php echo $row['gender'] == '男' ? 'fa-mars text-blue-500' : ($row['gender'] == '女' ? 'fa-venus text-pink-500' : 'fa-genderless text-slate-300'); ?> text-xs" title="<?php echo htmlspecialchars($row['gender'] ?: '保密'); ?>"></i>
                                    </div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">ID: #<?php echo $row['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-6">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center space-x-1.5 font-black text-orange-600">
                                    <i class="fas fa-coins text-xs opacity-80"></i>
                                    <span class="text-sm"><?php echo number_format($row['qiuqiao_balance'] ?? 0); ?> ✵</span>
                                </div>
                                <?php if (!empty($row['role_label'])): ?>
                                    <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 text-[9px] font-black uppercase tracking-widest rounded-full shadow-sm w-max">
                                        <?php echo htmlspecialchars($row['role_label']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-300 font-bold">无特定名分</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="px-6 py-6">
                            <div class="text-sm font-bold text-slate-600 flex items-center gap-2">
                                <i class="fas fa-envelope text-slate-300 text-xs"></i>
                                <span><?php echo htmlspecialchars($row['email']); ?></span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-2">
                                <i class="fas fa-mobile-alt text-slate-300 text-xs"></i>
                                <span><?php echo htmlspecialchars($row['phone'] ?: '未绑定手机'); ?></span>
                            </div>
                        </td>

                        <td class="px-6 py-6 text-center">
                            <span class="px-3 py-1 <?php echo $row['status'] == 1 ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200'; ?> rounded-lg text-[10px] font-black uppercase border shadow-sm">
                                <?php echo $row['status'] == 1 ? '活跃正常' : '封禁受限'; ?>
                            </span>
                        </td>

                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end space-x-2">
                                <button onclick='openUserModal("edit", <?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' 
                                        class="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-600 hover:shadow-md rounded-xl transition-all shadow-sm text-xs font-bold">
                                    <i class="fas fa-edit mr-1.5"></i> 查看/修改
                                </button>
                                <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>')" 
                                        class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-500 hover:shadow-md rounded-xl transition-all shadow-sm">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="py-20 text-center text-slate-300 font-bold italic">茫茫人海，未寻得相关成员记录...</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                总计 <?php echo $total_count; ?> 位成员 | 第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页
            </div>
            
            <div class="flex items-center space-x-3">
                <a href="admin_dashboard.php?tab=users&page=<?php echo max(1, $page-1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_order === 'DESC' ? 'desc' : 'asc'; ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-indigo-600 transition-all shadow-sm <?php echo $page <= 1 ? 'opacity-30 pointer-events-none' : ''; ?>">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>

                <div class="flex items-center space-x-2 bg-white border border-slate-200 rounded-xl px-2 shadow-sm focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                    <span class="text-[10px] font-bold text-slate-400 uppercase pl-1">跳至</span>
                    <input type="number" id="jumpPageInput" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>"
                           class="w-10 py-2 text-center text-xs font-black text-indigo-600 focus:outline-none bg-transparent">
                    <button onclick="jumpToPage()" class="text-slate-400 hover:text-indigo-600 p-2 transition-colors">
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                </div>

                <a href="admin_dashboard.php?tab=users&page=<?php echo min($total_pages, $page+1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_order === 'DESC' ? 'desc' : 'asc'; ?>" 
                   class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-indigo-600 transition-all shadow-sm <?php echo $page >= $total_pages ? 'opacity-30 pointer-events-none' : ''; ?>">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div id="userModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity" onclick="closeUserModal()"></div>
    
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl max-h-[90vh] relative z-10 flex flex-col overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="modalUI">
        
        <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
            <div>
                <h3 id="modalTitle" class="text-xl font-black text-slate-800">修订成员档案</h3>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">全字段数据同步与安全维护</p>
            </div>
            <button onclick="closeUserModal()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white text-slate-400 hover:bg-rose-500 hover:text-white shadow-sm transition-all"><i class="fas fa-times"></i></button>
        </div>
        
        <form action="admin_dashboard.php?tab=users" method="POST" class="overflow-y-auto p-8 space-y-8 flex-grow custom-scrollbar">
            <input type="hidden" name="action" id="actionType" value="update">
            <input type="hidden" name="id" id="field_id">
            
            <div class="space-y-4">
                <div class="flex items-center text-amber-500 space-x-2 mb-2">
                    <i class="fas fa-shield-alt text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">核心资产与安全</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-slate-50/80 p-6 rounded-3xl border border-slate-100">
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">秋葵余额 (QiuQiao)</label>
                        <div class="relative">
                            <i class="fas fa-coins absolute left-4 top-1/2 -translate-y-1/2 text-orange-400"></i>
                            <input type="number" name="qiuqiao_balance" id="field_qiuqiao_balance" required class="w-full pl-10 pr-4 py-3 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-orange-500/20 font-black text-orange-600 outline-none transition-all shadow-sm">
                        </div>
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">修改密码 (留空则不修改)</label>
                        <input type="password" name="new_password" placeholder="如需重置密码请在此输入" class="w-full px-5 py-3 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none shadow-sm">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">账号状态</label>
                        <select name="status" id="field_status" class="w-full px-5 py-3 bg-white border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none shadow-sm appearance-none">
                            <option value="1">🟢 正常活跃</option>
                            <option value="0">🔴 限制登录 (封禁)</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">专属身份头衔</label>
                        <input type="text" name="role_label" id="field_role_label" placeholder="如：书屋主理人" class="w-full px-5 py-3 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none shadow-sm">
                    </div>
                    <div class="flex items-center space-x-4 px-2 mt-4 md:mt-0">
                        <input type="checkbox" name="is_admin" id="field_is_admin" value="1" class="w-6 h-6 rounded-lg text-indigo-600 focus:ring-indigo-500 border-slate-300 shadow-sm cursor-pointer">
                        <label for="field_is_admin" class="text-xs font-black text-rose-500 uppercase tracking-widest cursor-pointer select-none">授予超级管理员权限</label>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center text-indigo-500 space-x-2 mb-2">
                    <i class="fas fa-user-edit text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">基础档案信息</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">账号昵称</label>
                        <input type="text" name="username" id="field_username" required class="w-full px-5 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">邮箱地址</label>
                        <input type="email" name="email" id="field_email" required class="w-full px-5 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">联系手机</label>
                        <input type="text" name="phone" id="field_phone" class="w-full px-5 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none transition-all">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">性别</label>
                            <select name="gender" id="field_gender" class="w-full px-5 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none transition-all appearance-none">
                                <option value="保密">保密</option>
                                <option value="男">男</option>
                                <option value="女">女</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">降生辰光</label>
                            <input type="date" name="birthdate" id="field_birthdate" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-bold text-slate-700 outline-none transition-all">
                        </div>
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">搜奇引逸 (爱好)</label>
                        <input type="text" name="hobbies" id="field_hobbies" class="w-full px-5 py-3 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-medium text-slate-600 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">个人志向 (格言)</label>
                        <textarea name="motto" id="field_motto" rows="2" class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 font-medium text-slate-600 resize-none outline-none transition-all"></textarea>
                    </div>
                </div>
            </div>

            <div class="pt-4 sticky bottom-0 bg-white">
                <button type="submit" name="update_user_v3" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 transition-all shadow-xl hover:shadow-indigo-200 uppercase tracking-[0.2em] text-sm">
                    确认并保存所有档案数据
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<script>
// --- 分页跳转逻辑 ---
function jumpToPage() {
    const page = document.getElementById('jumpPageInput').value;
    const maxPage = <?php echo $total_pages; ?>;
    const search = "<?php echo urlencode($search); ?>";
    const sort = "<?php echo $sort_order === 'DESC' ? 'desc' : 'asc'; ?>";
    if(page >= 1 && page <= maxPage) {
        window.location.href = `admin_dashboard.php?tab=users&page=${page}&search=${search}&sort=${sort}`;
    } else {
        Swal.fire('页码超出范围', `请输入 1 到 ${maxPage} 之间的数字`, 'info');
    }
}

// --- 弹窗交互逻辑 ---
const modal = document.getElementById('userModal');
const modalUI = document.getElementById('modalUI');

function openUserModal(mode, data = null) {
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalUI.classList.remove('scale-95', 'opacity-0');
        modalUI.classList.add('scale-100', 'opacity-100');
    }, 10);

    if(mode === 'edit' && data) {
        document.getElementById('modalTitle').innerText = '修订成员档案';
        document.getElementById('actionType').value = 'update';
        // 字段自动填充
        document.getElementById('field_id').value = data.id;
        document.getElementById('field_username').value = data.username;
        document.getElementById('field_email').value = data.email;
        document.getElementById('field_phone').value = data.phone || '';
        document.getElementById('field_gender').value = data.gender || '保密';
        document.getElementById('field_birthdate').value = data.birthdate || '';
        document.getElementById('field_role_label').value = data.role_label || '';
        document.getElementById('field_hobbies').value = data.hobbies || '';
        document.getElementById('field_motto').value = data.motto || '';
        document.getElementById('field_status').value = data.status;
        document.getElementById('field_is_admin').checked = parseInt(data.is_admin) === 1;
        document.getElementById('field_qiuqiao_balance').value = data.qiuqiao_balance || 0; // 填入秋葵余额
        document.getElementsByName('new_password')[0].value = '';
    } else {
        document.getElementById('modalTitle').innerText = '手动录入新成员';
        document.getElementById('actionType').value = 'add';
        const inputs = modal.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(i => {
            if(i.type === 'checkbox') i.checked = false;
            else i.value = '';
        });
        document.getElementById('field_gender').value = '保密';
        document.getElementById('field_status').value = '1';
        document.getElementById('field_qiuqiao_balance').value = 0; // 默认0秋葵
    }
}

function closeUserModal() {
    modalUI.classList.remove('scale-100', 'opacity-100');
    modalUI.classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function confirmDelete(id, name) {
    Swal.fire({
        title: '高危操作确认',
        text: `确定要彻底删除 [${name}] 的所有档案及相关数据吗？此操作无法撤销。`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#cbd5e1',
        confirmButtonText: '确认销毁',
        cancelButtonText: '暂不操作',
        customClass: { popup: 'rounded-[2rem]' }
    }).then((r) => { if(r.isConfirmed) window.location.href=`admin_dashboard.php?tab=users&delete_id=${id}`; });
}
</script>