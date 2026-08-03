<?php
/**
 * B-Care Mobile - 最新バイタル取得（AJAX用）
 * 配置先: mobile/sp_vitals_latest.php
 *
 * 患者ホーム画面のバイタルカードの「更新」ボタンから呼ばれる。
 * ページ遷移せずに最新1件のバイタルをJSONで返す。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['sp_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_idが指定されていません。']);
    exit;
}

$mysqli = getDB();

$stmt = $mysqli->prepare("
    SELECT measured_at, temperature, systolic_bp, diastolic_bp, pulse, spo2, respiratory_rate
    FROM vitals
    WHERE patient_id = ?
    ORDER BY measured_at DESC
    LIMIT 1
");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$latest_vital = $stmt->get_result()->fetch_assoc();
$stmt->close();
$mysqli->close();

function vitalValue($latest_vital, $field) {
    if (!$latest_vital || $latest_vital[$field] === null || $latest_vital[$field] === '') {
        return null;
    }
    return $latest_vital[$field];
}

echo json_encode([
    'success'      => true,
    'measured_at'  => $latest_vital ? date('Y/m/d H:i', strtotime($latest_vital['measured_at'])) : null,
    'temperature'  => vitalValue($latest_vital, 'temperature'),
    'systolic_bp'  => vitalValue($latest_vital, 'systolic_bp'),
    'diastolic_bp' => vitalValue($latest_vital, 'diastolic_bp'),
    'pulse'        => vitalValue($latest_vital, 'pulse'),
    'spo2'         => vitalValue($latest_vital, 'spo2'),
    'respiratory_rate' => vitalValue($latest_vital, 'respiratory_rate'),
]);
