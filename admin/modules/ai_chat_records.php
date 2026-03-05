<?php
/**
 * AI聊天记录管理模块
 * 
 * 管理员可以查看和管理用户与AI书童的聊天记录。
 */

if (!defined('ADMIN_AUTH')) exit('禁止直接访问');

// --- 调试模式开关：上线后请设为 false ---
$debug_mode = false;

// --- 1. 业务逻辑层 ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$model_filter = isset($_GET['model']) ? $_GET['model'] : 'all';

// 检查表是否存在
$table_check = $conn->query("SHOW TABLES LIKE 'ai_chat_records'");
if ($table_check->num_rows == 0) {
    die('<div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center"><h3 class="text-xl font-bold text-red-600">错误</h3><p class="text-red-500 mt-2">AI聊天记录表不存在，请先导入数据库结构</p></div>');
}

// 获取模型统计
$model_stats = array(
    'total' => 0,
    'deepseek-chat' => 0,
    'gpt-3.5-turbo' => 0,
    'gpt-4' => 0,
    'other' => 0
);

$res = $conn->query("SELECT 
    model_used,
    COUNT(*) as count
    FROM ai_chat_records 
    GROUP BY model_used");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $model = $row['model_used'];
        $model_stats['total'] += (int)$row['count'];
        if (array_key_exists($model, $model_stats)) {
            $model_stats[$model] = (int)$row['count'];
        } else {
            $model_stats['other'] += (int)$row['count'];
        }
    }
}

// 构建查询条件
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where .= " AND (u.username LIKE '%$search_safe%' OR ar.user_message LIKE '%$search_safe%' OR ar.ai_response LIKE '%$search_safe%')";
}

if ($model_filter !== 'all' && $model_filter !== 'other') {
    $where .= " AND ar.model_used = '$model_filter'";
} elseif ($model_filter === 'other') {
    $where .= " AND ar.model_used NOT IN ('deepseek-chat', 'gpt-3.5-turbo', 'gpt-4')";
}

// 获取聊天记录列表，关联用户表
$query = "SELECT ar.*, u.username, u.email, u.id as user_id
          FROM ai_chat_records ar
          LEFT JOIN users u ON ar.user_id = u.id
          $where 
          ORDER BY ar.created_at DESC 
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

if (!$result) {
    error_log("AI聊天记录查询错误: " . $conn->error);
    error_log("完整SQL: " . $query);
}

// 获取总记录数
$total_res = $conn->query("SELECT COUNT(*) as count FROM ai_chat_records $where");
$total_count = $total_res->fetch_assoc()['count'] ?? 0;
$total_pages = max(1, ceil($total_count / $limit));

