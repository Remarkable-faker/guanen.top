/*******************************************************
 * Monopoly-like local game (fixed version)
 * - Saves: index.html, style.css must be in same folder
 * - Fixed issues:
 * 1. Added bail card to chance events
 * 2. Fixed dice roll alignment (center position)
 * 3. Fixed AI not continuing turns after rolling dice
 * 4. 【已修复】棋子不按规定路线行动 (coordToIndex 修正)
 * 5. 【已修复】骰子动画僵硬 (rollVisual 修正)
 *******************************************************/
(function(){
  /* ---------------- Config ---------------- */
  const START_MONEY = 20000;
  const PASS_START = 2000;
  const BIG_HOUSE_COST = 6000;
  const BASE_PRICE_MIN = 850;
  const BASE_BUILD_MIN = 550;
  const TRANSPORT_BASE_RENT = 700;
  const MOVE_STEP_MS = 160;
  const DICE_STRIP_ITEM_H = 32;
  // AI timing (accelerated; logic unchanged)
  const AI_ROLL_DELAY = 300;    // AI 掷骰等待
  const AI_THINK_DELAY = 180;   // AI 落地思考 / 决策等待
  const AI_END_DELAY = 180;     // AI 决策后结束回合等待

  // candidate city pool (40 names you provided)
  const CANDIDATES = [
    "纽约","旧金山","芝加哥","洛杉矶","伦敦","曼切斯特","上海","北京","香港","深圳",
    "杭州","斑马小镇","巴黎","东京","大阪","神奈川","琦玉","新加坡","名古屋","横滨",
    "札幌","阿尔卑斯山脉","悉尼","墨尔本","布里斯班","阿德莱德","黄金海岸","堪培拉","奥克兰","惠灵顿",
    "皇后镇","多伦多","温哥华","蒙特利尔","渥太华","米兰","罗马","威尼斯","巴塞罗那","首尔"
  ];
  // fixed positions
  const CORNERS = [0,10,20,30];
  const CHANCE   = [7,22];
  const TRANSPORT_POS = {5:'bus',15:'air',25:'metro',35:'ferry'}; // 5/15/25/35
  const SPECIALS = new Set([...CORNERS, ...CHANCE, ...Object.keys(TRANSPORT_POS).map(x=>Number(x))]);
  // color palette (10 groups)
  const COLOR_PALETTE = [
    "#8e6e53","#5dade2","#af7ac5","#f39c12","#e74c3c",
    "#f4d03f","#27ae60","#2e86c1","#d35400","#16a085"
  ];
  const TOKEN_COLORS = ["#e74c3c","#3498db","#2ecc71","#9b59b6","#e67e22","#1abc9c","#f1c40f","#34495e"];
  /* ---------------- State ---------------- */
  let tileState = []; // length 40: {type, name, owner, buildings, bigHouse, transportGroup, colorGroup, level, mortgaged}
  let players = [];   // {name,money,pos,color,isAI, bailCard}
  let current = 0;
  let started = false;
  let selectedTile = -1;
  // auction state
  let auction = {active:false, tile:-1, high:0, highName:null};
  /* ---------------- Utilities ---------------- */
  function $(id){ return document.getElementById(id); }
  function logMsg(msg){
    const el = $('log'); if(!el) return;
    const ts = new Date().toLocaleTimeString();
    el.innerHTML = `[${ts}] ${msg}<br>` + el.innerHTML;
  }
  function chatLeft(name,msg){
    const box = $('chat'); if(!box) return;
    const d = document.createElement('div'); d.className='chat-message chat-left'; d.innerHTML = `<b>${escapeHtml(name)}：</b>${escapeHtml(msg)}`; box.appendChild(d); box.scrollTop = box.scrollHeight;
  }
  function chatRight(msg){
    const box = $('chat'); if(!box) return;
    const d = document.createElement('div'); d.className='chat-message chat-right'; d.textContent = msg; box.appendChild(d); box.scrollTop = box.scrollHeight;
  }
  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
  function clamp(v,min,max){ return Math.max(min,Math.min(max,v)); }

  // 新增：破产检查函数
  function checkBankrupt(playerIndex){
    const p = players[playerIndex];
    if(!p || p.money >= 0) return false;
    // 破产处理：资产进入拍卖
    logMsg(`${p.name} 资金为负，宣布破产！所有资产进入拍卖`);
    for(let i=0;i<40;i++){
      const tile = tileState[i];
      if(tile.owner === p.name){
        tile.owner = null;
        tile.buildings = 0;
        tile.bigHouse = false;
        tile.mortgaged = false;
        // 延迟触发拍卖，避免同时触发多个弹窗
        setTimeout(()=>triggerAuction(i), 500 * i);
      }
    }
    // 移除破产玩家
    players.splice(playerIndex, 1);
    // 检查游戏胜利
    if(players.length === 1){
      alert(`游戏结束！${players[0].name} 获得胜利！`);
      location.reload();
    }
    // 更新当前回合（避免索引异常）
    current = current % players.length;
    updateTurn();
    return true;
  }
  /* ---------------- RNG / Shuffle ---------------- */
  function shuffleArray(arr, seed=null){
    const a = arr.slice();
    let rnd = Math.random;
    if(seed != null){
      let x = seed >>> 0;
      rnd = ()=>{ x ^= x << 13; x ^= x >>> 17; x ^= x << 5; return (x >>> 0)/4294967296; };
    }
    for(let i=a.length-1;i>0;i--){
      const j = Math.floor(rnd()*(i+1));
      [a[i],a[j]] = [a[j],a[i]];
    }
    return a;
  }
  /* ---------------- Tile helpers ---------------- */
  function isCityIndex(i){ return !SPECIALS.has(i) && !CORNERS.includes(i); }
  function getTilePrice(idx){
    const lvl = tileState[idx].level || 0; // 0..3
    return Math.round(BASE_PRICE_MIN * Math.pow(2, lvl));
  }
  function getBuildCost(idx){
    const lvl = tileState[idx].level || 0;
    return Math.round(BASE_BUILD_MIN * Math.pow(2, lvl));
  }
  function getTransportRent(group, owner){
    if(!owner) return 0;
    let count = 0;
    for(let i=0;i<40;i++){
      if(tileState[i].type === 'transport' && tileState[i].transportGroup === group && tileState[i].owner === owner) count++;
    }
    if(count <=0) return 0;
    return TRANSPORT_BASE_RENT * Math.pow(2, count-1);
  }
  function calcCityRent(idx){
    const price = getTilePrice(idx);
    const base = Math.floor(price * 0.5);
    const s = tileState[idx];
    let rent = s.bigHouse ? base * 3 : base * (1 + (s.buildings||0));
    // monopoly check
    if(s.colorGroup != null && s.owner){
      const cg = s.colorGroup;
      const members = tileState.reduce((acc,t,i)=>{ if(t.colorGroup === cg) acc.push(i); return acc; },[]);
      const ownerAll = members.length>0 && members.every(i=>tileState[i].owner === s.owner);
      if(ownerAll) rent = Math.floor(rent * 1.5);
    }
    return rent;
  }
  /* ---------------- Board Initialization ---------------- */
  function initTileState(randomize=true, seed=null){
    tileState = Array.from({length:40}, (_,i)=>({
      type: CORNERS.includes(i) ? 'corner' : (CHANCE.includes(i) ? 'chance' : (Object.keys(TRANSPORT_POS).map(x=>Number(x)).includes(i) ? 'transport' : 'city')),
      name: '',
      owner: null,
      buildings: 0,
      bigHouse: false,
      transportGroup: null,
      colorGroup: null,
      level: 0,
      mortgaged: false
    }));
    // set transport groups
    for(const k in TRANSPORT_POS){
      const idx = Number(k);
      tileState[idx].type = 'transport';
      tileState[idx].transportGroup = TRANSPORT_POS[k];
    }
    // assign special names
    tileState[0].name = '起点'; tileState[10].name = '监狱'; tileState[20].name = '停车'; tileState[30].name = '进监狱';
    CHANCE.forEach(i=> tileState[i].name = '机遇');
    // prepare city indexes (in board order)
    const cityIndexes = [];
    for(let i=0;i<40;i++){
      if(!CORNERS.includes(i) && !CHANCE.includes(i) && !Object.keys(TRANSPORT_POS).map(x=>Number(x)).includes(i)) cityIndexes.push(i);
    }
    // should be 30
    if(cityIndexes.length !== 30){
      console.warn("City indexes length not 30:", cityIndexes.length);
    }
    // choose 30 cities from candidates randomly
    let pool = CANDIDATES.slice();
    if(randomize) pool = shuffleArray(pool, seed);
    const chosen = pool.slice(0,30);
    // map chosen to cityIndexes in order
    for(let i=0;i<cityIndexes.length;i++){
      const idx = cityIndexes[i];
      tileState[idx].name = chosen[i];
    }
    // map transports names (uppercase or readable)
    for(const k in TRANSPORT_POS){
      const idx = Number(k);
      const label = TRANSPORT_POS[k];
      tileState[idx].name = label.charAt(0).toUpperCase() + label.slice(1);
    }
    // color groups: 10 groups * 3 cities = 30 cities
    for(let i=0;i<30;i++){
      const idx = cityIndexes[i];
      const g = Math.floor(i/3); // 0..9
      tileState[idx].colorGroup = g;
      // level distribution: 0..3 roughly as earlier
      let level = 0;
      if(g <= 2) level = 0;
      else if(g <= 4) level = 1;
      else if(g <= 7) level = 2;
      else level = 3;
      tileState[idx].level = level;
    }
  }
  /* ---------------- DOM Board Generation ---------------- */
  function generateBoardDOM(){
    const board = $('board');
    board.innerHTML = '';
    const N = 11;
    const cellCssSize = 'var(--cell)';
    for(let r=0;r<N;r++){
      for(let c=0;c<N;c++){
        // only outer ring
        if(!(r===0 || r===N-1 || c===0 || c===N-1)) continue;
        const idx = coordToIndex(r,c);
        if(idx === null) continue;
        const s = tileState[idx];
        const cell = document.createElement('div'); cell.className='cell'; cell.dataset.index = idx;
        if(s.type === 'corner') cell.classList.add('corner');
        cell.style.left = `calc(${c} * ${cellCssSize})`;
        cell.style.top  = `calc(${r} * ${cellCssSize})`;
        cell.style.width = cellCssSize; cell.style.height = cellCssSize;
        // color strip for city only
        if(s.type === 'city' && s.colorGroup != null){
          const color = COLOR_PALETTE[s.colorGroup % COLOR_PALETTE.length];
          cell.style.borderTop = `8px solid ${color}`;
        } else {
          cell.style.borderTop = `0px solid transparent`;
        }
        let titleHTML = '';
        if(s.type === 'corner'){
          if(idx === 0) titleHTML = `<div class="title">起点</div>`;
          else if(idx === 10) titleHTML = `<div class="title">监狱</div>`;
          else if(idx === 20) titleHTML = `<div class="title">停车</div>`;
          else if(idx === 30) titleHTML = `<div class="title">进监狱</div>`;
        } else if(s.type === 'transport'){
          titleHTML = `<div class="title">${s.name}</div><div style="font-size:11px;color:#1e88e5">交通站</div>`;
        } else if(s.type === 'chance'){
          titleHTML = `<div class="title">机遇</div>`;
        } else {
          titleHTML = `<div class="title">${s.name}</div>`;
        }
        const priceHtml = s.type === 'corner' ? `<div class="price" style="visibility:hidden">--</div>` : `<div class="price">¥${getTilePrice(idx)}</div>`;
        cell.innerHTML = `<div class="owner-badge" id="owner-${idx}"></div>
          ${titleHTML}
          ${priceHtml}
          <div class="token-box" id="token-box-${idx}"></div>
        `;
        cell.addEventListener('click', ()=> selectTile(idx));
        board.appendChild(cell);
        updateTileVisual(idx);
      }
    }
  }
  
  // =======================================================
  // 【修复 1/2】：修正 coordToIndex 函数，确保棋子沿正确路线移动
  // =======================================================
  function coordToIndex(r,c){
    const N = 11;
    // 1. 底部行 (Row 10): 从左往右 0->10
    if(r === N-1) return c; 
    
    // 2. 右侧列 (Col 10): 从下往上 11->19
    if(c === N-1 && r>0 && r<N-1) return 10 + (N - 1 - r);
    
    // 3. 顶部行 (Row 0): 从右往左 20->30
    if(r === 0) return 20 + (N - 1 - c);
    
    // 4. 左侧列 (Col 0): 从上往下 31->39
    if(c === 0 && r>0 && r<N-1) return 30 + r;

    return null;
  }
  // =======================================================
  
  function updateTileVisual(i){
    const ownerEl = $(`owner-${i}`);
    if(!ownerEl) return;
    const s = tileState[i];
    ownerEl.textContent = s.owner ? s.owner : '';
    ownerEl.style.display = s.owner ? 'block' : 'none';
    ownerEl.title = s.mortgaged ? '已抵押' : '';
    // house icons
    const cell = ownerEl.closest('.cell');
    if(!cell) return;
    cell.querySelectorAll('.house-small,.house-big').forEach(n=>n.remove());
    if(s.buildings > 0){
      for(let k=0;k<s.buildings;k++){
        const h = document.createElement('div'); h.className='house-small'; cell.appendChild(h);
      }
    }
    if(s.bigHouse){
      const hb = document.createElement('div'); hb.className='house-big'; cell.appendChild(hb);
    }
  }
  /* ---------------- Tokens & Players UI ---------------- */
  function renderTokens(){
    for(let i=0;i<40;i++){
      const box = $(`token-box-${i}`);
      if(box) box.innerHTML = '';
    }
    players.forEach((p,idx)=>{
      const box = $(`token-box-${p.pos}`);
      if(!box) return;
      const t = document.createElement('div'); t.className='token'; t.style.background = p.color; t.textContent = idx+1;
      box.appendChild(t);
    });
  }
  function renderPlayersUI(){
    const el = $('players'); if(!el) return;
    el.innerHTML = '';
    players.forEach((p,idx)=>{
      const div = document.createElement('div'); div.className='player'; if(idx === current) div.classList.add('current-turn');
      div.innerHTML = `<div class="meta"><div class="avatar" style="background:${p.color}"></div><div><div style="font-weight:700">${p.name}</div><div style="font-size:12px;color:#666">¥${p.money} | 保释卡: ${p.bailCard || 0}张</div></div></div>`;
      el.appendChild(div);
    });
    renderTokens();
    refreshButtons();
  }
  function refreshButtons(){
    const buy = $('buyBtn'), build = $('buildBtn'), upgrade = $('upgradeBtn'), mort = $('mortBtn');
    if(selectedTile < 0){ if(buy) buy.disabled=true; if(build) build.disabled=true; if(upgrade) upgrade.disabled=true; if(mort) mort.disabled=true; return; }
    const s = tileState[selectedTile];
    const p = players[current];
    if(!p){ buy.disabled=true; build.disabled=true; upgrade.disabled=true; mort.disabled=true; return; }
    buy.disabled = !(s.type !== 'corner' && !s.owner && p.money >= getTilePrice(selectedTile) && !p.isAI);
    build.disabled = !(s.type === 'city' && s.owner === p.name && !s.bigHouse && s.buildings < 4 && p.money >= getBuildCost(selectedTile));
    upgrade.disabled = !(s.type === 'city' && s.owner === p.name && s.buildings === 4 && p.money >= BIG_HOUSE_COST);
    mort.disabled = !(s.owner === p.name && s.type !== 'corner');
  }
  /* ---------------- Dice strips (slot) ---------------- */
  function initDiceStrips(){
    const s1 = $('strip1'), s2 = $('strip2');
    if(!s1 || !s2) return;
    const arr = [];
    for(let i=0;i<60;i++) arr.push((i%6)+1);
    // 修复：确保每个骰子数字容器高度一致且居中
    s1.innerHTML = arr.map(x=>`<div style="height:${DICE_STRIP_ITEM_H}px; display:flex; align-items:center; justify-content:center">${x}</div>`).join('');
    s2.innerHTML = arr.map(x=>`<div style="height:${DICE_STRIP_ITEM_H}px; display:flex; align-items:center; justify-content:center">${x}</div>`).join('');
    // 强制设置视口高度，避免错位
    s1.parentElement.style.height = `${DICE_STRIP_ITEM_H}px`;
    s2.parentElement.style.height = `${DICE_STRIP_ITEM_H}px`;
  }
  
  // =======================================================
  // 【修复 2/2】：修正 rollVisual 函数，添加回弹、模糊和错开停顿时间
  // =======================================================
  function rollVisual(r1, r2, cb) {
    const s1 = $('strip1'), s2 = $('strip2'); 
    if(!s1||!s2){ if(cb) cb(); return; }
    
    const h = DICE_STRIP_ITEM_H; // 32px
    const centerOffset = h / 2; 

    // 1. 添加模糊类（配合 CSS）
    s1.classList.add('rolling');
    s2.classList.add('rolling');

    // 2. 重置位置
    s1.style.transition = 'none';
    s2.style.transition = 'none';
    s1.style.transform = `translateY(0px)`;
    s2.style.transform = `translateY(0px)`;
    
    // 强制重绘
    void s1.offsetHeight;
    void s2.offsetHeight;
    
    // 3. 设置动画：使用贝塞尔曲线模拟回弹，并错开两个骰子的停止时间
    // 骰子1：0.8秒，骰子2：1.0秒
    s1.style.transition = 'transform 0.8s cubic-bezier(0.15, 0.9, 0.3, 1.2)';
    s2.style.transition = 'transform 1.0s cubic-bezier(0.15, 0.9, 0.3, 1.2)';
    
    // 计算目标位置
    const target1 = (40 + (r1-1))*h + centerOffset;
    const target2 = (40 + (r2-1))*h + centerOffset;

    s1.style.transform = `translateY(-${target1}px)`;
    s2.style.transform = `translateY(-${target2}px)`;
    
    // 4. 动画结束处理
    setTimeout(()=>{
      s1.classList.remove('rolling'); // 移除模糊
    }, 800);

    setTimeout(()=>{
      s2.classList.remove('rolling');
      // 彻底停止后移除过渡，防止改变窗口大小时错位
      s1.style.transition = 'none'; 
      s2.style.transition = 'none';
      // 重新校准位置
      s1.style.transform = `translateY(-${(r1-1)*h + centerOffset}px)`;
      s2.style.transform = `translateY(-${(r2-1)*h + centerOffset}px)`;
      if(cb) cb();
    }, 1050); // 等待最慢的那个骰子停稳
  }
  // =======================================================

  /* ---------------- Movement ---------------- */
  // 修复：AI移动后触发决策并结束回合
  function smoothMove(playerIndex, steps, doneCb){
    const p = players[playerIndex];
    if(!p) { if(doneCb) doneCb(); return; }
    let moved = 0;
    function step(){
      p.pos = (p.pos + 1) % 40;
      // pass start
      if(p.pos === 0){
        p.money += PASS_START;
        logMsg(`${p.name} 经过起点，获得 ¥${PASS_START}`);
      }
      renderPlayersUI();
      moved++;
      if(moved < steps){
        setTimeout(step, MOVE_STEP_MS);
      } else {
        setTimeout(()=>{
          afterLanding(playerIndex); 
          // 关键：AI玩家移动后执行决策并结束回合
          if(p.isAI){
            setTimeout(()=>{
              aiAfterLanding(playerIndex); // AI买地、建房等决策
              // AI决策后自动结束回合
              setTimeout(()=>{
                current = (current + 1) % players.length;
                updateTurn();
                logMsg(`AI回合结束，轮到 ${players[current].name}`);
              }, AI_END_DELAY); // 原 800 -> AI_END_DELAY
            }, AI_THINK_DELAY); // 原 300 -> AI_THINK_DELAY
          }
          if(doneCb) doneCb();
        }, 220);
      }
    }
    step();
  }
  // 修复：机遇格添加保释卡事件
  function afterLanding(playerIndex){
    const p = players[playerIndex];
    const idx = p.pos;
    const s = tileState[idx];
    selectTile(idx);

    // NEW: 如果踩到“进监狱”(index 30)，直接进监狱（修复你提出的问题）
    if(idx === 30){
      sendToJail(p);
      return;
    }

    // transport
    if(s.type === 'transport'){
      if(s.owner && s.owner !== p.name && !s.mortgaged){
        const rent = getTransportRent(s.transportGroup, s.owner);
        p.money -= rent;
        const ownerP = players.find(x=>x.name === s.owner);
        if(ownerP) ownerP.money += rent;
        logMsg(`${p.name} 使用公共交通 ${s.name}，支付 ¥${rent} 给 ${s.owner}`);
        renderPlayersUI();
        checkBankrupt(playerIndex); // 检查破产
        // forced move 3
        setTimeout(()=> smoothMove(playerIndex, 3), 500);
        return;
      }
      // else can be purchased
      return;
    }
    // chance: 新增保释卡事件
    if(s.type === 'chance'){
      const ev = Math.random();
      if(ev < 0.25){ // 25%几率获得奖金
        const amt = 200 + Math.floor(Math.random()*800);
        p.money += amt; logMsg(`${p.name} 抽到机遇，获得 ¥${amt}`);
      }else if(ev < 0.5){ // 25%几率支付罚金
        const amt = 150 + Math.floor(Math.random()*600);
        p.money -= amt; logMsg(`${p.name} 抽到意外支出，支付 ¥${amt}`);
      }else if(ev < 0.7){ // 20%几率获得保释卡
        p.bailCard = (p.bailCard || 0) + 1; // 初始化保释卡属性
        logMsg(`${p.name} 抽到保释卡！当前持有 ${p.bailCard} 张`);
      }else if(ev < 0.82){ // 12%几率进监狱
        p.pos = 10; p.inJail = 3; logMsg(`${p.name} 抽到进监狱，已传送到监狱`);
        // 入狱时自动检查保释卡
        if(p.bailCard && p.bailCard > 0){
          if(p.isAI){ // AI自动使用
            p.bailCard -= 1;
            p.pos = 11; p.inJail = 0; // 使用保释卡后不入狱
            logMsg(`${p.name} 自动使用保释卡离开监狱，传送到探监口`);
          }else{ // 玩家手动选择
            if(confirm('你持有保释卡，是否立即使用离开监狱？')){
              p.bailCard -= 1;
              p.pos = 11; p.inJail = 0;
              logMsg(`${p.name} 使用保释卡离开监狱，传送到探监口`);
            }
          }
        }
      } else { // 18%几率普通事件
        logMsg(`${p.name} 抽到普通事件，无特殊影响`);
      }
      renderPlayersUI(); 
      checkBankrupt(playerIndex); // 检查破产
      return;
    }
    // corner: nothing
    if(s.type === 'corner') return;
    // city land
    if(s.type === 'city' && s.owner && s.owner !== p.name && !s.mortgaged){
      const rent = calcCityRent(idx);
      p.money -= rent;
      const ownerP = players.find(x=>x.name === s.owner);
      if(ownerP) ownerP.money += rent;
      logMsg(`${p.name} 支付过路费 ¥${rent} 给 ${s.owner}`);
      renderPlayersUI();
      checkBankrupt(playerIndex); // 检查破产
      return;
    }
    // else: unowned city/transport: human can buy; AI will auto-buy in their afterLanding
  }
  /* ---------------- Selection & Buttons ---------------- */
  function selectTile(i){
    selectedTile = i;
    const s = tileState[i];
    const pn = $('propName'), pi = $('propInfo');
    if(pn) pn.innerHTML = `<b>${s.name}</b>`;
    let info = `编号：${i}<br>`;
    if(s.type === 'corner') info += '角落格（不可购买）<br>';
    else if(s.type === 'chance') info += '机遇格（触发随机事件）<br>';
    else if(s.type === 'transport') info += `公共交通：${s.transportGroup}<br>`;
    else info += '类别：城市<br>';
    if(s.type !== 'corner') info += `价格：¥${getTilePrice(i)}<br>`;
    if(s.type === 'city') info += `建筑：${s.buildings} 大房：${s.bigHouse ? '是':'否'}<br>`;
    info += `拥有者：${s.owner ? s.owner : '无人'}<br>`;
    info += `抵押：${s.mortgaged ? '是':'否'}<br>`;
    if(pi) pi.innerHTML = info;
    refreshButtons();
  }
  function buySelected(){
    if(selectedTile < 0) return;
    const s = tileState[selectedTile]; const p = players[current];
    if(s.type === 'corner') return alert('角落不可购买');
    const price = getTilePrice(selectedTile);
    if(p.money < price) return alert('余额不足');
    p.money -= price; s.owner = p.name; logMsg(`${p.name} 购买 ${s.name}（¥${price}）`);
    updateTileVisual(selectedTile); renderPlayersUI();
    refreshButtons();
  }
  function buildSelected(){
    if(selectedTile < 0) return;
    const s = tileState[selectedTile]; const p = players[current];
    if(s.type !== 'city') return alert('只能在城市建房');
    if(s.owner !== p.name) return alert('只有拥有者可建造');
    if(s.bigHouse) return alert('已是大房');
    if(s.buildings >= 4) return alert('已达 4 个小房，请升级为大房');
    const cost = getBuildCost(selectedTile);
    if(p.money < cost) return alert('余额不足');
    p.money -= cost; s.buildings++; logMsg(`${p.name} 在 ${s.name} 建造小房（共 ${s.buildings}） 花费 ¥${cost}`);
    updateTileVisual(selectedTile); renderPlayersUI(); refreshButtons();
  }
  function upgradeSelected(){
    if(selectedTile < 0) return;
    const s = tileState[selectedTile]; const p = players[current];
    if(s.type !== 'city') return alert('只能升级城市大房');
    if(s.owner !== p.name) return alert('只有拥有者可升级');
    if(s.buildings < 4) return alert('需要先建 4 个小房');
    if(p.money < BIG_HOUSE_COST) return alert('余额不足');
    p.money -= BIG_HOUSE_COST; s.bigHouse = true; s.buildings = 0; logMsg(`${p.name} 将 ${s.name} 升级为大房`);
    updateTileVisual(selectedTile); renderPlayersUI(); refreshButtons();
  }
  function mortgageToggle(){
    if(selectedTile < 0) return;
    const s = tileState[selectedTile]; const p = players[current];
    if(s.owner !== p.name) return alert('只有拥有者可抵押/赎回');
    if(s.type === 'corner') return alert('角落不可抵押');
    if(!s.mortgaged){
      const amt = Math.floor(getTilePrice(selectedTile) * 0.5);
      s.mortgaged = true; p.money += amt; logMsg(`${p.name} 抵押 ${s.name} 获得 ¥${amt}`);
    } else {
      const cost = Math.ceil(getTilePrice(selectedTile) * 0.55);
      if(p.money < cost) return alert('余额不足赎回抵押');
      s.mortgaged = false; p.money -= cost; logMsg(`${p.name} 赎回 ${s.name} 支付 ¥${cost}`);
    }
    updateTileVisual(selectedTile); renderPlayersUI(); refreshButtons();
  }
  /* ---------------- Auction ---------------- */
  function triggerAuction(tileIdx){
    auction.active = true; auction.tile = tileIdx; auction.high = 0; auction.highName = null;
    $('aucTileName').textContent = tileState[tileIdx].name;
    $('aucHigh').textContent = '0';
    $('aucLog').innerHTML = '';
    $('aucBid').value = Math.max(100, Math.floor(getTilePrice(tileIdx)/2));
    $('auctionModal').classList.remove('hidden');
  }
  function finalizeAuction(){
    if(!auction.active){ $('auctionModal').classList.add('hidden'); return; }
    if(auction.high > 0 && auction.highName){
      const buyer = players.find(p=>p.name===auction.highName);
      if(buyer){
        buyer.money -= auction.high;
        tileState[auction.tile].owner = buyer.name;
        logMsg(`${buyer.name} 以 ¥${auction.high} 竞得 ${tileState[auction.tile].name}`);
        updateTileVisual(auction.tile); renderPlayersUI();
        checkBankrupt(players.findIndex(p=>p.name===auction.highName)); // 检查买家是否破产
      }
    }
    auction.active = false; $('auctionModal').classList.add('hidden');
  }
  /* ---------------- AI ---------------- */
  function aiAfterLanding(aiIdx){
    const ai = players[aiIdx]; 
    const pos = ai.pos; 
    const s = tileState[pos];
    // 1. 购买无主土地（90%几率）
    if(!s.owner && s.type !== 'corner'){
      const price = getTilePrice(pos);
      if(ai.money > price && Math.random() < 0.9){
        ai.money -= price; 
        s.owner = ai.name; 
        logMsg(`${ai.name} 自动购买 ${s.name}（¥${price}）`); 
        updateTileVisual(pos); 
        renderPlayersUI();
      }
    }
    // 2. 建造小房（资金充足时）
    if(s.owner === ai.name && s.type === 'city' && !s.bigHouse && s.buildings < 4){
      const cost = getBuildCost(pos);
      if(ai.money > cost * 1.2 && Math.random() > 0.45){
        ai.money -= cost; 
        s.buildings++; 
        logMsg(`${ai.name} 在 ${s.name} 建造小房（当前 ${s.buildings} 个）`); 
        updateTileVisual(pos); 
        renderPlayersUI();
      }
    }
    // 3. 升级大房（资金充足时）
    if(s.owner === ai.name && s.type === 'city' && s.buildings === 4 && !s.bigHouse){
      if(ai.money > BIG_HOUSE_COST && Math.random() > 0.4){
        ai.money -= BIG_HOUSE_COST; 
        s.bigHouse = true; 
        s.buildings = 0; 
        logMsg(`${ai.name} 升级 ${s.name} 为大房`); 
        updateTileVisual(pos); 
        renderPlayersUI();
      }
    }
    // 4. 检查AI是否破产
    checkBankrupt(aiIdx);
  }
  /* ---------------- Turn / Roll handlers ---------------- */
  $('btnStart')?.addEventListener('click', ()=> {
    const aiCount = parseInt($('aiCount')?.value || '2',10);
    startGame(aiCount);
  });
  $('btnReset')?.addEventListener('click', ()=> location.reload());
  $('rollBtn')?.addEventListener('click', ()=>{
    const p = players[current]; if(!p) return;
    $('rollBtn').disabled = true;
    const r1 = 1 + Math.floor(Math.random()*6);
    const r2 = 1 + Math.floor(Math.random()*6);
    rollVisual(r1,r2, ()=> {
      const sum = r1 + r2;
      logMsg(`${p.name} 掷出 ${r1} + ${r2} = ${sum}`);
      smoothMove(current, sum);
    });
    // 确保动画结束后才能点击结束按钮 (1050ms 对应 rollVisual 中的最长延时)
    setTimeout(()=>{ $('endBtn').disabled = false; }, 1050);
  });
  $('endBtn')?.addEventListener('click', ()=> {
    current = (current + 1) % players.length; updateTurn(); logMsg(`轮到 ${players[current].name}`);
  });
  $('buyBtn')?.addEventListener('click', buySelected);
  $('buildBtn')?.addEventListener('click', buildSelected);
  $('upgradeBtn')?.addEventListener('click', upgradeSelected);
  $('mortBtn')?.addEventListener('click', mortgageToggle);
  $('aucBtn')?.addEventListener('click', ()=> { if(selectedTile>=0) triggerAuction(selectedTile); });
  $('aucBidBtn')?.addEventListener('click', ()=>{
    const b = Number($('aucBid').value || 0);
    if(!auction.active) return;
    if(b <= auction.high) return alert('出价必须高于当前最高价');
    auction.high = b; auction.highName = players[current] ? players[current].name : null;
    $('aucHigh').textContent = auction.high;
    $('aucLog').innerHTML = `<div>最高：${auction.highName} ¥${auction.high}</div>` + $('aucLog').innerHTML;
  });
  $('aucClose')?.addEventListener('click', finalizeAuction);
  $('chatSend')?.addEventListener('click', ()=>{
    const v = $('chatInput').value.trim(); if(!v) return;
    chatRight(v); $('chatInput').value = '';
    setTimeout(()=> chatLeft('AI','已读'), 600);
  });
  /* ---------------- Start / Render helpers ---------------- */
  function startGame(aiCount=2){
    initTileState(true, null);
    generateBoardDOM();
    initDiceStrips();
    players = [];
    // 初始化玩家：添加保释卡属性（默认0张）
    players.push({name:'你',money:START_MONEY,pos:0,color:TOKEN_COLORS[0],isAI:false, bailCard:0});
    for(let i=0;i<aiCount;i++){
      players.push({name:`AI-${i+1}`,money:START_MONEY,pos:0,color:TOKEN_COLORS[(i+1)%TOKEN_COLORS.length],isAI:true, bailCard:0});
    }
    current = 0; started = true;
    logMsg(`游戏开始：你 vs ${aiCount} AI`);
    renderPlayersUI(); updateTurn();
  }
  function updateTurn(){
    $('curName').textContent = players[current] ? players[current].name : '—';
    document.querySelectorAll('.player').forEach((el,i)=> el.classList.toggle('current-turn', i===current));
    const isAI = players[current] ? players[current].isAI : false;
    $('rollBtn').disabled = isAI;
    $('endBtn').disabled = true;
    // if AI, auto roll after brief pause
    if(isAI){
      setTimeout(()=> {
        const r1 = 1 + Math.floor(Math.random()*6);
        const r2 = 1 + Math.floor(Math.random()*6);
        rollVisual(r1,r2, ()=> smoothMove(current, r1+r2));
      }, AI_ROLL_DELAY);
    }
  }
  /* ---------------- Initialization on load ---------------- */
  function initUI(){
    initTileState(true,null);
    generateBoardDOM();
    initDiceStrips();
    // wire quick start button already attached
    // default auto start 2 AI
    startGame(2);
  }

  // ===== 新增部分：发送玩家入狱并设置停3回合（用于修复 进监狱） =====
  function sendToJail(player){
    player.inJail = 3;
    player.pos = 10; // 监狱索引
    renderPlayersUI();
    logMsg(`${player.name} 被送入监狱，停 3 回合`);
    // add a small visual cue if jail cell exists
    const jailCell = document.querySelector(`.cell[data-index='10']`);
    if(jailCell){
      jailCell.classList.add('jail-locked');
      setTimeout(()=> jailCell.classList.remove('jail-locked'), 900);
    }
  }

  // 在 updateTurn 中添加对 inJail 的跳过支持（最小变动）
  const _origUpdateTurn = updateTurn;
  updateTurn = function(){
    // 如果当前玩家在监狱中，先处理监狱轮数并跳过（自动）
    if(players[current] && players[current].inJail && players[current].inJail > 0){
      players[current].inJail -= 1;
      logMsg(`${players[current].name} 在监狱中，跳过本回合，剩余 ${players[current].inJail} 回合`);
      // 出狱时传送到探监口（index 11），遵循你原来规则（不经过起点）
      if(players[current].inJail === 0){
        players[current].pos = 11;
        renderPlayersUI();
        logMsg(`${players[current].name} 已出狱并被传送到探监口`);
      }
      // 切换到下一位
      current = (current + 1) % players.length;
      // 防止无限循环，延迟执行下一个回合
      setTimeout(()=> _origUpdateTurn(), 120);
      return;
    }
    // 否则调用原本逻辑
    _origUpdateTurn();
  };

  // expose debug api
  window._monopoly = {
    tileState, players, startGame, getTilePrice, getBuildCost
  };
  // start
  document.addEventListener('DOMContentLoaded', initUI);
})();