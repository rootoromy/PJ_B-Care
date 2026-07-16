<?php
/**
 * B-Care Mobile - バイタル画面
 * 配置先: sp_vitals.php
 */

require_once __DIR__ . '/../includes/config.php';

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
    SELECT measured_at, temperature, systolic_bp, diastolic_bp, pulse, spo2, respiratory_rate
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
// 固定枠（6/9/12/15/18時）にマッピング
// ---------------------------------------------------
$slot_times = ['06:00', '09:00', '12:00', '15:00', '18:00'];
$slot_labels = ['6:00', '9:00', '12:00', '15:00', '18:00'];
$slot_map = array_fill_keys($slot_times, null);

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
    return $out;
}

$vital_defs = [
    ['key' => 'temp',  'label' => '体温',       'unit' => '℃',     'field' => 'temperature',   'min' => 35, 'max' => 40,  'css' => 'value-temp',  'color' => '#e47537'],
    ['key' => 'sys',   'label' => '血圧（上）', 'unit' => 'mmHg',  'field' => 'systolic_bp',   'min' => 80, 'max' => 180, 'css' => 'value-sys',   'color' => '#3478d1'],
    ['key' => 'dia',   'label' => '血圧（下）', 'unit' => 'mmHg',  'field' => 'diastolic_bp',  'min' => 40, 'max' => 110, 'css' => 'value-dia',   'color' => '#39a0ca'],
    ['key' => 'pulse', 'label' => '脈拍',       'unit' => '回/分', 'field' => 'pulse',         'min' => 40, 'max' => 130, 'css' => 'value-pulse', 'color' => '#3f8d4d'],
    ['key' => 'spo2',  'label' => 'SpO₂',      'unit' => '%',     'field' => 'spo2',          'min' => 85, 'max' => 100, 'css' => 'value-spo2',  'color' => '#d54f4f'],
];

$chart_datasets = [];
foreach ($vital_defs as $def) {
    $chart_datasets[] = [
        'key'   => $def['key'],
        'label' => $def['label'],
        'unit'  => $def['unit'],
        'values'=> pickVals($slot_map, $def['field']),
        'min'   => $def['min'],
        'max'   => $def['max'],
        'css'   => $def['css'],
        'color' => $def['color'],
    ];
}

// ---------------------------------------------------
// グラフ左側の基準値表（横線と同じ本数で min→max を分割）
// ---------------------------------------------------
$scale_row_count = 6; // drawChart() の横線本数(i=0..5)と合わせる
$scale_rows = [];
for ($i = 0; $i < $scale_row_count; $i++) {
    $ratio = ($scale_row_count > 1) ? $i / ($scale_row_count - 1) : 0;
    $row = [];
    foreach ($vital_defs as $def) {
        $val = $def['max'] - ($def['max'] - $def['min']) * $ratio;
        $row[$def['key']] = ($def['key'] === 'temp')
            ? number_format($val, 1)
            : (string)(int)round($val);
    }
    $scale_rows[] = $row;
}

$gender = getGenderStyle($patient['gender'] ?? '');

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>B-Care Mobile｜バイタル</title>
  <link rel="stylesheet" href="css/sp_common.css?v=1" />
  <link rel="stylesheet" href="css/sp_vitals.css?v=1" />
