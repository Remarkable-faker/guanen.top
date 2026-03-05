<?php
/**
 * 漂流系统用户 API (登录与注册)
 * 
 * 本文件负责处理书籍漂流系统的用户体系，包括：
 * 1. 用户注册（支持实名信息与问卷数据保存）
 * 2. 用户登录（基于 password_hash 加密验证）
 * 3. 登录状态检查与退出登录
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 引入 API 辅助工具函数
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 2. 启动输出缓冲并设置严格的错误控制
ob_start();
error_reporting(E_ALL); // 开启错误报告以便调试
ini_set('display_errors', 1);

/**
 * 确保用户表存在 (根据指令统一使用 bc_users 表)
 * 整合了 db_guanen.sql 中的云端结构与本地逻辑所需字段
 */
function ensure_user_table($conn) {
    $create_sql = "CREATE TABLE IF NOT EXISTS `bc_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL COMMENT '登录账号',
        `password` varchar(255) NOT NULL COMMENT '加密密码',
        `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
        `real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名',
        `id_card` varchar(20) DEFAULT NULL COMMENT '身份证号',
        `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
        `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
        `gender` varchar(20) DEFAULT NULL COMMENT '性别',
        `birthdate` date DEFAULT NULL COMMENT '生日',
        `hobbies` text COMMENT '兴趣爱好',
        `motto` text COMMENT '座右铭',
        `quiz_data` longtext DEFAULT NULL COMMENT '准入测试数据',
        `address` text DEFAULT NULL COMMENT '收货地址',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `last_login` timestamp NULL DEFAULT NULL,
        `status` tinyint(4) DEFAULT '1',
        `is_admin` tinyint(1) DEFAULT '0',
        `role_label` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username_unique` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='漂流系统用户表';";
    
    $conn->query($create_sql) or die($conn->error);

    // 动态检查缺失字段并补齐 (防止因表已存在而跳过新字段)
    $required_columns = [
        'nickname' => "VARCHAR(50) DEFAULT NULL COMMENT '昵称'",
        'real_name' => "VARCHAR(50) DEFAULT NULL COMMENT '真实姓名'",
        'id_card' => "VARCHAR(20) DEFAULT NULL COMMENT '身份证号'",
        'phone' => "VARCHAR(20) DEFAULT NULL COMMENT '手机号'",
        'email' => "VARCHAR(100) DEFAULT NULL COMMENT '邮箱'",
        'quiz_data' => "LONGTEXT DEFAULT NULL COMMENT '准入测试数据'",
        'address' => "TEXT DEFAULT NULL COMMENT '收货地址'",
        'gender' => "VARCHAR(20) DEFAULT NULL",
        'birthdate' => "DATE DEFAULT NULL",
        'hobbies' => "TEXT",
        'motto' => "TEXT",
        'status' => "TINYINT(4) DEFAULT '1'",
        'is_admin' => "TINYINT(1) DEFAULT '0'",
        'role_label' => "VARCHAR(50) DEFAULT NULL"
    ];

    foreach ($required_columns as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM `bc_users` LIKE '$col'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `bc_users` ADD COLUMN `$col` $def");
        }
    }
}

// 执行用户表结构检查
ensure_user_table($conn);

// 3. 解析操作指令
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 统一系统初始化配置接口
if ($action === 'init' || $action === 'get_config') {
    $is_logged_in = isset($_SESSION['bc_user_id']);
    $data = array(
        'is_logged_in' => $is_logged_in,
        'user_id' => $is_logged_in ? $_SESSION['bc_user_id'] : null,
        'username' => $is_logged_in ? $_SESSION['username'] : '',
        'nickname' => $is_logged_in ? (isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : $_SESSION['username']) : '',
        'config' => array(
            'allow_registration' => true
        )
    );
    // 兼容性处理：将 is_logged_in 等字段平铺到顶层
    api_success($data, '初始化成功', 200, $data);
}

// 4. 解析客户端输入数据 (支持 JSON 或标准表单)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

// 核心业务处理
try {
    switch ($action) {
        case 'register':
            // --- 用户注册逻辑 ---
            $username = isset($data['username']) ? trim($data['username']) : '';
            $password = isset($data['password']) ? $data['password'] : '';
            $confirm_password = isset($data['confirm_password']) ? $data['confirm_password'] : '';
            $email = isset($data['email']) ? trim($data['email']) : '';
            $phone = isset($data['phone']) ? trim($data['phone']) : '';
            $nickname = isset($data['nickname']) ? trim($data['nickname']) : '';
            $real_name = isset($data['real_name']) ? trim($data['real_name']) : '';
            $id_card = isset($data['id_card']) ? trim($data['id_card']) : '';
            $address = isset($data['address']) ? trim($data['address']) : '';
            $quiz_data = isset($data['quiz_answers']) ? json_encode($data['quiz_answers'], JSON_UNESCAPED_UNICODE) : null;

            if (empty($username) || empty($password)) {
                api_error('账号和密码不能为空');
            }

            if ($password !== $confirm_password) {
                api_error('两次输入的密码不一致');
            }

            // 检查用户名/手机号/邮箱是否已存在
            $stmt = $conn->prepare("SELECT id FROM bc_users WHERE username = ? OR phone = ? OR (email != '' AND email = ?)") or die($conn->error);
            $stmt->bind_param("sss", $username, $phone, $email);
            $stmt->execute() or die($stmt->error);
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                api_error('用户名、手机号或邮箱已被占用');
            }
            $stmt->close();

            // 密码加密 (使用 password_hash 加密)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 插入新用户记录
            $stmt = $conn->prepare("INSERT INTO bc_users (username, password, nickname, real_name, id_card, phone, email, address, quiz_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)") or die($conn->error);
            $stmt->bind_param("sssssssss", $username, $hashed_password, $nickname, $real_name, $id_card, $phone, $email, $address, $quiz_data);
            
            if ($stmt->execute()) {
                $stmt->close();
                api_success(null, '注册成功！请登录');
            } else {
                die($stmt->error);
            }
            break;

        case 'login':
            // --- 用户登录逻辑 ---
            $username = isset($data['username']) ? trim($data['username']) : '';
            $password = isset($data['password']) ? $data['password'] : '';

            if (empty($username) || empty($password)) {
                api_error('请输入账号和密码');
            }

            // 查询用户信息 (支持用户名、邮箱、手机号登录)
            $stmt = $conn->prepare("SELECT * FROM bc_users WHERE username = ? OR email = ? OR phone = ?") or die($conn->error);
            $stmt->bind_param("sss", $username, $username, $username);
            $stmt->execute() or die($stmt->error);
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                api_error('账号不存在');
            }

            if (isset($user['status']) && $user['status'] == 0) {
                api_error('账号已被禁用，请联系管理员');
            }

            // 验证密码 (优先使用 password_verify，兼容 MD5 和明文)
            $is_password_correct = false;
            $db_password = isset($user['password']) ? $user['password'] : (isset($user['password_hash']) ? $user['password_hash'] : '');
            
            if (password_verify($password, $db_password)) {
                $is_password_correct = true;
            } elseif (md5($password) === $db_password) {
                $is_password_correct = true;
            } elseif ($password === $db_password) {
                $is_password_correct = true;
            }

            if ($is_password_correct) {
                // 登录成功 - 解决 PHP 7.4 Session 刷新问题
                // 强制从 users 表同步/获取 ID 以确保聊天系统兼容性
                $stmt_sync = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt_sync->bind_param("s", $user['username']);
                $stmt_sync->execute();
                $res_sync = $stmt_sync->get_result();
                $user_id_val = $user['id'];
                if ($row_sync = $res_sync->fetch_assoc()) {
                    $user_id_val = $row_sync['id'];
                }
                $stmt_sync->close();

                $_SESSION['user_id'] = (int)$user_id_val; // 强制存储数字ID
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_logged_in'] = true;
                $_SESSION['user_table'] = 'bc_users'; 
                
                // 兼容性字段
                $_SESSION['bc_user_id'] = $user['id']; 
                $_SESSION['bc_nickname'] = $user['nickname'] ?: $user['username'];
                $_SESSION['is_admin'] = isset($user['is_admin']) ? (bool)$user['is_admin'] : false;
                
                // 兼容旧系统的 admin_id 赋值与所有相关键名
                if ($_SESSION['is_admin'] || in_array($user['username'], ['admin', 'guanen', 'mr.guanen'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['is_admin'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                }
                
                // 更新最后登录时间
                $update_stmt = $conn->prepare("UPDATE bc_users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $response_data = array(
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'nickname' => $_SESSION['bc_nickname'],
                    'is_admin' => $_SESSION['is_admin'],
                    'is_logged_in' => true
                );
                api_success($response_data, '登录成功', 200, $response_data);
            } else {
                api_error('密码错误');
            }
            break;

        case 'check_status':
            // --- 登录状态检查 ---
            if (isset($_SESSION['bc_user_id'])) {
                $data = array(
                    'is_logged_in' => true,
                    'user_id' => $_SESSION['bc_user_id'],
                    'username' => $_SESSION['username'],
                    'nickname' => isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : $_SESSION['username']
                );
                api_success($data, '已登录', 200, $data);
            } else {
                $data = array('is_logged_in' => false);
                api_success($data, '未登录', 200, $data);
            }
            break;

        case 'logout':
            // --- 退出登录 ---
            // 逻辑：清除所有相关的 Session 变量
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_logged_in'], $_SESSION['bc_user_id'], $_SESSION['bc_nickname']);
            api_success(null, '已退出登录');
            break;

        default:
            api_error('未知的操作指令: ' . $action);
            break;
    }
} catch (Exception $e) {
    api_error('系统错误: ' . $e->getMessage());
}

// 释放连接资源
if (isset($conn)) $conn->close();
