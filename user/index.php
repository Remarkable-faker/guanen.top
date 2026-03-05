<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <script>
        // 自动计算 API 路径前缀
        const getApiPath = (endpoint) => {
            const path = window.location.pathname;
            const isSubPage = path.includes('/user/') || path.includes('/pages/') || path.includes('/games/');
            return (isSubPage ? '../api/' : 'api/') + endpoint;
        };

        // 检查登录状态并更新 UI
        fetch(getApiPath('check_login.php'), { credentials: 'include' })
            .then(response => response.json())
            .then(data => {
                const status = data.data || data;
                window.isLoggedIn = status.logged_in || status.is_logged_in;
                console.log('Global Login Status:', window.isLoggedIn);
            })
            .catch(error => {
                console.error('Login check failed:', error);
            });
    </script>
    <!-- 
        项目名称：冠恩先生个人网站
        功能描述：网站主页，包含欢迎动画、导航链接、React组件集成及实时聊天功能
        维护者：Mr Gauen
    -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
    <title>冠恩先生 | Mr Gauen</title>
        <link rel="icon" type="image/webp" sizes="32x32" href="assets/images/favicon-32x32.ico">
    
    <!-- 核心动画脚本：负责页面首屏的欢迎语及入场效果 -->
    <script src="assets/js/hello-animation.js"></script>
    
    <!-- 外部库集成：React 框架及其 DOM 渲染库，Lucide 图标库 (本地化加载) -->
    <!-- 核心库加载 -->
    <script src="assets/vendor/react.min.js"></script>
    <script src="assets/vendor/react-dom.min.js"></script>
    <script>
        /**
         * 核心库初始化
         * 确保 React 全局变量可用，兼容不同大小写形式的 UMD 模块
         * 这样可以防止 Lucide 等库在加载时因找不到 React 而报错
         */
        try {
            if (typeof React !== 'undefined') {
                window.React = React;
                window.react = React; // 兼容某些寻找小写 'react' 的 UMD 模块
            }
            if (typeof ReactDOM !== 'undefined') {
                window.ReactDOM = ReactDOM;
                window.reactdom = ReactDOM;
            }
        } catch (e) {
            console.error('React initialization failed:', e);
        }
    </script>
    <script src="assets/vendor/lucide-react.min.js"></script>
    <script>
        /**
         * 图标库加载后的容错处理
         * 如果 Lucide 加载失败，提供一个 Mock 对象防止代码报错
         * 并尝试在 DOM 加载后初始化图标（如果有相关元素）
         */
        if (typeof LucideReact === 'undefined') {
            console.warn('LucideReact failed to load. Using mock to prevent crashes.');
            window.LucideReact = new Proxy({}, {
                get: function(target, prop) {
                    return function() { return null; };
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // 如果页面使用了 data-lucide 属性，尝试自动创建图标
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }
            } catch (e) {
                console.error('Icon initialization failed, but page will continue to load:', e);
            }
        });
    </script>
    
    <!-- 样式表引用 -->
    <link rel="stylesheet" href="assets/css/main.css">          <!-- 全局基础样式 -->
    <link rel="stylesheet" href="assets/css/pages.css">         <!-- 各页面通用布局样式 -->
    <link rel="stylesheet" href="assets/css/page-loading.css">   <!-- 页面加载动画专用样式 -->
    <link rel="stylesheet" href="assets/css/chat.css">          <!-- 聊天室/客服组件样式 -->
    <link rel="stylesheet" href="assets/css/new-year.css">      <!-- 新年节日氛围样式 -->
    <!-- 引入 FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- 引入 Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ========== 响应式基础 ========== */
* { margin:0; padding:0; box-sizing:border-box;}
body { font-family:'Microsoft YaHei',sans-serif; overflow-x:hidden; background:#fff; color:#222;}

/* ========== 加载动画 ========== */
    #loading-ink {
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background: white;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        font-family: "KaiTi", "SimHei", serif;
        opacity: 1;
        transition: opacity 0.8s ease-out;
    }

    /* 加载内容容器 - 确保垂直居中排列 */
    #loading-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px; /* 元素之间的间距 */
        width: 100%;
        max-width: 500px;
        padding: 20px;
        margin-top: 20vh; /* 向下移动20% */
    }

    #loading-message {
        margin-bottom: 30px;
        font-size: 1.8rem;
        font-weight: bold;
        color: #333;
        text-align: center;
    }

    /* 加载进度条样式 */
    #loading-progress {
        width: 300px;
        height: 4px;
        background: rgba(0,0,0,0.1);
        border-radius: 2px;
        margin: 20px auto;
        overflow: hidden;
    }

    #loading-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0078ff, #00d4ff);
        border-radius: 2px;
        width: 0%;
        transition: width 0.3s ease;
    }

    /* 跳过按钮样式 */
    #skip-loading {
        margin-top: 20px;
        padding: 8px 20px;
        background: rgba(0,0,0,0.1);
        border: 1px solid rgba(0,0,0,0.2);
        border-radius: 20px;
        color: #666;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    #skip-loading:hover {
        background: rgba(0,0,0,0.2);
        color: #333;
    }

    .floating-text {
        position: absolute;
        font-size: 20px;
        opacity: 0;
        white-space: nowrap;
        pointer-events: none;
        user-select: none;
        transition: transform 1s linear, opacity 0.4s linear;
        color: rgba(0, 0, 0, 0.6);
        font-family: "KaiTi", "SimHei", serif;
    }

    #loading-ink.hidden {
        opacity: 0;
        transition: opacity 0.8s ease-out;
        pointer-events: none;
    }

    .page-content {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.6s ease;
        /* 确保页面内容完全隐藏，防止闪烁 */
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    .page-content.show {
        opacity: 1;
        visibility: visible;
        /* 显示时恢复正常定位 */
        position: static;
        top: auto;
        left: auto;
    }

    /* Hello动画容器样式 - 固定在进度条上方 */
    #hello-animation-container {
        position: fixed;
        top: 30%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
        pointer-events: none;
    }

    .hello-svg {
        width: 100%;
        max-width: 600px;
        height: auto;
    }

/* ========== 响应式布局 ========== */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}

.small-text {
  font-size: 1.2rem;
}

/* 响应式图片和视频 */
img, video {
  max-width: 100%;
  height: auto;
}

/* 顶部全屏大图板块 */
#hero {
  width: 100%;
  height: 100vh;
  background-color: #f0f0f0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  transition: background-image 0.5s ease-in-out;
}

/* 顶部导航 */
header, .nav-container {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 1rem;
}

/* 本杰明文段 */
.quote-author {
  display: block;
  text-align: right;
  margin-top: 10px;
  font-size: 0.9em;
  color: #666;
}

/* 顶部全屏区 */
.fullscreen {
    position: relative;
    width: 100%;
    height: 100vh;
    min-height: 600px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-image: url('assets/images/he.webp'); /* 已更换为 he.webp */
    background-color: #333; /* 添加默认背景色，防止图片加载失败时文字不可见 */
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* 主标题 */
.main-title {
    font-size: 8.5rem;
    font-weight: 900;
    letter-spacing: 3px;
    color: rgba(255, 255, 255, 0.8);
    position: relative;
    text-align: center;
    margin: 0 auto;
    transition: transform 0.1s linear;
}

/* 欢迎语容器 */
.welcome-text {
    font-size: 3.5rem;
    font-weight: 600;
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.6;
    white-space: nowrap;
}

/* 左上角：logo + 联系作者（并排） - 调整位置与右侧菜单对齐 */
.logo-container {
    position: fixed;
    top: 22px; /* 向下移动，使中心点与右侧菜单对齐 */
    left: 30px;
    z-index: 101;
    display: flex;
    align-items: center;
    gap: 15px; /* 减小间距，防止旋转圆圈与联系冠恩重叠 */
}

/* 联系作者样式 */
.logo-author {
    cursor: pointer;
    display: inline-block;
    margin-left: 50px;
    font-weight: bold;
    color: #333;
    transition: color 0.3s;
}

.logo-author:hover {
    color: #ff6600;
}

/* 文章按钮 */
.logo-article {
    cursor: pointer;
    display: inline-block;
    margin-left: 10px;
    font-weight: bold;
    color: #333;
    transition: color 0.3s;
}

.logo-article:hover {
    color: #ff6600;
}

/* logo 样式 */
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: transform 0.3s ease;
    position: relative;
}
.logo:hover { transform: scale(1.05); }

