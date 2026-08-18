<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP7準備: 稼働中ラベル一覧の確認）
 * ブラウザで直接開いて実行してください: /tools/aims_test_labels_list.php
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$url = AIMS_BASE_URL . '/labels?' . http_build_query([
    'stationCode' => AIMS_STATION_CODE,
    'page' => 0,
    'size' => 50,
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

$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSONパースエラー: " . json_last_error_msg() . "\n";
    echo $body . "\n";
    exit;
}

echo "件数: " . count($data) . "\n\n";
foreach ($data as $i => $label) {
    $code = $label['labelCode'] ?? '(不明)';
    $type = $label['type'] ?? '';
    $status = $label['status'] ?? '';
    $alive = $label['sLabelStatus']['aliveStatus'] ?? '';
    $battery = $label['sLabelStatus']['battery'] ?? '';
    $signal = $label['sLabelStatus']['signalStrength'] ?? '';
    $articleId = $label['articleList'][0]['id'] ?? '(未割当)';
    echo "[{$i}] labelCode={$code} type={$type} status={$status} alive={$alive} battery={$battery} signal={$signal} article={$articleId}\n";
}
