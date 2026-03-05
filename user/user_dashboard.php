<?php
/**
 * 用户中心主页 - 现代清爽版 (底层优化版)
 * 修改重点：恢复经典蓝绿配色、保留移动端完美适配、勋章蓝色高亮、合并秋葵余额查询
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 权限检查
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

// 数据库逻辑：动态获取表结构
$user_table = 'users';
$result = $conn->query("SHOW COLUMNS FROM `$user_table` ");
if (!$result) { $user_table = 'bc_users'; $result = $conn->query("SHOW COLUMNS FROM `$user_table` "); }
$fields = array();
while ($row = $result->fetch_assoc()) { $fields[] = $row['Field']; }

// 将 qiuqiao_balance 加入基础查询列表
$select_fields = array('username', 'email', 'created_at', 'status', 'qiuqiao_balance');
$additional_fields = array('phone', 'gender', 'birthdate', 'hobbies', 'motto', 'role_label', 'last_login');
foreach ($additional_fields as $field) { if (in_array($field, $fields)) { $select_fields[] = $field; } } 
$select_fields = array_unique($select_fields);

// 一次性查询出所有用户数据及资产
$sql = "SELECT " . implode(', ', $select_fields) . " FROM `$user_table` WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $username = $user['username'];
    $email = $user['email'];
    $created_at = $user['created_at'];
    $last_login = $user['last_login'];
    $phone = isset($user['phone']) ? $user['phone'] : '未设置';
    $gender = isset($user['gender']) ? $user['gender'] : '保密';
    $birthdate = isset($user['birthdate']) ? $user['birthdate'] : '未设置';
    $hobbies = isset($user['hobbies']) ? $user['hobbies'] : '';
    $motto = isset($user['motto']) && !empty($user['motto']) ? $user['motto'] : '失之东隅，又失桑榆';
    $role_label = isset($user['role_label']) && !empty($user['role_label']) ? $user['role_label'] : '普通用户';

    // 直接从 users 表中获取秋葵余额
    $qiuqiao_balance = isset($user['qiuqiao_balance']) ? (int)$user['qiuqiao_balance'] : 0;
    
} else {
    session_destroy();
    header("Location: user_login.php");
    exit();
}
$stmt->close();
// 注意：不要在这里 close($conn)，因为底部的 user_layout.php 可能还需要用到数据库连接

$page_title = "用户中心";
ob_start();
?>

<style>
    body { background-color: #f6f8fa; font-family: "Source Han Serif SC", "Noto Serif SC", -apple-system, "Microsoft YaHei", serif; -webkit-font-smoothing: antialiased; }
    .dashboard-container { display: grid; grid-template-columns: 1fr 320px; gap: 24px; max-width: 1100px; margin: 20px auto; padding: 0 20px; }

    /* 欢迎区域 - 纯净居中无蓝条 */
    .welcome-section {
        background: white; border-radius: 20px; padding: 50px 40px; margin-bottom: 24px;
        border: 1px solid rgba(0, 0, 0, 0.05); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        text-align: center;
    }
    .user-name { font-size: 38px; color: #1e293b; font-weight: 800; letter-spacing: 1px; margin-bottom: 20px; font-family: inherit; }
    .user-motto { 
        color: #64748b; font-size: 16px; background: #f8fafc; padding: 14px 30px; 
        border-radius: 14px; display: inline-flex; align-items: center; border: 1px solid #f1f5f9; 
        line-height: 1.6;
    }

    /* 卡片通用 */
    .info-section { background: white; border-radius: 18px; padding: 24px; border: 1px solid rgba(226, 232, 240, 0.8); margin-bottom: 24px; }
    .section-title { font-size: 15px; font-weight: 700; color: #475569; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; letter-spacing: 1px; font-family: -apple-system, sans-serif; }

    /* 信息格 */
    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .info-label { font-size: 13px; color: #94a3b8; margin-bottom: 6px; font-family: -apple-system, sans-serif; }
    .info-value { color: #334155; font-size: 14px; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; word-break: break-all; font-family: -apple-system, sans-serif; }

    /* 荣誉成就 - 重构版 */
    .medal-wall { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .medal-item { text-align: center; transition: 0.3s; padding: 10px 0; }
    .medal-icon { 
        width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; 
        justify-content: center; font-size: 22px; margin: 0 auto 10px; 
        background: #f8fafc; color: #cbd5e1; border: 1px solid #f1f5f9;
        transition: all 0.4s ease;
    }
    
    /* 激活状态：清爽亮蓝色 */
    .medal-item.active .medal-icon { 
        background: #007aff; 
        color: #fff; 
        border-color: #007aff; 
        box-shadow: 0 8px 15px rgba(0, 122, 255, 0.25); 
    }
    .medal-name { font-size: 13px; color: #64748b; font-weight: 600; font-family: -apple-system, sans-serif; }
    .medal-item.active .medal-name { color: #007aff; }

    /* 雅趣标签 */
    .hobby-tag { display: inline-block; padding: 6px 14px; background: #f1f5f9; color: #64748b; border-radius: 9px; font-size: 12px; margin: 0 8px 8px 0; border: 1px solid #e2e8f0; font-family: -apple-system, sans-serif; }
    
    /* 侧边栏卡片重构 */
    .stat-cards-wrapper { display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px; }
    .stat-card { padding: 24px; border-radius: 18px; color: white; position: relative; font-family: -apple-system, sans-serif; }
    
    /* 恢复经典色块 */
    .card-role { background: #007aff; } /* 名分：经典蓝 */
    .card-tokens { background: #34c759; } /* 秋葵：经典绿 */
    
    .stat-value { font-size: 26px; font-weight: 800; margin-top: 8px; }
    .stat-label { font-size: 13px; opacity: 0.85; }
    
    .action-buttons { display: flex; flex-direction: column; gap: 12px; }
    .btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 14px; font-size: 14px; font-weight: 700; text-decoration: none; transition: 0.3s; font-family: -apple-system, sans-serif; }
    
    /* 按钮恢复为清爽蓝色系 */
    .btn-primary { background: #007aff; color: white; } 
    .btn-success { background: #007aff; color: white; } /* 充值也统一为蓝色，保持视觉统一 */
    .btn-outline { background: white; color: #007aff; border: 1px solid #007aff; } /* 边框按钮也改为蓝色 */
    .btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0, 122, 255, 0.2); }

    /* ================= 移动端深度适配 ================= */
    @media (max-width: 850px) { 
        .dashboard-container { grid-template-columns: 1fr; } 
        /* 平板端卡片横向排列 */
        .stat-cards-wrapper { flex-direction: row; }
        .stat-card { flex: 1; margin-bottom: 0; }
    }

    @media (max-width: 600px) {
        .dashboard-container { padding: 0 12px; margin-top: 15px; }
        
        /* 欢迎区缩放 */
        .welcome-section { padding: 35px 20px; border-radius: 16px; }
        .user-name { font-size: 26px; margin-bottom: 15px; }
        .user-motto { font-size: 14px; padding: 12px 20px; text-align: left; }
        .user-motto i { margin: 0 6px; }

        /* 内容区紧凑排版 */
        .info-section { padding: 20px 16px; border-radius: 16px; }
        
        /* 解决长邮箱撑破屏幕的问题，改为单列 */
        .info-grid { grid-template-columns: 1fr; gap: 12px; }
        .info-value { padding: 10px 14px; }

        /* 徽章墙改为2列 */
        .medal-wall { grid-template-columns: repeat(2, 1fr); gap: 15px 10px; }
        .medal-icon { width: 50px; height: 50px; font-size: 18px; }

        /* 侧边信息堆叠 */
        .stat-cards-wrapper { flex-direction: column; gap: 12px; }
        .stat-card { padding: 20px; }
        .stat-value { font-size: 22px; }
    }
</style>

<div class="dashboard-container" style="max-width: 1100px; margin: 20px auto 0; grid-template-columns: 1fr; padding-bottom: 0;">
    <div class="welcome-section">
        <div class="user-name">新年快乐，<?php echo htmlspecialchars($username); ?></div>
        <div class="user-motto-box">
            <div class="user-motto">
                <i class="fas fa-quote-left" style="font-size: 12px; color: #cbd5e1;"></i>
                <span><?php echo htmlspecialchars($motto); ?></span>
                <i class="fas fa-quote-right" style="font-size: 12px; color: #cbd5e1;"></i>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-container" style="margin-top: 0;">
    <div class="main-info">
        <div class="info-section">
            <h3 class="section-title"><i class="fas fa-fingerprint"></i> 身份识别</h3>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">电子邮箱</div><div class="info-value"><?php echo htmlspecialchars($email); ?></div></div>
                <div class="info-item"><div class="info-label">联系电话</div><div class="info-value"><?php echo htmlspecialchars($phone); ?></div></div>
                <div class="info-item"><div class="info-label">性别</div><div class="info-value"><?php echo htmlspecialchars($gender); ?></div></div>
                <div class="info-item"><div class="info-label">寿辰</div><div class="info-value"><?php echo htmlspecialchars($birthdate); ?></div></div>
            </div>
        </div>

        <div class="info-section">
            <h3 class="section-title"><i class="fas fa-scroll"></i> 志行修养</h3>
            <div class="medal-wall">
                <div class="medal-item active">
                    <div class="medal-icon"><i class="fas fa-book-open"></i></div>
                    <span class="medal-name">贤贤易色</span>
                </div>
                <div class="medal-item active">
                    <div class="medal-icon"><i class="fas fa-seedling"></i></div>
                    <span class="medal-name">君子务本</span>
                </div>
                <div class="medal-item">
                    <div class="medal-icon"><i class="fas fa-landmark"></i></div>
                    <span class="medal-name">慎终追远</span>
                </div>
                <div class="medal-item <?php echo ($qiuqiao_balance >= 100) ? 'active' : ''; ?>">
                    <div class="medal-icon"><i class="fas fa-feather-alt"></i></div>
                    <span class="medal-name">不舍昼夜</span>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h3 class="section-title"><i class="fas fa-tags"></i> 雅趣</h3>
            <div class="hobby-container">
                <?php 
                if (!empty($hobbies)) {
                    $hobby_list = explode(',', $hobbies);
                    foreach ($hobby_list as $hobby) { echo '<span class="hobby-tag">' . htmlspecialchars(trim($hobby)) . '</span>'; }
                } else { echo '<span style="color:#94a3b8; font-size:14px;">素心如简</span>'; }
                ?>
            </div>
        </div>
    </div>

    <div class="side-info">
        <div class="stat-cards-wrapper">
            <div class="stat-card card-role">
                <div class="stat-label">当前名分</div>
                <div class="stat-value"><?php echo htmlspecialchars($role_label); ?></div>
            </div>
            <div class="stat-card card-tokens">
                <div class="stat-label">秋葵余量</div>
                <div class="stat-value"><?php echo $qiuqiao_balance; ?> <span style="font-size:16px;">✵</span></div>
            </div>
        </div>
        
        <div class="info-section">
            <h3 class="section-title" style="font-size:13px;">往来记录</h3>
            <div style="font-size:13px; color:#64748b; line-height:2.5; font-family: -apple-system, sans-serif;">
                始见于：<span style="color:#1e293b; float:right; font-weight:600;"><?php echo date('Y-m-d', strtotime($created_at)); ?></span><br>
                近造访：<span style="color:#1e293b; float:right; font-weight:600;"><?php echo date('m-d H:i', strtotime($last_login)); ?></span>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="user_edit.php" class="btn btn-primary"><i class="fas fa-pen-fancy"></i> 易经改志</a>
            <a href="recharge.php" class="btn btn-success"><i class="fas fa-coins"></i> 纳贤充值</a>
            <a href="change_password.php" class="btn btn-outline"><i class="fas fa-lock"></i> 闭关锁钥</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
define('IN_USER_CENTER', true);
require_once __DIR__ . '/user_layout.php';
?>