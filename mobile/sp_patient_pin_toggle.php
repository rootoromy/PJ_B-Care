<?php
/**
 * B-Care Mobile - 患者ピックアップ（ピン留め）切り替え処理
 * 配置先: mobile/sp_patient_pin_toggle.php
 *
 * ログイン中のスタッフ単位で staff_pinned_patients を追加/削除する。
 * mobile/sp_patient_list.php から fetch で呼び出される。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['sp_logged_in']) || empty($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$body = json_decode(file_get_contents('php://input'), true);

$staff_id   = $_SESSION['staff_id'];
$patient_id = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';

if ($patient_id === '') {
    echo json_encode(['success' => false, 'message' => '患者IDが指定されていません。']);
    exit;
}

$mysqli = getDB();

$stmt_check = $mysqli->prepare("SELECT 1 FROM staff_pinned_patients WHERE staff_id = ? AND patient_id = ?");
$stmt_check->bind_param('ss', $staff_id, $patient_id);
$stmt_check->execute();
$already_pinned = (bool)$stmt_check->get_result()->fetch_row();
$stmt_check->close();

if ($already_pinned) {
    $stmt = $mysqli->prepare("DELETE FROM staff_pinned_patients WHERE staff_id = ? AND patient_id = ?");
    $stmt->bind_param('ss', $staff_id, $patient_id);
    $stmt->execute();
    $stmt->close();
    $pinned = false;
} else {
    $stmt = $mysqli->prepare("INSERT IGNORE INTO staff_pinned_patients (staff_id, patient_id) VALUES (?, ?)");
    $stmt->bind_param('ss', $staff_id, $patient_id);
    $stmt->execute();
    $stmt->close();
    $pinned = true;
}

$mysqli->close();

echo json_encode(['success' => true, 'pinned' => $pinned]);
