/**
 * 聊天系统核心逻辑 - 微信双栏版
 */
class ChatSystem {
    constructor() {
        this.currentFriendId = null;
        this.isGroup = false; // 是否为群聊模式
        this.lastMsgId = 0;
        this.lastMessageTime = null; // 用于记录最后一条消息的时间，判断是否显示时间条
        this.pollingInterval = null;
        this.unreadPollingInterval = null;
        this.emojis = ['😊', '😂', '🤣', '😍', '😒', '😭', '😘', '😩', '😔', '👌', '👍', '🙌', '🙏', '🔥', '✨', '💖', '🤔', '😎', '😜', '😢', '😡', '😴', '👋', '🎉'];
        
        // 动态检测 API 路径，兼容子目录部署
        const scripts = document.getElementsByTagName('script');
        let basePath = '/';
        for (let i = 0; i < scripts.length; i++) {
            if (scripts[i].src && scripts[i].src.includes('assets/js/chat.js')) {
                const src = scripts[i].src;
                // 从 src 中提取基础路径
                const url = new URL(src);
                basePath = url.pathname.replace('assets/js/chat.js', '');
                break;
            }
        }
        this.apiPath = basePath + 'api/chat_api.php';
        console.log('Chat System: API Path resolved to:', this.apiPath);
        this.init();
    }

    /**
     * 获取用户身份标签 HTML
     * @param {Object} user - 用户对象，包含 id, role_label 等
     */
    getBadgeHtml(user) {
        if (!user) return '';
        
        // 特殊处理：ID 为 1 的用户显示黑底金字发光的“特邀”徽章
        if (user.id == 1) {
            return `<span class="user-badge-invited">特邀</span>`;
        }
        
        if (!user.role_label) {
            // 如果是 ID 为 2 的站长且没有标签，默认显示作者标识
            return user.id == 2 ? `<span class="user-badge-author">作者</span>` : '';
        }
        
        const label = user.role_label;
        
        if (label === '作者' || label === '站长' || label === '官方') {
            return `<span class="user-badge-author">${this.escapeHtml(label)}</span>`;
        }
        
        if (label === '恋爱中' || label === '热恋中') {
            return `<span class="user-badge-pink">恋爱中</span>`;
        }
        
        // 其他所有标签默认使用灰底白字
        return `<span class="user-badge-grey">${this.escapeHtml(label)}</span>`;
    }

    /**
     * 获取用户头像 HTML
     * @param {Object} user - 用户对象，包含 id, username 等
     */
    getAvatarHtml(user) {
        if (!user) return '';
        
        // 特殊处理：ID 为 2 的用户使用本地图片头像
        if (user.id == 2) {
            // 根据当前页面路径动态调整图片路径，并添加随机数防止缓存
            const path = window.location.pathname;
            const random = Math.random().toString(36).substring(7); // 生成随机字符串彻底防止缓存
            let avatarPath = `assets/images/touxiang.webp?v=${random}`;
            if (path.includes('/user/') || path.includes('/pages/') || path.includes('/games/')) {
                avatarPath = `../assets/images/touxiang.webp?v=${random}`;
            }
            return `<div class="friend-avatar" style="background: none; overflow: hidden;"><img src="${avatarPath}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.style.background='#3b82f6';this.parentElement.innerHTML='${user.username ? user.username[0].toUpperCase() : 'G'}';"></div>`;
        }
        
        // 其他用户默认显示名字首字母
        const initial = user.username ? user.username[0].toUpperCase() : 'U';
        return `<div class="friend-avatar">${initial}</div>`;
    }

