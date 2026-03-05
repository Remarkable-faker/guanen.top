<?php
/**
 * 用户注销页面
 * 
 * 彻底清除用户会话并跳转至登录页。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';

// 清除所有 Session 变量
$_SESSION = array();

// 如果使用了 Cookie，则销毁它
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 跳转到登录页面
header("Location: user_login.php");
exit();
?>
