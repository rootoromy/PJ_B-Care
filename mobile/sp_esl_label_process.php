<?php
/**
 * B-Care Mobile - ラベル割当・解除処理
 * 配置先: mobile/sp_esl_label_process.php
 *
 * mobile/sp_esl_label.php（ラベル割当画面）と
 * mobile/includes/blocks/block_esl_label.php（患者ホームの「割当を解除」）
 * から呼び出される。実処理は includes/aims_functions.php の共通関数を
 * 利用するため、B-Care Manager側（manager/esl_management.php）と
 * 同じロジック・同じ結果になる。
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/sp_auth.php';
sp_require_login();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sp_patient_list.php');
    exit;
}

$patient_id = trim($_POST['patient_id'] ?? '');
if ($patient_id === '') {
    header('Location: sp_patient_list.php');
    exit;
}

$redirect_base = ($_POST['redirect'] ?? '') === 'home'
    ? 'sp_patient_home.php?patient_id=' . urlencode($patient_id)
    : 'sp_esl_label.php?patient_id=' . urlencode($patient_id);

$mysqli = getDB();

if (isset($_POST['assign_label'])) {
    $label_code = trim($_POST['label_code'] ?? '');
    $assign_message = $label_code === '' ? 'error' : assignEslLabelToPatient($mysqli, $patient_id, $label_code);
    $mysqli->close();
    header('Location: ' . $redirect_base . '&assign=' . $assign_message);
    exit;
}

if (isset($_POST['unassign_label'])) {
    unassignEslLabelFromPatient($mysqli, $patient_id);
    $mysqli->close();
    header('Location: ' . $redirect_base . '&assign=unassigned');
    exit;
}

$mysqli->close();
header('Location: ' . $redirect_base);
exit;
