/**
 * 抽奖系统核心模块 - 智能角度计算修复版
 * 修复连续抽奖时角度累积导致结果不对齐的问题
 */

/**
 * 抽奖系统配置
 */
const lotteryConfig = {
    // 转盘奖品配置 - 6个扇区
    prizes: [
        { 
            name: '浮生若梦', 
            value: '虚浮的人生，如同一场大梦', 
            probability: 0.2475, 
            isWin: false, 
            color: '#EFE3D0'
        },
        { 
            name: '澄怀观道', 
            value: '清澄自己的胸怀，以观照宇宙的本源与法则。', 
            probability: 0.2475, 
            isWin: false, 
            color: '#F9F1E7'
        },
        { 
            name: '书籍一本', 
            value: '冠恩亲选书籍一本', 
            probability: 0.01, 
            isWin: true, 
            color: '#FFD700'
        },
        { 
            name: '和光同尘', 
            value: '收敛光芒，与世俗尘埃混同', 
            probability: 0.2475, 
            isWin: false, 
            color: '#EFE3D0'
        },
        { 
            name: '空谷幽兰', 
            value: '空寂的山谷中，悄然开放的兰花。', 
            probability: 0.2475, 
            isWin: false, 
            color: '#F9F1E7'
        },
        { 
            name: '冰壶秋月', 
            value: '冰做的玉壶，秋夜皎洁的明月', 
            probability: 0, 
            isWin: false, 
            color: '#EFE3D0'
        }
    ],
    
    // API端点配置 - 统一使用绝对路径，确保在不同目录下的页面都能正确调用
    apiEndpoints: {
        init: '/api/lottery_api.php?action=init',
        drawLottery: '/api/lottery_api.php?action=draw_lottery',
        getRecords: '/api/lottery_api.php?action=get_records',
        saveDeliveryInfo: '/api/lottery_api.php?action=save_delivery_info'
    },
    
    // 旋转动画配置
    spinConfig: {
        baseSpins: 5,
        duration: 4000,
        easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)'
    }
};

/**
 * 抽奖系统状态管理
 */
const lotteryState = {
    userId: null,
    username: null,
    remainingDraws: 0,
    maxDailyDraws: 3,
    isLoggedIn: false,
    isSpinning: false,
    currentRotation: 0,
    records: [],
    debugMode: true
};

/**
 * DOM 元素缓存
 */
const domElements = {
    wheelContainer: null,
    svgContainer: null,
    drawButton: null,
    drawCountDisplay: null,
    recordsContainer: null,
    resultModal: null,
    modalTitle: null,
    modalMessage: null,
    modalClose: null,
    loginStatus: null,
    loginMessage: null,
    loadingIndicator: null,
    manageAddressBtn: null,
    // 收货信息相关
    addressModal: null,
    addressForm: null,
    winRecordIdInput: null,
    addressModalClose: null
};

/**
 * API调用函数
 */
const api = {
    async request(url, method = 'GET', data = null) {
        try {
            const requestUrl = url.includes('?') 
                ? `${url}&_=${Date.now()}`
                : `${url}?_=${Date.now()}`;
            
            const options = {
                method: method,
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'include'
            };

            if (data) {
                if (data instanceof FormData) {
                    options.body = data;
                } else {
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    options.body = new URLSearchParams(data).toString();
                }
            }
            
            const response = await fetch(requestUrl, options);
            const responseText = await response.text();
            
            if (responseText.includes('<b>Fatal error</b>') || 
                responseText.includes('<b>Parse error</b>') || 
                responseText.includes('Call to a member function')) {
                
                const errorMatch = responseText.match(/error<\/b>:\s*(.*?)<br/);
                const errorMessage = errorMatch ? errorMatch[1].trim() : 'PHP脚本错误';
                
                return {
                    success: false,
                    error: true,
                    message: `服务器错误: ${errorMessage}`,
                    php_error: true
                };
            }
            
            try {
                return JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON解析失败:', responseText.substring(0, 200));
                return {
                    success: false,
                    error: true,
                    message: '服务器返回了无效的响应格式'
                };
            }
            
        } catch (error) {
            console.error('API请求失败:', error);
            return {
                success: false,
                error: true,
                message: error.message.includes('Failed to fetch') 
                    ? '网络连接失败，请检查网络连接'
                    : '请求失败: ' + error.message
            };
        }
    },

    async init() {
        return await this.request(lotteryConfig.apiEndpoints.init);
    },

    async drawLottery() {
        return await this.request(lotteryConfig.apiEndpoints.drawLottery, 'POST', { action: 'draw_lottery' });
    },

    async getRecords() {
        return await this.request(lotteryConfig.apiEndpoints.getRecords);
    },

    async checkLogin() {
        return await this.request('/api/check_login.php');
    },

    async getRemainingDraws() {
        return await this.request('/api/lottery_api.php?action=get_remaining_draws');
    },

    async createPlaceholderRecord() {
        return await this.request('/api/lottery_api.php?action=create_placeholder_record', 'POST');
    },

    async saveDeliveryInfo(data) {
        return await this.request(lotteryConfig.apiEndpoints.saveDeliveryInfo, 'POST', data);
    }
};

