<?php
/**
 * B-Care Mobile - ログアウト処理
 * 配置先: mobile/sp_logout.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
session_destroy();

header('Location: sp_login.php');
exit;
