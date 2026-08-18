<?php
/**
 * B-Care Mobile - ログイン認証処理
 * 配置先: mobile/sp_login_process.php
 *
 * sp_login.php のフォームから非同期(fetch)で呼び出される。
 * 認証成功時はセッションを発行し、ログイン前に開こうとしていたページ
 * （redirectパラメータ、未指定時はsp_patient_home.php）をJSONで返す。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';

header('Content-Type: application/json; charset=UTF-8');

$loginId  = isset($_POST['userId']) ? trim($_POST['userId']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
$redirect = sp_safe_redirect_path($_POST['redirect'] ?? null);

if ($loginId === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'ユーザーIDとパスワードを入力してください。']);
    exit;
}

$account = sp_authenticate($loginId, $password);

if ($account === null) {
    echo json_encode(['success' => false, 'message' => 'ユーザーIDまたはパスワードが正しくありません。']);
    exit;
}

session_regenerate_id(true);
$_SESSION['sp_logged_in'] = true;
$_SESSION['staff_id']     = $account['staff_id'];
$_SESSION['user_id']      = $loginId;
$_SESSION['user_name']    = $account['name'];
$_SESSION['role']         = $account['role'];

echo json_encode(['success' => true, 'redirect' => $redirect]);
