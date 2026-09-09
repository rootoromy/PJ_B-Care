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

/**
 * ログイン中ユーザーが「閲覧のみ」ロールかどうかを返す。
 * 食事介助者など、書き込み系操作(ピクトグラム変更・預かり品登録/返却・
 * 退院処理・ESLラベル割当/解除など)を一切行わせたくないユーザー向け。
 * 患者のピン留め(staff_pinned_patients)は表示上の個人設定のため対象外。
 */
function sp_is_viewer(): bool {
    return ($_SESSION['role'] ?? '') === 'viewer';
}

function sp_require_login(): void {
    if (empty($_SESSION['sp_logged_in'])) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: sp_login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

/**
 * ログイン後リダイレクト先として安全なパスかを検証する。
 * オープンリダイレクト対策として、スキーム付き絶対URLや "//" 始まりは拒否し、
 * サーバー内の相対パス（"/" 始まりの .php へのパス）のみ許可する。
 */
function sp_safe_redirect_path(?string $path, string $default = 'sp_patient_home.php'): string {
    if ($path === null || $path === '') {
        return $default;
    }
    if (preg_match('#^/[A-Za-z0-9_\-/]+\.php(\?[A-Za-z0-9_%.\-=&]*)?$#', $path) === 1) {
        return $path;
    }
    return $default;
}
