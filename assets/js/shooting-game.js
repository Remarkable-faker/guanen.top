// 射击游戏主文件
// 实现纵向卷轴飞机射击游戏的核心功能

// 游戏常量
const GAME_CONSTANTS = {
    PLAYER: {
        INITIAL_HP: 20,
        INITIAL_LIVES: 1,
        DEFAULT_SPEED: 8,
        DEFAULT_FIRE_RATE: 100, // 毫秒
        INVINCIBLE_TIME: 2000, // 无敌时间，毫秒
        MAX_POWER_LEVEL: 6
    },
    ENEMY: {
        TYPES: {
            NORMAL: { hp: 1, speed: 3, score: 10, fireRate: 500, patternRate: 1000 },
            FAST: { hp: 1, speed: 6, score: 20, fireRate: 300, patternRate: 800 },
            STRONG: { hp: 3, speed: 2, score: 30, fireRate: 400, patternRate: 1200 },
            SEEKER: { hp: 2, speed: 4, score: 25, fireRate: 600, patternRate: 1500 },
            SPREADER: { hp: 2, speed: 3, score: 35, fireRate: 700, patternRate: 900 },
            SPLITTER: { hp: 4, speed: 2.5, score: 50, fireRate: 800, patternRate: 1800 }
        },
        SPAWN_RATE: 100, // 初始生成率，帧
        PATTERNS: {
            STRAIGHT: 0,      // 直线下落
            SINE_WAVE: 1,     // 正弦波运动
            SPIRAL: 2,        // 螺旋运动
            HOMING: 3,        // 追踪玩家
            CIRCLE: 4,        // 圆周运动
            SPLIT: 5,         // 分裂行为
            ZIG_ZAG: 6,       // Z字形移动
            WAVE_SPIN: 7,     // 波浪旋转
            ORBIT: 8,         // 围绕中心点轨道运动
            BOUNCE: 9,        // 反弹运动
            EVADE: 10         // 规避玩家子弹
        },
        MAX_ON_SCREEN: 15   // 屏幕上最大敌人数量
    },
    BULLET: {
        PLAYER_SPEED: 10,
        ENEMY_SPEED: 5,
        POOL_SIZE: 400,
        TYPES: {
            SINGLE: 'single',
            DOUBLE: 'double',
            TRIPLE: 'triple',
            SPREAD: 'spread',
            HOMING: 'homing',
            LASER: 'laser',
            BURST: 'burst'
        }
    },
    ITEM: {
        DROP_RATE: 0.5, // 50%掉落率
        TYPES: {
            POWER_UP: 'powerUp',
            HEALTH: 'health',
            ENERGY: 'energy',
            SHIELD: 'shield',
            BOMB: 'bomb',
            RAPID_FIRE: 'rapidFire', // 快速射击
            INVINCIBILITY: 'invincibility' // 无敌
        },
        FALL_SPEED: 2
    },
    WAVE: {
        BASE_ENEMIES: 12,     // 每波基础敌人数量
        INCREMENT: 6,        // 每波增加的敌人数量
        BOSS_WAVE_INTERVAL: 10 // 每10波出现一次BOSS
    },
    DIFFICULTY: {
        EASY: {
            enemyHpMultiplier: 1,
            enemySpeedMultiplier: 1,
            enemyFireRateMultiplier: 1,
            spawnRateMultiplier: 1,
            bulletSpeedMultiplier: 1
        },
        HARD: {
            enemyHpMultiplier: 1.5,
            enemySpeedMultiplier: 1.3,
            enemyFireRateMultiplier: 0.7,
            spawnRateMultiplier: 0.8,
            bulletSpeedMultiplier: 1.2
        }
    },
    SOUND: {
        VOLUME: 0.5,
        TYPES: {
            SHOOT: 'shoot',
            EXPLOSION: 'explosion',
            ITEM_PICKUP: 'itemPickup',
            PLAYER_HIT: 'playerHit',
            SKILL: 'skill',
            BOSS_SPAWN: 'bossSpawn',
            GAME_OVER: 'gameOver',
            POWER_UP: 'powerUp'
        },
        FILES: {
            shoot: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-short-laser-shot-1670.mp3'],
                volume: 0.3
            },
            explosion: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-explosion-in-the-game-2064.mp3'],
                volume: 0.5
            },
            itemPickup: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-arcade-game-jump-coin-216.mp3'],
                volume: 0.4
            },
            playerHit: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-player-losing-or-failing-2042.mp3'],
                volume: 0.6
            },
            skill: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-game-ball-tap-2073.mp3'],
                volume: 0.5
            },
            bossSpawn: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-sci-fi-alarm-or-warning-991.mp3'],
                volume: 0.7
            },
            gameOver: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-arcade-retro-game-over-213.mp3'],
                volume: 0.6
            },
            powerUp: {
                src: ['https://assets.mixkit.co/sfx/preview/mixkit-game-level-completed-2059.mp3'],
                volume: 0.5
            }
        }
    }
};

// 音效管理器
class SoundManager {
    constructor() {
        this.sounds = {};
        this.music = null;
        this.volume = GAME_CONSTANTS.SOUND.VOLUME;
        this.isMuted = false;
        this.audioEnabled = true; // 音频是否可用的标志
        this.init();
    }
    
    // 初始化音效管理器
    init() {
        // 加载所有音效
        for (const [key, soundConfig] of Object.entries(GAME_CONSTANTS.SOUND.FILES)) {
            this.loadSound(key, soundConfig);
        }
    }
    
    // 加载音效
    loadSound(name, config) {
        const audio = new Audio();
        
        // 添加错误处理
        audio.onerror = () => {
            console.warn(`无法加载音效: ${name} (${config.src[0]})`);
            // 不将无效的音频对象存储到sounds中
            this.sounds[name] = null;
        };
        
        // 仅在开发环境中尝试加载音效，避免生产环境中的跨域错误
        // 或者可以选择不加载任何外部音效，完全依赖游戏的视觉反馈
        // audio.src = config.src[0]; // 使用第一个可用的音效文件
        // audio.volume = config.volume * this.volume;
        // this.sounds[name] = audio;
        
        // 暂时禁用外部音效加载，避免net::ERR_BLOCKED_BY_ORB错误
        this.sounds[name] = null;
    }
    
    // 播放音效
    playSound(name) {
        if (this.isMuted || !this.sounds[name]) return;
        
        try {
            // 重置并播放音效
            const audio = this.sounds[name];
            audio.currentTime = 0;
            audio.play().catch(error => {
                // 忽略播放错误，不影响游戏运行
                console.warn(`播放音效失败: ${name}`, error);
            });
        } catch (error) {
            // 捕获所有音频相关错误，确保游戏不会崩溃
            console.warn(`音效系统错误: ${name}`, error);
        }
    }
    
    // 设置音量
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        
        // 更新所有音效的音量
        for (const audio of Object.values(this.sounds)) {
            if (audio) {
                audio.volume = audio.volume * this.volume;
            }
        }
    }
    
    // 切换静音状态
    toggleMute() {
        this.isMuted = !this.isMuted;
        
        // 更新所有音效的静音状态
        for (const audio of Object.values(this.sounds)) {
            if (audio) {
                audio.muted = this.isMuted;
            }
        }
    }
};

// 创建音效管理器实例
const soundManager = new SoundManager();

// 游戏状态
let gameState = {
    canvas: null,
    ctx: null,
    width: 0,
    height: 0,
    gameRunning: false,
    gamePaused: false,
    difficulty: 'easy',
    score: 0,
    level: 1,
    wave: 1,
    waveEnemyCount: 0,         // 当前波次已生成敌人数量
    waveEnemyTotal: 0,         // 当前波次总敌人数量
    levelComplete: false,      // 关卡是否完成
    bossDefeated: false,       // BOSS是否被击败
    lastTime: 0,
    deltaTime: 0
};

// 玩家实体
let player = {
    position: { x: 0, y: 0 },
    velocity: { x: 0, y: 0 },
    hp: GAME_CONSTANTS.PLAYER.INITIAL_HP,
    maxHp: GAME_CONSTANTS.PLAYER.INITIAL_HP,
    lives: GAME_CONSTANTS.PLAYER.INITIAL_LIVES,
    fireRate: GAME_CONSTANTS.PLAYER.DEFAULT_FIRE_RATE,
    tempFireRate: GAME_CONSTANTS.PLAYER.DEFAULT_FIRE_RATE,
    lastFireTime: 0,
    bulletType: 'single',
    powerLevel: 1,
    score: 0,
    invincibleTimer: 0,
    energy: 0,
    maxEnergy: 100,
    shield: false,
    shieldTimer: 0,
    rapidFire: false,
    rapidFireTimer: 0,
    invincible: false
};

// 游戏实体数组
let entities = {
    enemies: [],
    bullets: [],
    items: [],
    explosions: []
};

// 子弹池
let bulletPool = [];

// 输入状态
let input = {
    keys: {},
    mouse: { x: 0, y: 0, down: false },
    touch: { x: 0, y: 0, active: false }
};

// UI元素
let uiElements = {
    score: null,
    level: null,
    wave: null,
    lives: null,
    hpBar: null,
    hpFill: null,
    energyBar: null,
    energyFill: null,
    skillBtn: null,
    startScreen: null,
    gameOverScreen: null,
    pauseMenu: null,
    finalScore: null,
    difficultyBtns: []
};

// 初始化游戏
function initGame() {
    // 获取Canvas元素
    gameState.canvas = document.getElementById('game-canvas');
    gameState.ctx = gameState.canvas.getContext('2d');
    
    // 获取UI元素
    uiElements.score = document.getElementById('score');
    uiElements.level = document.getElementById('level');
    uiElements.wave = document.getElementById('wave');
    uiElements.lives = document.getElementById('lives');
    uiElements.hpBar = document.getElementById('hp-bar');
    uiElements.hpFill = document.getElementById('hp-fill');
    uiElements.energyBar = document.getElementById('energy-bar');
    uiElements.energyFill = document.getElementById('energy-fill');
    uiElements.skillBtn = document.getElementById('skill-btn');
    uiElements.startScreen = document.getElementById('start-screen');
    uiElements.gameOverScreen = document.getElementById('game-over');
    uiElements.finalScore = document.getElementById('final-score');
    uiElements.pauseMenu = document.getElementById('pause-menu');
    uiElements.difficultyBtns = document.querySelectorAll('.difficulty-btn');
    
    // 初始化画布大小
    resizeCanvas();
    
    // 初始化子弹池
    initBulletPool();
    
    // 添加事件监听
    addEventListeners();
    
    // 初始化UI
    updateUI();
    
    console.log('游戏初始化完成');
}

// 初始化子弹池
function initBulletPool() {
    for (let i = 0; i < GAME_CONSTANTS.BULLET.POOL_SIZE; i++) {
        bulletPool.push({
            active: false,
            owner: 'player',
            position: { x: 0, y: 0 },
            velocity: { x: 0, y: 0 },
            damage: 1,
            radius: 3,
            color: '#ffff00'
        });
    }
}

// 从子弹池获取子弹
function getBulletFromPool(owner, position, velocity, damage, color) {
    for (let bullet of bulletPool) {
        if (!bullet.active) {
            bullet.active = true;
            bullet.owner = owner;
            bullet.position = { ...position };
            bullet.velocity = { ...velocity };
            bullet.damage = damage;
            bullet.radius = owner === 'player' ? 3 : 4;
            bullet.color = color || (owner === 'player' ? '#ffff00' : '#ff0000');
            return bullet;
        }
    }
    // 如果池已满，创建新子弹
    return {
        active: true,
        owner,
        position: { ...position },
        velocity: { ...velocity },
        damage,
        radius: owner === 'player' ? 3 : 4,
        color: color || (owner === 'player' ? '#ffff00' : '#ff0000')
    };
}

// 重置游戏
function resetGame() {
    // 重置玩家
    player = {
        position: { x: gameState.width / 2, y: gameState.height - 100 },
        velocity: { x: 0, y: 0 },
        hp: GAME_CONSTANTS.PLAYER.INITIAL_HP,
        maxHp: GAME_CONSTANTS.PLAYER.INITIAL_HP,
        lives: GAME_CONSTANTS.PLAYER.INITIAL_LIVES,
        fireRate: GAME_CONSTANTS.PLAYER.DEFAULT_FIRE_RATE,
        tempFireRate: GAME_CONSTANTS.PLAYER.DEFAULT_FIRE_RATE,
        lastFireTime: 0,
        bulletType: 'single',
        powerLevel: 1,
        score: 0,
        invincibleTimer: 0,
        energy: 0,
        maxEnergy: 100,
        shield: false,
        shieldTimer: 0,
        rapidFire: false,
        rapidFireTimer: 0,
        invincible: false
    };
    
    // 重置游戏状态
    gameState.score = 0;
    gameState.level = 1;
    gameState.wave = 1;
    gameState.waveEnemyCount = 0;
    gameState.waveEnemyTotal = calculateWaveEnemyCount();
    gameState.levelComplete = false;
    gameState.bossDefeated = false;
    gameState.gameRunning = true;
    gameState.gamePaused = false;
    
    // 清空实体
    entities.enemies = [];
    entities.bullets = [];
    entities.items = [];
    entities.explosions = [];
    
    // 重置子弹池
    for (let bullet of bulletPool) {
        bullet.active = false;
    }
    
    // 更新UI
    updateUI();
    
    // 隐藏UI元素
    if (uiElements.startScreen) {
        uiElements.startScreen.style.display = 'none';
    }
    if (uiElements.gameOverScreen) {
        uiElements.gameOverScreen.style.display = 'none';
    }
    if (uiElements.pauseMenu) {
        uiElements.pauseMenu.style.display = 'none';
    }
    
    console.log('游戏重置完成');
}

