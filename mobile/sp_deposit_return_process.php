<?php
/**
 * B-Care Mobile - 預かり品返却処理（複数件一括）
 * 配置先: mobile/sp_deposit_return_process.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['sp_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$body = json_decode(file_get_contents('php://input'), true);

$patient_id  = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';
$deposit_ids = is_array($body['deposit_ids'] ?? null) ? array_values(array_filter(array_map('intval', $body['deposit_ids']))) : [];
$returned_at = isset($body['returned_at']) ? trim((string)$body['returned_at']) : '';
$returned_by = isset($body['returned_by']) ? trim((string)$body['returned_by']) : '';
$return_to   = isset($body['return_to']) ? trim((string)$body['return_to']) : '';
$return_to_other = isset($body['return_to_other']) ? trim((string)$body['return_to_other']) : '';
$remarks     = isset($body['return_remarks']) ? trim((string)$body['return_remarks']) : '';

$valid_return_to = ['本人', '家族', 'その他'];

if ($patient_id === '' || empty($deposit_ids) || $returned_at === '' || $returned_by === '' || !in_array($return_to, $valid_return_to, true)) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません。']);
    exit;
}

if ($return_to === 'その他' && $return_to_other === '') {
    echo json_encode(['success' => false, 'message' => '返却先を入力してください。']);
    exit;
}

$returned_at_sql = str_replace('T', ' ', $returned_at);
$return_to_other_param = $return_to === 'その他' ? $return_to_other : null;
$remarks_param = $remarks !== '' ? $remarks : null;

$mysqli = getDB();

$placeholders = implode(',', array_fill(0, count($deposit_ids), '?'));
$sql = "
    UPDATE patient_deposits
    SET status = 'returned', returned_at = ?, returned_by = ?, return_to = ?, return_to_other = ?, return_remarks = ?
    WHERE patient_id = ? AND status = 'stored' AND deposit_id IN ($placeholders)
";

$types = 'sssss s' . str_repeat('i', count($deposit_ids));
$types = str_replace(' ', '', $types);

$stmt = $mysqli->prepare($sql);
$params = array_merge(
    [$returned_at_sql, $returned_by, $return_to, $return_to_other_param, $remarks_param, $patient_id],
    $deposit_ids
);
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => '返却処理に失敗しました。']);
    exit;
}

echo json_encode(['success' => true, 'redirect' => 'sp_deposit_list.php?patient_id=' . urlencode($patient_id)]);
