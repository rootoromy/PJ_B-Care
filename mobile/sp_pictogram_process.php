<?php
/**
 * B-Care Mobile - ピクトグラム設定保存処理
 * 配置先: mobile/sp_pictogram_process.php
 *
 * patient_pictograms を全削除→選択されたものを再登録する。
 * manager/pictogram_setting.php（B-Care Manager側）と全く同じ方式・同じ
 * テーブルを更新するため、モバイルで変更すれば Manager 側にもそのまま
 * 反映される（別途の同期処理は不要）。
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

$patient_id     = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';
$pictogram_ids  = is_array($body['pictogram_ids'] ?? null) ? $body['pictogram_ids'] : [];

if ($patient_id === '') {
    echo json_encode(['success' => false, 'message' => '患者IDが指定されていません。']);
    exit;
}

$mysqli = getDB();

$stmt_check = $mysqli->prepare("SELECT 1 FROM patients WHERE patient_id = ?");
$stmt_check->bind_param('s', $patient_id);
$stmt_check->execute();
$patient_exists = (bool)$stmt_check->get_result()->fetch_row();
$stmt_check->close();

if (!$patient_exists) {
    echo json_encode(['success' => false, 'message' => '患者が見つかりません。']);
    exit;
}

$mysqli->begin_transaction();

$stmt_del = $mysqli->prepare("DELETE FROM patient_pictograms WHERE patient_id = ?");
$stmt_del->bind_param('s', $patient_id);
$stmt_del->execute();
$stmt_del->close();

if (!empty($pictogram_ids)) {
    $stmt_ins = $mysqli->prepare("INSERT INTO patient_pictograms (patient_id, pictogram_id, display_order) VALUES (?, ?, ?)");
    $order = 0;
    foreach ($pictogram_ids as $pic_id) {
        $pic_id = (int)$pic_id;
        if ($pic_id <= 0) {
            continue;
        }
        $order++;
        $stmt_ins->bind_param('sii', $patient_id, $pic_id, $order);
        $stmt_ins->execute();
    }
    $stmt_ins->close();
}

$mysqli->commit();
$mysqli->close();

echo json_encode(['success' => true, 'redirect' => 'sp_patient_home.php?patient_id=' . urlencode($patient_id)]);