// 开始游戏
function startGame() {
    resetGame();
    gameLoop();
}

// 游戏循环
function gameLoop(timestamp = 0) {
    if (!gameState.gameRunning) return;
    
    if (gameState.gamePaused) {
        requestAnimationFrame(gameLoop);
        return;
    }
    
    // 计算deltaTime
    if (gameState.lastTime === 0) {
        gameState.lastTime = timestamp;
    }
    gameState.deltaTime = timestamp - gameState.lastTime;
    gameState.lastTime = timestamp;
    
    // 清空画布
    gameState.ctx.clearRect(0, 0, gameState.width, gameState.height);
    
    // 更新游戏逻辑
    updateGame();
    
    // 绘制游戏
    drawGame();
    
    // 继续循环
    requestAnimationFrame(gameLoop);
}

// 更新游戏逻辑
function updateGame() {
    // 更新玩家
    updatePlayer();
    
    // 更新敌人
    updateEnemies();
    
    // 更新子弹
    updateBullets();
    
    // 更新道具
    updateItems();
    
    // 更新爆炸效果
    updateExplosions();
    
    // 生成敌人
    spawnEnemies();
    
    // 碰撞检测
    checkCollisions();
    
    // 检查游戏状态
    checkGameState();
}

// 更新玩家
function updatePlayer() {
    // 处理输入
    handlePlayerInput();
    
    // 更新位置
    player.position.x += player.velocity.x;
    player.position.y += player.velocity.y;
    
    // 边界检查
    player.position.x = Math.max(0, Math.min(gameState.width, player.position.x));
    player.position.y = Math.max(0, Math.min(gameState.height, player.position.y));
    
    // 更新无敌时间
    if (player.invincibleTimer > 0) {
        player.invincibleTimer -= gameState.deltaTime;
        if (player.invincibleTimer <= 0) {
            player.invincibleTimer = 0;
            player.invincible = false;
        }
    }
    
    // 更新护盾时间
    if (player.shield && player.shieldTimer > 0) {
        player.shieldTimer -= gameState.deltaTime;
        if (player.shieldTimer <= 0) {
            player.shield = false;
            player.shieldTimer = 0;
        }
    }
    
    // 更新快速射击时间
    if (player.rapidFire && player.rapidFireTimer > 0) {
        player.rapidFireTimer -= gameState.deltaTime;
        if (player.rapidFireTimer <= 0) {
            player.rapidFire = false;
            player.rapidFireTimer = 0;
            // 恢复原始射击间隔
            player.fireRate = player.tempFireRate;
        }
    }
    
    // 自动射击
    const currentTime = Date.now();
    if (currentTime - player.lastFireTime > player.fireRate) {
        shoot();
        player.lastFireTime = currentTime;
    }
}

// 处理玩家输入
function handlePlayerInput() {
    const speed = GAME_CONSTANTS.PLAYER.DEFAULT_SPEED;
    
    // 重置速度
    player.velocity.x = 0;
    player.velocity.y = 0;
    
    // 键盘输入
    if (input.keys['ArrowLeft'] || input.keys['a'] || input.keys['A']) {
        player.velocity.x = -speed;
    }
    if (input.keys['ArrowRight'] || input.keys['d'] || input.keys['D']) {
        player.velocity.x = speed;
    }
    if (input.keys['ArrowUp'] || input.keys['w'] || input.keys['W']) {
        player.velocity.y = -speed;
    }
    if (input.keys['ArrowDown'] || input.keys['s'] || input.keys['S']) {
        player.velocity.y = speed;
    }
    
    // 鼠标/触摸输入 - 平滑跟随
    if (input.touch.active || (input.mouse.x !== 0 && input.mouse.y !== 0)) {
        const targetX = input.touch.active ? input.touch.x : input.mouse.x;
        const targetY = input.touch.active ? input.touch.y : input.mouse.y;
        
        const dx = targetX - player.position.x;
        const dy = targetY - player.position.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        if (distance > speed) {
            player.velocity.x = (dx / distance) * speed;
            player.velocity.y = (dy / distance) * speed;
        } else {
            player.velocity.x = dx;
            player.velocity.y = dy;
        }
    }
}

// 玩家射击
function shoot() {
    // 根据powerLevel确定射击模式
    switch (player.powerLevel) {
        case 1:
            shootSingle();
            break;
        case 2:
            shootDouble();
            break;
        case 3:
            shootTriple();
            break;
        case 4:
            shootSpread();
            break;
        case 5:
            shootHoming();
            break;
        case 6:
            shootLaser();
            break;
    }
}

// 单发射击
function shootSingle() {
    const bullet = getBulletFromPool(
        'player',
        { x: player.position.x, y: player.position.y },
        { x: 0, y: -GAME_CONSTANTS.BULLET.PLAYER_SPEED },
        1
    );
    entities.bullets.push(bullet);
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.SHOOT);
}

// 双发射击
function shootDouble() {
    const offset = 10;
    
    // 左侧子弹
    const leftBullet = getBulletFromPool(
        'player',
        { x: player.position.x - offset, y: player.position.y },
        { x: 0, y: -GAME_CONSTANTS.BULLET.PLAYER_SPEED },
        1
    );
    
    // 右侧子弹
    const rightBullet = getBulletFromPool(
        'player',
        { x: player.position.x + offset, y: player.position.y },
        { x: 0, y: -GAME_CONSTANTS.BULLET.PLAYER_SPEED },
        1
    );
    
    entities.bullets.push(leftBullet, rightBullet);
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.SHOOT);
}

// 三发射击
function shootTriple() {
    shootSingle();
    shootDouble();
    // 不需要重复播放音效，shootSingle和shootDouble会播放
}

// 散射射击
function shootSpread() {
    const angles = [-30, -15, 0, 15, 30];
    const speed = GAME_CONSTANTS.BULLET.PLAYER_SPEED;
    
    for (let angle of angles) {
        const radian = (angle * Math.PI) / 180;
        const bullet = getBulletFromPool(
            'player',
            { x: player.position.x, y: player.position.y },
            { 
                x: Math.sin(radian) * speed, 
                y: -Math.cos(radian) * speed 
            },
            1
        );
        entities.bullets.push(bullet);
    }
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.SHOOT);
}

// 跟踪射击
function shootHoming() {
    const bullet = getBulletFromPool(
        'player',
        { x: player.position.x, y: player.position.y },
        { x: 0, y: -GAME_CONSTANTS.BULLET.PLAYER_SPEED * 0.8 },
        1,
        '#00ffff'
    );
    bullet.type = GAME_CONSTANTS.BULLET.TYPES.HOMING;
    entities.bullets.push(bullet);
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.SHOOT);
}

// 激光射击 - 穿透型武器
function shootLaser() {
    // 创建激光束（穿透型，伤害更高）
    const bullet = getBulletFromPool(
        'player',
        { x: player.position.x, y: player.position.y },
        { x: 0, y: -GAME_CONSTANTS.BULLET.PLAYER_SPEED * 1.2 },
        2,
        '#ff00ff'
    );
    bullet.type = GAME_CONSTANTS.BULLET.TYPES.LASER;
    bullet.pierce = true; // 穿透能力
    bullet.pierceCount = 3; // 穿透次数
    entities.bullets.push(bullet);
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.SHOOT);
}

// 计算每波敌人总数
function calculateWaveEnemyCount() {
    return GAME_CONSTANTS.WAVE.BASE_ENEMIES + (gameState.wave - 1) * GAME_CONSTANTS.WAVE.INCREMENT;
}

// 生成敌人
function spawnEnemies() {
    // 检查是否是BOSS波次
    const isBossWave = gameState.wave % GAME_CONSTANTS.WAVE.BOSS_WAVE_INTERVAL === 0;
    
    // 如果是BOSS波次，直接生成BOSS（仅当屏幕上没有敌人时）
    if (isBossWave) {
        if (entities.enemies.length === 0) {
            spawnBoss();
        }
        return;
    }
    
    // 限制屏幕上的敌人数量
    if (entities.enemies.length >= GAME_CONSTANTS.ENEMY.MAX_ON_SCREEN) {
        return;
    }
    
    // 检查当前波次是否已经生成了足够的敌人
    if (gameState.waveEnemyCount >= gameState.waveEnemyTotal) {
        return;
    }
    
    const spawnRate = GAME_CONSTANTS.ENEMY.SPAWN_RATE - (gameState.level - 1) * 5;
    const actualSpawnRate = Math.max(20, spawnRate);
    
    if (Math.random() < 1 / actualSpawnRate) {
        let enemyType;
        
        // 根据波次和关卡调整敌人类型分布
        const randomValue = Math.random();
        const waveProgress = Math.min(10, gameState.wave); // 波次进度，最多10
        const totalProgress = (gameState.level - 1) * 5 + waveProgress;
        
        // 根据总进度动态调整敌人类型分布
        if (totalProgress < 5) {
            // 非常前期：主要是普通敌人
            enemyType = randomValue < 0.8 ? 'NORMAL' : randomValue < 0.95 ? 'FAST' : 'STRONG';
        } else if (totalProgress < 10) {
            // 前期：增加快速敌人
            enemyType = randomValue < 0.65 ? 'NORMAL' : randomValue < 0.85 ? 'FAST' : 
                       randomValue < 0.95 ? 'STRONG' : 'SEEKER';
        } else if (totalProgress < 15) {
            // 中期：开始出现特殊敌人
            enemyType = randomValue < 0.5 ? 'NORMAL' : randomValue < 0.7 ? 'FAST' : 
                       randomValue < 0.85 ? 'STRONG' : randomValue < 0.95 ? 'SEEKER' : 'SPREADER';
        } else if (totalProgress < 25) {
            // 中后期：包含更多特殊敌人
            enemyType = randomValue < 0.4 ? 'NORMAL' : randomValue < 0.6 ? 'FAST' : 
                       randomValue < 0.75 ? 'STRONG' : randomValue < 0.85 ? 'SEEKER' : 
                       randomValue < 0.95 ? 'SPREADER' : 'SPLITTER';
        } else {
            // 后期：高比例特殊敌人
            enemyType = randomValue < 0.3 ? 'NORMAL' : randomValue < 0.5 ? 'FAST' : 
                       randomValue < 0.65 ? 'STRONG' : randomValue < 0.75 ? 'SEEKER' : 
                       randomValue < 0.85 ? 'SPREADER' : 'SPLITTER';
        }
        
        const enemyData = GAME_CONSTANTS.ENEMY.TYPES[enemyType];
        const difficultyMultiplier = gameState.difficulty === 'easy' ? 
                                      GAME_CONSTANTS.DIFFICULTY.EASY : 
                                      GAME_CONSTANTS.DIFFICULTY.HARD;
        
        // 创建敌人
        const enemy = {
            type: enemyType,
            position: { x: Math.random() * gameState.width, y: -50 },
            hp: Math.floor(enemyData.hp * difficultyMultiplier.enemyHpMultiplier * (1 + (gameState.level - 1) * 0.1)),
            maxHp: Math.floor(enemyData.hp * difficultyMultiplier.enemyHpMultiplier * (1 + (gameState.level - 1) * 0.1)),
            speed: enemyData.speed * difficultyMultiplier.enemySpeedMultiplier + (gameState.level - 1) * 0.5,
            score: Math.floor(enemyData.score * (1 + (gameState.level - 1) * 0.2)),
            fireRate: Math.floor(enemyData.fireRate * difficultyMultiplier.enemyFireRateMultiplier),
            lastFireTime: Date.now(),
            pattern: Math.floor(Math.random() * Object.keys(GAME_CONSTANTS.ENEMY.PATTERNS).length) // 随机移动模式
        };
        
        entities.enemies.push(enemy);
        gameState.waveEnemyCount++;
    }
}

// 更新敌人
function updateEnemies() {
    for (let i = entities.enemies.length - 1; i >= 0; i--) {
        const enemy = entities.enemies[i];
        
        // 更新位置
        updateEnemyMovement(enemy);
        
        // 敌人射击
    const currentTime = Date.now();
    if (currentTime - enemy.lastFireTime > enemy.fireRate) {
        enemyShoot(enemy);
        enemy.lastFireTime = currentTime;
    }
    
    // 更新BOSS相位
    if (enemy.isBoss) {
        updateBossPhase(enemy);
    }
        
        // 移除超出屏幕的敌人
        if (enemy.position.y > gameState.height + 50) {
            entities.enemies.splice(i, 1);
        }
    }
}

