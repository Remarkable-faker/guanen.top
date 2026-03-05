<?php
/**
 * 抽奖系统 API 接口
 * 
 * 本文件负责处理抽奖系统的核心逻辑，包括：
 * 1. 抽奖配置加载（支持数据库与文件双备份）
 * 2. 抽奖次数校验（每日限制）
 * 3. 概率抽取算法（支持权重随机）
 * 4. 中奖记录保存与收货信息维护
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 解析客户端输入数据
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_REQUEST;
}

$action = isset($data['action']) ? $data['action'] : '';

// 3. 设置响应头并关闭错误报告
error_reporting(0);
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 4. 数据库错误处理宏
function handle_db_error($conn, $stmt = null) {
    $error = $stmt ? $stmt->error : $conn->error;
    api_error("数据库操作失败: " . $error, 500);
}

/**
 * 确保抽奖相关表存在 (兼容性检查)
 */
function ensure_lottery_tables($conn) {
    // 1. 检查并创建设置表
    $conn->query("CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key` VARCHAR(50) PRIMARY KEY,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点全局设置表';") or die($conn->error);

    // 2. 检查并创建抽奖记录表
    $conn->query("CREATE TABLE IF NOT EXISTS `lottery_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL COMMENT '用户ID',
        `prize_name` VARCHAR(100) NOT NULL COMMENT '奖品名称',
        `prize_value` TEXT DEFAULT NULL COMMENT '奖品描述',
        `is_win` TINYINT(1) DEFAULT 0 COMMENT '是否中奖',
        `draw_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '抽奖时间',
        `is_delivered` TINYINT(1) DEFAULT 0 COMMENT '是否发货',
        `receiver_name` VARCHAR(50) DEFAULT NULL COMMENT '收件人',
        `receiver_phone` VARCHAR(20) DEFAULT NULL COMMENT '收件电话',
        `receiver_address` TEXT DEFAULT NULL COMMENT '收件地址',
        INDEX (`user_id`),
        INDEX (`draw_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录表';") or die($conn->error);
}

// 执行结构检查
ensure_lottery_tables($conn);

/**
 * 加载抽奖配置逻辑
 * 优先级：数据库 > 配置文件 > 代码默认值
 */
function loadLotteryConfig($conn) {
    $config_file = dirname(__DIR__) . '/includes/lottery_config.json';
    $default_config = array(
        'max_daily_draws' => 9,
        'book_probability' => 0.003,
        'reward_pool_enabled' => false,
        'max_total_wins' => 1,
        'prizes' => array(
            array('name' => '浮生若梦', 'value' => '虚浮的人生', 'probability' => 20, 'is_win' => false),
            array('name' => '和光同尘', 'value' => '与世俗同尘', 'probability' => 80, 'is_win' => false)
        )
    );
    
    // 1. 尝试从数据库读取
    $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'lottery_config'");
    if (!$stmt) handle_db_error($conn);
    
    if (!$stmt->execute()) handle_db_error($conn, $stmt);
    
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $db_config = json_decode($row['setting_value'], true);
        $stmt->close();
        if ($db_config) return array_merge($default_config, $db_config);
    }
    $stmt->close();
    
    // 2. 备选：从文件读取
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        $file_config = json_decode($content, true);
        if ($file_config) return array_merge($default_config, $file_config);
    }
    
    return $default_config;
}

// 5. 初始化配置与操作
$config = loadLotteryConfig($conn);

// ==================== 核心修复：强制应用全局中奖概率 ====================
if (isset($config['book_probability'])) {
    // 1. 获取管理员填写的全局概率。
    // 如果后台填写 0.01（即前端标签写的 0-1 之间），为了适配后端的百分制，需要乘以 100（变成 1%）
    $raw_prob = (float)$config['book_probability'];
    $win_prob = ($raw_prob <= 1) ? ($raw_prob * 100) : $raw_prob;
    
    // 2. 统计有多少个“未中奖”（文案激励）选项
    $lose_count = 0;
    foreach ($config['prizes'] as $p) {
        if (empty($p['is_win']) && empty($p['isWin'])) {
            $lose_count++;
        }
    }
    
    // 3. 计算未中奖选项平分的剩余概率
    $lose_prob = $lose_count > 0 ? (100 - $win_prob) / $lose_count : 0;
    
    // 4. 强制重写每个奖项的最终抽奖概率
    foreach ($config['prizes'] as &$p) {
        $is_win = !empty($p['is_win']) || !empty($p['isWin']);
        $p['probability'] = $is_win ? $win_prob : $lose_prob;
    }
    unset($p); // 销毁引用，防止后续变量冲突
}
// ====================================================================

