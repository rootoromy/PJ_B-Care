<?php
/**
 * B-Care Mobile - 患者一覧画面
 * 配置先: mobile/sp_patient_list.php
 *
 * 患者を選択してから患者ホーム画面へ遷移するための一覧画面。
 * 共通の .phone-shell / sp_simple_header.php / sp_drawer.php を利用し、
 * 表示データは patients テーブルから取得する。
 * 元は Downloads 配下のモック（bcare_mobile_patient_list_final_mock）を
 * ベースに、ヘッダーを共通ヘッダーへ差し替え・表示データを DB 参照に
 * 置き換えたもの。ピックアップ（ピン留め）状態は staff_pinned_patients
 * テーブルでログイン中のスタッフ単位に保存する（共有端末で複数スタッフが
 * ログインし直しても状態が混ざらないようにするため）。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';
sp_require_login();

require_once __DIR__ . '/../includes/config.php';

$staff_id = $_SESSION['staff_id'] ?? '';

$mysqli = getDB();
$stmt = $mysqli->prepare("
    SELECT p.patient_id, p.patient_name, p.gender, p.age,
           (spp.staff_id IS NOT NULL) AS pinned
    FROM patients p
    LEFT JOIN staff_pinned_patients spp
      ON spp.patient_id = p.patient_id AND spp.staff_id = ?
    ORDER BY p.patient_id
");
$stmt->bind_param('s', $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();

foreach ($patients as &$p) {
    $p['pinned'] = (bool)$p['pinned'];
}
unset($p);

$patients_json = json_encode($patients, JSON_UNESCAPED_UNICODE);

$active_menu = 'patients';
$patient_id  = '';
$page_title  = 'B-Care Mobile';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜患者一覧</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=31">
  <link rel="stylesheet" href="css/sp_patient_list.css?v=11">
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_simple_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="patient-list-main">
      <header class="page-header">
        <h1>患者一覧</h1>
      </header>

      <section class="search-area" aria-label="患者検索">
        <div class="search-input-wrap">
          <input
            id="searchInput"
            type="search"
            placeholder="患者IDまたは氏名を入力"
            autocomplete="off"
          >
        </div>

        <button id="searchButton" class="search-button" type="button" aria-label="検索">
          <svg class="search-button-icon"><use href="#i-search"></use></svg>
        </button>
      </section>

      <section class="patient-table" aria-label="患者一覧">
        <div class="table-head">
          <span>No.</span>
          <span>★</span>
          <span>患者ID</span>
          <span>氏名</span>
          <span>性別</span>
          <span>年齢</span>
          <span>選択</span>
        </div>

        <section id="pickupSection" class="patient-section">
          <div class="section-title pickup-title">
            <span class="section-bar" aria-hidden="true"></span>
            <span>ピックアップ</span>
          </div>
          <div id="pickupList" class="patient-list"></div>
        </section>

        <section id="allSection" class="patient-section">
          <div class="section-title all-title">
            <span class="section-bar" aria-hidden="true"></span>
            <span>患者一覧</span>
          </div>
          <div id="patientList" class="patient-list"></div>
        </section>

        <p id="emptyMessage" class="empty-message" hidden>
          該当する患者が見つかりません。
        </p>
      </section>

      <nav id="pagination" class="pagination" aria-label="ページ送り" hidden></nav>
    </main>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>
  </div>

  <script>window.PATIENTS_DATA = <?= $patients_json ?>;</script>
  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_patient_list.js?v=3"></script>
</body>
</html>
