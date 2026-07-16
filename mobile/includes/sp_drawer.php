<?php
/**
 * 共通パーツ：ドロワーメニュー（縦向きページ用）
 * 配置先: mobile/includes/sp_drawer.php
 *
 * 【呼び出し側で用意しておく変数】
 *   $patient_id   : GETから受け取った患者ID文字列
 *   $active_menu  : 現在ページのメニューキー
 *                   ('home' / 'vitals' / 'handover' / 'deposit' / 'list')
 *                   未設定ならどれもハイライトしない
 *
 * 新しいメニュー項目を増やしたい場合は、下の $menu_items 配列に追記するだけでOK。
 *
 * 【対応CSS】css/sp_common.css の .drawer 系クラス
 * 【対応JS 】js/sp_drawer.js
 *
 * ※ sp_vitals.php は独自のドロワー実装（.vitals-drawer）のため、
 *    このファイルを使用しません。
 */

$active_menu = $active_menu ?? '';
$patient_id  = $patient_id ?? '';

$menu_items = [
    'home'     => ['label' => 'HOME',    'href' => 'sp_patient_home.php?patient_id=' . urlencode($patient_id)],
    'vitals'   => ['label' => 'バイタル', 'href' => 'sp_vitals.php?patient_id=' . urlencode($patient_id)],
    'handover' => ['label' => '申し送り', 'href' => '#'],
    'deposit'  => ['label' => '預かり品', 'href' => '#'],
    'list'     => ['label' => '患者一覧', 'href' => '#'],
];
?>
<nav class="drawer" id="drawer" aria-label="メインメニュー" aria-hidden="true" inert>
  <button class="drawer-close" id="drawerClose" type="button" aria-label="メニューを閉じる">×</button>
  <?php foreach ($menu_items as $key => $item): ?>
    <a href="<?= htmlspecialchars($item['href']) ?>"<?= $key === $active_menu ? ' class="active"' : '' ?>>
      <?= htmlspecialchars($item['label']) ?>
    </a>
  <?php endforeach; ?>
</nav>
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
