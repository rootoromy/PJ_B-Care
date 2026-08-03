<?php
/**
 * 表示ブロック: 今日の予定
 * 配置先: mobile/includes/blocks/block_schedule_today.php
 * ブロックキー: schedule_today
 *
 * 【呼び出し側で用意しておく変数】
 *   $schedule_today : patient_schedule から取得した当日分の連想配列の配列
 *                     （scheduled_at, category, content を含む）
 *   h()             : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
?>
<section class="card section-card">
  <a class="section-title" href="sp_schedule.php?patient_id=<?= urlencode($patient_id) ?>">
    <span><b></b>今日の予定</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <?php if (empty($schedule_today)): ?>
      <p class="empty-note">本日の予定はありません</p>
    <?php else: ?>
      <ul class="schedule-list">
        <?php foreach ($schedule_today as $item): ?>
          <li>
            <span class="schedule-time"><?= h(date('H:i', strtotime($item['scheduled_at']))) ?></span>
            <?= h($item['content']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
