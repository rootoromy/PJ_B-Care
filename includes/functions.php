<?php
/**
 * B-Care Manager - 共通関数・定数
 * 配置先: includes/functions.php
 */

// ---------------------------------------------------
// カラー定数
// ---------------------------------------------------
define('COLOR_GREEN_DARK',   '#1e5c2e');
define('COLOR_GREEN_MID',    '#2e7d42');
define('COLOR_GREEN_LIGHT',  '#e8f5ec');
define('COLOR_RISK_BG',      '#fdecea');
define('COLOR_RISK_TEXT',    '#c0392b');
define('COLOR_SAFE_BG',      '#e8f5ec');
define('COLOR_SAFE_TEXT',    '#1e5c2e');
define('COLOR_TRANSFER_BG',  '#fff3cd');
define('COLOR_TRANSFER_TEXT','#7d5a00');
define('COLOR_MALE',         '#2563eb');
define('COLOR_FEMALE',       '#db2777');

// ---------------------------------------------------
// カラー取得関数
// ---------------------------------------------------

/**
 * 転倒リスクのバッジカラーを返す
 * @param int $risk 0=なし, 1=あり
 * @return array ['bg' => '...', 'text' => '...', 'label' => '...']
 */
function getRiskColor(int $risk): array {
    if ($risk) {
        return [
            'bg'    => COLOR_RISK_BG,
            'text'  => COLOR_RISK_TEXT,
            'label' => 'リスクあり',
        ];
    }
    return [
        'bg'    => COLOR_SAFE_BG,
        'text'  => COLOR_SAFE_TEXT,
        'label' => 'なし',
    ];
}

/**
 * 性別のカラーとアイコンを返す
 * @param string $gender '男性' or '女性'
 * @return array ['color' => '...', 'icon' => '...']
 */
function getGenderStyle(string $gender): array {
    if ($gender === '男性') {
        return ['color' => COLOR_MALE,   'icon' => '♂'];
    }
    if ($gender === '女性') {
        return ['color' => COLOR_FEMALE, 'icon' => '♀'];
    }
    return ['color' => '#666', 'icon' => ''];
}

/**
 * 移動区分のバッジカラーを返す
 * @return array ['bg' => '...', 'text' => '...']
 */
function getTransferColor(): array {
    return [
        'bg'   => COLOR_TRANSFER_BG,
        'text' => COLOR_TRANSFER_TEXT,
    ];
}
