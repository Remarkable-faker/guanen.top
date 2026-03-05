<?php
/**
 * 模块：仪表盘 (Dashboard)
 * 这是后台的默认欢迎页面，展示系统统计数据。
 */
if (!defined('ADMIN_AUTH')) {
    die('Direct access not permitted');
}

// --- 统计数据获取 --- 
// 1. 用户统计
$total_users = 0;
$total_users_res = $conn->query("SELECT COUNT(*) as count FROM users");
if ($total_users_res) {
    $total_users = $total_users_res->fetch_assoc()['count'];
}

// 今日新增用户
$today_users = 0;
$today = date('Y-m-d');
$today_users_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = '$today'");
if ($today_users_res) {
    $today_users = $today_users_res->fetch_assoc()['count'];
}

// 2. 图书统计
$total_books = 0;
$total_books_res = $conn->query("SELECT COUNT(*) as count FROM site_library");
if ($total_books_res) {
    $total_books = $total_books_res->fetch_assoc()['count'];
}

// 3. 图书申请统计
$pending_requests = 0;
$pending_requests_res = $conn->query("SELECT COUNT(*) as count FROM book_requests WHERE status = 'pending'");
if ($pending_requests_res) {
    $pending_requests = $pending_requests_res->fetch_assoc()['count'];
}

// 4. 问卷调查统计
$total_surveys = 0;
$total_surveys_res = $conn->query("SELECT COUNT(*) as count FROM wenjuan");
if ($total_surveys_res) {
    $total_surveys = $total_surveys_res->fetch_assoc()['count'];
}

// 5. 抽奖统计
$total_lotteries = 0;
$total_lotteries_res = $conn->query("SELECT COUNT(*) as count FROM lottery");
if ($total_lotteries_res) {
    $total_lotteries = $total_lotteries_res->fetch_assoc()['count'];
}
?>

<div class="animate-bounce-in">
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center mb-8">
        <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
            <i class="fas fa-tachometer-alt"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">欢迎来到后台管理系统</h2>
        <p class="text-gray-500 max-w-lg mx-auto">
            您可以在左侧的导航菜单中选择需要管理的功能模块。祝您有美好的一天！
        </p>
    </div>

    <!-- 统计卡片网格 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- 总用户数 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">总用户数</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $total_users; ?></h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <!-- 今日新增用户 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">今日新增用户</p>
                    <h3 class="text-3xl font-bold text-green-600"><?php echo $today_users; ?></h3>
                </div>
                <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>
        </div>

        <!-- 总图书数 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">总图书数</p>
                    <h3 class="text-3xl font-bold text-gray-800"><?php echo $total_books; ?></h3>
                </div>
                <div class="w-12 h-12 bg-yellow-50 text-yellow-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-book"></i>
                </div>
            </div>
        </div>

        <!-- 待处理申请 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">待处理图书申请</p>
                    <h3 class="text-3xl font-bold text-orange-600"><?php echo $pending_requests; ?></h3>
                </div>
                <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        </div>

        <!-- 问卷调查数 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">问卷调查总数</p>
                    <h3 class="text-3xl font-bold text-purple-600"><?php echo $total_surveys; ?></h3>
                </div>
                <div class="w-12 h-12 bg-purple-50 text-purple-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-poll"></i>
                </div>
            </div>
        </div>

        <!-- 抽奖活动数 -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">抽奖活动总数</p>
                    <h3 class="text-3xl font-bold text-red-600"><?php echo $total_lotteries; ?></h3>
                </div>
                <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-gift"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 快捷操作 -->
    <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <h3 class="text-xl font-semibold text-gray-800 mb-6">快捷操作</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <a href="?tab=users" class="flex items-center space-x-3 px-6 py-4 bg-gray-800 text-white rounded-lg hover:bg-gray-700 transition-colors font-semibold">
                <i class="fas fa-users text-xl"></i>
                <span>用户管理</span>
            </a>
            <a href="?tab=collection" class="flex items-center space-x-3 px-6 py-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-semibold">
                <i class="fas fa-book text-xl"></i>
                <span>图书管理</span>
            </a>
            <a href="?tab=book_requests" class="flex items-center space-x-3 px-6 py-4 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold">
                <i class="fas fa-clipboard-list text-xl"></i>
                <span>图书申请</span>
            </a>
            <a href="?tab=settings" class="flex items-center space-x-3 px-6 py-4 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                <i class="fas fa-cogs text-xl"></i>
                <span>系统设置</span>
            </a>
        </div>
    </div>
</div>
