<?php
/**
 * B-Care Mobile - 預かり品一覧画面
 * 配置先: mobile/sp_deposit_list.php
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

$stmt_dup = $mysqli->prepare("
    SELECT COUNT(*) AS cnt
    FROM patients
    WHERE patient_name = ? AND patient_id != ?
");
$stmt_dup->bind_param('ss', $patient['patient_name'], $patient_id);
$stmt_dup->execute();
$dup_count = (int)($stmt_dup->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_dup->close();

$stmt_d = $mysqli->prepare("
    SELECT pd.*, sl.name AS location_name,
           st.name AS stored_by_name,
           rt.name AS returned_by_name
    FROM patient_deposits pd
    LEFT JOIN storage_locations sl ON sl.location_id = pd.storage_location_id
    LEFT JOIN staff st ON st.staff_id = pd.stored_by
    LEFT JOIN staff rt ON rt.staff_id = pd.returned_by
    WHERE pd.patient_id = ?
    ORDER BY pd.stored_at DESC
");
$stmt_d->bind_param('s', $patient_id);
$stmt_d->execute();
$deposits = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_d->close();

$mysqli->close();

$stored_items   = array_values(array_filter($deposits, fn($d) => $d['status'] === 'stored'));
$returned_items = array_values(array_filter($deposits, fn($d) => $d['status'] === 'returned'));

function depositQty($item) {
    return $item['quantity'] . ($item['item_master_id'] !== null && isset($item['unit']) ? $item['unit'] : '個');
}

$active_menu = 'deposit';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜預かり品一覧</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=17">
  <link rel="stylesheet" href="css/sp_deposit.css?v=15">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-return-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 8.2 6.7 10.5 9 12.8"/><path d="M6.7 10.5H13a4 4 0 1 1-2.8 6.8"/></symbol>
    <symbol id="i-plus-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
    <symbol id="i-clipboard" viewBox="0 0 24 24"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1Z"/><path d="M6 6h12v14H6z"/><path d="m9 12 2 2 4-4"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="deposit-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <div class="page-header-row">
          <h1>預かり品一覧</h1>
        </div>
      </header>

      <div class="deposit-actions">
        <a class="deposit-action-btn" href="sp_deposit_register.php?patient_id=<?= urlencode($patient_id) ?>">
          <svg><use href="#i-plus-circle"></use></svg>登録
        </a>
        <a class="deposit-action-btn<?= empty($stored_items) ? ' is-disabled' : '' ?>" href="<?= empty($stored_items) ? '#' : 'sp_deposit_return.php?patient_id=' . urlencode($patient_id) ?>">
          <svg><use href="#i-return-circle"></use></svg>返却
        </a>
      </div>

      <section class="card section-card">
        <div class="section-title">
          <span><b></b>保管中の預かり品（<?= count($stored_items) ?>点）</span>
        </div>
        <div class="section-body">
          <?php if (empty($stored_items)): ?>
            <p class="empty-note">保管中の預かり品はありません</p>
          <?php else: ?>
            <ul class="deposit-detail-list">
              <?php foreach ($stored_items as $item): ?>
                <li>
                  <div class="deposit-detail-head">
                    <strong><?= h($item['item_name']) ?></strong>
                    <span class="deposit-status deposit-status--stored">保管中</span>
                  </div>
                  <p>数量：<?= h($item['quantity']) ?>個</p>
                  <p>預かり日時：<?= h(date('Y/m/d H:i', strtotime($item['stored_at']))) ?></p>
                  <p>預かり者：<?= h($item['stored_by_name'] ?? '') ?></p>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </section>

      <details class="card section-card returned-section">
        <summary class="section-title">
          <span><b></b>返却済みの預かり品（<?= count($returned_items) ?>点）</span>
          <svg><use href="#i-chevron"></use></svg>
        </summary>
        <div class="section-body">
          <?php if (empty($returned_items)): ?>
            <p class="empty-note">返却済みの品はありません</p>
          <?php else: ?>
            <ul class="deposit-detail-list">
              <?php foreach ($returned_items as $item): ?>
                <li>
                  <div class="deposit-detail-head">
                    <strong><?= h($item['item_name']) ?></strong>
                    <span class="deposit-status deposit-status--returned">返却済</span>
                  </div>
                  <p>数量：<?= h($item['quantity']) ?>個</p>
                  <p>預かり日時：<?= h(date('Y/m/d H:i', strtotime($item['stored_at']))) ?></p>
                  <p>返却日時：<?= $item['returned_at'] ? h(date('Y/m/d H:i', strtotime($item['returned_at']))) : '-' ?></p>
                  <p>返却者：<?= h($item['returned_by_name'] ?? '') ?></p>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </details>

      <section class="deposit-notice">
        <svg><use href="#i-clipboard"></use></svg>
        <div>
          <strong>退院時のお願い</strong>
          <p>保管中の預かり品がある場合、退院時に返却漏れがないかご確認ください。</p>
        </div>
      </section>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
</body>
</html>
