<?php
/**
 * B-Care Mobile - 予定表画面
 * 配置先: mobile/sp_schedule.php
 *
 * 1日分の予定（患者スケジュール）を一覧表示する。共通の .phone-shell /
 * sp_header.php / sp_drawer.php を患者ホーム画面・バイタル画面と共通利用する。
 * 元は Downloads 配下のモック（bcare_schedule_mock）をベースに、
 * ヘッダーを共通ヘッダーへ差し替え・下部ナビを削除し、表示データを
 * patient_schedule テーブル参照に置き換えたもの。
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
// 予定取得（指定日）
// ---------------------------------------------------
$stmt_s = $mysqli->prepare("
    SELECT scheduled_at, category, content, location
    FROM patient_schedule
    WHERE patient_id = ? AND DATE(scheduled_at) = ?
    ORDER BY scheduled_at
");
$stmt_s->bind_param('ss', $patient_id, $target_date);
$stmt_s->execute();
$schedule_rows = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_s->close();

$mysqli->close();

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

$active_menu = 'schedule';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>B-Care Mobile｜予定</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_common.css?v=33" />
  <link rel="stylesheet" href="css/sp_schedule.css?v=8" />
</head>
<body>
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="schedule-main">
      <header class="page-header">
        <p class="breadcrumb"><a href="sp_patient_home.php?patient_id=<?= urlencode($patient_id) ?>"><svg class="inline-chevron"><use href="#i-chevron-left"></use></svg>戻る</a></p>
        <h1>予定</h1>
      </header>

      <div class="schedule-panel">
        <div class="date-navigation">
          <a class="circle-button" href="sp_schedule.php<?= buildDateQs($patient_id, $prev_date) ?>" aria-label="前日へ"><svg class="circle-button-icon"><use href="#i-chevron-left"></use></svg></a>

          <label class="date-button" for="datePicker">
            <svg class="calendar-icon"><use href="#i-calendar"></use></svg>
            <span id="displayDate"><?= h($date_display) ?></span>
            <input type="date" id="datePicker" class="date-picker-input" value="<?= h($target_date) ?>" data-patient-id="<?= h($patient_id) ?>" aria-label="表示日を選択">
          </label>

          <a class="circle-button" href="sp_schedule.php<?= buildDateQs($patient_id, $next_date) ?>" aria-label="翌日へ"><svg class="circle-button-icon"><use href="#i-chevron"></use></svg></a>
        </div>

        <?php if ($target_date !== date('Y-m-d')): ?>
          <div class="today-jump">
            <a href="sp_schedule.php?<?= http_build_query(['patient_id' => $patient_id]) ?>">今日を表示</a>
          </div>
        <?php endif; ?>

        <div class="schedule-list" aria-live="polite">
          <?php if (empty($schedule_rows)): ?>
            <div class="schedule-row">
              <div class="schedule-time">--:--</div>
              <div>
                <p class="schedule-name">予定はありません</p>
                <p class="schedule-place">この日の予定は登録されていません。</p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($schedule_rows as $item): ?>
              <article class="schedule-row">
                <time class="schedule-time"><?= h(date('H:i', strtotime($item['scheduled_at']))) ?></time>
                <div>
                  <p class="schedule-name"><?= h($item['content']) ?></p>
                  <p class="schedule-place"><?= h($item['location'] ?? '') ?></p>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="schedule-note">
          <svg class="info-icon"><use href="#i-info"></use></svg>
          <p>予定は変更される場合があります。</p>
        </div>
      </div>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_schedule.js?v=1"></script>
</body>
</html>