.logo-img {
    width:42px; height:42px; border-radius:50%; overflow:hidden;
    box-shadow:0 2px 6px rgba(0,0,0,0.2); display:flex;
    justify-content:center; align-items:center; background:#f0f0f0;
    transition: transform 0.3s ease;
}
.logo:hover .logo-img { transform: scale(1.1); }
.logo-img img{ width:100%; height:100%; object-fit:cover; }

.logo-text {
    font-size:1.2rem; font-weight:700; color:#111; letter-spacing:2px;
    transition: transform 0.3s ease;
}
.logo:hover .logo-text { transform: scale(1.05); }

/* logo tooltip */
.logo-tooltip {
    position: absolute;
    top: 60px;
    left: 0;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    white-space: nowrap;
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    pointer-events: none;
    z-index: 150;
}
.logo:hover .logo-tooltip {
    opacity: 1;
    transform: translateY(0);
}

/* 独立的"联系作者"文本 */
.logo-author {
    font-size: 1.05rem;
    font-weight: 600;
    color: #333;
    cursor: pointer;
    position: relative;
    padding: 6px 10px;
    border-radius: 6px;
    transition: color .18s ease, background .18s ease, transform .12s ease;
    user-select: none;
}
.logo-author:hover {
    color: #0078ff;
    background: rgba(0,0,0,0.02);
    transform: translateY(-1px);
}

/* 弹窗样式已移除，联系作者按钮直接跳转到页面底部 */

/* 右上角菜单 */
.menu { position:fixed; top:22px; right:30px; z-index:100; display:flex; gap:12px; }
.menu-btn {
    cursor: pointer;
    display: inline-block;
    font-weight: bold;
    color: #333;
    transition: transform 0.3s ease;
    background: none;
    border: none;
    padding: 6px 10px;
    font-size: 1.05rem;
}
.menu-btn:hover { transform: scale(1.1); }

/* 移动端响应式按钮优化 */
@media (max-width: 768px) {
    /* 主头部布局调整 - 保持两边排列 */
    .main-header {
        padding: 8px 12px !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: nowrap;
        background: transparent !important;
        background-color: transparent !important;
    }
    
    /* 左侧logo容器 - 保持在左侧 */
    .logo-container {
        position: static !important;
        display: flex !important;
        align-items: center !important;
        gap: 6px !important; /* 进一步减小间距，防止旋转圆圈与联系冠恩重叠 */
        flex-wrap: nowrap !important;
        margin: 0 !important;
    }
    
    .logo {
        display: flex !important;
        align-items: center !important;
        gap: 6px !important; /* 减小间距更紧凑 */
    }
    
    .logo-text {
        font-size: 0.9rem; /* 略微减小字体 */
        white-space: nowrap;
    }
    
    /* 联系冠恩和冠恩书屋 - 横向并排且更紧凑 */
    .logo-author, .logo-article {
        font-size: 0.8rem; /* 减小字体 */
        padding: 4px 6px; /* 减小内边距 */
        white-space: nowrap;
        margin: 0 !important;
    }
    
    /* 右侧菜单按钮 - 保持在右侧 */
    .menu {
        position: static !important;
        display: flex !important;
        align-items: center !important;
        gap: 4px !important; /* 减小间距更紧凑 */
    }
    
    .menu-btn {
        padding: 4px 6px; /* 减小内边距 */
        font-size: 0.8rem; /* 减小字体 */
        min-width: 40px; /* 减小最小宽度 */
        text-align: center;
    }
    
    /* 超小屏幕下的优化 - 仍然保持两边排列，更加紧凑 */
    @media (max-width: 480px) {
        .main-header {
            padding: 6px 8px !important;
            flex-wrap: nowrap;
            background: transparent !important;
            background-color: transparent !important;
        }
        
        .logo-container {
            gap: 6px !important;
        }
        
        .logo {
            gap: 4px !important;
        }
        
        .logo-text {
            font-size: 0.8rem;
        }
        
        .logo-author, .logo-article {
            font-size: 0.7rem;
            padding: 3px 5px;
        }
        
        .menu {
            gap: 3px !important;
        }
        
        .menu-btn {
            padding: 3px 5px;
            font-size: 0.7rem;
            min-width: 35px;
        }
    }
}

/* ========== 横向滚动展示区 ========== */
.horizontal-scroll-section {
    padding: 80px 0;
    background: linear-gradient(to bottom, #fff);
    overflow: hidden;
    position: relative;
    z-index: 1;
}
#decorativeHellos {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    overflow: hidden;
    z-index: 1;
}
.hello-bubble {
    position: absolute;
    font-size: 1rem;
    color: rgba(0,0,0,0.45);
    white-space: nowrap;
    pointer-events: none;
    user-select: none;
    animation: floatUp 5s linear;
}
@keyframes floatUp {
    0% { transform: translateY(0) scale(1); opacity: 1; }
    100% { transform: translateY(-100px) scale(1.12); opacity: 0; }
}

.scroll-container { max-width: 100%; margin: 0 auto; padding-top: 20px; }
.scroll-title {
    text-align: center;
    font-size: 2.8rem;
    margin-bottom: 50px;
    color: #333;
    position: relative;
}
.scroll-title:after {
    content: '';
    display: block;
    width: 80px;
    height: 5px;
    background: #333;
    margin: 20px auto;
    border-radius: 3px;
}

.horizontal-scroll {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 30px 20px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    scroll-behavior: smooth;
}
.horizontal-scroll::-webkit-scrollbar { display: none; }

.scroll-content { display:flex; gap: 35px; padding: 0 50px; }

.photo-item {
    scroll-snap-align: start;
    flex: 0 0 auto;
    width: 600px;
    height: 400px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    background: #f5f5f5;
    position: relative;
}
.photo-item img { width:100%; height:100%; object-fit:cover; transition: transform 0.5s ease; }
.photo-item:hover { transform: translateY(-12px) scale(1.03); box-shadow: 0 20px 45px rgba(0,0,0,0.25); }
.photo-item:hover img { transform: scale(1.12); }

/* 图片标题 */
.photo-caption {
    position: absolute; bottom: 0; left:0; right:0; color:#fff; padding:15px;
    transform: translateY(100%); transition: transform 0.4s ease; text-align:center;
    font-size:16px; font-weight:500; text-shadow: 0 2px 4px rgba(0,0,0,0.8); z-index: 2;
}

.photo-item:hover .photo-caption { transform: translateY(0); }

/* 版权归作者所有*/
.copyright-note {
    text-align: center;
    font-size: 0.85rem;
    color: rgba(0,0,0,0.5);
    margin-top: 25px;
    margin-bottom: 1px;
}

