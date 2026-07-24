<?php
/**
 * B-Care Manager - 患者詳細画面
 * 配置先: manager/patient_detail.php
 */

require_once __DIR__ . '/../includes/config.php';

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
$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('<p style="color:red;">患者が見つかりません。</p>');
}

// ---------------------------------------------------
// QRコード生成
// ---------------------------------------------------
require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';
define('CACHE_DIR', __DIR__ . '/../qr_cache/');
define('QR_SIZE',   5);
define('QR_MARGIN', 1);

function generateQRBase64(string $relativeUrl): string {
    $baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST'];
    $fullUrl   = $baseUrl . '/' . ltrim($relativeUrl, '/');
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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/patient_detail.css">
</head>
<body>

<!-- ヘッダー -->
<header>
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
        <rect width="26" height="26" rx="6" fill="#fff" fill-opacity="0.15"/>
        <path d="M7 13h12M13 7v12" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
    </svg>
    <span class="logo">B-Care Manager</span>
        <span class="user">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="16" cy="16" r="15" stroke="#fff" stroke-width="1.5"/>
            <circle cx="16" cy="13" r="4.5" stroke="#fff" stroke-width="1.5"/>
            <path d="M7 26c0-5 4-8 9-8s9 3 9 8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        管理者
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </span>
</header>

<div class="layout">

    <!-- サイドバー -->
    <aside>
        <nav>
            <a href="index.php">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                患者一覧
            </a>
            <a href="#" class="active">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/></svg>
                QRコード管理
            </a>
            <a href="#">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 17H7A5 5 0 017 7h2M15 7h2a5 5 0 010 10h-2M9 12h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                読み取り履歴
            </a>
            <a href="#">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ユーザー管理
            </a>
            <a href="#">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                表示設定
            </a>
        </nav>
        <div class="logout">
            <a href="#">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ログアウト
            </a>
        </div>
    </aside>

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
                </div>
                <div class="info-row">
                    <span class="info-label">性別</span>
                    <span class="info-value" style="color:<?= $gender['color'] ?>; font-weight:bold;">
                        <?= $gender['icon'] ?> <?= htmlspecialchars($patient['gender'] ?? '-') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">年齢</span>
                    <span class="info-value"><?= htmlspecialchars($patient['age'] ?? '-') ?>歳</span>
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
                    <span class="info-label">移動区分</span>
                    <span class="badge" style="background:<?= $transfer['bg'] ?>; color:<?= $transfer['text'] ?>;">
                        <?= htmlspecialchars($patient['transfer_type']) ?>
                    </span>
                </div>

                <hr class="info-divider">

                <div class="info-row">
                    <span class="info-label">最終更新日時</span>
                    <span class="info-value" style="font-size:0.85rem;">
                        <?= htmlspecialchars($patient['updated_at']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ESL配信状態</span>
                    <div class="esl-status esl-ok">
                        <span class="dot"></span>
                        <span>配信済み</span>
                    </div>
                    <div class="esl-sub">正常にESLへ配信されています</div>
                </div>
            </div>

            <!-- 中央カラム：QRコード -->
            <div>
                <div class="card">
                    <div class="card-title">QRコード</div>
                    <div class="qr-wrap">
                        <?php if ($qr_base64): ?>
                            <img src="<?= $qr_base64 ?>" alt="QRコード">
                        <?php else: ?>
                            <p style="color:#aaa; font-size:0.85rem;">QRコードURLが設定されていません</p>
                        <?php endif; ?>
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
                        <div class="pictogram-note">※ ESLに表示されているピクトグラムの一覧です</div>
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

            <!-- 右カラム：ESLプレビュー -->
            <div class="card">
                <div class="card-title">ESL のプレビュー</div>
                <div class="esl-preview">
                    <div class="esl-preview-name"><?= htmlspecialchars($patient['patient_name']) ?> 様</div>
                    <div class="esl-preview-meta">
                        <?= htmlspecialchars($patient['ward_name']) ?>
                        <?= htmlspecialchars($patient['room_no']) ?>号室
                        <?= htmlspecialchars($patient['bed_no']) ?>ベッド
                    </div>
                    <div class="esl-preview-badges">
                        <span class="esl-badge" style="background:<?= $risk['bg'] ?>; color:<?= $risk['text'] ?>;">
                            <?= $risk['label'] ?>
                        </span>
                        <span class="esl-badge" style="background:<?= $transfer['bg'] ?>; color:<?= $transfer['text'] ?>;">
                            <?= htmlspecialchars($patient['transfer_type']) ?>
                        </span>
                    </div>
                    <?php if (!empty($pictograms)): ?>
                        <div class="esl-preview-pics">
                            <?php foreach ($pictograms as $pic): ?>
                                <img src="../<?= htmlspecialchars($pic['image_path']) ?>"
                                     alt="<?= htmlspecialchars($pic['name']) ?>"
                                     title="<?= htmlspecialchars($pic['name']) ?>"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /detail-grid -->
    </main>
</div>

<footer>B-Care Manager &copy; <?= date('Y') ?></footer>

</body>
</html>
