// 建议反馈系统逻辑
document.addEventListener('DOMContentLoaded', function() {
    // ========== 自动获取用户信息 ==========
    async function checkLoginStatus() {
        try {
            // 使用统一的 init 接口获取登录状态
            const response = await fetch('/api/suggestion_api.php?action=init', { credentials: 'include' });
            if (!response.ok) return;
            const data = await response.json();
            
            if (data.success && data.is_logged_in && data.username) {
                const username = data.username;
                console.log('检测到登录用户:', username);
                
                // 更新建议表单中的昵称
                const nicknameInput = document.getElementById('nickname');
                if (nicknameInput && !nicknameInput.value) {
                    nicknameInput.value = username;
                }
            }
        } catch (error) {
            console.error('获取用户信息失败:', error);
        }
    }
    
    checkLoginStatus();

    // 字符计数器
    const suggestionTextarea = document.getElementById('suggestion');
    const charCount = document.getElementById('charCount');
    
    if (suggestionTextarea && charCount) {
        suggestionTextarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCount.textContent = currentLength;
            
            // 如果接近字符限制，改变颜色
            if (currentLength > 900) {
                charCount.style.color = '#e74c3c';
            } else if (currentLength > 700) {
                charCount.style.color = '#f39c12';
            } else {
                charCount.style.color = '#7f8c8d';
            }
        });
    }
    
    // 表单提交处理
    const suggestionForm = document.getElementById('suggestionForm');
    const thankYouMessage = document.getElementById('thankYouMessage');
    const newSuggestionBtn = document.getElementById('newSuggestionBtn');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const successMessage = document.getElementById('successMessage');
    const successText = document.getElementById('successText');
    
    if (suggestionForm) {
        // 隐藏错误和成功信息
        if (errorMessage) errorMessage.classList.remove('show');
        if (successMessage) successMessage.classList.remove('show');
        
        suggestionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // 获取表单数据
            const nickname = document.getElementById('nickname').value;
            const email = document.getElementById('email').value;
            const suggestion = document.getElementById('suggestion').value;
            
            // 验证邮箱格式
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('请输入有效的邮箱地址');
                return;
            }
            
            // 验证建议内容长度
            if (suggestion.length < 5) {
                showError('建议内容至少需要5个字符');
                return;
            }
            
            // 禁用提交按钮并显示加载状态
            if (submitBtn) submitBtn.disabled = true;
            if (submitText) submitText.textContent = '提交中...';
            if (loadingSpinner) loadingSpinner.style.display = 'inline-block';
            if (errorMessage) errorMessage.classList.remove('show');
            if (successMessage) successMessage.classList.remove('show');
            
            try {
                console.log('准备提交数据:', { nickname, email });
                
                // 使用新的API地址（根目录绝对路径）
                const response = await fetch('/api/suggestion_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        nickname: nickname,
                        email: email,
                        suggestion: suggestion
                    })
                });
                
                console.log('API响应状态:', response.status, response.statusText);
                
                console.log('API响应状态:', response.status, response.statusText);
                
                // 获取响应文本
                const responseText = await response.text();
                console.log('原始响应文本:', responseText);
                
                if (!responseText) {
                    throw new Error('服务器返回空响应');
                }
                
                // 解析JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    throw new Error('服务器返回的数据格式不正确');
                }
                
                console.log('解析后的响应:', result);
                
                if (result.success) {
                    // 提交成功
                    console.log('✅ 建议提交成功，ID:', result.suggestionId);
                    
                    // 显示成功
                    showSuccess('提交成功！感谢您的宝贵建议。');
                    
                    // 重置表单
                    setTimeout(() => {
                        suggestionForm.reset();
                        if (charCount) {
                            charCount.textContent = '0';
                            charCount.style.color = '#7f8c8d';
                        }
                        
                        // 清除草稿
                        localStorage.removeItem('suggestionDraft');
                        
                        // 显示感谢页面
                        setTimeout(() => {
                            const formContainer = document.querySelector('.form-container');
                            if (formContainer) {
                                formContainer.style.opacity = '0.5';
                                setTimeout(() => {
                                    formContainer.style.display = 'none';
                                    if (thankYouMessage) thankYouMessage.classList.add('show');
                                }, 300);
                            }
                        }, 1000);
                    }, 500);
                    
                } else {
                    // 服务器返回错误
                    console.error('API错误:', result.message);
                    showError(result.message || '提交失败');
                }
                
            } catch (error) {
                console.error('提交错误:', error);
                showError('提交时出错: ' + error.message);
            } finally {
                // 恢复提交按钮
                if (submitBtn) submitBtn.disabled = false;
                if (submitText) submitText.textContent = '提交建议';
                if (loadingSpinner) loadingSpinner.style.display = 'none';
            }
        });
    }
    
    // 显示错误信息
    function showError(message) {
        if (errorText && errorMessage) {
            errorText.textContent = message;
            errorMessage.classList.add('show');
            if (successMessage) successMessage.classList.remove('show');
            
            // 滚动到错误信息
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // 8秒后自动隐藏错误信息
            setTimeout(() => {
                errorMessage.classList.remove('show');
            }, 8000);
        }
    }
    
    // 显示成功信息
    function showSuccess(message) {
        if (successText && successMessage) {
            successText.textContent = message;
            successMessage.classList.add('show');
            if (errorMessage) errorMessage.classList.remove('show');
            
            // 滚动到成功信息
            successMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
    
    // 提交新建议按钮
    if (newSuggestionBtn) {
        newSuggestionBtn.addEventListener('click', function() {
            if (thankYouMessage) thankYouMessage.classList.remove('show');
            const formContainer = document.querySelector('.form-container');
            if (formContainer) {
                formContainer.style.display = 'block';
                
                // 添加一个小延迟，让显示动画更平滑
                setTimeout(() => {
                    formContainer.style.opacity = '1';
                    formContainer.style.animation = 'fadeIn 0.5s ease';
                }, 50);
            }
            
            // 重置所有状态
            if (errorMessage) errorMessage.classList.remove('show');
            if (successMessage) successMessage.classList.remove('show');
        });
    }
    
    // 页面加载时的简单动画
    const header = document.querySelector('header');
    const formContainer = document.querySelector('.form-container');
    if (header) header.style.animation = 'fadeIn 1s ease';
    if (formContainer) formContainer.style.animation = 'fadeIn 1.2s ease';
    
    // 表单自动保存草稿功能
    let saveTimer;
    if (suggestionForm) {
        suggestionForm.addEventListener('input', function() {
            // 清除之前的定时器
            clearTimeout(saveTimer);
            
            // 设置新的定时器，1秒后保存
            saveTimer = setTimeout(function() {
                const nickname = document.getElementById('nickname').value;
                const email = document.getElementById('email').value;
                const suggestion = document.getElementById('suggestion').value;
                
                if (nickname || email || suggestion) {
                    const draft = {
                        nickname: nickname,
                        email: email,
                        suggestion: suggestion,
                        savedAt: new Date().toISOString()
                    };
                    
                    localStorage.setItem('suggestionDraft', JSON.stringify(draft));
                    console.log('草稿已自动保存');
                }
            }, 1000);
        });
        
        // 恢复草稿
        try {
            const savedDraft = localStorage.getItem('suggestionDraft');
            if (savedDraft) {
                const draft = JSON.parse(savedDraft);
                
                // 检查草稿是否在今天保存的（不超过24小时）
                const savedTime = new Date(draft.savedAt);
                const now = new Date();
                const hoursDiff = (now - savedTime) / (1000 * 60 * 60);
                
                if (hoursDiff < 24) {
                    if (document.getElementById('nickname')) document.getElementById('nickname').value = draft.nickname || '';
                    if (document.getElementById('email')) document.getElementById('email').value = draft.email || '';
                    if (document.getElementById('suggestion')) document.getElementById('suggestion').value = draft.suggestion || '';
                    
                    if (charCount && draft.suggestion) {
                        charCount.textContent = draft.suggestion.length;
                    }
                    console.log('已恢复草稿');
                }
            }
        } catch (e) {
            console.error('恢复草稿失败:', e);
        }
    }
});
