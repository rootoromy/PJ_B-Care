<?php
/**
 * B-Care Mobile - 患者ホーム画面
 * 配置先: mobile/sp_patient_home.php
 * 患者基本情報(ふりがな/氏名/年齢/性別/病棟/ベッド)は patients テーブル、
 * ピクトグラムは pictograms / patient_pictograms テーブル、
 * バイタルの最新値は vitals テーブルから取得します。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : 'P001';

$mysqli = getDB();

$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    $patient = [
        'patient_name' => '患者 太郎',
        'patient_kana' => 'カンジャ タロウ',
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
    SELECT measured_at, temperature, systolic_bp, diastolic_bp, pulse, spo2
    FROM vitals
    WHERE patient_id = ?
    ORDER BY measured_at DESC
    LIMIT 1
");
$stmt_vital->bind_param('s', $patient_id);
$stmt_vital->execute();
$latest_vital = $stmt_vital->get_result()->fetch_assoc();
$stmt_vital->close();

$mysqli->close();

function vitalValue($latest_vital, $field) {
    if (!$latest_vital || $latest_vital[$field] === null || $latest_vital[$field] === '') {
        return null;
    }
    return $latest_vital[$field];
}

$active_menu = 'home';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>B-Care Mobile｜患者ホーム</title>
  <link rel="stylesheet" href="css/sp_common.css?v=6">
  <link rel="stylesheet" href="css/sp_patient_home.css?v=6">
</head>
<body>
  <!-- SVG icon sprite（外部ライブラリ不要） -->
  <svg class="svg-sprite" aria-hidden="true">
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 20c.8-4.2 3.2-6 7-6s6.2 1.8 7 6"/></symbol>
    <symbol id="i-stethoscope" viewBox="0 0 24 24"><path d="M6 3v6a5 5 0 0 0 10 0V3M4 3h4M14 3h4M16 12v2a4 4 0 0 0 8 0v-1"/><circle cx="21" cy="10" r="2"/></symbol>
    <symbol id="i-nurse" viewBox="0 0 24 24"><path d="M8 4h8l1 3H7zM9 7v2a3 3 0 0 0 6 0V7M5 21c.8-4.7 3.2-7 7-7s6.2 2.3 7 7"/><path d="M11 5h2M12 4v2"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><path d="M4 21V4h12v17M16 10h4v11M8 8h2M8 12h2M8 16h2M13 8h1M13 12h1M13 16h1M2 21h20"/></symbol>
    <symbol id="i-bed" viewBox="0 0 24 24"><path d="M3 18V6M3 14h18v4M7 14v-3h5a3 3 0 0 1 3 3M3 18v3M21 18v3"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 10a7 7 0 0 0-12-2M6 14a7 7 0 0 0 12 2"/></symbol>
  </svg>

  <div class="phone-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php include __DIR__ . '/includes/sp_drawer.php'; ?>

    <div class="home-label">HOME</div>

    <main>
      <section class="card info-card" aria-label="患者基本情報">
        <div class="info-row">
          <span class="info-label"><svg><use href="#i-stethoscope"></use></svg>主治医</span>
          <span class="info-value">テスト 次郎</span>
        </div>
        <div class="info-row">
          <span class="info-label"><svg><use href="#i-nurse"></use></svg>受持看護師</span>
          <span class="info-value">テスト 次郎</span>
        </div>
        <div class="info-row">
          <span class="info-label"><svg><use href="#i-building"></use></svg>病棟・病室</span>
          <span class="info-value"><?= h($patient['ward_name'] ?? '') ?><?= !empty($patient['room_no']) ? ' ' . h($patient['room_no']) . '号室' : '' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><svg><use href="#i-bed"></use></svg>ベッド</span>
          <span class="info-value"><?= h($patient['bed_no'] ?? '') ?><?= !empty($patient['bed_no']) ? 'ベッド' : '' ?></span>
        </div>
      </section>

      <section class="card section-card">
        <button class="section-title" type="button" data-toggle="pictogramPanel">
          <span><b></b>ピクトグラム</span>
          <svg><use href="#i-chevron"></use></svg>
        </button>
        <div class="section-body" id="pictogramPanel">
          <?php if (empty($pictograms)): ?>
            <p style="padding:10px; font-size:12px; color:var(--muted);">ピクトグラムが設定されていません</p>
          <?php else: ?>
            <div class="pictogram-grid">
              <?php foreach ($pictograms as $i => $pic):
                $is_prohibited = strpos(basename($pic['image_path']), 'no_') === 0;
                // 2段×4列を1ページとして、行優先（左→右、あふれたら次ページへ横スクロール）で配置する
                $page          = intdiv($i, 8);
                $pos_in_page   = $i % 8;
                $grid_row      = intdiv($pos_in_page, 4) + 1;
                $grid_column   = $page * 4 + ($pos_in_page % 4) + 1;
              ?>
                <div class="pictogram-item<?= $is_prohibited ? ' prohibited' : '' ?>" style="grid-row:<?= $grid_row ?>; grid-column:<?= $grid_column ?>;">
                  <img class="pictogram" src="../<?= h($pic['image_path']) ?>" alt="" onerror="this.style.visibility='hidden'">
                  <?php if ($is_prohibited): ?><span class="ban-mark" aria-hidden="true"></span><?php endif; ?>
                  <span><?= h($pic['name']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="risk-grid" aria-label="患者リスク情報">
        <article class="risk-card fall-risk">
          <small>転倒危険度</small>
          <strong><?= h($patient['fall_risk'] ?? 0) ?></strong>
        </article>
        <article class="risk-card transport">
          <small>移送区分</small>
          <strong><?= h($patient['transfer_type'] ?? '-') ?></strong>
        </article>
      </section>

      <section class="card section-card">
        <button class="section-title" type="button" data-toggle="schedulePanel">
          <span><b></b>今日の予定</span>
          <svg><use href="#i-chevron"></use></svg>
        </button>
        <div class="section-body" id="schedulePanel">
          <ul class="schedule-list">
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
          </ul>
        </div>
      </section>

      <section class="card section-card">
        <button class="section-title" type="button" data-toggle="scheduleTomorrowPanel">
          <span><b></b>明日の予定</span>
          <svg><use href="#i-chevron"></use></svg>
        </button>
        <div class="section-body" id="scheduleTomorrowPanel">
          <ul class="schedule-list">
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
          </ul>
        </div>
      </section>

      <section class="card section-card vital-card">
        <button class="section-title" type="button" data-toggle="vitalPanel">
          <span><b></b>バイタル</span>
          <svg><use href="#i-chevron"></use></svg>
        </button>
        <div class="section-body" id="vitalPanel">
          <?php
            $bp_sys = vitalValue($latest_vital, 'systolic_bp');
            $bp_dia = vitalValue($latest_vital, 'diastolic_bp');
            $temp   = vitalValue($latest_vital, 'temperature');
            $pulse  = vitalValue($latest_vital, 'pulse');
            $spo2   = vitalValue($latest_vital, 'spo2');
          ?>
          <div class="vitals-grid">
            <div><span>血圧(上)</span><strong><?= $bp_sys !== null ? h($bp_sys) : '－' ?></strong><small>mmHg</small></div>
            <div><span>体温</span><strong><?= $temp !== null ? h($temp) : '－' ?></strong><small>℃</small></div>
            <div><span>血圧(下)</span><strong><?= $bp_dia !== null ? h($bp_dia) : '－' ?></strong><small>mmHg</small></div>
            <div><span>脈拍</span><strong><?= $pulse !== null ? h($pulse) : '－' ?></strong><small>bpm</small></div>
            <div class="empty"></div>
            <div><span>SPO2</span><strong><?= $spo2 !== null ? h($spo2) : '－' ?></strong><small>%</small></div>
          </div>
          <div class="vital-footer">
            <p><svg><use href="#i-clock"></use></svg>最終更新：<span><?= $latest_vital ? h(date('Y/m/d H:i', strtotime($latest_vital['measured_at']))) : '記録なし' ?></span></p>
            <a class="vital-update-btn" href="sp_vitals.php?patient_id=<?= urlencode($patient_id) ?>"><svg><use href="#i-refresh"></use></svg>更新</a>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
  <script src="js/sp_patient_home.js?v=1"></script>
</body>
</html>
