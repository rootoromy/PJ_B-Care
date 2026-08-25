<?php
/**
 * 共通パーツ：サイドバー
 * 配置先: manager/includes/sidebar.php
 *
 * 【呼び出し側で用意しておく変数】
 *   $active_menu : 現在ページのメニューキー
 *                  ('patients' / 'users' / 'esl')
 *                  未設定ならどれもハイライトしない
 *
 * 新しいメニュー項目を増やしたい場合は、下の $menu_items 配列に追記するだけでOK。
 *
 * index.php / patient_detail.php / pictogram_setting.php など
 * manager配下の各画面から include されます。
 */

$active_menu = $active_menu ?? '';

$menu_items = [
    'patients' => [
        'label' => '患者一覧',
        'href'  => 'index.php',
        'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ],
    'users' => [
        'label' => 'ユーザー管理',
        'href'  => 'user_management.php',
        'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ],
    'esl' => [
        'label' => 'ESL管理',
        'href'  => 'esl_management.php',
        'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M7 9h6M7 13h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ],
];
?>
<aside>
    <nav>
        <?php foreach ($menu_items as $key => $item): ?>
            <a href="<?= htmlspecialchars($item['href']) ?>"<?= $key === $active_menu ? ' class="active"' : '' ?>>
                <?= $item['icon'] ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="logout">
        <a href="logout.php">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            ログアウト
        </a>
    </div>
</aside>