// 更新BOSS相位
function updateBossPhase(boss) {
    // 根据BOSS当前HP更新相位
    const hpPercent = boss.hp / boss.maxHp;
    
    if (hpPercent < 0.33 && boss.phase < 3) {
        boss.phase = 3;
    } else if (hpPercent < 0.66 && boss.phase < 2) {
        boss.phase = 2;
    }
}

// 更新敌人移动
function updateEnemyMovement(enemy) {
    const now = Date.now();
    
    // 根据模式移动
    switch (enemy.pattern) {
        case GAME_CONSTANTS.ENEMY.PATTERNS.STRAIGHT: // 直线下落
            enemy.position.y += enemy.speed;
            break;
        case GAME_CONSTANTS.ENEMY.PATTERNS.SINE_WAVE: // 正弦波运动
            enemy.position.y += enemy.speed;
            enemy.position.x += Math.sin(now / 200) * 3;
            break;
        case GAME_CONSTANTS.ENEMY.PATTERNS.SPIRAL: // 螺旋运动
            // 使用patternTimer代替now，使螺旋运动更可控
            enemy.patternTimer = enemy.patternTimer || 0;
            enemy.patternTimer += gameState.deltaTime / 16;
            
            // 动态调整螺旋半径和速度
            const spiralRadius = 5 + Math.sin(enemy.patternTimer * 0.05) * 3;
            const spiralSpeed = 0.08;
            
            enemy.position.y += enemy.speed * 0.6;
            enemy.position.x += Math.cos(enemy.patternTimer * spiralSpeed) * spiralRadius;
            break;
        case GAME_CONSTANTS.ENEMY.PATTERNS.HOMING: // 追踪玩家
            // 添加追踪延迟，使追踪更有挑战性
            enemy.homingTimer = enemy.homingTimer || 0;
            enemy.homingTimer += gameState.deltaTime / 16;
            
            const dx = player.position.x - enemy.position.x;
            const dy = player.position.y - enemy.position.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            
            // 只在一定距离内追踪，增加策略性
            if (distance > 0 && distance < 300) {
                // 添加转向加速度，使追踪更平滑
                const turnSpeed = 0.1;
                const targetAngle = Math.atan2(dy, dx);
                
                // 保存并更新当前角度
                enemy.currentAngle = enemy.currentAngle || Math.atan2(enemy.speed, 0);
                const angleDiff = (targetAngle - enemy.currentAngle + Math.PI * 2) % (Math.PI * 2);
                
                // 选择最短转向方向
                const turnDirection = angleDiff < Math.PI ? 1 : -1;
                enemy.currentAngle += turnSpeed * turnDirection;
                
                // 限制追踪速度，增加游戏平衡性
                const homingSpeedMultiplier = 0.8;
                enemy.position.x += Math.cos(enemy.currentAngle) * enemy.speed * homingSpeedMultiplier;
                enemy.position.y += Math.sin(enemy.currentAngle) * enemy.speed * homingSpeedMultiplier;
            } else {
                enemy.position.y += enemy.speed;
            }
            break;
        case GAME_CONSTANTS.ENEMY.PATTERNS.CIRCLE: // 圆周运动
            enemy.patternTimer = enemy.patternTimer || 0;
            enemy.patternTimer += gameState.deltaTime / 16;
            
            // 动态半径，随时间变化
            const baseRadius = 60;
            const radiusVariation = 20 * Math.sin(enemy.patternTimer * 0.05);
            const radius = baseRadius + radiusVariation;
            const angleSpeed = 0.04;
            
            // 保存初始位置用于圆周运动
            if (!enemy.orbitCenter) {
                enemy.orbitCenter = { x: enemy.position.x, y: enemy.position.y };
            }
            
            enemy.position.y += enemy.speed * 0.5;
            enemy.position.x = enemy.orbitCenter.x + Math.cos(enemy.patternTimer * angleSpeed) * radius;
            break;
        case GAME_CONSTANTS.ENEMY.PATTERNS.SPLIT: // 分裂行为
            enemy.position.y += enemy.speed;
            // 分裂逻辑在碰撞检测中处理
            break;
        
        case GAME_CONSTANTS.ENEMY.PATTERNS.ZIG_ZAG: // Z字形移动
            enemy.patternTimer = enemy.patternTimer || 0;
            enemy.patternTimer += gameState.deltaTime / 16;
            
            enemy.position.y += enemy.speed;
            enemy.position.x += Math.sin(enemy.patternTimer * 0.1) * 5;
            break;
            
        case GAME_CONSTANTS.ENEMY.PATTERNS.WAVE_SPIN: // 波浪旋转
            enemy.patternTimer = enemy.patternTimer || 0;
            enemy.patternTimer += gameState.deltaTime / 16;
            
            enemy.position.y += enemy.speed * 0.8;
            enemy.position.x += Math.sin(enemy.patternTimer * 0.1) * 4 + Math.cos(enemy.patternTimer * 0.05) * 3;
            break;
            
        case GAME_CONSTANTS.ENEMY.PATTERNS.ORBIT: // 围绕中心点轨道运动
            enemy.patternTimer = enemy.patternTimer || 0;
            enemy.patternTimer += gameState.deltaTime / 16;
            
            // 随机选择或保持一个轨道中心点
            if (!enemy.orbitCenter) {
                enemy.orbitCenter = { x: Math.random() * gameState.width, y: Math.random() * (gameState.height / 2) };
                enemy.orbitRadius = Math.random() * 100 + 50;
            }
            
            enemy.position.x = enemy.orbitCenter.x + Math.cos(enemy.patternTimer * 0.05) * enemy.orbitRadius;
            enemy.position.y = enemy.orbitCenter.y + Math.sin(enemy.patternTimer * 0.05) * enemy.orbitRadius + enemy.speed * 0.3;
            break;
            
        case GAME_CONSTANTS.ENEMY.PATTERNS.BOUNCE: // 反弹运动
            enemy.position.y += enemy.speed;
            
            // 初始化反弹方向
            if (!enemy.bounceDirection) {
                enemy.bounceDirection = Math.random() > 0.5 ? 1 : -1;
            }
            
            enemy.position.x += enemy.bounceDirection * enemy.speed * 0.5;
            
            // 边界反弹
            if (enemy.position.x <= 0 || enemy.position.x >= gameState.width) {
                enemy.bounceDirection *= -1;
            }
            break;
            
        case GAME_CONSTANTS.ENEMY.PATTERNS.EVADE: // 规避玩家子弹
            enemy.position.y += enemy.speed;
            
            // 寻找接近的玩家子弹
            let closestBullet = null;
            let closestDistance = Infinity;
            
            for (let bullet of entities.bullets) {
                if (bullet.owner === 'player') {
                    const dx = bullet.position.x - enemy.position.x;
                    const dy = bullet.position.y - enemy.position.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    // 只考虑前方和当前高度附近的子弹
                    if (distance < 120 && Math.abs(dy) < 80) {
                        closestDistance = distance;
                        closestBullet = bullet;
                    }
                }
            }
            
            // 如果有接近的子弹，向侧面移动规避
            if (closestBullet) {
                // 基于距离的动态规避速度
                const evadeSpeed = Math.min(8, (120 - closestDistance) / 15);
                // 预测子弹轨迹，提前规避
                const bulletVelocityX = closestBullet.velocity.x;
                const bulletVelocityY = closestBullet.velocity.y;
                const futureBulletX = closestBullet.position.x + bulletVelocityX * 5;
                
                // 向子弹未来位置的反方向移动
                enemy.position.x += (futureBulletX > enemy.position.x ? -evadeSpeed : evadeSpeed);
                
                // 添加垂直方向的规避，增加难度
                enemy.position.y += (closestBullet.position.y > enemy.position.y ? -2 : 2);
            }
            break;
    }
    
    // 边界检查
    enemy.position.x = Math.max(0, Math.min(gameState.width, enemy.position.x));
}

// 敌人射击
function enemyShoot(enemy) {
    // BOSS特殊射击逻辑
    if (enemy.isBoss) {
        bossShoot(enemy);
        return;
    }
    
    // 普通敌人射击
    const bullet = getBulletFromPool(
        'enemy',
        { x: enemy.position.x, y: enemy.position.y },
        { x: 0, y: GAME_CONSTANTS.BULLET.ENEMY_SPEED },
        1,
        '#ff0000'
    );
    entities.bullets.push(bullet);
}

// BOSS射击
function bossShoot(boss) {
    const now = Date.now();
    const phase = boss.phase;
    
    // 根据不同相位使用不同的攻击模式
    switch (phase) {
        case 1:
            // 相位1：直线射击 + 扇形散射
            if (now - boss.lastFireTime > 200) {
                // 直线射击
                for (let i = 0; i < 3; i++) {
                    const bullet = getBulletFromPool(
                        'enemy',
                        { x: boss.position.x + (i - 1) * 20, y: boss.position.y },
                        { x: 0, y: GAME_CONSTANTS.BULLET.ENEMY_SPEED * 1.5 },
                        2,
                        '#ff4400'
                    );
                    entities.bullets.push(bullet);
                }
                boss.lastFireTime = now;
            }
            
            if (now - boss.lastFireTime > 1000) {
                // 扇形散射
                const angleStep = 30; // 角度间隔
                const angleCount = 7; // 角度数量
                for (let i = 0; i < angleCount; i++) {
                    const angle = (i - angleCount/2) * angleStep;
                    const radian = (angle * Math.PI) / 180;
                    const speed = GAME_CONSTANTS.BULLET.ENEMY_SPEED * 1.2;
                    
                    const bullet = getBulletFromPool(
                        'enemy',
                        { x: boss.position.x, y: boss.position.y },
                        { 
                            x: Math.sin(radian) * speed, 
                            y: Math.cos(radian) * speed 
                        },
                        1,
                        '#ff8800'
                    );
                    entities.bullets.push(bullet);
                }
                boss.lastFireTime = now;
            }
            break;
            
        case 2:
            // 相位2：螺旋射击 + 跟踪子弹
            if (now - boss.lastFireTime > 300) {
                // 螺旋射击
                const bulletCount = 8;
                for (let i = 0; i < bulletCount; i++) {
                    const angle = (i / bulletCount) * Math.PI * 2;
                    const speed = GAME_CONSTANTS.BULLET.ENEMY_SPEED * 1.3;
                    
                    const bullet = getBulletFromPool(
                        'enemy',
                        { x: boss.position.x, y: boss.position.y },
                        { 
                            x: Math.sin(angle) * speed, 
                            y: Math.cos(angle) * speed 
                        },
                        1,
                        '#ffaa00'
                    );
                    entities.bullets.push(bullet);
                }
                boss.lastFireTime = now;
            }
            
            if (now - boss.lastFireTime > 1500) {
                // 跟踪子弹
                for (let i = 0; i < 4; i++) {
                    const bullet = getBulletFromPool(
                        'enemy',
                        { x: boss.position.x + (i - 1.5) * 30, y: boss.position.y },
                        { 
                            x: (Math.random() - 0.5) * 2, 
                            y: GAME_CONSTANTS.BULLET.ENEMY_SPEED * 0.8 
                        },
                        2,
                        '#ffcc00'
                    );
                    bullet.type = GAME_CONSTANTS.BULLET.TYPES.HOMING;
                    entities.bullets.push(bullet);
                }
                boss.lastFireTime = now;
            }
            break;
            
        case 3:
            // 相位3：全屏弹幕 + 快速射击
            if (now - boss.lastFireTime > 150) {
                // 快速射击
                const angle = Math.atan2(player.position.y - boss.position.y, 
                                        player.position.x - boss.position.x);
                
                for (let i = 0; i < 5; i++) {
                    const offsetAngle = (i - 2) * 0.1;
                    const speed = GAME_CONSTANTS.BULLET.ENEMY_SPEED * 2;
                    
                    const bullet = getBulletFromPool(
                        'enemy',
                        { x: boss.position.x, y: boss.position.y },
                        { 
                            x: Math.sin(angle + offsetAngle) * speed, 
                            y: Math.cos(angle + offsetAngle) * speed 
                        },
                        2,
                        '#ffff00'
                    );
                    entities.bullets.push(bullet);
                }
                boss.lastFireTime = now;
            }
            
            if (now - boss.lastFireTime > 1200) {
                // 全屏弹幕
                const bulletCount = 20;
                for (let i = 0; i < bulletCount; i++) {
                    const angle = (i / bulletCount) * Math.PI * 2;
                    const speed = GAME_CONSTANTS.BULLET.ENEMY_SPEED * 1.5;
                    
                    for (let j = 0; j < 3; j++) {
                        const bullet = getBulletFromPool(
                            'enemy',
                            { x: boss.position.x, y: boss.position.y },
                            { 
                                x: Math.sin(angle + j * 0.2) * speed, 
                                y: Math.cos(angle + j * 0.2) * speed 
                            },
                            1,
                            '#ff00ff'
                        );
                        entities.bullets.push(bullet);
                    }
                }
                boss.lastFireTime = now;
            }
            break;
    }
}

