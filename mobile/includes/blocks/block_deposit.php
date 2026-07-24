<?php
/**
 * 表示ブロック: 預かり品
 * 配置先: mobile/includes/blocks/block_deposit.php
 * ブロックキー: deposit
 *
 * 【呼び出し側で用意しておく変数】なし（現状ダミー表示。品名とステータス(保管中/返却済)のみ）
 * ステータスの切り替え（返却済への変更時に日付・担当者名を記録する機能）は
 * このページの表示範囲外のため未実装。
 */
$deposit_items = [
    ['name' => '義歯',       'status' => 'stored'],
    ['name' => '補聴器',     'status' => 'stored'],
    ['name' => '眼鏡',       'status' => 'returned'],
];
?>
<section class="card section-card">
  <a class="section-title" href="#">
    <span><b></b>預かり品</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <?php if (empty($deposit_items)): ?>
      <p style="padding:10px; font-size:12px; color:var(--muted);">預かり品はありません</p>
    <?php else: ?>
      <ul class="deposit-list">
        <?php foreach ($deposit_items as $item): ?>
          <li>
            <span class="deposit-name"><?= h($item['name']) ?></span>
            <span class="deposit-status deposit-status--<?= h($item['status']) ?>"><?= $item['status'] === 'returned' ? '返却済' : '保管中' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
