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

// ---------------------------------------------------
// B-Care側の患者情報（labelCode -> 患者）を取得
// ---------------------------------------------------
$patientsByLabel = [];
$res = $mysqli->query("SELECT patient_id, patient_name, ward_name, room_no, bed_no, esl_label_code FROM patients WHERE esl_label_code IS NOT NULL AND esl_label_code <> ''");
while ($row = $res->fetch_assoc()) {
    $patientsByLabel[$row['esl_label_code']] = $row;
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
    <link rel="stylesheet" href="css/esl_management.css?v=1">
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
                                        <a href="patient_detail.php?patient_id=<?= urlencode($patient['patient_id']) ?>">
                                            <?= htmlspecialchars($patient['patient_name']) ?>（<?= htmlspecialchars($patient['patient_id']) ?>）
                                        </a>
                                    <?php else: ?>
                                        <span class="esl-unassigned">未割当</span>
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
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
