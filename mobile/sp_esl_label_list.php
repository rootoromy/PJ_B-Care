<?php
/**
 * B-Care Mobile - ラベル一覧画面
 * 配置先: mobile/sp_esl_label_list.php
 *
 * ESL（電子棚札）ラベルを軸に、患者への割当状況を一覧・絞り込みできる画面。
 * 患者を軸にした sp_esl_label.php（患者ごとのラベル割当）とは逆方向の入口だが、
 * どちらも includes/aims_functions.php の共通関数（assignEslLabelToPatient /
 * unassignEslLabelFromPatient）を使って処理するため、結果は常に一致する。
 * 実際の割当・解除処理は sp_esl_label_process.php に POST して行う。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';
sp_require_login();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$assign_message = $_GET['assign'] ?? '';

$mysqli = getDB();

$wards = $mysqli->query("
    SELECT DISTINCT ward_name FROM patients
    WHERE is_admitted = 1 AND ward_name IS NOT NULL AND ward_name <> ''
    ORDER BY ward_name
")->fetch_all(MYSQLI_ASSOC);

$patientsByLabel = [];
$resPatients = $mysqli->query("
    SELECT patient_id, patient_name, ward_name, room_no, bed_no, esl_label_code
    FROM patients
    WHERE esl_label_code IS NOT NULL AND esl_label_code <> ''
");
while ($row = $resPatients->fetch_assoc()) {
    $patientsByLabel[$row['esl_label_code']] = $row;
}

// ラベルを持たない患者（＝これから割り当てる先の候補）。「患者を選択」の選択肢に使う。
// ward_name も持たせておき、「病棟で絞り込み」と連動して選択肢を絞り込めるようにする。
$unassignedPatients = [];
$resUnassigned = $mysqli->query("
    SELECT patient_id, patient_name, ward_name
    FROM patients
    WHERE (esl_label_code IS NULL OR esl_label_code = '') AND is_admitted = 1
    ORDER BY patient_id
");
while ($row = $resUnassigned->fetch_assoc()) {
    $unassignedPatients[] = $row;
}

$hiddenLabelCodes = [];
$resHidden = $mysqli->query("SELECT label_code FROM esl_hidden_labels");
while ($row = $resHidden->fetch_assoc()) {
    $hiddenLabelCodes[$row['label_code']] = true;
}

$mysqli->close();

// ---------------------------------------------------
// AIMSからラベル一覧を取得し、患者情報と突き合わせる
// ---------------------------------------------------
$labelsResult = getAimsLabels();
$aims_error = '';
$labels = [];
if ($labelsResult['error'] !== '' || $labelsResult['httpCode'] < 200 || $labelsResult['httpCode'] >= 300) {
    $aims_error = 'AIMSサーバーに接続できませんでした（' . ($labelsResult['error'] ?: 'HTTP ' . $labelsResult['httpCode']) . '）';
} else {
    $labels = $labelsResult['body'] ?? [];
}

$labelRows = [];
foreach ($labels as $label) {
    $code = $label['labelCode'] ?? '';
    if ($code === '' || isset($hiddenLabelCodes[$code])) {
        continue;
    }

    $patient = $patientsByLabel[$code] ?? null;
    $labelRows[] = [
        'code'          => $code,
        'assigned'      => $patient !== null,
        'patient_id'    => $patient['patient_id'] ?? '',
        'patient_name'  => $patient['patient_name'] ?? '',
        'ward_name'     => $patient['ward_name'] ?? '',
        'room_no'       => $patient['room_no'] ?? '',
        'bed_no'        => $patient['bed_no'] ?? '',
    ];
}

$labelRows_json = json_encode($labelRows, JSON_UNESCAPED_UNICODE);

$active_menu = 'esl_label_list';
$patient_id  = '';
$page_title  = 'B-Care Mobile';
$last_updated = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜ラベル一覧</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=32">
  <link rel="stylesheet" href="css/sp_esl_label_list.css?v=2">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 10a7 7 0 0 0-12-2M6 14a7 7 0 0 0 12 2"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_simple_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="esl-list-main">
      <header class="page-header">
        <div class="list-header-row">
          <div class="list-header-main">
            <h1>ラベル一覧</h1>
            <p class="list-desc">ESL（電子棚札）に患者を割り当てることができます。</p>
          </div>
          <div class="list-header-side">
            <a class="page-refresh-btn" href="sp_esl_label_list.php">
              <svg><use href="#i-refresh"></use></svg>
              更新
            </a>
            <p class="list-updated">最終更新 <?= h($last_updated) ?></p>
          </div>
        </div>
      </header>

      <?php if ($assign_message === 'success'): ?>
        <section class="discharge-notice discharge-success">ESLラベルを割り当て、配信しました。</section>
      <?php elseif ($assign_message === 'deliver_error'): ?>
        <section class="discharge-notice">ラベルの割り当ては保存しましたが、AIMSへの配信に失敗しました。時間をおいて再度お試しください。</section>
      <?php elseif ($assign_message === 'unassigned'): ?>
        <section class="discharge-notice discharge-success">割り当てを解除しました。</section>
      <?php elseif ($assign_message === 'error'): ?>
        <section class="discharge-notice">割り当てに失敗しました。対象のラベルが既に他の患者へ割り当て済みの可能性があります。</section>
      <?php endif; ?>

      <?php if ($aims_error !== ''): ?>
        <section class="discharge-notice"><?= h($aims_error) ?></section>
      <?php endif; ?>

      <section class="card filter-card" aria-label="絞り込み">
        <div class="filter-field">
          <label for="wardFilter">病棟で絞り込み</label>
          <div class="select-wrap">
            <select id="wardFilter">
              <option value="">すべての病棟</option>
              <?php foreach ($wards as $w): ?>
                <option value="<?= h($w['ward_name']) ?>"><?= h($w['ward_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="filter-field">
          <label for="patientSearchInput">患者を検索</label>
          <div class="esl-search-wrap">
            <svg class="esl-search-icon"><use href="#i-search"></use></svg>
            <input id="patientSearchInput" type="search" placeholder="患者ID・氏名で検索" autocomplete="off">
          </div>
        </div>

        <div class="filter-field">
          <label for="patientSelect">患者を選択</label>
          <div class="select-wrap select-wrap--icon">
            <svg class="select-icon"><use href="#i-user"></use></svg>
            <select id="patientSelect">
              <option value="">患者を選択してください</option>
              <?php foreach ($unassignedPatients as $p): ?>
                <option value="<?= h($p['patient_id']) ?>"><?= h($p['patient_id']) ?>　<?= h($p['patient_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="filter-hint">未割当の患者のみ表示しています。「割当」ボタンはここで選んだ患者に割り当てます。</p>
        </div>

      </section>

      <section class="card list-section">
        <div class="section-title collapsible" data-target="assignedBody">
          <span>割当済み<span class="section-count">（<span id="assignedCount">0</span>件）</span></span>
          <svg class="chevron-icon"><use href="#i-chevron"></use></svg>
        </div>
        <div id="assignedBody" class="section-body">
          <div id="assignedRowList" class="esl-row-list"></div>
          <p id="assignedEmptyMessage" class="esl-empty-text" hidden>該当するラベルがありません</p>
        </div>
      </section>

      <section class="card list-section">
        <div class="section-title collapsible" data-target="unassignedBody">
          <span>未割当<span class="section-count">（<span id="unassignedCount">0</span>件）</span></span>
          <svg class="chevron-icon"><use href="#i-chevron"></use></svg>
        </div>
        <div id="unassignedBody" class="section-body">
          <div id="unassignedRowList" class="esl-row-list"></div>
          <p id="unassignedEmptyMessage" class="esl-empty-text" hidden>該当するラベルがありません</p>
        </div>
      </section>
    </main>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>
  </div>

  <form id="listAssignForm" method="POST" action="sp_esl_label_process.php" hidden>
    <input type="hidden" name="assign_label" value="1">
    <input type="hidden" name="redirect" value="list">
    <input type="hidden" name="patient_id" value="">
    <input type="hidden" name="label_code" value="">
  </form>
  <form id="listUnassignForm" method="POST" action="sp_esl_label_process.php" hidden>
    <input type="hidden" name="unassign_label" value="1">
    <input type="hidden" name="redirect" value="list">
    <input type="hidden" name="patient_id" value="">
  </form>

  <script>
    window.ESL_LABEL_ROWS = <?= $labelRows_json ?>;
    window.ESL_UNASSIGNED_PATIENTS = <?= json_encode($unassignedPatients, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_esl_label_list.js?v=5"></script>
</body>
</html>
