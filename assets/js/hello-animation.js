/**
 * Hello动画效果实现
 * 基于single-letter-animation.html的实现，确保在古诗漂浮页面的进度条上方显示
 */

// 全局变量，供index.html使用
window.mainImageUrl = 'assets/images/he.webp'; // 已更换为 he.webp

/**
 * 为路径元素应用动画效果
 * @param {SVGPathElement} path - SVG路径元素
 * @param {number} delay - 动画延迟时间（秒）
 * @param {number} duration - 动画持续时间（秒）
 */
function animatePath(path, delay, duration = 1) {
    // 获取路径长度
    const pathLength = path.getTotalLength();
    
    // 设置初始状态
    path.style.strokeDasharray = pathLength;
    path.style.strokeDashoffset = pathLength;
    path.style.opacity = '0';
    path.style.transition = 'none';
    
    // 使用requestAnimationFrame确保更精确的定时
    setTimeout(() => {
        // 先显示元素
        path.style.opacity = '1';
        
        // 触发重排
        void path.offsetWidth;
        
        // 应用动画 - 使用统一的平滑缓动函数
        path.style.transition = `stroke-dashoffset ${duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.1s ease-in-out`;
        path.style.strokeDashoffset = '0';
    }, delay * 1000);
}

/**
 * 创建Hello动画
 * 供index.html调用的全局函数
 */
