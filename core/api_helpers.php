<?php
/**
 * API 帮助函数集合
 *
 * 设计目标：
 * 1. 统一接口返回结构（success / message / code / data）。
 * 2. 与现有前端代码保持兼容 —— 可以在不破坏旧字段的前提下，额外补充标准结构。
 * 3. 让每个 API 文件的结尾只需调用一个函数，提升可读性。
 */

/**
 * 统一构造 JSON 响应并直接输出
 *
 * @param bool        $success 是否成功
 * @param string      $message 提示信息
 * @param mixed       $data    业务数据
 * @param int|string  $code    业务状态码（200 表示成功，401 表示未登录，500 表示服务器/数据库错误）
 * @param array       $extra   额外需要保留在顶层的兼容字段
 */
function api_json_response(
    $success,
    $message = '',
    $data = null,
    $code = null,
    $extra = array()
) {
    // 如果未指定 code，根据 success 自动判断
    if ($code === null) {
        $code = $success ? 200 : 400;
    }

    // 基础统一结构 - 按照用户要求：code, msg, data
    $payload = array(
        'code'    => (int)$code,
        'msg'     => $message, // 兼容性：用户要求使用 msg 字段
        'message' => $message, // 保持 message 字段以兼容旧前端
        'success' => $success,
        'data'    => $data,
    );

    // 如果 code 是 401，设置 HTTP 状态码
    if ((int)$code === 401 && !headers_sent()) {
        header('HTTP/1.1 401 Unauthorized');
    }

    // 注入额外字段（用于平铺旧版 API 的特定字段）
    if (!empty($extra)) {
        $payload = array_merge($payload, $extra);
    }

    // 设置 Content-Type 并输出
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // 清理可能已经产生的输出缓冲，防止 JSON 损坏
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * 语义化的成功响应封装
 *
 * @param mixed      $data
 * @param string     $message
 * @param int|string $code
 * @param array      $extra
 */
function api_success($data = null, $message = '', $code = null, $extra = array())
{
    api_json_response(true, $message, $data, $code, $extra);
}

/**
 * 语义化的失败响应封装
 *
 * @param string     $message
 * @param int|string $code
 * @param mixed      $data
 * @param array      $extra
 */
function api_error($message, $code = null, $data = null, $extra = array())
{
    api_json_response(false, $message, $data, $code, $extra);
}

