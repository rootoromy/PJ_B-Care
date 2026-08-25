<?php
/**
 * B-Care Manager - ログイン認証処理
 * 配置先: manager/login_process.php
 *
 * login.php のフォームから非同期(fetch)で呼び出される。
 * 認証成功時はセッションを発行し、ログイン前に開こうとしていたページ
 * （redirectパラメータ、未指定時はindex.php）をJSONで返す。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';

header('Content-Type: application/json; charset=UTF-8');

$loginId  = isset($_POST['login_id']) ? trim($_POST['login_id']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
$redirect = mgr_safe_redirect_path($_POST['redirect'] ?? null);

if ($loginId === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'ユーザーIDとパスワードを入力してください。']);
    exit;
}

$account = mgr_authenticate($loginId, $password);

if ($account === null) {
    echo json_encode(['success' => false, 'message' => 'ユーザーIDまたはパスワードが正しくありません。']);
    exit;
}

session_regenerate_id(true);
$_SESSION['mgr_logged_in'] = true;
$_SESSION['mgr_staff_id']  = $account['staff_id'];
$_SESSION['mgr_user_id']   = $loginId;
$_SESSION['mgr_user_name'] = $account['name'];
$_SESSION['mgr_role']      = $account['role'];

echo json_encode(['success' => true, 'redirect' => $redirect]);
