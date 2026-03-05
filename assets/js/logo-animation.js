/**
 * logo-animation.js - 实现logo旋转文本弧效果（响应式设计）
 * 在logoBox中创建rotating-logo-wrapper和rotating-text-arc元素
 * 实现文本环绕和旋转动画，支持桌面端和移动端显示
 */

// 等待DOM加载完成
window.addEventListener('DOMContentLoaded', function() {
    // 获取logoBox元素和logo-img元素
    const logoBox = document.getElementById('logoBox');
    const logoImg = logoBox.querySelector('.logo-img img');
    
    if (!logoBox || !logoImg) {
        console.error('未找到logoBox或logo图片元素');
        return;
    }
    
    // 保存原始logo内容
    const originalLogoContent = logoBox.innerHTML;
    
    // 创建rotating-logo-wrapper元素
    const rotatingWrapper = document.createElement('div');
    rotatingWrapper.className = 'rotating-logo-wrapper';
    
    // 确保图片已加载完成，以便获取准确尺寸
    function setupRotation() {
        // 设置rotating-logo-wrapper尺寸，考虑响应式设计
        const logoBoxRect = logoBox.getBoundingClientRect();
        // 根据屏幕宽度确定合适的容器大小
        const isMobile = window.innerWidth <= 768;
        const baseSize = isMobile ? 60 : 80; // 移动端60px，桌面端80px
        const containerSize = Math.min(logoBoxRect.width, logoBoxRect.height, baseSize);
        
        rotatingWrapper.style.width = `${containerSize}px`;
        rotatingWrapper.style.height = `${containerSize}px`;
        
        // 创建rotating-text-arc元素
        const textArc = document.createElement('div');
        textArc.className = 'rotating-text-arc';
        
        // 文本弧内容 - 环绕的文字
        const arcText = '冠恩超人•无限进步•西西弗斯•';
        
        // 为每个字符创建一个div，形成圆弧排列
        for (let i = 0; i < arcText.length; i++) {
            const charDiv = document.createElement('div');
            // 计算旋转角度，确保文字均匀分布在圆弧上
            // 移动端可以适当调整角度，使文字更紧凑
            const isMobile = window.innerWidth <= 768;
            const angleStep = isMobile ? 28 : 24; // 移动端28度，桌面端24度一个字符
            const angle = (i * angleStep) % 360;
            
            charDiv.className = 'text-arc-char-container';
            charDiv.style.transform = `rotate(${angle}deg)`;
            
            const span = document.createElement('span');
            span.className = 'text-arc-char';
            span.textContent = arcText[i];
            
            charDiv.appendChild(span);
            textArc.appendChild(charDiv);
        }
        
        // 创建central-logo元素，用于放置原始logo内容
        const centralLogo = document.createElement('div');
        centralLogo.className = 'central-logo';
        centralLogo.innerHTML = originalLogoContent;
        
        // 组合元素结构
        rotatingWrapper.appendChild(textArc);
        rotatingWrapper.appendChild(centralLogo);
        
        // 清空logoBox并添加新的结构
        logoBox.innerHTML = '';
        logoBox.appendChild(rotatingWrapper);
        
        // 启动旋转动画
        startRotationAnimation(textArc);
    }
    
    // 如果图片已加载完成，则直接设置；否则等待加载完成
    if (logoImg.complete) {
        setupRotation();
    } else {
        logoImg.addEventListener('load', setupRotation);
    }
    
    // 添加CSS样式（如果页面中没有这些样式）
    ensureCSSLoaded();
    
    // 添加窗口大小变化监听，实现响应式调整
    window.addEventListener('resize', function() {
        // 移除现有的rotatingWrapper
        const existingWrapper = logoBox.querySelector('.rotating-logo-wrapper');
        if (existingWrapper) {
            logoBox.removeChild(existingWrapper);
        }
        
        // 重新创建rotatingWrapper
        const newRotatingWrapper = document.createElement('div');
        newRotatingWrapper.className = 'rotating-logo-wrapper';
        
        // 保存原始内容
        const originalContent = originalLogoContent;
        
        // 重新创建旋转结构
        function createNewRotation() {
            // 设置新wrapper尺寸
            const logoBoxRect = logoBox.getBoundingClientRect();
            const isMobile = window.innerWidth <= 768;
            const baseSize = isMobile ? 60 : 80;
            const containerSize = Math.min(logoBoxRect.width, logoBoxRect.height, baseSize);
            
            newRotatingWrapper.style.width = `${containerSize}px`;
            newRotatingWrapper.style.height = `${containerSize}px`;
            
            // 创建rotating-text-arc元素
            const textArc = document.createElement('div');
            textArc.className = 'rotating-text-arc';
            
            // 文本弧内容
            const arcText = '冠恩超人•无限进步•西西弗斯•';
            
            // 为每个字符创建div
            for (let i = 0; i < arcText.length; i++) {
                const charDiv = document.createElement('div');
                const isMobile = window.innerWidth <= 768;
                const angleStep = isMobile ? 28 : 24;
                const angle = (i * angleStep) % 360;
                
                charDiv.className = 'text-arc-char-container';
                charDiv.style.transform = `rotate(${angle}deg)`;
                
                const span = document.createElement('span');
                span.className = 'text-arc-char';
                span.textContent = arcText[i];
                
                charDiv.appendChild(span);
                textArc.appendChild(charDiv);
            }
            
            // 创建central-logo元素
            const centralLogo = document.createElement('div');
            centralLogo.className = 'central-logo';
            centralLogo.innerHTML = originalContent;
            
            // 组合元素结构
            newRotatingWrapper.appendChild(textArc);
            newRotatingWrapper.appendChild(centralLogo);
            
            // 添加到logoBox
            logoBox.appendChild(newRotatingWrapper);
            
            // 启动旋转动画
            startRotationAnimation(textArc);
        }
        
        // 执行重建
        createNewRotation();
    });
});

