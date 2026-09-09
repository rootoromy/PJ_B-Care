<?php
/**
 * B-Care Manager - 預かり品管理
 * 配置先: manager/deposit_management.php
 *
 * 3つのセクションで構成する。
 *   1. 保管中・返却済状態確認: patient_deposits を「登録イベント」「返却イベント」に
 *      分けて時系列で表示する（1件のレコードが返却されると、
 *      登録イベントと返却イベントの2件として並ぶ）。
 *   2. 預かり品マスタ: deposit_item_masters の一覧・新規登録・編集・表示切替。
 *   3. 保管場所マスタ: storage_locations の一覧・新規登録・編集・表示切替。
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/mgr_auth.php';
mgr_require_login();

require_once __DIR__ . '/../includes/config.php';

$mysqli = getDB();
$is_viewer = mgr_is_viewer();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * 非表示にした直後、残っている表示中の行の表示順を1から振り直す
 * （$table・$idColumnは呼び出し元固定の定数のみを渡すこと。ユーザー入力は含めない）。
 */
function renumberDisplayOrder(mysqli $mysqli, string $table, string $idColumn): void {
    $mysqli->query('SET @dm_order := 0');
    $mysqli->query("UPDATE $table SET display_order = (@dm_order := @dm_order + 1) WHERE is_active = 1 ORDER BY display_order, $idColumn");
}

/**
 * 表示に戻した直後、表示中の一番後ろに追加されるよう表示順を振り直す。
 */
function appendToVisibleOrder(mysqli $mysqli, string $table, string $idColumn, int $id): void {
    $res = $mysqli->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM $table WHERE is_active = 1");
    $next_order = (int)$res->fetch_assoc()['next_order'];
    $stmt = $mysqli->prepare("UPDATE $table SET display_order = ? WHERE $idColumn = ?");
    $stmt->bind_param('ii', $next_order, $id);
    $stmt->execute();
    $stmt->close();
}