    async init() {
        this.renderEmojiPicker();
        this.bindEvents();
        
        try {
            console.log('Chat System: Fetching initialization data from:', this.apiPath);
            // 使用统一的 init 接口获取当前用户信息和状态
            const res = await fetch(`${this.apiPath}?action=init`, { credentials: 'include' });
            
            if (!res.ok) {
                console.error('Chat System: Network error', res.status, res.statusText);
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            console.log('Chat System: Init data received:', data);
            
            // 严谨判断：优先使用 data.data 中的字段（如果存在），否则使用顶层字段
            const status = data.data || data;
            const isLoggedIn = status.is_logged_in || status.logged_in;
            const userData = status.user || status.user_data;
            
            if (!data.success || !isLoggedIn || !userData) {
                this.isLoggedIn = false;
                console.warn('Chat System: User not logged in or incomplete data', {
                    success: data.success,
                    isLoggedIn: isLoggedIn,
                    hasUser: !!userData
                });
                
                const widget = document.querySelector('.chat-widget');
                if (widget) {
                    widget.style.display = 'flex'; 
                }
                return;
            }

            // 执行到这里说明用户已登录
            this.isLoggedIn = true;
            this.user = userData; // 保存当前用户信息
            console.log('Chat System: User logged in as:', this.user.username);
            
            // 设置当前用户头像和名字 - 使用更通用的选择器
            const profileArea = document.querySelector('.chat-user-profile');
            if (profileArea && data.user) {
                const avatarContainer = profileArea.querySelector('.friend-avatar');
                if (avatarContainer) {
                    avatarContainer.outerHTML = this.getAvatarHtml(data.user);
                }
            }
            
            const profileName = document.getElementById('currentUserName');
            const profileMotto = document.getElementById('currentUserMotto');
            if (profileName) {
                const badgeHtml = this.getBadgeHtml(data.user);
                profileName.innerHTML = `<span class="friend-name-text">${data.user.username}</span>${badgeHtml}`;
            }
            
            if (profileMotto) profileMotto.innerText = data.user.motto || '[暂无签名]';
            if (profileMotto) profileMotto.title = data.user.motto || '';
            
            // 显示气泡
            const widget = document.querySelector('.chat-widget');
            if (widget) {
                widget.style.display = 'flex'; // 匹配 CSS 中的 display: flex
                console.log('Chat widget displayed for user:', data.user.username);
            }
            
            this.loadFriends();
            this.startUnreadPolling();
        } catch (error) {
            console.error('Chat system init failed:', error);
            const widget = document.querySelector('.chat-widget');
            if (widget) widget.style.display = 'none';
        }
    }

    /**
     * 处理未登录状态
     */
    handleNotLoggedIn() {
        console.warn('Chat system: User not logged in');
        const widget = document.querySelector('.chat-widget');
        if (widget) {
            // 不直接隐藏，而是显示一个“请先登录”的提示（如果是在最大化状态）
            // 或者在气泡点击时提示
            widget.style.display = 'none'; 
        }
        
        // 如果用户尝试操作，可以在这里统一拦截并提示
        this.isLoggedIn = false;
    }

    open(tabName = 'friends') {
        if (!this.isLoggedIn) {
            if (confirm('聊天系统需要登录后使用，是否立即前往登录？')) {
                // 根据当前路径判断登录页位置
                const isSubPage = window.location.pathname.includes('/user/') || 
                                 window.location.pathname.includes('/pages/') || 
                                 window.location.pathname.includes('/games/');
                window.location.href = isSubPage ? '../user/user_login.php' : 'user/user_login.php';
            }
            return;
        }
        const widget = document.querySelector('.chat-widget');
        const overlay = document.querySelector('.chat-overlay');
        if (widget && overlay) {
            // 确保组件在 body 根部，避免父级 transform 限制 position: fixed
            if (widget.parentElement !== document.body) {
                document.body.appendChild(widget);
                document.body.appendChild(overlay);
            }

            widget.classList.remove('minimized');
            widget.classList.add('maximized');
            overlay.classList.add('show');
            
            // 移动端防止背景滚动并确保进入正确的初始面板
            if (window.innerWidth <= 768) {
                this.scrollPos = window.scrollY;
                document.documentElement.classList.add('chat-open');
                document.body.style.position = 'fixed';
                document.body.style.top = `-${this.scrollPos}px`;
                document.body.style.width = '100%';

                // 确保在移动端打开时，如果之前在聊天中，则回到列表/搜索
                const rightPanel = document.querySelector('.chat-right-panel');
                if (rightPanel) {
                    rightPanel.classList.remove('mobile-active');
                    rightPanel.classList.remove('emoji-open');
                }
            }
            
            this.switchTab(tabName);
            this.loadFriends();
        }
    }

    close() {
        const widget = document.querySelector('.chat-widget');
        const overlay = document.querySelector('.chat-overlay');
        if (widget && overlay) {
            widget.classList.add('minimized');
            widget.classList.remove('maximized');
            overlay.classList.remove('show');
            
            // 恢复背景滚动
            document.documentElement.classList.remove('chat-open');
            if (window.innerWidth <= 768) {
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                window.scrollTo(0, this.scrollPos || 0);
            }
            document.body.style.overflow = '';
            document.body.style.height = '';
            
            // 清除可能存在的移动端内联样式
            widget.style.removeProperty('height');
            widget.style.removeProperty('top');
            
            // 重置移动端状态
            const rightPanel = document.querySelector('.chat-right-panel');
            if (rightPanel) {
                rightPanel.classList.remove('mobile-active');
                rightPanel.classList.remove('emoji-open');
            }
            
            this.stopPolling();
            this.updateUnreadCount();
        }
    }

    bindEvents() {
        const widget = document.querySelector('.chat-widget');
        const overlay = document.querySelector('.chat-overlay');
        const bubble = document.querySelector('.bubble-icon');

        if (!widget || !overlay || !bubble) {
            console.error('Chat elements not found:', { widget, overlay, bubble });
            return;
        }

        // 点击气泡或最小化的组件展开
        const openHandler = (e) => {
            if (widget.classList.contains('minimized')) {
                if (e) e.stopPropagation();
                // 移动端默认进入“发现”页，桌面端进入“好友”页
                const defaultTab = window.innerWidth <= 768 ? 'search' : 'friends';
                this.open(defaultTab);
            }
        };
        
        widget.addEventListener('click', openHandler);
        bubble.addEventListener('click', openHandler);

        // 关闭按钮
        const closeBtn = document.getElementById('close-widget-btn');
        const mobileCloseBtn = document.getElementById('mobile-close-widget-btn');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.close();
            });
        }
        
        if (mobileCloseBtn) {
            mobileCloseBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.close();
            });
        }

        // 移动端返回按钮
        const backBtn = document.getElementById('mobile-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const rightPanel = document.querySelector('.chat-right-panel');
                if (rightPanel) {
                    rightPanel.classList.remove('mobile-active');
                    rightPanel.classList.remove('emoji-open');
                }
                const picker = document.querySelector('.emoji-picker');
                if (picker) picker.style.display = 'none';
                
                this.currentFriendId = null;
                this.stopPolling();
            });
        }

        // 监听视口变化（处理移动端软键盘）
        if (window.visualViewport) {
            const handleViewportChange = () => {
                const widget = document.querySelector('.chat-widget.maximized');
                if (widget && window.innerWidth <= 768) {
                    const viewport = window.visualViewport;
                    const height = viewport.height;
                    const top = viewport.offsetTop;
                    
                    // 动态调整高度和位置，确保始终在可见区域
                    // 使用 fixed 定位配合 top 偏移，解决 iOS 键盘弹起时的视口错位
                    widget.style.setProperty('height', `${height}px`, 'important');
                    widget.style.setProperty('top', `${top}px`, 'important');
                    
                    // 强制滚动到顶部，防止浏览器自带的推起效果干扰
                    window.scrollTo(0, 0);
                    
                    // 确保输入框聚焦时滚动到最底部
                    if (document.activeElement.tagName === 'TEXTAREA' || document.activeElement.tagName === 'INPUT') {
                        setTimeout(() => {
                            const container = document.getElementById('messages-container');
                            if (container) {
                                container.scrollTop = container.scrollHeight;
                            }
                        }, 50);
                    }
                }
            };

            window.visualViewport.addEventListener('resize', handleViewportChange);
            window.visualViewport.addEventListener('scroll', handleViewportChange);
        }

        // 移动端滚动穿透处理
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.addEventListener('touchstart', (e) => {
                const top = messagesContainer.scrollTop;
                const totalScroll = messagesContainer.scrollHeight;
                const currentScroll = top + messagesContainer.offsetHeight;

                if (top === 0) {
                    messagesContainer.scrollTop = 1;
                } else if (currentScroll === totalScroll) {
                    messagesContainer.scrollTop = top - 1;
                }
            });
        }

        // 点击遮罩关闭
        overlay.addEventListener('click', () => {
            this.close();
        });

        // 标签切换
        document.querySelectorAll('.chat-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const target = e.currentTarget.dataset.target;
                this.switchTab(target);
            });
        });

        // 搜索功能 (实时搜索)
        let searchTimeout;
        document.getElementById('search-query').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query) {
                this.switchTab('search');
                searchTimeout = setTimeout(() => this.searchUsers(query), 500);
            } else {
                if (document.querySelector('.chat-tab[data-target="search"]').classList.contains('active')) {
                    this.loadRecommendations();
                } else {
                    this.switchTab('friends');
                }
            }
        });

        // 发送消息
        document.getElementById('send-msg-btn').addEventListener('click', () => this.sendMessage());
        
        // Enter 直接发送，Shift+Enter 换行
        document.getElementById('chat-input').addEventListener('keydown', (e) => {
            const btn = document.getElementById('send-msg-btn');
            
            // 实时更新发送按钮状态
            setTimeout(() => {
                if (e.target.value.trim()) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            }, 0);

            if (e.key === 'Enter') {
                if (e.shiftKey) {
                    // Shift + Enter 保持默认行为（换行）
                } else {
                    // 仅 Enter 直接发送
                    e.preventDefault();
                    this.sendMessage();
                }
            }
        });

        // 表情包切换
        document.getElementById('emoji-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            const picker = document.querySelector('.emoji-picker');
            const rightPanel = document.querySelector('.chat-right-panel');
            const isVisible = picker.style.display === 'grid';
            
            picker.style.display = isVisible ? 'none' : 'grid';
            if (rightPanel) {
                if (!isVisible) {
                    rightPanel.classList.add('emoji-open');
                    setTimeout(() => {
                        const container = document.getElementById('messages-container');
                        container.scrollTop = container.scrollHeight;
                    }, 100);
                } else {
                    rightPanel.classList.remove('emoji-open');
                }
            }
        });

        // 图片按钮点击事件
        const imageBtn = document.getElementById('image-btn');
        if (imageBtn) {
            imageBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                alert('无资金开发，如需要，请联系作者');
            });
        }

        // 文件按钮点击事件
        const fileBtn = document.getElementById('file-btn');
        if (fileBtn) {
            fileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                alert('无资金开发，如需要，请联系作者');
            });
        }

        document.addEventListener('click', () => {
            const picker = document.querySelector('.emoji-picker');
            const rightPanel = document.querySelector('.chat-right-panel');
            if (picker) picker.style.display = 'none';
            if (rightPanel) rightPanel.classList.remove('emoji-open');
        });
    }

    switchTab(tabName) {
        document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
        const activeTab = document.querySelector(`.chat-tab[data-target="${tabName}"]`);
        if (activeTab) activeTab.classList.add('active');

        document.getElementById('friend-list-tab').style.display = 'none';
        document.getElementById('search-tab').style.display = 'none';
        document.getElementById('requests-tab').style.display = 'none';

        if (tabName === 'friends') {
            document.getElementById('friend-list-tab').style.display = 'block';
            this.loadFriends();
        } else if (tabName === 'group') {
            document.getElementById('friend-list-tab').style.display = 'block'; // 借用好友列表区域，或者保持现状
            this.openGroupChat();
        } else if (tabName === 'search') {
            document.getElementById('search-tab').style.display = 'block';
            const query = document.getElementById('search-query').value.trim();
            if (!query) {
                this.loadRecommendations();
            }
        } else if (tabName === 'requests') {
            document.getElementById('requests-tab').style.display = 'block';
            this.loadRequests();
        }
    }

    async loadFriends() {
        const res = await fetch(`${this.apiPath}?action=get_friends`, { credentials: 'include' });
        const data = await res.json();
        console.log('Chat System: Friends loaded:', data);
        if (data.success) {
            const container = document.getElementById('friend-list-container');
            container.innerHTML = '';
            if (!data.friends || data.friends.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#999;font-size:13px;">暂无好友</div>';
                return;
            }
            data.friends.forEach(friend => {
                const item = document.createElement('div');
                item.className = `friend-item ${this.currentFriendId == friend.friend_id ? 'active' : ''}`;
                item.onclick = () => this.openChat(friend);
                
                const lastMsg = friend.last_message || '暂无消息';
                const unreadHtml = friend.unread_count > 0 ? `<div class="unread-badge">${friend.unread_count}</div>` : '';
                const avatarHtml = this.getAvatarHtml({id: friend.friend_id, username: friend.username});
                const badgeHtml = this.getBadgeHtml({id: friend.friend_id, role_label: friend.role_label});

                item.innerHTML = `
                    ${avatarHtml}
                    <div class="friend-info">
                        <div class="friend-name">
                            <span class="friend-name-text">${friend.username}</span>
                            ${badgeHtml}
                        </div>
                        <div class="friend-motto">${this.escapeHtml(lastMsg)}</div>
                    </div>
                    ${unreadHtml}
                `;
                container.appendChild(item);
            });
        }
    }

    async loadRecommendations() {
        const res = await fetch(`${this.apiPath}?action=get_recommendations`, { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('search-results');
        container.innerHTML = '';
        if (data.success && data.users.length > 0) {
            container.innerHTML = '<div class="section-header">推荐用户</div>';
            data.users.forEach(user => {
                container.appendChild(this.renderUserItem(user));
            });
        } else {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">暂无其他用户</div>';
        }
    }

    renderUserItem(user) {
        const item = document.createElement('div');
        item.className = 'friend-item';
        
        let buttonHtml = '';
        if (user.relation_status === 'friend') {
            buttonHtml = '<span style="font-size:12px;color:#999;padding-right:10px;">已是好友</span>';
        } else if (user.relation_status === 'pending') {
            buttonHtml = '<span style="font-size:12px;color:#999;padding-right:10px;">已申请</span>';
        } else {
            buttonHtml = `<button class="send-btn active" style="padding:4px 10px;font-size:12px;" onclick="chatSystem.sendRequest(${user.id})">添加</button>`;
        }

        const isGuanen = user.id == 2;
        const avatarHtml = this.getAvatarHtml(user);
        const desc = user.motto || (isGuanen ? `可以申请加 ${user.username} 为好友` : `用户 ID: #${user.id}`);
        const badgeHtml = this.getBadgeHtml(user);

        item.innerHTML = `
            ${avatarHtml}
            <div class="friend-info">
                <div class="friend-name">
                    <span class="friend-name-text">${user.username}</span>
                    ${badgeHtml}
                </div>
                <div class="friend-motto" title="${user.motto || ''}">${desc}</div>
            </div>
            ${buttonHtml}
        `;
        return item;
    }

    async searchUsers(query) {
        const res = await fetch(`${this.apiPath}?action=search_user&query=${encodeURIComponent(query)}`, { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('search-results');
        container.innerHTML = '';
        if (data.success) {
            if (data.users.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">未找到用户</div>';
                return;
            }
            container.innerHTML = '<div class="section-header">搜索结果</div>';
            data.users.forEach(user => {
                container.appendChild(this.renderUserItem(user));
            });
        }
    }

    async sendRequest(receiverId) {
        if (!confirm('确定要发送好友申请吗？')) return;
        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        const res = await fetch(`${this.apiPath}?action=send_request`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const data = await res.json();
        alert(data.message);
    }

    async loadRequests() {
        const res = await fetch(`${this.apiPath}?action=get_requests`, { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('requests-container');
        container.innerHTML = '';
        if (data.success) {
            if (data.requests.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#999;font-size:13px;">暂无好友请求</div>';
                return;
            }
            data.requests.forEach(req => {
                const item = document.createElement('div');
                item.className = 'friend-item';
                
                const badgeHtml = this.getBadgeHtml({id: req.sender_id, role_label: req.role_label});
                const avatarHtml = this.getAvatarHtml({id: req.sender_id, username: req.username});

                item.innerHTML = `
                    ${avatarHtml}
                    <div class="friend-info">
                        <div class="friend-name"><span class="friend-name-text">${req.username}</span>${badgeHtml}</div>
                        <div style="font-size:11px;color:#999">请求添加你为好友</div>
                    </div>
                    <div style="display:flex;gap:5px;">
                        <button class="send-btn active" style="padding:4px 10px;font-size:12px;" onclick="chatSystem.handleRequest(${req.id}, 'accepted')">接受</button>
                        <button class="send-btn" style="padding:4px 10px;font-size:12px;" onclick="chatSystem.handleRequest(${req.id}, 'rejected')">拒绝</button>
                    </div>
                `;
                container.appendChild(item);
            });
        }
    }

    async handleRequest(requestId, status) {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', status);
        const res = await fetch(`${this.apiPath}?action=handle_request`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            this.loadRequests();
            this.loadFriends();
        }
    }

    openChat(friend) {
        this.currentFriendId = friend.friend_id;
        this.currentFriend = friend; // 保存当前聊天对象信息
        this.isGroup = false;
        this.lastMsgId = 0;
        this.lastMessageTime = null; // 重置时间追踪
        
        // UI 切换
        document.getElementById('chat-window-placeholder').style.display = 'none';
        document.getElementById('chat-window').style.display = 'flex';
        
        const badgeHtml = this.getBadgeHtml({id: friend.friend_id, role_label: friend.role_label});
        document.getElementById('chat-friend-name').innerHTML = `<span class="friend-name-text">${friend.username}</span>${badgeHtml}`;
        
        document.getElementById('messages-container').innerHTML = '';
        
        // 移动端适配：显示右侧面板
        document.querySelector('.chat-right-panel').classList.add('mobile-active');
        
        // 高亮选中
        document.querySelectorAll('.friend-item').forEach(item => item.classList.remove('active'));
        this.loadFriends(); // 刷新列表以清除未读数并应用 active 样式

        this.loadMessages();
        this.updateUnreadCount();
        this.startPolling();

        // 移动端额外确保滚动到底部
        if (window.innerWidth <= 768) {
            setTimeout(() => {
                const container = document.getElementById('messages-container');
                if (container) container.scrollTop = container.scrollHeight;
            }, 300);
        }
    }

    /**
     * 打开交流群聊天
     */
    openGroupChat() {
        this.currentFriendId = 'group';
        this.currentFriend = { username: '共享交流群', isGroup: true };
        this.isGroup = true;
        this.lastMsgId = 0;
        this.lastMessageTime = null; // 重置时间追踪

        // UI 切换
        document.getElementById('chat-window-placeholder').style.display = 'none';
        document.getElementById('chat-window').style.display = 'flex';
        
        document.getElementById('chat-friend-name').innerHTML = '<i class="fas fa-users" style="margin-right:8px;color:#3b82f6;"></i><span class="friend-name-text">共享交流群</span>';
        
        document.getElementById('messages-container').innerHTML = '';
        
        // 移动端适配：显示右侧面板
        document.querySelector('.chat-right-panel').classList.add('mobile-active');
        
        // 高亮选中
        document.querySelectorAll('.friend-item').forEach(item => item.classList.remove('active'));

        this.loadMessages();
        this.startPolling();

        // 移动端额外确保滚动到底部
        if (window.innerWidth <= 768) {
            setTimeout(() => {
                const container = document.getElementById('messages-container');
                if (container) container.scrollTop = container.scrollHeight;
            }, 300);
        }
    }

    async loadMessages() {
        if (!this.currentFriendId) return;
        
        let url = '';
        if (this.isGroup) {
            url = `${this.apiPath}?action=get_group_messages&last_id=${this.lastMsgId}`;
        } else {
            url = `${this.apiPath}?action=get_messages&friend_id=${this.currentFriendId}&last_id=${this.lastMsgId}`;
        }

        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.messages.length > 0) {
            const container = document.getElementById('messages-container');
            
            data.messages.forEach(msg => {
                const isSentByMe = msg.sender_id == this.user.id;
                
                // 时间显示策略：判断是否需要插入中间时间条（间隔 > 5分钟）
                const msgTime = new Date(msg.timestamp || msg.created_at || msg.time);
                if (this.lastMessageTime) {
                    const diff = (msgTime - this.lastMessageTime) / 1000 / 60; // 分钟
                    if (diff > 5) {
                        const timeDivider = document.createElement('div');
                        timeDivider.className = 'chat-time-divider';
                        timeDivider.innerHTML = `<span>${this.formatMessageTime(msgTime)}</span>`;
                        container.appendChild(timeDivider);
                    }
                } else if (this.lastMsgId === 0) {
                    // 第一条消息显示时间
                    const timeDivider = document.createElement('div');
                    timeDivider.className = 'chat-time-divider';
                    timeDivider.innerHTML = `<span>${this.formatMessageTime(msgTime)}</span>`;
                    container.appendChild(timeDivider);
                }
                this.lastMessageTime = msgTime;

                const bubble = document.createElement('div');
                bubble.className = `message-bubble ${isSentByMe ? 'sent' : 'received'}`;
                
                // 气泡内只显示 HH:mm
                const hours = msgTime.getHours().toString().padStart(2, '0');
                const minutes = msgTime.getMinutes().toString().padStart(2, '0');
                const innerTimeStr = `${hours}:${minutes}`;
                
                let senderNameHtml = '';
                if (this.isGroup && !isSentByMe) {
                    const badgeHtml = this.getBadgeHtml({id: msg.sender_id, role_label: msg.role_label});
                    senderNameHtml = `<div class="message-sender">${this.escapeHtml(msg.username)}${badgeHtml}</div>`;
                }
                
                bubble.innerHTML = `
                    ${senderNameHtml}
                    <div class="message-content">${this.escapeHtml(msg.message)}</div>
                    <div class="message-time-inner">${innerTimeStr}</div>
                `;
                container.appendChild(bubble);
                this.lastMsgId = Math.max(this.lastMsgId, msg.id);
            });
            
            // 滚动到底部
            container.scrollTop = container.scrollHeight;
            
            if (!this.isGroup) this.updateUnreadCount();
        }
    }

    async sendMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        if (!message || !this.currentFriendId) return;

        input.value = '';
        document.getElementById('send-msg-btn').classList.remove('active');
        
        const formData = new FormData();
        if (!this.isGroup) {
            formData.append('receiver_id', this.currentFriendId);
        }
        formData.append('message', message);

        const action = this.isGroup ? 'send_group_msg' : 'send_message';
        const res = await fetch(`${this.apiPath}?action=${action}`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            this.loadMessages();
            // 私人聊天才更新未读数
            if (!this.isGroup) this.updateUnreadCount();
        }
    }

    renderEmojiPicker() {
        const container = document.querySelector('.emoji-picker');
        this.emojis.forEach(emoji => {
            const span = document.createElement('span');
            span.className = 'emoji-item';
            span.innerText = emoji;
            span.onclick = (e) => {
                e.stopPropagation();
                const input = document.getElementById('chat-input');
                input.value += emoji;
                input.focus();
                document.getElementById('send-msg-btn').classList.add('active');
                container.style.display = 'none';
            };
            container.appendChild(span);
        });
    }

    startPolling() {
        this.stopPolling();
        this.pollingInterval = setInterval(() => this.loadMessages(), 3000);
    }

    stopPolling() {
        if (this.pollingInterval) clearInterval(this.pollingInterval);
    }

    async updateUnreadCount() {
        try {
            const res = await fetch(`${this.apiPath}?action=get_unread_total`, { credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                const bubbleBadge = document.getElementById('bubble-unread');
                const sidebarBadge = document.getElementById('chat-total-unread');
                
                if (data.unread_total > 0) {
                    if (bubbleBadge) {
                        bubbleBadge.innerText = data.unread_total;
                        bubbleBadge.style.display = 'flex';
                    }
                    if (sidebarBadge) {
                        sidebarBadge.innerText = data.unread_total;
                        sidebarBadge.style.display = 'inline-flex';
                    }
                } else {
                    if (bubbleBadge) bubbleBadge.style.display = 'none';
                    if (sidebarBadge) sidebarBadge.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Update unread count failed:', error);
        }
    }

    startUnreadPolling() {
        this.updateUnreadCount(); // 立即执行一次
        this.unreadPollingInterval = setInterval(() => this.updateUnreadCount(), 5000);
    }

    /**
     * 格式化消息时间显示
     * @param {string|number} timestamp - 时间戳或时间字符串
     * @returns {string} 格式化后的时间字符串
     */
    formatMessageTime(timestamp) {
        if (!timestamp) return '';
        
        let date;
        if (typeof timestamp === 'number') {
            date = new Date(timestamp * 1000); // 假设是Unix时间戳
        } else {
            date = new Date(timestamp);
        }
        
        if (isNaN(date.getTime())) return '';
        
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        
        // 格式化时间部分
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const timeStr = `${hours}:${minutes}`;
        
        // 判断日期
        if (messageDate.getTime() === today.getTime()) {
            return timeStr; // 今天只显示时间
        } else if (messageDate.getTime() === yesterday.getTime()) {
            return `昨天 ${timeStr}`; // 昨天
        } else {
            // 显示完整日期
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${month}-${day} ${timeStr}`;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

let chatSystem;
document.addEventListener('DOMContentLoaded', () => {
    chatSystem = new ChatSystem();
});
