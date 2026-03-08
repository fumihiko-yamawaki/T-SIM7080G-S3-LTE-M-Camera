<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$intervalPath = __DIR__ . '/interval.txt';

// GitHub公開用の見本パスワード
$adminPassword = 'YOUR_INTERVAL_ADMIN_PASSWORD';

$v = trim((string)($_POST['interval_min'] ?? ''));
$p = trim((string)($_POST['admin_password'] ?? ''));

if ($p === '' || $p !== $adminPassword) {
    http_response_code(403);
    echo 'パスワードが正しくありません。';
    exit;
}

if ($v === '' || !ctype_digit($v)) {
    http_response_code(400);
    echo '数値を入力してください。';
    exit;
}

$min = (int)$v;
if ($min < 1) $min = 1;
if ($min > 1440) $min = 1440;

if (file_put_contents($intervalPath, (string)$min) === false) {
    http_response_code(500);
    echo 'interval.txt の保存に失敗しました。';
    exit;
}

echo '撮影間隔を ' . $min . ' 分に保存しました。';
