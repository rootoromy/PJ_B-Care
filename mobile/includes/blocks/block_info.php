<?php
/**
 * 表示ブロック: 患者基本情報（主治医/受持看護師/病棟・病室/ベッド）
 * 配置先: mobile/includes/blocks/block_info.php
 * ブロックキー: info
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient : patients テーブルの連想配列
 *   h()      : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
?>
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