/**
 * ==================== 核心修复：智能角度计算 ====================
 */

/**
 * 智能角度计算函数 - 解决连续抽奖角度累积问题
 * @param {number} targetPrizeIndex - 目标奖品索引
 * @returns {number} - 需要旋转的总角度
 */
function calculateSmartAngle(targetPrizeIndex) {
    const prizes = lotteryConfig.prizes;
    const sliceCount = prizes.length;
    const sliceAngle = 360 / sliceCount;
    
    console.log('=== 智能角度计算开始 ===');
    console.log(`目标奖品索引: ${targetPrizeIndex} (${prizes[targetPrizeIndex].name})`);
    console.log(`当前旋转角度: ${lotteryState.currentRotation}°`);
    
    // 1. 计算目标奖品的中心角度（相对于转盘0度位置）
    const prizeCenterAngle = targetPrizeIndex * sliceAngle + (sliceAngle / 2);
    
    // 2. 计算指针指向该奖品时，转盘需要旋转到的角度
    // 指针在0度位置，我们希望奖品的中心角度旋转到指针位置
    // 所以：转盘需要旋转的角度 = 360° - 奖品中心角度
    let targetFinalAngle = 360 - prizeCenterAngle;
    
    // 确保角度在0-360度范围内
    targetFinalAngle = (targetFinalAngle + 360) % 360;
    
    // 3. 计算当前旋转角度（归一化到0-360度）
    const currentRotationNormalized = lotteryState.currentRotation % 360;
    
    // 4. 计算从当前位置到目标位置需要旋转的最小角度
    // 我们需要找到最短路径到达目标角度
    let neededRotation = targetFinalAngle - currentRotationNormalized;
    
    // 调整到最短路径
    if (neededRotation > 180) {
        neededRotation = neededRotation - 360;
    } else if (neededRotation < -180) {
        neededRotation = neededRotation + 360;
    }
    
    // 确保角度为正数
    if (neededRotation < 0) {
        neededRotation = neededRotation + 360;
    }
    
    // 5. 添加额外的完整旋转圈数（为了动画效果）
    const fullRotations = lotteryConfig.spinConfig.baseSpins;
    const totalAngle = fullRotations * 360 + neededRotation;
    
    // 6. 计算旋转后的新角度（用于验证）
    const newRotation = (currentRotationNormalized + neededRotation) % 360;
    
    console.log('=== 智能角度计算结果 ===');
    console.log(`- 每份角度: ${sliceAngle}°`);
    console.log(`- 奖品中心角度: ${prizeCenterAngle.toFixed(1)}°`);
    console.log(`- 目标最终角度: ${targetFinalAngle.toFixed(1)}°`);
    console.log(`- 归一化当前角度: ${currentRotationNormalized.toFixed(1)}°`);
    console.log(`- 需要旋转的角度: ${neededRotation.toFixed(1)}°`);
    console.log(`- 完整旋转圈数: ${fullRotations}`);
    console.log(`- 总旋转角度: ${totalAngle.toFixed(1)}°`);
    console.log(`- 旋转后角度: ${newRotation.toFixed(1)}°`);
    console.log('========================');
    
    return totalAngle;
}

/**
 * 验证指针指向的奖品
 */
function verifyPointerPosition(currentAngle) {
    const prizes = lotteryConfig.prizes;
    const sliceAngle = 360 / prizes.length;
    
    // 计算指针指向的角度（转盘旋转后，指针相对位置）
    // 指针在0度位置，所以需要计算转盘旋转后哪个奖品在指针位置
    const normalizedAngle = (360 - (currentAngle % 360)) % 360;
    const prizeIndex = Math.floor(normalizedAngle / sliceAngle) % prizes.length;
    
    console.log(`指针验证:`);
    console.log(`- 当前转盘角度: ${currentAngle}°`);
    console.log(`- 归一化角度: ${normalizedAngle}°`);
    console.log(`- 指针指向的奖品索引: ${prizeIndex}`);
    console.log(`- 奖品名称: ${prizes[prizeIndex].name}`);
    
    return prizeIndex;
}

/**
 * 执行转盘旋转动画 - 智能版
 */
function spinWheel(targetAngle, callback) {
    if (!domElements.svgContainer) {
        console.error('未找到SVG容器');
        if (callback) callback();
        return;
    }
    
    console.log(`开始旋转动画 - 目标角度: ${targetAngle}°`);
    
    const animationDuration = lotteryConfig.spinConfig.duration;
    const animationEasing = lotteryConfig.spinConfig.easing;
    
    // 取消之前的过渡效果
    domElements.svgContainer.style.transition = 'none';
    domElements.svgContainer.style.transform = `rotate(${lotteryState.currentRotation}deg)`;
    
    // 强制重绘
    domElements.svgContainer.offsetHeight;
    
    // 设置新的过渡效果
    domElements.svgContainer.style.transition = `transform ${animationDuration}ms ${animationEasing}`;
    domElements.svgContainer.style.transform = `rotate(${lotteryState.currentRotation + targetAngle}deg)`;
    
    // 动画完成后执行回调
    setTimeout(() => {
        // 更新当前旋转角度
        const newRotation = (lotteryState.currentRotation + targetAngle) % 360;
        lotteryState.currentRotation = newRotation;
        
        // 移除过渡效果
        domElements.svgContainer.style.transition = 'none';
        domElements.svgContainer.style.transform = `rotate(${newRotation}deg)`;
        
        console.log(`动画完成 - 新旋转角度: ${newRotation}°`);
        
        // 验证指针位置
        const pointedPrizeIndex = verifyPointerPosition(newRotation);
        
        if (callback) {
            callback();
        }
    }, animationDuration);
}

