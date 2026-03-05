<?php
/**
 * 用户中心统一布局模板
 * 
 * 此文件定义了用户中心页面的通用结构，包括头部、侧边栏和底部。
 * 采用了逻辑与视图分离的设计模式。
 */

// 防止直接访问此文件
if (!defined('IN_USER_CENTER')) {
    exit('Access Denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - guanen.top" : "用户中心 - guanen.top"; ?></title>
    
    <!-- 静态资源 CDN 统一管理 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- 本地样式 -->
    <link rel="stylesheet" href="../assets/css/chat.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Serif SC', 'Microsoft YaHei', serif;
        }
        
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            color: #334141; /* 修正颜色，使其更具设计感 */
            line-height: 1.6;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        /* 布局控制 */
        .full-width-container {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* 头部样式优化 */
        .header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 24px 32px;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .site-info h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .site-info p {
            color: #64748b;
            font-size: 14px;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .user-actions a {
            padding: 8px 18px;
            background: #3b82f6;
            color: white !important;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .user-actions a:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        /* 移动端自适应增强 */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            .header {
                flex-direction: column;
                padding: 20px;
                gap: 16px;
                border-radius: 16px;
            }
            .site-info {
                text-align: center;
            }
            .site-info h1 {
                justify-content: center;
                font-size: 20px;
            }
            .user-actions {
                justify-content: center;
                width: 100%;
            }
            .user-actions a {
                flex: 1;
                justify-content: center;
                min-width: calc(50% - 10px);
                padding: 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .user-actions a {
                min-width: 100%;
            }
        }

        /* 页脚样式优化 */
        .site-footer {
            margin-top: 48px;
            padding: 32px 0;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 14px;
        }

        .site-footer p {
            margin-bottom: 12px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 8px;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            .site-footer {
                margin-top: 32px;
                padding: 24px 0;
            }
            .footer-links {
                gap: 16px;
            }
        }
    </style>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <div class="container fade-in <?php echo isset($full_width) && $full_width ? 'full-width-container' : ''; ?>">
        <?php if (!isset($hide_header) || !$hide_header): ?>
        <!-- 头部导航 -->
        <header class="header">
            <div class="site-info">
                <h1><i class="fas fa-gem"></i> 个人中心</h1>
                <p>过去都是假的，回忆没有归路</p>
            </div>
            <nav class="user-actions">
                <a href="../index.php"><i class="fas fa-home"></i> 返回主站</a>
                <a href="../pages/lottery.html" style="background-color: #f59e0b;"><i class="fas fa-gift"></i> 抽奖</a>
                <a href="../pages/book_crossing.html" style="background-color: #10b981;"><i class="fas fa-paper-plane"></i> 漂流</a>
                <a href="user_logout.php" style="background-color: #ef4444;"><i class="fas fa-sign-out-alt"></i> 退出</a>
            </nav>
        </header>
        <?php endif; ?>

        <!-- 页面主体内容 -->
        <main>
            <?php echo $content; ?>
        </main>

        <?php if (!isset($hide_footer) || !$hide_footer): ?>
        <!-- 页脚 -->
        <footer class="site-footer">
            <p>&copy; <?php echo date('Y'); ?> guanen.top | 记录生活，分享快乐</p>
            <div class="footer-links">
                <a href="../pages/privacy.html">隐私政策</a>
                <a href="../pages/agreement.html">用户协议</a>
            </div>
        </footer>
        <?php endif; ?>
    </div>

    <?php if (isset($extra_js)) echo $extra_js; ?>

    <!-- 聊天系统遮罩 -->
    <div class="chat-overlay"></div>

    <!-- 聊天系统小部件 -->
    <div class="chat-widget minimized">
        <!-- 气泡图标 -->
        <div class="bubble-icon">
            <i class="fas fa-comments"></i>
            <span id="bubble-unread" class="unread-badge bubble-unread-badge" style="display:none;">0</span>
        </div>

        <!-- 左侧面板 -->
        <div class="chat-left-panel">
            <div class="chat-user-profile">
                <div style="display:flex;align-items:center;gap:12px;flex:1;">
                    <div class="friend-avatar" style="background:#3b82f6;">U</div>
                    <div class="friend-info">
                        <div class="friend-name" id="currentUserName">Loading...</div>
                        <div class="friend-motto" id="currentUserMotto"></div>
                    </div>
                </div>
                <div class="mobile-close-btn" id="mobile-close-widget-btn">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            
            <div class="search-bar-container">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-query" placeholder="搜索好友或用户...">
                </div>
            </div>

            <div class="chat-tabs">
                <div class="chat-tab active" data-target="friends">聊天</div>
                
                <div class="chat-tab" data-target="search">发现</div>
                <div class="chat-tab" data-target="requests">请求</div>
                <div class="chat-tab" data-target="group">交流群</div>
            </div>
            
            <div class="chat-list-container">
                <div id="friend-list-tab" class="friend-list">
                    <div id="friend-list-container"></div>
                </div>

                <div id="search-tab" style="display:none;">
                    <div id="search-results" class="search-results"></div>
                </div>

                <div id="requests-tab" class="friend-list" style="display:none;">
                    <div id="requests-container"></div>
                </div>
            </div>
        </div>

        <!-- 右侧面板 -->
        <div class="chat-right-panel">
            <div id="chat-window-placeholder" class="chat-window-placeholder">
                <i class="fas fa-comments"></i>
                <p>未选中聊天</p>
            </div>

            <div id="chat-window" class="chat-window">
                <div class="chat-window-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="mobile-back-btn" id="mobile-back-btn">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        <div class="friend-name" id="chat-friend-name" style="font-size:16px;font-weight:600;"></div>
                    </div>
                    <div class="chat-header-actions">
                        <i class="fas fa-ellipsis-h header-action-btn" title="更多"></i>
                        <i class="fas fa-times header-action-btn close-chat-btn" id="close-widget-btn" title="关闭"></i>
                    </div>
                </div>
                
                <div class="messages-container" id="messages-container"></div>
                
                <div class="chat-input-area">
                    <div class="chat-toolbar">
                        <i class="far fa-smile" id="emoji-btn"></i>
                        <i class="far fa-image" id="image-btn"></i>
                        <i class="far fa-folder" id="file-btn"></i>
                    </div>
                    <div class="chat-input-main">
                        <textarea id="chat-input" placeholder="请输入消息..."></textarea>
                    </div>
                    <div class="chat-footer">
                        <button id="send-msg-btn" class="send-btn">发送</button>
                    </div>
                    <div class="emoji-picker"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/chat.js"></script>
</body>
</html>