// 更新子弹
function updateBullets() {
    for (let i = entities.bullets.length - 1; i >= 0; i--) {
        const bullet = entities.bullets[i];
        
        // 跟踪子弹特殊处理
        if (bullet.type === GAME_CONSTANTS.BULLET.TYPES.HOMING && bullet.owner === 'player') {
            // 寻找最近的敌人
            let closestEnemy = null;
            let closestDistance = Infinity;
            
            for (let enemy of entities.enemies) {
                const dx = enemy.position.x - bullet.position.x;
                const dy = enemy.position.y - bullet.position.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestEnemy = enemy;
                }
            }
            
            // 如果找到敌人，调整子弹方向
            if (closestEnemy) {
                const dx = closestEnemy.position.x - bullet.position.x;
                const dy = closestEnemy.position.y - bullet.position.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance > 0) {
                    const speed = GAME_CONSTANTS.BULLET.PLAYER_SPEED * 0.8;
                    bullet.velocity.x = (dx / distance) * speed;
                    bullet.velocity.y = (dy / distance) * speed;
                }
            }
        }
        
        // 更新位置
        bullet.position.x += bullet.velocity.x;
        bullet.position.y += bullet.velocity.y;
        
        // 移除超出屏幕的子弹
        if (
            bullet.position.x < -10 || 
            bullet.position.x > gameState.width + 10 || 
            bullet.position.y < -10 || 
            bullet.position.y > gameState.height + 10
        ) {
            bullet.active = false;
            entities.bullets.splice(i, 1);
        }
    }
}

// 更新道具
function updateItems() {
    for (let i = entities.items.length - 1; i >= 0; i--) {
        const item = entities.items[i];
        
        // 道具下落
        item.position.y += 2;
        
        // 移除超出屏幕的道具
        if (item.position.y > gameState.height + 50) {
            entities.items.splice(i, 1);
        }
        
        // 检查玩家拾取
        if (checkCollisionCircle(
            player.position, 20,
            item.position, 15
        )) {
            collectItem(item);
            entities.items.splice(i, 1);
        }
    }
}

// 收集道具
function collectItem(item) {
    switch (item.type) {
        case GAME_CONSTANTS.ITEM.TYPES.POWER_UP:
            if (player.powerLevel < GAME_CONSTANTS.PLAYER.MAX_POWER_LEVEL) {
                player.powerLevel++;
            }
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.POWER_UP);
            break;
        case GAME_CONSTANTS.ITEM.TYPES.HEALTH:
            player.hp = Math.min(player.hp + 30, player.maxHp);
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.ITEM_PICKUP);
            break;
        case GAME_CONSTANTS.ITEM.TYPES.ENERGY:
            player.energy = Math.min(player.energy + 50, player.maxEnergy);
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.ITEM_PICKUP);
            break;
        case GAME_CONSTANTS.ITEM.TYPES.SHIELD:
            player.shield = true;
            player.shieldTimer = 5000; // 5秒护盾
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.ITEM_PICKUP);
            break;
        case GAME_CONSTANTS.ITEM.TYPES.RAPID_FIRE:
            player.rapidFire = true;
            player.rapidFireTimer = 10000; // 10秒快速射击
            // 临时降低射击间隔
            player.tempFireRate = player.fireRate;
            player.fireRate = Math.max(20, player.fireRate / 2); // 最小20ms
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.ITEM_PICKUP);
            break;
        case GAME_CONSTANTS.ITEM.TYPES.INVINCIBILITY:
            player.invincible = true;
            player.invincibleTimer = 5000; // 5秒无敌
            soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.ITEM_PICKUP);
            break;
    }
    
    updateUI();
}

// 更新爆炸效果
function updateExplosions() {
    for (let i = entities.explosions.length - 1; i >= 0; i--) {
        const explosion = entities.explosions[i];
        explosion.life -= gameState.deltaTime;
        
        // 更新爆炸粒子
        for (let j = explosion.particles.length - 1; j >= 0; j--) {
            const particle = explosion.particles[j];
            particle.life -= gameState.deltaTime;
            
            // 更新粒子位置
            particle.x += particle.vx;
            particle.y += particle.vy;
            
            // 粒子减速
            particle.vx *= 0.98;
            particle.vy *= 0.98;
            
            // 添加重力效果
            particle.vy += 0.1;
            
            // 移除过期粒子
            if (particle.life <= 0) {
                explosion.particles.splice(j, 1);
            }
        }
        
        // 更新冲击波效果
        if (explosion.shockwave) {
            // 确保progress在0到1之间，避免负数半径
            const progress = Math.max(0, Math.min(1, (explosion.maxLife - explosion.life) / explosion.maxLife));
            explosion.shockwave.radius = explosion.shockwave.maxRadius * progress;
            explosion.shockwave.opacity = 1 - progress;
        }
        
        if (explosion.life <= 0) {
            entities.explosions.splice(i, 1);
        }
    }
}

// 创建爆炸效果
function createExplosion(position, size = 50, color = '#ff6600') {
    entities.explosions.push({
        position: { ...position },
        size,
        maxSize: size,
        life: 1000, // 延长效果时间到1秒
        maxLife: 1000,
        color,
        particles: []
    });
    
    const explosion = entities.explosions[entities.explosions.length - 1];
    
    // 添加爆炸核心粒子（减少数量，优化性能）
    for (let i = 0; i < 20; i++) {
        explosion.particles.push({
            x: position.x,
            y: position.y,
            vx: (Math.random() - 0.5) * 12,
            vy: (Math.random() - 0.5) * 12,
            life: 1000,
            maxLife: 1000,
            size: 4 + Math.random() * 6,
            color: '#ffffcc' // 更亮的核心色
        });
    }
    
    // 添加中层粒子（减少数量，优化性能）
    for (let i = 0; i < 30; i++) {
        // 随机选择中层颜色
        const middleColors = [color, '#ff8800', '#ffaa00', '#ffcc00'];
        const middleColor = middleColors[Math.floor(Math.random() * middleColors.length)];
        
        explosion.particles.push({
            x: position.x,
            y: position.y,
            vx: (Math.random() - 0.5) * 10,
            vy: (Math.random() - 0.5) * 10,
            life: 1000,
            maxLife: 1000,
            size: 3 + Math.random() * 5,
            color: middleColor
        });
    }
    
    // 添加外层扩散粒子（减少数量，优化性能）
    for (let i = 0; i < 20; i++) {
        explosion.particles.push({
            x: position.x,
            y: position.y,
            vx: (Math.random() - 0.5) * 15,
            vy: (Math.random() - 0.5) * 15,
            life: 1000,
            maxLife: 1000,
            size: 2 + Math.random() * 4,
            color: '#ffaa00' // 外层橙黄色
        });
    }
    
    // 添加火花粒子（减少数量，优化性能）
    for (let i = 0; i < 10; i++) {
        explosion.particles.push({
            x: position.x,
            y: position.y,
            vx: (Math.random() - 0.5) * 20,
            vy: (Math.random() - 0.5) * 20,
            life: 600,
            maxLife: 600,
            size: 1 + Math.random() * 3,
            color: '#ffffff' // 白色火花
        });
    }
    
    // 添加冲击波效果
    explosion.shockwave = {
        radius: 0,
        maxRadius: size * 3,
        opacity: 1
    };
}

// 碰撞检测 - 圆形碰撞
function checkCollisionCircle(obj1, radius1, obj2, radius2) {
    const dx = obj1.x - obj2.x;
    const dy = obj1.y - obj2.y;
    const distance = Math.sqrt(dx * dx + dy * dy);
    return distance < (radius1 + radius2);
}

// 碰撞检测
function checkCollisions() {
    // 玩家子弹 vs 敌人
    for (let i = entities.bullets.length - 1; i >= 0; i--) {
        const bullet = entities.bullets[i];
        let shouldRemoveBullet = false;
        
        if (bullet.owner === 'player') {
            for (let j = entities.enemies.length - 1; j >= 0; j--) {
                const enemy = entities.enemies[j];
                
                if (checkCollisionCircle(
                    bullet.position, bullet.radius,
                    enemy.position, 25 // 敌人碰撞半径
                )) {
                    // 敌人受伤
                    enemy.hp -= bullet.damage;
                    
                    // 检查敌人是否死亡
                    if (enemy.hp <= 0) {
                        // 先从数组中移除敌人，再调用killEnemy函数
                        // 这样可以避免在killEnemy中修改entities.enemies时可能导致的问题
                        entities.enemies.splice(j, 1);
                        killEnemy(enemy);
                    }
                    
                    // 检查子弹是否有穿透属性
                    if (bullet.pierce && bullet.pierceCount > 0) {
                        // 减少穿透次数
                        bullet.pierceCount--;
                        // 如果还有穿透次数，继续穿透
                        if (bullet.pierceCount <= 0) {
                            shouldRemoveBullet = true;
                        }
                    } else {
                        // 普通子弹，直接失效
                        shouldRemoveBullet = true;
                    }
                    
                    // 如果子弹需要移除，跳出敌人循环
                    if (shouldRemoveBullet) {
                        break;
                    }
                }
            }
            
            // 处理子弹移除
            if (shouldRemoveBullet) {
                bullet.active = false;
                entities.bullets.splice(i, 1);
            }
        }
    }
    
    // 敌人子弹 vs 玩家
    for (let i = entities.bullets.length - 1; i >= 0; i--) {
        const bullet = entities.bullets[i];
        
        if (bullet.owner === 'enemy') {
            if (checkCollisionCircle(
                bullet.position, bullet.radius,
                player.position, 20 // 玩家碰撞半径
            )) {
                // 玩家受伤（如果不在无敌状态且没有护盾）
            if ((player.invincibleTimer <= 0 && !player.invincible) && !player.shield) {
                player.hp -= bullet.damage;
                player.invincibleTimer = GAME_CONSTANTS.PLAYER.INVINCIBLE_TIME;
                
                // 播放玩家受伤音效
                soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.PLAYER_HIT);
                
                // 检查玩家是否死亡
                if (player.hp <= 0) {
                    playerDie();
                }
            }
                
                // 子弹失效
                bullet.active = false;
                entities.bullets.splice(i, 1);
            }
        }
    }
    
    // 玩家 vs 敌人
    for (let i = entities.enemies.length - 1; i >= 0; i--) {
        const enemy = entities.enemies[i];
        
        if (checkCollisionCircle(
            player.position, 20,
            enemy.position, 25
        )) {
            // 玩家受伤
            if ((player.invincibleTimer <= 0 && !player.invincible) && !player.shield) {
                player.hp -= 20; // 碰撞伤害更高
                player.invincibleTimer = GAME_CONSTANTS.PLAYER.INVINCIBLE_TIME;
                
                // 播放玩家受伤音效
                soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.PLAYER_HIT);
                
                // 检查玩家是否死亡
                if (player.hp <= 0) {
                    playerDie();
                }
            }
            
            // 敌人死亡
            killEnemy(enemy);
            entities.enemies.splice(i, 1);
        }
    }
}

// 杀死敌人
function killEnemy(enemy) {
    // 增加分数
    gameState.score += enemy.score;
    player.score += enemy.score;
    
    // BOSS特殊处理
    if (enemy.isBoss) {
        gameState.bossDefeated = true;
        // 播放BOSS击败音效
        soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.POWER_UP);
    }
    
    // 分裂敌人特殊处理
    if (enemy.type === 'SPLITTER') {
        // 生成两个更小的敌人
        for (let i = 0; i < 2; i++) {
            const splitEnemy = {
                type: 'NORMAL',
                position: { 
                    x: enemy.position.x + (i === 0 ? -30 : 30), 
                    y: enemy.position.y 
                },
                hp: 1,
                maxHp: 1,
                speed: enemy.speed * 1.5,
                score: enemy.score / 2,
                fireRate: enemy.fireRate,
                lastFireTime: Date.now(),
                pattern: GAME_CONSTANTS.ENEMY.PATTERNS.STRAIGHT
            };
            entities.enemies.push(splitEnemy);
        }
    } else if (!enemy.isBoss) {
        // 有概率掉落道具（除了BOSS和分裂敌人）
        if (Math.random() < GAME_CONSTANTS.ITEM.DROP_RATE) {
            dropItem(enemy.position);
        }
    }
    
    // 创建爆炸效果
    createExplosion(enemy.position);
    
    // 播放爆炸音效
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.EXPLOSION);
    
    // 更新UI
    updateUI();
}

// 掉落道具
function dropItem(position) {
    const itemTypes = Object.values(GAME_CONSTANTS.ITEM.TYPES);
    const itemType = itemTypes[Math.floor(Math.random() * itemTypes.length)];
    
    const item = {
        type: itemType,
        position: { ...position },
        size: 20
    };
    
    entities.items.push(item);
}

// 玩家死亡
function playerDie() {
    player.lives--;
    
    if (player.lives > 0) {
        // 重生
        player.position = { x: gameState.width / 2, y: gameState.height - 100 };
        player.hp = player.maxHp;
        player.invincibleTimer = GAME_CONSTANTS.PLAYER.INVINCIBLE_TIME;
    } else {
        // 游戏结束
        gameOver();
    }
    
    updateUI();
}

