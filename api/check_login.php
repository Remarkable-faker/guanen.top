<?php
/**
 * 登录状态检查接口
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once dirname(__DIR__) . '/core/session.php';
// 强制包含数据库配置文件（统一标准）
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);
require_once dirname(__DIR__) . '/core/api_helpers.php';

// 处理请求动作
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// 组装兼容字段（旧版前端依赖）
$compat = array(
    'logged_in' => false,
    'username'  => '',
);

// 检查是否来自藏书阁页面
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$is_library_request = strpos($referer, 'library.html') !== false;

// 使用核心函数判断登录态
if ($is_library_request) {
    // 藏书阁页面需要验证漂流系统登录
    if (isset($_SESSION['bc_user_id'])) {
        $compat['logged_in'] = true;
        $compat['username'] = isset($_SESSION['bc_nickname']) ? $_SESSION['bc_nickname'] : $_SESSION['username'];
    }
} else {
    // 其他页面使用通用登录验证
    if (core_is_logged_in()) {
        $compat['logged_in'] = true;
        $compat['username'] = core_current_username();
    }
}

// 统一返回结构
$data = array(
    'logged_in'    => $compat['logged_in'],
    'is_logged_in' => $compat['logged_in'], // 新版统一字段
    'username'     => $compat['username'],
    'user_id'      => core_get_user_id(),
    'is_admin'     => core_is_admin()
);

api_success($data, $compat['logged_in'] ? '已登录' : '未登录', 200, $compat);