// 6. 获取当前用户 ID
$user_id = core_get_user_id();
// 调试日志：记录登录状态检查
// file_put_contents(dirname(__DIR__) . '/logs/session_debug.log', date('[Y-m-d H:i:s] ') . "Action: $action, UserID: " . var_export($user_id, true) . ", Session: " . json_encode($_SESSION) . "\n", FILE_APPEND);

// 7. 统一标准：获取初始化配置和登录状态
if ($action === 'init' || $action === 'get_config') {
    $today = date('Y-m-d');
    $used_draws = 0;
    
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE user_id = ? AND DATE(draw_time) = ? AND prize_name != '信息登记'");
        if (!$stmt) handle_db_error($conn);
        $stmt->bind_param("is", $user_id, $today);
        if (!$stmt->execute()) handle_db_error($conn, $stmt);
        $stmt->bind_result($used_draws);
        $stmt->fetch();
        $stmt->close();

        // 同时获取最近 10 条记录，用于页面初始化展示
        $stmt = $conn->prepare("SELECT id, prize_name, prize_value, is_win, draw_time, receiver_name FROM lottery_records WHERE user_id = ? ORDER BY draw_time DESC LIMIT 10");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $records = array();
        while ($row = $res->fetch_assoc()) {
            $row['is_win'] = (bool)$row['is_win'];
            $records[] = $row;
        }
        $stmt->close();
    }

    $init_data = array(
        'is_logged_in' => $user_id > 0,
        'user_id' => $user_id,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'max_daily_draws' => (int)$config['max_daily_draws'],
        'used_draws' => (int)$used_draws,
        'remaining_draws' => max(0, (int)$config['max_daily_draws'] - (int)$used_draws),
        'prizes' => $config['prizes'],
        'records' => isset($records) ? $records : array()
    );
    // 使用 extra 参数将数据平铺到顶层，兼容旧版前端
    api_success($init_data, '', 200, $init_data);
}

// --- 抽奖核心逻辑开始 ---
if ($user_id <= 0) {
    if ($action !== 'get_winners') {
        api_error('请先登录', 401);
    }
}

