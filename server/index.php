<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$baseDir = __DIR__;
$latestJsonPath = $baseDir . '/latest.json';
$intervalPath   = $baseDir . '/interval.txt';

$latest = [
    'device_id'     => '',
    'filename'      => '',
    'image_url'     => 'images/latest.jpg',
    'latest_url'    => 'images/latest.jpg',
    'received_at'   => '',
    'boot_seq'      => 0,
    'interval_min'  => 60,
    'batt_mv'       => 0,
    'batt_percent'  => -1,
    'vbus_in'       => 0,
    'vbus_mv'       => 0,
    'sys_mv'        => 0,
    'charging'      => 0,
    'csq'           => -1,
    'mode'          => '',
];

if (is_file($latestJsonPath)) {
    $json = file_get_contents($latestJsonPath);
    $tmp = json_decode($json, true);
    if (is_array($tmp)) {
        $latest = array_merge($latest, $tmp);
    }
}

$currentInterval = 60;
if (is_file($intervalPath)) {
    $v = trim((string)file_get_contents($intervalPath));
    if (ctype_digit($v)) {
        $currentInterval = (int)$v;
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>タイトル</title>
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #eef2f3;
      color: #1f2937;
      font-family: Arial, "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
      overflow: hidden;
    }

    .page {
      width: 100%;
      max-width: 1900px;
      min-width: 1400px;
      height: 900px;
      margin: 0 auto;
      padding: 12px;
      box-sizing: border-box;
    }

    .header {
      height: 68px;
      margin-bottom: 10px;
      border-radius: 12px;
      padding: 0 18px;
      box-sizing: border-box;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: #fff;
      background: linear-gradient(135deg, #2e6a2f, #4b8d3a);
      box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    }

    .header-title {
      font-size: 34px;
      font-weight: 700;
      letter-spacing: 0.02em;
    }

    .header-sub {
      font-size: 13px;
      line-height: 1.5;
      text-align: right;
      opacity: 0.96;
    }

    .layout {
      display: grid;
      grid-template-columns: 1080px 760px;
      grid-template-rows: 560px 238px;
      gap: 10px;
      height: calc(900px - 68px - 10px - 24px);
    }

    .card {
      background: #fff;
      border: 1px solid #d8e0e6;
      border-radius: 12px;
      padding: 12px;
      box-sizing: border-box;
      box-shadow: 0 1px 4px rgba(0,0,0,0.05);
      overflow: hidden;
    }

    .card-title {
      font-size: 20px;
      font-weight: 700;
      color: #234a23;
      margin-bottom: 8px;
      line-height: 1.2;
    }

    .latest-card {
      grid-column: 1;
      grid-row: 1;
      display: flex;
      flex-direction: column;
    }

    .latest-image-box {
      width: 100%;
      aspect-ratio: 4 / 3;
      background: #11181d;
      border: 1px solid #cfd8df;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .latest-image {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }

    .latest-meta {
      margin-top: 8px;
      display: grid;
      grid-template-columns: 220px 1fr 170px 120px;
      gap: 8px;
    }

    .meta-box {
      background: #f7fafc;
      border: 1px solid #e2e8ef;
      border-radius: 8px;
      padding: 8px 10px;
      box-sizing: border-box;
      min-height: 58px;
    }

    .meta-k {
      font-size: 11px;
      color: #607080;
      margin-bottom: 4px;
    }

    .meta-v {
      font-size: 15px;
      font-weight: 700;
      line-height: 1.25;
      word-break: break-all;
    }

    .recent-card {
      grid-column: 2;
      grid-row: 1;
      display: flex;
      flex-direction: column;
    }

    .recent-grid {
      flex: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      grid-template-rows: 1fr 1fr;
      gap: 10px;
      min-height: 0;
    }

    .recent-item {
      display: flex;
      flex-direction: column;
      min-height: 0;
    }

    .recent-image-box {
      width: 100%;
      aspect-ratio: 4 / 3;
      background: #1f252b;
      border: 1px solid #d2dce6;
      border-radius: 8px;
      overflow: hidden;
      flex: 1;
    }

    .recent-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      cursor: pointer;
    }

    .recent-name {
      margin-top: 4px;
      font-size: 11px;
      line-height: 1.2;
      color: #4b5563;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .status-card {
      grid-column: 1;
      grid-row: 2;
      display: flex;
      flex-direction: column;
    }

    .status-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
    }

    .status-box {
      background: #f8fafc;
      border: 1px solid #e1e7ed;
      border-radius: 8px;
      padding: 7px 8px;
      min-height: 52px;
      box-sizing: border-box;
    }

    .status-k {
      font-size: 10px;
      color: #667788;
      margin-bottom: 3px;
      line-height: 1.2;
    }

    .status-v {
      font-size: 16px;
      font-weight: 700;
      line-height: 1.2;
      color: #1f2937;
      word-break: break-word;
    }

    .control-card {
      grid-column: 2;
      grid-row: 2;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .control-row {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 8px;
    }

    .control-row label {
      font-size: 14px;
      font-weight: 700;
    }

    input[type="number"] {
      width: 120px;
      height: 38px;
      padding: 0 10px;
      font-size: 18px;
      border: 1px solid #b8c6d4;
      border-radius: 8px;
      box-sizing: border-box;
    }

    button, .btn-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 38px;
      padding: 0 14px;
      border-radius: 8px;
      border: 1px solid #2f6b2f;
      background: #2f6b2f;
      color: #fff;
      text-decoration: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 700;
      box-sizing: border-box;
    }

    .btn-sub {
      background: #fff;
      color: #2f6b2f;
    }

    .save-status {
      min-height: 22px;
      font-size: 13px;
      color: #334155;
      margin-bottom: 6px;
    }

    .control-note {
      font-size: 12px;
      line-height: 1.6;
      color: #4b5563;
    }

    .footer-note {
      margin-top: 8px;
      font-size: 11px;
      color: #6b7280;
    }
  </style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="header-title">タイトル</div>
    <div class="header-sub">
      コメント１<br>
      コメント２
    </div>
  </div>

  <div class="layout">
    <div class="card latest-card">
      <div class="card-title">最新画像</div>
      <div class="latest-image-box">
        <img id="mainImage" class="latest-image" src="<?php echo htmlspecialchars($latest['latest_url']); ?>?t=<?php echo time(); ?>" alt="最新画像">
      </div>
      <div class="latest-meta">
        <div class="meta-box">
          <div class="meta-k">受信時刻</div>
          <div class="meta-v" id="receivedAt"><?php echo htmlspecialchars((string)$latest['received_at']); ?></div>
        </div>
        <div class="meta-box">
          <div class="meta-k">画像ファイル名</div>
          <div class="meta-v" id="fileName" style="font-size:13px;"><?php echo htmlspecialchars((string)$latest['filename']); ?></div>
        </div>
        <div class="meta-box">
          <div class="meta-k">デバイスID</div>
          <div class="meta-v" id="deviceId"><?php echo htmlspecialchars((string)$latest['device_id']); ?></div>
        </div>
        <div class="meta-box">
          <div class="meta-k">通信</div>
          <div class="meta-v" id="mode"><?php echo htmlspecialchars((string)$latest['mode']); ?></div>
        </div>
      </div>
    </div>

    <div class="card recent-card">
      <div class="card-title">直近画像 4 枚</div>
      <div class="recent-grid" id="recentGrid">
        <div class="recent-item">
          <div class="recent-image-box"><img class="recent-image" src="images/latest.jpg?t=<?php echo time(); ?>" alt=""></div>
          <div class="recent-name">読込中...</div>
        </div>
        <div class="recent-item">
          <div class="recent-image-box"><img class="recent-image" src="images/latest.jpg?t=<?php echo time(); ?>" alt=""></div>
          <div class="recent-name">読込中...</div>
        </div>
        <div class="recent-item">
          <div class="recent-image-box"><img class="recent-image" src="images/latest.jpg?t=<?php echo time(); ?>" alt=""></div>
          <div class="recent-name">読込中...</div>
        </div>
        <div class="recent-item">
          <div class="recent-image-box"><img class="recent-image" src="images/latest.jpg?t=<?php echo time(); ?>" alt=""></div>
          <div class="recent-name">読込中...</div>
        </div>
      </div>
    </div>

    <div class="card status-card">
      <div class="card-title">電源状態</div>
      <div class="status-grid">
        <div class="status-box"><div class="status-k">バッテリー電圧</div><div class="status-v" id="battMv"><?php echo (int)$latest['batt_mv']; ?> mV</div></div>
        <div class="status-box"><div class="status-k">バッテリー残量</div><div class="status-v" id="battPercent"><?php echo (int)$latest['batt_percent']; ?> %</div></div>
        <div class="status-box"><div class="status-k">VBUS入力</div><div class="status-v" id="vbusIn"><?php echo ((int)$latest['vbus_in'] === 1) ? 'あり' : 'なし'; ?></div></div>
        <div class="status-box"><div class="status-k">VBUS電圧</div><div class="status-v" id="vbusMv"><?php echo (int)$latest['vbus_mv']; ?> mV</div></div>
        <div class="status-box"><div class="status-k">システム電圧</div><div class="status-v" id="sysMv"><?php echo (int)$latest['sys_mv']; ?> mV</div></div>
        <div class="status-box"><div class="status-k">充電状態</div><div class="status-v" id="charging"><?php echo ((int)$latest['charging'] === 1) ? '充電中' : '停止'; ?></div></div>
        <div class="status-box"><div class="status-k">CSQ</div><div class="status-v" id="csq"><?php echo (int)$latest['csq']; ?></div></div>
        <div class="status-box"><div class="status-k">現在インターバル</div><div class="status-v" id="intervalNow"><?php echo (int)$currentInterval; ?> 分</div></div>
      </div>
    </div>

    <div class="card control-card">
      <div>
        <div class="card-title">設定・操作</div>
 <div class="control-row">
  <label for="intervalMin">撮影間隔（分）</label>
  <input type="number" id="intervalMin" min="1" max="1440" value="<?php echo (int)$currentInterval; ?>">

  <label for="adminPassword">変更パスワード</label>
  <input type="password" id="adminPassword" style="width:180px;height:38px;padding:0 10px;font-size:16px;border:1px solid #b8c6d4;border-radius:8px;box-sizing:border-box;">

  <button type="button" onclick="saveInterval()">保存</button>
  <button class="btn-sub" type="button" onclick="reloadAll()">最新状態を更新</button>
  <a class="btn-link btn-sub" href="history.php">過去画像ページへ</a>
</div>        <div class="save-status" id="saveStatus">通常は 60 分程度の設定で運用できます。</div>
        <div class="control-note">
          農作物の成長を定点観測に適したシステムです。<br>
          インターバル変更後は、次回端末通信時に新しい設定が反映されます。<br>
        </div>
      </div>
      <div class="footer-note">
        フルHD画面用にデザインされています。
      </div>
    </div>
  </div>
</div>

<script>
async function fetchJson(url) {
  const r = await fetch(url + (url.includes('?') ? '&' : '?') + 't=' + Date.now(), {cache:'no-store'});
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return await r.json();
}

async function reloadLatest() {
  const d = await fetchJson('latest.json');

  document.getElementById('mainImage').src = (d.latest_url || 'images/latest.jpg') + '?t=' + Date.now();
  document.getElementById('receivedAt').textContent = d.received_at || '';
  document.getElementById('fileName').textContent = d.filename || '';
  document.getElementById('deviceId').textContent = d.device_id || '';
  document.getElementById('mode').textContent = d.mode || '';

  document.getElementById('battMv').textContent = (d.batt_mv ?? 0) + ' mV';
  document.getElementById('battPercent').textContent = (d.batt_percent ?? -1) + ' %';
  document.getElementById('vbusIn').textContent = Number(d.vbus_in) === 1 ? 'あり' : 'なし';
  document.getElementById('vbusMv').textContent = (d.vbus_mv ?? 0) + ' mV';
  document.getElementById('sysMv').textContent = (d.sys_mv ?? 0) + ' mV';
  document.getElementById('charging').textContent = Number(d.charging) === 1 ? '充電中' : '停止';
  document.getElementById('csq').textContent = d.csq ?? -1;
  document.getElementById('intervalNow').textContent = (d.interval_min ?? '-') + ' 分';
}

async function reloadRecent() {
  const r = await fetch('api_list_recent.php?t=' + Date.now(), {cache:'no-store'});
  if (!r.ok) throw new Error('recent load error');
  const list = await r.json();

  const grid = document.getElementById('recentGrid');
  grid.innerHTML = '';

  const items = Array.isArray(list) ? list.slice(0, 4) : [];
  for (const item of items) {
    const wrap = document.createElement('div');
    wrap.className = 'recent-item';

    const box = document.createElement('div');
    box.className = 'recent-image-box';

    const img = document.createElement('img');
    img.className = 'recent-image';
    img.src = item.url + '?t=' + Date.now();
    img.alt = item.name || '';
    img.title = item.name || '';
    img.onclick = () => {
      document.getElementById('mainImage').src = item.url + '?t=' + Date.now();
      document.getElementById('fileName').textContent = item.name || '';
    };

    const name = document.createElement('div');
    name.className = 'recent-name';
    name.textContent = item.name || '';

    box.appendChild(img);
    wrap.appendChild(box);
    wrap.appendChild(name);
    grid.appendChild(wrap);
  }
}

async function saveInterval() {
  const v = document.getElementById('intervalMin').value;
  const p = document.getElementById('adminPassword').value;

  const fd = new FormData();
  fd.append('interval_min', v);
  fd.append('admin_password', p);

  const status = document.getElementById('saveStatus');
  status.textContent = '保存中...';

  const r = await fetch('save_interval.php', { method:'POST', body:fd });
  const text = await r.text();

  if (r.ok) {
    status.textContent = text;
    document.getElementById('intervalNow').textContent = Number(v) + ' 分';
    document.getElementById('adminPassword').value = '';
  } else {
    status.textContent = '保存失敗: ' + text;
  }
}

async function reloadAll() {
  try {
    await reloadLatest();
    await reloadRecent();
  } catch (e) {
    console.error(e);
  }
}

reloadAll();
setInterval(reloadAll, 60000);
</script>
</body>
</html>
