<?php
/**
 * 共通パーツ：ヘッダー
 * 配置先: manager/includes/header.php
 *
 * index.php / patient_detail.php / pictogram_setting.php など
 * manager配下の各画面から include されます。
 */
?>
<header>
    <img src="../img/logo/logo_transparent_white.png" alt="" width="26" height="26">
    <span class="logo">B-Care Manager</span>
        <span class="user">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="16" cy="16" r="15" stroke="#fff" stroke-width="1.5"/>
            <circle cx="16" cy="13" r="4.5" stroke="#fff" stroke-width="1.5"/>
            <path d="M7 26c0-5 4-8 9-8s9 3 9 8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        管理者
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </span>
</header>
