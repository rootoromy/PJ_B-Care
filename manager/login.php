<?php
/**
 * B-Care Manager - ログイン画面
 * 配置先: manager/login.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';

$redirect = mgr_safe_redirect_path($_GET['redirect'] ?? null);

if (!empty($_SESSION['mgr_logged_in'])) {
    header('Location: ' . $redirect);
    exit;
}

// 開発環境（localhostアクセス時）のみログイン情報を自動入力する
$isLocalDev = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$devUserId = $isLocalDev ? 'admin' : '';
$devPassword = $isLocalDev ? 'admin' : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>B-Care Manager ログイン</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/login.css?v=1">
</head>
<body>
  <main class="login-page">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="wave wave-top" aria-hidden="true"></div>

      <div class="login-content">
        <header class="brand">
          <h1 id="login-title">B-Care</h1>
          <p class="product-name">Manager</p>
          <p class="subtitle">医療従事者向けログイン</p>
        </header>

        <form id="loginForm" class="login-form" novalidate data-redirect="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-group">
            <label for="loginId">ユーザーID</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
                </svg>
              </span>
              <input
                id="loginId"
                name="login_id"
                type="text"
                autocomplete="username"
                placeholder="ユーザーIDを入力"
                aria-describedby="loginIdError"
                value="<?= htmlspecialchars($devUserId, ENT_QUOTES, 'UTF-8') ?>"
              >
            </div>
            <p id="loginIdError" class="error-message" aria-live="polite"></p>
          </div>

          <div class="form-group">
            <label for="password">パスワード</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4V6Zm3 10.73V18h-2v-1.27a2 2 0 1 1 2 0Z"/>
                </svg>
              </span>
              <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                placeholder="パスワードを入力"
                aria-describedby="passwordError"
                value="<?= htmlspecialchars($devPassword, ENT_QUOTES, 'UTF-8') ?>"
              >
              <button id="togglePassword" class="password-toggle" type="button" aria-label="パスワードを表示">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 5c-5.5 0-9.5 5.4-9.5 7s4 7 9.5 7 9.5-5.4 9.5-7S17.5 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                </svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A10.7 10.7 0 0 1 12 20C6.5 20 2.5 14.6 2.5 13c0-1 .9-2.8 2.7-4.5L2 3.3 3.3 2Zm4.5 9.2a4 4 0 0 0 5.1 5.1l-5.1-5.1ZM12 6c5.5 0 9.5 5.4 9.5 7 0 .7-.7 2.1-2 3.6l-2.2-2.2A4 4 0 0 0 9.6 6.7 10.8 10.8 0 0 1 12 6Z"/>
                </svg>
              </button>
            </div>
            <p id="passwordError" class="error-message" aria-live="polite"></p>
          </div>

          <button class="login-button" type="submit">ログイン</button>
          <p id="formMessage" class="form-message" aria-live="polite"></p>
        </form>
      </div>

      <div class="wave wave-bottom" aria-hidden="true"></div>
      <footer class="footer">@TOMARE CORPORATION</footer>
    </section>
  </main>

  <script src="js/login.js?v=2"></script>
</body>
</html>
