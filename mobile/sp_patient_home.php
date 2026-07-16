<?php
/**
 * B-Care Mobile - 患者ホーム画面
 * 配置先: mobile/sp_patient_home.php
 * 患者基本情報(ふりがな/氏名/年齢/性別)は patients テーブル、
 * ピクトグラムは pictograms / patient_pictograms テーブルから取得します。
 */
require_once __DIR__ . '/../includes/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : 'P001';

$mysqli = getDB();

$stmt = $mysqli->prepare("SELECT patient_name, patient_kana, age, gender FROM patients WHERE patient_id = ?");
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

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>B-Care Mobile｜患者ホーム</title>
  <link rel="stylesheet" href="css/sp_common.css?v=2">
  <link rel="stylesheet" href="css/sp_patient_home.css?v=1">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/includes/sp_header.php'; ?>

    <?php $active_menu = 'home'; include __DIR__ . '/includes/sp_drawer.php'; ?>

    <main class="main-content">
      <p class="page-name">HOME</p>

      <section class="patient-summary card" aria-label="患者基本情報">
        <dl class="summary-list">
          <div class="summary-row">
            <dt>主治医</dt>
            <dd>テスト 次郎</dd>
          </div>
          <div class="summary-row">
            <dt>受持看護師</dt>
            <dd>テスト 次郎</dd>
          </div>
          <div class="summary-row">
            <dt>病棟・病室</dt>
            <dd>A棟3階 301号室</dd>
          </div>
          <div class="summary-row">
            <dt>ベッド</dt>
            <dd>Aベッド</dd>
          </div>
        </dl>
      </section>

      <section class="home-section" aria-labelledby="memo-title">
        <a class="section-heading" href="#">
          <span id="memo-title">共有メモ</span>
          <span class="section-arrow" aria-hidden="true">&gt;</span>
        </a>
        <div class="section-body memo-body">
          <p>
            ここにテキストが入ります。ここにテキストが入ります。
            ここにテキストが入ります。ここにテキストが入ります。
            ここにテキストが入ります。ここにテキストが入ります。
            ここにテキストが入ります。
          </p>
        </div>
      </section>

      <section class="risk-card card" aria-label="患者リスク情報">
        <div class="risk-row">
          <span class="risk-label risk-label--danger">転倒危険度</span>
          <strong>危険度2：転倒・転落を起こしやすい</strong>
        </div>
        <div class="risk-row">
          <span class="risk-label risk-label--safe">移送区分</span>
          <strong>担送</strong>
        </div>
      </section>

      <section class="pictogram-panel card" aria-label="注意事項ピクトグラム">
        <div class="pictogram-scroll" tabindex="0">
          <?php if (empty($pictograms)): ?>
            <p style="padding:10px; font-size:13px; color:#68736b;">ピクトグラムが設定されていません</p>
          <?php else: ?>
            <?php foreach ($pictograms as $pic): ?>
              <article class="pictogram-item">
                <div class="pictogram-image">
                  <img src="../<?= h($pic['image_path']) ?>" alt="<?= h($pic['name']) ?>" onerror="this.style.visibility='hidden'">
                </div>
                <p><?= h($pic['name']) ?></p>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="scroll-guide" aria-hidden="true"><span></span></div>
      </section>

      <section class="home-section schedule-section" aria-labelledby="schedule-title">
        <a class="section-heading" href="#">
          <span id="schedule-title">今日の予定</span>
          <span class="section-arrow" aria-hidden="true">&gt;</span>
        </a>
        <div class="section-body schedule-body">
          <ul>
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
            <li>ここにテキストが入ります。</li>
          </ul>
        </div>
      </section>
    </main>
  </div>

  <script src="js/sp_drawer.js?v=1"></script>
</body>
</html>