// 生成BOSS
function spawnBoss() {
    // 创建BOSS敌人
    const boss = {
        type: 'BOSS',
        position: { x: gameState.width / 2, y: -100 },
        hp: 50 + (gameState.level - 1) * 20,
        maxHp: 50 + (gameState.level - 1) * 20,
        speed: 1.5,
        score: 500 + (gameState.level - 1) * 100,
        fireRate: 200,
        lastFireTime: Date.now(),
        pattern: GAME_CONSTANTS.ENEMY.PATTERNS.STRAIGHT,
        isBoss: true,
        phase: 1,
        maxPhase: 3
    };
    
    entities.enemies.push(boss);
    
    // 播放BOSS生成音效
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.BOSS_SPAWN);
    
    console.log('生成BOSS:', boss);
}

// 检查游戏状态
function checkGameState() {
    // 检查波次完成条件：
    // 1. 当前波次的敌人已经全部生成
    // 2. 屏幕上没有敌人
    // 3. 如果是BOSS波次，BOSS已经被击败
    const isWaveEnemiesGenerated = gameState.waveEnemyCount >= gameState.waveEnemyTotal;
    const isScreenClear = entities.enemies.length === 0;
    const isBossWave = gameState.wave % GAME_CONSTANTS.WAVE.BOSS_WAVE_INTERVAL === 0;
    const isBossDefeated = !isBossWave || gameState.bossDefeated;
    
    if (isWaveEnemiesGenerated && isScreenClear && isBossDefeated) {
        // 波次完成
        handleWaveComplete();
    }
}

// 处理波次完成
function handleWaveComplete() {
    // 重置波次状态
    gameState.wave++;
    gameState.waveEnemyCount = 0;
    gameState.waveEnemyTotal = calculateWaveEnemyCount();
    gameState.bossDefeated = false;
    
    // 检查是否需要提升关卡
    if (gameState.wave > GAME_CONSTANTS.WAVE.BOSS_WAVE_INTERVAL) {
        handleLevelUp();
    }
    
    // 更新UI
    updateUI();
    
    console.log(`波次完成！当前：第${gameState.level}关 第${gameState.wave}波`);
}

// 处理关卡提升
function handleLevelUp() {
    gameState.level++;
    gameState.wave = 1;
    
    // 重置波次计数
    gameState.waveEnemyCount = 0;
    gameState.waveEnemyTotal = calculateWaveEnemyCount();
    
    console.log(`关卡提升！当前：第${gameState.level}关`);
    
    // 可以在这里添加关卡提升的特效或奖励
}

// 游戏结束
function gameOver() {
    gameState.gameRunning = false;
    
    // 播放游戏结束音效
    soundManager.playSound(GAME_CONSTANTS.SOUND.TYPES.GAME_OVER);
    
    // 更新最终分数
    if (uiElements.finalScore) {
        uiElements.finalScore.textContent = `最终分数: ${gameState.score}`;
    }
    
    // 显示游戏结束界面
    if (uiElements.gameOverScreen) {
        uiElements.gameOverScreen.style.display = 'block';
    }
}

// 绘制游戏
function drawGame() {
    // 绘制背景
    drawBackground();
    
    // 绘制玩家
    drawPlayer();
    
    // 绘制敌人
    drawEnemies();
    
    // 绘制子弹
    drawBullets();
    
    // 绘制道具
    drawItems();
    
    // 绘制爆炸效果
    drawExplosions();
}

// 绘制背景
function drawBackground() {
    const ctx = gameState.ctx;
    
    // 创建多层渐变背景
    const gradient = ctx.createLinearGradient(0, 0, 0, gameState.height);
    gradient.addColorStop(0, '#001133');
    gradient.addColorStop(0.5, '#000022');
    gradient.addColorStop(1, '#000000');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameState.width, gameState.height);
    
    // 绘制动态星星
    ctx.fillStyle = '#ffffff';
    for (let i = 0; i < 150; i++) {
        // 使用质数和随时间变化的偏移量生成更自然的星星分布
        const timeOffset = Date.now() * 0.001;
        const x = (i * 137.5 + timeOffset * 10) % gameState.width;
        const y = (i * 197.5 + gameState.level * 50 + timeOffset * 5) % gameState.height;
        const size = 0.5 + Math.random() * 2.5;
        
        // 星星闪烁效果
        const twinkle = 0.5 + 0.5 * Math.sin(i * 5 + timeOffset * 2);
        ctx.globalAlpha = twinkle;
        
        // 星星发光效果
        ctx.shadowBlur = size * 2;
        ctx.shadowColor = '#ffffff';
        
        ctx.fillRect(x, y, size, size);
    }
    
    ctx.globalAlpha = 1;
    ctx.shadowBlur = 0;
}

// 绘制玩家
function drawPlayer() {
    const ctx = gameState.ctx;
    const pos = player.position;
    
    // 无敌状态闪烁效果
    if (player.invincibleTimer > 0 && Math.floor(Date.now() / 100) % 2 === 0) {
        return;
    }
    
    ctx.save();
    
    // 绘制护盾
    if (player.shield) {
        // 护盾脉动效果
        const shieldPulse = 35 + Math.sin(Date.now() * 0.01) * 3;
        // 确保半径参数大于或等于0，避免Canvas错误
        const innerRadius = Math.max(0, shieldPulse - 5);
        const gradient = ctx.createRadialGradient(pos.x, pos.y, innerRadius, pos.x, pos.y, shieldPulse + 5);
        gradient.addColorStop(0, 'rgba(0, 255, 255, 0.3)');
        gradient.addColorStop(0.8, 'rgba(0, 255, 255, 0.7)');
        gradient.addColorStop(1, 'rgba(0, 255, 255, 0)');
        
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, shieldPulse, 0, Math.PI * 2);
        ctx.fill();
        
        // 护盾外轮廓
        ctx.strokeStyle = '#00ffff';
        ctx.lineWidth = 3;
        ctx.globalAlpha = 0.8;
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, shieldPulse, 0, Math.PI * 2);
        ctx.stroke();
    }
    
    // 玩家飞机发光效果
    ctx.shadowBlur = 20;
    ctx.shadowColor = '#00ff00';
    
    // 机身主体 - 流线型设计
    ctx.fillStyle = '#00aa00';
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 30);
    ctx.lineTo(pos.x - 22, pos.y + 15);
    ctx.lineTo(pos.x - 18, pos.y + 28);
    ctx.lineTo(pos.x + 18, pos.y + 28);
    ctx.lineTo(pos.x + 22, pos.y + 15);
    ctx.closePath();
    ctx.fill();
    
    // 机身侧面 - 更立体的效果
    ctx.fillStyle = '#008800';
    ctx.beginPath();
    ctx.moveTo(pos.x - 22, pos.y + 15);
    ctx.lineTo(pos.x - 18, pos.y + 28);
    ctx.lineTo(pos.x - 10, pos.y + 25);
    ctx.lineTo(pos.x - 12, pos.y + 10);
    ctx.closePath();
    ctx.fill();
    
    ctx.beginPath();
    ctx.moveTo(pos.x + 22, pos.y + 15);
    ctx.lineTo(pos.x + 18, pos.y + 28);
    ctx.lineTo(pos.x + 10, pos.y + 25);
    ctx.lineTo(pos.x + 12, pos.y + 10);
    ctx.closePath();
    ctx.fill();
    
    // 机身高光
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(pos.x - 3, pos.y - 25);
    ctx.lineTo(pos.x - 10, pos.y + 5);
    ctx.lineTo(pos.x + 10, pos.y + 5);
    ctx.lineTo(pos.x + 3, pos.y - 25);
    ctx.closePath();
    ctx.fill();
    
    // 机翼 - 更复杂的结构
    ctx.fillStyle = '#00cc00';
    ctx.beginPath();
    ctx.moveTo(pos.x - 35, pos.y + 5);
    ctx.lineTo(pos.x - 18, pos.y + 10);
    ctx.lineTo(pos.x - 10, pos.y - 10);
    ctx.lineTo(pos.x - 25, pos.y - 15);
    ctx.lineTo(pos.x - 32, pos.y - 5);
    ctx.closePath();
    ctx.fill();
    
    ctx.beginPath();
    ctx.moveTo(pos.x + 35, pos.y + 5);
    ctx.lineTo(pos.x + 18, pos.y + 10);
    ctx.lineTo(pos.x + 10, pos.y - 10);
    ctx.lineTo(pos.x + 25, pos.y - 15);
    ctx.lineTo(pos.x + 32, pos.y - 5);
    ctx.closePath();
    ctx.fill();
    
    // 机翼装饰线
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pos.x - 35, pos.y + 5);
    ctx.lineTo(pos.x - 10, pos.y - 10);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(pos.x + 35, pos.y + 5);
    ctx.lineTo(pos.x + 10, pos.y - 10);
    ctx.stroke();
    
    // 驾驶舱
    ctx.fillStyle = '#0066cc';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y - 10, 8, 0, Math.PI * 2);
    ctx.fill();
    
    // 驾驶舱玻璃高光
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(pos.x - 3, pos.y - 13, 3, 0, Math.PI * 2);
    ctx.fill();
    
    // 尾翼
    ctx.fillStyle = '#ff6600';
    ctx.beginPath();
    ctx.moveTo(pos.x - 8, pos.y + 20);
    ctx.lineTo(pos.x - 15, pos.y + 38);
    ctx.lineTo(pos.x - 3, pos.y + 35);
    ctx.closePath();
    ctx.fill();
    
    ctx.beginPath();
    ctx.moveTo(pos.x + 8, pos.y + 20);
    ctx.lineTo(pos.x + 15, pos.y + 38);
    ctx.lineTo(pos.x + 3, pos.y + 35);
    ctx.closePath();
    ctx.fill();
    
    // 动态引擎火焰效果
    const currentTime = Date.now();
    const flameIntensity = 0.5 + Math.sin(currentTime * 0.01) * 0.5;
    
    // 左引擎火焰
    ctx.fillStyle = '#ff6600';
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y + 28);
    ctx.lineTo(pos.x - 20, pos.y + 28 + 15 * flameIntensity);
    ctx.lineTo(pos.x - 10, pos.y + 28 + 15 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    ctx.fillStyle = '#ffff00';
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y + 28);
    ctx.lineTo(pos.x - 17, pos.y + 28 + 10 * flameIntensity);
    ctx.lineTo(pos.x - 13, pos.y + 28 + 10 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y + 28);
    ctx.lineTo(pos.x - 16, pos.y + 28 + 5 * flameIntensity);
    ctx.lineTo(pos.x - 14, pos.y + 28 + 5 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右引擎火焰
    ctx.fillStyle = '#ff6600';
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y + 28);
    ctx.lineTo(pos.x + 20, pos.y + 28 + 15 * flameIntensity);
    ctx.lineTo(pos.x + 10, pos.y + 28 + 15 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    ctx.fillStyle = '#ffff00';
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y + 28);
    ctx.lineTo(pos.x + 17, pos.y + 28 + 10 * flameIntensity);
    ctx.lineTo(pos.x + 13, pos.y + 28 + 10 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y + 28);
    ctx.lineTo(pos.x + 16, pos.y + 28 + 5 * flameIntensity);
    ctx.lineTo(pos.x + 14, pos.y + 28 + 5 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 引擎细节
    ctx.fillStyle = '#333333';
    ctx.beginPath();
    ctx.arc(pos.x - 15, pos.y + 28, 5, 0, Math.PI * 2);
    ctx.arc(pos.x + 15, pos.y + 28, 5, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.restore();
}

// 绘制敌人
function drawEnemies() {
    const ctx = gameState.ctx;
    
    entities.enemies.forEach(enemy => {
        const pos = enemy.position;
        
        // 绘制敌机（增强版）
        ctx.save();
        
        // 根据敌人类型绘制不同外观和颜色
        switch (enemy.type) {
            case 'NORMAL':
                // 普通敌机 - 红色流线型
                drawNormalEnemy(ctx, pos);
                break;
            case 'FAST':
                // 快速敌机 - 橙色扁平型
                drawFastEnemy(ctx, pos);
                break;
            case 'STRONG':
                // 强化敌机 - 紫色厚重型
                drawStrongEnemy(ctx, pos);
                break;
            case 'SEEKER':
                // 追踪敌机 - 蓝色箭头型
                drawSeekerEnemy(ctx, pos);
                break;
            case 'SPREADER':
                // 散射敌机 - 绿色多炮管型
                drawSpreaderEnemy(ctx, pos);
                break;
            case 'SPLITTER':
                // 分裂敌机 - 黄色双体型
                drawSplitterEnemy(ctx, pos);
                break;
            default:
                // 默认敌机
                drawNormalEnemy(ctx, pos);
        }
        
        // 绘制血条
        const hpPercent = enemy.hp / enemy.maxHp;
        ctx.fillStyle = '#333333';
        ctx.fillRect(pos.x - 20, pos.y - 30, 40, 5);
        ctx.fillStyle = hpPercent > 0.5 ? '#00ff00' : hpPercent > 0.2 ? '#ffff00' : '#ff0000';
        ctx.fillRect(pos.x - 20, pos.y - 30, 40 * hpPercent, 5);
        
        ctx.restore();
    });
}

// 绘制普通敌机
function drawNormalEnemy(ctx, pos) {
    // 机身主体 - 红色流线型
    ctx.fillStyle = '#ff0000';
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 25);
    ctx.lineTo(pos.x - 18, pos.y + 20);
    ctx.lineTo(pos.x - 12, pos.y + 28);
    ctx.lineTo(pos.x + 12, pos.y + 28);
    ctx.lineTo(pos.x + 18, pos.y + 20);
    ctx.closePath();
    ctx.fill();
    
    // 机身侧面 - 增强立体感
    ctx.fillStyle = '#cc0000';
    ctx.beginPath();
    ctx.moveTo(pos.x - 18, pos.y + 20);
    ctx.lineTo(pos.x - 12, pos.y + 28);
    ctx.lineTo(pos.x - 15, pos.y + 33);
    ctx.lineTo(pos.x - 23, pos.y + 25);
    ctx.closePath();
    ctx.fill();
    
    ctx.beginPath();
    ctx.moveTo(pos.x + 18, pos.y + 20);
    ctx.lineTo(pos.x + 12, pos.y + 28);
    ctx.lineTo(pos.x + 15, pos.y + 33);
    ctx.lineTo(pos.x + 23, pos.y + 25);
    ctx.closePath();
    ctx.fill();
    
    // 机翼 - 增强设计
    ctx.fillStyle = '#ff3300';
    // 左机翼
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y - 5);
    ctx.lineTo(pos.x - 35, pos.y + 10);
    ctx.lineTo(pos.x - 30, pos.y + 18);
    ctx.lineTo(pos.x - 10, pos.y + 3);
    ctx.closePath();
    ctx.fill();
    
    // 右机翼
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y - 5);
    ctx.lineTo(pos.x + 35, pos.y + 10);
    ctx.lineTo(pos.x + 30, pos.y + 18);
    ctx.lineTo(pos.x + 10, pos.y + 3);
    ctx.closePath();
    ctx.fill();
    
    // 驾驶舱
    ctx.fillStyle = '#00ffff';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y - 15, 8, 0, Math.PI * 2);
    ctx.fill();
    
    // 引擎火焰 - 动态效果
    const currentTime = Date.now();
    const flameIntensity = 0.4 + Math.sin(currentTime * 0.01 + pos.x) * 0.3;
    
    ctx.fillStyle = '#ff6600';
    // 左引擎火焰
    ctx.beginPath();
    ctx.moveTo(pos.x - 10, pos.y + 28);
    ctx.lineTo(pos.x - 15, pos.y + 28 + 12 * flameIntensity);
    ctx.lineTo(pos.x - 5, pos.y + 28 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右引擎火焰
    ctx.beginPath();
    ctx.moveTo(pos.x + 10, pos.y + 28);
    ctx.lineTo(pos.x + 15, pos.y + 28 + 12 * flameIntensity);
    ctx.lineTo(pos.x + 5, pos.y + 28 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
}

// 绘制快速敌机
function drawFastEnemy(ctx, pos) {
    // 快速敌机 - 橙色扁平型
    ctx.fillStyle = '#ffaa00';
    
    // 机身主体 - 扁平流线型
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 20);
    ctx.lineTo(pos.x - 25, pos.y + 15);
    ctx.lineTo(pos.x - 15, pos.y + 25);
    ctx.lineTo(pos.x + 15, pos.y + 25);
    ctx.lineTo(pos.x + 25, pos.y + 15);
    ctx.closePath();
    ctx.fill();
    
    // 机翼 - 锐利三角形
    ctx.fillStyle = '#ff8800';
    // 左机翼
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y - 10);
    ctx.lineTo(pos.x - 40, pos.y + 5);
    ctx.lineTo(pos.x - 25, pos.y + 10);
    ctx.closePath();
    ctx.fill();
    
    // 右机翼
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y - 10);
    ctx.lineTo(pos.x + 40, pos.y + 5);
    ctx.lineTo(pos.x + 25, pos.y + 10);
    ctx.closePath();
    ctx.fill();
    
    // 驾驶舱
    ctx.fillStyle = '#00ffff';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y - 5, 6, 0, Math.PI * 2);
    ctx.fill();
    
    // 尾翼
    ctx.fillStyle = '#ff6600';
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 20);
    ctx.lineTo(pos.x - 8, pos.y - 30);
    ctx.lineTo(pos.x + 8, pos.y - 30);
    ctx.closePath();
    ctx.fill();
    
    // 引擎火焰 - 蓝色尾焰（代表高速）
    const currentTime = Date.now();
    const flameIntensity = 0.5 + Math.sin(currentTime * 0.02 + pos.x) * 0.4;
    
    // 主引擎火焰
    ctx.fillStyle = '#0088ff';
    ctx.beginPath();
    ctx.moveTo(pos.x - 10, pos.y + 25);
    ctx.lineTo(pos.x - 12, pos.y + 25 + 18 * flameIntensity);
    ctx.lineTo(pos.x + 12, pos.y + 25 + 18 * flameIntensity);
    ctx.lineTo(pos.x + 10, pos.y + 25);
    ctx.closePath();
    ctx.fill();
    
    // 内层火焰
    ctx.fillStyle = '#00ccff';
    ctx.beginPath();
    ctx.moveTo(pos.x - 6, pos.y + 25);
    ctx.lineTo(pos.x - 8, pos.y + 25 + 14 * flameIntensity);
    ctx.lineTo(pos.x + 8, pos.y + 25 + 14 * flameIntensity);
    ctx.lineTo(pos.x + 6, pos.y + 25);
    ctx.closePath();
    ctx.fill();
}