/* 渐变覆盖 */
.photo-item::after {
    content:''; position:absolute; bottom:0; left:0; right:0; height:40%;
    background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%);
    opacity:0; transition:opacity .4s ease; z-index: 1;
}
.photo-item:hover::after { opacity: 1; }

/* 自定义滚动条 */
.custom-scrollbar { width: 80%; height: 6px; background: rgba(0,0,0,0.08); border-radius:3px; margin:30px auto 0; position:relative; overflow:hidden; display:none; }
.scroll-track { width:100%; height:100%; position:relative; }
.scroll-thumb {
    position:absolute; height:100%; background: rgba(0,0,0,0.5); border-radius:3px; cursor:pointer; transition:background .3s ease; min-width:50px;
}
.scroll-thumb:hover { background: rgba(0,0,0,0.7); }

/* 小光标样式 */
.cursor { display:inline-block; width:2px; height:1.2em; background:#111; margin-left:4px; vertical-align:middle; animation: blink 1s steps(2,start) infinite; }
@keyframes blink { 50% { opacity:0; } }

/* ========= 中心文字展示 ========= */
.center-text-section {
    width: 100%;
    min-height: 60vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 60px 20px;
    background-color: #fefefe;
}

.center-text-container {
    max-width: 900px;
}

.center-text {
    font-size: 2rem;
    font-weight: 600;
    line-height: 1.8;
    color: #222;
    opacity: 0;
    transform: scale(0.7);
    transition: opacity 1s ease, transform 1s ease;
    word-break: break-word;
    white-space: normal;
    text-align: left;
}

/* 激活状态：淡入放大 */
.center-text.show {
    opacity: 1;
    transform: scale(1);
}

/* 新增引用区块样式 */
.quote-section {
    width: 100%;
    padding: 60px 20px;
    background-color: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quote-container {
    max-width: 1000px;
    width: 100%;
    text-align: center;
}

.highlight-quote {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
    line-height: 1.6;
    margin: 0;
    font-family: "Microsoft YaHei", Arial, sans-serif;
}

/* 用户评价模块样式 */
.bg-background {
    background-color: #f9f9f9;
    position: relative;
    overflow: hidden;
}

/* 横三竖三布局 */
.testimonials-grid-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
    animation: scrollUp 30s linear infinite;
    position: relative;
    will-change: transform;
}

/* 设置一个容器来实现无缝循环滚动 */
.testimonials-wrapper {
    position: relative;
    height: 500px; /* 设置固定高度，显示3行 */
    overflow: hidden;
    margin: 0 auto;
}

.testimonial-row {
    display: flex;
    justify-content: center;
    gap: 30px;
}

/* 实现更流畅、更慢的永久反复滚动效果 */
@keyframes scrollUp {
    0% {
        transform: translateY(0);
    }
    100% {
        transform: translateY(-50%);
    }
}

/* 调整响应式布局 */
@media (max-width: 1024px) {
    .testimonials-wrapper {
        height: 450px;
    }
    .testimonial-item {
        width: 280px;
    }
}

@media (max-width: 768px) {
    .testimonials-wrapper {
        height: 400px;
    }
    .testimonial-item {
        width: 220px;
    }
    .testimonial-row {
        gap: 20px;
    }
}

.testimonial-item {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: 320px; /* 设置固定宽度，确保三列布局 */
}

.testimonial-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.testimonial-text {
    font-size: 1rem;
    line-height: 1.6;
    color: #333;
    margin-bottom: 20px;
    font-family: "Microsoft YaHei", Arial, sans-serif;
}

.testimonial-author {
    display: flex;
    justify-content: center;
    padding-top: 15px;
}

/* 额外的动画优化 */
.testimonials-wrapper {
    position: relative;
    height: 500px; /* 设置固定高度，显示3行 */
    overflow: hidden;
    margin: 0 auto;
    perspective: 1000px;
}

/* 响应式设计 - 针对用户评价模块的优化 */
/* 桌面端（永久滚动动画） */
@media (min-width: 769px) {
    .testimonials-wrapper {
        position: relative;
        height: 500px; /* 设置固定高度，显示3行 */
        overflow: hidden;
        margin: 0 auto;
        perspective: 1000px;
    }
    
    /* 自动滚动动画 */
    @keyframes scrollUp {
        0% { transform: translateY(0); }
        100% { transform: translateY(-50%); }
    }
    
    .testimonials-grid-container {
        will-change: transform;
        backface-visibility: hidden;
        transform: translateZ(0);
        animation: scrollUp 30s linear infinite;
    }
}
    display: flex;
    flex-direction: column;
}

.testimonial-name {
    font-weight: bold;
    color: #111;
    font-size: 0.95rem;
}

.testimonial-role {
    color: #666;
    font-size: 0.85rem;
    margin-top: 2px;
}

/* InteractiveHoverButton基础样式 */
.interactive-hover-button {
    background-color: #fff;
    color: #333;
    border: 1px solid #ddd;
}

.interactive-hover-button:hover {
    background-color: #f5f5f5;
}

/* 优雅样式的按钮 - 更长的UI和增强的动画效果 */
.elegantly-styled-button {
    /* 增加按钮长度 - 使UI更长 */
    width: 220px !important;
    padding: 12px 24px !important;
    
    /* 更优雅的外观 */
    border-radius: 30px;
    border: 2px solid #333;
    background: linear-gradient(135deg, #ffffff, #f5f5f5);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
    color: #333;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    
    /* 增强的过渡动画 */
    transition: all 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    overflow: hidden;
}

/* 添加装饰性元素 */
.elegantly-styled-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    transition: all 0.6s;
}

.elegantly-styled-button:hover::before {
    left: 100%;
}

.elegantly-styled-button:hover {
    /* 悬停时的缩放和阴影效果 */
    transform: translateY(-4px) scale(1.03);
    box-shadow: 0 12px 20px rgba(0,0,0,0.18), 0 3px 6px rgba(0,0,0,0.12);
    border-color: #444;
    background: linear-gradient(135deg, #f5f5f5, #ffffff);
}

.elegantly-styled-button:active {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

/* 添加波纹动画效果 */
.elegantly-styled-button span {
    position: relative;
    z-index: 2;
}

/* 点击波纹效果样式 */
.elegantly-styled-button .ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.1);
    transform: scale(0);
    animation: buttonRipple 0.6s ease-out;
    pointer-events: none;
    z-index: 1;
}

/* 波纹效果的关键帧动画 */
@keyframes buttonRipple {
    0% {
        transform: scale(0);
        opacity: 0.7;
    }
    100% {
        transform: scale(4);
        opacity: 0;
    }
}

/* 主标题栏样式 - 确保完全透明 */
.main-header {
    background: transparent !important;
    backdrop-filter: none !important;
    border-bottom: none !important;
    background-color: transparent !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 101 !important;
    padding: 15px 30px !important;
    background-image: none !important;
    -webkit-background-clip: border-box !important;
    background-clip: border-box !important;
    box-shadow: none !important;
    -webkit-box-shadow: none !important;
    -moz-box-shadow: none !important;
}

/* 确保所有元素对齐 */
.logo-container,
.menu {
    display: flex !important;
    align-items: center !important;
    background: transparent !important;
}

/* 修复logo容器和menu容器的样式冲突 */
.logo-container {
    position: static !important;
    z-index: auto !important;
    background: transparent !important;
}

/* 确保所有顶部容器都是透明的 */
header,
.nav-container,
.logo,
.rotating-logo-wrapper,
.central-logo {
    background: transparent !important;
    background-color: transparent !important;
}

/* 确保logo图片容器也是透明的 */
.logo-img {
    background: transparent !important;
    background-color: transparent !important;
}

