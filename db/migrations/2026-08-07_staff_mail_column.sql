-- B-Care DB migration: staff テーブルに mail 列を追加し、ダミーのメールアドレスを投入
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- mail はログインIDに @iryou_test.co.jp を付けたダミーアドレス（本番の実メールではない）。

ALTER TABLE staff
  ADD COLUMN mail VARCHAR(255) DEFAULT NULL COMMENT 'メールアドレス（ダミー）' AFTER login_id;

UPDATE staff
SET mail = CONCAT(login_id, '@iryou_test.co.jp')
WHERE mail IS NULL;
