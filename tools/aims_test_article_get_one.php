<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP5確認: 登録したArticleを1件取得）
 * ブラウザで直接開いて実行してください: /tools/aims_test_article_get_one.php?articleId=TEST_BCARE_001
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$articleId = $_GET['articleId'] ?? 'TEST_BCARE_001';

$url = AIMS_BASE_URL . '/articles/article?' . http_build_query([
    'articleId' => $articleId,
    'stationCode' => AIMS_STATION_CODE,
]);

echo "=== Request ===\n";
echo "GET {$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
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
