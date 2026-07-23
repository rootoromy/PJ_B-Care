<?php
/**
 * 表示ブロック: 患者リスク情報（転倒危険度/移送区分）
 * 配置先: mobile/includes/blocks/block_risk.php
 * ブロックキー: risk
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient : patients テーブルの連想配列（fall_risk, transfer_type を含む）
 *   h()      : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
?>
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
