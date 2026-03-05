
// 古诗数组
const sentences = [
  "日有熹,月有光,富且昌,寿而康,新春嘉平,长乐未央",
  "相信另一种可能",
  "不思量，自难忘",
  "人生若只如初见",
  "如根深种，如浪翻涌",
  "我记不生活再过去，也不生活在未来",
  "这一切都等着你去探索",
  "落霞与孤鹜齐飞，秋水共长天一色",
  "为你千千万万遍",
  "什么是好，什么又是不好？",
  "青青子衿，悠悠我心",
  "追光而遇，沐光而行",
  "成长就是不停的遇到错误，不停的改变，越变越好",
  "做你认为正确的事情",
  "不必成为别人，只需成为自己",
  "等待和希望",
  "过去都是假的，回忆没有归路",
  "重要的不是过去，而是未来",
  "生活过得好一点，比大多数更宏伟",
  "明天又是新的一天了",
  "满纸荒唐言，一把辛酸泪",
  "如果这个世界本身足够荒唐，那到底什么才算疯狂",
  "宇宙由故事构成，而非原子",
  "只有依靠自己，胜算才更大",
  "我翻山越岭而来，只为更好的活",
  "你太年轻，太理想主义了，以为每个问题都能找到答案",
  "游隼的眼睛里，没有过去，也没有未来，只有此刻",
  "在荒野中长大的人，骨子里都刻着自由的密码",
  "我不可能只是仰望你，我要成为和你并肩的人",
  "在我身上有一个不可战胜的夏天",
  "你爱上的是一个臆想出来的形象",
  "我们必须想象西西弗斯是幸福的",
  "有一天，我一共看了四十四次日落",
  "反抗痛苦的最好方式是爱生活",
  "我们脚下才算万物生长之地",
  "相爱太短，遗忘太长",
  "命里有时终须有"
];
const maxFloating = 30;
let floatingElements = [];

/**
 * 计算网站运行天数
 * @param {string} startDate - 开始日期，格式为YYYY-MM-DD
 * @returns {number} 运行天数
 */
function calculateRunningDays(startDate) {
    const start = new Date(startDate);
    const now = new Date();
    
    // 设置时间为同一天的00:00:00，避免时间差异影响天数计算
    start.setHours(0, 0, 0, 0);
    now.setHours(0, 0, 0, 0);
    
    const timeDiff = now.getTime() - start.getTime();
    const daysDiff = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
    
    return daysDiff >= 0 ? daysDiff : 0;
}

/**
 * 更新网站运行天数显示
 */
function updateRunningDays() {
    const startDate = '2025-09-09'; // 网站开始运行日期
    const days = calculateRunningDays(startDate);
    
    const daysElement = document.getElementById('running-days');
    if (daysElement) {
        daysElement.textContent = days;
    }
}

// 页面加载完成后更新运行天数
document.addEventListener('DOMContentLoaded', function() {
    updateRunningDays();
});

// 每天午夜更新一次天数
setInterval(function() {
    const now = new Date();
    if (now.getHours() === 0 && now.getMinutes() === 0) {
        updateRunningDays();
    }
}, 60000); // 每分钟检查一次
const uniformSpeed = 0.3;

function random(min, max) {
    return Math.random() * (max - min) + min;
}

// 创建一个漂浮文字
function createFloatingText() {
    if (floatingElements.length >= maxFloating) return;
    const div = document.createElement("div");
    div.className = "floating-text";
    div.innerText = sentences[Math.floor(Math.random() * sentences.length)];
    
    // 伪随机分布，避免重叠，确保屏幕左右都有文字
    // 将屏幕分成左中右三个区域，每个区域随机生成
    const region = Math.floor(Math.random() * 3);
    let x;
    if (region === 0) { // 左区域
        x = random(50, window.innerWidth / 3 - 100);
    } else if (region === 1) { // 中区域
        x = random(window.innerWidth / 3, window.innerWidth * 2 / 3 - 100);
    } else { // 右区域
        x = random(window.innerWidth * 2 / 3, window.innerWidth - 150);
    }
    
    const y = window.innerHeight + 20;
    // 随机速度，创造错落有致的效果
    const speed = 0.5 + Math.random() * 1; // 0.5-1.5之间的随机速度
    
    div.style.left = x + "px";
    div.style.top = y + "px";
    
    document.getElementById('loading-ink').appendChild(div);
    floatingElements.push({el: div, y: y, speed: speed});
}

// 立即填充更多文字，保证一进页面就有全屏漂浮
for (let i = 0; i < 12; i++) {
    createFloatingText();
}

function animate() {
    for (let i = floatingElements.length - 1; i >= 0; i--) {
        const obj = floatingElements[i];
        obj.y -= obj.speed;
        obj.el.style.top = obj.y + "px";
        obj.el.style.opacity = Math.min(1, (window.innerHeight - obj.y) / window.innerHeight);

        if (obj.y + obj.el.offsetHeight < 0) {
            obj.el.remove();
            floatingElements.splice(i, 1);
            createFloatingText(); // 保持漂浮数量恒定
        }
    }
    requestAnimationFrame(animate);
}

// 平滑地持续生成文字，确保无限漂浮，但频率降低一些
setInterval(createFloatingText, 800); // 每 0.8 秒生成一个

// 启动动画
animate();