// 4. 核心业务处理
try {
    switch ($action) {
        case 'get_remaining_draws':
            // --- 获取今日剩余抽奖次数 ---
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE user_id = ? AND DATE(draw_time) = ? AND prize_name != '信息登记'");
            if (!$stmt) handle_db_error($conn);
            $stmt->bind_param("is", $user_id, $today);
            if (!$stmt->execute()) handle_db_error($conn, $stmt);
            $stmt->bind_result($used_draws);
            $stmt->fetch();
            $stmt->close();
            
            $draws_info = array(
                'remaining_draws' => max(0, (int)$config['max_daily_draws'] - (int)$used_draws),
                'max_daily_draws' => (int)$config['max_daily_draws'],
                'used_draws' => (int)$used_draws
            );
            
            api_success($draws_info, '', 200, $draws_info);
            break;

        case 'get_records':
            // --- 获取用户最近 10 条抽奖记录 ---
            $stmt = $conn->prepare("SELECT id, prize_name, prize_value, is_win, draw_time, is_delivered FROM lottery_records WHERE user_id = ? ORDER BY draw_time DESC LIMIT 10") or die($conn->error);
            $stmt->bind_param("i", $user_id);
            $stmt->execute() or die($stmt->error);
            $res = $stmt->get_result();
            $records = array();
            while ($row = $res->fetch_assoc()) {
                $row['is_win'] = (bool)$row['is_win'];
                $records[] = $row;
            }
            $stmt->close();
            
            // 使用 extra 参数将 records 平铺到顶层，兼容前端
            api_success($records, '', 200, array('records' => $records));
            break;

        case 'draw_lottery':
            // --- 执行抽奖逻辑 ---
            $today = date('Y-m-d');
            
            // 1. 次数检查
            $stmt = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE user_id = ? AND DATE(draw_time) = ? AND prize_name != '信息登记'") or die($conn->error);
            $stmt->bind_param("is", $user_id, $today);
            $stmt->execute() or die($stmt->error);
            $stmt->bind_result($used_draws);
            $stmt->fetch();
            $stmt->close();

            if ($used_draws >= $config['max_daily_draws']) {
                api_error("今日抽奖次数已用完，明天再来吧", "NO_MORE_DRAWS");
            }

            // 2. 概率抽取逻辑 (权重随机算法)
            $prizes = $config['prizes'];
            
            // 奖池余量检查（如果启用全局中奖限制）
            if (isset($config['reward_pool_enabled']) && $config['reward_pool_enabled']) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM lottery_records WHERE is_win = 1") or die($conn->error);
                $stmt->execute() or die($stmt->error);
                $stmt->bind_result($total_wins);
                $stmt->fetch();
                $stmt->close();
                
                if ($total_wins >= $config['max_total_wins']) {
                    // 奖池已空，强制不中奖
                    foreach ($prizes as &$p) {
                        if ($p['is_win']) $p['probability'] = 0;
                    }
                }
            }

            // 执行权重随机抽取 (1-10000 映射 0.01%-100%)
            $rand = mt_rand(1, 10000) / 100;
            $cumulative = 0;
            $selected = $prizes[0];
            foreach ($prizes as $p) {
                $cumulative += $p['probability'];
                if ($rand <= $cumulative) {
                    $selected = $p;
                    break;
                }
            }

            // 3. 保存抽奖结果
            $is_win = $selected['is_win'] ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO lottery_records (user_id, prize_name, prize_value, is_win) VALUES (?, ?, ?, ?)");
            if (!$stmt) handle_db_error($conn);
            $stmt->bind_param("issi", $user_id, $selected['name'], $selected['value'], $is_win);
            
            if ($stmt->execute()) {
                $record_id = $conn->insert_id;
                $stmt->close();
                
                $draw_result = array(
                    'record_id' => $record_id,
                    'prize_name' => $selected['name'],
                    'prize_value' => $selected['value'],
                    'is_win' => (bool)$is_win
                );
                
                // 使用 extra 参数平铺数据到顶层，解决前端显示 undefined 的问题
                api_success($draw_result, "抽奖成功", 200, $draw_result);
            } else {
                handle_db_error($conn, $stmt);
            }
            break;

        case 'save_delivery_info':
            // --- 保存中奖者收货信息 ---
           $record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
// 兼容前端传过来的 receiver_name 等字段名
$name = isset($_POST['receiver_name']) ? trim($_POST['receiver_name']) : (isset($_POST['name']) ? trim($_POST['name']) : '');
$phone = isset($_POST['receiver_phone']) ? trim($_POST['receiver_phone']) : (isset($_POST['phone']) ? trim($_POST['phone']) : '');
$address = isset($_POST['receiver_address']) ? trim($_POST['receiver_address']) : (isset($_POST['address']) ? trim($_POST['address']) : '');

            if (!$record_id || !$name || !$phone || !$address) {
                api_error("请填写完整的收货信息");
            }

            $stmt = $conn->prepare("UPDATE lottery_records SET receiver_name = ?, receiver_phone = ?, receiver_address = ? WHERE id = ? AND user_id = ?");
            if (!$stmt) handle_db_error($conn);
            $stmt->bind_param("sssii", $name, $phone, $address, $record_id, $user_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                api_success(null, "收货信息已保存，我们将尽快为您安排发货");
            } else {
                handle_db_error($conn, $stmt);
            }
            break;

        case 'test':
            // --- 健康检查 ---
            api_success(array('status' => 'online', 'time' => date('Y-m-d H:i:s')));
            break;

        default:
            api_error("未知的操作指令: " . $action);
            break;
    }
} catch (Exception $e) {
    api_error('系统错误: ' . $e->getMessage());
}

// 释放连接资源
if (isset($conn)) $conn->close();
