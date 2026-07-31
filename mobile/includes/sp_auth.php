<?php
/**
 * B-Care Mobile - 認証共通処理
 * 配置先: mobile/includes/sp_auth.php
 *
 * staff テーブルを参照してログイン認証を行う。
 */
require_once __DIR__ . '/../../includes/config.php';

function sp_authenticate(string $loginId, string $password): ?array {
    $mysqli = getDB();
    $stmt = $mysqli->prepare(
        'SELECT s.staff_id, s.name, s.password_hash, r.role_key
         FROM staff s
         JOIN roles r ON r.role_id = s.role_id
         WHERE s.login_id = ? AND s.is_active = 1'
    );
    $stmt->bind_param('s', $loginId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();

    if ($staff === null || !password_verify($password, $staff['password_hash'])) {
        return null;
    }

    return [
        'staff_id' => $staff['staff_id'],
        'name'     => $staff['name'],
        'role'     => $staff['role_key'],
    ];
}

function sp_require_login(): void {
    if (empty($_SESSION['sp_logged_in'])) {
        header('Location: sp_login.php');
        exit;
    }
}
