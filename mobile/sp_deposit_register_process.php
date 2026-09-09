<?php
/**
 * B-Care Mobile - 預かり品登録処理（マスタ選択・複数件一括）
 * 配置先: mobile/sp_deposit_register_process.php
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

$patient_id  = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';
$items       = is_array($body['items'] ?? null) ? $body['items'] : [];
$stored_at   = isset($body['stored_at']) ? trim((string)$body['stored_at']) : '';
$stored_by   = isset($body['stored_by']) ? trim((string)$body['stored_by']) : '';
$location_id = isset($body['storage_location_id']) ? (int)$body['storage_location_id'] : 0;
$remarks     = isset($body['remarks']) ? trim((string)$body['remarks']) : '';

if ($patient_id === '' || empty($items) || $stored_at === '' || $stored_by === '' || $location_id === 0) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません。']);
    exit;
}

$mysqli = getDB();

if (isPatientDischarged($mysqli, $patient_id)) {
    echo json_encode(['success' => false, 'message' => '退院済みの患者には預かり品を登録できません。']);
    exit;
}

$stored_at_sql = str_replace('T', ' ', $stored_at);

$stmt = $mysqli->prepare("
    INSERT INTO patient_deposits
        (patient_id, item_master_id, item_name, quantity, storage_location_id, status, stored_at, stored_by, remarks)
    VALUES (?, ?, ?, ?, ?, 'stored', ?, ?, ?)
");

$bind_item_master_id = null;
$bind_item_name = '';
$bind_quantity = 0;
$stmt->bind_param(
    'sisiisss',
    $patient_id,
    $bind_item_master_id,
    $bind_item_name,
    $bind_quantity,
    $location_id,
    $stored_at_sql,
    $stored_by,
    $remarks
);

$inserted = 0;
$mysqli->begin_transaction();

foreach ($items as $item) {
    $item_master_id = isset($item['item_master_id']) ? (int)$item['item_master_id'] : 0;
    $item_name = isset($item['name']) ? trim((string)$item['name']) : '';
    $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

    if ($item_name === '' || $quantity <= 0) {
        continue;
    }

    $bind_item_master_id = $item_master_id > 0 ? $item_master_id : null;
    $bind_item_name = $item_name;
    $bind_quantity = $quantity;

    if (!$stmt->execute()) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => '登録に失敗しました。']);
        exit;
    }
    $inserted++;
}

if ($inserted === 0) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => '登録する品目を選択してください。']);
    exit;
}

$mysqli->commit();

echo json_encode(['success' => true, 'redirect' => 'sp_deposit_list.php?patient_id=' . urlencode($patient_id)]);