/* 额外的波纹效果 */
.elegantly-styled-button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(0,0,0,0.1);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%, -50%);
    transform-origin: 50% 50%;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(100, 100);
        opacity: 0;
    }
}

.elegantly-styled-button:focus:not(:active)::after {
    animation: ripple 0.6s ease-out;
}

/* 底部图标样式 */
.footer-icons {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.footer-icon {
  width: 28px;
  height: 28px;
  transition: transform 0.3s ease, filter 0.3s ease;
  cursor: pointer;
}

.footer-icons a,
.footer-icons #wechat-icon {
    display: inline-block;
    margin: 0 12px;
    vertical-align: middle;
}

.footer-icons .footer-icon {
    width: 32px;
    height: 32px;
    display: block;
}

.footer-icons .footer-icon:hover {
  transform: scale(1.2) translateY(-3px);
  filter: brightness(1.2);
}

/* 底部图标链接样式 */
.footer-icon-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 12px;
    transition: transform 0.3s ease, filter 0.3s ease;
}

.footer-icon-link:hover {
    transform: scale(1.2) translateY(-3px);
    filter: brightness(1.2);
}

/* 微信容器样式 */
.wechat-container {
    position: relative;
    cursor: pointer;
}

/* 微信弹出框样式 */
.wechat-popup {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 12px;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    font-family: Microsoft YaHei, Arial, sans-serif;
    font-size: 14px;
    white-space: nowrap;
    margin-bottom: 8px;
    z-index: 1000;
}

.wechat-popup::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: #fff transparent transparent transparent;
}

.wechat-container:hover .wechat-popup {
    display: block;
}

/* 备案信息样式 */
footer {
    width: 100%;
    padding: 12px 8px;
    box-sizing: border-box;
    text-align: center;
    font-family: "Microsoft YaHei", Arial, sans-serif;
    color: #555;
    font-size: 0.8rem;
}
.beian-link {
    color: #555;
    text-decoration: none;
}
.beian-link:hover {
    text-decoration: underline;
}
.beian-icon {
    height: 16px;
    vertical-align: -3px;
    margin-right: 4px;
}
.divider {
    margin: 0 6px;
}

/* 页面底部按钮 */
.to-bottom-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(0,0,0,0.8);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 99;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.to-bottom-btn:hover {
    transform: translateY(-5px);
    background: rgba(0,0,0,0.9);
}

/* ========== 响应式设计 ========== */
/* 平板设备 */
@media (max-width: 1024px) {
    .main-title {
        font-size: 6rem;
    }
    
    .welcome-text {
        font-size: 2.5rem;
    }
    
    .photo-item {
        width: 500px;
        height: 350px;
    }
    
    .scroll-title {
        font-size: 2.2rem;
    }
    
    .center-text {
        font-size: 1.8rem;
    }
    
    .logo-container {
        left: 20px;
        gap: 15px;
    }
    
    .menu {
        right: 20px;
    }
}

/* 大屏手机 */
@media (max-width: 768px) {
    .main-title {
        font-size: 4rem;
    }
    
    .welcome-text {
        font-size: 2rem;
        white-space: normal;
    }
    
    .photo-item {
        width: 400px;
        height: 280px;
    }
    
    .scroll-title {
        font-size: 1.8rem;
        margin-bottom: 30px;
    }
    
    .center-text {
        font-size: 1.5rem;
        text-align: center;
    }
    
    .logo-container {
        top: 15px;
        left: 15px;
        gap: 10px;
    }
    
    .logo-text {
        font-size: 1rem;
    }
    
    /* 优化手机端导航栏 - 保持透明背景 */
    .main-header {
        padding: 10px !important;
        background-color: transparent !important;
        backdrop-filter: none !important;
        box-shadow: none !important;
    }
    
    /* 优化logo和导航按钮布局 */
    .logo-container {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    /* 调整导航按钮尺寸和间距 */
    .menu-btn {
        padding: 6px 10px;
        font-size: 0.9rem;
    }
    
    /* 隐藏桌面端的一些元素 */
    .logo-author, .logo-article {
        display: none;
    }
    
    /* 在小屏幕上显示更多空间 */
    .fullscreen {
        padding-top: 80px;
    }
    
    /* 用户评价模块在手机端的优化 */
    .testimonials-wrapper {
        height: auto !important;
        overflow-x: hidden !important; /* 自动滚动时通常隐藏手动滚动条，或者允许手动干预 */
        overflow-y: hidden !important;
        padding: 20px 0 !important;
        display: block !important;
    }
    
    .testimonials-grid-container {
        display: flex !important;
        flex-direction: row !important;
        gap: 15px !important;
        padding: 0 15px !important;
        width: max-content !important;
        animation: scrollLeft 80s linear infinite !important; /* 慢速自动滚动，从 60s 调整为 80s */
    }
    
    /* 鼠标悬停或触摸时暂停滚动 */
    .testimonials-wrapper:hover .testimonials-grid-container,
    .testimonials-wrapper:active .testimonials-grid-container {
        animation-play-state: paused !important;
    }
    
    .testimonial-row {
        display: flex !important;
        flex-direction: row !important;
        gap: 15px !important;
        width: auto !important;
    }

    /* 显示所有行（共9个卡片及其副本） */
    .testimonial-row:not(:first-child) {
        display: flex !important;
    }
    
    .testimonial-item {
        width: 280px !important;
        flex-shrink: 0 !important;
        margin: 0 !important;
        max-width: none !important;
    }
    
    @keyframes scrollLeft {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-50%); /* 滚动到一半，因为后半部分是副本 */
        }
    }
    
    .testimonial-text {
        font-size: 0.9rem;
    }
    
    .menu {
        top: 20px;
        right: 15px;
    }
    
    .horizontal-scroll-section {
        padding: 50px 0;
    }
    
    .to-bottom-btn {
        bottom: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
    }
}

/* 小屏手机 */
@media (max-width: 480px) {
    .main-title {
        font-size: 2.8rem;
    }
    
    .welcome-text {
        font-size: 1.5rem;
    }
    
    .photo-item {
        width: 320px;
        height: 220px;
    }
    
    .scroll-title {
        font-size: 1.5rem;
    }
    
    .center-text {
        font-size: 1.2rem;
        line-height: 1.6;
        word-break: keep-all; /* 防止单个汉字换行 */
        word-wrap: break-word;
        hyphens: auto;
    }
    
    /* 移动端文字换行优化 */
    .mobile-break {
        display: inline;
        white-space: nowrap;
    }
    
    .logo-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .logo-author, .logo-article {
        margin-left: 0;
    }
    
    .menu {
        flex-direction: column;
        gap: 8px;
    }
    
    #loading-message {
        font-size: 1.4rem;
    }
    
    .floating-text {
        font-size: 16px;
    }
    
    .footer-icons {
        gap: 10px;
    }
    
    .footer-icon {
        width: 24px;
        height: 24px;
    }
    
    footer {
        font-size: 0.7rem;
    }
    
    .to-bottom-btn {
        bottom: 15px;
        right: 15px;
        width: 35px;
        height: 35px;
    }
}

/* 超小屏设备 */
@media (max-width: 360px) {
    .main-title {
        font-size: 2.2rem;
    }
    
    .welcome-text {
        font-size: 1.2rem;
    }
    
    .photo-item {
        width: 280px;
        height: 200px;
    }
    
    .scroll-title {
        font-size: 1.3rem;
    }
    
    .center-text {
        font-size: 1rem;
    }
    
    .logo-text {
        font-size: 0.9rem;
    }
    
    .logo-author, .logo-article {
        font-size: 0.8rem;
    }
}

