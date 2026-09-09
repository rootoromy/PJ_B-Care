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

if (sp_is_viewer()) {
    echo json_encode(['success' => false, 'message' => '閲覧のみの権限のため、この操作はできません。']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

$body = json_decode(file_get_contents('php://input'), true);

$patient_id     = isset($body['patient_id']) ? trim((string)$body['patient_id']) : '';
$pictogram_ids  = is_array($body['pictogram_ids'] ?? null) ? $body['pictogram_ids'] : [];

if ($patient_id === '') {
    echo json_encode(['success' => false, 'message' => '患者IDが指定されていません。']);
    exit;
}

$mysqli = getDB();

$stmt_check = $mysqli->prepare("SELECT is_admitted FROM patients WHERE patient_id = ?");
$stmt_check->bind_param('s', $patient_id);
$stmt_check->execute();
$patient_row = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$patient_row) {
    echo json_encode(['success' => false, 'message' => '患者が見つかりません。']);
    exit;
}

if ((int)$patient_row['is_admitted'] !== 1) {
    echo json_encode(['success' => false, 'message' => '退院済みの患者はピクトグラムを変更できません。']);
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

// 保存直後にESLラベルへも即時反映する(ラベル未割当の患者はスキップ)。
// 配信に失敗しても、ピクトグラム自体の保存は成功しているので処理は継続する。
$stmt_patient = $mysqli->prepare("
    SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse, pic.pictogram_names, pic.pictogram_ids,
        (SELECT COUNT(*) FROM patients p2 WHERE p2.patient_name = p.patient_name AND p2.patient_id != p.patient_id) AS dup_count
    FROM patients p
    LEFT JOIN patients_staff ps_doc ON ps_doc.patient_id = p.patient_id AND ps_doc.role = 'doctor'
    LEFT JOIN staff doc ON doc.staff_id = ps_doc.staff_id
    LEFT JOIN patients_staff ps_nur ON ps_nur.patient_id = p.patient_id AND ps_nur.role = 'nurse'
    LEFT JOIN staff nur ON nur.staff_id = ps_nur.staff_id
    LEFT JOIN (
        SELECT pp.patient_id,
            GROUP_CONCAT(pg.name ORDER BY pp.display_order SEPARATOR '、') AS pictogram_names,
            GROUP_CONCAT(pg.pictogram_id ORDER BY pp.display_order SEPARATOR ',') AS pictogram_ids
        FROM patient_pictograms pp
        JOIN pictograms pg ON pg.pictogram_id = pp.pictogram_id
        GROUP BY pp.patient_id
    ) pic ON pic.patient_id = p.patient_id
    WHERE p.patient_id = ?
");
$stmt_patient->bind_param('s', $patient_id);
$stmt_patient->execute();
$patientForEsl = $stmt_patient->get_result()->fetch_assoc();
$stmt_patient->close();

$esl_delivered = false;
if ($patientForEsl && !empty($patientForEsl['esl_label_code'])) {
    $eslResult = linkPatientArticleToLabel($patientForEsl, $patientForEsl['esl_label_code']);
    $esl_delivered = ($eslResult['httpCode'] >= 200 && $eslResult['httpCode'] < 300);
    if ($esl_delivered) {
        $stmtSync = $mysqli->prepare("UPDATE patients SET esl_synced_at = NOW() WHERE patient_id = ?");
        $stmtSync->bind_param('s', $patient_id);
        $stmtSync->execute();
        $stmtSync->close();
    }
}

$mysqli->close();

echo json_encode([
    'success' => true,
    'redirect' => 'sp_patient_home.php?patient_id=' . urlencode($patient_id),
    'esl_delivered' => $esl_delivered,
]);
