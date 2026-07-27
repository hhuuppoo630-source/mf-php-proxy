<?php
// index.php

set_time_limit(0);
ini_set('memory_limit', '512M');

// إعدادات CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, HEAD, OPTIONS");
header("Access-Control-Allow-Headers: Range, Content-Type, Content-Range");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. استخراج الـ File Key من مسار الرابط أو الـ Query
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '/health') {
    header("Content-Type: application/json");
    echo json_encode(["status" => "ok", "service" => "MediaFire PHP Proxy"]);
    exit;
}

$file_key = null;
if (preg_match('#/(?:v|stream|play)/([a-zA-Z0-9]+)(?:\.mp4)?$#', $uri, $matches)) {
    $file_key = $matches[1];
} else {
    $file_key = $_GET['key'] ?? null;
}

if (!$file_key) {
    http_response_code(400);
    echo "Error: File key is missing.";
    exit;
}

// 2. دالة لاستخراج رابط التحميل المباشر مع كاش بسيط
function get_mediafire_direct_url($key) {
    $cache_file = sys_get_temp_dir() . "/mf_{$key}.json";
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < 300)) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $page_url = "https://www.mediafire.com/file/{$key}/file";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $page_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    $pattern = '/https?:\/\/download\d+\.mediafire\.com\/[^\s"\'<>]+/';
    if (preg_match($pattern, $html, $matches)) {
        $download_url = trim($matches[0], "'\"<>");
        $info = ['url' => $download_url];
        file_put_contents($cache_file, json_encode($info));
        return $info;
    }

    return null;
}

$info = get_mediafire_direct_url($file_key);

if (!$info) {
    http_response_code(404);
    echo "Error: Could not extract download URL from MediaFire.";
    exit;
}

$download_url = $info['url'];

// 3. تجهيز الطلب الموجه لـ MediaFire
$request_headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0.0 Safari/537.36',
    'Referer: https://www.mediafire.com/file/' . $file_key . '/file'
];

if (isset($_SERVER['HTTP_RANGE'])) {
    $request_headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $download_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// معالجة HEAD Request
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    curl_setopt($ch, CURLOPT_NOBODY, true);
}

// نقل الـ Headers القادمة من MediaFire مباشرة إلى العميل
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header_line) {
    $len = strlen($header_line);
    $header = trim($header_line);
    
    if (empty($header)) {
        return $len;
    }

    // تمرير الهيدرز الأساسية لتشغيل الفيديو والترجيع
    if (preg_match('/^(Content-Type|Content-Range|Content-Length|Accept-Ranges):/i', $header)) {
        header($header);
    }
    
    return $len;
});

// تمرير بيانات الفيديو كبث حي دون تخزين في الذاكرة
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    echo $data;
    ob_flush();
    flush();
    return strlen($data);
});

curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($http_code && $http_code !== 200) {
    http_response_code($http_code);
}

curl_close($ch);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header_line) {
    if (strpos($header_line, 'Content-Type:') === 0 || 
        strpos($header_line, 'Content-Range:') === 0 || 
        strpos($header_line, 'Content-Length:') === 0 || 
        strpos($header_line, 'Accept-Ranges:') === 0) {
        header($header_line);
    }
    return strlen($header_line);
});

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    echo $data;
    flush();
    return strlen($data);
});

header("Content-Type: video/mp4");
header("Accept-Ranges: bytes");

curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
http_response_code($http_code);
curl_close($ch);