/**
 * 执行抽奖操作 - 智能角度计算版
 */
async function drawLottery() {
    try {
        // 1. 检查是否正在抽奖
        if (lotteryState.isSpinning) {
            console.log('抽奖正在进行中，请稍候');
            return;
        }
        
        // 2. 更新状态
        lotteryState.isSpinning = true;
        if (domElements.drawButton) {
            domElements.drawButton.disabled = true;
            domElements.drawButton.textContent = '请求中...';
        }
        
        // 3. 执行抽奖API调用 (不再预判登录，由后端统一处理)
        const result = await api.drawLottery();
        
        // 4. 处理 401 未登录错误或其他错误
        if (result.error) {
            lotteryState.isSpinning = false;
            updateButtonState();
            
            if (result.code === 401 || result.message.includes('登录')) {
                showLoginRequired('API返回401或登录提示');
            } else if (result.php_error) {
                showResultModal('服务器错误', '服务器内部错误，请联系管理员');
            } else {
                showResultModal('抽奖失败', result.message);
            }
            return;
        }
        
        // 5. 抽奖成功，开始转盘动画
        if (result.success) {
            console.log('抽奖成功，开始转盘动画');
            
            if (domElements.drawButton) {
                domElements.drawButton.textContent = '抽奖中...';
            }

            let targetPrizeIndex = -1;
            
            // 查找中奖的奖品索引
            const prizes = lotteryConfig.prizes;
            const prizeName = result.prize_name || (result.data && result.data.prize_name);
            const prizeValue = result.prize_value || (result.data && result.data.prize_value);
            const isWin = result.is_win !== undefined ? result.is_win : (result.data && result.data.is_win);
            const recordId = result.record_id || (result.data && result.data.record_id);

            for (let i = 0; i < prizes.length; i++) {
                if (prizes[i].name === prizeName) {
                    targetPrizeIndex = i;
                    break;
                }
            }
            
            // 如果没找到匹配的奖品名，使用第一个作为兜底
            if (targetPrizeIndex === -1) {
                console.warn('未找到匹配奖品名:', prizeName);
                targetPrizeIndex = 0;
            }
            
            // 计算旋转角度
            const totalRotation = calculateSmartAngle(targetPrizeIndex);
            
            // 执行旋转
            spinWheel(totalRotation, () => {
                lotteryState.isSpinning = false;
                
                // 动画结束后的处理
                if (isWin) {
                    showResultModal('恭喜您中奖了！', `您获得了：${prizeName} (${prizeValue})`, recordId);
                    // 如果中奖了，提示填写地址
                    setTimeout(() => {
                        openAddressModal(recordId);
                    }, 2000);
                } else {
                    showResultModal('很遗憾没中奖', `本次抽奖结果：${prizeName}`);
                }
                
                // 刷新状态
                checkUserLoginStatus();
                updateUI();
            });
        }
    } catch (error) {
        console.error('抽奖执行异常:', error);
        lotteryState.isSpinning = false;
        updateButtonState();
        showResultModal('系统异常', '抽奖过程发生错误，请刷新页面重试');
    }
}

/**
 * ==================== 其他必要函数 ====================
 */

/**
 * 初始化抽奖系统
 */
