<?php
/**
 * B-Care Mobile - バイタル画面
 * 配置先: mobile/sp_vitals.php
 *
 * 1日分のバイタル推移をグラフと表で表示する。スマホ縦画面（iPhone 14相当の
 * 390px幅）を基準にしたレイアウト。共通の .phone-shell / sp_header.php /
 * sp_drawer.php を患者ホーム画面と共通利用する。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------
// パラメータ取得
// ---------------------------------------------------
$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    header('Location: sp_patient_home.php');
    exit;
}

$date_param = isset($_GET['date']) ? trim($_GET['date']) : '';
if ($date_param !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_param)) {
    $target_date = $date_param;
} else {
    $target_date = date('Y-m-d');
}

$mysqli = getDB();

// ---------------------------------------------------
// 患者情報取得
// ---------------------------------------------------
$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('<p style="color:red;">患者が見つかりません。</p>');
}

// ---------------------------------------------------
// 同姓同名チェック
// ---------------------------------------------------
$stmt_dup = $mysqli->prepare("
    SELECT COUNT(*) AS cnt
    FROM patients
    WHERE patient_name = ? AND patient_id != ?
");
$stmt_dup->bind_param('ss', $patient['patient_name'], $patient_id);
$stmt_dup->execute();
$dup_count = (int)($stmt_dup->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_dup->close();

// ---------------------------------------------------
// バイタル取得（指定日）
// ---------------------------------------------------
$stmt_v = $mysqli->prepare("
    SELECT measured_at, temperature, systolic_bp, diastolic_bp, pulse, spo2
    FROM vitals
    WHERE patient_id = ? AND DATE(measured_at) = ?
    ORDER BY measured_at
");
$stmt_v->bind_param('ss', $patient_id, $target_date);
$stmt_v->execute();
$vital_rows = $stmt_v->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_v->close();

$mysqli->close();

// ---------------------------------------------------
// 3時間おきの固定枠（0/3/6/9/12/15/18/21時）にマッピング
// 24:00は見た目上の右端ラベルのみで、実データは対応させない
// ---------------------------------------------------
$slot_times  = ['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00'];
$slot_labels = ['0:00', '3:00', '6:00', '9:00', '12:00', '15:00', '18:00', '21:00', '24:00'];
$slot_map    = array_fill_keys($slot_times, null);

foreach ($vital_rows as $row) {
    $hm = date('H:i', strtotime($row['measured_at']));
    if (array_key_exists($hm, $slot_map)) {
        $slot_map[$hm] = $row;
    }
}

function pickVals(array $slot_map, string $field): array {
    $out = [];
    foreach ($slot_map as $row) {
        $out[] = ($row !== null && $row[$field] !== null && $row[$field] !== '')
            ? (float)$row[$field]
            : null;
    }
    $out[] = null; // 24:00（右端の見た目上の枠。実データは対応させない）
    return $out;
}

$vital_data = [
    'labels'      => $slot_labels,
    'temperature' => pickVals($slot_map, 'temperature'),
    'systolic'    => pickVals($slot_map, 'systolic_bp'),
    'diastolic'   => pickVals($slot_map, 'diastolic_bp'),
    'pulse'       => pickVals($slot_map, 'pulse'),
    'spo2'        => pickVals($slot_map, 'spo2'),
];

// ---------------------------------------------------
// 最終更新（その日の最新の測定時刻）
// ---------------------------------------------------
$last_updated = null;
foreach ($vital_rows as $row) {
    if ($last_updated === null || strtotime($row['measured_at']) > strtotime($last_updated)) {
        $last_updated = $row['measured_at'];
    }
}

// ---------------------------------------------------
// 日付表示・前日/翌日リンク
// ---------------------------------------------------
$dt = DateTime::createFromFormat('Y-m-d', $target_date);
$prev_date = (clone $dt)->modify('-1 day')->format('Y-m-d');
$next_date = (clone $dt)->modify('+1 day')->format('Y-m-d');
$weekdays_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_display = $dt->format('Y/m/d') . '（' . $weekdays_jp[(int)$dt->format('w')] . '）';

function buildDateQs($patient_id, $date) {
    return '?' . http_build_query(['patient_id' => $patient_id, 'date' => $date]);
}

$active_menu = 'vitals';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>B-Care Mobile｜バイタル</title>
  <link rel="stylesheet" href="css/sp_common.css?v=10" />
  <link rel="stylesheet" href="css/sp_vitals.css?v=14" />
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 20c.8-4.2 3.2-6 7-6s6.2 1.8 7 6"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 10a7 7 0 0 0-12-2M6 14a7 7 0 0 0 12 2"/></symbol>
    <symbol id="i-thermometer" viewBox="0 0 24 24"><path d="M12 14.5V5a2 2 0 1 0-4 0v9.5a4 4 0 1 0 4 0z"/><path d="M10 8h1"/></symbol>
    <symbol id="i-bp" viewBox="0 0 24 24"><circle cx="12" cy="13" r="7"/><path d="M12 13l3.5-3.5M9 5h6"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 21s-7-4.35-9.5-8.5C1 9 2.5 5 6 5c2 0 3.5 1.5 4 3 .5-1.5 2-3 4-3 3.5 0 5 4 3.5 7.5C19 16.65 12 21 12 21z"/></symbol>
    <symbol id="i-drop" viewBox="0 0 24 24"><path d="M12 3c4 5 7 8.5 7 12a7 7 0 0 1-14 0c0-3.5 3-7 7-12z"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="vitals-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>">HOME</a> &gt; バイタル</p>
        <h1>バイタル</h1>

        <div class="date-controls" aria-label="日付切り替え">
          <a class="date-nav date-nav--prev" href="sp_vitals.php<?= buildDateQs($patient_id, $prev_date) ?>" aria-label="前日">
            <svg><use href="#i-chevron"></use></svg>
          </a>
          <span class="date-display"><?= h($date_display) ?></span>
          <a class="date-nav" href="sp_vitals.php<?= buildDateQs($patient_id, $next_date) ?>" aria-label="翌日">
            <svg><use href="#i-chevron"></use></svg>
          </a>
        </div>
      </header>

      <section class="vital-card" aria-labelledby="vitalTitle">
        <div class="card-topbar">
          <div>
            <span class="eyebrow">1日表示</span>
            <h2 id="vitalTitle">バイタル推移</h2>
          </div>

          <div class="update-area">
            <span>最終更新：<?= $last_updated ? h(date('Y/m/d H:i', strtotime($last_updated))) : '記録なし' ?></span>
            <a class="refresh-button" href="sp_vitals.php<?= buildDateQs($patient_id, $target_date) ?>">
              <svg><use href="#i-refresh"></use></svg>
              更新
            </a>
          </div>
        </div>

        <div class="metric-tabs" role="tablist" aria-label="表示するバイタル">
          <button class="metric-tab is-active" type="button" data-series="temperature">
            <span class="metric-icon"><svg><use href="#i-thermometer"></use></svg></span>
            <span><strong>体温</strong><small>℃</small></span>
          </button>
          <button class="metric-tab is-active" type="button" data-series="systolic,diastolic">
            <span class="metric-icon"><svg><use href="#i-bp"></use></svg></span>
            <span><strong>血圧（上/下）</strong><small>mmHg</small></span>
          </button>
          <button class="metric-tab is-active" type="button" data-series="pulse">
            <span class="metric-icon"><svg><use href="#i-heart"></use></svg></span>
            <span><strong>脈拍</strong><small>回/分</small></span>
          </button>
          <button class="metric-tab is-active" type="button" data-series="spo2">
            <span class="metric-icon"><svg><use href="#i-drop"></use></svg></span>
            <span><strong>SpO₂</strong><small>%</small></span>
          </button>
        </div>

        <div class="chart-wrap">
          <svg id="vitalChart" class="vital-chart" role="img" aria-labelledby="chartTitle chartDesc">
            <title id="chartTitle">1日分のバイタル推移</title>
            <desc id="chartDesc">0時から24時までの体温、血圧、脈拍、SpO2の推移を示します。</desc>
          </svg>
        </div>

        <div class="table-wrap">
          <table class="vital-table">
            <thead>
              <tr>
                <th scope="col">時刻</th>
                <?php foreach ($slot_labels as $label): ?>
                  <th scope="col"><?= h($label) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody id="vitalTableBody"></tbody>
          </table>
        </div>

        <p class="chart-note">※ グラフ上の数値は測定値を表示しています。</p>
      </section>
    </main>
  </div>

  <script>
    window.vitalData = <?= json_encode($vital_data, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_vitals.js?v=5"></script>
</body>
</html>