/* 横屏模式 */
@media (max-height: 500px) and (orientation: landscape) {
    .fullscreen {
        height: auto;
        min-height: 100vh;
        padding: 0;
    }
    
    .main-title {
        font-size: 4rem;
        margin: 20px 0;
    }
    
    .welcome-text {
        font-size: 1.8rem;
    }
}

/* 高分辨率设备 */
@media (min-width: 1600px) {
    .container {
        max-width: 1400px;
    }
    
    .main-title {
        font-size: 10rem;
    }
    
    .welcome-text {
        font-size: 4rem;
    }
    
    .photo-item {
        width: 700px;
        height: 450px;
    }
}

/* ========= 移动端体验优化 ========= */

/* 触摸目标优化 */
@media (max-width: 768px) {
    /* 增强触摸目标大小 */
    a, button, .footer-icon-link, .photo-item, .menu-btn, .logo-author, .logo-article {
        touch-action: manipulation;
        min-height: 44px;
        min-width: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    /* 防止移动端双击缩放 */
    * {
        -webkit-tap-highlight-color: transparent;
    }
    
    /* 优化横向滚动体验 */
    .horizontal-scroll-wrapper {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    
    .horizontal-scroll-wrapper::-webkit-scrollbar {
        display: none;
    }
    
    /* 优化触摸反馈 */
    .interactive-hover-button:active {
        transform: scale(0.95);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    /* 优化图片触摸效果 */
    .photo-item:active {
        transform: translateY(-2px);
    }
    
    /* 优化按钮触摸效果 */
    .menu-btn:active {
        transform: scale(0.95);
    }
    
    /* 优化打字机效果在小屏幕上的显示 */
    .cursor {
        height: 1.2em !important;
        width: 3px !important;
    }
    
    /* 优化悬浮文字在小屏幕上的显示 */
    .floating-text {
        font-size: 14px !important;
        padding: 8px 15px !important;
    }
}

/* 小屏幕设备优化 */
@media (max-width: 480px) {
    /* 优化顶部区域布局 */
    .main-header {
        padding: 10px !important;
    }
    
    /* 优化欢迎语容器 */
    #welcomeContainer {
        font-size: 16px !important;
        padding: 15px;
        text-align: center;
    }
    
    /* 优化横向滚动容器 */
    .scroll-content {
        padding: 0 10px !important;
        gap: 15px !important;
    }
    
    /* 优化中心文字区域间距 */
    #centerText {
        margin-top: 80px !important;
        margin-bottom: 80px !important;
        padding: 0 15px;
        line-height: 1.8 !important;
    }
    
    /* 优化底部图标布局 */
    .footer-icons {
        gap: 15px;
        padding: 10px;
    }
    
    /* 优化备案信息显示 */
    footer {
        padding: 20px 10px !important;
        line-height: 1.8;
    }
    
    /* 优化分割线显示 */
    .divider {
        display: block !important;
        margin: 5px auto !important;
        width: 50px;
        height: 1px;
        background: #ddd;
    }
    
    /* 优化底部按钮位置 */
    .to-bottom-btn {
        bottom: 20px !important;
        right: 20px !important;
        z-index: 999;
    }
}

/* 超小屏幕设备优化 */
@media (max-width: 360px) {
    /* 进一步优化文字大小 */
    #welcomeContainer {
        font-size: 14px !important;
    }
    
    #centerText {
        font-size: 1.1rem !important;
        margin-top: 50px !important;
        margin-bottom: 50px !important;
    }
    
    /* 优化滚动条显示 */
    .custom-scrollbar {
        width: 90% !important;
    }
    
    /* 优化图片尺寸 */
    .photo-item {
        min-width: 240px !important;
        height: 180px !important;
    }
    
    /* 优化底部图标大小 */
    .footer-icon {
        width: 28px !important;
        height: 28px !important;
    }
    
    /* 优化备案信息字体 */
    footer {
        font-size: 0.65rem !important;
    }
}

/* 横屏模式优化 */
@media (max-height: 500px) and (orientation: landscape) {
    /* 优化顶部区域高度 */
    .fullscreen {
        height: 100vh !important;
        min-height: 300px;
    }
    
    /* 优化欢迎语容器位置 */
    #welcomeContainer {
        transform: translateY(-20%) !important;
    }
    
    /* 优化横向滚动区 */
    .horizontal-scroll-section {
        margin-top: 10px;
    }
    
    .photo-item {
        height: 120px !important;
    }
    
    /* 优化中心文字区域 */
    #centerText {
        margin-top: 40px !important;
        margin-bottom: 40px !important;
    }
}

/* 高分辨率设备优化 */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    /* 确保图像在高DPI屏幕上清晰显示 */
    .photo-item img {
        image-rendering: -webkit-optimize-contrast;
    }
    
    /* 优化SVG图标显示 */
    .footer-icon {
        image-rendering: optimizeQuality;
    }
}

/* 性能优化 */
@media (max-width: 768px) {
    /* 减少动画复杂度 */
    .photo-item {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .photo-item:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    }
    
    /* 减少过渡效果复杂度 */
    .elegantly-styled-button {
        transition: all 0.3s ease;
    }
    
    /* 优化渲染性能 */
    * {
        will-change: auto;
    }
    
    .main-title, .welcome-text, .photo-item {
        will-change: transform;
    }
}
    </style>
</head>
<body>
    <!-- 加载动画区域 -->
    <div id="loading-ink">
        <!-- 加载内容容器 - 确保所有元素垂直居中排列 -->
        <div id="loading-content">
            <!-- Hello动画容器 - 放在最前面，确保最先显示 -->
            <div id="hello-animation-container"></div>
            
            <!-- 致敬文字 - 在动画之后显示 -->
            <div id="quote-text"></div>
            
            <!-- 加载进度条 -->
            <div id="loading-progress">
                <div id="loading-progress-bar"></div>
            </div>
            
            <!-- 跳过按钮 -->
            <div id="skip-loading" onclick="skipLoading()">
                等待和希望
            </div>
        </div>
    </div>
    
    <!-- 主页面内容 - 初始隐藏，等待加载完成后显示 -->
    <div id="page-content-wrapper">

<div class="page-content">
    <!-- 新年装饰：灯笼 -->
    <div class="lantern-box lantern-left">
        <div class="lantern-line"></div>
        <div class="lantern-top"></div>
        <div class="lantern">
            <span class="lantern-text">春</span>
        </div>
        <div class="lantern-bottom"></div>
        <div class="tassel"></div>
    </div>
    <div class="lantern-box lantern-right">
        <div class="lantern-line"></div>
        <div class="lantern-top"></div>
        <div class="lantern">
            <span class="lantern-text">节</span>
        </div>
        <div class="lantern-bottom"></div>
        <div class="tassel"></div>
    </div>

    <!-- 新年装饰：福字 -->
    <div class="fu-character fu-left" title="祝您新春快乐，万事如意！">
        <span>福</span>
    </div>
    <div class="fu-character fu-right" title="祝您岁岁平安，福星高照！">
        <span>福</span>
    </div>
    <!-- 页面主内容 -->
    <!-- 顶部白板 -->
    <link rel="preload" as="image" href="assets/images/he.webp">
    
    <!-- 顶部导航栏：包含左侧logo和右侧导航按钮 -->
