<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$baseDir   = __DIR__;
$imgDir    = $baseDir . '/images';
$latestJs  = $baseDir . '/latest.json';
$logCsv    = $baseDir . '/status_log.csv';
$latestJpg = $imgDir . '/latest.jpg';

if (!is_dir($imgDir)) {
    mkdir($imgDir, 0775, true);
}

$deviceId    = $_POST['device_id']    ?? '';
$secret      = $_POST['secret']       ?? '';
$bootSeq     = $_POST['boot_seq']     ?? '0';
$intervalMin = $_POST['interval_min'] ?? '0';
$battMv      = $_POST['batt_mv']      ?? '0';
$battPercent = $_POST['batt_percent'] ?? '-1';
$vbusIn      = $_POST['vbus_in']      ?? '0';
$vbusMv      = $_POST['vbus_mv']      ?? '0';
$sysMv       = $_POST['sys_mv']       ?? '0';
$charging    = $_POST['charging']     ?? '0';
$csq         = $_POST['csq']          ?? '-1';
$mode        = $_POST['mode']         ?? '';

if ($deviceId === '' || $secret === '') {
    http_response_code(400);
    echo 'missing device_id or secret';
    exit;
}

/*
  GitHub公開用のダミー設定
  実運用では別ファイルに分離することを推奨
*/
$validSecrets = [
    'TSIM7080G_CAM01' => 'YOUR_DEVICE_SECRET',
];

if (!isset($validSecrets[$deviceId]) || $validSecrets[$deviceId] !== $secret) {
    http_response_code(403);
    echo 'invalid secret';
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'image upload failed';
    exit;
}

$ts = date('Ymd_His');
$filename = $deviceId . '_' . $ts . '.jpg';
$dest = $imgDir . '/' . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
    http_response_code(500);
    echo 'save failed';
    exit;
}

@copy($dest, $latestJpg);

$latest = [
    'device_id'     => $deviceId,
    'filename'      => $filename,
    'image_url'     => 'images/' . $filename,
    'latest_url'    => 'images/latest.jpg',
    'received_at'   => date('c'),
    'boot_seq'      => (int)$bootSeq,
    'interval_min'  => (int)$intervalMin,
    'batt_mv'       => (int)$battMv,
    'batt_percent'  => (int)$battPercent,
    'vbus_in'       => (int)$vbusIn,
    'vbus_mv'       => (int)$vbusMv,
    'sys_mv'        => (int)$sysMv,
    'charging'      => (int)$charging,
    'csq'           => (int)$csq,
    'mode'          => $mode,
];

file_put_contents(
    $latestJs,
    json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

if (!file_exists($logCsv)) {
    file_put_contents(
        $logCsv,
        "received_at,device_id,filename,boot_seq,interval_min,batt_mv,batt_percent,vbus_in,vbus_mv,sys_mv,charging,csq,mode\n"
    );
}

$line = sprintf(
    "%s,%s,%s,%d,%d,%d,%d,%d,%d,%d,%d,%d,%s\n",
    date('c'),
    $deviceId,
    $filename,
    (int)$bootSeq,
    (int)$intervalMin,
    (int)$battMv,
    (int)$battPercent,
    (int)$vbusIn,
    (int)$vbusMv,
    (int)$sysMv,
    (int)$charging,
    (int)$csq,
    $mode
);
file_put_contents($logCsv, $line, FILE_APPEND | LOCK_EX);

echo 'ok:' . $filename;
exit;
