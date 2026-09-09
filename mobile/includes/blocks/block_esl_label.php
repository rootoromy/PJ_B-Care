<?php
/**
 * 表示ブロック: ESLラベル割当
 * 配置先: mobile/includes/blocks/block_esl_label.php
 * ブロックキー: esl_label
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient    : patients テーブルの連想配列（esl_label_code, esl_synced_at を含む）
 *   $patient_id : GETから受け取った患者ID文字列
 *   h()         : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
$esl_label_code = $patient['esl_label_code'] ?? '';
$is_viewer = $is_viewer ?? false;
$is_discharged = $is_discharged ?? false;
?>
<section class="card section-card">
  <?php if ($is_viewer || $is_discharged): ?>
    <div class="section-title"><span><b></b>ラベル割当</span></div>
  <?php else: ?>
    <a class="section-title" href="sp_esl_label.php?patient_id=<?= urlencode($patient_id) ?>">
      <span><b></b>ラベル割当</span>
      <svg><use href="#i-chevron"></use></svg>
    </a>
  <?php endif; ?>
  <div class="section-body">
    <?php if ($esl_label_code === ''): ?>
      <p style="padding:10px; font-size:13px; color:var(--muted);">ラベルが割り当てられていません</p>
    <?php else: ?>
      <div class="esl-label-card">
        <span class="esl-label-icon"><svg><use href="#i-tag"></use></svg></span>
        <div class="esl-label-fields">
          <div class="esl-label-field">
            <span class="esl-label-caption">ラベルコード</span>
            <span class="esl-label-value"><?= h($esl_label_code) ?></span>
          </div>
          <div class="esl-label-field">
            <span class="esl-label-caption">最終更新</span>
            <span class="esl-label-value esl-label-value--muted"><?= h($patient['esl_synced_at'] ?? '-') ?></span>
          </div>
        </div>
        <?php if (!$is_viewer): ?>
          <form method="POST" action="sp_esl_label_process.php" data-confirm-title="ラベル割り当てを解除" data-confirm-message="この患者のラベル割り当てを解除しますか？">
            <input type="hidden" name="patient_id" value="<?= h($patient_id) ?>">
            <input type="hidden" name="redirect" value="home">
            <button type="submit" name="unassign_label" value="1" class="esl-unassign-btn">割当を解除</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
