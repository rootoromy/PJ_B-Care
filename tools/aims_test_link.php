<?php
/**
 * AIMS API 動作確認用テストスクリプト（STEP7: Article↔ESLの紐付けをAPI化）
 * 実際にAIMSへ送信し、対象のESLラベルの表示を更新する。
 * ブラウザで直接開いて実行してください: /tools/aims_test_link.php?patient_id=P001
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

header('Content-Type: text/plain; charset=utf-8');

$patientId = $_GET['patient_id'] ?? '';
if ($patientId === '') {
    echo "patient_id を指定してください。例: ?patient_id=P001\n";
    exit;
}

$mysqli = getDB();
$stmt = $mysqli->prepare("
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
$stmt->bind_param('s', $patientId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo "患者が見つかりません: {$patientId}\n";
    exit;
}
if (empty($patient['esl_label_code'])) {
    echo "この患者にはesl_label_codeが未設定です: {$patientId}\n";
    exit;
}

echo "=== 紐付け実行 ===\n";
echo "patient_id={$patientId} labelCode={$patient['esl_label_code']}\n\n";

$result = linkPatientArticleToLabel($patient, $patient['esl_label_code']);

echo "HTTP {$result['httpCode']}\n\n";
if ($result['error'] !== '') {
    echo "cURLエラー: {$result['error']}\n";
    exit;
}
echo "--- Raw Body ---\n";
echo $result['rawBody'] . "\n";
