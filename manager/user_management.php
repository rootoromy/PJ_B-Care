<?php
/**
 * B-Care Manager - ユーザー管理（モック）
 * 配置先: manager/user_management.php
 *
 * DB未接続のフロントエンドのみのモックです（js/user_management.js 内のダミーデータで動作）。
 */

require_once __DIR__ . '/../includes/config.php';
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
    <link rel="stylesheet" href="css/user_management.css?v=1">
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
            <div class="table-meta" id="recordCount">全6件中 1〜6件を表示</div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ユーザーID</th>
                            <th>氏名</th>
                            <th>メールアドレス</th>
                            <th>所属部署・病棟</th>
                            <th>権限</th>
                            <th>利用状態</th>
                            <th>最終ログイン</th>
                            <th>更新日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody"></tbody>
                </table>
            </div>

            <div class="pagination">
                <span class="disabled">&#8249;</span>
                <span class="current">1</span>
                <span class="disabled">&#8250;</span>
            </div>
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

        <form id="userForm">
            <input type="hidden" id="editingId">

            <div class="form-grid">
                <div class="field">
                    <label>ユーザーID</label>
                    <input id="userId" type="text" required placeholder="例：U007">
                </div>

                <div class="field">
                    <label>氏名</label>
                    <input id="userName" type="text" required placeholder="例：山田 太郎">
                </div>

                <div class="field full">
                    <label>メールアドレス</label>
                    <input id="userEmail" type="email" required placeholder="example@tomare.co.jp">
                </div>

                <div class="field">
                    <label>所属部署・病棟</label>
                    <select id="userWard" required>
                        <option value="">選択してください</option>
                        <option value="3階東病棟">3階東病棟</option>
                        <option value="4階西病棟">4階西病棟</option>
                        <option value="2階南病棟">2階南病棟</option>
                        <option value="事務部">事務部</option>
                    </select>
                </div>

                <div class="field">
                    <label>権限</label>
                    <select id="userRole" required>
                        <option value="スタッフ">スタッフ</option>
                        <option value="管理者">管理者</option>
                    </select>
                </div>

                <div class="field">
                    <label>利用状態</label>
                    <select id="userStatus" required>
                        <option value="有効">有効</option>
                        <option value="無効">無効</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-clear" id="cancelModal" type="button">キャンセル</button>
                <button class="btn-save" type="submit">保存</button>
            </div>
        </form>
    </div>
</div>

<script src="js/user_management.js?v=1"></script>
</body>
</html>