// ---------------------------------------------------
// 預かり品マスタ・保管場所マスタの書き込み処理
// ---------------------------------------------------
$master_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_action'])) {
    if ($is_viewer) {
        header('Location: deposit_management.php?master=forbidden');
        exit;
    }

    $action = $_POST['master_action'];

    if ($action === 'create_item' || $action === 'update_item') {
        $name       = trim($_POST['name'] ?? '');
        $unit       = trim($_POST['unit'] ?? '') !== '' ? trim($_POST['unit']) : '個';
        $display_order = (int)($_POST['display_order'] ?? 0);

        if ($name === '') {
            header('Location: deposit_management.php?master=error');
            exit;
        }

        if ($action === 'create_item') {
            $stmt = $mysqli->prepare('INSERT INTO deposit_item_masters (name, unit, display_order) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $name, $unit, $display_order);
            $stmt->execute();
            $stmt->close();
        } else {
            $item_master_id = (int)($_POST['item_master_id'] ?? 0);
            $stmt = $mysqli->prepare('UPDATE deposit_item_masters SET name = ?, unit = ?, display_order = ? WHERE item_master_id = ?');
            $stmt->bind_param('ssii', $name, $unit, $display_order, $item_master_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: deposit_management.php?master=saved');
        exit;
    }

    if ($action === 'toggle_item_active') {
        $item_master_id = (int)($_POST['item_master_id'] ?? 0);
        $stmt = $mysqli->prepare('SELECT is_active FROM deposit_item_masters WHERE item_master_id = ?');
        $stmt->bind_param('i', $item_master_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current) {
            $was_active = (int)$current['is_active'] === 1;
            $stmt = $mysqli->prepare('UPDATE deposit_item_masters SET is_active = 1 - is_active WHERE item_master_id = ?');
            $stmt->bind_param('i', $item_master_id);
            $stmt->execute();
            $stmt->close();

            if ($was_active) {
                renumberDisplayOrder($mysqli, 'deposit_item_masters', 'item_master_id');
            } else {
                appendToVisibleOrder($mysqli, 'deposit_item_masters', 'item_master_id', $item_master_id);
            }
        }
        header('Location: deposit_management.php?master=saved');
        exit;
    }

    if ($action === 'create_location' || $action === 'update_location') {
        $name          = trim($_POST['name'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 0);

        if ($name === '') {
            header('Location: deposit_management.php?master=error');
            exit;
        }

        if ($action === 'create_location') {
            $stmt = $mysqli->prepare('INSERT INTO storage_locations (name, display_order) VALUES (?, ?)');
            $stmt->bind_param('si', $name, $display_order);
            $stmt->execute();
            $stmt->close();
        } else {
            $location_id = (int)($_POST['location_id'] ?? 0);
            $stmt = $mysqli->prepare('UPDATE storage_locations SET name = ?, display_order = ? WHERE location_id = ?');
            $stmt->bind_param('sii', $name, $display_order, $location_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: deposit_management.php?master=saved');
        exit;
    }

    if ($action === 'toggle_location_active') {
        $location_id = (int)($_POST['location_id'] ?? 0);
        $stmt = $mysqli->prepare('SELECT is_active FROM storage_locations WHERE location_id = ?');
        $stmt->bind_param('i', $location_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current) {
            $was_active = (int)$current['is_active'] === 1;
            $stmt = $mysqli->prepare('UPDATE storage_locations SET is_active = 1 - is_active WHERE location_id = ?');
            $stmt->bind_param('i', $location_id);
            $stmt->execute();
            $stmt->close();

            if ($was_active) {
                renumberDisplayOrder($mysqli, 'storage_locations', 'location_id');
            } else {
                appendToVisibleOrder($mysqli, 'storage_locations', 'location_id', $location_id);
            }
        }
        header('Location: deposit_management.php?master=saved');
        exit;
    }
}
$master_message = $_GET['master'] ?? '';

// ---------------------------------------------------
// 保管中・返却済状態確認: フィルター取得
// ---------------------------------------------------
$today          = date('Y-m-d');
$month_start    = date('Y-m-01');
$month_end      = date('Y-m-t');
$filter_from    = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : $month_start;
$filter_to      = isset($_GET['date_to'])   && $_GET['date_to']   !== '' ? $_GET['date_to']   : $month_end;
$filter_ward    = isset($_GET['ward'])      ? trim($_GET['ward'])      : '';
$filter_status  = isset($_GET['status'])    ? trim($_GET['status'])    : '';
$filter_keyword = isset($_GET['keyword'])   ? trim($_GET['keyword'])   : '';

if (!in_array($filter_status, ['stored', 'returned'], true)) {
    $filter_status = '';
}

$per_page     = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

// ---------------------------------------------------
// 保管中・返却済状態確認: 一覧を組み立てる
// patient_deposits を1レコード1行のまま表示する
// （預かり日時/対応者と返却日時/対応者を同じ行に並べる）。
// ---------------------------------------------------
$events_sql = "
    SELECT
        pd.deposit_id,
        pd.patient_id,
        p.patient_name,
        p.ward_name,
        pd.item_name,
        pd.quantity,
        pd.status,
        pd.stored_at,
        st.name AS stored_by_name,
        pd.returned_at,
        rt.name AS returned_by_name,
        CASE WHEN pd.return_to = 'その他' THEN pd.return_to_other ELSE pd.return_to END AS return_to,
        pd.remarks,
        pd.return_remarks
    FROM patient_deposits pd
    JOIN patients p ON p.patient_id = pd.patient_id
    LEFT JOIN staff st ON st.staff_id = pd.stored_by
    LEFT JOIN staff rt ON rt.staff_id = pd.returned_by
    WHERE (
        (pd.stored_at >= ? AND pd.stored_at < DATE_ADD(?, INTERVAL 1 DAY))
        OR (pd.returned_at >= ? AND pd.returned_at < DATE_ADD(?, INTERVAL 1 DAY))
    )
";
$from_at = $filter_from . ' 00:00:00';
$events_params = [$from_at, $filter_to, $from_at, $filter_to];
$events_types  = 'ssss';

if ($filter_ward !== '') {
    $events_sql      .= ' AND p.ward_name = ?';
    $events_params[]  = $filter_ward;
    $events_types    .= 's';
}
if ($filter_status !== '') {
    $events_sql      .= ' AND pd.status = ?';
    $events_params[]  = $filter_status;
    $events_types    .= 's';
}
if ($filter_keyword !== '') {
    $events_sql      .= ' AND (pd.patient_id LIKE ? OR p.patient_name LIKE ? OR pd.item_name LIKE ?)';
    $like             = '%' . $filter_keyword . '%';
    $events_params[]  = $like;
    $events_params[]  = $like;
    $events_params[]  = $like;
    $events_types    .= 'sss';
}

$stmt_count = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM ($events_sql) AS filtered");
$stmt_count->bind_param($events_types, ...$events_params);
$stmt_count->execute();
$total_events = (int)$stmt_count->get_result()->fetch_assoc()['cnt'];
$stmt_count->close();
$total_pages = max(1, (int)ceil($total_events / $per_page));

$stmt_events = $mysqli->prepare("$events_sql ORDER BY COALESCE(pd.returned_at, pd.stored_at) DESC, pd.deposit_id DESC LIMIT ? OFFSET ?");
$stmt_events->bind_param($events_types . 'ii', ...array_merge($events_params, [$per_page, $offset]));
$stmt_events->execute();
$events = $stmt_events->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_events->close();

$wards = $mysqli->query("SELECT DISTINCT ward_name FROM patients WHERE ward_name IS NOT NULL AND ward_name <> '' ORDER BY ward_name")->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------------
// 預かり品マスタ・保管場所マスタ一覧
// ---------------------------------------------------
$item_masters_all = $mysqli->query("SELECT * FROM deposit_item_masters ORDER BY display_order, item_master_id")->fetch_all(MYSQLI_ASSOC);
$item_masters = array_values(array_filter($item_masters_all, fn($i) => (int)$i['is_active'] === 1));
$item_masters_hidden = array_values(array_filter($item_masters_all, fn($i) => (int)$i['is_active'] === 0));

$storage_locations_all = $mysqli->query("SELECT * FROM storage_locations ORDER BY display_order, location_id")->fetch_all(MYSQLI_ASSOC);
$storage_locations = array_values(array_filter($storage_locations_all, fn($i) => (int)$i['is_active'] === 1));
$storage_locations_hidden = array_values(array_filter($storage_locations_all, fn($i) => (int)$i['is_active'] === 0));

$mysqli->close();

function buildHistoryQuery(array $extra = []): string {
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
    <title>B-Care Manager - 預かり品管理</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/user_management.css?v=2">
    <link rel="stylesheet" href="css/deposit_management.css?v=4">
</head>
<body>

<?php $active_menu = 'deposits'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <div class="page-header">
            <div>
                <div class="page-title">預かり品管理</div>
                <p class="page-desc">患者さまの預かり品に関するマスタ管理と、保管中・返却済の状態確認ができます。</p>
            </div>
        </div>

        <?php if ($master_message === 'saved'): ?>
            <div class="alert-success">保存しました。</div>
        <?php elseif ($master_message === 'forbidden'): ?>
            <div class="alert-error">閲覧のみの権限のため、この操作はできません。</div>
        <?php elseif ($master_message === 'error'): ?>
            <div class="alert-error">品名を入力してください。</div>
        <?php endif; ?>

        <!-- ---------------------------------------------------- -->
        <!-- 1. 保管中・返却済状態確認 -->
        <!-- ---------------------------------------------------- -->
        <section class="dm-section">
            <div class="dm-section-head">
                <div>
                    <h2>保管中・返却済状態確認</h2>
                    <p class="dm-section-desc">預かり品の保管中・返却済の状態を確認できます。</p>
                </div>
            </div>

            <form method="GET" action="deposit_management.php" class="filter-bar dm-history-filter">
                <div class="filter-group">
                    <label>期間</label>
                    <div class="dm-date-range">
                        <input type="date" name="date_from" value="<?= h($filter_from) ?>">
                        <span>〜</span>
                        <input type="date" name="date_to" value="<?= h($filter_to) ?>">
                    </div>
                </div>
                <div class="dm-filter-row">
                    <div class="filter-group">
                        <label>病棟</label>
                        <select name="ward">
                            <option value="">すべての病棟</option>
                            <?php foreach ($wards as $w): ?>
                                <option value="<?= h($w['ward_name']) ?>" <?= $filter_ward === $w['ward_name'] ? 'selected' : '' ?>><?= h($w['ward_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>状態</label>
                        <select name="status">
                            <option value="">すべての状態</option>
                            <option value="stored" <?= $filter_status === 'stored' ? 'selected' : '' ?>>保管中</option>
                            <option value="returned" <?= $filter_status === 'returned' ? 'selected' : '' ?>>返却済</option>
                        </select>
                    </div>
                    <div class="filter-group filter-group--keyword">
                        <label>キーワード</label>
                        <div class="search-input-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="text" name="keyword" value="<?= h($filter_keyword) ?>" placeholder="患者ID・氏名・品名で検索">
                        </div>
                    </div>
                    <button type="submit" class="btn-filter">絞り込む</button>
                    <a href="deposit_management.php" class="btn-clear">クリア</a>
                </div>
            </form>

            <div class="table-wrap">
                <div class="table-meta">全 <?= $total_events ?> 件中 <?= $total_events === 0 ? 0 : $offset + 1 ?>〜<?= min($offset + $per_page, $total_events) ?> 件を表示</div>

                <?php if (!empty($events)): ?>
                <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>患者ID</th>
                            <th>患者氏名</th>
                            <th>品名</th>
                            <th>数量</th>
                            <th>状態</th>
                            <th>預かり日時</th>
                            <th>預かり対応者</th>
                            <th>返却日時</th>
                            <th>返却対応者</th>
                            <th>返却先</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <?php
                                $noteParts = [];
                                if (!empty($e['remarks'])) $noteParts[] = $e['remarks'];
                                if (!empty($e['return_remarks'])) $noteParts[] = $e['return_remarks'];
                                $note = implode(' / ', $noteParts);
                            ?>
                            <tr>
                                <td><?= h($e['patient_id']) ?></td>
                                <td><?= h($e['patient_name']) ?></td>
                                <td><?= h($e['item_name']) ?></td>
                                <td><?= (int)$e['quantity'] ?></td>
                                <td>
                                    <?php if ($e['status'] === 'stored'): ?>
                                        <span class="badge badge-active">保管中</span>
                                    <?php else: ?>
                                        <span class="badge badge-return">返却済</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(date('Y/m/d H:i', strtotime($e['stored_at']))) ?></td>
                                <td><?= h($e['stored_by_name'] ?? '-') ?></td>
                                <td><?= $e['returned_at'] ? h(date('Y/m/d H:i', strtotime($e['returned_at']))) : '-' ?></td>
                                <td><?= h($e['returned_by_name'] ?? '-') ?></td>
                                <td><?= h($e['return_to'] ?? '-') ?></td>
                                <td><?= $note !== '' ? h($note) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div class="pagination">
                    <?php $q = buildHistoryQuery(); ?>
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
                    <div class="no-data">該当する保管中・返却済の状態がありません。</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ---------------------------------------------------- -->
        <!-- 2. 預かり品マスタ -->
        <!-- ---------------------------------------------------- -->
        <section class="dm-section">
            <div class="dm-section-head">
                <div>
                    <h2>預かり品マスタ</h2>
                    <p class="dm-section-desc">Mobileで表示する預かり品のマスタ情報を管理できます。</p>
                </div>
                <div class="dm-section-actions">
                    <div class="search-input-wrap dm-master-search">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" id="itemSearchInput" placeholder="品名で検索">
                    </div>
                    <?php if (!$is_viewer): ?>
                        <button type="button" class="btn-add" id="openCreateItemModal">＋ 新規登録</button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="itemMasterSection">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>品名</th>
                                <th>表示順</th>
                                <?php if (!$is_viewer): ?><th>操作</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="itemMasterBody">
                            <?php foreach ($item_masters as $item): ?>
                                <tr class="dm-row" data-name="<?= h(mb_strtolower($item['name'])) ?>">
                                    <td>I<?= str_pad((string)$item['item_master_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= h($item['name']) ?></td>
                                    <td><?= (int)$item['display_order'] ?></td>
                                    <?php if (!$is_viewer): ?>
                                    <td class="dm-actions">
                                        <button type="button" class="btn-edit" data-edit-item
                                            data-id="<?= (int)$item['item_master_id'] ?>"
                                            data-name="<?= h($item['name']) ?>"
                                            data-unit="<?= h($item['unit']) ?>"
                                            data-order="<?= (int)$item['display_order'] ?>">編集</button>
                                        <form method="POST" action="deposit_management.php" class="dm-inline-form">
                                            <input type="hidden" name="master_action" value="toggle_item_active">
                                            <input type="hidden" name="item_master_id" value="<?= (int)$item['item_master_id'] ?>">
                                            <button type="submit" class="btn-hide">非表示</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($item_masters_hidden)): ?>
                <div class="table-wrap dm-hidden-wrap">
                    <div class="table-meta">非表示にした品目（<?= count($item_masters_hidden) ?>件）</div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>品名</th>
                                <?php if (!$is_viewer): ?><th>操作</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="itemMasterHiddenBody">
                            <?php foreach ($item_masters_hidden as $item): ?>
                                <tr class="dm-row" data-name="<?= h(mb_strtolower($item['name'])) ?>">
                                    <td>I<?= str_pad((string)$item['item_master_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= h($item['name']) ?></td>
                                    <?php if (!$is_viewer): ?>
                                    <td class="dm-actions">
                                        <button type="button" class="btn-edit" data-edit-item
                                            data-id="<?= (int)$item['item_master_id'] ?>"
                                            data-name="<?= h($item['name']) ?>"
                                            data-unit="<?= h($item['unit']) ?>"
                                            data-order="<?= (int)$item['display_order'] ?>">編集</button>
                                        <form method="POST" action="deposit_management.php" class="dm-inline-form">
                                            <input type="hidden" name="master_action" value="toggle_item_active">
                                            <input type="hidden" name="item_master_id" value="<?= (int)$item['item_master_id'] ?>">
                                            <button type="submit" class="btn-assign">表示に戻す</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ---------------------------------------------------- -->
        <!-- 3. 保管場所マスタ -->
        <!-- ---------------------------------------------------- -->
        <section class="dm-section">
            <div class="dm-section-head">
                <div>
                    <h2>保管場所マスタ</h2>
                    <p class="dm-section-desc">預かり品の保管場所のマスタ情報を管理できます。</p>
                </div>
                <div class="dm-section-actions">
                    <div class="search-input-wrap dm-master-search">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="text" id="locationSearchInput" placeholder="保管場所名で検索">
                    </div>
                    <?php if (!$is_viewer): ?>
                        <button type="button" class="btn-add" id="openCreateLocationModal">＋ 新規登録</button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="locationMasterSection">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>保管場所名</th>
                                <th>表示順</th>
                                <?php if (!$is_viewer): ?><th>操作</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="locationMasterBody">
                            <?php foreach ($storage_locations as $loc): ?>
                                <tr class="dm-row" data-name="<?= h(mb_strtolower($loc['name'])) ?>">
                                    <td>L<?= str_pad((string)$loc['location_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= h($loc['name']) ?></td>
                                    <td><?= (int)$loc['display_order'] ?></td>
                                    <?php if (!$is_viewer): ?>
                                    <td class="dm-actions">
                                        <button type="button" class="btn-edit" data-edit-location
                                            data-id="<?= (int)$loc['location_id'] ?>"
                                            data-name="<?= h($loc['name']) ?>"
                                            data-order="<?= (int)$loc['display_order'] ?>">編集</button>
                                        <form method="POST" action="deposit_management.php" class="dm-inline-form">
                                            <input type="hidden" name="master_action" value="toggle_location_active">
                                            <input type="hidden" name="location_id" value="<?= (int)$loc['location_id'] ?>">
                                            <button type="submit" class="btn-hide">非表示</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($storage_locations_hidden)): ?>
                <div class="table-wrap dm-hidden-wrap">
                    <div class="table-meta">非表示にした保管場所（<?= count($storage_locations_hidden) ?>件）</div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>保管場所名</th>
                                <?php if (!$is_viewer): ?><th>操作</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="locationMasterHiddenBody">
                            <?php foreach ($storage_locations_hidden as $loc): ?>
                                <tr class="dm-row" data-name="<?= h(mb_strtolower($loc['name'])) ?>">
                                    <td>L<?= str_pad((string)$loc['location_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= h($loc['name']) ?></td>
                                    <?php if (!$is_viewer): ?>
                                    <td class="dm-actions">
                                        <button type="button" class="btn-edit" data-edit-location
                                            data-id="<?= (int)$loc['location_id'] ?>"
                                            data-name="<?= h($loc['name']) ?>"
                                            data-order="<?= (int)$loc['display_order'] ?>">編集</button>
                                        <form method="POST" action="deposit_management.php" class="dm-inline-form">
                                            <input type="hidden" name="master_action" value="toggle_location_active">
                                            <input type="hidden" name="location_id" value="<?= (int)$loc['location_id'] ?>">
                                            <button type="submit" class="btn-assign">表示に戻す</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php if (!$is_viewer): ?>
<!-- 預かり品マスタ 新規登録・編集モーダル -->
<div class="modal-backdrop hidden" id="itemModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="itemModalTitle">
        <div class="modal-header">
            <h2 id="itemModalTitle">預かり品マスタ 新規登録</h2>
            <button class="icon-button" id="closeItemModal" type="button" aria-label="閉じる">×</button>
        </div>
        <form method="POST" action="deposit_management.php">
            <input type="hidden" name="master_action" id="itemFormAction" value="create_item">
            <input type="hidden" name="item_master_id" id="itemIdHidden">
            <div class="form-grid">
                <div class="field full">
                    <label>品名</label>
                    <input type="text" name="name" id="itemName" required placeholder="例：財布">
                </div>
                <div class="field">
                    <label>単位</label>
                    <input type="text" name="unit" id="itemUnit" placeholder="個">
                </div>
                <div class="field">
                    <label>表示順</label>
                    <input type="number" name="display_order" id="itemOrder" min="0">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-clear" id="cancelItemModal">キャンセル</button>
                <button type="submit" class="btn-save">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 保管場所マスタ 新規登録・編集モーダル -->
<div class="modal-backdrop hidden" id="locationModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="locationModalTitle">
        <div class="modal-header">
            <h2 id="locationModalTitle">保管場所マスタ 新規登録</h2>
            <button class="icon-button" id="closeLocationModal" type="button" aria-label="閉じる">×</button>
        </div>
        <form method="POST" action="deposit_management.php">
            <input type="hidden" name="master_action" id="locationFormAction" value="create_location">
            <input type="hidden" name="location_id" id="locationIdHidden">
            <div class="form-grid">
                <div class="field full">
                    <label>保管場所名</label>
                    <input type="text" name="name" id="locationName" required placeholder="例：病棟金庫">
                </div>
                <div class="field">
                    <label>表示順</label>
                    <input type="number" name="display_order" id="locationOrder" min="0">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-clear" id="cancelLocationModal">キャンセル</button>
                <button type="submit" class="btn-save">保存</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="js/deposit_management.js?v=3"></script>
</body>
</html>
