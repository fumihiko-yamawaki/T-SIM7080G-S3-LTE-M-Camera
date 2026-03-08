<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

$selected = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected)) {
    $selected = date('Y-m-d');
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>高須棚田の風景 - 過去画像</title>
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
      font-size: 32px;
      font-weight: 700;
    }

    .header-sub {
      font-size: 13px;
      line-height: 1.5;
      text-align: right;
      opacity: 0.96;
    }

    .layout {
      display: grid;
      grid-template-rows: 82px 704px;
      gap: 10px;
      height: calc(900px - 68px - 10px - 24px);
    }

    .card {
      background: #fff;
      border: 1px solid #d8e0e6;
      border-radius: 12px;
      padding: 12px;
      box-sizing: border-box;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    }

    .controls {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .list-card {
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .card-title {
      font-size: 20px;
      font-weight: 700;
      color: #234a23;
      margin-bottom: 8px;
      line-height: 1.2;
    }

    .list-grid {
      flex: 1;
      overflow-y: auto;
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      grid-auto-rows: max-content;
      gap: 10px;
      padding-right: 4px;
      align-content: start;
    }

    .item {
      border: 1px solid #d7dfe7;
      border-radius: 8px;
      padding: 6px;
      background: #fff;
      box-sizing: border-box;
    }

    .item-img-box {
      width: 100%;
      aspect-ratio: 4 / 3;
      background: #1f252b;
      border-radius: 6px;
      overflow: hidden;
    }

    .item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      cursor: pointer;
    }

    .item-time {
      margin-top: 5px;
      font-size: 13px;
      font-weight: 700;
      color: #234a23;
      line-height: 1.3;
    }

    .item-name {
      margin-top: 2px;
      font-size: 11px;
      line-height: 1.3;
      color: #475569;
      word-break: break-all;
    }

    input[type="date"] {
      height: 38px;
      padding: 0 10px;
      font-size: 16px;
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

    .note {
      font-size: 13px;
      color: #4b5563;
    }

    .empty {
      font-size: 15px;
      color: #475569;
      padding: 20px 0;
    }

    .mode-label {
      font-size: 13px;
      color: #234a23;
      font-weight: 700;
    }

    @media (max-width: 1750px) {
      .list-grid {
        grid-template-columns: repeat(5, 1fr);
      }
    }

    @media (max-width: 1550px) {
      .list-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }
  </style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="header-title">高須棚田の風景 - 過去画像</div>
    <div class="header-sub">
      標準は1時間に1枚表示<br>
      必要時のみ全画像や週単位表示に切替
    </div>
  </div>

  <div class="layout">
    <div class="card">
      <div class="controls">
        <a class="btn-link btn-sub" href="index.php">メインページへ戻る</a>

        <label for="dateSel">日付</label>
        <input type="date" id="dateSel" value="<?php echo htmlspecialchars($selected); ?>">

        <button type="button" onclick="loadByDate('hourly')">1時間1枚</button>
        <button type="button" onclick="loadByDate('weekly')">7日に1枚</button>
        <button class="btn-sub" type="button" onclick="loadByDate('all')">全画像</button>

        <div class="mode-label" id="modeLabel">表示モード: 1時間1枚</div>
        <div class="note">画像をクリックすると別タブで表示します。</div>
      </div>
    </div>

    <div class="card list-card">
      <div class="card-title">画像一覧</div>
      <div id="list" class="list-grid"></div>
    </div>
  </div>
</div>

<script>
function extractTimeFromName(name) {
  const m = name.match(/_(\d{8})_(\d{6})\.jpg$/i);
  if (!m) return '';
  const hh = m[2].substring(0, 2);
  const mm = m[2].substring(2, 4);
  const ss = m[2].substring(4, 6);
  return hh + ':' + mm + ':' + ss;
}

async function loadByDate(mode = 'hourly') {
  const d = document.getElementById('dateSel').value;
  const r = await fetch(
    'api_list_by_date.php?date=' + encodeURIComponent(d) +
    '&mode=' + encodeURIComponent(mode) +
    '&t=' + Date.now(),
    {cache:'no-store'}
  );

  if (!r.ok) {
    console.error('HTTP error', r.status);
    return;
  }

  const list = await r.json();

  document.getElementById('modeLabel').textContent =
    mode === 'all'
      ? '表示モード: 全画像'
      : (mode === 'weekly' ? '表示モード: 7日に1枚' : '表示モード: 1時間1枚');

  const area = document.getElementById('list');
  area.innerHTML = '';

  if (!Array.isArray(list) || list.length === 0) {
    const div = document.createElement('div');
    div.className = 'empty';
    div.textContent = 'この日の画像はありません。';
    area.appendChild(div);
    return;
  }

  for (const item of list) {
    const box = document.createElement('div');
    box.className = 'item';

    const imgBox = document.createElement('div');
    imgBox.className = 'item-img-box';

    const img = document.createElement('img');
    img.src = item.url + '?t=' + Date.now();
    img.alt = item.name;
    img.title = item.name;
    img.onclick = () => window.open(item.url, '_blank');

    imgBox.appendChild(img);

    const time = document.createElement('div');
    time.className = 'item-time';
    time.textContent = extractTimeFromName(item.name) || item.time || '';

    const name = document.createElement('div');
    name.className = 'item-name';
    name.textContent = item.name;

    box.appendChild(imgBox);
    box.appendChild(time);
    box.appendChild(name);
    area.appendChild(box);
  }
}

// 初期表示は 1時間1枚
loadByDate('hourly');
</script>
</body>
</html>
