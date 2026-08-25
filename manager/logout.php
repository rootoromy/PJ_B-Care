<?php
/**
 * B-Care Manager - ログアウト処理
 * 配置先: manager/logout.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['mgr_logged_in'],
    $_SESSION['mgr_staff_id'],
    $_SESSION['mgr_user_id'],
    $_SESSION['mgr_user_name'],
    $_SESSION['mgr_role']
);
session_regenerate_id(true);

header('Location: login.php');
exit;
