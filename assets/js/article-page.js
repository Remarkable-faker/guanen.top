document.addEventListener('DOMContentLoaded', function() {
  const sidebarList = document.getElementById('sidebarList');
  const contentDiv = document.getElementById('content');
  const chapterList = document.getElementById('chapterList');
  const mobileMenuButton = document.getElementById('mobileMenuButton');
  const sidebar = document.getElementById('sidebar');

  // 1. 渲染目录树 (Sidebar)
  function renderSidebar(data) {
    sidebarList.innerHTML = '';
    const ul = document.createElement('ul');
    
    for (const catKey in data) {
      const category = data[catKey];
      const li = document.createElement('li');
      
      li.innerHTML = `
        <div class="li-main">
          <div class="dot" style="background-color:${category.color || 'var(--primary-soft)'}"></div>
          <div class="item-text">
            <span class="item-title">${category.title}</span>
          </div>
          <div class="arrow"></div>
        </div>
      `;
      
      const childUl = document.createElement('ul');
      childUl.className = 'child-list';
      
      for (const artKey in category.children) {
        const article = category.children[artKey];
        const artLi = document.createElement('li');
        artLi.className = 'article-item';
        artLi.innerHTML = `
          <div class="li-main">
            <div class="dot" style="border: 2px solid ${article.color || 'var(--accent-soft)'}; background: transparent;"></div>
            <div class="item-text">
              <span class="item-title">${article.title}</span>
              ${article.author ? `<span class="item-subtitle">${article.author}</span>` : ''}
            </div>
          </div>
        `;
        
        artLi.onclick = (e) => {
          e.stopPropagation();
          selectArticle(article, artLi);
        };
        
        childUl.appendChild(artLi);
      }
      
      li.appendChild(childUl);
      
      // 分类展开/折叠
      li.querySelector('.li-main').onclick = () => {
        const isOpen = li.classList.contains('open');
        if (isOpen) {
          li.classList.remove('open');
        } else {
          li.classList.add('open');
        }
      };
      
      ul.appendChild(li);
    }
    sidebarList.appendChild(ul);
  }

  // 2. 选择文章
  function selectArticle(articleData, element) {
    // 侧边栏激活状态
    document.querySelectorAll('.article-item').forEach(i => i.classList.remove('active'));
    element.classList.add('active');

    // 移动端关闭侧边栏
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('mobile-open');
    }

    // 更新章节侧边栏
    renderChapterList(articleData);

    // 默认显示第一章
    if (articleData.chapters && articleData.chapters.length > 0) {
      renderChapter(articleData.chapters[0], articleData);
    } else {
      contentDiv.innerHTML = `
        <div class="article-wrapper">
          <h2 style="margin-top: 40px;">${articleData.title}</h2>
          <div style="text-align:center; color:var(--text-light); margin-top:40px;">暂无内容</div>
        </div>
      `;
    }
  }

  // 3. 渲染章节列表
  function renderChapterList(articleData) {
    chapterList.innerHTML = '';
    
    if (!articleData.chapters) return;
    
    articleData.chapters.forEach((chapter, index) => {
      const item = document.createElement('div');
      item.className = 'chapter-item';
      if (index === 0) item.classList.add('active');
      
      item.innerHTML = `
        <div class="chapter-number">${chapter.number}</div>
        <div class="chapter-title">${chapter.title}</div>
      `;
      
      item.onclick = () => {
        document.querySelectorAll('.chapter-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        renderChapter(chapter, articleData);
      };
      
      chapterList.appendChild(item);
    });
  }

  // 4. 渲染章节内容
  function renderChapter(chapter, articleData) {
    // 切换动画
    contentDiv.style.opacity = '0';
    contentDiv.style.transform = 'translateY(10px)';
    contentDiv.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
      contentDiv.innerHTML = `
        <div class="article-wrapper">
          <div class="chapter-meta" style="font-size: 13px; color: var(--primary-soft); margin-bottom: 10px; text-align: center; letter-spacing: 1px;">
            ${articleData.title} · 第 ${chapter.number} 章
          </div>
          <h2 style="margin-bottom: 15px; text-align: center;">${chapter.title}</h2>
          
          <div class="article-info" style="text-align: center; font-size: 13px; color: var(--text-light); margin-bottom: 40px; display: flex; justify-content: center; gap: 15px;">
            ${articleData.author ? `<span>作者：${articleData.author}</span>` : ''}
            ${articleData.date ? `<span>日期：${articleData.date}</span>` : ''}
          </div>

          <div class="article-content">
            ${chapter.content}
          </div>
          <div style="margin-top: 60px; padding-top: 30px; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-light); font-size: 14px; letter-spacing: 2px;">
            THE END
          </div>
        </div>
      `;
      
      contentDiv.style.opacity = '1';
      contentDiv.style.transform = 'translateY(0)';
      contentDiv.scrollTop = 0;
      
      // 更新页面标题
      document.title = `${chapter.title} - ${articleData.title} - 冠恩书屋`;
    }, 300);
  }

  // 5. 移动端菜单
  mobileMenuButton.onclick = (e) => {
    e.stopPropagation();
    sidebar.classList.toggle('mobile-open');
  };

  // 点击其他地方关闭侧边栏
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !mobileMenuButton.contains(e.target) && 
        sidebar.classList.contains('mobile-open')) {
      sidebar.classList.remove('mobile-open');
    }
  });

  // 6. 初始化
  if (typeof articles !== 'undefined') {
    renderSidebar(articles);
  } else {
    console.error('Articles data not found');
  }
  
  // 初始状态下隐藏章节侧边栏内容或显示提示
  chapterList.innerHTML = '<div style="text-align:center; color:var(--text-light); padding:20px; font-size:13px;">请选择文章以查看章节</div>';
});
