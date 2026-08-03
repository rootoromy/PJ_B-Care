<?php
/**
 * 表示ブロック: 明日の予定
 * 配置先: mobile/includes/blocks/block_schedule_tomorrow.php
 * ブロックキー: schedule_tomorrow
 *
 * 【呼び出し側で用意しておく変数】
 *   $schedule_tomorrow : patient_schedule から取得した翌日分の連想配列の配列
 *                        （scheduled_at, category, content を含む）
 *   h()                : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
?>
<section class="card section-card">
  <a class="section-title" href="sp_schedule.php?<?= http_build_query(['patient_id' => $patient_id, 'date' => $tomorrow_date]) ?>">
    <span><b></b>明日の予定</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <?php if (empty($schedule_tomorrow)): ?>
      <p class="empty-note">明日の予定はありません</p>
    <?php else: ?>
      <ul class="schedule-list">
        <?php foreach ($schedule_tomorrow as $item): ?>
          <li>
            <span class="schedule-time"><?= h(date('H:i', strtotime($item['scheduled_at']))) ?></span>
            <?= h($item['content']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