async function initLotterySystem() {
    console.log('初始化抽奖系统（智能角度计算版）...');
    
    try {
        // 缓存DOM元素
        cacheDomElements();
        
        // 1. 使用统一的 init 接口获取配置和状态
        const result = await api.init();
        
        if (result.error) {
            if (result.php_error) {
                showError('服务器内部错误，请联系管理员');
                return;
            }
            throw new Error(result.message);
        }

        // 2. 更新配置
        if (result.success) {
            if (result.max_daily_draws) {
                lotteryState.maxDailyDraws = result.max_daily_draws;
            }
            
            // 更新奖品配置
            if (result.prizes && Array.isArray(result.prizes) && result.prizes.length > 0) {
                const defaultColors = ['#EFE3D0', '#F9F1E7', '#FFD700', '#EFE3D0', '#F9F1E7', '#EFE3D0'];
                lotteryConfig.prizes = result.prizes.map((p, index) => ({
                    name: p.name,
                    value: p.value,
                    probability: p.probability,
                    isWin: p.is_win || p.isWin,
                    color: p.color || defaultColors[index % defaultColors.length]
                }));
            }
        }

        // 3. 更新登录状态和用户数据
        console.log('当前所有 Cookie:', document.cookie);
        const isLoggedIn = result.is_logged_in || result.logged_in || (result.data && (result.data.is_logged_in || result.data.logged_in));
        console.log('登录状态检查结果:', { 
            isLoggedIn, 
            userId: result.user_id, 
            username: result.username,
            rawResult: result 
        });

        if (isLoggedIn) {
            lotteryState.isLoggedIn = true;
            lotteryState.userId = result.user_id || (result.data && result.data.user_id);
            lotteryState.username = result.username || (result.data && result.data.username);
            lotteryState.remainingDraws = result.remaining_draws !== undefined ? result.remaining_draws : (result.data && result.data.remaining_draws);
            
            // 确保记录数据被正确赋值
            const records = result.records || (result.data && result.data.records) || [];
            if (records.length > 0) {
                lotteryState.records = records;
                updateUI();
            } else {
                // 如果初始化时没有记录，尝试异步单独获取一次
                api.getRecords().then(recordsResult => {
                    if (recordsResult.success) {
                        lotteryState.records = recordsResult.records || recordsResult.data || [];
                        updateUI();
                    }
                });
            }
        } else {
            lotteryState.isLoggedIn = false;
            lotteryState.userId = null;
            lotteryState.username = null;
            lotteryState.remainingDraws = 0;
            lotteryState.records = [];
            // 只有在明确未登录时才显示提示
            console.warn('用户未登录，将限制抽奖功能');
            // 不要在初始化时自动弹出确认框，只更新UI状态
            updateUI();
        }
        
        // 4. 更新UI显示
        updateUI();

        // 5. 更新登录重定向链接
        const loginLink = document.getElementById('loginRedirectLink');
        if (loginLink) {
            const currentUrl = encodeURIComponent(window.location.pathname + window.location.search);
            loginLink.href = `/user/user_login.php?redirect=${currentUrl}`;
        }
        
        // 6. 创建转盘
        createLotteryWheel();
        
        // 6. 重置转盘到初始位置
        resetWheelImmediately();
        
        console.log('抽奖系统初始化完成');
    } catch (error) {
        console.error('初始化失败:', error);
        showError('系统初始化失败，请刷新页面重试');
    }
}

/**
 * 缓存DOM元素
 */
function cacheDomElements() {
    domElements.wheelContainer = document.getElementById('wheelContainer');
    domElements.drawButton = document.getElementById('drawButton');
    domElements.drawCountDisplay = document.getElementById('drawCount');
    domElements.recordsContainer = document.getElementById('recordsContainer');
    domElements.resultModal = document.getElementById('resultModal');
    domElements.modalTitle = document.getElementById('modalTitle');
    domElements.modalMessage = document.getElementById('modalMessage');
    domElements.modalClose = document.getElementById('closeResultBtn');
    domElements.loginStatus = document.getElementById('loginStatus');
    domElements.loginMessage = document.getElementById('loginMessage');
    domElements.manageAddressBtn = document.getElementById('manageAddressBtn');
    
    // 缓存收货信息相关元素
    domElements.addressModal = document.getElementById('addressModal');
    domElements.addressForm = document.getElementById('addressForm');
    domElements.winRecordIdInput = document.getElementById('winRecordId');
    domElements.addressModalClose = document.getElementById('addressModalClose');
}

/**
 * 检查用户登录状态
 */
async function checkUserLoginStatus() {
    try {
        const result = await api.checkLogin();
        
        if (result.error) {
            if (result.php_error) {
                showError('服务器内部错误，请联系管理员');
                return;
            }
            throw new Error(result.message);
        }
        
        const isLoggedIn = result.is_logged_in || result.logged_in || (result.data && (result.data.is_logged_in || result.data.logged_in));
        
        if (isLoggedIn) {
            lotteryState.isLoggedIn = true;
            lotteryState.userId = result.user_id || (result.data && result.data.user_id);
            lotteryState.username = result.username || (result.data && result.data.username);
            
            // 获取剩余抽奖次数
            try {
                const drawsResult = await api.getRemainingDraws();
                if (!drawsResult.error && drawsResult.success) {
                    lotteryState.remainingDraws = drawsResult.remaining_draws !== undefined ? drawsResult.remaining_draws : (drawsResult.data && drawsResult.data.remaining_draws);
                    lotteryState.maxDailyDraws = drawsResult.max_daily_draws !== undefined ? drawsResult.max_daily_draws : (drawsResult.data && drawsResult.data.max_daily_draws);
                } else {
                    lotteryState.remainingDraws = 3;
                    lotteryState.maxDailyDraws = 3;
                }
            } catch {
                lotteryState.remainingDraws = 3;
                lotteryState.maxDailyDraws = 3;
            }
            
            // 获取抽奖记录
            try {
                const recordsResult = await api.getRecords();
                if (!recordsResult.error && recordsResult.success) {
                    lotteryState.records = recordsResult.records || recordsResult.data || [];
                }
            } catch {
                lotteryState.records = [];
            }
            
        } else {
            lotteryState.isLoggedIn = false;
            lotteryState.userId = null;
            lotteryState.username = null;
            lotteryState.remainingDraws = 0;
            lotteryState.records = [];
            // 如果是自动检查导致的未登录，通常不应该强制弹出模态框，除非是用户点击抽奖时
            // showLoginRequired(); 
        }
        
    } catch (error) {
        console.error('检查登录状态失败:', error);
        lotteryState.isLoggedIn = false;
        showError('登录状态检查失败');
    }
}