<div class="main-header" style="position: fixed; top: 0; left: 0; right: 0; z-index: 101; display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background-color: transparent;">
    <!-- 左上角：logo + 联系作者 + 冠恩书屋 -->
    <div class="logo-container" aria-label="logo and contact" style="display: flex; align-items: center; gap: 15px;">
        <div class="logo" id="logoBox" title="">
            <div class="logo-img"><img src="assets/images/autumn-rose.webp" alt="logo" /></div>
           <!--  <div class="logo-text">冠恩先生</div>
            <div class="logo-tooltip" id="logoTooltip">虽不能至，心向往之</div>-->
        </div>

        <!-- 联系作者按钮 -->
        <div class="logo-author" id="contactLink" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
            联系冠恩
        </div>

        <!-- 独立文章按钮，样式与联系作者一致 -->
        <div class="logo-article" id="articleLink" role="button" tabindex="0">
            冠恩书屋
        </div>

        <!-- 文化分享按钮，样式与联系作者一致 -->
        <a href="pages/Resonance.html" class="logo-article" id="cultureLink" role="button" tabindex="0">
            文化分享
        </a>

        <!-- 艺术按钮 -->
        <a href="pages/culture_pastel.html" class="logo-article" id="artLink" role="button" tabindex="0">
            艺术
        </a>

         <!--建议作者按钮，样式与联系作者一致 -->
        <a href="pages/wenjuan.html" class="logo-article" id="suggestionLink" role="button" tabindex="0">
            建议作者
        </a>
    </div>

    <!-- 右侧导航菜单 -->
    <nav class="menu" aria-label="menu" style="display: flex; align-items: center; gap: 8px;">