// 内容文本（中/英）
// 内容文本（中/英）
const content = {
    chinese:{
        mainTitle:"想象另一种可能",
        welcomeText:"冠恩先生欢迎您",
        home:"首页",
        language:"EN/中",
        tooltip: "虽不能至，心向往之",
        galleryTitle: "对于伟大的摄影作品，重要的是情深，而不是景深",
        centerText: `我想起现实世界是多么广阔，<br>
                充满了纷繁的希望与恐惧，<span class="mobile-break">刺激与兴奋</span><br>
                就等那些勇敢的人们踏入这片天地，<br>
                在生活的危险之中寻找真知。<br>
                <span class="quote-author">----夏洛蒂·勃朗特</span>`,
        wenan: "生活远比戏剧更荒诞与沉重，但荒诞不是让我们绝望，而是让我们重新滋生勇气与信心。", 
        contactGuanen: "联系冠恩",
        bookStore: "冠恩书屋",
        culture: "文化分享",
        art: "艺术",
        suggestion: "建议作者",
        bookCrossing: "书籍漂流",
        lottery: "抽奖",
        monopoly: "鹏鹏大富翁",
        login: "登录",
        userCenter: "用户中心",
        goToBookStore: "前往冠恩书屋",
        skipLoading: "等待和希望",
        icp: "互联网ICP备案：",
        footerAuthor: "冠恩",
        copyright: "版权所有，任何形式转载请联系作者",
        agreement: "用户协议",
        questionnaire: "问卷调查",
        privacy: "隐私政策"
    },
    english:{
        mainTitle:"Guanen Superman",
        welcomeText:"Hello, Mr. Guanen says hello!",
        home:"Home",
        language:"EN/中",
        tooltip: "Although we cannot reach it, we yearn for it in our hearts",
        galleryTitle: "What matters is emotional depth, not depth of field",
        centerText: `I thought of how vast the real world is,<br>
filled with a riot of hopes and fears, thrills and excitements,<br>just waiting for those brave enough to step into it and<br>seek truth amidst the dangers of life.<br>
<span class="quote-author">----Charlotte Bronte</span>`,
        contactGuanen: "@Mr.guanen",
        bookStore: "Guanen-Bookstor",
        culture: "Resonance",
        art: "Art",
        suggestion: "Suggestions",
        bookCrossing: "Book Crossing",
        lottery: "Lucky draw",
        monopoly: "Monopoly",
        login: "Login",
        userCenter: "User Center",
        wenan: "Life is far more absurd and heavy than drama, but absurdity does not make us despair, but makes us regain courage and confidence.",
        goToBookStore: "Go to Bookstore",
        skipLoading: "Waiting and Hope",
        icp: "ICP Filing: ",
        footerAuthor: "Guanen",
        copyright: "All rights reserved. Please contact the author for any form of reproduction.",
        agreement: "User Agreement",
        questionnaire: "Questionnaire",
        privacy: "Privacy Policy"
    }
};
window.content = content;
window.currentLanguage = 'chinese';
let currentTypingCancel = null;

/* 打字机效果（安全清理旧 cursor） */
function typeText(text){
    if (!text) return; // 安全检查：如果 text 为空则不执行
    if(currentTypingCancel) currentTypingCancel();
    const welcomeText = document.getElementById('welcomeText');
    if (!welcomeText) return; // 安全检查：如果元素不存在则不执行
    welcomeText.innerHTML = ''; // 先清空（防止残留）
    let i = 0, stopped = false;
    currentTypingCancel = () => stopped = true;
    const cursor = document.createElement('span');
    cursor.className = 'cursor';
    welcomeText.appendChild(cursor);

    function typing(){
        if(stopped){ try{ cursor.remove(); }catch(e){}; return; }
        if(i < text.length) {
            cursor.insertAdjacentText('beforebegin', text.charAt(i));
            i++;
            setTimeout(typing, 200); // 调整古诗速度，从90ms增加到200ms，减慢显示速度
        } else {
            setTimeout(()=>{ if(!stopped) try{ cursor.remove(); }catch(e){}; currentTypingCancel = null; }, 600);
        }
    }
    typing();
}

/* 装饰"你好"气泡 */
const countriesHello = ["Hello","你好","Hola","Bonjour","Hallo","Ciao","こんにちは","안녕하세요","Привет","مرحبا","Merhaba","Здравствуйте!"];
function createHelloBubble() {
    const container = document.getElementById('decorativeHellos');
    if(!container) return;
    const bubble = document.createElement('div');
    bubble.className = 'hello-bubble';
    bubble.textContent = countriesHello[Math.floor(Math.random()*countriesHello.length)];

    // 计算标题宽度与两侧空白区域，仍尽量保持在两侧
    const fullscreenWidth = container.clientWidth || window.innerWidth;
    const titleEl = document.getElementById('mainTitle');
    const titleWidth = titleEl ? titleEl.offsetWidth : 300;
    const safeGap = 50;
    const halfSpace = Math.max((fullscreenWidth - titleWidth)/2 - safeGap, 20);
    const useLeft = Math.random() < 0.5;
    const leftRegion = Math.random() * halfSpace;
    const rightRegion = Math.random() * halfSpace;
    bubble.style.left = useLeft ? `${leftRegion}px` : `${(fullscreenWidth+titleWidth)/2 + rightRegion}px`;
    bubble.style.top = `${Math.random()*50 + 180}px`;

    container.appendChild(bubble);
    setTimeout(()=> {
        try{ bubble.remove(); } catch(e){}
    }, 5000);
}

