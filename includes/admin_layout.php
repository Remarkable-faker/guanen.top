<?php
/**
 * 后台管理母版组件
 * 
 * 包含公共 CSS、JS、侧边栏和头部导航。
 * 已实现静态资源 CDN 化及样式表分离。
 */

/**
 * 渲染后台头部
 * @param string $title 页面标题
 */
function render_admin_header($title = '管理后台 - 冠恩') {
    global $tab, $_SESSION;
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    
    <!-- 引入公共库 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- 引入后台核心样式表 -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/admin.css">
    
    <!-- 引入后台核心脚本 -->
    <script src="<?php echo SITE_URL; ?>assets/js/admin.js" defer></script>
    
    <!-- 高德地图安全配置与 API -->
    <script type="text/javascript">
        window._AMapSecurityConfig = { securityJsCode: "0f19a109043d7ac7d214666a849004a4" };
    </script>
    <script type="text/javascript" src="https://webapi.amap.com/maps?v=2.0&key=bb3b7a0c75ca8febc85078fbc811f5b1&plugin=AMap.Geocoder"></script>
</head>
<body>
    <!-- 侧边栏导航 -->
    <div class="sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <i class="fas fa-shield-alt"></i>
            <span>冠恩管理后台</span>
        </div>
        
        <nav>
            <div class="sidebar-title">基础管理</div>
            <a href="admin_dashboard.php?tab=users" class="nav-item <?php echo (!isset($tab) || $tab == 'users') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>用户管理</span>
            </a>
            
            <div class="sidebar-title">书籍漂流</div>
            <a href="admin_dashboard.php?tab=book_crossing" class="nav-item <?php echo (isset($tab) && $tab == 'book_crossing') ? 'active' : ''; ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>漂流地图</span>
            </a>
            <a href="admin_dashboard.php?tab=bc_users" class="nav-item <?php echo (isset($tab) && $tab == 'bc_users') ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <span>漂流用户</span>
            </a>
            
            <div class="sidebar-title">互动管理</div>
            <a href="admin_dashboard.php?tab=library" class="nav-item <?php echo (isset($tab) && $tab == 'library') ? 'active' : ''; ?>">
                <i class="fas fa-book-reader"></i>
                <span>借阅管理</span>
            </a>
            <a href="admin_dashboard.php?tab=lottery" class="nav-item <?php echo (isset($tab) && $tab == 'lottery') ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i>
                <span>抽奖管理</span>
            </a>
            <a href="admin_dashboard.php?tab=wenjuan" class="nav-item <?php echo (isset($tab) && $tab == 'wenjuan') ? 'active' : ''; ?>">
                <i class="fas fa-comment-dots"></i>
                <span>问卷管理</span>
            </a>
        </nav>
        
        <!-- 底部退出按钮 -->
        <div style="margin-top: auto; padding: 20px;">
            <a href="admin_logout.php" class="btn-logout" style="display: block; text-align: center;">
                <i class="fas fa-sign-out-alt"></i> 退出登录
            </a>
        </div>
    </div>

    <!-- 主内容区包装器 -->
    <div class="main-wrapper">
        <!-- 顶部通栏 -->
        <header class="admin-header">
            <div class="header-left">
                <div class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="header-title">
                    <?php 
                    if (isset($tab)) {
                        switch($tab) {
                            case 'book_crossing': echo '<i class="fas fa-map-marked-alt"></i> 书籍漂流管理'; break;
                            case 'bc_users': echo '<i class="fas fa-id-card"></i> 漂流用户管理'; break;
                            case 'library': echo '<i class="fas fa-book-reader"></i> 借阅申请管理'; break;
                            case 'lottery': echo '<i class="fas fa-gift"></i> 抽奖活动管理'; break;
                            case 'wenjuan': echo '<i class="fas fa-comment-dots"></i> 问卷建议管理'; break;
                            case 'users': echo '<i class="fas fa-users-cog"></i> 系统用户管理'; break;
                            default: echo '<i class="fas fa-tachometer-alt"></i> 控制面板'; break;
                        }
                    } else {
                        echo '<i class="fas fa-tachometer-alt"></i> 控制面板';
                    }
                    ?>
                </div>
            </div>

            <div class="header-right">
                <!-- 顶部搜索框 -->
                <form class="header-search" method="GET" action="admin_dashboard.php">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="搜索关键词..." value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
                    <?php if(isset($_GET['search']) && $_GET['search']): ?>
                        <a href="admin_dashboard.php?tab=<?php echo htmlspecialchars($tab); ?>" style="color: var(--text-muted); margin-right: 5px;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="admin-info">
                    <span>
                        <i class="far fa-user-circle"></i> 
                        <?php echo htmlspecialchars(isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '管理员'); ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- 页面主体容器 -->
        <main class="container">
    <?php
}

/**
 * 渲染后台底部
 */
function render_admin_footer() {
    ?>
        </main>
    </div>
    <!-- 全局抽屉组件 (Slide-over Drawer) -->
    <div class="drawer-overlay" id="globalDrawer" onclick="if(event.target === this) closeDrawer()">
        <div class="drawer-content" onclick="event.stopPropagation()">
            <div class="drawer-header">
                <h3 id="drawerTitle">详情详情</h3>
                <button class="btn-close" onclick="closeDrawer()" title="关闭">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="drawer-body" id="drawerBody">
                <!-- 内容异步加载 -->
            </div>
        </div>
    </div>
</body>
</html>
    <?php
}
