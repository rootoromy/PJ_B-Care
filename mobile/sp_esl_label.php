<?php
/**
 * B-Care Mobile - ラベル割当画面
 * 配置先: mobile/sp_esl_label.php
 *
 * 患者に対するESL（電子棚札）ラベルの割当・解除をMobileから行う画面。
 * B-Care Manager側の manager/esl_management.php と同じ、
 * includes/aims_functions.php の共通関数（assignEslLabelToPatient /
 * unassignEslLabelFromPatient）を使って処理するため、Manager/Mobile
 * どちらから操作しても同じ結果になる。
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

$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    header('Location: sp_patient_home.php');
    exit;
}

$mysqli = getDB();

$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('<p style="color:red;">患者が見つかりません。</p>');
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

$current_label_code = $patient['esl_label_code'] ?? '';
$assign_message = $_GET['assign'] ?? '';

// ---------------------------------------------------
// AIMSからラベル一覧を取得し、割当可能なものだけに絞り込む
// （非表示にしたラベル / 他の患者が既に使用中のラベルを除外）
// ---------------------------------------------------
$labelsResult = getAimsLabels();
$aims_error = '';
$labels = [];
if ($labelsResult['error'] !== '' || $labelsResult['httpCode'] < 200 || $labelsResult['httpCode'] >= 300) {
    $aims_error = 'AIMSサーバーに接続できませんでした（' . ($labelsResult['error'] ?: 'HTTP ' . $labelsResult['httpCode']) . '）';
} else {
    $labels = $labelsResult['body'] ?? [];
}

$hiddenLabelCodes = [];
$resHidden = $mysqli->query("SELECT label_code FROM esl_hidden_labels");
while ($row = $resHidden->fetch_assoc()) {
    $hiddenLabelCodes[$row['label_code']] = true;
}

$assignedLabelCodes = [];
$resAssigned = $mysqli->query("SELECT esl_label_code FROM patients WHERE esl_label_code IS NOT NULL AND esl_label_code <> ''");
while ($row = $resAssigned->fetch_assoc()) {
    $assignedLabelCodes[$row['esl_label_code']] = true;
}

$mysqli->close();

$availableLabels = array_values(array_filter($labels, function ($label) use ($hiddenLabelCodes, $assignedLabelCodes) {
    $code = $label['labelCode'] ?? '';
    return $code !== '' && !isset($hiddenLabelCodes[$code]) && !isset($assignedLabelCodes[$code]);
}));

function statusBadgeClass(string $value): string {
    $ok = ['ONLINE', 'GOOD', 'EXCELLENT', 'SUCCESS'];
    $ng = ['OFFLINE', 'BAD', 'TIMEOUT'];
    if (in_array($value, $ok, true)) return 'esl-badge--ok';
    if (in_array($value, $ng, true)) return 'esl-badge--ng';
    return 'esl-badge--neutral';
}

$active_menu = 'home';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜ラベル割当</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=32">
  <link rel="stylesheet" href="css/sp_esl_label.css?v=2">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M20.59 13.41 13.41 20.59a2 2 0 0 1-2.82 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1" fill="currentColor" stroke="none"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="esl-label-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <div class="page-title-row">
          <h1>ラベル割当</h1>
        </div>
      </header>

      <p class="form-lead">患者にESL（電子棚札）ラベルを割り当てます。</p>

      <?php if ($assign_message === 'success'): ?>
        <section class="discharge-notice discharge-success">ESLラベルを割り当て、配信しました。</section>
      <?php elseif ($assign_message === 'deliver_error'): ?>
        <section class="discharge-notice">ラベルの割り当ては保存しましたが、AIMSへの配信に失敗しました。時間をおいて再度お試しください。</section>
      <?php elseif ($assign_message === 'unassigned'): ?>
        <section class="discharge-notice discharge-success">割り当てを解除しました。</section>
      <?php elseif ($assign_message === 'error'): ?>
        <section class="discharge-notice">割り当てに失敗しました。対象のラベルが既に他の患者へ割り当て済みの可能性があります。</section>
      <?php endif; ?>

      <section class="card">
        <div class="section-title"><span>現在の割当状況</span></div>
        <div class="section-body">
          <?php if ($current_label_code === ''): ?>
            <p class="esl-empty-text">ラベルが割り当てられていません</p>
          <?php else: ?>
            <div class="esl-current-row">
              <span class="esl-current-icon"><svg><use href="#i-tag"></use></svg></span>
              <div class="esl-current-fields">
                <div class="esl-current-field">
                  <span class="esl-current-caption">ラベルコード</span>
                  <span class="esl-current-value"><?= h($current_label_code) ?></span>
                </div>
                <div class="esl-current-field">
                  <span class="esl-current-caption">最終更新</span>
                  <span class="esl-current-value esl-current-value--muted"><?= h($patient['esl_synced_at'] ?? '-') ?></span>
                </div>
              </div>
              <form method="POST" action="sp_esl_label_process.php" onsubmit="return confirm('この患者のラベル割り当てを解除しますか？');">
                <input type="hidden" name="patient_id" value="<?= h($patient_id) ?>">
                <button type="submit" name="unassign_label" value="1" class="esl-unassign-btn">割当を解除</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($aims_error !== ''): ?>
        <section class="discharge-notice"><?= h($aims_error) ?></section>
      <?php endif; ?>

      <form method="POST" action="sp_esl_label_process.php" id="assignForm">
        <input type="hidden" name="patient_id" value="<?= h($patient_id) ?>">

        <section class="card">
          <div class="section-title"><span>割り当てるラベルを選択</span></div>
          <div class="section-body">
            <div class="esl-search-wrap">
              <svg class="esl-search-icon"><use href="#i-search"></use></svg>
              <input id="labelSearchInput" type="search" placeholder="ラベルコードで検索" autocomplete="off">
            </div>

            <?php if (empty($availableLabels)): ?>
              <p class="esl-empty-text">割当可能なラベルがありません</p>
            <?php else: ?>
              <div id="labelOptionList" class="esl-option-list">
                <?php foreach ($availableLabels as $label):
                  $code = $label['labelCode'] ?? '';
                  $alive = $label['sLabelStatus']['aliveStatus'] ?? '-';
                  $battery = $label['sLabelStatus']['battery'] ?? '-';
                  $signal = $label['sLabelStatus']['signalStrength'] ?? '-';
                ?>
                  <label class="esl-option" data-code="<?= h(strtolower($code)) ?>">
                    <input type="radio" name="label_code" value="<?= h($code) ?>">
                    <span class="esl-option-radio" aria-hidden="true"></span>
                    <span class="esl-option-body">
                      <span class="esl-option-code"><?= h($code) ?></span>
                      <span class="esl-option-badges">
                        <span class="esl-badge <?= statusBadgeClass($alive) ?>"><?= h($alive) ?></span>
                        <span class="esl-badge <?= statusBadgeClass($battery) ?>">電池:<?= h($battery) ?></span>
                        <span class="esl-badge <?= statusBadgeClass($signal) ?>">電波:<?= h($signal) ?></span>
                      </span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p id="labelSearchEmpty" class="esl-empty-text" hidden>該当するラベルが見つかりません</p>
            <?php endif; ?>
          </div>
        </section>

        <section class="action-card">
          <button id="assignSubmitBtn" class="primary-btn" type="submit" name="assign_label" value="1" disabled>選択したラベルを割り当てる</button>
          <a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>" class="secondary-btn">キャンセル</a>
        </section>
      </form>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_esl_label.js?v=1"></script>
</body>
</html>
