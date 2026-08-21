<?php
/**
 * B-Care Manager - ユーザー管理
 * 配置先: manager/user_management.php
 *
 * staff テーブルと接続。一覧表示・新規追加・編集はここでDBに反映する。
 * フィルター・検索はページ内のJS（user_management.js）でクライアント側処理する。
 */

require_once __DIR__ . '/../includes/config.php';

$mysqli = getDB();

$errors = [];

// ---------------------------------------------------
// 保存処理（新規追加・編集）
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action    = $_POST['action'];
    $name      = trim($_POST['user_name'] ?? '');
    $mail      = trim($_POST['user_email'] ?? '');
    $ward      = trim($_POST['user_ward'] ?? '');
    $role_key  = ($_POST['user_role'] ?? '') === '管理者' ? 'admin' : 'user';
    $is_active = ($_POST['user_status'] ?? '') === '有効' ? 1 : 0;
    $password  = $_POST['user_password'] ?? '';

    if ($name === '' || $mail === '' || $ward === '') {
        $errors[] = '必須項目が入力されていません。';
    }

    $stmt = $mysqli->prepare('SELECT role_id FROM roles WHERE role_key = ?');
    $stmt->bind_param('s', $role_key);
    $stmt->execute();
    $role_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $role_id = (int)($role_row['role_id'] ?? 0);

    if ($action === 'create') {
        $login_id = trim($_POST['user_id'] ?? '');

        if ($login_id === '') {
            $errors[] = 'ユーザーIDを入力してください。';
        }
        if ($password === '') {
            $errors[] = '初期パスワードを入力してください。';
        }

        if (empty($errors)) {
            $stmt = $mysqli->prepare('SELECT staff_id FROM staff WHERE login_id = ?');
            $stmt->bind_param('s', $login_id);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                $errors[] = '同じユーザーIDがすでに登録されています。';
            }
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('
                INSERT INTO staff (staff_id, login_id, mail, password_hash, name, ward_name, role_id, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('ssssssii', $login_id, $login_id, $mail, $password_hash, $name, $ward, $role_id, $is_active);
            $stmt->execute();
            $stmt->close();

            $mysqli->close();
            header('Location: user_management.php?saved=1');
            exit;
        }
    } elseif ($action === 'update') {
        $staff_id = trim($_POST['staff_id_hidden'] ?? '');

        if ($staff_id === '') {
            $errors[] = '対象のユーザーが見つかりません。';
        }

        if (empty($errors)) {
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare('
                    UPDATE staff SET mail = ?, name = ?, ward_name = ?, role_id = ?, is_active = ?, password_hash = ?
                    WHERE staff_id = ?
                ');
                $stmt->bind_param('sssiiss', $mail, $name, $ward, $role_id, $is_active, $password_hash, $staff_id);
            } else {
                $stmt = $mysqli->prepare('
                    UPDATE staff SET mail = ?, name = ?, ward_name = ?, role_id = ?, is_active = ?
                    WHERE staff_id = ?
                ');
                $stmt->bind_param('sssiis', $mail, $name, $ward, $role_id, $is_active, $staff_id);
            }
            $stmt->execute();
            $stmt->close();

            $mysqli->close();
            header('Location: user_management.php?saved=1');
            exit;
        }
    }
}

// ---------------------------------------------------
// 一覧取得（表示・並び替え・絞り込みはJS側で行う）
// ---------------------------------------------------
$staffRows = $mysqli->query('
    SELECT s.staff_id, s.login_id, s.mail, s.name, s.ward_name, r.role_key, s.is_active, s.updated_at
    FROM staff s
    JOIN roles r ON r.role_id = s.role_id
    ORDER BY s.staff_id
')->fetch_all(MYSQLI_ASSOC);
$mysqli->close();

$usersForJs = array_map(static function (array $row): array {
    return [
        'staffId'   => $row['staff_id'],
        'id'        => $row['login_id'],
        'name'      => $row['name'],
        'email'     => $row['mail'] ?? '',
        'ward'      => $row['ward_name'] ?? '',
        'role'      => $row['role_key'] === 'admin' ? '管理者' : 'スタッフ',
        'status'    => ((int)$row['is_active'] === 1) ? '有効' : '無効',
        'lastLogin' => '-',
        'updatedAt' => date('Y/m/d H:i', strtotime($row['updated_at'])),
    ];
}, $staffRows);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Care Manager - ユーザー管理</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=1">
    <link rel="stylesheet" href="css/common.css?v=2">
    <link rel="stylesheet" href="css/user_management.css?v=2">
</head>
<body>

<?php $active_menu = 'users'; ?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- メイン -->
    <main>
        <div class="page-header">
            <div>
                <div class="page-title">ユーザー管理</div>
                <p class="page-desc">B-Careを利用するユーザーの新規追加・権限変更・利用停止を行います。</p>
            </div>
            <button class="btn-add" id="openCreateModal" type="button">
                <span>＋</span>
                <span>新規ユーザー追加</span>
            </button>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert-success">保存しました。</div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <!-- フィルター -->
        <div class="card filter-card">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>キーワード</label>
                    <input id="keywordInput" type="search" placeholder="氏名・ユーザーID・メールアドレスで検索">
                </div>
                <div class="filter-group">
                    <label>権限</label>
                    <select id="roleFilter">
                        <option value="">すべて</option>
                        <option value="管理者">管理者</option>
                        <option value="スタッフ">スタッフ</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>所属病棟</label>
                    <select id="wardFilter">
                        <option value="">すべて</option>
                        <option value="3階東病棟">3階東病棟</option>
                        <option value="4階西病棟">4階西病棟</option>
                        <option value="2階南病棟">2階南病棟</option>
                        <option value="事務部">事務部</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>利用状態</label>
                    <select id="statusFilter">
                        <option value="">すべて</option>
                        <option value="有効">有効</option>
                        <option value="無効">無効</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn-filter" id="filterButton" type="button">絞り込む</button>
                    <button class="btn-clear" id="clearButton" type="button">クリア</button>
                </div>
            </div>

            <label class="checkbox-row">
                <input id="showInactive" type="checkbox">
                <span>無効なユーザーも表示する</span>
            </label>
        </div>

        <!-- テーブル -->
        <div class="table-wrap">
            <div class="table-meta" id="recordCount"></div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ユーザーID</th>
                            <th>氏名</th>
                            <th>所属部署・病棟</th>
                            <th>権限</th>
                            <th>利用状態</th>
                            <th>最終ログイン</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody"></tbody>
                </table>
            </div>

            <div class="pagination" id="pagination"></div>
        </div>

        <!-- ご案内 -->
        <div class="info-card">
            <div class="info-title">ⓘ ユーザー管理のご案内</div>
            <ul>
                <li>ユーザーIDはログイン時に使用します。登録後の変更はできません。</li>
                <li>権限は「管理者」と「スタッフ」の2種類です。</li>
                <li>ユーザーを無効化すると、B-Careへログインできなくなります。</li>
                <li>無効化したユーザーは一覧から非表示になりますが、「無効なユーザーも表示する」にチェックを入れると表示できます。</li>
            </ul>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- ユーザー追加・編集モーダル -->
<div class="modal-backdrop hidden" id="userModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <h2 id="modalTitle">新規ユーザー追加</h2>
            <button class="icon-button" id="closeModal" type="button" aria-label="閉じる">×</button>
        </div>

        <form id="userForm" method="POST" action="user_management.php">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="staff_id_hidden" id="staffIdHidden">

            <div class="form-grid">
                <div class="field">
                    <label>ユーザーID</label>
                    <input name="user_id" id="userId" type="text" required placeholder="例：s023">
                </div>

                <div class="field">
                    <label>氏名</label>
                    <input name="user_name" id="userName" type="text" required placeholder="例：山田 太郎">
                </div>

                <div class="field full">
                    <label>メールアドレス</label>
                    <input name="user_email" id="userEmail" type="email" required placeholder="example@tomare.co.jp">
                </div>

                <div class="field">
                    <label>所属部署・病棟</label>
                    <select name="user_ward" id="userWard" required>
                        <option value="">選択してください</option>
                        <option value="3階東病棟">3階東病棟</option>
                        <option value="4階西病棟">4階西病棟</option>
                        <option value="2階南病棟">2階南病棟</option>
                        <option value="事務部">事務部</option>
                    </select>
                </div>

                <div class="field">
                    <label>権限</label>
                    <select name="user_role" id="userRole" required>
                        <option value="スタッフ">スタッフ</option>
                        <option value="管理者">管理者</option>
                    </select>
                </div>

                <div class="field">
                    <label>利用状態</label>
                    <select name="user_status" id="userStatus" required>
                        <option value="有効">有効</option>
                        <option value="無効">無効</option>
                    </select>
                </div>

                <div class="field full">
                    <label id="userPasswordLabel">初期パスワード</label>
                    <input name="user_password" id="userPassword" type="password" placeholder="ログイン用のパスワードを入力" autocomplete="new-password">
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-clear" id="cancelModal" type="button">キャンセル</button>
                <button class="btn-save" type="submit">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
  window.INITIAL_USERS = <?= json_encode($usersForJs, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="js/user_management.js?v=4"></script>
</body>
</html>
