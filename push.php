<?php
$token = 'YJzFCrp3G1c9EMPS';
$urls = [
    'https://guanen.top/',
    'https://guanen.top/pages/library.html',
    'https://guanen.top/pages/article.html',
    'https://guanen.top/pages/Resonance.html',
    'https://guanen.top/pages/culture_pastel.html',
    'https://guanen.top/pages/book_crossing.html',
    'https://guanen.top/pages/bc_library_rules.html',
    'https://guanen.top/pages/bc_agreement.html',
    'https://guanen.top/pages/bc_borrow_agreement.html',
    'https://guanen.top/pages/bc_penalties.html',
    'https://guanen.top/pages/bc_quiz.html',
    'https://guanen.top/pages/wenjuan.html',
    'https://guanen.top/pages/jianyi.html',
    'https://guanen.top/pages/privacy.html',
    'https://guanen.top/pages/agreement.html',
    'https://guanen.top/pages/guanen-profile.html'
];

// 按优先级分批
$priority_urls = [
    'high' => [  // 高优先级：首页和核心功能
        'https://guanen.top/',
        'https://guanen.top/pages/book_crossing.html',
        'https://guanen.top/pages/library.html',
        'https://guanen.top/pages/article.html',
        'https://guanen.top/pages/guanen-profile.html'
    ],
    'medium' => [  // 中优先级：协议和规则
        'https://guanen.top/pages/bc_library_rules.html',
        'https://guanen.top/pages/bc_agreement.html',
        'https://guanen.top/pages/bc_borrow_agreement.html',
        'https://guanen.top/pages/bc_penalties.html',
        'https://guanen.top/pages/privacy.html',
        'https://guanen.top/pages/agreement.html'
    ],
    'low' => [  // 低优先级：互动和其他
        'https://guanen.top/pages/Resonance.html',
        'https://guanen.top/pages/culture_pastel.html',
        'https://guanen.top/pages/bc_quiz.html',
        'https://guanen.top/pages/wenjuan.html',
        'https://guanen.top/pages/jianyi.html',
        'https://guanen.top/pages/guanen-profile.html'
    ]
];

// 分批推送函数
function pushUrls($urls, $token, $batchName) {
    if (empty($urls)) return;
    
    $api = "http://data.zz.baidu.com/urls?site=https://guanen.top&token={$token}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => implode("\n", $urls),
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "[{$batchName}] 推送 " . count($urls) . " 条，状态码：{$httpCode}\n";
    echo "返回结果：{$result}\n\n";
    
    return json_decode($result, true);
}

// 按优先级推送
echo "开始推送...\n";
echo "今日日期：" . date('Y-m-d') . "\n\n";

// 先推送高优先级
$result = pushUrls($priority_urls['high'], $token, '高优先级');
if ($result && isset($result['error']) && $result['error'] == 400) {
    echo "配额已用完，停止推送\n";
    exit;
}
sleep(2); // 等待2秒

// 再推送中优先级
$result = pushUrls($priority_urls['medium'], $token, '中优先级');
if ($result && isset($result['error']) && $result['error'] == 400) {
    echo "配额已用完，停止推送\n";
    exit;
}
sleep(2);

// 最后推送低优先级
pushUrls($priority_urls['low'], $token, '低优先级');

echo "推送完成！\n";
?>