<!--<button class="menu-btn" id="homeBtn">首页</button>-->
<button class="menu-btn" id="languageBtn">EN/中</button>
<!--<button class="menu-btn" id="paokuBtn">跳跳乐</button>-->
<button class="menu-btn" id="bookCrossingBtn">书籍漂流</button>
<button class="menu-btn" id="lotteryBtn">抽奖</button>
<!--<button class="menu-btn" id="monopolyBtn">鹏鹏大富翁</button>-->
<button class="menu-btn" id="loginBtn"><?php echo core_is_logged_in() ? '用户中心' : '登录'; ?></button>
</nav>
</div>

    <!-- 顶部全屏标题区域 -->
    <div class="fullscreen" id="topSection">
        <div class="title-container">
            <h1 class="main-title" id="mainTitle">新春快乐</h1>
            
            <div class="welcome-container" id="welcomeContainer">
                <div class="welcome-text" id="welcomeText"></div>
            </div>
        </div>

        <!-- 装饰"你好"气泡 -->
        <div id="decorativeHellos"></div>
    </div>

    <!-- 新增文字模块 -->
    <section class="quote-section">
        <div class="quote-container">
            <p class="highlight-quote" id="highlightQuote">生活远比戏剧更荒诞与沉重，但荒诞不是让我们绝望，而是让我们重新滋生勇气与信心。</p>
        </div>
    </section>

    <!-- 用户评价模块 -->
    <section class="bg-background py-6 relative">
        <div class="container z-10 mx-auto">
            <!-- 添加wrapper容器实现无缝循环滚动 -->
            <div class="testimonials-wrapper">
                <!-- 内容容器 - 将复制一份用于无缝滚动 -->
                <div class="testimonials-grid-container">
                    <!-- 第一行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">“耐心是应对所有状况的万能钥匙，人必须随一切共振，热衷于一切，同时又保持冷静与耐心。”</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">在绝望之巅</div>
                                    <div class="testimonial-role"></div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我们首先将是善良的，这一点最要紧，然后是正直的。然后——我们将彼此永不相忘</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">卡拉马佐夫兄弟</div>
                                    <div class="testimonial-role">陀思妥耶夫斯基</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">先前的事太好了，结果就长久不了，他想。现在我倒希望那是一场梦，希望我从来没钓到这条鱼，希望睁开眼看到自己独个儿躺在铺着报纸的床上。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">老人与海</div>
                                    <div class="testimonial-role">海明威</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 第二行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">我希望你狂热、勇敢、向往、渴求。愿你能够获得爱情，同时享受孤独。愿你如星辰般永恒、蚂蚁般果决、教堂中的疑问般思辨。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">我也不知道</div>
                                    <div class="testimonial-role">i don't know</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">在我的黑暗里,那虚浮的冥色我用一把迟疑的手杖慢慢摸索我总是暗暗设想,天堂应是座图书馆的模样。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">博尔赫斯</div>
                                    <div class="testimonial-role">Project Manager</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">西西弗的幸福就在于，他在重复而无望的生活中不断雕琢自己，从而找到了人生的意义。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">西西弗神话</div>
                                    <div class="testimonial-role">加缪</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 第三行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">他说他一直在研究我的灵魂，结果发现其中空虚无物。他说我实际上没有灵魂，没有丝毫人性，没有任何一条在人类灵魂中占神圣地位的道德原则，所有这些都与我格格不</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">局外人</div>
                                    <div class="testimonial-role">加缪</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我希望，大家无论通过什么方法，都能挣到足够的钱去旅行，去闲着，去思考世界的过去和未来，去看书做梦，去街角闲逛让思绪的钓线深深沉入街流之中</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">弗吉尼亚·伍尔夫</div>
                                    <div class="testimonial-role">Virginia Woolf</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我们所要做的事，应该一想到就做。因为人的想法是会变化的，有多少舌头、多少手、多少意外，就会有多少犹豫、多少迟延，那时候再空谈该做什么，只不过等于聊以自慰的长吁短叹，只能伤害自己的身体罢了。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">哈姆雷特</div>
                                    <div class="testimonial-role">威廉·莎士比亚</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 复制评价内容，实现无缝循环滚动 -->
                    <!-- 第一行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">“耐心是应对所有状况的万能钥匙，人必须随一切共振，热衷于一切，同时又保持冷静与耐心。”</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">在绝望之巅</div>
                                    <div class="testimonial-role"></div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我们首先将是善良的，这一点最要紧，然后是正直的。然后——我们将彼此永不相忘</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">卡拉马佐夫兄弟</div>
                                    <div class="testimonial-role">陀思妥耶夫斯基</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">先前的事太好了，结果就长久不了，他想。现在我倒希望那是一场梦，希望我从来没钓到这条鱼，希望睁开眼看到自己独个儿躺在铺着报纸的床上。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">老人与海</div>
                                    <div class="testimonial-role">海明威</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 第二行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">我希望你狂热、勇敢、向往、渴求。愿你能够获得爱情，同时享受孤独。愿你如星辰般永恒、蚂蚁般果决、教堂中的疑问般思辨。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">我也不知道</div>
                                    <div class="testimonial-role">i don't know</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">在我的黑暗里,那虚浮的冥色我用一把迟疑的手杖慢慢摸索我总是暗暗设想,天堂应是座图书馆的模样。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">博尔赫斯</div>
                                    <div class="testimonial-role">Project Manager</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">西西弗的幸福就在于，他在重复而无望的生活中不断雕琢自己，从而找到了人生的意义。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">西西弗神话</div>
                                    <div class="testimonial-role">加缪</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 第三行 -->
                    <div class="testimonial-row">
                        <div class="testimonial-item">
                            <p class="testimonial-text">他说他一直在研究我的灵魂，结果发现其中空虚无物。他说我实际上没有灵魂，没有丝毫人性，没有任何一条在人类灵魂中占神圣地位的道德原则，所有这些都与我格格不</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">局外人</div>
                                    <div class="testimonial-role">加缪</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我希望，大家无论通过什么方法，都能挣到足够的钱去旅行，去闲着，去思考世界的过去和未来，去看书做梦，去街角闲逛让思绪的钓线深深沉入街流之中</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">弗吉尼亚·伍尔夫</div>
                                    <div class="testimonial-role">Virginia Woolf</div>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-item">
                            <p class="testimonial-text">我们所要做的事，应该一想到就做。因为人的想法是会变化的，有多少舌头、多少手、多少意外，就会有多少犹豫、多少迟延，那时候再空谈该做什么，只不过等于聊以自慰的长吁短叹，只能伤害自己的身体罢了。</p>
                            <div class="testimonial-author">
                                <div class="testimonial-info">
                                    <div class="testimonial-name">哈姆雷特</div>
                                    <div class="testimonial-role">威廉·莎士比亚</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- InteractiveHoverButton组件容器 -->
        <div class="testimonial-button-container" style="display: flex; justify-content: center; margin-top: 40px;margin-bottom: 20px">
            <div id="interactiveHoverButton"></div>
        </div>
    </section>

    <!-- React Components -->
    <script src="assets/js/react-components.js"></script>
    
    <!-- 横向滚动展示区 -->
    <section class="horizontal-scroll-section">
        <div class="scroll-container">
            <h2 class="scroll-title" id="galleryTitle">对于伟大的摄影作品，重要的是情深，而不是景深</h2>

            <div class="horizontal-scroll" id="horizontalScroll">
                <div class="scroll-content">
                    <div class="photo-item">
                        <img src="assets/images/怎样.webp" alt="山川"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/23542342.webp" alt="山川"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/不知道.webp" alt="山脉"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/不过我知道.webp" alt="日出"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/当下每一刻.webp" alt="河流"/>
                        <div class="photo-caption">摄于 - 中国上海</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/123124123123.webp" alt="山川"/>
                        <div class="photo-caption">摄于 - 中国湖北-宜昌</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/鸟儿.webp" alt="日落"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/rose.webp" alt="山川"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/Y4W.webp" alt="春天"/>
                        <div class="photo-caption">摄于 - 中国北京</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/花.webp" alt="夏天"/>
                        <div class="photo-caption">摄于 - 中国湖北-宜昌</div>
                    </div>
                    <div class="photo-item">
                        <img src="assets/images/3.webp" alt="冬天"/>
                        <div class="photo-caption">摄于 - 中国四川</div>
                    </div>
                </div>
            </div>

            <!-- 自定义滚动条 -->
            <div class="custom-scrollbar" id="customScrollbar">
                <div class="scroll-track">
                    <div class="scroll-thumb" id="scrollThumb"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========= 中心文字展示 ========= -->
    <section class="center-text-section">
        <div class="center-text-container">
            <p class="center-text" id="centerText" 
                style="margin-top:150px;margin-bottom:150px;line-height:1.8;text-align:center;">
                我想起现实世界是多么广阔<br>
                充满了纷繁的希望与恐惧，刺激与兴奋
                <br>就等那些勇敢的人们踏入这片天地
                <br>在生活的危险之中寻找真谛
                <span class="quote-author">----夏洛蒂·勃朗特</span>
            </p>
        </div>
    </section>

    <!-- ========== 底部图标导航 ========== -->
    <div class="footer-icons">
        <!-- 抖音 -->
        <a href="https://v.douyin.com/KbjT0h9jDMI/" target="_blank" aria-label="抖音" class="footer-icon-link">
            <svg class="footer-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                <path fill="#000000" d="M30.818 5.455A8.727 8.727 0 0 0 22.09 14.18v17.46a5.455 5.455 0 1 1-5.455-5.454v-6.546a12 12 0 1 0 12 12V17.09c2.177 1.32 4.748 2.09 7.455 2.09V12.73c-2.91 0-5.455-2.545-5.455-5.455z"/>
            </svg>
        </a>

        <!-- GitHub -->
        <a href="https://github.com/RemarkableHunter" target="_blank" aria-label="GitHub" class="footer-icon-link">
            <svg class="footer-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path fill="#000000" d="M12 .5C5.648.5.5 5.648.5 12c0 5.096 3.292 9.408 7.865 10.942.574.106.785-.25.785-.555 0-.273-.01-1.165-.015-2.116-3.198.695-3.872-1.54-3.872-1.54-.523-1.33-1.278-1.686-1.278-1.686-1.045-.715.080-.7.080-.7 1.157.082 1.765 1.188 1.765 1.188 1.028 1.762 2.696 1.253 3.352.958.103-.745.402-1.253.730-1.54-2.552-.29-5.235-1.276-5.235-5.680 0-1.255.452-2.28 1.190-3.084-.12-.29-.515-1.454.115-3.033 0 0 .965-.309 3.162 1.18a11.033 11.033 0 0 1 2.88-.388c.975.004 1.96.132 2.88.388 2.197-1.489 3.160-1.18 3.160-1.18.63 1.579.236 2.743.116 3.033.740.804 1.188 1.83 1.188 3.084 0 4.415-2.686 5.387-5.247 5.673.413.355.782 1.06.782 2.137 0 1.54-.014 2.783-.014 3.162 0 .307.21.665.790.552C20.210 21.405 23.5 17.094 23.5 12c0-6.352-5.148-11.5-11.5-11.5z"/>
            </svg>
        </a>

        <!-- 微信 -->
        <div class="footer-icon-link wechat-container" id="wechat-icon" aria-label="微信">
            <svg class="footer-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                <path fill="#07C160" d="M19.5 6C10.387 6 3 12.61 3 20.75c0 4.124 2.313 7.82 6.125 10.406L8 38l7.219-3.688C16.266 35.156 17.867 35.5 19.5 35.5c9.113 0 16.5-6.61 16.5-14.75S28.613 6 19.5 6zm18.188 8.5c-1.098 0-2.18.12-3.22.34 1.808 2.28 2.906 5.02 2.906 8.03 0 7.565-6.95 13.75-15.5 13.75-1.072 0-2.117-.103-3.125-.297 2.794 3.110 7.48 5.177 12.75 5.177 1.63 0 3.23-.26 4.75-.75L44 42l-1.344-5.5C45.688 33.55 48 29.9 48 25.75 48 17.61 41.613 11 32.5 11z"/>
            </svg>
            <div class="wechat-popup" id="wechat-popup">
                Mr_Guanen
                <div class="wechat-tooltip-arrow"></div>
            </div>
        </div>

        <!-- Facebook -->
        <a href="https://www.facebook.com/profile.php?id=100052982301970" target="_blank" aria-label="Facebook" class="footer-icon-link">
            <svg class="footer-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path fill="#1877F2" d="M22.675 0h-21.35C.597 0 0 .597 0 1.325v21.351C0 23.403.597 24 1.325 24H12.82V14.706h-3.12v-3.62h3.12V8.414c0-3.1 1.894-4.788 4.659-4.788 1.325 0 2.463.099 2.794.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.62h-3.12V24h6.116C23.403 24 24 23.403 24 22.676V1.325C24 .597 23.403 0 22.675 0z"/>
            </svg>
        </a>

        <!-- Gmail -->
        <a href="mailto:Mr.Guanen@gmail.com" aria-label="Gmail" class="footer-icon-link"> 
            <svg class="footer-icon" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                <path d="M128 128h768v768H128z" fill="#ECEFF1"></path>
                <path d="M512 592.768L896 896V297.344z" fill="#CFD8DC"></path>
                <path d="M928 128h-32L512 431.232 128 128H96C43.008 128 0 171.008 0 224v576c0 52.992 43.008 96 96 96h32V297.344l384 295.36L896 297.28V896h32c52.992 0 96-43.008 96-96V224c0-52.992-43.008-96-96-96z" fill="#F44336"></path>
            </svg>
        </a>

        <!-- Instagram -->
        <a href="https://www.instagram.com" target="_blank" aria-label="Instagram" class="footer-icon-link">
            <svg class="footer-icon" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                <path d="M650.3 512c0-38.2-13.5-70.8-40.5-97.8s-59.6-40.5-97.8-40.5-70.8 13.5-97.8 40.5-40.5 59.6-40.5 97.8c0 38.2 13.5 70.8 40.5 97.8s59.6 40.5 97.8 40.5 70.8-13.5 97.8-40.5 40.5-59.6 40.5-97.8z m74.6 0c0 59.1-20.7 109.3-62.1 150.8-41.4 41.4-91.7 62.1-150.8 62.1-59.1 0-109.3-20.7-150.8-62.1-41.4-41.4-62.1-91.7-62.1-150.8 0-59.1 20.7-109.3 62.1-150.8 41.4-41.4 91.7-62.1 150.8-62.1 59.1 0 109.3 20.7 150.8 62.1 41.4 41.5 62.1 91.7 62.1 150.8z m58.4-221.6c0 13.7-4.9 25.4-14.6 35.1-9.7 9.7-21.4 14.6-35.1 14.6-13.7 0-25.4-4.9-35.1-14.6-9.7-9.7-14.6-21.4-14.6-35.1 0-13.7 4.9-25.4 14.6-35.1 9.7-9.7 21.4-14.6 35.1-14.6 13.7 0 25.4 4.9 35.1 14.6 9.7 9.8 14.6 21.5 14.6 35.1zM512 171.6c-2.5 0-16.3-0.1-41.3-0.3-25-0.2-44-0.2-57 0s-30.4 0.7-52.1 1.6c-21.8 0.9-40.3 2.7-55.7 5.4-15.3 2.7-28.2 6-38.6 10-18 7.2-33.9 17.7-47.6 31.3-13.7 13.7-24.1 29.5-31.3 47.6-4 10.4-7.3 23.3-10 38.6s-4.5 33.9-5.4 55.7c-0.9 21.8-1.4 39.2-1.6 52.1-0.2 13-0.2 32 0 57s0.3 38.8 0.3 41.3-0.1 16.3-0.3 41.3c-0.2 25-0.2 44 0 57s0.7 30.3 1.6 52.1c0.9 21.8 2.7 40.4 5.4 55.7 2.7 15.3 6 28.2 10 38.6 7.2 18 17.7 33.9 31.3 47.6 13.7 13.7 29.5 24.1 47.6 31.3 10.4 4 23.3 7.3 38.6 10s33.9 4.5 55.7 5.4c21.8 0.9 39.2 1.4 52.1 1.6 13 0.2 32 0.2 57 0s38.8-0.3 41.3-0.3 16.3 0.1 41.3 0.3c25 0.2 44 0.2 57 0s30.4-0.7 52.1-1.6c21.8-0.9 40.4-2.7 55.7-5.4 15.3-2.7 28.2-6 38.6-10 18-7.2 33.9-17.7 47.5-31.3 13.7-13.7 24.1-29.5 31.3-47.6 4-10.4 7.3-23.3 10-38.6s4.5-33.9 5.4-55.7c0.9-21.8 1.4-39.2 1.6-52.1 0.2-13 0.2-32 0-57s-0.3-38.8-0.3-41.3 0.1-16.3 0.3-41.3c0.2-25 0.2-44 0-57s-0.7-30.4-1.6-52.1c-0.9-21.8-2.7-40.3-5.4-55.7-2.7-15.3-6-28.2-10-38.6-7.2-18-17.7-33.9-31.3-47.6-13.7-13.7-29.5-24.1-47.5-31.3-10.4-4-23.3-7.3-38.6-10s-33.9-4.5-55.7-5.4c-21.8-0.9-39.2-1.4-52.1-1.6-13-0.2-32-0.2-57 0s-38.8 0.3-41.3 0.3zM927 512c0 82.5-0.9 139.6-2.7 171.3-3.6 74.9-25.9 132.9-67 174-41.1 41.1-99.1 63.4-174 67-31.7 1.8-88.8 2.7-171.3 2.7s-139.6-0.9-171.3-2.7c-74.9-3.6-132.9-25.9-174-67-41.1-41.1-63.4-99.1-67-174C97.9 651.6 97 594.5 97 512c0-82.5 0.9-139.6 2.7-171.3 3.6-74.9 25.9-132.9 67-174 41.1-41.1 99.1-63.4 174-67C372.4 97.9 429.5 97 512 97s139.6 0.9 171.3 2.7c74.9 3.6 132.9 25.9 174 67 41.1 41.1 63.4 99.1 67 174 1.8 31.7 2.7 88.8 2.7 171.3z m0 0" fill="#E20C35"></path>
            </svg>
        </a>
    </div>

    <!-- 原备案信息 footer -->
    <footer role="contentinfo" aria-label="网站备案信息" id="pageFooter">  
        <!-- 网站访问总量显示区域 -->
        <span class="beian-link">本站访问总量：<span id="visit-count">加载中...</span>次</span>
        
        <span class="divider"></span>
        
        <!-- 公网安备 -->
        <a href="https://beian.mps.gov.cn/#/query/webSearch?code=42050002420851"
            rel="noreferrer" target="_blank" class="beian-link">
            <img src="https://www.beian.gov.cn/img/new/gongan.png"
                alt="公安备案图标" class="beian-icon">
            鄂公网安备42050002420851号
        </a>  

        <span class="divider"></span>  

        <!-- ICP备案 -->
        <span id="icpText">互联网ICP备案：</span>
        <a href="https://beian.miit.gov.cn/" target="_blank" class="beian-link">
            鄂ICP备2025145045号-1
        </a>  

        <span class="divider"></span>  

        <!-- 年份版权 -->
        © <span id="year-range"></span> <span id="footerAuthor">冠恩</span>  

        <span class="divider"></span>  
        <span id="copyrightText">版权所有，任何形式转载请联系作者</span>  

      <span class="divider"></span>  
