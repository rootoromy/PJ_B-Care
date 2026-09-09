<?php
/**
 * B-Care Mobile - 預かり品登録処理（その他・新規登録）
 * 配置先: mobile/sp_deposit_register_other_process.php
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

if (sp_is_viewer()) {
    echo json_encode(['success' => false, 'message' => '閲覧のみの権限のため、この操作はできません。']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$body = json_decode(file_get_contents('php://input'), true);

$patient_id     = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';
$item_name      = isset($body['item_name']) ? trim((string)$body['item_name']) : '';
$quantity       = isset($body['quantity']) ? (int)$body['quantity'] : 0;
$condition_note = isset($body['condition_note']) ? trim((string)$body['condition_note']) : '';
$stored_at      = isset($body['stored_at']) ? trim((string)$body['stored_at']) : '';
$stored_by      = isset($body['stored_by']) ? trim((string)$body['stored_by']) : '';
$location_id    = isset($body['storage_location_id']) ? (int)$body['storage_location_id'] : 0;
$remarks        = isset($body['remarks']) ? trim((string)$body['remarks']) : '';
$add_to_master  = !empty($body['add_to_master']);

if ($patient_id === '' || $item_name === '' || $quantity <= 0 || $stored_at === '' || $stored_by === '' || $location_id === 0) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません。']);
    exit;
}

$mysqli = getDB();

if (isPatientDischarged($mysqli, $patient_id)) {
    echo json_encode(['success' => false, 'message' => '退院済みの患者には預かり品を登録できません。']);
    exit;
}

$stored_at_sql = str_replace('T', ' ', $stored_at);
$condition_note_param = $condition_note !== '' ? $condition_note : null;

$mysqli->begin_transaction();

$item_master_id = null;

if ($add_to_master) {
    $stmt_master = $mysqli->prepare("
        INSERT INTO deposit_item_masters (name, unit) VALUES (?, '個')
        ON DUPLICATE KEY UPDATE name = name
    ");
    $stmt_master->bind_param('s', $item_name);
    if (!$stmt_master->execute()) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => '登録に失敗しました。']);
        exit;
    }

    $stmt_lookup = $mysqli->prepare("SELECT item_master_id FROM deposit_item_masters WHERE name = ?");
    $stmt_lookup->bind_param('s', $item_name);
    $stmt_lookup->execute();
    $row = $stmt_lookup->get_result()->fetch_assoc();
    $item_master_id = $row ? (int)$row['item_master_id'] : null;
}

$stmt = $mysqli->prepare("
    INSERT INTO patient_deposits
        (patient_id, item_master_id, item_name, quantity, condition_note, storage_location_id, status, stored_at, stored_by, remarks)
    VALUES (?, ?, ?, ?, ?, ?, 'stored', ?, ?, ?)
");
$stmt->bind_param(
    'sisisisss',
    $patient_id,
    $item_master_id,
    $item_name,
    $quantity,
    $condition_note_param,
    $location_id,
    $stored_at_sql,
    $stored_by,
    $remarks
);

if (!$stmt->execute()) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => '登録に失敗しました。']);
    exit;
}

$mysqli->commit();

echo json_encode(['success' => true, 'redirect' => 'sp_deposit_list.php?patient_id=' . urlencode($patient_id)]);