</head>
<body>
  <div class="app-shell vitals-shell">
    <aside class="patient-panel" aria-label="患者情報">
      <button class="vitals-menu-button" type="button" aria-label="メニューを開く" id="menuButton">
        <span></span><span></span><span></span>
      </button>

      <div class="patient-block">
        <p class="patient-kana"><?= htmlspecialchars($patient['patient_kana'] ?? '') ?></p>
        <h1><?= htmlspecialchars($patient['patient_name']) ?><span>様</span></h1>
        <div class="patient-meta">
          <strong><?= htmlspecialchars($patient['age'] ?? '-') ?>歳</strong>
          <span style="color:<?= $gender['color'] ?>; font-weight:700;">
            <?= $gender['icon'] ?> <?= htmlspecialchars($patient['gender'] ?? '-') ?>
          </span>
        </div>
        <?php if ($dup_count > 0): ?>
          <span class="duplicate-badge">同姓同名あり</span>
        <?php endif; ?>
      </div>

      <div class="login-user">
        <span class="user-icon" aria-hidden="true">●</span>
        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'ナース') ?></span>
      </div>
    </aside>

    <main class="main-content">
      <header class="content-header">
        <div>
          <p class="breadcrumb">HOME &gt; バイタル</p>
          <h2>バイタル</h2>
        </div>

        <div class="date-controls" aria-label="日付切り替え">
          <a href="sp_vitals.php<?= buildDateQs($patient_id, $prev_date) ?>" role="button" aria-label="前日">‹</a>
          <span class="date-display" id="dateDisplay"><?= htmlspecialchars($date_display) ?></span>
          <a href="sp_vitals.php<?= buildDateQs($patient_id, $next_date) ?>" role="button" aria-label="翌日">›</a>
        </div>
      </header>

      <section class="vital-card" aria-labelledby="vitalTitle">
        <div class="card-head">
          <div>
            <p class="eyebrow">1日表示</p>
            <h3 id="vitalTitle">バイタル推移</h3>
          </div>
          <div class="legend" aria-label="グラフ凡例">
            <span><i class="line temp"></i>体温</span>
            <span><i class="line sys"></i>血圧（上）</span>
            <span><i class="line dia"></i>血圧（下）</span>
            <span><i class="line pulse"></i>脈拍</span>
            <span><i class="line spo2"></i>SpO₂</span>
          </div>
        </div>

        <div class="chart-wrap">
          <div class="chart-scale" aria-hidden="true">
            <div class="scale-rows">
              <?php foreach ($scale_rows as $row): ?>
                <div class="scale-row">
                  <span class="value-temp"><?= htmlspecialchars($row['temp']) ?></span>
                  <span class="value-sys"><?= htmlspecialchars($row['sys']) ?></span>
                  <span class="value-dia"><?= htmlspecialchars($row['dia']) ?></span>
                  <span class="value-pulse"><?= htmlspecialchars($row['pulse']) ?></span>
                  <span class="value-spo2"><?= htmlspecialchars($row['spo2']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="chart-canvas-area">
            <canvas id="vitalChart" aria-label="6時から18時までのバイタルグラフ"></canvas>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th scope="col">項目</th>
                <?php foreach ($slot_labels as $label): ?>
                  <th scope="col"><?= htmlspecialchars($label) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody id="vitalTableBody">
              <?php foreach ($chart_datasets as $ds): ?>
                <tr>
                  <th scope="row" class="<?= $ds['css'] ?>">
                    <?= htmlspecialchars($ds['label']) ?><small> <?= htmlspecialchars($ds['unit']) ?></small>
                  </th>
                  <?php foreach ($ds['values'] as $v): ?>
                    <td class="<?= $ds['css'] ?>"><?= $v === null ? '－' : htmlspecialchars((string)$v) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <div class="vitals-drawer-backdrop" id="drawerBackdrop" hidden></div>
  <nav class="vitals-drawer" id="drawer" aria-label="メインメニュー" aria-hidden="true" inert>
    <button type="button" class="vitals-drawer-close" id="drawerClose" aria-label="メニューを閉じる">×</button>
    <a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>">患者情報</a>
    <a href="#">申し送り</a>
    <a href="#">預かり品</a>
    <a href="#">今日の予定</a>
    <a href="#">明日の予定</a>
    <a href="sp_vitals.php<?= buildDateQs($patient_id, $target_date) ?>" class="active">バイタル</a>
    <a href="#">入力履歴</a>
    <a href="#">ログアウト</a>
  </nav>

  <script>
    window.vitalData = {
      times: <?= json_encode($slot_labels, JSON_UNESCAPED_UNICODE) ?>,
      datasets: <?= json_encode($chart_datasets, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="js/sp_vitals.js?v=1"></script>
</body>
</html>
