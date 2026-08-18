<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP6: 患者情報 → Article変換）
 * 実際にAIMSへは送信せず、変換結果のプレビューのみ行う。
 * ブラウザで直接開いて実行してください: /tools/aims_test_patient_convert.php?patient_id=P001
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

header('Content-Type: text/plain; charset=utf-8');

$patientId = $_GET['patient_id'] ?? '';

$mysqli = getDB();

if ($patientId !== '') {
    $stmt = $mysqli->prepare("
        SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse
        FROM patients p
        LEFT JOIN patients_staff ps_doc ON ps_doc.patient_id = p.patient_id AND ps_doc.role = 'doctor'
        LEFT JOIN staff doc ON doc.staff_id = ps_doc.staff_id
        LEFT JOIN patients_staff ps_nur ON ps_nur.patient_id = p.patient_id AND ps_nur.role = 'nurse'
        LEFT JOIN staff nur ON nur.staff_id = ps_nur.staff_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param('s', $patientId);
    $stmt->execute();
    $patients = [$stmt->get_result()->fetch_assoc()];
    $stmt->close();
    if (!$patients[0]) {
        echo "患者が見つかりません: {$patientId}\n";
        exit;
    }
} else {
    $result = $mysqli->query("
        SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse
        FROM patients p
        LEFT JOIN patients_staff ps_doc ON ps_doc.patient_id = p.patient_id AND ps_doc.role = 'doctor'
        LEFT JOIN staff doc ON doc.staff_id = ps_doc.staff_id
        LEFT JOIN patients_staff ps_nur ON ps_nur.patient_id = p.patient_id AND ps_nur.role = 'nurse'
        LEFT JOIN staff nur ON nur.staff_id = ps_nur.staff_id
        LIMIT 3
    ");
    $patients = $result->fetch_all(MYSQLI_ASSOC);
}

$body = patientsToAimsArticlesRequestBody($patients);

echo "=== 変換結果 (POST /articles 送信予定のボディ) ===\n\n";
echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
