<?php
/**
 * 表示ブロック: ピクトグラム
 * 配置先: mobile/includes/blocks/block_pictogram.php
 * ブロックキー: pictogram
 *
 * 【呼び出し側で用意しておく変数】
 *   $pictograms : patient_pictograms/pictograms から取得した連想配列の配列
 *                 （name, image_path を含む）
 *   h()         : エスケープ用ヘルパー関数（mobile/sp_patient_home.php で定義）
 */
?>
<section class="card section-card">
  <a class="section-title" href="sp_pictogram.php?patient_id=<?= urlencode($patient_id) ?>">
    <span><b></b>ピクトグラム</span>
    <svg><use href="#i-chevron"></use></svg>
  </a>
  <div class="section-body">
    <?php if (empty($pictograms)): ?>
      <p style="padding:10px; font-size:13px; color:var(--muted);">ピクトグラムが設定されていません</p>
    <?php else: ?>
      <div class="pictogram-grid" id="pictogramGrid">
        <?php foreach ($pictograms as $i => $pic):
          // 2段×3列を1ページとして、行優先（左→右、あふれたら次ページへ横スクロール）で配置する
          $page          = intdiv($i, 6);
          $pos_in_page   = $i % 6;
          $grid_row      = intdiv($pos_in_page, 3) + 1;
          $grid_column   = $page * 3 + ($pos_in_page % 3) + 1;
        ?>
          <div class="pictogram-item" style="grid-row:<?= $grid_row ?>; grid-column:<?= $grid_column ?>;">
            <img class="pictogram" src="../<?= h($pic['image_path']) ?>" alt="" onerror="this.style.visibility='hidden'">
            <span><?= h($pic['name']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="pictogram-scrollbar" id="pictogramScrollbar">
        <div class="pictogram-scrollbar-thumb" id="pictogramScrollbarThumb"></div>
      </div>
    <?php endif; ?>
  </div>
</section>
