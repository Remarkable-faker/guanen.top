document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // 阻止表单默认的同步提交

            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            
            // 从表单中获取数据
            const formData = Object.fromEntries(new FormData(form));

            // 调用封装好的异步 post 方法
            // 注意：这里的 API 地址是 /api/admin_actions.php，我稍后会创建它
            const res = await Utils.post('/api/admin_actions.php?action=login', formData, btn);

            // 如果请求成功且后端返回 success: true
            if (res && res.success) {
                // 延迟一小段时间，让用户看到成功提示
                setTimeout(() => {
                    // 跳转到后台主页
                    window.location.href = 'admin_dashboard.php';
                }, 800);
            }
        });
    }
});
