<?php
/**
 * B-Care Manager - 主治医・受持看護師 設定画面
 * 配置先: manager/patient_staff_setting.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';
mgr_require_login();

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
// 保存処理
// ---------------------------------------------------
$save_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $doctor_staff_id = isset($_POST['doctor_staff_id']) ? trim($_POST['doctor_staff_id']) : '';
    $nurse_staff_id  = isset($_POST['nurse_staff_id'])  ? trim($_POST['nurse_staff_id'])  : '';

    $roles = ['doctor' => $doctor_staff_id, 'nurse' => $nurse_staff_id];
    foreach ($roles as $role => $staff_id) {
        if ($staff_id === '') {
            $stmt_del = $mysqli->prepare("DELETE FROM patients_staff WHERE patient_id = ? AND role = ?");
            $stmt_del->bind_param('ss', $patient_id, $role);
            $stmt_del->execute();
            $stmt_del->close();
        } else {
            $stmt_up = $mysqli->prepare("
                INSERT INTO patients_staff (patient_id, staff_id, role)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id), assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt_up->bind_param('sss', $patient_id, $staff_id, $role);
            $stmt_up->execute();
            $stmt_up->close();
        }
    }

    $save_message = 'success';
}

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
// 医師・看護師の一覧取得
// ---------------------------------------------------
$doctors = $mysqli->query("SELECT staff_id, name FROM staff WHERE position = '医師' AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$nurses  = $mysqli->query("SELECT staff_id, name FROM staff WHERE position = '看護師' AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------------
// 現在の割り当て取得
// ---------------------------------------------------
$stmt_cur = $mysqli->prepare("SELECT role, staff_id FROM patients_staff WHERE patient_id = ?");
$stmt_cur->bind_param('s', $patient_id);
$stmt_cur->execute();
$current_rows = $stmt_cur->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cur->close();

$current_doctor_id = '';
$current_nurse_id  = '';
foreach ($current_rows as $row) {
    if ($row['role'] === 'doctor') $current_doctor_id = $row['staff_id'];
    if ($row['role'] === 'nurse')  $current_nurse_id  = $row['staff_id'];
}

$mysqli->close();

$gender = getGenderStyle($patient['gender'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - 主治医・受持看護師の設定</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/pictogram_setting.css?v=1">
    <link rel="stylesheet" href="css/patient_staff_setting.css?v=1">
</head>
<body>

<?php $active_menu = 'patients'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <a href="patient_detail.php?patient_id=<?= urlencode($patient_id) ?>" class="back-link">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            戻る
        </a>
        <div class="page-header">
            <div class="page-title">主治医・受持看護師の設定</div>
        </div>

        <?php if ($save_message === 'success'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                主治医・受持看護師を保存しました。
            </div>
        <?php endif; ?>

        <div class="staff-setting-grid">

            <!-- 患者情報 -->
            <div class="card patient-info-card">
                <div class="card-title">患者情報</div>
                <div class="info-row">
                    <span class="info-label">患者ID</span>
                    <span class="info-value"><?= htmlspecialchars($patient['patient_id']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">患者名</span>
                    <span class="info-value"><?= htmlspecialchars($patient['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">性別</span>
                    <span class="info-value" style="color:<?= $gender['color'] ?>;"><?= $gender['icon'] ?> <?= htmlspecialchars($patient['gender'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">年齢</span>
                    <span class="info-value"><?= htmlspecialchars($patient['age'] ?? '-') ?>歳</span>
                </div>
            </div>

            <!-- 割り当てフォーム -->
            <form method="POST" action="patient_staff_setting.php?patient_id=<?= urlencode($patient_id) ?>">
                <div class="card">
                    <div class="card-title">主治医</div>
                    <select name="doctor_staff_id" class="staff-select">
                        <option value="">未設定</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= htmlspecialchars($d['staff_id']) ?>" <?= $current_doctor_id === $d['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="card">
                    <div class="card-title">受持看護師</div>
                    <select name="nurse_staff_id" class="staff-select">
                        <option value="">未設定</option>
                        <?php foreach ($nurses as $n): ?>
                            <option value="<?= htmlspecialchars($n['staff_id']) ?>" <?= $current_nurse_id === $n['staff_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($n['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="save" class="btn-save">✓ 保存する</button>
                <a href="patient_detail.php?patient_id=<?= urlencode($patient_id) ?>" class="btn-cancel">キャンセル</a>
            </form>

        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
