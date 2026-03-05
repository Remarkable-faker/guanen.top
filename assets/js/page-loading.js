/**
 * 页面跳转加载动画组件
 * 功能：在用户点击跳转链接时显示加载动画
 * 使用：在需要跳转的链接上添加 data-page-loading="true" 属性
 */

// 页面跳转加载管理器
class PageLoadingManager {
    constructor() {
        this.isLoading = false;
        this.loadingContainer = null;
        this.init();
    }
    
    /**
     * 初始化加载管理器
     */
    init() {
        // 创建加载动画容器
        this.createLoadingContainer();
        
        // 绑定页面跳转事件
        this.bindNavigationEvents();
        
        console.log('页面跳转加载动画管理器已初始化');
    }
    
    /**
     * 创建加载动画容器
     */
    createLoadingContainer() {
        // 检查是否已存在容器
        this.loadingContainer = document.getElementById('page-loading-container');
        
        if (!this.loadingContainer) {
            this.loadingContainer = document.createElement('div');
            this.loadingContainer.id = 'page-loading-container';
            this.loadingContainer.innerHTML = this.getLoadingHTML();
            document.body.appendChild(this.loadingContainer);
        }
    }
    
    /**
     * 获取加载动画HTML
     */
    getLoadingHTML() {
        return `
            <div class="page-loading-overlay">
                <div class="page-loading-content">
                    <!-- 加载文字动画 -->
                    <div class="loading-text-container">
                        <span class="loading-text" id="page-loading-text">等待和希望</span>
                    </div>
                    
                    <!-- 加载进度条 -->
                    <div class="loading-progress-container">
                        <div class="loading-progress-bar" id="page-loading-progress"></div>
                    </div>
                    
                    <!-- 加载图标 -->
                    <div class="loading-icon">
                        <div class="loading-spinner"></div>
                    </div>
                    
                    <!-- 跳过按钮 -->
                    <button class="skip-loading-btn" id="skip-page-loading">
                        跳过等待
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * 绑定页面跳转事件
     */
    bindNavigationEvents() {
        // 监听所有链接点击事件
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a[data-page-loading="true"], button[data-page-loading="true"]');
            
            if (target && !this.isLoading) {
                e.preventDefault();
                const targetUrl = target.href || target.getAttribute('data-href');
                this.showLoading(targetUrl, target.getAttribute('data-loading-message'));
            }
        });
        
        // 绑定跳过按钮事件
        document.addEventListener('click', (e) => {
            if (e.target.id === 'skip-page-loading') {
                this.skipLoading();
            }
        });
        
        // 监听浏览器前进后退事件
        window.addEventListener('popstate', () => {
            this.hideLoading();
        });
    }
    
    /**
     * 显示加载动画
     * @param {string} targetUrl - 目标URL
     * @param {string} message - 加载消息
     */
    showLoading(targetUrl, message = "等待和希望") {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        // 更新加载消息
        const loadingText = document.getElementById('page-loading-text');
        if (loadingText) {
            loadingText.textContent = message;
        }
        
        // 显示加载动画
        this.loadingContainer.style.display = 'block';
        
        // 开始文字闪烁动画
        this.startTextAnimation();
        
        // 开始进度条动画
        this.startProgressAnimation();
        
        // 如果提供了目标URL，延迟跳转
        if (targetUrl) {
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 2000); // 2秒后跳转
        }
        
        console.log('页面跳转加载动画已显示，目标URL:', targetUrl);
    }
    
    /**
     * 开始文字闪烁动画
     */
    startTextAnimation() {
        const loadingText = document.getElementById('page-loading-text');
        if (!loadingText) return;
        
        // 移除之前的动画类
        loadingText.classList.remove('text-shimmer');
        
        // 强制重绘
        void loadingText.offsetWidth;
        
        // 添加动画类
        loadingText.classList.add('text-shimmer');
    }
    
    /**
     * 开始进度条动画
     */
    startProgressAnimation() {
        const progressBar = document.getElementById('page-loading-progress');
        if (!progressBar) return;
        
        // 重置进度条
        progressBar.style.width = '0%';
        
        // 开始动画
        setTimeout(() => {
            progressBar.style.width = '100%';
            progressBar.style.transition = 'width 1.8s ease-in-out';
        }, 100);
    }
    
    /**
     * 隐藏加载动画
     */
    hideLoading() {
        this.isLoading = false;
        
        if (this.loadingContainer) {
            this.loadingContainer.style.display = 'none';
        }
        
        console.log('页面跳转加载动画已隐藏');
    }
    
    /**
     * 跳过加载动画
     */
    skipLoading() {
        if (this.isLoading) {
            this.hideLoading();
            console.log('用户跳过了加载动画');
        }
    }
    
    /**
     * 手动显示加载动画
     * @param {string} message - 加载消息
     */
    show(message = "等待和希望") {
        this.showLoading(null, message);
    }
    
    /**
     * 手动隐藏加载动画
     */
    hide() {
        this.hideLoading();
    }
}

// 创建全局页面加载管理器实例
window.PageLoadingManager = new PageLoadingManager();

// 页面加载完成后初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.PageLoadingManager.init();
    });
} else {
    window.PageLoadingManager.init();
}