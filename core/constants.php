<?php
/**
 * 全局常量集中入口
 *
 * 设计原则：
 * 1. 复用 includes/user_config.php 中已有的常量（例如 BASE_URL, SITE_URL）。
 * 2. 不重复定义已有常量，避免产生 Warning。
 * 3. 为后续新增全局常量预留统一位置。
 */

// 引入现有的站点配置与常量定义
require_once dirname(__DIR__) . '/includes/user_config.php';

// 如需新增全局常量，可在此处使用 defined() 判断后再定义，例如：
//
// if (!defined('APP_ENV')) {
//     define('APP_ENV', 'production');
// }

