<?php
/**
 * 共通パーツ：ドロワーメニュー（縦向きページ用）
 * 配置先: mobile/includes/sp_drawer.php
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient_id   : GETから受け取った患者ID文字列
 *   $active_menu  : 現在ページのメニューキー
 *                   ('home' / 'pictogram' / 'vitals' / 'deposit' / 'schedule')
 *                   未設定ならどれもハイライトしない
 *
 * 新しいメニュー項目を増やしたい場合は、下の $menu_items 配列に追記するだけでOK。
 *
 * 【対応CSS】css/sp_common.css の .drawer 系クラス
 * 【対応JS 】js/sp_drawer.js
 *
 * sp_patient_home.php / sp_vitals.php の両方から include されます。
 */

$active_menu = $active_menu ?? '';
$patient_id  = $patient_id ?? '';

$menu_items = [
    'home'      => ['label' => 'TOP',         'href' => 'sp_patient_home.php?patient_id=' . urlencode($patient_id)],
    'pictogram' => ['label' => 'ピクトグラム', 'href' => '#'],
    'vitals'    => ['label' => 'バイタル',     'href' => 'sp_vitals.php?patient_id=' . urlencode($patient_id)],
    'deposit'   => ['label' => '預かり品',     'href' => 'sp_deposit_list.php?patient_id=' . urlencode($patient_id)],
    'schedule'  => ['label' => '予定',         'href' => '#'],
];
?>
<nav class="drawer" id="drawer" aria-label="メインメニュー" aria-hidden="true" inert>
  <div class="drawer-head">
    <strong>B-Care Mobile</strong>
    <button class="drawer-close" id="drawerClose" type="button" aria-label="メニューを閉じる">×</button>
  </div>
  <div class="drawer-links">
    <?php foreach ($menu_items as $key => $item): ?>
      <a href="<?= htmlspecialchars($item['href']) ?>"<?= $key === $active_menu ? ' class="active"' : '' ?>>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <a href="sp_logout.php" class="drawer-logout">ログアウト</a>
</nav>
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