/**
 * 更新UI显示
 */
function updateUI() {
    updateLoginStatus();
    updateDrawCountDisplay();
    renderDrawRecords();
    updateButtonState();
    updateManageAddressButton();
}

/**
 * 更新登录状态显示
 */
function updateLoginStatus() {
    if (!domElements.loginStatus || !domElements.loginMessage) return;
    
    if (lotteryState.isLoggedIn) {
        domElements.loginMessage.textContent = `欢迎，${lotteryState.username}！`;
        const loginLink = domElements.loginStatus.querySelector('a');
        if (loginLink) loginLink.style.display = 'none';
    } else {
        domElements.loginMessage.textContent = '请先登录后再进行抽奖';
        const loginLink = domElements.loginStatus.querySelector('a');
        if (loginLink) loginLink.style.display = 'inline';
    }
}

/**
 * 更新抽奖按钮状态
 */
function updateButtonState() {
    if (!domElements.drawButton) return;
    
    if (!lotteryState.isLoggedIn) {
        domElements.drawButton.disabled = true;
        domElements.drawButton.textContent = '请先登录';
    } else if (lotteryState.remainingDraws <= 0) {
        domElements.drawButton.disabled = true;
        domElements.drawButton.textContent = '今日次数已用完';
    } else {
        domElements.drawButton.disabled = false;
        domElements.drawButton.textContent = '开始抽奖';
    }
}

/**
 * 更新管理地址按钮显示状态
 */
function updateManageAddressButton() {
    if (!domElements.manageAddressBtn) return;
    
    // 用户要求直接显示，不再根据中奖记录隐藏
    domElements.manageAddressBtn.style.display = 'inline-block';
}

/**
 * 更新抽奖次数显示
 */
function updateDrawCountDisplay() {
    if (domElements.drawCountDisplay) {
        if (lotteryState.isLoggedIn) {
            domElements.drawCountDisplay.textContent = 
                `剩余抽奖次数: ${lotteryState.remainingDraws}/${lotteryState.maxDailyDraws}`;
        } else {
            domElements.drawCountDisplay.textContent = '请先登录后抽奖';
        }
    }
}

/**
 * 显示登录提示
 */
function showLoginRequired(reason = '未检测到登录状态') {
    console.log('触发登录提示，原因:', reason);
    if (domElements.drawButton) {
        domElements.drawButton.disabled = true;
        domElements.drawButton.textContent = '请先登录';
    }
    
    if (domElements.drawCountDisplay) {
        domElements.drawCountDisplay.textContent = '请先登录后抽奖';
    }

    // 更新登录链接的重定向参数
    const currentUrl = encodeURIComponent(window.location.href);
    const loginUrl = `/user/user_login.php?redirect=${currentUrl}`;
    
    const loginLink = document.getElementById('loginRedirectLink');
    if (loginLink) {
        loginLink.href = loginUrl;
    }
    
    // 如果已经在登录提示中，不要重复弹出
    if (window.isShowingLoginConfirm) return;
    
    window.isShowingLoginConfirm = true;
    if (confirm('请先登录后再进行抽奖！\n是否立即前往登录？')) {
        window.isShowingLoginConfirm = false;
        window.location.href = loginUrl;
    } else {
        window.isShowingLoginConfirm = false;
    }
}

/**
 * 显示错误信息
 */
function showError(message) {
    if (domElements.drawButton) {
        domElements.drawButton.disabled = true;
        domElements.drawButton.textContent = '系统错误';
    }
    
    showResultModal('系统错误', message);
}

/**
 * 显示结果模态框
 */
function showResultModal(title, message, recordId = null) {
    console.log('显示结果模态框:', { title, message, recordId });
    
    if (domElements.modalTitle && domElements.modalMessage && domElements.resultModal) {
        domElements.modalTitle.textContent = title;
        domElements.modalMessage.textContent = message;
        domElements.resultModal.style.display = 'flex';
        
        // 自动关闭非中奖且非错误模态框
        if (!title.includes('恭喜') && !title.includes('错误') && !title.includes('失败') && !title.includes('提示')) {
            setTimeout(() => {
                if (domElements.resultModal && domElements.resultModal.style.display === 'flex') {
                    closeResultModal();
                }
            }, 5000);
        }
    }
}

/**
 * 打开收货信息模态框
 * @param {number|string} recordId - 记录ID
 */
function openAddressModal(recordId = null) {
    closeResultModal();
    
    // 如果传入了recordId，则设置到隐藏域
    if (recordId) {
        if (domElements.winRecordIdInput) {
            domElements.winRecordIdInput.value = recordId;
        }
        
        // 尝试从本地状态中查找已有信息并回显
        // 优先从当前记录查找，如果当前记录没有，则从其他记录中找最近填写的地址
        let displayRecord = lotteryState.records.find(r => r.id == recordId);
        if (!displayRecord || !displayRecord.receiver_name) {
            displayRecord = lotteryState.records.find(r => r.receiver_name && r.receiver_name.trim() !== '');
        }

        if (displayRecord) {
            const nameInput = document.getElementById('receiverName');
            const phoneInput = document.getElementById('receiverPhone');
            const addressInput = document.getElementById('receiverAddress');
            
            if (nameInput) nameInput.value = displayRecord.receiver_name || '';
            if (phoneInput) phoneInput.value = displayRecord.receiver_phone || '';
            if (addressInput) addressInput.value = displayRecord.receiver_address || '';
        }
    }
    
    if (domElements.addressModal) {
        domElements.addressModal.style.display = 'flex';
    }
}

