<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$imgDir = __DIR__ . '/images';
$files = glob($imgDir . '/*.jpg');
if (!$files) {
    header('Content-Type: application/json; charset=utf-8');
    echo '[]';
    exit;
}

usort($files, function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

$out = [];
foreach ($files as $f) {
    $name = basename($f);
    if ($name === 'latest.jpg') {
      continue;
    }
    $out[] = [
      'name' => $name,
      'url'  => 'images/' . $name,
      'time' => date('c', filemtime($f)),
    ];
    if (count($out) >= 4) {
      break;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
