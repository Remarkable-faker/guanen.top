<?php
/**
 * 后台管理仪表盘 - 权限增强版
 */
$debug_mode = false; 
if ($debug_mode) { error_reporting(E_ALL); ini_set('display_errors', 1); } else { error_reporting(0); }

$base_path = dirname(__DIR__); 
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once $base_path . '/includes/user_config.php';
require_once $base_path . '/core/db.php';
require_once $base_path . '/core/api_helpers.php';

define('ADMIN_AUTH', true);

// 1. 拦截：如果不是管理员，跳转登录
if (!core_is_admin()) {
    header("Location: admin_login.php");
    exit;
}

$conn = db_connect(); 

// 2. 核心拦截：如果是“只读管理员”，禁止一切修改、删除、添加操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete_id']) || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
    if (core_is_readonly_admin()) {
        echo "<script>alert('权限不足：您的账号（ID:3）仅有查看权限，无法进行增删改操作。'); window.history.back();</script>";
        exit;
    }
}

// --- 以下为原有的业务逻辑 (已受上方代码保护) ---

// 问卷建议删除
if (isset($_GET['tab']) && $_GET['tab'] === 'wenjuan' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM wenjuan_suggestions WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: admin_dashboard.php?tab=wenjuan");
    exit;
}

// 漂流用户认证
if (isset($_GET['tab']) && $_GET['tab'] === 'auth_users' && isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $new_status = ($_GET['action'] === 'approve') ? 1 : 2;
    $stmt = $conn->prepare("UPDATE bc_users SET status = ? WHERE id = ?");
    $stmt->bind_param('ii', $new_status, $user_id);
    $stmt->execute();
    header("Location: admin_dashboard.php?tab=auth_users");
    exit;
}

// 用户数据处理 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_v3'])) {
    $action = $_POST['action'];
    if ($action === 'update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, status=?, is_admin=? WHERE id=?");
        $stmt->bind_param("ssssii", $_POST['username'], $_POST['email'], $_POST['phone'], $_POST['status'], $is_admin, $id);
        $stmt->execute();
    }
    header("Location: admin_dashboard.php?tab=users&update_success=1");
    exit;
}

// 删除用户逻辑 (GET)
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin_dashboard.php?tab=users&delete_success=1");
    exit;
}

$menu_items = [
    'dashboard'       => ['icon' => 'fa-tachometer-alt', 'text' => '仪表盘'],
    'users'           => ['icon' => 'fa-users', 'text' => '用户管理'],
    'collection'      => ['icon' => 'fa-book', 'text' => '藏书阁'],
    'book_crossing'   => ['icon' => 'fa-book-open', 'text' => '书籍漂流'],
    'book_requests'   => ['icon' => 'fa-book', 'text' => '书记借阅'],
    'auth_users'      => ['icon' => 'fa-user-check', 'text' => '漂流认证'],
    'lottery'         => ['icon' => 'fa-dice', 'text' => '抽奖系统'],
    'shop_manage'     => ['icon' => 'fa-store', 'text' => '文创小铺'], // <--- 新增这行
    'wenjuan'         => ['icon' => 'fa-file-alt', 'text' => '问卷建议'],
    'ai_chat_records' => ['icon' => 'fa-robot', 'text' => 'AI聊天记录'],
    'user_logs'       => ['icon' => 'fa-history', 'text' => '用户足迹'],
    'settings'        => ['icon' => 'fa-cogs', 'text' => '系统设置'],
];
$tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <title>冠恩后台 - <?php echo $menu_items[$tab]['text'] ?? '管理中心'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full">
<div class="flex h-screen">
    <aside class="w-64 bg-slate-900 text-white flex flex-col">
        <div class="p-6 text-xl font-bold text-blue-400">Guanen Admin</div>
        <nav class="flex-1 px-4 space-y-2">
            <?php foreach ($menu_items as $key => $item): ?>
                <a href="?tab=<?php echo $key; ?>" class="flex items-center px-4 py-3 rounded-lg <?php echo $tab===$key?'bg-blue-600':'text-slate-400'; ?>">
                    <i class="fas <?php echo $item['icon']; ?> mr-3"></i><?php echo $item['text']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-8">
            <div class="font-semibold"><?php echo $menu_items[$tab]['text'] ?? ''; ?></div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500">
                    ID: <?php echo core_get_user_id(); ?> 
                    <?php if(core_is_readonly_admin()) echo '<b class="text-red-500">(只读)</b>'; ?>
                </span>
                <a href="admin_logout.php" class="text-red-500"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-8">
            <?php 
                $module_file = __DIR__ . '/modules/' . $tab . '.php';
                if (file_exists($module_file)) include $module_file; 
            ?>
        </main>
    </div>
</div>
</body>
</html>