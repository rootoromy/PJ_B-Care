<?php
/**
 * B-Care Manager - 患者詳細画面
 * 配置先: manager/patient_detail.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';
mgr_require_login();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/aims_functions.php';

// ---------------------------------------------------
// 患者ID取得
// ---------------------------------------------------
$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
if ($patient_id === '') {
    header('Location: index.php');
    exit;
}

$mysqli = getDB();

// ---------------------------------------------------
// 患者情報取得
// ---------------------------------------------------
$stmt = $mysqli->prepare("
    SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse, pic.pictogram_names, pic.pictogram_ids
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
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('<p style="color:red;">患者が見つかりません。</p>');
}

$stmt_dup = $mysqli->prepare("
    SELECT COUNT(*) AS cnt
    FROM patients
    WHERE patient_name = ? AND patient_id != ?
");
$stmt_dup->bind_param('ss', $patient['patient_name'], $patient_id);
$stmt_dup->execute();
$dup_count = (int)($stmt_dup->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_dup->close();
$patient['dup_count'] = $dup_count;
$has_namesake = $dup_count > 0 || (int)($patient['has_namesake'] ?? 0) === 1;

// ---------------------------------------------------
// ESL配信処理
// ---------------------------------------------------
$esl_deliver_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deliver_esl']) && !empty($patient['esl_label_code'])) {
    $result = linkPatientArticleToLabel($patient, $patient['esl_label_code']);
    $esl_deliver_result = ($result['httpCode'] >= 200 && $result['httpCode'] < 300)
        ? ['success' => true,  'message' => '配信リクエストを送信しました']
        : ['success' => false, 'message' => 'HTTP ' . $result['httpCode'] . ($result['error'] ? ' ' . $result['error'] : '')];
}

// ---------------------------------------------------
// QRコード生成
// ---------------------------------------------------
require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';
define('CACHE_DIR', __DIR__ . '/../qr_cache/');
define('QR_SIZE',   5);
define('QR_MARGIN', 1);

function generateQRBase64(string $relativeUrl): string {
    $fullUrl   = BCARE_BASE_URL . '/' . ltrim($relativeUrl, '/');
    $cacheFile = CACHE_DIR . md5($fullUrl) . '.png';
    if (!file_exists($cacheFile)) {
        QRcode::png($fullUrl, $cacheFile, QR_ECLEVEL_M, QR_SIZE, QR_MARGIN);
    }
    return 'data:image/png;base64,' . base64_encode(file_get_contents($cacheFile));
}

$qr_base64 = !empty($patient['qr_url']) ? generateQRBase64($patient['qr_url']) : '';

// ---------------------------------------------------
// ピクトグラム取得
// ---------------------------------------------------
$stmt_pic = $mysqli->prepare("
    SELECT p.pictogram_id, p.name, p.category, p.image_path
    FROM patient_pictograms pp
    JOIN pictograms p ON pp.pictogram_id = p.pictogram_id
    WHERE pp.patient_id = ?
    ORDER BY pp.display_order
");
$stmt_pic->bind_param('s', $patient_id);
$stmt_pic->execute();
$pictograms = $stmt_pic->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pic->close();

$mysqli->close();

// ---------------------------------------------------
// カラー関数
// ---------------------------------------------------
$risk     = getRiskColor((int)$patient['fall_risk']);
$gender   = getGenderStyle($patient['gender'] ?? '');
$transfer = getTransferColor();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - 患者詳細</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/patient_detail.css?v=8">
</head>
<body>

<?php $active_menu = 'patients'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <a href="index.php" class="back-link">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            戻る
        </a>
        <div class="page-header">
            <div class="page-title">患者詳細</div>
        </div>

        <!-- メイングリッド -->
        <div class="detail-grid">

            <!-- 左カラム：患者基本情報 -->
            <div class="card">
                <div class="card-title">患者基本情報</div>

                <div class="info-row">
                    <span class="info-label">患者ID</span>
                    <span class="info-value"><?= htmlspecialchars($patient['patient_id']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">患者氏名</span>
                    <span class="info-value large"><?= htmlspecialchars($patient['patient_name']) ?></span>
                    <?php if ($has_namesake): ?>
                        <span class="badge" style="background:<?= COLOR_RISK_BG ?>; color:<?= COLOR_RISK_TEXT ?>; width:fit-content; margin-top:4px;">
                            同姓同名有
                        </span>
                    <?php endif; ?>
                </div>
                <div class="info-row">
                    <span class="info-label">性別</span>
                    <span class="info-value" style="color:<?= $gender['color'] ?>; font-weight:bold;">
                        <?= htmlspecialchars($patient['gender'] ?? '-') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">年齢</span>
                    <span class="info-value"><?= htmlspecialchars($patient['age'] ?? '-') ?>歳</span>
                </div>

                <hr class="info-divider">

                <div class="info-row">
                    <span class="info-label">主治医</span>
                    <span class="info-value"><?= htmlspecialchars($patient['doctor_name'] ?? '') ?: '未設定' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">受持看護師</span>
                    <span class="info-value"><?= htmlspecialchars($patient['primary_nurse'] ?? '') ?: '未設定' ?></span>
                </div>
                <hr class="info-divider">

                <div class="info-row">
                    <span class="info-label">病棟</span>
                    <span class="info-value"><?= htmlspecialchars($patient['ward_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">病室</span>
                    <span class="info-value"><?= htmlspecialchars($patient['room_no']) ?>号室</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ベッド番号</span>
                    <span class="info-value"><?= htmlspecialchars($patient['bed_no']) ?>ベッド</span>
                </div>

                <hr class="info-divider">

                <div class="info-row">
                    <span class="info-label">転倒リスク</span>
                    <span class="badge" style="background:<?= $risk['bg'] ?>; color:<?= $risk['text'] ?>;">
                        <?= $risk['label'] ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">移送区分</span>
                    <span class="badge" style="background:<?= $transfer['bg'] ?>; color:<?= $transfer['text'] ?>;">
                        <?= htmlspecialchars($patient['transfer_type']) ?>
                    </span>
                </div>
            </div>

            <!-- 中央カラム：QRコード -->
            <div>
                <div class="bottom-grid" style="margin-top:0;">
                    <!-- 最終更新日時・ESL配信状態 -->
                    <div class="card">
                        <div class="card-title">配信ステータス</div>
                        <div class="info-row">
                            <span class="info-label">最終更新日時</span>
                            <span class="info-value" style="font-size:0.85rem;">
                                <?= htmlspecialchars($patient['updated_at']) ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">ESL配信状態</span>
                            <?php if ($esl_deliver_result !== null): ?>
                                <div class="esl-status <?= $esl_deliver_result['success'] ? 'esl-ok' : 'esl-ng' ?>">
                                    <span class="dot"></span>
                                    <span><?= $esl_deliver_result['success'] ? '配信済み' : '配信失敗' ?></span>
                                </div>
                                <div class="esl-sub"><?= htmlspecialchars($esl_deliver_result['message']) ?></div>
                            <?php elseif (empty($patient['esl_label_code'])): ?>
                                <div class="esl-status esl-ng">
                                    <span class="dot"></span>
                                    <span>未設定</span>
                                </div>
                                <div class="esl-sub">この患者にはESLラベルが割り当てられていません</div>
                            <?php else: ?>
                                <div class="esl-status">
                                    <span class="dot"></span>
                                    <span>未配信</span>
                                </div>
                                <div class="esl-sub">ラベル: <?= htmlspecialchars($patient['esl_label_code']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($patient['esl_label_code'])): ?>
                            <form method="POST" action="patient_detail.php?patient_id=<?= urlencode($patient_id) ?>" style="margin-top:8px;">
                                <button type="submit" name="deliver_esl" value="1" class="btn-pictogram">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    ESL配信
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="card qr-card">
                        <div class="card-title">QRコード</div>
                        <div class="qr-wrap">
                            <?php if ($qr_base64): ?>
                                <img src="<?= $qr_base64 ?>" alt="QRコード">
                            <?php else: ?>
                                <p style="color:#aaa; font-size:0.85rem;">QRコードURLが設定されていません</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 表示中ピクトグラム -->
                <div class="card" style="margin-top:18px;">
                    <div class="card-title">表示中ピクトグラム（<?= count($pictograms) ?>件）</div>
                    <?php if (!empty($pictograms)): ?>
                        <div class="pictogram-grid">
                            <?php foreach ($pictograms as $pic): ?>
                                <div class="pictogram-item">
                                    <img src="../<?= htmlspecialchars($pic['image_path']) ?>"
                                         alt="<?= htmlspecialchars($pic['name']) ?>"
                                         onerror="this.style.display='none'">
                                    <span><?= htmlspecialchars($pic['name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#aaa; font-size:0.85rem;">ピクトグラムが設定されていません</p>
                    <?php endif; ?>

                    <a href="pictogram_setting.php?patient_id=<?= urlencode($patient_id) ?>" class="btn-pictogram">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        ピクトグラム設定へ
                    </a>
                    <div class="btn-desc">表示するピクトグラムの追加・編集ができます</div>
                </div>
            </div>


        </div><!-- /detail-grid -->
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