<a href="pages/agreement.html" target="_blank" class="beian-link" id="agreementLink">用户协议</a>

<span class="divider"></span>  
<a href="pages/wenjuan.html" target="_blank" class="beian-link" id="footerQuestionnaireLink">问卷调查</a>

<span class="divider"></span>  
<a href="pages/privacy.html" target="_blank" class="beian-link" id="privacyLink">隐私政策</a>

<span class="divider"></span>  
<span class="beian-link">本网站已正常运行 <span id="running-days">0</span> 天</span>

<span class="divider"></span>  

<!-- 怀念豆豆 - 已合并至页脚主行 -->
<span class="doudou-trigger" id="doudouBtn" title="点击一下，送上一份思念">
  秋葵 丨 怀念豆豆 <span class="doudou-paw">🐾</span>
    <div class="doudou-popover" id="doudouPopover">
        <img src="assets/images/doudou.webp" alt="豆豆">
        <div class="doudou-popover-arrow"></div>
    </div>
</span>

</footer>
    
    <!-- 页面底部按钮 -->
    <div class="to-bottom-btn" id="toBottomBtnFixed" title="跳转到页面底部">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script src="assets/js/index-logic.js"></script>
<!-- 添加hello动画功能 -->
<!-- 添加logo旋转文本弧效果 -->
<script src="assets/js/logo-animation.js"></script>

<!-- 页面跳转加载动画 -->
    <script src="assets/js/page-loading.js"></script>

</div>
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

<script src="assets/js/chat.js"></script>
<script src="assets/js/fireworks.js"></script>
</body>
</html>
    