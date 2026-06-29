<?php
/**
 * B-Care Manager - 患者一覧
 * 配置先: index.php
 */

require_once __DIR__ . '/includes/config.php';

// ---------------------------------------------------
// DB接続
// ---------------------------------------------------
$mysqli = getDB();

// ---------------------------------------------------
// フィルター取得
// ---------------------------------------------------
$filter_ward     = isset($_GET['ward'])     ? trim($_GET['ward'])     : '';
$filter_room     = isset($_GET['room'])     ? trim($_GET['room'])     : '';
$filter_gender   = isset($_GET['gender'])   ? trim($_GET['gender'])   : '';
$filter_risk     = isset($_GET['risk'])     ? trim($_GET['risk'])     : '';
$filter_transfer = isset($_GET['transfer']) ? trim($_GET['transfer']) : '';

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
    <link rel="stylesheet" href="css/style.css">
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
            <a href="index.php" class="active">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                患者一覧
            </a>
            <a href="#">
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
        <div class="page-header">
            <div class="page-title">患者一覧</div>
        </div>

        <!-- フィルターバー -->
        <form method="GET" action="index.php">
            <div class="filter-bar">
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
                    <label>移動区分</label>
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
                        <th>病棟</th>
                        <th>病室</th>
                        <th>ベッド</th>
                        <th>性別</th>
                        <th>年齢</th>
                        <th>転倒リスク</th>
                        <th>移動区分</th>
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
                        <td><?= htmlspecialchars($p['patient_name']) ?></td>
                        <td><?= htmlspecialchars($p['ward_name']) ?></td>
                        <td><?= htmlspecialchars($p['room_no']) ?>号室</td>
                        <td><?= htmlspecialchars($p['bed_no']) ?>ベッド</td>
                        <td>
                            <span style="color:<?= $gender['color'] ?>; font-weight:bold;">
                                <?= $gender['icon'] ?> <?= htmlspecialchars($p['gender'] ?? '-') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($p['age'] ?? '-') ?>歳</td>
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

<footer>B-Care Manager &copy; <?= date('Y') ?></footer>

</body>
</html>