/**
 * 关闭收货信息模态框
 */
function closeAddressModal() {
    if (domElements.addressModal) {
        domElements.addressModal.style.display = 'none';
    }
}

/**
 * 提交收货信息
 */
async function submitAddressForm(event) {
    event.preventDefault();
    
    const recordId = domElements.winRecordIdInput.value;
    const name = document.getElementById('receiverName').value;
    const phone = document.getElementById('receiverPhone').value;
    const address = document.getElementById('receiverAddress').value;
    
    if (!name || !phone || !address) {
        alert('请填写完整的收货信息');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('record_id', recordId);
        formData.append('receiver_name', name);
        formData.append('receiver_phone', phone);
        formData.append('receiver_address', address);
        
        const result = await api.saveDeliveryInfo(formData);
        
        if (result.success) {
            alert('收货信息已提交成功！我们将尽快为您发货。');
            
            // 更新本地所有记录中的信息，以便同步显示
            lotteryState.records.forEach(record => {
                // 仅更新未发货或没有地址的记录（逻辑上与后端一致）
                record.receiver_name = name;
                record.receiver_phone = phone;
                record.receiver_address = address;
            });
            
            // 重新渲染记录列表以反映变化
            renderDrawRecords();
            
            closeAddressModal();
        } else {
            alert('提交失败: ' + (result.message || '未知错误'));
        }
    } catch (error) {
        console.error('提交收货信息失败:', error);
        alert('提交失败，请检查网络连接后重试');
    }
}

/**
 * 关闭结果模态框
 */
function closeResultModal() {
    if (domElements.resultModal) {
        domElements.resultModal.style.display = 'none';
    }
}

/**
 * 立即重置转盘位置（无动画）
 */
function resetWheelImmediately() {
    if (!domElements.svgContainer) return;
    
    lotteryState.currentRotation = 0;
    domElements.svgContainer.style.transition = 'none';
    domElements.svgContainer.style.transform = 'rotate(0deg)';
    
    // 强制重绘
    domElements.svgContainer.offsetHeight;
    
    console.log('转盘位置已立即重置为0度');
}

/**
 * 创建抽奖转盘
 */
function createLotteryWheel() {
    console.log('开始创建抽奖转盘...');
    
    if (!domElements.wheelContainer) {
        console.error('未找到转盘容器元素');
        return;
    }
    
    // 清空容器（保留指针）
    const existingElements = domElements.wheelContainer.children;
    const elementsToRemove = [];
    
    for (let i = 0; i < existingElements.length; i++) {
        const child = existingElements[i];
        if (!child.classList || !child.classList.contains('wheel-pointer')) {
            elementsToRemove.push(child);
        }
    }
    
    elementsToRemove.forEach(element => {
        if (element.parentNode === domElements.wheelContainer) {
            domElements.wheelContainer.removeChild(element);
        }
    });
    
    try {
        // 创建SVG容器
        domElements.svgContainer = document.createElement('div');
        domElements.svgContainer.className = 'svg-wheel-container';
        
        // 设置样式
        domElements.svgContainer.style.position = 'relative';
        domElements.svgContainer.style.width = '100%';
        domElements.svgContainer.style.height = '100%';
        domElements.svgContainer.style.display = 'block';
        domElements.svgContainer.style.borderRadius = '50%';
        domElements.svgContainer.style.transform = 'rotate(0deg)';
        
        // 创建SVG元素
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttributeNS(null, 'width', '100%');
        svg.setAttributeNS(null, 'height', '100%');
        svg.setAttributeNS(null, 'viewBox', '0 0 400 400');
        svg.setAttributeNS(null, 'id', 'lotteryWheel');
        
        const prizes = lotteryConfig.prizes;
        const sliceCount = prizes.length;
        const centerX = 200;
        const centerY = 200;
        const radius = 180;
        const sliceAngle = 360 / sliceCount;
        
        // 创建每个扇形区域
        for (let i = 0; i < sliceCount; i++) {
            const startAngle = i * sliceAngle;
            const endAngle = startAngle + sliceAngle;
            
            const startRad = (startAngle - 90) * Math.PI / 180;
            const endRad = (endAngle - 90) * Math.PI / 180;
            const x1 = centerX + radius * Math.cos(startRad);
            const y1 = centerY + radius * Math.sin(startRad);
            const x2 = centerX + radius * Math.cos(endRad);
            const y2 = centerY + radius * Math.sin(endRad);
            const largeArcFlag = sliceAngle > 180 ? 1 : 0;
            
            // 创建扇形
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttributeNS(null, 'd', `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2} Z`);
            path.setAttributeNS(null, 'fill', prizes[i].color);
            path.setAttributeNS(null, 'stroke', '#8b4513');
            path.setAttributeNS(null, 'stroke-width', '2');
            
            svg.appendChild(path);
            
            // 创建文字
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            const midAngle = startAngle + sliceAngle / 2;
            const midRad = (midAngle - 90) * Math.PI / 180;
            const textRadius = radius * 0.7;
            const textX = centerX + textRadius * Math.cos(midRad);
            const textY = centerY + textRadius * Math.sin(midRad);
            
            text.setAttributeNS(null, 'x', textX);
            text.setAttributeNS(null, 'y', textY);
            text.setAttributeNS(null, 'text-anchor', 'middle');
            text.setAttributeNS(null, 'dy', '0.3em');
            text.setAttributeNS(null, 'font-size', prizes[i].isWin ? '18px' : '16px');
            text.setAttributeNS(null, 'font-weight', prizes[i].isWin ? 'bold' : 'normal');
            text.setAttributeNS(null, 'fill', prizes[i].isWin ? '#8b4513' : '#654321');
            text.setAttributeNS(null, 'transform', `rotate(${midAngle + 90} ${textX} ${textY})`);
            
            const textNode = document.createTextNode(prizes[i].name);
            text.appendChild(textNode);
            
            svg.appendChild(text);
        }
        
        // 添加中心圆点
        const centerCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        centerCircle.setAttributeNS(null, 'cx', centerX);
        centerCircle.setAttributeNS(null, 'cy', centerY);
        centerCircle.setAttributeNS(null, 'r', '20');
        centerCircle.setAttributeNS(null, 'fill', '#8b4513');
        centerCircle.setAttributeNS(null, 'stroke', '#654321');
        centerCircle.setAttributeNS(null, 'stroke-width', '3');
        svg.appendChild(centerCircle);
        
        // 添加SVG到容器
        domElements.svgContainer.appendChild(svg);
        
        // 将容器添加到wheelContainer
        const pointer = domElements.wheelContainer.querySelector('.wheel-pointer');
        if (pointer && pointer.nextSibling) {
            domElements.wheelContainer.insertBefore(domElements.svgContainer, pointer.nextSibling);
        } else {
            domElements.wheelContainer.appendChild(domElements.svgContainer);
        }
        
        console.log('转盘创建完成');
    } catch (error) {
        console.error('创建转盘时发生错误:', error);
        createFallbackWheel();
    }
}