// 调试信息
if ($debug_mode && isset($_GET['debug'])) {
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 text-sm text-yellow-800 font-mono">';
    echo '<p><strong>SQL查询:</strong></p>';
    echo '<pre>' . htmlspecialchars($query) . '</pre>';
    echo '<p><strong>WHERE条件:</strong> ' . htmlspecialchars($where) . '</p>';
    echo '<p><strong>总记录数:</strong> ' . $total_count . '</p>';
    echo '<p><strong>查询结果:</strong> ' . ($result ? $result->num_rows . ' 条' : '查询失败') . '</p>';
    echo '</div>';
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="animate-fade-in pb-10">
    <!-- 统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i class="fas fa-comments text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">总记录数</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $model_stats['total']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                    <i class="fas fa-robot text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">DeepSeek</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $model_stats['deepseek-chat']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                    <i class="fas fa-bolt text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">GPT-3.5</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $model_stats['gpt-3.5-turbo']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <i class="fas fa-cube text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">GPT-4</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $model_stats['gpt-4']; ?></h3>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-slate-50 flex items-center justify-center text-slate-600">
                    <i class="fas fa-layer-group text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">其他模型</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $model_stats['other']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- 搜索和筛选 -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <form class="relative w-full md:w-96" method="GET" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="ai_chat_records">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="搜索用户名、消息内容..." 
                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-slate-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all shadow-sm">
        </form>
        
        <div class="flex items-center space-x-3">
            <!-- 模型筛选 -->
            <div class="flex items-center bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <a href="admin_dashboard.php?tab=ai_chat_records&model=all&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $model_filter === 'all' ? 'bg-indigo-50 text-indigo-600 border-r border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">全部</span>
                </a>
                <a href="admin_dashboard.php?tab=ai_chat_records&model=deepseek-chat&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $model_filter === 'deepseek-chat' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">DeepSeek</span>
                </a>
                <a href="admin_dashboard.php?tab=ai_chat_records&model=gpt-3.5-turbo&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $model_filter === 'gpt-3.5-turbo' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">GPT-3.5</span>
                </a>
                <a href="admin_dashboard.php?tab=ai_chat_records&model=gpt-4&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-3.5 flex items-center space-x-2 transition-all <?php echo $model_filter === 'gpt-4' ? 'bg-indigo-50 text-indigo-600 border-l border-slate-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <span class="text-xs font-black">GPT-4</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 聊天记录列表 -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                        <th class="px-6 py-6">聊天详情</th>
                        <th class="px-6 py-6">用户信息</th>
                        <th class="px-6 py-6 text-center">使用的模型</th>
                        <th class="px-6 py-6">创建时间</th>
                        <th class="px-6 py-6 text-center">温度参数</th>
                        <th class="px-6 py-6 text-right">操作管理</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if($result && $result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="px-6 py-6">
                            <div class="flex items-start space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black border border-indigo-100 shrink-0">
                                    <i class="fas fa-comment-dots text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-slate-800 tracking-tight mb-2">
                                        <span class="text-xs text-slate-400 mr-2">👤</span>
                                        <?php echo mb_substr(htmlspecialchars($row['user_message']), 0, 60); ?>
                                        <?php echo mb_strlen($row['user_message']) > 60 ? '...' : ''; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 mb-2">
                                        <span class="text-xs text-slate-400 mr-2">🤖</span>
                                        <?php echo mb_substr(htmlspecialchars($row['ai_response']), 0, 60); ?>
                                        <?php echo mb_strlen($row['ai_response']) > 60 ? '...' : ''; ?>
                                    </div>
                                    <div class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">记录ID: #<?php echo $row['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-6">
                            <div class="font-bold text-slate-800">
                                <?php if (!empty($row['user_id'])): ?>
                                <a href="admin_dashboard.php?tab=users&id=<?php echo $row['user_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 transition-colors">
                                    <?php echo htmlspecialchars($row['username'] ?? '未知用户'); ?>
                                </a>
                                <?php else: ?>
                                <?php echo htmlspecialchars($row['username'] ?? '未知用户'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-[9px] text-slate-400 mt-0.5">
                                <?php if (!empty($row['email'])): ?>
                                <?php echo htmlspecialchars($row['email']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-[9px] text-slate-400">用户ID: <?php echo $row['user_id']; ?></div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <span class="px-3 py-1.5 text-xs font-black uppercase rounded-full inline-flex items-center justify-center bg-purple-50 text-purple-600 border border-purple-100" style="min-width: 100px;">
                                <?php echo htmlspecialchars($row['model_used']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-6">
                            <div class="font-bold text-slate-800"><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></div>
                            <div class="text-[9px] text-slate-400">
                                <?php 
                                $time_diff = time() - strtotime($row['created_at']);
                                if ($time_diff < 86400) {
                                    echo floor($time_diff / 3600) . ' 小时前';
                                } else {
                                    echo floor($time_diff / 86400) . ' 天前';
                                }
                                ?>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-center">
                            <div class="inline-flex items-center justify-center">
                                <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-blue-400 to-purple-500 rounded-full" 
                                         style="width: <?php echo ($row['temperature'] / 2) * 100; ?>%;"></div>
                                </div>
                                <span class="ml-2 text-xs font-bold text-slate-600"><?php echo $row['temperature']; ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-6 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button onclick="viewChatDetail(<?php echo json_encode($row); ?>)" 
                                        class="px-4 py-2 bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-600 rounded-xl transition-all shadow-sm text-xs font-bold">
                                    <i class="fas fa-eye mr-1.5"></i> 查看详情
                                </button>
                                <a href="admin_dashboard.php?tab=ai_chat_records&action=delete&id=<?php echo $row['id']; ?>" 
                                   onclick="return confirm('确定要删除这条聊天记录吗？此操作不可恢复！')"
                                   class="w-9 h-9 flex items-center justify-center bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-500 rounded-xl transition-all shadow-sm">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="px-8 py-12 text-center">
                            <div class="flex flex-col items-center space-y-4">
                                <i class="fas fa-inbox text-4xl text-slate-300"></i>
                                <div class="text-slate-500 font-bold">暂无聊天记录</div>
                                <div class="text-xs text-slate-400">当前筛选条件下没有找到任何记录</div>
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
            <a href="admin_dashboard.php?tab=ai_chat_records&model=<?php echo urlencode($model_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo max(1, $page - 1); ?>" 
               class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl font-black text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="admin_dashboard.php?tab=ai_chat_records&model=<?php echo urlencode($model_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
               class="px-4 py-2.5 <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?> rounded-xl font-black transition-all">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="admin_dashboard.php?tab=ai_chat_records&model=<?php echo urlencode($model_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo min($total_pages, $page + 1); ?>" 
               class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl font-black text-slate-600 hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 聊天详情弹窗 -->
<div id="chatDetailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity" onclick="closeChatDetailModal()"></div>
    
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-3xl max-h-[90vh] relative z-10 flex flex-col overflow-hidden transform transition-all duration-300 scale-95 opacity-0" id="chatDetailModalUI">
        
        <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
            <div>
                <h3 id="modalTitle" class="text-xl font-black text-slate-800">聊天详情</h3>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">完整对话内容与元数据</p>
            </div>
            <button onclick="closeChatDetailModal()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white text-slate-300 hover:text-rose-500 shadow-sm transition-all"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 space-y-6 flex-grow custom-scrollbar">
            <div class="space-y-4">
                <div class="flex items-center text-indigo-500 space-x-2">
                    <i class="fas fa-user text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">用户信息</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50/50 p-6 rounded-3xl border border-slate-100">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">用户名</label>
                        <div id="modalUsername" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">用户邮箱</label>
                        <div id="modalEmail" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">用户ID</label>
                        <div id="modalUserId" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">记录ID</label>
                        <div id="modalRecordId" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center text-purple-500 space-x-2">
                    <i class="fas fa-robot text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">对话内容</span>
                </div>
                <div class="space-y-4 bg-slate-50/50 p-6 rounded-3xl border border-slate-100">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2 text-slate-400 text-xs font-black uppercase">
                            <i class="fas fa-user text-xs"></i>
                            <span>用户消息</span>
                        </div>
                        <div id="modalUserMessage" class="p-4 bg-white border border-slate-200 rounded-2xl text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2 text-slate-400 text-xs font-black uppercase">
                            <i class="fas fa-robot text-xs"></i>
                            <span>AI回复</span>
                        </div>
                        <div id="modalAiResponse" class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-2xl text-sm text-indigo-700 leading-relaxed whitespace-pre-wrap"></div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center text-emerald-500 space-x-2">
                    <i class="fas fa-cog text-xs"></i><span class="text-[10px] font-black uppercase tracking-widest">模型参数</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50/50 p-6 rounded-3xl border border-slate-100">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">使用的模型</label>
                        <div id="modalModelUsed" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">温度参数</label>
                        <div id="modalTemperature" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">创建时间</label>
                        <div id="modalCreatedAt" class="font-bold text-slate-800 text-lg">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * 查看聊天详情
 * @param {object} chatData - 聊天记录数据
 */
function viewChatDetail(chatData) {
    const modal = document.getElementById('chatDetailModal');
    const modalUI = document.getElementById('chatDetailModalUI');
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalUI.classList.remove('scale-95', 'opacity-0');
        modalUI.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // 填充数据
    document.getElementById('modalUsername').innerText = chatData.username || '未知用户';
    document.getElementById('modalEmail').innerText = chatData.email || '无';
    document.getElementById('modalUserId').innerText = '#' + chatData.user_id;
    document.getElementById('modalRecordId').innerText = '#' + chatData.id;
    document.getElementById('modalUserMessage').innerText = chatData.user_message;
    document.getElementById('modalAiResponse').innerText = chatData.ai_response;
    document.getElementById('modalModelUsed').innerText = chatData.model_used;
    document.getElementById('modalTemperature').innerText = chatData.temperature;
    document.getElementById('modalCreatedAt').innerText = chatData.created_at;
}

/**
 * 关闭聊天详情弹窗
 */
function closeChatDetailModal() {
    const modal = document.getElementById('chatDetailModal');
    const modalUI = document.getElementById('chatDetailModalUI');
    
    modalUI.classList.remove('scale-100', 'opacity-100');
    modalUI.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
</style>
