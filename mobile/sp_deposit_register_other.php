<?php
/**
 * B-Care Mobile - 預かり品登録画面（その他・新規登録）
 * 配置先: mobile/sp_deposit_register_other.php
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

$staff_list = $mysqli->query("SELECT staff_id, name, position FROM staff WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$locations = $mysqli->query("SELECT location_id, name FROM storage_locations WHERE is_active = 1 ORDER BY location_id")->fetch_all(MYSQLI_ASSOC);

$mysqli->close();

$active_menu = 'deposit';
$current_staff_id = $_SESSION['staff_id'] ?? '';
$now_local = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜預かり品 その他（新規登録）</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=17">
  <link rel="stylesheet" href="css/sp_deposit.css?v=16">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="deposit-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_deposit_register.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <div class="page-header-row">
          <h1>その他（新規登録）</h1>
        </div>
      </header>

      <p class="form-lead">預かり品の情報を入力してください。</p>

      <form id="registerOtherForm">
        <section class="card form-section">
          <div class="form-row">
            <label for="itemName">預かり品名<span class="required">必須</span></label>
            <input type="text" id="itemName" name="item_name" placeholder="例）腕時計、イヤホン、書類ケース など" required>
          </div>
          <div class="form-row">
            <label for="quantity">数量<span class="required">必須</span></label>
            <div class="qty-stepper">
              <button type="button" class="qty-btn qty-minus" aria-label="減らす">−</button>
              <input type="number" class="qty-input" id="quantity" name="quantity" value="1" min="1" step="1">
              <button type="button" class="qty-btn qty-plus" aria-label="増やす">＋</button>
              <span class="qty-unit">個</span>
            </div>
          </div>
          <div class="form-row">
            <label for="conditionNote">状態（任意）</label>
            <input type="text" id="conditionNote" name="condition_note" placeholder="例）傷あり、動作良好、現金ありなど">
          </div>
          <div class="form-row">
            <label for="storedAt">預かり日時<span class="required">必須</span></label>
            <input type="datetime-local" id="storedAt" name="stored_at" value="<?= h($now_local) ?>" required>
          </div>
          <div class="form-row">
            <label for="storedBy">預かり者<span class="required">必須</span></label>
            <select id="storedBy" name="stored_by" required>
              <?php foreach ($staff_list as $s): ?>
                <option value="<?= h($s['staff_id']) ?>"<?= $s['staff_id'] === $current_staff_id ? ' selected' : '' ?>>
                  <?= h($s['position'] ? $s['position'] . ' ' . $s['name'] : $s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label for="storageLocation">保管場所<span class="required">必須</span></label>
            <select id="storageLocation" name="storage_location_id" required>
              <?php foreach ($locations as $loc): ?>
                <option value="<?= h($loc['location_id']) ?>"><?= h($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label for="remarks">備考（任意）</label>
            <textarea id="remarks" name="remarks" maxlength="200" placeholder="備考を入力してください"></textarea>
          </div>
        </section>

        <section class="card master-register-card">
          <p class="master-register-title">マスターリストに登録しますか？</p>
          <p class="master-register-note">入力した「預かり品名」はマスターリストに登録されていません。</p>
          <label class="radio-row">
            <input type="radio" name="add_to_master" value="0" checked>
            今回のみ登録する
          </label>
          <label class="radio-row">
            <input type="radio" name="add_to_master" value="1">
            マスターリストに登録する
          </label>
        </section>

        <p class="form-status" id="formStatus" aria-live="polite"></p>

        <div class="form-actions">
          <a class="btn-secondary" href="sp_deposit_register.php?patient_id=<?= urlencode($patient_id) ?>">キャンセル</a>
          <button type="submit" class="btn-primary" id="submitBtn">保存する</button>
        </div>
      </form>
    </main>
  </div>

  <script>
    window.depositPatientId = <?= json_encode($patient_id) ?>;
  </script>
  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_deposit_register_other.js?v=1"></script>
</body>
</html>