/**
 * 创建回退的转盘显示
 */
function createFallbackWheel() {
    console.log('使用回退方案创建转盘');
    
    const fallbackWheel = document.createElement('div');
    fallbackWheel.className = 'svg-wheel-container fallback-wheel';
    fallbackWheel.style.width = '100%';
    fallbackWheel.style.height = '100%';
    fallbackWheel.style.borderRadius = '50%';
    fallbackWheel.style.background = '#FFD700';
    fallbackWheel.style.display = 'flex';
    fallbackWheel.style.justifyContent = 'center';
    fallbackWheel.style.alignItems = 'center';
    fallbackWheel.style.fontSize = '20px';
    fallbackWheel.style.color = '#8b4513';
    fallbackWheel.style.fontWeight = 'bold';
    fallbackWheel.style.border = '8px solid #8b4513';
    
    const text = document.createElement('div');
    text.textContent = '幸运抽奖';
    text.style.textAlign = 'center';
    
    fallbackWheel.appendChild(text);
    
    if (domElements.svgContainer && domElements.svgContainer.parentNode === domElements.wheelContainer) {
        domElements.wheelContainer.removeChild(domElements.svgContainer);
    }
    
    domElements.wheelContainer.appendChild(fallbackWheel);
    domElements.svgContainer = fallbackWheel;
}

/**
 * 渲染抽奖记录
 */
