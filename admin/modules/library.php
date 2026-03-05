<?php
/**
 * 图书借阅申请模块
 * 
 * 异步加载并处理用户的图书借阅和归还申请。
 */

if (!defined('ADMIN_AUTH')) {
    die('直接访问被禁止');
}

// 统一使用绝对路径引用配置文件
require_once dirname(__DIR__, 2) . '/includes/user_config.php';
require_once dirname(__DIR__, 2) . '/core/db.php';

// 获取基础统计数据
$stats = array(
    'total_pending' => 0,
    'borrow_pending' => 0,
    'return_pending' => 0
);

$res = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = 'borrow' THEN 1 ELSE 0 END) as borrow_count,
    SUM(CASE WHEN type = 'return' THEN 1 ELSE 0 END) as return_count
    FROM book_requests WHERE status = 'pending'");
if ($res && $row = $res->fetch_assoc()) {
    $stats['total_pending'] = (int)$row['total'];
    $stats['borrow_pending'] = (int)$row['borrow_count'];
    $stats['return_pending'] = (int)$row['return_count'];
}
?>

<div class="main-content">
    <!-- 统计卡片 -->
    <div class="stat-cards-container">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary-light); color: var(--primary-color);"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-info">
                <h3>待处理总数</h3>
                <div class="stat-number"><?php echo $stats['total_pending']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning-light); color: var(--warning-color);"><i class="fas fa-sign-out-alt"></i></div>
            <div class="stat-info">
                <h3>借阅申请</h3>
                <div class="stat-number"><?php echo $stats['borrow_pending']; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success-light); color: var(--success-color);"><i class="fas fa-sign-in-alt"></i></div>
            <div class="stat-info">
                <h3>归还申请</h3>
                <div class="stat-number"><?php echo $stats['return_pending']; ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <h3><i class="fas fa-list"></i> 借阅申请列表</h3>
            <button onclick="loadRequests()" class="btn-action btn-view">
                <i class="fas fa-sync-alt"></i> 刷新列表
            </button>
        </div>
    
    <div class="users-table-container">
        <div id="loading-spinner" class="spinner" style="display: none;"></div>
        <table id="requests-table">
            <thead>
                <tr>
                    <th>申请ID</th>
                    <th>书籍名称</th>
                    <th>申请人</th>
                    <th>申请类型</th>
                    <th>申请时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="requests-body">
                <!-- 数据由 JS 异步加载 -->
            </tbody>
        </table>
    </div>
</div>

<!-- 消息提示 -->
<div id="message-toast"></div>

<script>
/**
 * 异步加载借阅申请数据
 */
async function loadRequests() {
    const spinner = document.getElementById('loading-spinner');
    const tbody = document.getElementById('requests-body');
    
    spinner.style.display = 'block';
    tbody.innerHTML = '';
    
    try {
        // 请求 API 获取待处理列表
        const response = await fetch('../api/admin_book_requests.php', { credentials: 'include' });
        const data = await response.json();
        
        spinner.style.display = 'none';
        
        if (data.success && data.data && data.data.length > 0) {
            data.data.forEach(req => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="id-badge">#${req.id}</span></td>
                    <td><strong>${req.book_title}</strong></td>
                    <td>${req.username}</td>
                    <td>
                        <span class="badge ${req.type === 'borrow' ? 'badge-borrow' : 'badge-return'}">
                            ${req.type === 'borrow' ? '借阅申请' : '归还申请'}
                        </span>
                    </td>
                    <td>${req.created_at}</td>
                    <td>
                        <span class="status-dot ${req.status}"></span>
                        <span class="status-text ${req.status}">
                            ${req.status_text || '待处理'}
                        </span>
                    </td>
                    <td>
                        ${req.status === 'pending' ? `
                            <div class="action-buttons">
                                <button onclick="handleRequest(${req.id}, 'approve')" class="btn-action btn-approve" title="同意">
                                    <i class="fas fa-check"></i> 同意
                                </button>
                                <button onclick="handleRequest(${req.id}, 'reject')" class="btn-action btn-reject" title="拒绝">
                                    <i class="fas fa-times"></i> 拒绝
                                </button>
                            </div>
                        ` : '<span style="color:var(--text-light); font-size: 13px;">已完成</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:#64748b;">暂无待处理的申请</td></tr>';
        }
    } catch (error) {
        spinner.style.display = 'none';
        showToast('加载失败，请检查网络连接', 'error');
        console.error('Load Error:', error);
    }
}

/**
 * 处理申请（同意/拒绝）
 */
async function handleRequest(requestId, action) {
    const actionText = action === 'approve' ? '同意' : '拒绝';
    if (!confirm(`确定要${actionText}该申请吗？`)) return;
    
    try {
        // 使用 POST 方式提交操作，更符合 REST 规范
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
            showToast(data.message || '操作成功', 'success');
            loadRequests(); // 重新加载列表
        } else {
            showToast('操作失败: ' + (data.message || '未知错误'), 'error');
        }
    } catch (error) {
        showToast('请求异常，请稍后重试', 'error');
        console.error('Handle Error:', error);
    }
}

/**
 * 显示吐司消息
 */
function showToast(message, type) {
    const toast = document.getElementById('message-toast');
    toast.textContent = message;
    toast.className = `show toast-${type}`;
    
    setTimeout(() => {
        toast.className = '';
    }, 3000);
}

// 页面加载后自动获取数据
document.addEventListener('DOMContentLoaded', loadRequests);
</script>

<style>
/* 局部样式增强 */
.badge-borrow { background: var(--primary-light); color: var(--primary-color); }
.badge-return { background: var(--warning-light); color: var(--warning-color); }

.status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
.status-dot.pending { background: var(--warning-color); }
.status-dot.approved { background: var(--success-color); }
.status-dot.rejected { background: var(--danger-color); }

.status-text { font-weight: 500; font-size: 13px; }
.status-text.pending { color: var(--warning-color); }
.status-text.approved { color: var(--success-color); }
.status-text.rejected { color: var(--danger-color); }

#message-toast {
    position: fixed; bottom: 30px; right: 30px; padding: 12px 24px; border-radius: 10px; 
    color: white; box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
    transform: translateY(150px); transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
    z-index: 10000; font-weight: 500;
}
#message-toast.show { transform: translateY(0); }
.toast-success { background: var(--success-color); }
.toast-error { background: var(--danger-color); }

.spinner { width: 40px; height: 40px; border: 3px solid var(--bg-main); border-top: 3px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; margin: 40px auto; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
