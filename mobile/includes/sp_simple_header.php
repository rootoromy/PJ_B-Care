<?php
/**
 * 共通パーツ：シンプルヘッダー（特定患者に紐付かない画面用）
 * 配置先: mobile/includes/sp_simple_header.php
 *
 * 【呼び出し側で用意しておく変数】
 *   $page_title : ヘッダー中央に表示するタイトル文字列
 *
 * 【依存】
 *   セッション変数 $_SESSION['user_name']（未設定時は「ナース」表示）
 *
 * 【対応CSS】css/sp_common.css の .simple-header 系クラス
 *
 * 個別患者の情報を表示する mobile/includes/sp_header.php とは別に、
 * 患者一覧画面のように特定の患者に紐付かない画面向けの軽量ヘッダー。
 */
?>
<header class="simple-header">
  <span class="simple-header-wave" aria-hidden="true">
    <span class="wave-a"></span>
    <span class="wave-b"></span>
    <span class="wave-c"></span>
  </span>

  <button class="icon-button" type="button" aria-label="メニューを開く" id="menuButton" aria-controls="drawer" aria-expanded="false">
    <svg><use href="#i-menu"></use></svg>
  </button>

  <h1><?= htmlspecialchars($page_title ?? '') ?></h1>

  <div class="login-user">
    <span>Login: <?= htmlspecialchars($_SESSION['user_name'] ?? 'ナース') ?></span>
  </div>
</header>
