// 中奖名单数据
const winnerData = [
    { user: "2938****10@qq.com", prize: "一等奖", reward: "书籍一本", level: "win-1" },
    { user: "lvxue", prize: "一等奖", reward: "书籍一本", level: "win-4" },
    { user: "yj7", prize: "一等奖", reward: "书籍一本", level: "win-1" },
    { user: "凭栏意", prize: "一等奖", reward: "书籍一本", level: "win-2" },
    { user: "uu", prize: "一等奖", reward: "书籍一本", level: "win-3" },
    { user: "Aries", prize: "一等奖", reward: "书籍一本", level: "win-4" },
    { user: "今夏", prize: "一等奖", reward: "书籍一本", level: "win-1" },
    { user: "ssss123", prize: "一等奖", reward: "书籍一本", level: "win-2" },
    { user: "Liujiahui1224", prize: "一等奖", reward: "书籍一本", level: "win-3" },
    { user: "cr", prize: "一等奖", reward: "书籍一本", level: "win-4" },
    { user: "杨惠琴", prize: "一等奖", reward: "书籍一本", level: "win-1" },
    { user: "chai", prize: "一等奖", reward: "书籍一本", level: "win-2" },
    { user: "随风", prize: "一等奖", reward: "书籍一本", level: "win-3" },
    { user: "Mike", prize: "一等奖", reward: "书籍一本", level: "win-4" }
];

// 初始化中奖名单滚动显示
function initWinnerScroll() {
    const winnerScrollList = document.getElementById('winnerScrollList');
    
    if (!winnerScrollList) return;

    // 清空现有内容
    winnerScrollList.innerHTML = '';
    
    // 为了实现无缝滚动，我们需要复制一份数据
    const displayData = [...winnerData, ...winnerData];
    
    // 创建中奖项目
    displayData.forEach((winner, index) => {
        const winnerItem = document.createElement('div');
        winnerItem.className = `winner-item ${winner.level}`;
        winnerItem.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 2px;">用户 ${winner.user}</div>
            <div style="font-size: 0.85em; opacity: 0.9;">抽中${winner.prize}，奖励：${winner.reward}</div>
        `;
        winnerScrollList.appendChild(winnerItem);
    });
    
    // 动态计算动画时间：每个条目显示 3 秒
    const duration = winnerData.length * 3;
    winnerScrollList.style.animationDuration = `${duration}s`;
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    initWinnerScroll();
    
    // 监听窗口大小变化，重新计算动画
    window.addEventListener('resize', function() {
        setTimeout(initWinnerScroll, 100);
    });
});
