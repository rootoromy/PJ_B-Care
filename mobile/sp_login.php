<?php
/**
 * B-Care Manager - モバイル版ログイン画面
 * 配置先: mobile/sp_login.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';

$redirect = sp_safe_redirect_path($_GET['redirect'] ?? null);

if (!empty($_SESSION['sp_logged_in'])) {
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
  <title>B-Care Login</title>
  <link rel="icon" href="../favicon.ico">
  <link rel="stylesheet" href="css/sp_login.css">
</head>
<body>
  <main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
      <div class="login-content">
        <div class="brand">
          <h1 id="login-title">B-Care</h1>
          <p  class="product-name">Mobile</p>
          <p>医療従事者向けログイン</p>
        </div>

        <form id="loginForm" novalidate data-redirect="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-group">
            <label for="userId">ユーザーID</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="img">
                  <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"/>
                </svg>
              </span>
              <input id="userId" name="userId" type="text" autocomplete="username" placeholder="ユーザーIDを入力" aria-describedby="userIdError" value="<?= htmlspecialchars($devUserId, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <p class="error-message" id="userIdError"></p>
          </div>

          <div class="form-group">
            <label for="password">パスワード</label>
            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="img">
                  <path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2Zm-7-2a2 2 0 0 1 4 0v2h-4V6Zm2 11.25A1.75 1.75 0 1 1 12 13a1.75 1.75 0 0 1 0 3.5v.75Z"/>
                </svg>
              </span>
              <input id="password" name="password" type="password" autocomplete="current-password" placeholder="パスワードを入力" aria-describedby="passwordError" value="<?= htmlspecialchars($devPassword, ENT_QUOTES, 'UTF-8') ?>">
              <button type="button" class="password-toggle" id="passwordToggle" aria-label="パスワードを表示">
                <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 5c-5.5 0-9.5 4.8-10.7 6.4a1 1 0 0 0 0 1.2C2.5 14.2 6.5 19 12 19s9.5-4.8 10.7-6.4a1 1 0 0 0 0-1.2C21.5 9.8 17.5 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>
                </svg>
                <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m3.3 2 18.7 18.7-1.3 1.3-3.1-3.1A10.9 10.9 0 0 1 12 20C6.5 20 2.5 15.2 1.3 13.6a1 1 0 0 1 0-1.2 20.7 20.7 0 0 1 4-4.1L2 3.3 3.3 2Zm5.1 8.4 1.7 1.7A2 2 0 0 0 12 14c.3 0 .6-.1.9-.2l1.7 1.7A4 4 0 0 1 8.4 10.4ZM12 4c5.5 0 9.5 4.8 10.7 6.4a1 1 0 0 1 0 1.2 19.7 19.7 0 0 1-3.1 3.4l-2.1-2.1A6 6 0 0 0 11.1 6L9.5 4.4A11.1 11.1 0 0 1 12 4Z"/>
                </svg>
              </button>
            </div>
            <p class="error-message" id="passwordError"></p>
          </div>

          <button type="submit" class="login-button">ログイン</button>
          <p class="form-status" id="formStatus" aria-live="polite"></p>
        </form>
      </div>

      <div class="wave-background-top" aria-hidden="true">
        <span class="wave wave-one"></span>
        <span class="wave wave-two"></span>
        <span class="wave wave-three"></span>
      </div>

      <div class="wave-background" aria-hidden="true">
        <span class="wave wave-one"></span>
        <span class="wave wave-two"></span>
        <span class="wave wave-three"></span>
      </div>

      <footer>@TOMARE CORPORATION</footer>
    </section>
  </main>

  <script src="js/sp_login.js?v=2"></script>
</body>
</html>
