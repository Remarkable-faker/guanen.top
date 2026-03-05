<?php
/**
 * 藏书阁模块 - 交互增强版
 * 功能：合并查看编辑、全字段管理、可输入翻页
 */
if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC'; // 默认正向排序

// 搜索逻辑
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (title LIKE '%$search_safe%' OR author LIKE '%$search_safe%' OR publisher LIKE '%$search_safe%' OR category LIKE '%$search_safe%')";
}

// 获取统计数据
$total_res = $conn->query("SELECT COUNT(*) as count FROM site_library $where");
$total_count = $total_res->fetch_assoc()['count'];
$total_pages = max(1, ceil($total_count / $limit));

// 获取藏书列表
$query = "SELECT * FROM site_library $where ORDER BY id $sort_order LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="animate-fade-in pb-10">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <form class="relative w-full md:w-96" method="GET" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="collection">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索书名、作者、出版社、分类..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all shadow-sm">
        </form>
        
        <div class="flex items-center space-x-3">
            <!-- 排序按钮 -->
            <div class="flex items-center bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <a href="admin_dashboard.php?tab=collection&sort=asc&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $sort_order === 'ASC' ? 'bg-indigo-50 text-indigo-600 border-r border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fas fa-sort-amount-up-alt text-xs"></i>
                    <span class="text-xs font-black">正向</span>
                </a>
                <a href="admin_dashboard.php?tab=collection&sort=desc&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $sort_order === 'DESC' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <i class="fas fa-sort-amount-down-alt text-xs"></i>
                    <span class="text-xs font-black">反向</span>
                </a>
            </div>
            
            <button onclick="openBookModal('add')" class="px-8 py-3.5 bg-slate-900 text-white rounded-2xl font-black hover:bg-black transition-all active:scale-95 flex items-center justify-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>新增书籍</span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-8 py-6">ID</th>
                        <th class="px-6 py-6">书名</th>
                        <th class="px-6 py-6">作者</th>
                        <th class="px-6 py-6">出版社</th>
                        <th class="px-6 py-6">分类</th>
                        <th class="px-8 py-6 text-right">操作管理</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-8 py-6">
                            <div class="font-bold text-slate-800 tracking-tight">#<?php echo $row['id']; ?></div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="font-bold text-slate-800 tracking-tight"><?php echo htmlspecialchars($row['title']); ?></div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="text-slate-600"><?php echo htmlspecialchars($row['author']); ?></div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="text-slate-600"><?php echo htmlspecialchars($row['publisher']); ?></div>
                        </td>
                        <td class="px-6 py-6">
                            <span class="px-2 py-0.5 bg-white text-slate-800 border border-slate-200 text-[9px] font-black uppercase tracking-widest rounded-full shadow-sm inline-flex items-center justify-center" style="min-width: 80px;">
                                <?php echo htmlspecialchars($row['category']); ?>
                            </span>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button onclick="viewBook(<?php echo $row['id']; ?>)" class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center hover:bg-indigo-100 transition-all">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="deleteBook(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['title']); ?>')" class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-100 transition-all">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="px-8 py-12 text-center">
                            <div class="text-slate-400 text-sm">暂无书籍信息</div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between mt-8">
        <div class="text-sm text-slate-400">
            共 <?php echo $total_count; ?> 条记录，第 <?php echo $page; ?> 页 / 共 <?php echo $total_pages; ?> 页
        </div>
        <div class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="admin_dashboard.php?tab=collection&page=<?php echo $page-1; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>" 
               class="w-10 h-10 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="admin_dashboard.php?tab=collection&page=<?php echo $i; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>" 
               class="w-10 h-10 rounded-2xl <?php echo $i == $page ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?> flex items-center justify-center transition-all">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="admin_dashboard.php?tab=collection&page=<?php echo $page+1; ?>&sort=<?php echo $sort_order; ?>&search=<?php echo urlencode($search); ?>" 
               class="w-10 h-10 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
/**
 * 查看书籍详情
 */
function viewBook(bookId) {
    alert(`查看书籍ID: ${bookId} 的详情`);
    // 可以扩展为模态框显示详细信息
}

/**
 * 删除书籍
 */
function deleteBook(bookId, bookTitle) {
    Swal.fire({
        title: '操作确认',
        text: `确定要删除书籍 "${bookTitle}" 吗？`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        customClass: { popup: 'rounded-[2.5rem]' }
    }).then((r) => { 
        if(r.isConfirmed) {
            // 发送删除请求
            const formData = new FormData();
            formData.append('id', bookId);
            formData.append('action', 'delete');
            
            fetch('../api/admin_collection.php', {
                method: 'POST',
                credentials: 'include',
                body: formData
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      Swal.fire({
                          title: '删除成功',
                          icon: 'success',
                          customClass: { popup: 'rounded-[2.5rem]' }
                      }).then(() => {
                          window.location.reload();
                      });
                  } else {
                      Swal.fire({
                          title: '删除失败',
                          text: data.message || '未知错误',
                          icon: 'error',
                          customClass: { popup: 'rounded-[2.5rem]' }
                      });
                  }
              }).catch(error => {
                  Swal.fire({
                      title: '请求异常',
                      text: '请稍后重试',
                      icon: 'error',
                      customClass: { popup: 'rounded-[2.5rem]' }
                  });
              });
        }
    });
}

/**
 * 打开书籍模态框
 */
function openBookModal(action, bookId = null) {
    // 可以扩展为模态框添加/编辑书籍
    alert(`${action === 'add' ? '添加' : '编辑'}书籍`);
}
</script>