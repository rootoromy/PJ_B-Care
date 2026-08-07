<?php
/**
 * B-Care Mobile - ピクトグラム選択画面
 * 配置先: mobile/sp_pictogram.php
 *
 * 患者に設定するピクトグラムを選択・変更する。共通の .phone-shell /
 * sp_header.php / sp_drawer.php を他画面と共通利用する。
 * 元は Downloads 配下のモック（bcare_pictogram_mobile_mock）をベースに、
 * ヘッダーを共通ヘッダーへ差し替え、絵文字表示を pictograms テーブルの
 * image_path（実画像）に置き換えたもの。
 *
 * 保存は sp_pictogram_process.php（patient_pictograms を全削除→再登録）で行う。
 * B-Care Manager 側の manager/pictogram_setting.php も同じ patient_pictograms
 * テーブルを同じ「全削除→再登録」方式で更新するため、モバイル側で変更すれば
 * Manager 側にも自動的に反映される（別途の同期処理は不要）。
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

// ---------------------------------------------------
// パラメータ取得
// ---------------------------------------------------
$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    header('Location: sp_patient_home.php');
    exit;
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
// ピクトグラム一覧・カテゴリ取得
// ---------------------------------------------------
$all_pictograms = $mysqli->query("SELECT * FROM pictograms ORDER BY category, pictogram_id")->fetch_all(MYSQLI_ASSOC);

$categories = [];
foreach ($all_pictograms as $pic) {
    if ($pic['category'] !== null && !in_array($pic['category'], $categories, true)) {
        $categories[] = $pic['category'];
    }
}

// ---------------------------------------------------
// 現在設定中のピクトグラムID一覧
// ---------------------------------------------------
$stmt_cur = $mysqli->prepare("SELECT pictogram_id FROM patient_pictograms WHERE patient_id = ? ORDER BY display_order");
$stmt_cur->bind_param('s', $patient_id);
$stmt_cur->execute();
$current_ids = array_map('intval', array_column($stmt_cur->get_result()->fetch_all(MYSQLI_ASSOC), 'pictogram_id'));
$stmt_cur->close();

$mysqli->close();

$active_menu = 'pictogram';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>B-Care Mobile｜ピクトグラム</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=31" />
  <link rel="stylesheet" href="css/sp_pictogram.css?v=14" />
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M6 18L18 6"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 10a7 7 0 0 0-12-2M6 14a7 7 0 0 0 12 2"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="pictogram-main" data-patient-id="<?= h($patient_id) ?>">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <div class="page-title-row">
          <h1>ピクトグラム</h1>
          <a class="page-refresh-btn" href="sp_pictogram.php?patient_id=<?= urlencode($patient_id) ?>">
            <svg><use href="#i-refresh"></use></svg>
            更新
          </a>
        </div>
      </header>

      <section class="card">
        <div class="section-title collapsible" data-target="selectedBody">
          <span>選択中のピクトグラム<span id="selectedCount">：0件</span></span>
          <svg class="chevron-icon"><use href="#i-chevron"></use></svg>
        </div>
        <div id="selectedBody" class="section-body">
          <div id="selectedGrid" class="selected-grid"></div>
          <div class="pictogram-scrollbar" id="selectedScrollbar" hidden>
            <div class="pictogram-scrollbar-thumb" id="selectedScrollbarThumb"></div>
          </div>
          <div id="selectedEmpty" class="selected-empty" hidden>
            <p>確定する場合は、「このピクトグラムを設定する」ボタンを押下してください。</p>
          </div>
        </div>
      </section>

      <section class="action-card">
        <button id="applyBtn" class="primary-btn" type="button">
          <svg class="btn-icon"><use href="#i-check"></use></svg>
          このピクトグラムを設定する
        </button>
        <button id="clearAllBtn" class="clear-all-btn" type="button">すべてクリア</button>
        <a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>" class="secondary-btn">キャンセル</a>
      </section>

      <section class="card">
        <div class="section-title collapsible" data-target="pickerBody">
          <span>ピクトグラムを選択</span>
          <svg class="chevron-icon"><use href="#i-chevron"></use></svg>
        </div>
        <div id="pickerBody" class="section-body">
          <p class="hint">タップで選択・解除できます</p>

          <div id="categoryChips" class="chips">
            <button class="chip is-active" type="button" data-cat="すべて">すべて</button>
            <?php foreach ($categories as $cat): ?>
              <button class="chip" type="button" data-cat="<?= h($cat) ?>"><?= h($cat) ?></button>
            <?php endforeach; ?>
          </div>

          <div id="pictogramGrid" class="pictogram-grid">
            <?php foreach ($all_pictograms as $pic): ?>
              <div class="pictogram-item<?= in_array((int)$pic['pictogram_id'], $current_ids, true) ? ' selected' : '' ?>"
                   data-id="<?= (int)$pic['pictogram_id'] ?>"
                   data-name="<?= h($pic['name']) ?>"
                   data-img="../<?= h($pic['image_path']) ?>"
                   data-cat="<?= h($pic['category'] ?? '') ?>">
                <img src="../<?= h($pic['image_path']) ?>" alt="" onerror="this.style.visibility='hidden'">
                <span class="label"><?= h($pic['name']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="pictogram-scrollbar" id="pictogramScrollbar" hidden>
            <div class="pictogram-scrollbar-thumb" id="pictogramScrollbarThumb"></div>
          </div>
        </div>
      </section>
    </main>

    <div id="toast" class="toast" role="status"></div>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_pictogram.js?v=8"></script>
</body>
</html>
