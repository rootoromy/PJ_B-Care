<?php
/**
 * B-Care Mobile - 預かり品返却画面
 * 配置先: mobile/sp_deposit_return.php
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

$dup_count = 0;

$stmt_d = $mysqli->prepare("
    SELECT pd.deposit_id, pd.item_name, pd.quantity, pd.stored_at, st.name AS stored_by_name
    FROM patient_deposits pd
    LEFT JOIN staff st ON st.staff_id = pd.stored_by
    WHERE pd.patient_id = ? AND pd.status = 'stored'
    ORDER BY pd.stored_at
");
$stmt_d->bind_param('s', $patient_id);
$stmt_d->execute();
$stored_items = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_d->close();

$staff_list = $mysqli->query("SELECT staff_id, name, position FROM staff WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$mysqli->close();

if (empty($stored_items)) {
    header('Location: sp_deposit_list.php?patient_id=' . urlencode($patient_id));
    exit;
}

$active_menu = 'deposit';
$current_staff_id = $_SESSION['staff_id'] ?? '';
$now_local = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜預かり品返却</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=10">
  <link rel="stylesheet" href="css/sp_deposit.css?v=1">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 20c.8-4.2 3.2-6 7-6s6.2 1.8 7 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-undo" viewBox="0 0 24 24"><path d="M4 10h11a4 4 0 0 1 0 8h-1M4 10l4-4M4 10l4 4"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="deposit-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_deposit_list.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <h1>預かり品返却</h1>
      </header>

      <p class="form-lead">預かり中の預かり品をまとめて返却します。返却する預かり品にチェックを入れて、返却情報を入力してください。</p>

      <form id="returnForm">
        <section class="card">
          <div class="section-title">
            <span>返却する預かり品を選択</span>
            <button type="button" class="select-all-btn" id="selectAllBtn">すべて選択</button>
          </div>
          <div class="section-body">
            <ul class="return-item-list">
              <?php foreach ($stored_items as $item): ?>
                <li>
                  <label class="return-item-row">
                    <input type="checkbox" class="return-checkbox" value="<?= h($item['deposit_id']) ?>">
                    <span class="return-item-info">
                      <strong><?= h($item['item_name']) ?></strong>
                      <small>預かり日時：<?= h(date('Y/m/d H:i', strtotime($item['stored_at']))) ?></small>
                      <small>預かり者：<?= h($item['stored_by_name'] ?? '') ?></small>
                      <small>数量：<?= h($item['quantity']) ?>個</small>
                    </span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </section>

        <p class="selected-count-note"><span id="selectedCount">0</span>件選択中</p>

        <section class="card form-section">
          <h2 class="form-section-title">返却情報を入力</h2>
          <div class="form-row">
            <label for="returnedAt">返却日時<span class="required">必須</span></label>
            <input type="datetime-local" id="returnedAt" name="returned_at" value="<?= h($now_local) ?>" required>
          </div>
          <div class="form-row">
            <label for="returnedBy">返却者<span class="required">必須</span></label>
            <select id="returnedBy" name="returned_by" required>
              <?php foreach ($staff_list as $s): ?>
                <option value="<?= h($s['staff_id']) ?>"<?= $s['staff_id'] === $current_staff_id ? ' selected' : '' ?>>
                  <?= h($s['position'] ? $s['position'] . ' ' . $s['name'] : $s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label>返却先<span class="required">必須</span></label>
            <div class="return-to-options">
              <label class="radio-row">
                <input type="radio" name="return_to" value="本人" checked> 本人へ返却
              </label>
              <label class="radio-row">
                <input type="radio" name="return_to" value="家族"> ご家族へ返却
              </label>
              <label class="radio-row return-to-other-row">
                <input type="radio" name="return_to" value="その他"> その他
                <input type="text" id="returnToOther" name="return_to_other" placeholder="入力してください" disabled>
              </label>
            </div>
          </div>
          <div class="form-row">
            <label for="returnRemarks">備考（任意）</label>
            <textarea id="returnRemarks" name="return_remarks" maxlength="200" placeholder="必要に応じて入力してください"></textarea>
          </div>
        </section>

        <p class="return-warning">返却後の内容は履歴に記録されます。取り消しはできませんのでご注意ください。</p>

        <p class="form-status" id="formStatus" aria-live="polite"></p>

        <button type="submit" class="btn-primary btn-block" id="submitBtn">
          <svg><use href="#i-undo"></use></svg>
          <span id="selectedCount2">0</span>件を返却する
        </button>
      </form>
    </main>
  </div>

  <script>
    window.depositPatientId = <?= json_encode($patient_id) ?>;
  </script>
  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_deposit_return.js?v=1"></script>
</body>
</html>
