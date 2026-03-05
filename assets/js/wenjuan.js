document.addEventListener('DOMContentLoaded', function() {
    // 标签页切换功能
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // ========== 自动获取用户信息 ==========
    async function checkLoginStatus() {
        try {
            // 使用统一的 init 接口获取登录状态
            const response = await fetch('/api/wenjuan_api.php?action=init', { credentials: 'include' });
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
                
                // 更新签名处的名称
                const supportSignature = document.getElementById('support-signature-name');
                if (supportSignature) {
                    supportSignature.textContent = username;
                }
                
                const thankYouName = document.getElementById('thank-you-name');
                if (thankYouName) {
                    thankYouName.textContent = username;
                }
            }
        } catch (error) {
            console.error('获取用户信息失败:', error);
        }
    }
    
    checkLoginStatus();

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.getAttribute('data-tab');
            
            // 更新活动标签
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // 显示对应内容
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${targetTab}-tab`) {
                    content.classList.add('active');
                }
            });
            
            // 如果切换到评分页面，焦点放在输入框
            if (targetTab === 'rating') {
                setTimeout(() => {
                    document.getElementById('scoreInput').focus();
                }, 100);
            }
        });
    });
    
    // ========== 建议功能 ==========
    const suggestionTextarea = document.getElementById('suggestion');
    const charCount = document.getElementById('charCount');
    const suggestionForm = document.getElementById('suggestionForm');
    const thankYouMessage = document.getElementById('thankYouMessage');
    const newSuggestionBtn = document.getElementById('newSuggestionBtn');
    const suggestionSubmitBtn = document.getElementById('suggestionSubmitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const successMessage = document.getElementById('successMessage');
    const successText = document.getElementById('successText');
    
    // 字符计数器
    if (suggestionTextarea) {
        suggestionTextarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCount.textContent = currentLength;
            
            if (currentLength > 900) {
                charCount.style.color = '#e74c3c';
            } else if (currentLength > 700) {
                charCount.style.color = '#f39c12';
            } else {
                charCount.style.color = '#7f8c8d';
            }
        });
    }
    
    // 隐藏错误和成功信息
    if (errorMessage) errorMessage.classList.remove('show');
    if (successMessage) successMessage.classList.remove('show');
    
    // 表单提交处理
    if (suggestionForm) {
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
            suggestionSubmitBtn.disabled = true;
            submitText.textContent = '提交中...';
            loadingSpinner.style.display = 'inline-block';
            errorMessage.classList.remove('show');
            successMessage.classList.remove('show');
            
            // 调用真实API提交
            try {
                const response = await fetch('/api/wenjuan_api.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: JSON.stringify({
                        nickname: nickname,
                        email: email,
                        suggestion: suggestion
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result.message || '提交成功！感谢您的宝贵建议。');
                } else {
                    showError(result.message || '提交失败，请稍后再试');
                    return;
                }
                
                // 重置表单
                setTimeout(() => {
                    suggestionForm.reset();
                    charCount.textContent = '0';
                    charCount.style.color = '#7f8c8d';
                    
                    // 显示感谢页面
                    setTimeout(() => {
                        suggestionForm.style.opacity = '0.5';
                        setTimeout(() => {
                            suggestionForm.style.display = 'none';
                            thankYouMessage.classList.add('show');
                        }, 300);
                    }, 1000);
                }, 500);
                
            } catch (error) {
                console.error('提交错误:', error);
                showError('提交时出错，请稍后再试: ' + error.message);
            } finally {
                // 恢复提交按钮
                suggestionSubmitBtn.disabled = false;
                submitText.textContent = '提交建议';
                loadingSpinner.style.display = 'none';
            }
        });
    }
    
    // 显示错误信息
    function showError(message) {
        if (!errorText || !errorMessage) return;
        errorText.textContent = message;
        errorMessage.classList.add('show');
        successMessage.classList.remove('show');
        
        // 滚动到错误信息
        errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // 8秒后自动隐藏错误信息
        setTimeout(() => {
            errorMessage.classList.remove('show');
        }, 8000);
    }
    
    // 显示成功信息
    function showSuccess(message) {
        if (!successText || !successMessage) return;
        successText.textContent = message;
        successMessage.classList.add('show');
        errorMessage.classList.remove('show');
        
        // 滚动到成功信息
        successMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // 清除本地存储的草稿
        localStorage.removeItem('suggestionDraft');
    }
    
    // 提交新建议按钮
    if (newSuggestionBtn) {
        newSuggestionBtn.addEventListener('click', function() {
            thankYouMessage.classList.remove('show');
            suggestionForm.style.display = 'block';
            
            setTimeout(() => {
                suggestionForm.style.opacity = '1';
                suggestionForm.style.animation = 'fadeIn 0.5s ease';
            }, 50);
            
            errorMessage.classList.remove('show');
            successMessage.classList.remove('show');
        });
    }
    
    // 页面加载时的简单动画
    window.addEventListener('load', function() {
        const header = document.querySelector('.header');
        if (header) header.style.animation = 'fadeIn 1s ease';
    });
    
    // 表单自动保存草稿功能
    let saveTimer;
    if (suggestionForm) {
        suggestionForm.addEventListener('input', function() {
            clearTimeout(saveTimer);
            
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
    }
    
    // 页面加载时恢复草稿
    window.addEventListener('load', function() {
        try {
            const savedDraft = localStorage.getItem('suggestionDraft');
            if (savedDraft) {
                const draft = JSON.parse(savedDraft);
                
                // 检查草稿是否在今天保存的（不超过24小时）
                const savedTime = new Date(draft.savedAt);
                const now = new Date();
                const hoursDiff = (now - savedTime) / (1000 * 60 * 60);
                
                if (hoursDiff < 24) {
                    const nickInput = document.getElementById('nickname');
                    const emailInput = document.getElementById('email');
                    const suggInput = document.getElementById('suggestion');
                    
                    if (nickInput) nickInput.value = draft.nickname || '';
                    if (emailInput) emailInput.value = draft.email || '';
                    if (suggInput) suggInput.value = draft.suggestion || '';
                    
                    // 更新字符计数器
                    if (charCount) {
                        const currentLength = draft.suggestion ? draft.suggestion.length : 0;
                        charCount.textContent = currentLength;
                    }
                    
                    console.log('草稿已恢复');
                } else {
                    localStorage.removeItem('suggestionDraft');
                }
            }
        } catch (error) {
            console.log('恢复草稿时出错:', error);
        }
    });
    
    // ========== 评分功能 ==========
    const scoreInput = document.getElementById('scoreInput');
    const ratingSubmitBtn = document.getElementById('ratingSubmitBtn');
    const ratingMessage = document.getElementById('ratingMessage');
    const ratingResult = document.getElementById('ratingResult');
    
    function validateScoreInput() {
        if (!scoreInput || !ratingMessage || !ratingSubmitBtn) return false;
        const value = scoreInput.value.trim();
        
        ratingMessage.textContent = '';
        ratingMessage.className = 'rating-message';
        
        scoreInput.classList.remove('correct', 'wrong');
        
        if (value === '10') {
            scoreInput.classList.add('correct');
            ratingMessage.textContent = '✓ 冠恩超人！感谢您的评价';
            ratingMessage.classList.add('success');
            ratingSubmitBtn.disabled = false;
            return true;
        } 
        else if (value === '') {
            ratingSubmitBtn.disabled = true;
            return false;
        }
        else {
            scoreInput.classList.add('wrong');
            ratingMessage.textContent = '✗ 想好了再写哦';
            ratingMessage.classList.add('error');
            ratingSubmitBtn.disabled = true;
            return false;
        }
    }
    
    // 只允许输入数字
    if (scoreInput) {
        scoreInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
        
        scoreInput.addEventListener('input', function(e) {
            validateScoreInput();
        });
    }
    
    if (ratingSubmitBtn) {
        ratingSubmitBtn.addEventListener('click', function() {
            if (validateScoreInput()) {
                if (ratingResult) ratingResult.classList.add('show');
                
                if (ratingResult) ratingResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                scoreInput.disabled = true;
                ratingSubmitBtn.disabled = true;
                ratingSubmitBtn.textContent = '已提交';
                
                console.log('网站评分已提交：10分');
            }
        });
    }
    
    // 初始化
    validateScoreInput();
});