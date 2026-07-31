<?php
/**
 * 表示ブロック: 預かり品
 * 配置先: mobile/includes/blocks/block_deposit.php
 * ブロックキー: deposit
 *
 * 【呼び出し側で用意しておく変数】
 *   $deposit_items : patient_deposits テーブルの連想配列（item_name, status）
 *   $patient_id    : GETから受け取った患者ID文字列
 *   h()            : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
$deposit_items = $deposit_items ?? [];
?>
<section class="card section-card">
  <a class="section-title" href="sp_deposit_list.php?patient_id=<?= urlencode($patient_id) ?>">
    <span><b></b>預かり品</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <?php if (empty($deposit_items)): ?>
      <p style="padding:10px; color:var(--muted);">預かり品はありません</p>
    <?php else: ?>
      <ul class="deposit-list">
        <?php foreach ($deposit_items as $item): ?>
          <li>
            <span class="deposit-name"><?= h($item['item_name']) ?></span>
            <span class="deposit-status deposit-status--<?= h($item['status']) ?>"><?= $item['status'] === 'returned' ? '返却済' : '保管中' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