// 绘制强化敌机
function drawStrongEnemy(ctx, pos) {
    // 强化敌机 - 紫色厚重型
    ctx.fillStyle = '#aa00ff';
    
    // 机身主体 - 厚重装甲
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 30);
    ctx.lineTo(pos.x - 25, pos.y + 20);
    ctx.lineTo(pos.x - 20, pos.y + 35);
    ctx.lineTo(pos.x + 20, pos.y + 35);
    ctx.lineTo(pos.x + 25, pos.y + 20);
    ctx.closePath();
    ctx.fill();
    
    // 装甲板
    ctx.fillStyle = '#8800cc';
    ctx.beginPath();
    ctx.moveTo(pos.x - 20, pos.y - 15);
    ctx.lineTo(pos.x - 15, pos.y + 10);
    ctx.lineTo(pos.x + 15, pos.y + 10);
    ctx.lineTo(pos.x + 20, pos.y - 15);
    ctx.closePath();
    ctx.fill();
    
    // 机翼 - 厚重型
    ctx.fillStyle = '#9900dd';
    // 左机翼
    ctx.beginPath();
    ctx.moveTo(pos.x - 20, pos.y - 5);
    ctx.lineTo(pos.x - 35, pos.y + 15);
    ctx.lineTo(pos.x - 30, pos.y + 25);
    ctx.lineTo(pos.x - 15, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 右机翼
    ctx.beginPath();
    ctx.moveTo(pos.x + 20, pos.y - 5);
    ctx.lineTo(pos.x + 35, pos.y + 15);
    ctx.lineTo(pos.x + 30, pos.y + 25);
    ctx.lineTo(pos.x + 15, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 驾驶舱 - 装甲保护
    ctx.fillStyle = '#00ffff';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y - 15, 8, 0, Math.PI * 2);
    ctx.fill();
    
    // 装甲边框
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(pos.x - 25, pos.y + 20);
    ctx.lineTo(pos.x - 20, pos.y + 35);
    ctx.lineTo(pos.x + 20, pos.y + 35);
    ctx.lineTo(pos.x + 25, pos.y + 20);
    ctx.closePath();
    ctx.stroke();
    
    // 引擎火焰 - 红色强火焰
    const currentTime = Date.now();
    const flameIntensity = 0.6 + Math.sin(currentTime * 0.015 + pos.x) * 0.4;
    
    // 左引擎火焰
    ctx.fillStyle = '#ff0000';
    ctx.beginPath();
    ctx.moveTo(pos.x - 12, pos.y + 35);
    ctx.lineTo(pos.x - 15, pos.y + 35 + 15 * flameIntensity);
    ctx.lineTo(pos.x - 9, pos.y + 35 + 15 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右引擎火焰
    ctx.beginPath();
    ctx.moveTo(pos.x + 12, pos.y + 35);
    ctx.lineTo(pos.x + 15, pos.y + 35 + 15 * flameIntensity);
    ctx.lineTo(pos.x + 9, pos.y + 35 + 15 * flameIntensity);
    ctx.closePath();
    ctx.fill();
}

// 绘制追踪敌机
function drawSeekerEnemy(ctx, pos) {
    // 追踪敌机 - 蓝色箭头型
    ctx.fillStyle = '#0088ff';
    
    // 机身主体 - 箭头形状
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 30);
    ctx.lineTo(pos.x - 18, pos.y + 25);
    ctx.lineTo(pos.x - 8, pos.y + 20);
    ctx.lineTo(pos.x - 8, pos.y + 30);
    ctx.lineTo(pos.x + 8, pos.y + 30);
    ctx.lineTo(pos.x + 8, pos.y + 20);
    ctx.lineTo(pos.x + 18, pos.y + 25);
    ctx.closePath();
    ctx.fill();
    
    // 机翼 - 箭头型
    ctx.fillStyle = '#0066cc';
    // 左机翼
    ctx.beginPath();
    ctx.moveTo(pos.x - 10, pos.y - 10);
    ctx.lineTo(pos.x - 30, pos.y);
    ctx.lineTo(pos.x - 25, pos.y + 10);
    ctx.lineTo(pos.x - 5, pos.y - 5);
    ctx.closePath();
    ctx.fill();
    
    // 右机翼
    ctx.beginPath();
    ctx.moveTo(pos.x + 10, pos.y - 10);
    ctx.lineTo(pos.x + 30, pos.y);
    ctx.lineTo(pos.x + 25, pos.y + 10);
    ctx.lineTo(pos.x + 5, pos.y - 5);
    ctx.closePath();
    ctx.fill();
    
    // 追踪传感器
    ctx.fillStyle = '#ff0000';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y - 20, 5, 0, Math.PI * 2);
    ctx.fill();
    
    // 引擎火焰 - 蓝色火焰
    const currentTime = Date.now();
    const flameIntensity = 0.5 + Math.sin(currentTime * 0.02 + pos.x) * 0.5;
    
    // 主引擎火焰
    ctx.fillStyle = '#00ccff';
    ctx.beginPath();
    ctx.moveTo(pos.x - 8, pos.y + 30);
    ctx.lineTo(pos.x - 10, pos.y + 30 + 18 * flameIntensity);
    ctx.lineTo(pos.x + 10, pos.y + 30 + 18 * flameIntensity);
    ctx.lineTo(pos.x + 8, pos.y + 30);
    ctx.closePath();
    ctx.fill();
}

