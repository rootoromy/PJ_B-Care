<?php
/**
 * B-Care Mobile - 患者ホーム画面
 * 配置先: mobile/sp_patient_home.php
 * 患者基本情報(ふりがな/氏名/年齢/性別/病棟/ベッド)は patients テーブル、
 * ピクトグラムは pictograms / patient_pictograms テーブル、
 * バイタルの最新値は vitals テーブルから取得します。
 *
 * 画面内の各セクションは mobile/includes/blocks/block_*.php に分割されており、
 * mobile/config/display_items.json（病院ごとに用意、.gitignore対象）で
 * 表示するブロックと並び順をカスタマイズできます。
 * 設定例: mobile/config/display_items.json.example
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';
sp_require_login();

require_once __DIR__ . '/../includes/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    header('Location: sp_patient_list.php');
    exit;
}

$mysqli = getDB();

$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    $patient = [
        'patient_name' => '',
        'patient_kana' => '',
        'age'          => '',
        'gender'       => '',
        'ward_name'    => '',
        'room_no'      => '',
        'bed_no'       => '',
        'fall_risk'    => 0,
        'transfer_type'=> '',
    ];
}

$stmt_dup = $mysqli->prepare("
    SELECT COUNT(*) AS cnt
    FROM patients
    WHERE patient_name = ? AND patient_id != ?
");
$stmt_dup->bind_param('ss', $patient['patient_name'], $patient_id);
$stmt_dup->execute();
$dup_count = (int)($stmt_dup->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_dup->close();

$stmt_pic = $mysqli->prepare("
    SELECT p.name, p.image_path
    FROM patient_pictograms pp
    JOIN pictograms p ON pp.pictogram_id = p.pictogram_id
    WHERE pp.patient_id = ?
    ORDER BY pp.display_order
");
$stmt_pic->bind_param('s', $patient_id);
$stmt_pic->execute();
$pictograms = $stmt_pic->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pic->close();

$stmt_vital = $mysqli->prepare("
    SELECT measured_at, temperature, systolic_bp, diastolic_bp, pulse, spo2, respiratory_rate
    FROM vitals
    WHERE patient_id = ?
    ORDER BY measured_at DESC
    LIMIT 1
");
$stmt_vital->bind_param('s', $patient_id);
$stmt_vital->execute();
$latest_vital = $stmt_vital->get_result()->fetch_assoc();
$stmt_vital->close();

$stmt_deposit = $mysqli->prepare("
    SELECT item_name, status
    FROM patient_deposits
    WHERE patient_id = ? AND status = 'stored'
    ORDER BY stored_at DESC
");
$stmt_deposit->bind_param('s', $patient_id);
$stmt_deposit->execute();
$deposit_items = $stmt_deposit->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_deposit->close();

// 今日・明日の予定は、電カルから一括同期される想定の patient_schedule を
// 実際の日時（scheduled_at）で絞り込んで取得する（vitals と同じ考え方）
$today_date    = date('Y-m-d');
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));

$stmt_schedule = $mysqli->prepare("
    SELECT scheduled_at, category, content
    FROM patient_schedule
    WHERE patient_id = ? AND DATE(scheduled_at) = ?
    ORDER BY scheduled_at
");
$stmt_schedule->bind_param('ss', $patient_id, $today_date);
$stmt_schedule->execute();
$schedule_today = $stmt_schedule->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_schedule->close();

$stmt_schedule_tomorrow = $mysqli->prepare("
    SELECT scheduled_at, category, content
    FROM patient_schedule
    WHERE patient_id = ? AND DATE(scheduled_at) = ?
    ORDER BY scheduled_at
");
$stmt_schedule_tomorrow->bind_param('ss', $patient_id, $tomorrow_date);
$stmt_schedule_tomorrow->execute();
$schedule_tomorrow = $stmt_schedule_tomorrow->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_schedule_tomorrow->close();

$mysqli->close();

function vitalValue($latest_vital, $field) {
    if (!$latest_vital || $latest_vital[$field] === null || $latest_vital[$field] === '') {
        return null;
    }
    return $latest_vital[$field];
}

// ---------------------------------------------------
// 表示ブロックの構成（病院ごとのJSON設定で表示/非表示・並び順を変更可能）
// ---------------------------------------------------
// デフォルトの並び順（config/display_items.json 自体が無い場合のみ、
// この順序のまま全ブロックを表示するフェイルセーフとして使う）
$default_block_order = [
    'info',
    'pictogram',
    'risk',
    'vitals',
    'schedule_today',
    'schedule_tomorrow',
    'deposit',
];

/**
 * config/display_items.json の設定を反映したブロック表示順を返す。
 * - JSON の配列に書かれているブロックキーだけを、その並び順で表示する
 *   （配列に書かれていないブロックはデフォルト非表示）
 * - JSON 設定ファイル自体が無い/壊れている場合は、フェイルセーフとして
 *   $default_order のブロックを全て表示する（未設定時に画面が空にならないように）
 */
function resolveBlockOrder(string $config_key, array $default_order): array {
    $config_file = __DIR__ . '/config/display_items.json';

    if (!is_file($config_file)) {
        return $default_order;
    }

    $json = json_decode((string)file_get_contents($config_file), true);
    if (!is_array($json) || !isset($json[$config_key]) || !is_array($json[$config_key])) {
        return $default_order;
    }

    // 未知のキー（ブロックとして存在しないもの）は無視し、既知のブロックのみ残す
    return array_values(array_intersect($json[$config_key], $default_order));
}

$block_order = resolveBlockOrder('patient_home_blocks', $default_block_order);

$active_menu = 'home';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜患者ホーム</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=31">
  <link rel="stylesheet" href="css/sp_patient_home.css?v=52">
</head>
<body>
  <!-- SVG icon sprite（外部ライブラリ不要） -->
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-stethoscope" viewBox="0 0 24 24"><path d="M6 3v6a5 5 0 0 0 10 0V3M4 3h4M14 3h4M16 12v2a4 4 0 0 0 8 0v-1"/><circle cx="21" cy="10" r="2"/></symbol>
    <symbol id="i-nurse" viewBox="0 0 24 24"><path d="M8 4h8l1 3H7zM9 7v2a3 3 0 0 0 6 0V7M5 21c.8-4.7 3.2-7 7-7s6.2 2.3 7 7"/><path d="M11 5h2M12 4v2"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><path d="M4 21V4h12v17M16 10h4v11M8 8h2M8 12h2M8 16h2M13 8h1M13 12h1M13 16h1M2 21h20"/></symbol>
    <symbol id="i-bed" viewBox="0 0 24 24"><path d="M3 18V6M3 14h18v4M7 14v-3h5a3 3 0 0 1 3 3M3 18v3M21 18v3"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 10a7 7 0 0 0-12-2M6 14a7 7 0 0 0 12 2"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <header class="page-header">
      <p class="breadcrumb"><a href="sp_patient_list.php"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
      <div class="page-title-row">
        <h1>患者個別</h1>
        <a class="page-refresh-btn" href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>">
          <svg><use href="#i-refresh"></use></svg>
          更新
        </a>
      </div>
    </header>

    <main>
      <?php foreach ($block_order as $block_key): ?>
        <?php include __DIR__ . '/includes/blocks/block_' . $block_key . '.php'; ?>
      <?php endforeach; ?>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_patient_home.js?v=3"></script>
</body>
</html>