// 增强的复制函数
function copyToClipboard(text) {
    // 方法1: 使用现代Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).then(() => {
            return true;
        }).catch(err => {
            console.error('Clipboard API failed:', err);
            return false;
        });
    }
    
    // 方法2: 使用传统的execCommand方法
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        return successful;
    } catch (err) {
        document.body.removeChild(textArea);
        console.error('execCommand failed:', err);
        return false;
    }
}

// 复制文本处理 - 简化版，移除提示
function handleCopy() {
    const textToCopy = "Mr_Guanen";
    copyToClipboard(textToCopy);
}

// 页面加载后监听滚动，触发淡入放大
function observeCenterText() {
    const centerText = document.getElementById('centerText');
    if(!centerText) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if(entry.isIntersecting) {
                centerText.classList.add('show');
            }
        });
    }, {
        threshold: 0.5 // 当文字进入屏幕 50% 时触发
    });

    observer.observe(centerText);
}

// 滚动到底部函数
function scrollToBottom() {
    window.scrollTo({
        top: document.body.scrollHeight,
        behavior: 'smooth'
    });
}

/* DOMContentLoaded 初始化 */
window.addEventListener('DOMContentLoaded', ()=> {
    // 初始化文本
    typeText(content[currentLanguage].welcomeText);

    const mainTitle = document.getElementById('mainTitle');
    const welcomeContainer = document.getElementById('welcomeContainer');
    const galleryTitle = document.getElementById('galleryTitle');
    const logoBox = document.getElementById('logoBox');
    const homeBtn = document.getElementById('homeBtn');
    const languageBtn = document.getElementById('languageBtn');
    const cultureLink = document.getElementById('cultureLink');
    const artLink = document.getElementById('artLink');
    const suggestionLink = document.getElementById('suggestionLink');
    const toBottomBtn = document.getElementById('toBottomBtn');
    const toBottomBtnFixed = document.getElementById('toBottomBtnFixed');
    const tooltip = document.getElementById('logoTooltip');
    const horizontalScroll = document.getElementById('horizontalScroll');
    const customScrollbar = document.getElementById('customScrollbar');
    const scrollThumb = document.getElementById('scrollThumb');
    const copyText = document.getElementById('copyText');
    const contactLink = document.getElementById('contactLink');
    const authorPopup = document.getElementById('authorPopup');
    const highlightQuote = document.getElementById('highlightQuote');
    const icpText = document.getElementById('icpText');
    const footerAuthor = document.getElementById('footerAuthor');
    const copyrightText = document.getElementById('copyrightText');
    const agreementLink = document.getElementById('agreementLink');
    const footerQuestionnaireLink = document.getElementById('footerQuestionnaireLink');
    const privacyLink = document.getElementById('privacyLink');
    const articleLink = document.getElementById('articleLink');
    const lotteryBtn = document.getElementById('lotteryBtn');
    const bookCrossingBtn = document.getElementById('bookCrossingBtn');
    const paokuBtn = document.getElementById('paokuBtn');
    const monopolyBtn = document.getElementById('monopolyBtn');
    const loginBtn = document.getElementById('loginBtn');
    const aiChatBtn = document.getElementById('aiChatBtn');
    
    let isLoggedIn = false;

    // 检查登录状态
    async function checkLoginStatus() {
        try {
            const response = await fetch('/api/check_login.php', { credentials: 'include' });
            const data = await response.json();
            isLoggedIn = data.logged_in;
            updateLoginButton();
        } catch (error) {
            console.error('Error checking login status:', error);
        }
    }

    function updateLoginButton() {
        if (!loginBtn) return;
        if (isLoggedIn) {
            loginBtn.textContent = window.content[window.currentLanguage].userCenter;
        } else {
            loginBtn.textContent = window.content[window.currentLanguage].login;
        }
    }

    // 初始化时检查登录状态
    checkLoginStatus();
    
    // 初始化quote-section中的wenan文本
    if(highlightQuote) {
        highlightQuote.textContent = window.content[window.currentLanguage].wenan;
    }

    // 初始化页脚文本
    if(icpText) icpText.textContent = window.content[window.currentLanguage].icp;
    if(footerAuthor) footerAuthor.textContent = window.content[window.currentLanguage].footerAuthor;
    if(copyrightText) copyrightText.textContent = window.content[window.currentLanguage].copyright;
    if(agreementLink) agreementLink.textContent = window.content[window.currentLanguage].agreement;
    if(footerQuestionnaireLink) footerQuestionnaireLink.textContent = window.content[window.currentLanguage].questionnaire;
    if(privacyLink) privacyLink.textContent = window.content[window.currentLanguage].privacy;
    
    // 初始化skip-loading文本
    const skipLoadingBtn = document.getElementById('skip-loading');
    if(skipLoadingBtn) {
        skipLoadingBtn.textContent = window.content[window.currentLanguage].skipLoading;
    }

    // 语言切换
    if(languageBtn){
        languageBtn.addEventListener('click', () => {
            window.currentLanguage = window.currentLanguage === 'chinese' ? 'english' : 'chinese';
            const lang = window.currentLanguage;
            mainTitle.textContent = window.content[lang].mainTitle;
            if(homeBtn) homeBtn.textContent = window.content[lang].home;
            // 正确更新语言按钮文字
            languageBtn.textContent = lang === 'chinese' ? 'EN/中' : '中/EN';
            if(tooltip) tooltip.textContent = window.content[lang].tooltip;
            if(galleryTitle) galleryTitle.textContent = window.content[lang].galleryTitle;
            typeText(window.content[lang].welcomeText);
            // 更新联系冠恩、冠恩书屋、文化分享、艺术、建议作者和抽奖按钮的文本
            if(contactLink) contactLink.textContent = window.content[lang].contactGuanen;
            if(articleLink) articleLink.textContent = window.content[lang].bookStore;
            if(cultureLink) cultureLink.textContent = window.content[lang].culture;
            if(artLink) artLink.textContent = window.content[lang].art;
            if(suggestionLink) suggestionLink.textContent = window.content[lang].suggestion;
            if(bookCrossingBtn) bookCrossingBtn.textContent = window.content[lang].bookCrossing;
            if(lotteryBtn) lotteryBtn.textContent = window.content[lang].lottery;
            if(monopolyBtn) monopolyBtn.textContent = window.content[lang].monopoly;
            if(skipLoadingBtn) skipLoadingBtn.textContent = window.content[lang].skipLoading;
            
            // 更新页脚文本
            if(icpText) icpText.textContent = window.content[lang].icp;
            if(footerAuthor) footerAuthor.textContent = window.content[lang].footerAuthor;
            if(copyrightText) copyrightText.textContent = window.content[lang].copyright;
            if(agreementLink) agreementLink.textContent = window.content[lang].agreement;
            if(footerQuestionnaireLink) footerQuestionnaireLink.textContent = window.content[lang].questionnaire;
            if(privacyLink) privacyLink.textContent = window.content[lang].privacy;

            updateLoginButton();
            const centerText = document.getElementById('centerText');
            if(centerText) {
                centerText.innerHTML = window.content[lang].centerText;
                // 重新触发淡入动画
                centerText.classList.remove('show');
                setTimeout(()=> centerText.classList.add('show'), 50);
            }
            
            // 更新quote-section中的wenan文本
            const highlightQuote = document.getElementById('highlightQuote');
            if(highlightQuote) {
                highlightQuote.textContent = window.content[lang].wenan;
            }

            // 触发自定义事件，通知其他组件语言已更改
            window.dispatchEvent(new CustomEvent('languageChanged', { detail: lang }));
        });
    }
    
    // 冠恩书屋链接点击事件
    if(articleLink){
        articleLink.addEventListener('click', () => {
            window.location.href = 'pages/article.html';
        });
    }

    // 抽奖按钮点击事件
    if(lotteryBtn){
        lotteryBtn.addEventListener('click', () => {
            window.location.href = 'pages/lottery.html';
        });
    }

    // AI 书童按钮点击事件
    if(aiChatBtn){
        aiChatBtn.addEventListener('click', () => {
            window.location.href = 'pages/chat-ai.html';
        });
    }

    // 书籍漂流按钮点击事件
    if(bookCrossingBtn){
        bookCrossingBtn.addEventListener('click', () => {
            window.location.href = 'pages/book_crossing.html';
        });
    }

    // 跳跳乐按钮点击事件
    if(paokuBtn){
        paokuBtn.addEventListener('click', () => {
            window.location.href = 'games/paoku/index.html';
        });
    }

    // 大富翁按钮点击事件
    if(monopolyBtn) {
        monopolyBtn.addEventListener('click', () => {
            window.location.href = 'games/monopoly/index.html';
        });
    }

    // 登录按钮点击事件
    if(loginBtn) {
        loginBtn.addEventListener('click', () => {
            if (isLoggedIn) {
                window.location.href = 'user/user_dashboard.php';
            } else {
                window.location.href = 'user/user_login.php';
            }
        });
    }

    // 首页按钮
    if(homeBtn) homeBtn.addEventListener('click', ()=> window.scrollTo({ top:0, behavior:'smooth' }));

    // 底部按钮
    if(toBottomBtn) toBottomBtn.addEventListener('click', scrollToBottom);
    if(toBottomBtnFixed) toBottomBtnFixed.addEventListener('click', scrollToBottom);

  // 自定义滚动条逻辑
    function updateScrollbar() {
        if(!horizontalScroll || !customScrollbar || !scrollThumb) return;
        
        // 集中读取
        const scrollWidth = horizontalScroll.scrollWidth;
        const clientWidth = horizontalScroll.clientWidth;
        const scrollLeft = horizontalScroll.scrollLeft;
        const trackWidth = customScrollbar.offsetWidth;
        
        const maxScrollLeft = scrollWidth - clientWidth;

        // 集中写入
        if(maxScrollLeft > 1) {
            const thumbWidth = Math.max((clientWidth / scrollWidth) * trackWidth, 50);
            const thumbPosition = (scrollLeft / maxScrollLeft) * (trackWidth - thumbWidth);
            scrollThumb.style.width = thumbWidth + 'px';
            scrollThumb.style.left = thumbPosition + 'px';
            customScrollbar.style.display = 'block';
        } else {
            customScrollbar.style.display = 'none';
        }
    }
    updateScrollbar();

    let scrollRaf;
    if(horizontalScroll) {
        horizontalScroll.addEventListener('scroll', ()=> {
            if(scrollRaf) cancelAnimationFrame(scrollRaf);
            scrollRaf = requestAnimationFrame(updateScrollbar);
        });
    }
    window.addEventListener('resize', ()=> {
        if(scrollRaf) cancelAnimationFrame(scrollRaf);
        scrollRaf = requestAnimationFrame(updateScrollbar);
    });

    // 滚动条拖动
    let isDragging = false, startX = 0, startLeft = 0;
    function handleDragMove(e) {
        if(!isDragging) return;
        
        const deltaX = e.clientX - startX;
        const scrollTrackWidth = customScrollbar.offsetWidth;
        const thumbWidth = scrollThumb.offsetWidth;
        const scrollWidth = horizontalScroll.scrollWidth;
        const clientWidth = horizontalScroll.clientWidth;
        
        let newLeft = startLeft + deltaX;
        newLeft = Math.max(0, Math.min(scrollTrackWidth - thumbWidth, newLeft));
        const maxScrollLeft = scrollWidth - clientWidth;
        const scrollPercentage = newLeft / (scrollTrackWidth - thumbWidth);
        
        scrollThumb.style.left = newLeft + 'px';
        horizontalScroll.scrollLeft = scrollPercentage * maxScrollLeft;
    }

    function handleDragEnd() {
        isDragging = false;
        document.removeEventListener('mousemove', handleDragMove);
        document.removeEventListener('mouseup', handleDragEnd);
    }
    
    if (scrollThumb) {
        scrollThumb.addEventListener('mousedown', (e)=> {
            isDragging = true;
            startX = e.clientX;
            startLeft = parseFloat(scrollThumb.style.left || 0);
            document.addEventListener('mousemove', handleDragMove);
            document.addEventListener('mouseup', handleDragEnd);
            e.preventDefault();
        });
    }
    
    // 点击轨道
    if (customScrollbar) {
        customScrollbar.addEventListener('click', (e)=> {
            if(e.target === scrollThumb) return;
            const rect = customScrollbar.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const thumbWidth = scrollThumb.offsetWidth;
            const scrollTrackWidth = customScrollbar.offsetWidth;
            let newLeft = clickX - thumbWidth/2;
            newLeft = Math.max(0, Math.min(scrollTrackWidth - thumbWidth, newLeft));
            scrollThumb.style.left = newLeft + 'px';
            const scrollWidth = horizontalScroll.scrollWidth;
            const clientWidth = horizontalScroll.clientWidth;
            const maxScrollLeft = scrollWidth - clientWidth;
            const scrollPercentage = newLeft / (scrollTrackWidth - thumbWidth);
            horizontalScroll.scrollLeft = scrollPercentage * maxScrollLeft;
        });
    }
    // 跟随滚动缩放标题（保留你原有思路）
   // 跟随滚动缩放标题 (加入 rAF 性能优化)
let scrollTicking = false;
window.addEventListener('scroll', ()=> {
    if (!scrollTicking) {
        window.requestAnimationFrame(() => {
            const scrollY = window.scrollY || window.pageYOffset;
            let targetY = -scrollY * 0.5;
            if(mainTitle) mainTitle.style.transform = `translateY(${targetY}px)`;
            if(welcomeContainer) {
                welcomeContainer.style.transform = `translateY(${targetY}px)`;
            }
            scrollTicking = false;
        });
        scrollTicking = true;
    }
}, { passive: true }); // passive: true 告诉浏览器我们不会阻止默认滚动，提升页面流畅度

    // 关键图片列表
    const criticalImages = [
        'assets/images/秋玫瑰.webp',  // logo图片
        'assets/images/he.webp',              // 背景大图
        'assets/images/怎样.webp',     // 第一张小图
        'assets/images/23542342.webp', // 第二张小图
        'assets/images/不知道.webp'     // 第三张小图
    ];
    
    // 如果图片不存在，使用备用方案
    const fallbackImages = [
        'https://via.placeholder.com/400x300/cccccc/666666?text=Loading...',
        'https://via.placeholder.com/800x600/cccccc/666666?text=Background',
        'https://via.placeholder.com/600x400/cccccc/666666?text=Gallery+1',
        'https://via.placeholder.com/600x400/cccccc/666666?text=Gallery+2',
        'https://via.placeholder.com/600x400/cccccc/666666?text=Gallery+3'
    ];
    
    let criticalImagesLoaded = 0;
    let criticalResourcesLoaded = false;
    const totalCriticalImages = criticalImages.length;
    let currentProgress = 0;
    let progressInterval;
    
    // 更新进度条的函数
    function updateProgress(percent) {
        const progressBar = document.getElementById('loading-progress-bar');
        if (progressBar) {
            currentProgress = Math.min(100, percent);
            progressBar.style.width = currentProgress + '%';
            console.log(`更新进度条: ${currentProgress}%`);
        }
    }
    
    // 将updateProgress函数暴露给hello-animation.js
    window.mainUpdateProgress = updateProgress;
    
    // 将tryShowPage函数暴露给hello-animation.js
    window.mainShowPageContent = tryShowPage;
    
    // 检查关键资源是否加载完成
    function checkCriticalResources() {
        let loadedCount = 0;
        
        // 启动进度条动画
        progressInterval = setInterval(() => {
            if (currentProgress < 90) {
                updateProgress(currentProgress + Math.random() * 5);
            }
        }, 200);
        
        criticalImages.forEach((src, index) => {
            const img = new Image();
            let hasLoaded = false;
            
            img.onload = () => {
                if (hasLoaded) return; // 防止重复计数
                hasLoaded = true;
                loadedCount++;
                criticalImagesLoaded++;
                updateProgress((loadedCount / totalCriticalImages) * 50); // 关键资源占50%进度
                
                // 如果是背景图片，设置背景
                if (src === 'assets/images/he.webp' || src === 'http://www.guanen.top/assets/images/he.webp') {
                    const topSection = document.getElementById('topSection');
                    if (topSection) {
                        // 优先使用hello-animation.js中保存的图片URL（如果有）
                        if (window.mainImageUrl) {
                            topSection.style.backgroundImage = `url('${window.mainImageUrl}')`;
                            console.log(`使用hello-animation.js中的图片URL: ${window.mainImageUrl}`);
                        } else {
                            topSection.style.backgroundImage = `url('${src}')`;
                            console.log(`背景图片已设置: ${src}`);
                        }
                    }
                }
                
                console.log(`关键图片 ${index + 1}/${totalCriticalImages} 加载成功: ${src}`);
                
                if (loadedCount === totalCriticalImages) {
                    criticalResourcesLoaded = true;
                    tryShowPage();
                }
            };
            
            img.onerror = () => {
                if (hasLoaded) return; // 防止重复计数
                hasLoaded = true;
                loadedCount++; // 即使加载失败也计数，避免卡住
                console.log(`关键图片 ${index + 1}/${totalCriticalImages} 加载失败: ${src}`);
                updateProgress((loadedCount / totalCriticalImages) * 50);
                
                if (loadedCount === totalCriticalImages) {
                    criticalResourcesLoaded = true;
                    tryShowPage();
                }
            };
            
            // 设置超时，防止图片加载卡住
            setTimeout(() => {
                if (!hasLoaded) {
                    hasLoaded = true;
                    loadedCount++;
                    console.log(`关键图片 ${index + 1}/${totalCriticalImages} 加载超时: ${src}`);
                    updateProgress((loadedCount / totalCriticalImages) * 50);
                    
                    if (loadedCount === totalCriticalImages) {
                        criticalResourcesLoaded = true;
                        tryShowPage();
                    }
                }
            }, 6000); // 6秒超时
            
            img.src = src;
        });
    }
    

    /* ========== 联系作者按钮直接跳转到页面底部 ========== */
    if(contactLink) {
        contactLink.addEventListener('click', (e) => {
            e.stopPropagation();
            // 平滑滚动到页面底部
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        });
        // 键盘回车/空格触发
        contactLink.addEventListener('keydown', (e) => {
            if(e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                contactLink.click();
            }
        });
    }

    // 微信图标 - 添加点击事件处理，修复手机端点击问题
    const wechatIcon = document.getElementById('wechat-icon');
    const wechatPopup = document.getElementById('wechat-popup');

    if (wechatIcon && wechatPopup) {
        // 添加点击事件处理，在移动端显示/隐藏弹窗
        wechatIcon.addEventListener('click', function(event) {
            // 阻止冒泡，避免影响其他元素
            event.stopPropagation();
            
            // 切换弹窗显示状态
            if (wechatPopup.style.display === 'block') {
                wechatPopup.style.display = 'none';
            } else {
                wechatPopup.style.display = 'block';
            }
        });
        
        // 点击页面其他地方关闭弹窗
        document.addEventListener('click', function() {
            wechatPopup.style.display = 'none';
        });
        
        // 阻止弹窗内部点击事件冒泡，避免立即关闭
        wechatPopup.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
   
    setInterval(createHelloBubble, 800);
    
    // 监听中心文字
    observeCenterText();

    // 页面初始化完成后的完整逻辑
    function initPage() {
        // 启动打字机效果
        if (typeof typeText === 'function') {
            typeText(content[currentLanguage].welcomeText);
        }
        
        // 设置年份显示
        const startYear = 2025;
        const currentYear = new Date().getFullYear();
        const yearRange = startYear === currentYear ? `${startYear}` : `${startYear}–${currentYear}`;
        const yearElement = document.getElementById('year-range');
        if (yearElement) yearElement.textContent = yearRange;
        
        // 监听中心文字
        if (typeof observeCenterText === 'function') observeCenterText();
        
        // 启动装饰气泡动画
        setInterval(createHelloBubble, 1200);
    }

    // 访问总量计数器 - 改为从服务器获取总访问量
    function updateVisitCount() {
        // 调用后端 API 获取并增加访问量
        fetch('/api/total_visits.php', { credentials: 'include' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const visitCountElement = document.getElementById('visit-count');
                    if (visitCountElement) {
                        visitCountElement.textContent = data.total_visits;
                    }
                } else {
                    console.error('获取访问量失败:', data.message);
                }
            })
            .catch(error => {
                console.error('访问统计 API 请求出错:', error);
            });
    }
    
    // 初始化访问计数
    updateVisitCount();
    
    // 尝试显示页面内容
    function tryShowPage() {
        // 强制设置资源加载完成标志
        window.criticalResourcesLoaded = true;
        
        if (window.showPageContent && typeof window.showPageContent === 'function') {
            console.log('调用全局 showPageContent');
            window.showPageContent();
        } else {
            console.log('执行本地 tryShowPage 逻辑');
            try {
                if (progressInterval) clearInterval(progressInterval);
                updateProgress(100);
                
                // 延迟切换，确保动画连贯
                setTimeout(() => {
                    const loadingInk = document.getElementById('loading-ink');
                    if (loadingInk) {
                        loadingInk.classList.add('hidden');
                        setTimeout(() => { loadingInk.style.display = 'none'; }, 800);
                    }
                    
                    // 确保显示所有内容容器
                    const pageContent = document.querySelector('.page-content');
                    if (pageContent) {
                        pageContent.classList.add('show');
                        pageContent.style.opacity = '1';
                        pageContent.style.visibility = 'visible';
                    }
                    
                    const wrapper = document.getElementById('page-content-wrapper');
                    if (wrapper) {
                        wrapper.style.display = 'block';
                        wrapper.style.opacity = '1';
                    }
                    
                    const topSection = document.getElementById('topSection');
                    if (topSection && !topSection.style.backgroundImage) {
                        topSection.style.backgroundImage = `url('assets/images/he.webp')`;
                    }
                    
                    initPage();
                }, 800);
            } catch (error) {
                console.error('显示页面出错:', error);
                // 兜底方案
                const loadingInk = document.getElementById('loading-ink');
                if (loadingInk) loadingInk.style.display = 'none';
                const wrapper = document.getElementById('page-content-wrapper');
                if (wrapper) wrapper.style.display = 'block';
                initPage();
            }
        }
    }
    
    // 跳过加载功能
    window.skipLoading = function() {
        tryShowPage();
    };
    
    // 启动资源检查
    checkCriticalResources();
    
    // 备用方案：如果资源检查超时，强制显示
    setTimeout(() => {
        if (!window.criticalResourcesLoaded) {
            console.log('资源加载超时，强制显示页面');
            tryShowPage();
        }
    }, 3000); // 将 5000ms 缩短为 3000ms
    
    // 监听来自 hello-animation.js 的显示信号
    window.addEventListener('pageShowReady', () => {
        console.log('收到页面显示就绪信号');
        initPage();
    });

    // 监听hello-animation.js中的mainImageLoaded事件，确保背景图正确设置
    window.addEventListener('mainImageLoaded', function(event) {
        console.log('收到mainImageLoaded事件，尝试更新背景图:', event.detail.url);
        const topSection = document.getElementById('topSection');
        if (topSection) {
            topSection.style.backgroundImage = `url('${event.detail.url}')`;
            console.log('通过事件监听器更新背景图成功');
        }
    });
    
    // 添加轮询机制，确保在强制刷新时能正确获取mainImageUrl
    let pollCount = 0;
    const maxPollCount = 10; // 最多轮询10次（5秒）
    
    function pollForMainImageUrl() {
        if (pollCount >= maxPollCount) {
            console.log('轮询mainImageUrl达到最大次数，停止轮询');
            
            // 最后尝试一次，使用本地图片作为备用
            const topSection = document.getElementById('topSection');
        if (topSection && !topSection.style.backgroundImage) {
            console.log('使用本地图片：assets/images/he.webp');
            // 使用本地图片作为背景
            topSection.style.backgroundImage = `url('assets/images/he.webp')`;
            
            // 触发mainImageLoaded事件
            const event = new CustomEvent('mainImageLoaded', { 
                detail: { url: 'assets/images/he.webp' } 
            });
            window.dispatchEvent(event);
        }
            return;
        }
        
        // 检查是否有有效的mainImageUrl
        let imageUrl = window.mainImageUrl;
        // 如果mainImageUrl包含外部域名，使用完整的服务器URL
        if (imageUrl && imageUrl.includes('guanen.top')) {
            console.log('使用完整的服务器图片URL');
            imageUrl = 'https://guanen.top/assets/images/he.webp';
        }
        
        const topSection = document.getElementById('topSection');
        if (topSection && imageUrl && !topSection.style.backgroundImage) {
            console.log(`通过轮询获取到图片URL: ${imageUrl}`);
            topSection.style.backgroundImage = `url('${imageUrl}')`;
            console.log('通过轮询更新背景图成功');
            return;
        }
        
        pollCount++;
        console.log(`轮询图片URL (${pollCount}/${maxPollCount})...`);
        setTimeout(pollForMainImageUrl, 500); // 每500ms轮询一次
    }
    
    // 启动轮询
    setTimeout(pollForMainImageUrl, 1000); // 延迟1秒后开始轮询，给hello-animation.js一些加载时间

    /**
     * 初始化底部纪念区块的交互逻辑
     * 包含：点击切换照片显示，并触发烟花效果 (canvas-confetti)
     */
    /**
     * 猫咪漫步动画 (catWalkAnimation)
     * 在全屏生成左右交替的猫脚印 (使用重构后的写实猫爪 SVG)
     */
    function catWalkAnimation() {
        const container = document.createElement('div');
        container.className = 'cat-walk-container';
        document.body.appendChild(container);

        // 用户提供的写实猫爪 SVG 数据
        const pawData = {
            path: "M50 55 C35 55 25 70 25 80 C25 90 35 98 50 98 C65 98 75 90 75 80 C75 70 65 55 50 55 Z",
            circles: [
                { cx: 20, cy: 45, r: 10 },
                { cx: 40, cy: 30, r: 11 },
                { cx: 60, cy: 30, r: 11 },
                { cx: 80, cy: 45, r: 10 }
            ]
        };
        
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        
        // 随机路径参数
        const side = Math.floor(Math.random() * 4);
        let startX, startY, endX, endY;
        
        if (side === 0) { // Top
            startX = Math.random() * winW; startY = -60;
            endX = Math.random() * winW; endY = winH + 60;
        } else if (side === 1) { // Bottom
            startX = Math.random() * winW; startY = winH + 60;
            endX = Math.random() * winW; endY = -60;
        } else if (side === 2) { // Left
            startX = -60; startY = Math.random() * winH;
            endX = winW + 60; endY = Math.random() * winH;
        } else { // Right
            startX = winW + 60; startY = Math.random() * winH;
            endX = -60; endY = Math.random() * winH;
        }

        const steps = 14; // 增加步数，让漫步更完整
        const stepInterval = 450; // 稍微加快节奏
        const angle = Math.atan2(endY - startY, endX - startX) * 180 / Math.PI + 90;

        for (let i = 0; i < steps; i++) {
            setTimeout(() => {
                const ns = "http://www.w3.org/2000/svg";
                const paw = document.createElementNS(ns, "svg");
                paw.setAttribute("viewBox", "0 0 100 100");
                paw.classList.add('cat-paw-print');
                
                // 创建大肉垫
                const mainPad = document.createElementNS(ns, "path");
                mainPad.setAttribute("d", pawData.path);
                paw.appendChild(mainPad);

                // 创建四个脚趾
                pawData.circles.forEach(c => {
                    const circle = document.createElementNS(ns, "circle");
                    circle.setAttribute("cx", c.cx);
                    circle.setAttribute("cy", c.cy);
                    circle.setAttribute("r", c.r);
                    paw.appendChild(circle);
                });

                // 计算当前步的位置
                const progress = i / (steps - 1);
                const currentX = startX + (endX - startX) * progress;
                const currentY = startY + (endY - startY) * progress;
                
                // 左右交替偏移
                const offset = (i % 2 === 0 ? 18 : -18);
                const rad = angle * Math.PI / 180;
                const offsetX = offset * Math.cos(rad);
                const offsetY = offset * Math.sin(rad);

                paw.style.left = (currentX + offsetX) + 'px';
                paw.style.top = (currentY + offsetY) + 'px';
                paw.style.setProperty('--paw-rotate', `${angle}deg`);
                
                container.appendChild(paw);
                
                requestAnimationFrame(() => {
                    paw.classList.add('animate');
                });

                if (i === steps - 1) {
                    setTimeout(() => container.remove(), 6000);
                }
            }, i * stepInterval);
        }
    }

    /**
     * 初始化底部纪念区块的交互逻辑 (仿微信弹出框重构 - 悬停显示/点击烟花)
     */
    function initMemoryFooter() {
        const doudouBtn = document.getElementById('doudouBtn');
        const doudouPopover = document.getElementById('doudouPopover');
        if (!doudouBtn || !doudouPopover) return;

        // 预先挂载到 body
        document.body.appendChild(doudouPopover);

        // 统一位置计算函数
        const updatePosition = () => {
            const rect = doudouBtn.getBoundingClientRect();
            const popWidth = 150;
            // 精确计算：将弹窗底部（含箭头）放置在文字上方 8px 处
            // 8px 是箭头的长度，确保箭头尖端正好指向文字
            doudouPopover.style.left = (rect.left + rect.width / 2 - popWidth / 2) + 'px';
            doudouPopover.style.top = (rect.top - 8) + 'px'; 
            doudouPopover.style.transform = 'translateY(-100%)';
        };

        // 1. 鼠标移入：显示照片
        doudouBtn.addEventListener('mouseenter', function() {
            // 先设位置，再加 class，彻底消除闪动跳跃
            doudouPopover.style.display = 'block'; // 先设为 block 才能正确渲染位置
            updatePosition();
            doudouPopover.classList.add('show');
        });

        // 2. 鼠标移出：隐藏照片
        doudouBtn.addEventListener('mouseleave', function() {
            doudouPopover.classList.remove('show');
            // 延迟一点点设为 none，让淡出动画能跑完
            setTimeout(() => {
                if (!doudouPopover.classList.contains('show')) {
                    doudouPopover.style.display = 'none';
                }
            }, 200);
        });

        // 3. 点击：放烟花 + 猫咪漫步
        doudouBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 启动猫咪漫步动画
            catWalkAnimation();

            if (typeof confetti === 'function') {
                const rect = doudouBtn.getBoundingClientRect();
                const x = (rect.left + rect.width / 2) / window.innerWidth;
                const y = rect.top / window.innerHeight;

                confetti({
                    particleCount: 40,
                    spread: 70,
                    origin: { x, y: y - 0.05 },
                    colors: ['#FFD700', '#FFB7C5', '#FFFFFF', '#87CEEB'],
                    ticks: 200,
                    gravity: 1,
                    scalar: 0.8,
                    zIndex: 10000001
                });
            }
        });

        // 窗口调整大小时更新位置（如果当前正显示）
        window.addEventListener('resize', () => {
            if (doudouPopover.classList.contains('show')) {
                updatePosition();
            }
        });
    }

    // 执行底部纪念区块初始化
    initMemoryFooter();
});
