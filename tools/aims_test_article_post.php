<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP5: Article登録・更新）
 * ブラウザで直接開いて実行してください: /tools/aims_test_article_post.php
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$url = AIMS_BASE_URL . '/articles';

$payload = [
    'dataList' => [
        [
            'data' => [
                'Product_ID' => 'TEST_BCARE_001',
            ],
            'id' => 'TEST_BCARE_001',
            'name' => '動作確認用テスト',
            'nfc' => '',
            'stationCode' => AIMS_STATION_CODE,
        ],
    ],
];
$json = json_encode($payload, JSON_UNESCAPED_UNICODE);

echo "=== Request ===\n";
echo "POST {$url}\n\n";
echo "--- Body ---\n";
echo $json . "\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "=== Response ===\n";
if ($body === false) {
    echo "cURLエラー: {$curlError}\n";
    exit;
}
echo "HTTP {$httpCode}\n\n";
echo "--- Raw Body ---\n";
echo $body . "\n";
