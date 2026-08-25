<?php
/**
 * B-Care Manager - ピクトグラム設定画面
 * 配置先: manager/pictogram_setting.php
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
// 保存処理
// ---------------------------------------------------
$save_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $selected = isset($_POST['pictograms']) ? $_POST['pictograms'] : [];

    // 既存の設定を削除
    $stmt_del = $mysqli->prepare("DELETE FROM patient_pictograms WHERE patient_id = ?");
    $stmt_del->bind_param('s', $patient_id);
    $stmt_del->execute();
    $stmt_del->close();

    // 新しい設定を保存
    if (!empty($selected)) {
        $stmt_ins = $mysqli->prepare("INSERT INTO patient_pictograms (patient_id, pictogram_id, display_order) VALUES (?, ?, ?)");
        foreach ($selected as $order => $pic_id) {
            $order_num = (int)$order + 1;
            $pic_id    = (int)$pic_id;
            $stmt_ins->bind_param('sii', $patient_id, $pic_id, $order_num);
            $stmt_ins->execute();
        }
        $stmt_ins->close();
    }

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

    if ($patientForEsl && !empty($patientForEsl['esl_label_code'])) {
        $eslResult = linkPatientArticleToLabel($patientForEsl, $patientForEsl['esl_label_code']);
        if ($eslResult['httpCode'] >= 200 && $eslResult['httpCode'] < 300) {
            $stmtSync = $mysqli->prepare("UPDATE patients SET esl_synced_at = NOW() WHERE patient_id = ?");
            $stmtSync->bind_param('s', $patient_id);
            $stmtSync->execute();
            $stmtSync->close();
        }
    }

    $save_message = 'success';
}

// ---------------------------------------------------
// 患者情報取得
// ---------------------------------------------------
$stmt = $mysqli->prepare("
    SELECT p.*, doc.name AS doctor_name, nur.name AS primary_nurse
    FROM patients p
    LEFT JOIN patients_staff ps_doc ON ps_doc.patient_id = p.patient_id AND ps_doc.role = 'doctor'
    LEFT JOIN staff doc ON doc.staff_id = ps_doc.staff_id
    LEFT JOIN patients_staff ps_nur ON ps_nur.patient_id = p.patient_id AND ps_nur.role = 'nurse'
    LEFT JOIN staff nur ON nur.staff_id = ps_nur.staff_id
    WHERE p.patient_id = ?
");
$stmt->bind_param('s', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die('<p style="color:red;">患者が見つかりません。</p>');
}

// ---------------------------------------------------
// 全ピクトグラム取得
// ---------------------------------------------------
// pictogram_id=0はAIMS配信用の「空欄」を表す内部レコードのため、選択肢からは除外する
$all_pictograms = $mysqli->query("SELECT * FROM pictograms WHERE pictogram_id > 0 ORDER BY category, pictogram_id")->fetch_all(MYSQLI_ASSOC);

// カテゴリ一覧
$categories = [];
foreach ($all_pictograms as $pic) {
    if (!in_array($pic['category'], $categories)) {
        $categories[] = $pic['category'];
    }
}

// ---------------------------------------------------
// 現在設定中のピクトグラムID一覧
// ---------------------------------------------------
$stmt_cur = $mysqli->prepare("SELECT pictogram_id FROM patient_pictograms WHERE patient_id = ? ORDER BY display_order");
$stmt_cur->bind_param('s', $patient_id);
$stmt_cur->execute();
$current_rows   = $stmt_cur->get_result()->fetch_all(MYSQLI_ASSOC);
$current_ids    = array_column($current_rows, 'pictogram_id');
$stmt_cur->close();

$mysqli->close();

$gender   = getGenderStyle($patient['gender'] ?? '');
$risk     = getRiskColor((int)$patient['fall_risk']);
$transfer = getTransferColor();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - ピクトグラム設定</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/pictogram_setting.css?v=3">
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
            <div class="page-title">ピクトグラム設定</div>
        </div>

        <?php if ($save_message === 'success'): ?>
            <div class="alert-success">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                ピクトグラムの設定を保存しました。
            </div>
        <?php endif; ?>

        <form method="POST" action="pictogram_setting.php?patient_id=<?= urlencode($patient_id) ?>">
        <div class="setting-grid">

            <!-- 左：患者基本情報 -->
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

            <!-- 中央：ピクトグラム選択 -->
            <div class="card">
                <div class="card-title">ピクトグラムを選択</div>
                <div class="card-desc">クリックで選択・解除できます</div>

                <!-- カテゴリタブ -->
                <div class="tab-bar">
                    <button type="button" class="tab-btn active" data-cat="all">すべて</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="tab-btn" data-cat="<?= htmlspecialchars($cat) ?>">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- ピクトグラム一覧 -->
                <div class="pic-grid-wrap">
                    <div class="pic-grid" id="picGrid">
                        <?php foreach ($all_pictograms as $pic): ?>
                            <div class="pic-item <?= in_array($pic['pictogram_id'], $current_ids) ? 'selected' : '' ?>"
                                data-id="<?= $pic['pictogram_id'] ?>"
                                data-name="<?= htmlspecialchars($pic['name']) ?>"
                                data-img="../<?= htmlspecialchars($pic['image_path']) ?>"
                                data-cat="<?= htmlspecialchars($pic['category']) ?>"
                                onclick="togglePic(this)">
                                <div class="pic-check">
                                    <svg fill="none" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>
                                </div>
                                <img src="../<?= htmlspecialchars($pic['image_path']) ?>"
                                    alt="<?= htmlspecialchars($pic['name']) ?>"
                                    onerror="this.src='../img/pictograms/default.png'">
                                <span><?= htmlspecialchars($pic['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="pagination" style="display:flex;gap:6px;margin-top:16px;flex-wrap:wrap;justify-content:center;"></div>
            </div>
            

            <!-- 右：選択中ピクトグラム -->
            <div>
                <!-- 選択中ピクトグラム -->
                <div class="card">
                    <div class="selected-title">
                        <span>選択中のピクトグラム (<span id="selectedCount">0</span>件)</span>
                        <button type="button" class="btn-clear-all" onclick="clearAll()">すべてクリア</button>
                    </div>
                    <div class="selected-grid" id="selectedGrid">
                        <div class="no-selected" id="noSelected">未選択</div>
                    </div>

                    <!-- hidden input（選択されたID） -->
                    <div id="hiddenInputs"></div>

                    <button type="submit" name="save" class="btn-save">
                        ✓ このピクトグラムを設定する
                    </button>
                    <a href="patient_detail.php?patient_id=<?= urlencode($patient_id) ?>" class="btn-cancel">
                        キャンセル
                    </a>
                </div>
            </div>

        </div>
        </form>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// 現在選択中のピクトグラム（ID → {name, img} のMap）
const selected = new Map();

// 初期選択（PHP側から渡す）
const initialSelected = <?= json_encode(array_values(array_map(function($pic) {
    return [
        'id'   => (int)$pic['pictogram_id'],
        'name' => $pic['name'],
        'img'  => '../' . $pic['image_path'],
    ];
}, array_filter($all_pictograms, function($pic) use ($current_ids) {
    return in_array($pic['pictogram_id'], $current_ids);
})))) ?>;

// 初期化
initialSelected.forEach(p => selected.set(p.id, { name: p.name, img: p.img }));
renderSelected();

// ピクトグラムをトグル
function togglePic(el) {
    const id   = parseInt(el.dataset.id);
    const name = el.dataset.name;
    const img  = el.dataset.img;

    if (selected.has(id)) {
        selected.delete(id);
        el.classList.remove('selected');
    } else {
        selected.set(id, { name, img });
        el.classList.add('selected');
    }
    renderSelected();
}

// 選択済みエリアを再描画
function renderSelected() {
    const grid    = document.getElementById('selectedGrid');
    const noSel   = document.getElementById('noSelected');
    const count   = document.getElementById('selectedCount');
    const hidden  = document.getElementById('hiddenInputs');

    count.textContent = selected.size;
    hidden.innerHTML  = '';

    if (selected.size === 0) {
        grid.innerHTML = '<div class="no-selected" id="noSelected">未選択</div>';
        return;
    }

    grid.innerHTML = '';
    let i = 0;
    selected.forEach((val, id) => {
        // 選択済み表示
        const div = document.createElement('div');
        div.className = 'selected-pic';
        div.innerHTML = `
            <button type="button" class="remove-btn" onclick="removePic(${id})">
                <svg fill="none" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
            </button>
            <img src="${val.img}" alt="${val.name}" onerror="this.style.display='none'">
            <span>${val.name}</span>
        `;
        grid.appendChild(div);

        // hidden input
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = `pictograms[${i}]`;
        input.value = id;
        hidden.appendChild(input);
        i++;
    });
}

// 個別削除
function removePic(id) {
    selected.delete(id);
    // グリッドのチェックも外す
    const el = document.querySelector(`.pic-item[data-id="${id}"]`);
    if (el) el.classList.remove('selected');
    renderSelected();
}

// 全クリア
function clearAll() {
    selected.clear();
    document.querySelectorAll('.pic-item.selected').forEach(el => el.classList.remove('selected'));
    renderSelected();
}

// ページネーション管理
let currentPage = 1;
const PAGE_SIZE = 20;
let filteredItems = [];

function getFilteredItems(cat) {
    const all = Array.from(document.querySelectorAll('.pic-item'));
    return cat === 'all' ? all : all.filter(el => el.dataset.cat === cat);
}

function renderPage(items, page) {
    const start = (page - 1) * PAGE_SIZE;
    const end   = start + PAGE_SIZE;

    // まず全アイテムを非表示に
    document.querySelectorAll('.pic-item').forEach(el => el.style.display = 'none');

    // filteredItems の中でページ範囲内だけ表示
    items.forEach((el, i) => {
        if (i >= start && i < end) {
            el.style.display = '';
        }
    });

    renderPagination(items.length, page);
}

function renderPagination(total, page) {
    const totalPages = Math.ceil(total / PAGE_SIZE);
    const container = document.getElementById('pagination');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // 前へ
    html += `<button type="button" class="page-btn" onclick="goPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>&lsaquo;</button>`;

    // ページ番号
    for (let i = 1; i <= totalPages; i++) {
        if (
            i === 1 ||
            i === totalPages ||
            (i >= page - 1 && i <= page + 1)
        ) {
            html += `<button type="button" class="page-btn ${i === page ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
        } else if (i === page - 2 || i === page + 2) {
            html += `<button type="button" class="page-btn dots" disabled>…</button>`;
        }
    }

    // 次へ
    html += `<button type="button" class="page-btn" onclick="goPage(${page + 1})" ${page === totalPages ? 'disabled' : ''}>&rsaquo;</button>`;

    container.innerHTML = html;
}

function goPage(page) {
    currentPage = page;
    renderPage(filteredItems, currentPage);
}

// カテゴリタブ切り替え
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentPage = 1;
        filteredItems = getFilteredItems(this.dataset.cat);
        renderPage(filteredItems, currentPage);
    });
});

// 初期表示
filteredItems = getFilteredItems('all');
renderPage(filteredItems, currentPage);
</script>

</body>
</html>