// 绘制散射敌机
function drawSpreaderEnemy(ctx, pos) {
    // 散射敌机 - 绿色多炮管型
    ctx.fillStyle = '#00cc66';
    
    // 机身主体
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 25);
    ctx.lineTo(pos.x - 20, pos.y + 20);
    ctx.lineTo(pos.x - 15, pos.y + 30);
    ctx.lineTo(pos.x + 15, pos.y + 30);
    ctx.lineTo(pos.x + 20, pos.y + 20);
    ctx.closePath();
    ctx.fill();
    
    // 炮管
    ctx.fillStyle = '#009944';
    // 左炮管
    ctx.fillRect(pos.x - 18, pos.y - 10, 6, 15);
    ctx.fillRect(pos.x - 18, pos.y + 5, 6, 15);
    
    // 右炮管
    ctx.fillRect(pos.x + 12, pos.y - 10, 6, 15);
    ctx.fillRect(pos.x + 12, pos.y + 5, 6, 15);
    
    // 机翼
    ctx.fillStyle = '#00bb55';
    // 左机翼
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y - 5);
    ctx.lineTo(pos.x - 35, pos.y + 10);
    ctx.lineTo(pos.x - 30, pos.y + 20);
    ctx.lineTo(pos.x - 10, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 右机翼
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y - 5);
    ctx.lineTo(pos.x + 35, pos.y + 10);
    ctx.lineTo(pos.x + 30, pos.y + 20);
    ctx.lineTo(pos.x + 10, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 炮口
    ctx.fillStyle = '#ffaa00';
    ctx.beginPath();
    ctx.arc(pos.x - 21, pos.y - 3, 3, 0, Math.PI * 2);
    ctx.arc(pos.x - 21, pos.y + 12, 3, 0, Math.PI * 2);
    ctx.arc(pos.x + 21, pos.y - 3, 3, 0, Math.PI * 2);
    ctx.arc(pos.x + 21, pos.y + 12, 3, 0, Math.PI * 2);
    ctx.fill();
    
    // 引擎火焰
    const currentTime = Date.now();
    const flameIntensity = 0.4 + Math.sin(currentTime * 0.018 + pos.x) * 0.4;
    
    // 左引擎火焰
    ctx.fillStyle = '#00ff88';
    ctx.beginPath();
    ctx.moveTo(pos.x - 12, pos.y + 30);
    ctx.lineTo(pos.x - 15, pos.y + 30 + 12 * flameIntensity);
    ctx.lineTo(pos.x - 9, pos.y + 30 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右引擎火焰
    ctx.beginPath();
    ctx.moveTo(pos.x + 12, pos.y + 30);
    ctx.lineTo(pos.x + 15, pos.y + 30 + 12 * flameIntensity);
    ctx.lineTo(pos.x + 9, pos.y + 30 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
}

// 绘制分裂敌机
function drawSplitterEnemy(ctx, pos) {
    // 分裂敌机 - 黄色双体型
    ctx.fillStyle = '#ffdd00';
    
    // 主机身
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y - 25);
    ctx.lineTo(pos.x - 22, pos.y + 18);
    ctx.lineTo(pos.x - 15, pos.y + 28);
    ctx.lineTo(pos.x + 15, pos.y + 28);
    ctx.lineTo(pos.x + 22, pos.y + 18);
    ctx.closePath();
    ctx.fill();
    
    // 分裂部分
    ctx.fillStyle = '#ffcc00';
    // 左分裂体
    ctx.beginPath();
    ctx.moveTo(pos.x - 15, pos.y - 15);
    ctx.lineTo(pos.x - 30, pos.y + 5);
    ctx.lineTo(pos.x - 25, pos.y + 15);
    ctx.lineTo(pos.x - 10, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 右分裂体
    ctx.beginPath();
    ctx.moveTo(pos.x + 15, pos.y - 15);
    ctx.lineTo(pos.x + 30, pos.y + 5);
    ctx.lineTo(pos.x + 25, pos.y + 15);
    ctx.lineTo(pos.x + 10, pos.y + 5);
    ctx.closePath();
    ctx.fill();
    
    // 连接部分
    ctx.fillStyle = '#ffaa00';
    ctx.fillRect(pos.x - 8, pos.y + 5, 16, 10);
    
    // 驾驶舱
    ctx.fillStyle = '#00ffff';
    ctx.beginPath();
    ctx.arc(pos.x - 15, pos.y - 5, 5, 0, Math.PI * 2);
    ctx.arc(pos.x + 15, pos.y - 5, 5, 0, Math.PI * 2);
    ctx.fill();
    
    // 引擎火焰 - 黄色火焰
    const currentTime = Date.now();
    const flameIntensity = 0.5 + Math.sin(currentTime * 0.02 + pos.x) * 0.5;
    
    // 左引擎火焰
    ctx.fillStyle = '#ffaa00';
    ctx.beginPath();
    ctx.moveTo(pos.x - 10, pos.y + 28);
    ctx.lineTo(pos.x - 12, pos.y + 28 + 12 * flameIntensity);
    ctx.lineTo(pos.x - 8, pos.y + 28 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右引擎火焰
    ctx.beginPath();
    ctx.moveTo(pos.x + 10, pos.y + 28);
    ctx.lineTo(pos.x + 12, pos.y + 28 + 12 * flameIntensity);
    ctx.lineTo(pos.x + 8, pos.y + 28 + 12 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 分裂体引擎火焰
    ctx.fillStyle = '#ff8800';
    // 左分裂体火焰
    ctx.beginPath();
    ctx.moveTo(pos.x - 20, pos.y + 15);
    ctx.lineTo(pos.x - 22, pos.y + 15 + 8 * flameIntensity);
    ctx.lineTo(pos.x - 18, pos.y + 15 + 8 * flameIntensity);
    ctx.closePath();
    ctx.fill();
    
    // 右分裂体火焰
    ctx.beginPath();
    ctx.moveTo(pos.x + 20, pos.y + 15);
    ctx.lineTo(pos.x + 22, pos.y + 15 + 8 * flameIntensity);
    ctx.lineTo(pos.x + 18, pos.y + 15 + 8 * flameIntensity);
    ctx.closePath();
    ctx.fill();
}

// 绘制子弹
function drawBullets() {
    const ctx = gameState.ctx;
    
    entities.bullets.forEach(bullet => {
        ctx.save();
        
        // 根据子弹类型绘制不同效果
        switch (bullet.type) {
            case 'laser':
                drawLaserBullet(ctx, bullet);
                break;
            case 'homing':
                drawHomingBullet(ctx, bullet);
                break;
            case 'spread':
                drawSpreadBullet(ctx, bullet);
                break;
            case 'burst':
                drawBurstBullet(ctx, bullet);
                break;
            default:
                drawNormalBullet(ctx, bullet);
        }
        
        ctx.restore();
    });
}

// 绘制普通子弹
function drawNormalBullet(ctx, bullet) {
    const pos = bullet.position;
    
    // 多层轨迹效果
    for (let i = 0; i < 3; i++) {
        const alpha = 0.3 - i * 0.05;
        const width = 2 + i * 1;
        const offset = i * 1.5;
        
        ctx.strokeStyle = bullet.color;
        ctx.lineWidth = width;
        ctx.globalAlpha = alpha;
        
        // 计算子弹上一位置
        const prevX = pos.x - bullet.velocity.x * (3 + offset);
        const prevY = pos.y - bullet.velocity.y * (3 + offset);
        
        ctx.beginPath();
        ctx.moveTo(prevX, prevY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }
    
    // 子弹发光效果 - 增强版
    ctx.shadowBlur = 20;
    ctx.shadowColor = bullet.color;
    
    // 子弹外圈光晕
    const gradient = ctx.createRadialGradient(pos.x, pos.y, 0, pos.x, pos.y, bullet.radius * 2);
    gradient.addColorStop(0, bullet.color);
    gradient.addColorStop(0.7, bullet.color);
    gradient.addColorStop(1, 'transparent');
    
    ctx.globalAlpha = 0.6;
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius * 2, 0, Math.PI * 2);
    ctx.fill();
    
    // 子弹核心
    ctx.globalAlpha = 1;
    ctx.fillStyle = bullet.color;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius, 0, Math.PI * 2);
    ctx.fill();
    
    // 子弹内芯高光
    const highlightSize = bullet.radius * 0.6;
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(pos.x - bullet.radius * 0.3, pos.y - bullet.radius * 0.3, highlightSize, 0, Math.PI * 2);
    ctx.fill();
}

// 绘制激光子弹
function drawLaserBullet(ctx, bullet) {
    const pos = bullet.position;
    
    // 激光轨迹 - 更粗更亮
    ctx.strokeStyle = bullet.color;
    ctx.lineWidth = 6;
    ctx.globalAlpha = 0.8;
    
    // 计算激光起始位置（屏幕顶部或底部）
    const startX = pos.x;
    const startY = bullet.velocity.y < 0 ? -10 : gameState.canvas.height + 10;
    
    // 绘制激光主体
    ctx.beginPath();
    ctx.moveTo(startX, startY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    
    // 激光内部发光
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(startX, startY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    
    // 激光粒子效果
    ctx.fillStyle = bullet.color;
    ctx.globalAlpha = 0.6;
    for (let i = 0; i < 5; i++) {
        const particleX = pos.x + (Math.random() - 0.5) * 10;
        const particleY = pos.y + (Math.random() - 0.5) * 10;
        ctx.beginPath();
        ctx.arc(particleX, particleY, Math.random() * 2 + 1, 0, Math.PI * 2);
        ctx.fill();
    }
}

// 绘制追踪子弹
function drawHomingBullet(ctx, bullet) {
    const pos = bullet.position;
    
    // 螺旋轨迹效果
    ctx.strokeStyle = bullet.color;
    ctx.lineWidth = 1;
    ctx.globalAlpha = 0.4;
    
    // 绘制螺旋轨迹
    ctx.beginPath();
    for (let i = 0; i < Math.PI * 2; i += 0.5) {
        const radius = 10;
        const x = pos.x - bullet.velocity.x * 3 + Math.cos(i) * radius;
        const y = pos.y - bullet.velocity.y * 3 + Math.sin(i) * radius;
        if (i === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    }
    ctx.stroke();
    
    // 子弹发光效果
    ctx.shadowBlur = 25;
    ctx.shadowColor = '#ff0000';
    
    // 子弹核心
    ctx.globalAlpha = 1;
    ctx.fillStyle = bullet.color;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius, 0, Math.PI * 2);
    ctx.fill();
    
    // 追踪指示器
    ctx.strokeStyle = '#ff0000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius * 2, 0, Math.PI * 2);
    ctx.stroke();
    
    // 子弹内芯
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius * 0.5, 0, Math.PI * 2);
    ctx.fill();
}

// 绘制散射子弹
function drawSpreadBullet(ctx, bullet) {
    const pos = bullet.position;
    
    // 扇形轨迹效果
    ctx.strokeStyle = bullet.color;
    ctx.lineWidth = 1;
    ctx.globalAlpha = 0.3;
    
    // 绘制扇形轨迹
    const angle = Math.atan2(bullet.velocity.y, bullet.velocity.x);
    for (let i = -0.5; i <= 0.5; i += 0.25) {
        const newAngle = angle + i;
        const prevX = pos.x - Math.cos(newAngle) * 20;
        const prevY = pos.y - Math.sin(newAngle) * 20;
        
        ctx.beginPath();
        ctx.moveTo(prevX, prevY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }
    
    // 子弹发光效果
    ctx.shadowBlur = 15;
    ctx.shadowColor = bullet.color;
    
    // 子弹核心
    ctx.globalAlpha = 1;
    ctx.fillStyle = bullet.color;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius, 0, Math.PI * 2);
    ctx.fill();
    
    // 散射标记
    ctx.strokeStyle = '#ffff00';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pos.x - bullet.radius * 1.5, pos.y);
    ctx.lineTo(pos.x + bullet.radius * 1.5, pos.y);
    ctx.moveTo(pos.x, pos.y - bullet.radius * 1.5);
    ctx.lineTo(pos.x, pos.y + bullet.radius * 1.5);
    ctx.stroke();
}

// 绘制爆发子弹
function drawBurstBullet(ctx, bullet) {
    const pos = bullet.position;
    
    // 爆发子弹轨迹 - 星形轨迹
    ctx.strokeStyle = bullet.color;
    ctx.lineWidth = 1;
    ctx.globalAlpha = 0.3;
    
    // 绘制星形轨迹
    for (let i = 0; i < 6; i++) {
        const angle = (Math.PI * 2 / 6) * i;
        const prevX = pos.x - Math.cos(angle) * 15;
        const prevY = pos.y - Math.sin(angle) * 15;
        
        ctx.beginPath();
        ctx.moveTo(prevX, prevY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }
    
    // 子弹发光效果 - 多色光晕
    ctx.shadowBlur = 25;
    ctx.shadowColor = '#ffaa00';
    
    // 子弹外圈光晕
    const gradient = ctx.createRadialGradient(pos.x, pos.y, 0, pos.x, pos.y, bullet.radius * 2.5);
    gradient.addColorStop(0, '#ffffff');
    gradient.addColorStop(0.3, '#ffaa00');
    gradient.addColorStop(0.7, '#ff6600');
    gradient.addColorStop(1, 'transparent');
    
    ctx.globalAlpha = 0.7;
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius * 2.5, 0, Math.PI * 2);
    ctx.fill();
    
    // 子弹核心
    ctx.globalAlpha = 1;
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius, 0, Math.PI * 2);
    ctx.fill();
    
    // 爆发核心
    ctx.fillStyle = '#ff6600';
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, bullet.radius * 0.6, 0, Math.PI * 2);
    ctx.fill();
}

// 绘制道具
function drawItems() {
    const ctx = gameState.ctx;
    
    entities.items.forEach(item => {
        ctx.save();
        
        // 根据道具类型绘制不同形状和颜色
        switch (item.type) {
            case GAME_CONSTANTS.ITEM.TYPES.POWER_UP:
                ctx.fillStyle = '#ffff00';
                // 绘制星星
                drawStar(ctx, item.position.x, item.position.y, 5, 10, 5);
                break;
            case GAME_CONSTANTS.ITEM.TYPES.HEALTH:
                ctx.fillStyle = '#00ff00';
                // 绘制十字
                drawCross(ctx, item.position.x, item.position.y, 10);
                break;
            case GAME_CONSTANTS.ITEM.TYPES.ENERGY:
                ctx.fillStyle = '#0066ff';
                // 绘制闪电
                drawLightning(ctx, item.position.x, item.position.y, 10);
                break;
            case GAME_CONSTANTS.ITEM.TYPES.SHIELD:
                ctx.fillStyle = '#00ffff';
                // 绘制护盾
                ctx.beginPath();
                ctx.arc(item.position.x, item.position.y, 10, 0, Math.PI * 2);
                ctx.fill();
                break;
            case GAME_CONSTANTS.ITEM.TYPES.RAPID_FIRE:
                ctx.fillStyle = '#ff6600';
                // 绘制快速射击道具（双箭头）
                ctx.beginPath();
                ctx.moveTo(item.position.x - 10, item.position.y);
                ctx.lineTo(item.position.x + 5, item.position.y - 8);
                ctx.lineTo(item.position.x + 5, item.position.y + 8);
                ctx.closePath();
                ctx.fill();
                ctx.beginPath();
                ctx.moveTo(item.position.x + 10, item.position.y);
                ctx.lineTo(item.position.x - 5, item.position.y - 8);
                ctx.lineTo(item.position.x - 5, item.position.y + 8);
                ctx.closePath();
                ctx.fill();
                break;
            case GAME_CONSTANTS.ITEM.TYPES.INVINCIBILITY:
                ctx.fillStyle = '#ff00ff';
                // 绘制无敌道具（星形带光环）
                drawStar(ctx, item.position.x, item.position.y, 5, 10, 5);
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(item.position.x, item.position.y, 15, 0, Math.PI * 2);
                ctx.stroke();
                break;
        }
        
        ctx.restore();
    });
}

// 绘制爆炸效果
function drawExplosions() {
    const ctx = gameState.ctx;
    
    entities.explosions.forEach(explosion => {
        const lifePercent = explosion.life / explosion.maxLife;
        const alpha = lifePercent;
        
        ctx.save();
        
        // 绘制冲击波效果
        if (explosion.shockwave) {
            const shockwave = explosion.shockwave;
            ctx.globalAlpha = shockwave.opacity * 0.6;
            
            // 确保半径参数大于或等于0，避免Canvas错误
            const innerRadius = Math.max(0, shockwave.radius - 5);
            
            // 创建冲击波渐变
            const shockwaveGradient = ctx.createRadialGradient(
                explosion.position.x, explosion.position.y, innerRadius,
                explosion.position.x, explosion.position.y, shockwave.radius
            );
            shockwaveGradient.addColorStop(0, 'transparent');
            shockwaveGradient.addColorStop(0.7, '#ffffff');
            shockwaveGradient.addColorStop(1, 'transparent');
            
            ctx.strokeStyle = shockwaveGradient;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(explosion.position.x, explosion.position.y, shockwave.radius, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        // 爆炸外圈光晕
        // 确保所有半径参数大于或等于0，避免Canvas错误
        const outerRadius = Math.max(0, explosion.maxSize * 2);
        const gradient = ctx.createRadialGradient(
            explosion.position.x, explosion.position.y, 0,
            explosion.position.x, explosion.position.y, outerRadius
        );
        gradient.addColorStop(0, explosion.color);
        gradient.addColorStop(0.5, explosion.color);
        gradient.addColorStop(1, 'transparent');
        
        ctx.globalAlpha = alpha * 0.5;
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(explosion.position.x, explosion.position.y, explosion.maxSize * 2, 0, Math.PI * 2);
        ctx.fill();
        
        // 绘制爆炸粒子
        explosion.particles.forEach(particle => {
            const particleLifePercent = particle.life / particle.maxLife;
            const particleAlpha = particleLifePercent;
            const particleSize = particle.size * particleLifePercent;
            
            ctx.globalAlpha = particleAlpha;
            ctx.fillStyle = particle.color;
            
            // 移除阴影效果以优化性能
            // ctx.shadowBlur = particleSize * 2;
            // ctx.shadowColor = particle.color;
            
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particleSize, 0, Math.PI * 2);
            ctx.fill();
            
            // ctx.shadowBlur = 0;
        });
        
        ctx.restore();
    });
}

// 绘制星星（道具用）
function drawStar(ctx, cx, cy, spikes, outerRadius, innerRadius) {
    let rot = Math.PI / 2 * 3;
    let x = cx;
    let y = cy;
    const step = Math.PI / spikes;
    
    ctx.beginPath();
    ctx.moveTo(cx, cy - outerRadius);
    
    for (let i = 0; i < spikes; i++) {
        x = cx + Math.cos(rot) * outerRadius;
        y = cy + Math.sin(rot) * outerRadius;
        ctx.lineTo(x, y);
        rot += step;
        
        x = cx + Math.cos(rot) * innerRadius;
        y = cy + Math.sin(rot) * innerRadius;
        ctx.lineTo(x, y);
        rot += step;
    }
    
    ctx.lineTo(cx, cy - outerRadius);
    ctx.closePath();
    ctx.fill();
}

// 绘制十字（血包用）
function drawCross(ctx, cx, cy, size) {
    ctx.fillRect(cx - size/2, cy - size/6, size, size/3);
    ctx.fillRect(cx - size/6, cy - size/2, size/3, size);
}

// 绘制闪电（能量道具用）
function drawLightning(ctx, cx, cy, size) {
    ctx.beginPath();
    ctx.moveTo(cx, cy - size);
    ctx.lineTo(cx - size/2, cy);
    ctx.lineTo(cx, cy + size/2);
    ctx.lineTo(cx + size/2, cy);
    ctx.lineTo(cx, cy - size);
    ctx.closePath();
    ctx.fill();
}

// 更新UI
function updateUI() {
    // 更新分数
    if (uiElements.score) {
        uiElements.score.textContent = `分数: ${gameState.score}`;
    }
    
    // 更新关卡
    if (uiElements.level) {
        uiElements.level.textContent = `关卡: ${gameState.level}`;
    }
    
    // 更新波次
    if (uiElements.wave) {
        uiElements.wave.textContent = `波次: ${gameState.wave}`;
    }
    
    // 更新生命值
    if (uiElements.lives) {
        uiElements.lives.textContent = `生命: ${player.lives}`;
    }
    
    // 更新HP条
    if (uiElements.hpFill) {
        const hpPercent = (player.hp / player.maxHp) * 100;
        uiElements.hpFill.style.width = `${hpPercent}%`;
    }
    
    // 更新能量条
    if (uiElements.energyFill) {
        const energyPercent = (player.energy / player.maxEnergy) * 100;
        uiElements.energyFill.style.width = `${energyPercent}%`;
    }
    
    // 更新技能按钮状态
    if (uiElements.skillBtn) {
        uiElements.skillBtn.disabled = player.energy < 100;
    }
}

// 调整画布大小
function resizeCanvas() {
    gameState.width = window.innerWidth;
    gameState.height = window.innerHeight;
    
    gameState.canvas.width = gameState.width;
    gameState.canvas.height = gameState.height;
    
    // 如果玩家已经初始化，调整位置
    if (player.position) {
        player.position.x = Math.min(player.position.x, gameState.width);
        player.position.y = Math.min(player.position.y, gameState.height);
    }
}

// 添加事件监听
function addEventListeners() {
    // 窗口大小调整
    window.addEventListener('resize', resizeCanvas);
    
    // 键盘事件
    document.addEventListener('keydown', (e) => {
        input.keys[e.key] = true;
        
        // 暂停游戏
        if (e.key === 'Escape') {
            togglePause();
        }
        
        // 使用技能
        if (e.key === 'k' || e.key === 'K') {
            useSkill();
        }
    });
    
    document.addEventListener('keyup', (e) => {
        input.keys[e.key] = false;
    });
    
    // 鼠标事件
    gameState.canvas.addEventListener('mousemove', (e) => {
        const rect = gameState.canvas.getBoundingClientRect();
        input.mouse.x = e.clientX - rect.left;
        input.mouse.y = e.clientY - rect.top;
    });
    
    gameState.canvas.addEventListener('mousedown', (e) => {
        input.mouse.down = true;
    });
    
    gameState.canvas.addEventListener('mouseup', (e) => {
        input.mouse.down = false;
    });
    
    // 触摸事件
    gameState.canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const rect = gameState.canvas.getBoundingClientRect();
        const touch = e.touches[0];
        input.touch.x = touch.clientX - rect.left;
        input.touch.y = touch.clientY - rect.top;
        input.touch.active = true;
    });
    
    gameState.canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        const rect = gameState.canvas.getBoundingClientRect();
        const touch = e.touches[0];
        input.touch.x = touch.clientX - rect.left;
        input.touch.y = touch.clientY - rect.top;
    });
    
    gameState.canvas.addEventListener('touchend', (e) => {
        e.preventDefault();
        input.touch.active = false;
    });
    
    // UI事件
    // 开始按钮
    const startBtn = document.getElementById('start-btn');
    if (startBtn) {
        startBtn.addEventListener('click', () => {
            startGame();
        });
    }
    
    // 难度选择
    if (uiElements.difficultyBtns && uiElements.difficultyBtns.length > 0) {
        uiElements.difficultyBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // 移除所有选中状态
                uiElements.difficultyBtns.forEach(b => b.classList.remove('selected'));
                // 添加当前选中状态
                btn.classList.add('selected');
                // 更新难度
                gameState.difficulty = btn.dataset.difficulty;
            });
        });
    }
    
    // 重新开始按钮
    const restartBtn = document.getElementById('restart-btn');
    if (restartBtn) {
        restartBtn.addEventListener('click', () => {
            startGame();
        });
    }
    
    // 返回菜单按钮
    const menuBtn = document.getElementById('menu-btn');
    if (menuBtn) {
        menuBtn.addEventListener('click', () => {
            if (uiElements.startScreen) {
                uiElements.startScreen.style.display = 'flex';
            }
            if (uiElements.gameOverScreen) {
                uiElements.gameOverScreen.style.display = 'none';
            }
        });
    }
    
    // 继续游戏按钮
    const resumeBtn = document.getElementById('resume-btn');
    if (resumeBtn) {
        resumeBtn.addEventListener('click', () => {
            gameState.gamePaused = false;
            if (uiElements.pauseMenu) {
                uiElements.pauseMenu.style.display = 'none';
            }
            gameLoop();
        });
    }
    
    // 重新开始游戏按钮
    const restartGameBtn = document.getElementById('restart-game-btn');
    if (restartGameBtn) {
        restartGameBtn.addEventListener('click', () => {
            startGame();
        });
    }
    
    // 退出游戏按钮
    const quitBtn = document.getElementById('quit-btn');
    if (quitBtn) {
        quitBtn.addEventListener('click', () => {
            gameState.gameRunning = false;
            if (uiElements.pauseMenu) {
                uiElements.pauseMenu.style.display = 'none';
            }
            if (uiElements.startScreen) {
                uiElements.startScreen.style.display = 'flex';
            }
        });
    }
    
    // 技能按钮
    if (uiElements.skillBtn) {
        uiElements.skillBtn.addEventListener('click', useSkill);
    }
}

