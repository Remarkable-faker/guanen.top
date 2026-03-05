
// 定义cn函数，用于合并CSS类名
window.cn = function(...args) {
    return args.filter(Boolean).join(' ');
};

const Utils = {
    /**
     * 显示一个短暂的 Toast 消息
     * @param {string} message - 要显示的消息
     * @param {string} type - 消息类型 ('success' 或 'error')
     */
    toast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.textContent = message;
        
        // 基本样式
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '8px';
        toast.style.color = '#fff';
        toast.style.zIndex = '9999';
        toast.style.fontSize = '14px';
        toast.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
        toast.style.transition = 'opacity 0.3s, top 0.3s';
        
        // 根据类型设置颜色
        if (type === 'success') {
            toast.style.backgroundColor = '#10B981'; // 柔和翡翠绿
        } else {
            toast.style.backgroundColor = '#EF4444'; // 柔和珊瑚红
        }

        document.body.appendChild(toast);

        // 动画效果
        setTimeout(() => {
            toast.style.top = '40px';
            toast.style.opacity = '1';
        }, 10);

        // 3秒后自动移除
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.top = '20px';
            toast.addEventListener('transitionend', () => {
                toast.remove();
            });
        }, 3000);
    },

    /**
     * 通用异步 POST 请求函数
     * @param {string} url - 请求的 URL
     * @param {object} data - 发送的数据
     * @param {HTMLElement|null} btnElement - 触发操作的按钮元素
     * @returns {Promise<object|null>}
     */
    async post(url, data, btnElement = null) {
        // 1. 开启 Loading
        if (btnElement) {
            btnElement.classList.add('btn-loading');
            // 如果按钮是 <button>，禁用它
            if (btnElement.tagName === 'BUTTON') {
                btnElement.disabled = true;
            }
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            // 2. 根据后端 api_helpers.php 的结构处理结果
            if (!result.success) {
                throw new Error(result.msg || '请求失败');
            }
            
            // 成功时显示提示
            if (result.msg) {
                this.toast(result.msg, 'success');
            }

            return result;
        } catch (error) {
            console.error('Request Error:', error);
            // 调用自定义的 Toast 弹窗
            this.toast(error.message, 'error');
            return null;
        } finally {
            // 3. 关闭 Loading
            if (btnElement) {
                btnElement.classList.remove('btn-loading');
                if (btnElement.tagName === 'BUTTON') {
                    btnElement.disabled = false;
                }
            }
        }
    }
};
