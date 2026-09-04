<?php
/**
 * B-Care Manager - ESL管理画面
 * 配置先: manager/esl_management.php
 *
 * AIMSから取得したESLラベルの一覧（Alive状態・電池残量・電波強度）を、
 * B-Care側の患者情報（esl_label_codeで紐付け）と突き合わせて表示する。
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';
mgr_require_login();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

$mysqli = getDB();

// ---------------------------------------------------
// Alive再チェック（任意操作）
// ---------------------------------------------------
$refresh_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_alive'])) {
    $refreshResult = refreshAimsLabelsAlive();
    $refresh_message = ($refreshResult['httpCode'] >= 200 && $refreshResult['httpCode'] < 300)
        ? 'success'
        : 'error';
    header('Location: esl_management.php?refresh=' . $refresh_message);
    exit;
}
$refresh_message = $_GET['refresh'] ?? '';

// ---------------------------------------------------
// 患者へのESLラベル割り当て・解除（未割当ラベルに患者を紐付ける）
// ---------------------------------------------------
function fetchPatientForEslDelivery(mysqli $mysqli, string $patient_id): ?array {
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
    $stmt->bind_param('s', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $patient;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_label'])) {
    $label_code = trim($_POST['label_code'] ?? '');
    $patient_id = trim($_POST['patient_id'] ?? '');
    $assign_message = 'error';

    if ($label_code !== '' && $patient_id !== '') {
        // 割当先の患者が未割当であること、かつそのラベルコードが他の患者に
        // 使われていないことの両方を条件にし、同時操作による二重割当を防ぐ
        $stmt = $mysqli->prepare("
            UPDATE patients
            SET esl_label_code = ?, esl_synced_at = NULL
            WHERE patient_id = ?
              AND (esl_label_code IS NULL OR esl_label_code = '')
              AND NOT EXISTS (
                  SELECT 1 FROM (SELECT patient_id FROM patients WHERE esl_label_code = ?) AS taken
              )
        ");
        $stmt->bind_param('sss', $label_code, $patient_id, $label_code);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        if ($updated) {
            $assign_message = 'success';
            // 割当と同時にAIMSへ配信する。配信に失敗しても割当自体は成立させる。
            $patientForEsl = fetchPatientForEslDelivery($mysqli, $patient_id);
            if ($patientForEsl) {
                $eslResult = linkPatientArticleToLabel($patientForEsl, $label_code);
                if ($eslResult['httpCode'] >= 200 && $eslResult['httpCode'] < 300) {
                    $stmtSync = $mysqli->prepare("UPDATE patients SET esl_synced_at = NOW() WHERE patient_id = ?");
                    $stmtSync->bind_param('s', $patient_id);
                    $stmtSync->execute();
                    $stmtSync->close();
                } else {
                    $assign_message = 'deliver_error';
                }
            }
        }
    }
    header('Location: esl_management.php?assign=' . $assign_message);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign_label'])) {
    $patient_id = trim($_POST['patient_id'] ?? '');
    if ($patient_id !== '') {
        $stmt = $mysqli->prepare("SELECT esl_label_code FROM patients WHERE patient_id = ?");
        $stmt->bind_param('s', $patient_id);
        $stmt->execute();
        $label_code = $stmt->get_result()->fetch_assoc()['esl_label_code'] ?? null;
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE patients SET esl_label_code = NULL, esl_synced_at = NULL WHERE patient_id = ?");
        $stmt->bind_param('s', $patient_id);
        $stmt->execute();
        $stmt->close();

        // DB側の紐付けを消すだけでなく、AIMS側にもラベルの解除を通知して
        // 物理ラベルの表示自体をクリアする(通知に失敗してもDB側の解除は成立させる)。
        if (!empty($label_code)) {
            unlinkArticleFromLabel($label_code);
        }
    }
    header('Location: esl_management.php?assign=unassigned');
    exit;
}
$assign_message = $_GET['assign'] ?? '';

// ---------------------------------------------------
// ラベルの非表示・再表示
// （AIMSダッシュボードで削除してもGET /labelsに残り続けるゴーストラベル対策）
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hide_label'])) {
    $label_code = trim($_POST['label_code'] ?? '');
    if ($label_code !== '') {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO esl_hidden_labels (label_code, hidden_by) VALUES (?, ?)");
        $staff_id = $_SESSION['mgr_staff_id'] ?? '';
        $stmt->bind_param('ss', $label_code, $staff_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: esl_management.php?assign=hidden');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unhide_label'])) {
    $label_code = trim($_POST['label_code'] ?? '');
    if ($label_code !== '') {
        $stmt = $mysqli->prepare("DELETE FROM esl_hidden_labels WHERE label_code = ?");
        $stmt->bind_param('s', $label_code);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: esl_management.php?assign=unhidden');
    exit;
}

// ---------------------------------------------------
// AIMSからラベル一覧・サマリーを取得
// ---------------------------------------------------
$labelsResult = getAimsLabels();
$summaryResult = getAimsLabelsSummary();

$aims_error = '';
$labels = [];
if ($labelsResult['error'] !== '' || $labelsResult['httpCode'] < 200 || $labelsResult['httpCode'] >= 300) {
    $aims_error = 'AIMSサーバーに接続できませんでした（' . ($labelsResult['error'] ?: 'HTTP ' . $labelsResult['httpCode']) . '）';
} else {
    $labels = $labelsResult['body'] ?? [];
}
$summary = $summaryResult['body'] ?? null;

// 非表示にしたラベルを除外する
$hiddenLabels = [];
$resHidden = $mysqli->query("SELECT label_code, hidden_at FROM esl_hidden_labels ORDER BY hidden_at DESC");
while ($row = $resHidden->fetch_assoc()) {
    $hiddenLabels[$row['label_code']] = $row;
}
$labels = array_values(array_filter($labels, function ($label) use ($hiddenLabels) {
    return !isset($hiddenLabels[$label['labelCode'] ?? '']);
}));

// ---------------------------------------------------
// B-Care側の患者情報（labelCode -> 患者）を取得
// ---------------------------------------------------
$patientsByLabel = [];
$res = $mysqli->query("SELECT patient_id, patient_name, ward_name, room_no, bed_no, esl_label_code FROM patients WHERE esl_label_code IS NOT NULL AND esl_label_code <> ''");
while ($row = $res->fetch_assoc()) {
    $patientsByLabel[$row['esl_label_code']] = $row;
}

// ---------------------------------------------------
// まだESLラベルが割り当てられていない患者一覧（割当ドロップダウン用）
// ---------------------------------------------------
$unassignedPatients = [];
$res2 = $mysqli->query("SELECT patient_id, patient_name, ward_name, room_no, bed_no FROM patients WHERE (esl_label_code IS NULL OR esl_label_code = '') AND is_admitted = 1 ORDER BY ward_name, room_no, bed_no");
while ($row = $res2->fetch_assoc()) {
    $unassignedPatients[] = $row;
}
$mysqli->close();

// ---------------------------------------------------
// 状態表示用のヘルパー
// ---------------------------------------------------
function statusBadgeClass(string $value): string {
    $ok = ['ONLINE', 'GOOD', 'EXCELLENT', 'SUCCESS'];
    $ng = ['OFFLINE', 'BAD', 'TIMEOUT'];
    if (in_array($value, $ok, true)) return 'badge-active';
    if (in_array($value, $ng, true)) return 'badge-admin';
    return 'badge-inactive';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - ESL管理</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/user_management.css?v=1">
    <link rel="stylesheet" href="css/esl_management.css?v=7">
</head>
<body>

<?php $active_menu = 'esl'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <div class="page-header">
            <div>
                <div class="page-title">ESL管理</div>
                <p class="page-desc">AIMSに接続されているESL（電子棚札）ラベルの状態を確認します。</p>
            </div>
            <form method="POST" action="esl_management.php">
                <button class="btn-add" type="submit" name="refresh_alive" value="1">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M4 4v6h6M20 20v-6h-6M4.5 15a8 8 0 0014.5 3.5M19.5 9A8 8 0 005 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Alive状態を再チェック</span>
                </button>
            </form>
        </div>

        <?php if ($refresh_message === 'success'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Alive状態の再チェックをAIMSに要求しました。反映まで少し時間がかかる場合があります。
            </div>
        <?php elseif ($refresh_message === 'error'): ?>
            <div class="alert-error">再チェックのリクエストに失敗しました。</div>
        <?php endif; ?>

        <?php if ($assign_message === 'success'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ESLラベルを割り当て、配信しました。
            </div>
        <?php elseif ($assign_message === 'deliver_error'): ?>
            <div class="alert-error">ラベルの割り当ては保存しましたが、AIMSへの配信に失敗しました。時間をおいて再度お試しください。</div>
        <?php elseif ($assign_message === 'unassigned'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                割り当てを解除しました。
            </div>
        <?php elseif ($assign_message === 'error'): ?>
            <div class="alert-error">割り当てに失敗しました。対象の患者が既に別のラベルへ割り当て済みの可能性があります。</div>
        <?php elseif ($assign_message === 'hidden'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ラベルを非表示にしました。
            </div>
        <?php elseif ($assign_message === 'unhidden'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ラベルの表示を元に戻しました。
            </div>
        <?php endif; ?>

        <?php if ($aims_error !== ''): ?>
            <div class="alert-error"><?= htmlspecialchars($aims_error) ?></div>
        <?php endif; ?>

        <?php if ($summary): ?>
        <!-- サマリーカード -->
        <div class="esl-summary-grid">
            <div class="card esl-summary-card">
                <div class="esl-summary-value"><?= (int)($summary['totalTagCount'] ?? 0) ?></div>
                <div class="esl-summary-label">総ラベル数</div>
            </div>
            <div class="card esl-summary-card">
                <div class="esl-summary-value"><?= (int)($summary['status']['successfulTagCount'] ?? 0) ?></div>
                <div class="esl-summary-label">配信成功</div>
            </div>
            <div class="card esl-summary-card">
                <div class="esl-summary-value"><?= (int)($summary['status']['unassignedTagCount'] ?? 0) ?></div>
                <div class="esl-summary-label">未割当</div>
            </div>
            <div class="card esl-summary-card">
                <div class="esl-summary-value"><?= (int)($summary['batteryStatus']['badBatteryTagCount'] ?? 0) ?></div>
                <div class="esl-summary-label">電池残量：低</div>
            </div>
            <div class="card esl-summary-card">
                <div class="esl-summary-value"><?= (int)($summary['signalStrength']['badSignalTagCount'] ?? 0) ?></div>
                <div class="esl-summary-label">電波強度：弱</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- テーブル -->
        <div class="table-wrap">
            <div class="table-meta">全<?= count($labels) ?>件</div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ラベルコード</th>
                            <th>患者</th>
                            <th>病棟／病室</th>
                            <th>Alive状態</th>
                            <th>電池</th>
                            <th>電波強度</th>
                            <th>配信状態</th>
                            <th>最終更新</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labels)): ?>
                            <tr><td colspan="8" class="no-data">ラベル情報を取得できませんでした</td></tr>
                        <?php endif; ?>
                        <?php foreach ($labels as $label): ?>
                            <?php
                                $code = $label['labelCode'] ?? '';
                                $patient = $patientsByLabel[$code] ?? null;
                                $alive = $label['sLabelStatus']['aliveStatus'] ?? '-';
                                $battery = $label['sLabelStatus']['battery'] ?? '-';
                                $signal = $label['sLabelStatus']['signalStrength'] ?? '-';
                                $status = $label['status'] ?? '-';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($code) ?></td>
                                <td>
                                    <?php if ($patient): ?>
                                        <div class="esl-patient-cell">
                                            <a href="patient_detail.php?patient_id=<?= urlencode($patient['patient_id']) ?>">
                                                <?= htmlspecialchars($patient['patient_name']) ?>（<?= htmlspecialchars($patient['patient_id']) ?>）
                                            </a>
                                            <form method="POST" action="esl_management.php" class="esl-assign-form" onsubmit="return confirm('この患者のラベル割り当てを解除しますか？');">
                                                <input type="hidden" name="patient_id" value="<?= htmlspecialchars($patient['patient_id']) ?>">
                                                <button type="submit" name="unassign_label" value="1" class="btn-unassign">解除</button>
                                            </form>
                                        </div>
                                    <?php elseif (empty($unassignedPatients)): ?>
                                        <span class="esl-unassigned">割当可能な患者がいません</span>
                                        <form method="POST" action="esl_management.php" class="esl-assign-form" onsubmit="return confirm('このラベルを一覧から非表示にしますか？（AIMS側の削除操作が反映されていない場合などに使用）');">
                                            <input type="hidden" name="label_code" value="<?= htmlspecialchars($code) ?>">
                                            <button type="submit" name="hide_label" value="1" class="btn-hide">非表示</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="esl-actions-row">
                                            <form method="POST" action="esl_management.php" class="esl-assign-form">
                                                <input type="hidden" name="label_code" value="<?= htmlspecialchars($code) ?>">
                                                <select name="patient_id" required>
                                                    <option value="">未割当の患者を選択</option>
                                                    <?php foreach ($unassignedPatients as $up): ?>
                                                        <option value="<?= htmlspecialchars($up['patient_id']) ?>">
                                                            <?= htmlspecialchars($up['patient_id']) ?>　<?= htmlspecialchars($up['patient_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="assign_label" value="1" class="btn-assign">ESLを割当</button>
                                            </form>
                                            <form method="POST" action="esl_management.php" class="esl-assign-form" onsubmit="return confirm('このラベルを一覧から非表示にしますか？（AIMS側の削除操作が反映されていない場合などに使用）');">
                                                <input type="hidden" name="label_code" value="<?= htmlspecialchars($code) ?>">
                                                <button type="submit" name="hide_label" value="1" class="btn-hide">非表示</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient): ?>
                                        <?= htmlspecialchars($patient['ward_name']) ?> <?= htmlspecialchars($patient['room_no']) ?>号室<?= htmlspecialchars($patient['bed_no']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= statusBadgeClass($alive) ?>"><?= htmlspecialchars($alive) ?></span></td>
                                <td><span class="badge <?= statusBadgeClass($battery) ?>"><?= htmlspecialchars($battery) ?></span></td>
                                <td><span class="badge <?= statusBadgeClass($signal) ?>"><?= htmlspecialchars($signal) ?></span></td>
                                <td><span class="badge <?= statusBadgeClass($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td><?= htmlspecialchars($label['statusUpdateTime'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($hiddenLabels)): ?>
        <div class="table-wrap" style="margin-top:18px;">
            <div class="table-meta">非表示にしたラベル（<?= count($hiddenLabels) ?>件）</div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ラベルコード</th>
                            <th>非表示にした日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hiddenLabels as $code => $hidden): ?>
                            <tr>
                                <td><?= htmlspecialchars($code) ?></td>
                                <td><?= htmlspecialchars($hidden['hidden_at']) ?></td>
                                <td>
                                    <form method="POST" action="esl_management.php" class="esl-assign-form">
                                        <input type="hidden" name="label_code" value="<?= htmlspecialchars($code) ?>">
                                        <button type="submit" name="unhide_label" value="1" class="btn-assign">表示に戻す</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