function renderDrawRecords() {
    if (!domElements.recordsContainer) return;
    
    domElements.recordsContainer.innerHTML = '';
    
    if (!lotteryState.isLoggedIn) {
        const loginMsg = document.createElement('div');
        loginMsg.className = 'record-item';
        loginMsg.textContent = '请先登录查看抽奖记录';
        loginMsg.style.textAlign = 'center';
        loginMsg.style.color = '#999';
        domElements.recordsContainer.appendChild(loginMsg);
        return;
    }
    
    if (lotteryState.records.length === 0) {
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'record-item';
        emptyMsg.textContent = '暂无抽奖记录';
        emptyMsg.style.textAlign = 'center';
        emptyMsg.style.color = '#999';
        domElements.recordsContainer.appendChild(emptyMsg);
        return;
    }
    
    const recentRecords = lotteryState.records.slice(0, 10);
    
    recentRecords.forEach(record => {
        const recordItem = document.createElement('div');
        const isRegistration = record.prize_name === '信息登记';
        recordItem.className = `record-item ${record.is_win ? 'win' : ''} ${isRegistration ? 'registration' : ''}`;
        
        const time = record.draw_time || new Date().toLocaleString('zh-CN');
        let status = record.is_win ? '🎉 中奖' : '💫 参与';
        if (isRegistration) status = '📝 登记';
        
        const addressFilled = record.receiver_name ? 
            '<span style="color: #10b981; font-size: 0.85em; margin-left: 5px;"><i class="fas fa-check-circle"></i> 已填地址</span>' : 
            '<span style="color: #f59e0b; font-size: 0.85em; margin-left: 5px;"><i class="fas fa-exclamation-circle"></i> 待填地址</span>';
        
        recordItem.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: bold; color: ${isRegistration ? '#3b82f6' : 'inherit'}">${record.prize_name}</span>
                <span style="font-size: 0.8em; color: #666;">${time}</span>
            </div>
            <div style="font-size: 0.9em; color: #888; margin-top: 2px; display: flex; justify-content: space-between; align-items: center;">
                <span>${status} - ${record.prize_value}</span>
                ${addressFilled}
            </div>
        `;
        
        domElements.recordsContainer.appendChild(recordItem);
    });
}

/**
 * ==================== 页面初始化 ====================
 */

/**
 * 页面加载完成后初始化
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM加载完成，开始初始化抽奖系统（智能角度计算版）');
    
    // 初始化抽奖系统
    initLotterySystem();
    
    // 绑定事件
    const drawButton = document.getElementById('drawButton');
    if (drawButton) {
        drawButton.addEventListener('click', drawLottery);
        drawButton.addEventListener('touchstart', function() {
            if (!this.disabled) this.style.transform = 'scale(0.95)';
        });
        drawButton.addEventListener('touchend', function() {
            if (!this.disabled) this.style.transform = '';
        });
    }
    
    if (domElements.manageAddressBtn) {
        const manageAddressHandler = async function() {
            // 检查登录状态
            if (!lotteryState.isLoggedIn) {
                showLoginRequired('管理地址前检查未登录');
                return;
            }

            try {
                // 1. 尝试找到已有记录（优先寻找包含地址信息的记录，其次寻找最近的记录）
                let targetRecord = lotteryState.records.find(r => r.receiver_name || r.receiver_phone || r.receiver_address);
                if (!targetRecord && lotteryState.records.length > 0) {
                    targetRecord = lotteryState.records[0];
                }

                // 2. 如果没有记录，则创建一个占位记录
                if (!targetRecord) {
                    const createResult = await api.createPlaceholderRecord();
                    if (createResult.success && createResult.record_id) {
                        // 创建成功，添加到本地列表
                        targetRecord = {
                            id: createResult.record_id,
                            prize_name: '信息登记',
                            prize_value: '用户主动登记收货信息',
                            is_win: false,
                            draw_time: new Date().toLocaleString(),
                            receiver_name: null,
                            receiver_phone: null,
                            receiver_address: null
                        };
                        lotteryState.records.unshift(targetRecord);
                    } else {
                        alert('初始化信息失败: ' + (createResult.message || '未知错误'));
                        return;
                    }
                }

                // 3. 打开模态框并回显
                if (targetRecord && targetRecord.id) {
                    openAddressModal(targetRecord.id);
                } else {
                    alert('获取记录失败，请刷新页面重试');
                }
            } catch (error) {
                console.error('处理地址管理点击失败:', error);
                alert('操作失败，请重试');
            }
        };

        domElements.manageAddressBtn.addEventListener('click', manageAddressHandler);
        domElements.manageAddressBtn.addEventListener('touchstart', function() {
            this.style.opacity = '0.7';
        });
        domElements.manageAddressBtn.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    }
    
    const modalClose = document.getElementById('closeResultBtn');
    if (modalClose) {
        modalClose.addEventListener('click', closeResultModal);
        modalClose.addEventListener('touchstart', function() {
            this.style.opacity = '0.7';
        });
        modalClose.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    }
    
    const resultModal = document.getElementById('resultModal');
    if (resultModal) {
        resultModal.addEventListener('click', function(event) {
            if (event.target === resultModal) {
                closeResultModal();
            }
        });
    }

    // 收货信息相关事件
    if (domElements.addressModalClose) {
        domElements.addressModalClose.addEventListener('click', closeAddressModal);
        domElements.addressModalClose.addEventListener('touchstart', function() {
            this.style.opacity = '0.7';
        });
        domElements.addressModalClose.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    }
    if (domElements.addressForm) {
        domElements.addressForm.addEventListener('submit', submitAddressForm);
    }
    
    // ESC键关闭模态框
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (domElements.resultModal && domElements.resultModal.style.display === 'flex') {
                closeResultModal();
            }
            if (domElements.addressModal && domElements.addressModal.style.display === 'flex') {
                closeAddressModal();
            }
        }
    });
    
    console.log('抽奖系统事件绑定完成');
});

/**
 * 添加全局错误处理
 */
window.addEventListener('error', function(event) {
    console.error('全局错误捕获:', event.error);
    if (domElements.resultModal) {
        showResultModal('系统错误', '发生未知错误，请刷新页面重试');
    }
});

/**
 * 添加页面可见性变化监听
 */
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        console.log('页面重新可见，刷新抽奖状态');
        if (lotteryState.isLoggedIn) {
            checkUserLoginStatus().then(() => {
                updateUI();
                // 页面重新显示时重置转盘
                resetWheelImmediately();
            });
        }
    }
});

console.log('抽奖系统脚本（智能角度计算版）加载完成');