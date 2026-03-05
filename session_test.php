<?php
require_once __DIR__ . '/core/session.php';
header('Content-Type: text/plain');
echo "Session Diagnostic\n";
echo "==================\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . " (1=NONE, 2=ACTIVE)\n";
echo "Session Name: " . session_name() . "\n";
echo "Cookie Path: " . ini_get('session.cookie_path') . "\n";
echo "Cookie Domain: " . ini_get('session.cookie_domain') . "\n";
echo "User ID: " . var_export(core_get_user_id(), true) . "\n";
echo "Is Logged In: " . (core_is_logged_in() ? 'Yes' : 'No') . "\n";
echo "\n_SESSION content:\n";
print_r($_SESSION);
echo "\n_COOKIE content:\n";
print_r($_COOKIE);
?>