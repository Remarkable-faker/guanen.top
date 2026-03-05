<?php
/**
 * 图书借阅申请管理模块
 * 
 * 管理员可以查看、审核和管理用户的图书借阅和归还申请。
 */

if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// 获取申请状态统计
$status_stats = array(
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0
);

$res = $conn->query("SELECT 
    status,
    COUNT(*) as count
    FROM book_requests 
    GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status = $row['status'];
        if (array_key_exists($status, $status_stats)) {
            $status_stats[$status] = (int)$row['count'];
        }
    }
}

// 构建查询条件
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (book_title LIKE '%$search_safe%' OR username LIKE '%$search_safe%')";
}

if ($status_filter !== 'all' && array_key_exists($status_filter, $status_stats)) {
    $where .= " AND status = '$status_filter'";
}

// 获取申请列表，关联书籍和用户表
$query = "SELECT br.*, sl.title as library_title, sl.id as library_id, u.username as user_name, u.id as user_id
          FROM book_requests br
          LEFT JOIN site_library sl ON br.book_id = sl.id
          LEFT JOIN bc_users u ON br.user_id = u.id
          $where 
          ORDER BY br.created_at DESC 
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

// 获取总记录数
$total_res = $conn->query("SELECT COUNT(*) as count FROM book_requests $where");
$total_count = $total_res->fetch_assoc()['count'] ?? 0;
$total_pages = max(1, ceil($total_count / $limit));
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="animate-fade-in pb-10">
    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">待审核</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $status_stats['pending']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-green-50 flex items-center justify-center text-green-600">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">已通过</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $status_stats['approved']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-red-50 flex items-center justify-center text-red-600">
                    <i class="fas fa-times-circle text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">已拒绝</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $status_stats['rejected']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                    <i class="fas fa-check-double text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">已完成</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $status_stats['completed']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- 搜索和筛选 -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <form class="relative w-full md:w-96" method="GET" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="book_requests">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索书籍名称、申请人..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all shadow-sm">
        </form>
        
        <div class="flex items-center space-x-3">
            <!-- 状态筛选 -->
            <div class="flex items-center bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <a href="admin_dashboard.php?tab=book_requests&status=all&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $status_filter === 'all' ? 'bg-indigo-50 text-indigo-600 border-r border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">全部</span>
                </a>
                <a href="admin_dashboard.php?tab=book_requests&status=pending&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $status_filter === 'pending' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">待审核</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 申请列表 -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-6 py-6">申请详情</th>
                        <th class="px-6 py-6">申请人</th>
                        <th class="px-6 py-6 text-center">申请类型</th>
                        <th class="px-6 py-6">申请时间</th>
                        <th class="px-6 py-6">地址信息</th>
                        <th class="px-6 py-6 text-center">状态</th>
                        <th class="px-6 py-6 text-right">操作管理</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-6 py-6">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-black border border-amber-100">
                                    <?php echo mb_substr($row['library_title'] ?? $row['book_title'] ?? '书', 0, 1); ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800 tracking-tight">
                                        <?php if (!empty($row['library_id'])): ?>
                                        <a href="admin_dashboard.php?tab=library&id=<?php echo $row['library_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 transition-colors">
                                            <?php echo htmlspecialchars($row['library_title'] ?? $row['book_title'] ?? '未知书籍'); ?>
                                        </a>
                                        <?php else: ?>
                                        <?php echo htmlspecialchars($row['book_title'] ?? '未知书籍'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">申请ID: #<?php echo $row['id']; ?></div>
                                    <?php if (!empty($row['book_isbn'])): ?>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase">ISBN: <?php echo htmlspecialchars($row['book_isbn']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="font-bold text-slate-800">
                                <?php if (!empty($row['user_id'])): ?>
                                <a href="admin_dashboard.php?tab=users&id=<?php echo $row['user_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 transition-colors">
                                    <?php echo htmlspecialchars($row['user_name'] ?? $row['username'] ?? '未知用户'); ?>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($row['username'] ?? '未知用户'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-[9px] text-slate-400">用户ID: <?php echo $row['user_id']; ?></div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center <?php echo $row['type'] === 'borrow' ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600'; ?>" style="min-width: 60px;">
                                <?php echo $row['type'] === 'borrow' ? '借阅' : '归还'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-6">
                            <div class="font-bold text-slate-800"><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></div>
                            <div class="text-[9px] text-slate-400">已提交 <?php echo floor((time() - strtotime($row['created_at'])) / 86400); ?> 天</div>
                        </td>
                        <td class="px-6 py-6">
                            <?php if (!empty($row['address'])): ?>
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-map-marker-alt text-red-500 mt-0.5"></i>
                                <div>
                                    <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($row['address']); ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-slate-400 text-sm">无地址信息</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <?php 
                            $status_badge = array(
                                'pending' => '<span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-amber-50 text-amber-600" style="min-width: 80px;">待审核</span>',
                                'approved' => '<span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-green-50 text-green-600" style="min-width: 80px;">已通过</span>',
                                'rejected' => '<span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-red-50 text-red-600" style="min-width: 80px;">已拒绝</span>',
                                'completed' => '<span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-blue-50 text-blue-600" style="min-width: 80px;">已完成</span>'
                            );
                            echo $status_badge[$row['status']] ?? '<span class="px-3 py-1 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-slate-50 text-slate-600" style="min-width: 80px;">未知</span>';
                            ?>
                        </td>
                        <td class="px-6 py-6 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <?php if ($row['status'] === 'pending'): ?>
                                <button onclick="approveRequest(<?php echo $row['id']; ?>)" 
                                        class="px-3 py-1.5 bg-green-500 text-white rounded-lg font-black hover:bg-green-600 transition-all active:scale-95 text-xs">
                                    <i class="fas fa-check mr-1"></i> 同意
                                </button>
                                <button onclick="rejectRequest(<?php echo $row['id']; ?>)" 
                                        class="px-3 py-1.5 bg-red-500 text-white rounded-lg font-black hover:bg-red-600 transition-all active:scale-95 text-xs">
                                    <i class="fas fa-times mr-1"></i> 拒绝
                                </button>
                                <?php elseif ($row['status'] === 'approved' && $row['type'] === 'borrow'): ?>
                                <button onclick="markAsCompleted(<?php echo $row['id']; ?>)" 
                                        class="px-3 py-1.5 bg-blue-500 text-white rounded-lg font-black hover:bg-blue-600 transition-all active:scale-95 text-xs">
                                    <i class="fas fa-check-double mr-1"></i> 完成
                                </button>
                                <?php else: ?>
                                <span class="text-xs text-slate-400">无操作</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="px-8 py-12 text-center">
                            <div class="flex flex-col items-center space-y-4">
                                <i class="fas fa-inbox text-4xl text-slate-300"></i>
                                <div class="text-slate-500 font-bold">暂无申请记录</div>
                                <div class="text-xs text-slate-400">当前筛选条件下没有找到任何申请</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-between items-center mt-6">
        <div class="text-xs text-slate-400 font-black uppercase">
            共 <?php echo $total_count; ?> 条记录，第 <?php echo $page; ?> 页 / 共 <?php echo $total_pages; ?> 页
        </div>
        <div class="flex items-center space-x-2">
            <a href="admin_dashboard.php?tab=book_requests&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo max(1, $page - 1); ?>" 
               class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl font-black text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="admin_dashboard.php?tab=book_requests&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
               class="px-4 py-2.5 <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?> rounded-xl font-black transition-all">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="admin_dashboard.php?tab=book_requests&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo min($total_pages, $page + 1); ?>" 
               class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl font-black text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
/**
 * 同意借阅申请
 * @param {number} requestId - 申请ID
 */
async function approveRequest(requestId) {
    Swal.fire({
        title: '确认操作',
        text: '确定要同意该借阅申请吗？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '同意',
        cancelButtonText: '取消'
    }).then(async (result) => {
        if (result.isConfirmed) {
            await handleRequestAction(requestId, 'approve');
        }
    });
}

/**
 * 拒绝借阅申请
 * @param {number} requestId - 申请ID
 */
async function rejectRequest(requestId) {
    Swal.fire({
        title: '确认操作',
        text: '确定要拒绝该借阅申请吗？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '拒绝',
        cancelButtonText: '取消'
    }).then(async (result) => {
        if (result.isConfirmed) {
            await handleRequestAction(requestId, 'reject');
        }
    });
}

/**
 * 标记申请为已完成
 * @param {number} requestId - 申请ID
 */
async function markAsCompleted(requestId) {
    Swal.fire({
        title: '确认操作',
        text: '确定要标记该申请为已完成吗？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '完成',
        cancelButtonText: '取消'
    }).then(async (result) => {
        if (result.isConfirmed) {
            await handleRequestAction(requestId, 'complete');
        }
    });
}

/**
 * 处理申请操作
 * @param {number} requestId - 申请ID
 * @param {string} action - 操作类型（approve/reject/complete）
 */
async function handleRequestAction(requestId, action) {
    const actionTextMap = {
        'approve': '同意',
        'reject': '拒绝',
        'complete': '标记为完成'
    };
    
    try {
        const formData = new FormData();
        formData.append('id', requestId);
        formData.append('action', action);
        
        const response = await fetch('../api/admin_book_requests.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                title: '操作成功',
                text: data.message || `${actionTextMap[action]}成功`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            // 刷新页面
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            Swal.fire({
                title: '操作失败',
                text: '操作失败: ' + (data.message || '未知错误'),
                icon: 'error'
            });
        }
    } catch (error) {
        Swal.fire({
            title: '请求异常',
            text: '请求异常，请稍后重试',
            icon: 'error'
        });
        console.error('Action Error:', error);
    }
}
</script>