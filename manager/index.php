<?php
/**
 * B-Care Manager - 患者一覧
 * 配置先: manager/index.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';
mgr_require_login();

require_once __DIR__ . '/../includes/config.php';

// ---------------------------------------------------
// DB接続
// ---------------------------------------------------
$mysqli = getDB();

// ---------------------------------------------------
// フィルター取得
// ---------------------------------------------------
$filter_keyword  = isset($_GET['keyword'])  ? trim($_GET['keyword'])  : '';
$filter_ward     = isset($_GET['ward'])     ? trim($_GET['ward'])     : '';
$filter_room     = isset($_GET['room'])     ? trim($_GET['room'])     : '';
$filter_gender   = isset($_GET['gender'])   ? trim($_GET['gender'])   : '';
$filter_risk     = isset($_GET['risk'])     ? trim($_GET['risk'])     : '';
$filter_transfer = isset($_GET['transfer']) ? trim($_GET['transfer']) : '';
$admission_status = isset($_GET['admission_status']) ? trim($_GET['admission_status']) : 'admitted';
if (!in_array($admission_status, ['admitted', 'discharged', 'all'], true)) {
    $admission_status = 'admitted';
}

// ---------------------------------------------------
// ページネーション
// ---------------------------------------------------
$per_page     = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

// ---------------------------------------------------
// WHERE句組み立て
// ---------------------------------------------------
$where  = [];
$params = [];
$types  = '';

if ($filter_keyword !== '') {
    $where[]  = '(patient_id LIKE ? OR patient_name LIKE ? OR patient_kana LIKE ?)';
    $like     = '%' . $filter_keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($filter_ward !== '') {
    $where[]  = 'ward_name = ?';
    $params[] = $filter_ward;
    $types   .= 's';
}
if ($filter_room !== '') {
    $where[]  = 'room_no = ?';
    $params[] = $filter_room;
    $types   .= 's';
}
if ($filter_gender !== '') {
    $where[]  = 'gender = ?';
    $params[] = $filter_gender;
    $types   .= 's';
}
if ($filter_risk !== '') {
    $where[]  = 'fall_risk = ?';
    $params[] = $filter_risk;
    $types   .= 'i';
}
if ($filter_transfer !== '') {
    $where[]  = 'transfer_type = ?';
    $params[] = $filter_transfer;
    $types   .= 's';
}
if ($admission_status === 'admitted') {
    $where[] = 'is_admitted = 1';
} elseif ($admission_status === 'discharged') {
    $where[] = 'is_admitted = 0';
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------------------------------------------------
// 総件数取得
// ---------------------------------------------------
$stmt_count = $mysqli->prepare("SELECT COUNT(*) as cnt FROM patients $where_sql");
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_count = $stmt_count->get_result()->fetch_assoc()['cnt'];
$total_pages = max(1, ceil($total_count / $per_page));
$stmt_count->close();

// ---------------------------------------------------
// 患者データ取得
// ---------------------------------------------------
$stmt = $mysqli->prepare("SELECT * FROM patients $where_sql ORDER BY ward_name, room_no, bed_no LIMIT ? OFFSET ?");
$stmt->bind_param($types . 'ii', ...array_merge($params, [$per_page, $offset]));
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------------------------------
// フィルター用選択肢取得
// ---------------------------------------------------
$wards     = $mysqli->query("SELECT DISTINCT ward_name FROM patients ORDER BY ward_name")->fetch_all(MYSQLI_ASSOC);
$rooms     = $mysqli->query("SELECT DISTINCT room_no FROM patients ORDER BY room_no")->fetch_all(MYSQLI_ASSOC);
$transfers = $mysqli->query("SELECT DISTINCT transfer_type FROM patients ORDER BY transfer_type")->fetch_all(MYSQLI_ASSOC);

$mysqli->close();

// ---------------------------------------------------
// ページネーション用クエリ文字列
// ---------------------------------------------------
function buildQuery(array $extra = []): string {
    $params = array_merge($_GET, $extra);
    unset($params['page']);
    return http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - 患者一覧</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/index.css?v=9">
</head>
<body>

<?php $active_menu = 'patients'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <div class="page-header">
            <div class="page-title">患者一覧</div>
        </div>

        <!-- フィルターバー -->
        <form method="GET" action="index.php">
            <div class="filter-bar">
                <div class="filter-group filter-group--keyword">
                    <label>患者検索(ID/漢字・カナ)
                        <span class="help-icon" title="患者ID・氏名（漢字またはカナ）の一部を入力して検索できます">?</span>
                    </label>
                    <div class="search-input-wrap">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" name="keyword" value="<?= htmlspecialchars($filter_keyword) ?>" placeholder="患者ID・氏名（漢字・カナ）を入力してください">
                    </div>
                </div>
                <div class="filter-group">
                    <label>病棟</label>
                    <select name="ward">
                        <option value="">すべて</option>
                        <?php foreach ($wards as $w): ?>
                            <option value="<?= htmlspecialchars($w['ward_name']) ?>"
                                <?= $filter_ward === $w['ward_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($w['ward_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>病室</label>
                    <select name="room">
                        <option value="">すべて</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?= htmlspecialchars($r['room_no']) ?>"
                                <?= $filter_room === $r['room_no'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['room_no']) ?>号室
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>性別</label>
                    <select name="gender">
                        <option value="">すべて</option>
                        <option value="男性" <?= $filter_gender === '男性' ? 'selected' : '' ?>>男性</option>
                        <option value="女性" <?= $filter_gender === '女性' ? 'selected' : '' ?>>女性</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>転倒リスク</label>
                    <select name="risk">
                        <option value="">すべて</option>
                        <option value="1" <?= $filter_risk === '1' ? 'selected' : '' ?>>リスクあり</option>
                        <option value="0" <?= $filter_risk === '0' ? 'selected' : '' ?>>なし</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>移送区分</label>
                    <select name="transfer">
                        <option value="">すべて</option>
                        <?php foreach ($transfers as $t): ?>
                            <option value="<?= htmlspecialchars($t['transfer_type']) ?>"
                                <?= $filter_transfer === $t['transfer_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['transfer_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>入退院</label>
                    <select name="admission_status">
                        <option value="admitted" <?= $admission_status === 'admitted' ? 'selected' : '' ?>>在院中</option>
                        <option value="discharged" <?= $admission_status === 'discharged' ? 'selected' : '' ?>>退院済み</option>
                        <option value="all" <?= $admission_status === 'all' ? 'selected' : '' ?>>すべて(在院・退院済み)</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter">絞り込む</button>
                <a href="index.php" class="btn-clear">クリア</a>
            </div>
        </form>

        <!-- テーブル -->
        <div class="table-wrap">
            <div class="table-meta">
                全 <?= $total_count ?> 件中 <?= $offset + 1 ?>〜<?= min($offset + $per_page, $total_count) ?> 件を表示
            </div>

            <?php if (!empty($patients)): ?>
            <table>
                <thead>
                    <tr>
                        <th>患者ID</th>
                        <th>氏名</th>
                        <th>性別</th>
                        <th>年齢</th>
                        <th>病棟</th>
                        <th>病室</th>
                        <th>ベッド</th>
                        <th>転倒リスク</th>
                        <th>移送区分</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($patients as $p): ?>
                    <?php
                        $risk     = getRiskColor((int)$p['fall_risk']);
                        $gender   = getGenderStyle($p['gender'] ?? '');
                        $transfer = getTransferColor();
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($p['patient_id']) ?></td>
                        <td>
                            <?= htmlspecialchars($p['patient_name']) ?>
                            <?php if ((int)($p['is_admitted'] ?? 1) === 0): ?>
                                <span class="badge" style="background:#eee; color:#666; margin-left:4px;">退院済み</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:<?= $gender['color'] ?>; font-weight:bold;">
                                <?= htmlspecialchars($p['gender'] ?? '-') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($p['age'] ?? '-') ?>歳</td>
                        <td><?= htmlspecialchars($p['ward_name']) ?></td>
                        <td><?= htmlspecialchars($p['room_no']) ?>号室</td>
                        <td><?= htmlspecialchars($p['bed_no']) ?>ベッド</td>
                        <td>
                            <span class="badge" style="background:<?= $risk['bg'] ?>; color:<?= $risk['text'] ?>;">
                                <?= $risk['label'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $transfer['bg'] ?>; color:<?= $transfer['text'] ?>;">
                                <?= htmlspecialchars($p['transfer_type']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="patient_detail.php?patient_id=<?= urlencode($p['patient_id']) ?>" class="btn-select">選択</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- ページネーション -->
            <div class="pagination">
                <?php $q = buildQuery(); ?>
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>&<?= $q ?>">&#8249;</a>
                <?php else: ?>
                    <span class="disabled">&#8249;</span>
                <?php endif; ?>

                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <?php if ($i === $current_page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&<?= $q ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>&<?= $q ?>">&#8250;</a>
                <?php else: ?>
                    <span class="disabled">&#8250;</span>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="no-data">該当する患者データがありません。</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
