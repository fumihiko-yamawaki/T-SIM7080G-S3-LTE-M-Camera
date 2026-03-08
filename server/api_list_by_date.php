<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$date = $_GET['date'] ?? '';
$mode = $_GET['mode'] ?? 'hourly';   // hourly / all / weekly

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json; charset=utf-8');
    echo '[]';
    exit;
}

$ymd = str_replace('-', '', $date);
$imgDir = __DIR__ . '/images';
$files = glob($imgDir . '/*.jpg');

$out = [];

foreach ($files as $f) {
    $name = basename($f);
    if ($name === 'latest.jpg') continue;

    $out[] = [
        'name' => $name,
        'url'  => 'images/' . $name,
        'time' => date('c', filemtime($f)),
        'mtime'=> filemtime($f)
    ];
}

usort($out, function ($a, $b) {
    return $b['mtime'] <=> $a['mtime']; // 新しい順
});

if ($mode === 'hourly') {

    $hourly = [];
    $used = [];

    foreach ($out as $img) {
        if (preg_match('/_(\d{8})_(\d{2})(\d{2})(\d{2})\.jpg$/', $img['name'], $m)) {

            $key = $m[1].'_'.$m[2];

            if (!isset($used[$key])) {
                $used[$key] = true;
                $hourly[] = $img;
            }
        }
    }

    $out = array_slice($hourly, 0, 24);
}

elseif ($mode === 'weekly') {

    $weekly = [];
    $used = [];

    foreach ($out as $img) {

        if (preg_match('/_(\d{8})_(\d{6})\.jpg$/', $img['name'], $m)) {

            $date = $m[1];
            $weekKey = substr($date,0,4).date('W', strtotime($date));

            if (!isset($used[$weekKey])) {
                $used[$weekKey] = true;
                $weekly[] = $img;
            }
        }
    }

    $out = array_slice($weekly, 0, 52); // 最大1年
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