window.createHelloAnimation = function() {
    console.log('createHelloAnimation函数被调用');
    
    // 检查是否已存在hello动画元素
    var helloContainer = document.getElementById('hello-animation-container');
    
    // 重要修复：现在我们只使用index.html中已有的静态容器，不再创建新的容器
    if (helloContainer) {
        console.log('hello动画容器已存在（在loading-ink内部），清空内容并应用正确样式');
        // 清空现有内容
        helloContainer.innerHTML = '';
        
        // 为已存在的容器应用正确的样式，确保动画显示正常
        helloContainer.style.position = 'fixed'; // 保持固定定位，确保在所有设备上都能正确居中
        helloContainer.style.top = '25%'; // 调整为正中偏上位置
        helloContainer.style.left = '50%';
        helloContainer.style.transform = 'translate(-50%, -50%)'; // 确保水平和垂直居中
        helloContainer.style.zIndex = '9999'; // 保持高z-index，确保在所有内容上方
        helloContainer.style.pointerEvents = 'none';
        helloContainer.style.width = '100%';
        helloContainer.style.textAlign = 'center';
    } else {
        console.log('未找到hello动画容器，创建备用容器');
        // 创建备用容器
        helloContainer = document.createElement('div');
        helloContainer.id = 'hello-animation-container';
        
        // 设置备用容器样式
        helloContainer.style.position = 'fixed';
        helloContainer.style.top = '25%'; // 调整为正中偏上位置
        helloContainer.style.left = '50%';
        helloContainer.style.transform = 'translate(-50%, -50%)'; // 确保水平和垂直居中
        helloContainer.style.zIndex = '99999'; // 提高z-index，确保绝对优先显示
        helloContainer.style.pointerEvents = 'none';
        helloContainer.style.width = '100%';
        helloContainer.style.textAlign = 'center';
        
        // 立即添加到body
        document.body.appendChild(helloContainer);
    }
    
    // 确保容器引用正确，防止创建多个容器
    window.__helloAnimationContainerRef = helloContainer;
    
    // 创建致敬文字容器
    var greetingContainer = document.createElement('div');
    greetingContainer.style.textAlign = 'center';
    greetingContainer.style.marginBottom = '20px';
    
    // 添加主致敬文字
    var mainGreeting = document.createElement('div');
    mainGreeting.textContent = '冠恩先生向您致敬';
    mainGreeting.style.fontSize = '24px';
    mainGreeting.style.fontWeight = 'bold';
    mainGreeting.style.color = '#333';
    mainGreeting.style.marginBottom = '10px';
    mainGreeting.style.fontFamily = '"KaiTi", "SimHei", serif';
    
    // 添加引用文字
    var quoteText = document.createElement('div');
    quoteText.textContent = '宇宙由故事构成，而非原子';
    quoteText.style.fontSize = '16px';
    quoteText.style.color = '#666'; // 浅色文字
    quoteText.style.fontFamily = '"KaiTi", "SimHei", serif';
    
    // 将文本添加到容器
    greetingContainer.appendChild(mainGreeting);
    greetingContainer.appendChild(quoteText);
    
    // 将致敬文字容器添加到动画容器
    helloContainer.appendChild(greetingContainer);
    
    // 创建SVG元素
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    svg.setAttribute('viewBox', '0 0 638 200');
    svg.setAttribute('fill', 'none');
    svg.className.baseVal = 'hello-svg';
    
    // 添加标题
    var title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
    title.textContent = 'Hello - 单字母逐个显示动画';
    svg.appendChild(title);

    // 定义每个字母的路径和颜色
    var paths = [
        {
            id: 'hello-h',
            d: 'M8.69214 166.553C36.2393 151.239 61.3409 131.548 89.8191 98.0295C109.203 75.1488 119.625 49.0228 120.122 31.0026C120.37 17.6036 113.836 7.43883 101.759 7.43883C88.3598 7.43883 79.9231 17.6036 74.7122 40.9363C69.005 66.5793 64.7866 96.0036 54.1166 190.356',
            color: '#FF3366' // 粉色
        },
        {
            id: 'hello-e',
            d: 'M55.1624 181.135C60.6251 133.114 81.4118 98.0479 107.963 98.0479C123.844 98.0479 133.937 110.703 131.071 128.817C129.457 139.487 127.587 150.405 125.408 163.06C122.869 178.941 130.128 191.348 152.122 191.348',
            color: '#FF9900' // 橙色
        },
        {
            id: 'hello-l1',
            d: 'M152.122 191.348C184.197 191.348 219.189 173.523 237.097 145.915C243.198 136.509 245.68 128.073 245.9288 119.884C246.176 104.996 237.739 93.8296 222.851 93.8296C203.992 93.8296 189.6 115.17 189.6 142.465C189.6 171.745 205.481 192.341 239.208 192.341',
            color: '#FFCC00' // 黄色
        },
        {
            id: 'hello-l2',
            d: 'M239.208 192.341C285.066 192.341 335.86 137.292 359.199 75.8585C365.788 58.513 368.26 42.4065 368.26 31.1512C368.26 17.8057 364.042 7.55823 352.131 7.55823C340.469 7.55823 332.777 16.6141 325.829 30.9129C317.688 47.4967 311.667 71.4162 309.203 98.4549C303 166.301 316.896 191.348 349.936 191.348',
            color: '#33CC33' // 绿色
        },
        {
            id: 'hello-o',
            d: 'M349.936 191.348C390 191.348 434.542 135.534 457.286 75.6686C463.803 58.513 466.275 42.4065 466.275 31.1512C466.275 17.8057 462.057 7.55823 450.146 7.55823C438.484 7.55823 430.792 16.6141 423.844 30.9129C415.703 47.4967 409.682 71.4162 407.218 98.4549C401.015 166.301 414.911 191.348 444.416 191.348C473.874 191.348 489.877 165.67 499.471 138.402C508.955 111.447 520.618 94.8221 544.935 94.8221C565.035 94.8221 580.916 109.71 580.916 137.75C580.916 168.768 560.792 192.093 535.362 192.341C512.984 192.589 498.285 174.475 499.774 147.179C501.511 116.907 519.873 94.8221 543.943 94.8221C557.839 94.8221 569.51 100.999 578.682 107.725C603.549 125.866 622.709 114.656 630.047 96.7186',
            color: '#3366FF' // 蓝色
        }
    ];

    // 字母配置，实现真正连续的动画效果
    const lettersConfig = [
        { id: 'hello-h', startTime: 0, duration: 1.1 },
        { id: 'hello-e', startTime: 0.75, duration: 1 }, // 在h动画进行到80%时开始e
        { id: 'hello-l1', startTime: 1.55, duration: 1 }, // 在e动画进行到80%时开始l1
        { id: 'hello-l2', startTime: 2.4, duration: 1.1 }, // 在l1动画进行到80%时开始l2
        { id: 'hello-o', startTime: 3.4, duration: 3.7 }  // 在l2动画进行到80%时开始o
    ];

    // 添加路径到SVG
    paths.forEach(pathData => {
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.id = pathData.id;
        path.setAttribute('d', pathData.d);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', pathData.color);
        path.setAttribute('stroke-width', '14.8883');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        path.style.strokeDasharray = '1000';
        path.style.strokeDashoffset = '1000';
        path.style.opacity = '0';
        svg.appendChild(path);
    });

    // 添加SVG到容器
    helloContainer.appendChild(svg);

    // 启动动画
    lettersConfig.forEach(letter => {
        const path = document.getElementById(letter.id);
        if (path) {
            animatePath(path, letter.startTime, letter.duration);
        }
    });

    // 重要修复：不再添加窗口大小变化监听器，因为容器在loading-ink内部已经是居中的
    console.log('Hello动画已添加到loading-ink内部的容器中，将随loading-ink一起消失');

    // 不再需要z-index检查，因为容器在loading-ink内部
    console.log('由于容器在loading-ink内部，不需要额外的z-index检查');
    
    // 清除任何可能存在的z-index检查定时器
    if (window.helloAnimationInterval) {
        clearInterval(window.helloAnimationInterval);
        window.helloAnimationInterval = null;
        console.log('清除可能存在的z-index检查定时器');
    }
};

/**
 * 创建致敬文字
 * 供index.html调用的全局函数
 */
window.createGreetingText = function() {
    console.log('createGreetingText函数被调用');
};

/**
 * 强制创建动画
 * 供index.html调用的全局函数
 */
window.forceCreateAnimation = function(env) {
    console.log('forceCreateAnimation函数被调用', env);
    window.createHelloAnimation();
};

