<?php
/**
 * B-Care Manager - 共通関数・定数
 * 配置先: includes/functions.php
 */

require_once __DIR__ . '/aims_functions.php';

// ---------------------------------------------------
// カラー定数
// ---------------------------------------------------
define('COLOR_GREEN_DARK',   '#1e5c2e');
define('COLOR_GREEN_MID',    '#2e7d42');
define('COLOR_GREEN_LIGHT',  '#e8f5ec');
define('COLOR_RISK_BG',      '#fdecea');
define('COLOR_RISK_TEXT',    '#c0392b');
define('COLOR_SAFE_BG',      '#e8f5ec');
define('COLOR_SAFE_TEXT',    '#1e5c2e');
define('COLOR_TRANSFER_BG',  '#fff3cd');
define('COLOR_TRANSFER_TEXT','#7d5a00');
define('COLOR_MALE',         '#2563eb');
define('COLOR_FEMALE',       '#db2777');

// ---------------------------------------------------
// カラー取得関数
// ---------------------------------------------------

/**
 * 転倒リスクのバッジカラーを返す
 * @param int $risk 0=なし, 1=あり
 * @return array ['bg' => '...', 'text' => '...', 'label' => '...']
 */
function getRiskColor(int $risk): array {
    if ($risk) {
        return [
            'bg'    => COLOR_RISK_BG,
            'text'  => COLOR_RISK_TEXT,
            'label' => '転倒危険度' . $risk,
        ];
    }
    return [
        'bg'    => COLOR_SAFE_BG,
        'text'  => COLOR_SAFE_TEXT,
        'label' => '転倒危険度0',
    ];
}

/**
 * 性別のカラーとアイコンを返す
 * @param string $gender '男性' or '女性'
 * @return array ['color' => '...', 'icon' => '...']
 */
function getGenderStyle(string $gender): array {
    if ($gender === '男性') {
        return ['color' => COLOR_MALE,   'icon' => '♂'];
    }
    if ($gender === '女性') {
        return ['color' => COLOR_FEMALE, 'icon' => '♀'];
    }
    return ['color' => '#666', 'icon' => ''];
}

/**
 * 移動区分のバッジカラーを返す
 * @return array ['bg' => '...', 'text' => '...']
 */
function getTransferColor(): array {
    return [
        'bg'   => COLOR_TRANSFER_BG,
        'text' => COLOR_TRANSFER_TEXT,
    ];
}

// ---------------------------------------------------
// 退院処理
// ---------------------------------------------------

/**
 * 未返却(status='stored')の預かり品件数を返す。
 */
function countStoredDeposits(mysqli $mysqli, string $patient_id): int {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM patient_deposits WHERE patient_id = ? AND status = 'stored'");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt;
}

/**
 * 退院処理を行う(Mobile/Manager共通)。
 * 未返却の預かり品が残っている場合は失敗させる。
 * 成功時はESLラベルの紐付けもDB・AIMS双方から解除する
 * (AIMS側への通知に失敗しても、退院処理自体は成立させる)。
 * @return array{success:bool, message:string}
 */
function dischargePatient(mysqli $mysqli, string $patient_id, string $staff_id): array {
    if (countStoredDeposits($mysqli, $patient_id) > 0) {
        return ['success' => false, 'message' => '未返却の預かり品が残っているため退院処理できません。先に返却処理をしてください。'];
    }

    $stmt = $mysqli->prepare("SELECT esl_label_code FROM patients WHERE patient_id = ?");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $label_code = $stmt->get_result()->fetch_assoc()['esl_label_code'] ?? null;
    $stmt->close();

    $stmt = $mysqli->prepare("
        UPDATE patients
        SET is_admitted = 0, discharged_at = NOW(), discharged_by = ?,
            esl_label_code = NULL, esl_synced_at = NULL
        WHERE patient_id = ? AND is_admitted = 1
    ");
    $stmt->bind_param('ss', $staff_id, $patient_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return ['success' => false, 'message' => '退院処理に失敗しました(既に退院済みの可能性があります)。'];
    }

    if (!empty($label_code)) {
        unlinkArticleFromLabel($label_code);
    }

    return ['success' => true, 'message' => '退院処理しました。'];
}

/**
 * 退院処理を取り消し、在院状態に戻す。
 * ESLラベルの再割当は行わない(Managerのラベル管理画面で別途手動対応)。
 * @return array{success:bool, message:string}
 */
function undischargePatient(mysqli $mysqli, string $patient_id): array {
    $stmt = $mysqli->prepare("
        UPDATE patients
        SET is_admitted = 1, discharged_at = NULL, discharged_by = NULL
        WHERE patient_id = ? AND is_admitted = 0
    ");
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    return $updated
        ? ['success' => true, 'message' => '退院を取り消しました。']
        : ['success' => false, 'message' => '取り消しに失敗しました(在院中の可能性があります)。'];
}