// 切换暂停状态
function togglePause() {
    if (!gameState.gameRunning) return;
    
    gameState.gamePaused = !gameState.gamePaused;
    
    if (gameState.gamePaused) {
        if (uiElements.pauseMenu) {
            uiElements.pauseMenu.style.display = 'block';
        }
    } else {
        if (uiElements.pauseMenu) {
            uiElements.pauseMenu.style.display = 'none';
        }
        gameLoop();
    }
}

// 使用技能
function useSkill() {
    if (player.energy >= 100) {
        // 清除屏幕上所有敌人子弹
        for (let i = entities.bullets.length - 1; i >= 0; i--) {
            if (entities.bullets[i].owner === 'enemy') {
                entities.bullets[i].active = false;
                entities.bullets.splice(i, 1);
            }
        }
        
        // 消耗能量
        player.energy = 0;
        
        // 更新UI
        updateUI();
        
        // 创建爆炸效果
        createExplosion(player.position, 100, '#ffff00');
    }
}

// 绘制星星辅助函数
function drawStar(ctx, cx, cy, spikes, outerRadius, innerRadius) {
    let rot = Math.PI / 2 * 3;
    let x = cx;
    let y = cy;
    const step = Math.PI / spikes;
    
    ctx.beginPath();
    ctx.moveTo(cx, cy - outerRadius);
    
    for (let i = 0; i < spikes; i++) {
        x = cx + Math.cos(rot) * outerRadius;
        y = cy + Math.sin(rot) * outerRadius;
        ctx.lineTo(x, y);
        rot += step;
        
        x = cx + Math.cos(rot) * innerRadius;
        y = cy + Math.sin(rot) * innerRadius;
        ctx.lineTo(x, y);
        rot += step;
    }
    
    ctx.lineTo(cx, cy - outerRadius);
    ctx.closePath();
    ctx.fill();
}

// 绘制十字辅助函数
function drawCross(ctx, cx, cy, size) {
    ctx.fillRect(cx - size/2, cy - size/6, size, size/3);
    ctx.fillRect(cx - size/6, cy - size/2, size/3, size);
}

// 绘制闪电辅助函数
function drawLightning(ctx, cx, cy, size) {
    ctx.beginPath();
    ctx.moveTo(cx, cy - size);
    ctx.lineTo(cx - size/2, cy);
    ctx.lineTo(cx, cy + size/2);
    ctx.lineTo(cx + size/2, cy);
    ctx.lineTo(cx, cy - size);
    ctx.closePath();
    ctx.fill();
}

// 初始化游戏
window.addEventListener('DOMContentLoaded', () => {
    // 调用完整的initGame函数进行初始化
    initGame();
    
    // 初始化UI
    updateUI();
    
    console.log('冠恩飞机站 - 游戏加载完成');
});
