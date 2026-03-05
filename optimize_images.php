<?php
// 图片批量瘦身脚本：将超大 WebP/JPG/PNG 缩放至最大宽度 800px
$target_dir = __DIR__ . '/assets/images/'; // 你的图片目录
$max_width = 800; // 网页实际需要的最大宽度

if (!is_dir($target_dir)) {
    die("未找到图片目录: " . $target_dir);
}

$files = scandir($target_dir);
echo "<h3>开始优化图片...</h3><ul>";

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $file_path = $target_dir . $file;
    
    // 获取图片信息
    $img_info = @getimagesize($file_path);
    if (!$img_info) continue;
    
    list($width, $height) = $img_info;
    $mime = $img_info['mime'];
    
    // 如果宽度已经符合要求，跳过
    if ($width <= $max_width) {
        continue;
    }
    
    // 计算新尺寸 (保持比例)
    $new_width = $max_width;
    $new_height = floor($height * ($max_width / $width));
    
    // 根据类型加载原图
    $src_img = null;
    switch ($mime) {
        case 'image/jpeg': $src_img = imagecreatefromjpeg($file_path); break;
        case 'image/png':  $src_img = imagecreatefrompng($file_path); break;
        case 'image/webp': $src_img = imagecreatefromwebp($file_path); break;
    }
    
    if (!$src_img) continue;
    
    // 创建新画布并重采样
    $dst_img = imagecreatetruecolor($new_width, $new_height);
    
    // 处理透明通道 (PNG/WebP)
    imagealphablending($dst_img, false);
    imagesavealpha($dst_img, true);
    $transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
    imagefilledrectangle($dst_img, 0, 0, $new_width, $new_height, $transparent);
    
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // 覆盖原文件 (统一存为 WebP 格式以获得最佳体积，质量设为 80)
    imagewebp($dst_img, $file_path, 80);
    
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    echo "<li>已优化: <b>{$file}</b> (原 {$width}x{$height} -> 新 {$new_width}x{$new_height})</li>";
}
echo "</ul><p><b>优化完成！请务必清理 CDN 缓存后再测试。</b></p>";
?>