/**
 * 确保必要的CSS样式已加载
 */
function ensureCSSLoaded() {
    // 检查是否已有这些样式，如果没有则添加
    if (!document.querySelector('#logo-animation-styles')) {
        const style = document.createElement('style');
        style.id = 'logo-animation-styles';
        style.textContent = `
            .rotating-logo-wrapper {
                background: transparent !important;
                background-color: transparent !important;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 80px;
                height: 80px;
                transition: transform 0.3s ease;
                overflow: visible;
                /* 整体向右偏移2px */
                transform: translateX(2px);
            }
            
            .rotating-logo-wrapper:hover {
                transform: translateX(2px) scale(1.1);
            }
            
            .rotating-text-arc {
                position: absolute;
                width: 70px; /* 减小半径 */
                height: 70px; /* 减小半径 */
                top: 50%;
                left: 50%;
                margin-top: -35px; /* 调整为半径的一半 */
                margin-left: -35px; /* 调整为半径的一半 */
                transform-origin: center center;
                will-change: transform;
                pointer-events: none;
            }
            
            .text-arc-char-container {
                position: absolute;
                width: 100%;
                height: 100%;
                transform-origin: center center;
                top: 0;
                left: 0;
            }
            
            .text-arc-char {
                position: absolute;
                top: -8px; /* 减小距离，让文字更靠近中心 */
                left: 50%;
                transform: translateX(-50%);
                font-size: 10px; /* 稍微调小字体以适应更小的半径 */
                font-weight: bold;
                color: #333;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
                white-space: nowrap;
            }
            
            .central-logo {
                position: relative;
                z-index: 2;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                transition: transform 0.3s;
            }
            
            .rotating-logo-wrapper:hover .central-logo {
                transform: scale(1.05);
            }
            
            .logo-img {
                position: relative;
                z-index: 2;
                transition: transform 0.3s;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                overflow: hidden;
                border: 2px solid rgba(255, 255, 255, 0.8);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }
            
            .logo-img img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s;
            }
            
            .rotating-logo-wrapper:hover .logo-img img {
                transform: scale(1.1);
            }
            
            .logo-text {
                font-size: 9px;
                margin-top: 4px;
                text-align: center;
                font-weight: 600;
                color: #333;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            
            .logo-tooltip {
                font-size: 7px;
                opacity: 0;
                position: absolute;
                bottom: -20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 2px 6px;
                border-radius: 4px;
                white-space: nowrap;
                pointer-events: none;
                transition: opacity 0.3s;
                z-index: 10;
            }
            
            .rotating-logo-wrapper:hover .logo-tooltip {
                opacity: 1;
            }
            
            .rotating-logo-wrapper:hover .text-arc-char {
                color: #000;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            /* 响应式设计 - 移动端适配 */
            @media screen and (max-width: 768px) {
                .rotating-logo-wrapper {
                    width: 60px;
                    height: 60px;
                    /* 移动端也保持向右偏移2px */
                    transform: translateX(6px);
                }
                
                .rotating-logo-wrapper:hover {
                    transform: translateX(2px) scale(1.1);
                }
                
                .rotating-text-arc {
                    width: 50px; /* 移动端更小的半径 */
                    height: 50px; /* 移动端更小的半径 */
                    margin-top: -25px; /* 调整为半径的一半 */
                    margin-left: -25px; /* 调整为半径的一半 */
                    /* 确保移动端固定中心点 */
                    transform-origin: center center !important;
                }
                
                .text-arc-char {
                    font-size: 8px; /* 移动端更小的字体 */
                    top: -6px; /* 移动端调整文字位置 */
                }
                
                .logo-img {
                    width: 30px !important;
                    height: 30px !important;
                }
                
                .logo-text {
                    font-size: 7px;
                    margin-top: 2px;
                }
            }
            
            /* 小屏手机适配 */
            @media screen and (max-width: 480px) {
                .rotating-logo-wrapper {
                    width: 50px;
                    height: 50px;
                    transform: translateX(2px);
                }
                
                .rotating-logo-wrapper:hover {
                    transform: translateX(2px) scale(1.1);
                }
                
                .rotating-text-arc {
                    width: 45px;
                    height: 45px;
                    margin-top: -22.5px;
                    margin-left: -22.5px;
                    transform-origin: center center !important;
                }
                
                .text-arc-char {
                    font-size: 7px;
                    top: -5px;
                }
                
                .logo-img {
                    width: 25px !important;
                    height: 25px !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * 启动旋转动画函数
 * 确保圆环在固定位置匀速旋转，且鼠标悬停时不会停止
 */
function startRotationAnimation(textArc) {
    let rotation = 0;
    // 响应式旋转速度 - 移动端稍微快一点
    const isMobile = window.innerWidth <= 768;
    const duration = isMobile ? 15000 : 20000; // 移动端15秒一圈，桌面端20秒一圈
    let startTime = performance.now();
    
    function rotate(currentTime) {
        const elapsed = currentTime - startTime;
        // 使用线性插值计算当前旋转角度，确保匀速旋转
        rotation = (elapsed / duration) * 360 % 360;
        textArc.style.transform = `rotate(${rotation}deg)`;
        requestAnimationFrame(rotate);
    }
    
    // 启动旋转动画
    requestAnimationFrame(rotate);
}