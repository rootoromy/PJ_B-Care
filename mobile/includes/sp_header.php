<?php
/**
 * 共通パーツ：患者ヘッダー
 * 配置先: mobile/includes/sp_header.php
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient    : patients テーブルの連想配列
 *                 （patient_id, patient_name, patient_kana, age, gender, has_namesake を含む）
 *   $dup_count  : (任意) patient_name完全一致による同姓同名の件数。未設定なら 0 扱い
 *
 * 同姓同名バッジは「$dup_count > 0（名前が実際に重複）」または
 * 「$patient['has_namesake'] が 1（運用側が手動でフラグを立てた）」のどちらかで表示する。
 *
 * 【依存】
 *   includes/functions.php の getGenderStyle()
 *   セッション変数 $_SESSION['user_name']（未設定時は「ナース」表示）
 *
 * 【対応CSS】css/sp_common.css の .patient-header 系クラス
 *
 * sp_patient_home.php / sp_vitals.php の両方から include されます。
 */

$gender       = getGenderStyle($patient['gender'] ?? '');
$dup_count    = $dup_count ?? 0;
$has_namesake = ((int)($patient['has_namesake'] ?? 0) === 1);
?>
<header class="patient-header">
  <button class="icon-button" type="button" aria-label="メニューを開く" id="menuButton" aria-controls="drawer" aria-expanded="false">
    <svg><use href="#i-menu"></use></svg>
  </button>

  <div class="patient-heading">
    <p class="kana"><?= htmlspecialchars($patient['patient_kana'] ?? '') ?></p>
    <div class="name-row">
      <h1><?= htmlspecialchars($patient['patient_name'] ?? '') ?><span>様</span></h1>
      <span class="patient-id">[<?= htmlspecialchars($patient['patient_id'] ?? '') ?>]</span>
    </div>
    <div class="patient-meta">
      <span><?= htmlspecialchars($patient['age'] ?? '-') ?>歳</span>
      <span class="sex" style="color:<?= htmlspecialchars($gender['color']) ?>;"><?= htmlspecialchars($patient['gender'] ?? '-') ?></span>
      <?php if ($dup_count > 0 || $has_namesake): ?>
        <span class="same-name">同姓同名あり</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="login-user">
    <span>Login: <?= htmlspecialchars($_SESSION['user_name'] ?? 'ナース') ?></span>
  </div>
</header>
