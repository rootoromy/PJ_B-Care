<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP3: PHPからGET実行 / STEP4: JSON解析）
 * ブラウザで直接開いて実行してください: /tools/aims_test_articles.php
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$url = AIMS_BASE_URL . '/articles?' . http_build_query([
    'stationCode' => AIMS_STATION_CODE,
    'page' => 0,
    'size' => 20,
]);

echo "=== Request ===\n";
echo "GET {$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
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
echo $body . "\n\n";

echo "--- Parsed (json_decode) ---\n";
$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSONパースエラー: " . json_last_error_msg() . "\n";
    exit;
}
print_r($data);

echo "\n--- Summary ---\n";
if (is_array($data)) {
    echo "件数: " . count($data) . "\n";
    foreach ($data as $i => $article) {
        $id = $article['id'] ?? '(不明)';
        $name = $article['name'] ?? '(不明)';
        echo "[{$i}] id={$id} name={$name}\n";
    }
}