/**
 * 显示页面内容
 * 供index.html调用的全局函数
 */
window.showPageContent = function() {
    console.log('showPageContent函数被调用');
    
    // 如果mainUpdateProgress可用，则更新进度为100%
    if (window.mainUpdateProgress && typeof window.mainUpdateProgress === 'function') {
        window.mainUpdateProgress(100);
    }
    
    // 将延迟从 2000ms 缩短为 800ms
    setTimeout(function() {
        // 隐藏加载动画
        var loadingInk = document.getElementById('loading-ink');
        if (loadingInk) {
            loadingInk.classList.add('hidden');
            // 动画结束后完全移除加载层
            setTimeout(function() {
                loadingInk.style.display = 'none';
            }, 800);
        }
        
        // 隐藏Hello动画容器，确保它与进度条页面一起消失
        // 增强版本：尝试通过多种方式获取容器
        var helloContainer = null;
        
        // 1. 先尝试使用全局引用
        if (window.__helloAnimationContainerRef) {
            helloContainer = window.__helloAnimationContainerRef;
            console.log('通过全局引用找到Hello动画容器');
        }
        // 2. 如果全局引用不存在，尝试通过ID查找
        else {
            helloContainer = document.getElementById('hello-animation-container');
            if (helloContainer) {
                console.log('通过ID找到Hello动画容器');
            }
            // 3. 尝试查找所有可能的容器（可能由于某些原因有重复容器）
            else {
                var containers = document.querySelectorAll('[id="hello-animation-container"]');
                if (containers.length > 0) {
                    helloContainer = containers[0];
                    console.log('找到多个容器，使用第一个');
                }
            }
        }
        
        // 如果找到容器，立即应用淡出动画并移除
        if (helloContainer) {
            console.log('应用淡出动画到Hello动画容器');
            // 设置更明显的淡出动画效果
            helloContainer.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            helloContainer.style.opacity = '0';
            helloContainer.style.transform = 'translate(-50%, -50%) scale(0.8)';
            
            // 立即降低z-index，确保它不再遮挡其他元素
            helloContainer.style.zIndex = '1';
            
            // 动画结束后完全移除容器
            setTimeout(function() {
                if (helloContainer && helloContainer.parentNode) {
                    console.log('从DOM中完全移除Hello动画容器');
                    helloContainer.parentNode.removeChild(helloContainer);
                    // 清除全局引用
                    window.__helloAnimationContainerRef = null;
                }
            }, 500);
        } else {
            console.log('未找到Hello动画容器，但仍继续执行其他操作');
        }
        
        // 额外保险：清除任何与Hello动画相关的定时器
        if (window.helloAnimationInterval) {
            clearInterval(window.helloAnimationInterval);
            window.helloAnimationInterval = null;
            console.log('清除Hello动画相关的定时器');
        }
        
        // 显示主页面内容
        var pageContent = document.querySelector('.page-content');
        if (pageContent) {
            pageContent.classList.add('show');
        }
        
        // 触发页面初始化逻辑
        // 修复：由于 index-logic.js 中的 initPage 是局部函数，
        // 我们通过触发自定义事件来让 index-logic.js 执行初始化
        window.dispatchEvent(new CustomEvent('pageShowReady'));
    }, 800);
};

/**
 * 触发mainImageLoaded事件
 * 通知index.html图片已加载完成
 */
function triggerMainImageLoaded() {
    setTimeout(function() {
        try {
            var event = new CustomEvent('mainImageLoaded', {
                detail: { url: window.mainImageUrl }
            });
            window.dispatchEvent(event);
        } catch (e) {
            // 兼容旧浏览器的降级方案
            console.log('CustomEvent不支持，使用替代方案');
        }
    }, 1000);
}

// 确保DOM加载完成后执行初始化
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM加载完成，hello-animation.js初始化');
    
    // 触发图片加载完成事件
    triggerMainImageLoaded();
    
    // 立即创建Hello动画，不依赖外部调用，确保在进度条页面中最先显示
    setTimeout(function() {
        console.log('100ms延迟后，优先创建Hello动画以确保它最先显示');
        window.createHelloAnimation();
    }, 100);
});

// 重要修复：不再在load事件中创建备用动画，避免首页显示后再次创建动画
window.addEventListener('load', function() {
    console.log('页面完全加载后，检查hello动画是否已正确隐藏');
    
    // 确保在首页加载完成后，hello动画容器已经被正确处理
    var helloContainer = document.getElementById('hello-animation-container');
    if (helloContainer && window.criticalResourcesLoaded) {
        console.log('首页已加载完成，确保hello动画容器已被清除');
        if (helloContainer.parentNode) {
            helloContainer.parentNode.removeChild(helloContainer);
        }
    }
});
// 为了确保动画一定能显示，添加一个全局方法供用户手动触发
window.manualCreateHello = function() {
    console.log('用户手动触发创建Hello动画');
    window.createHelloAnimation();
};
