-- B-Care DB migration: 患者の入院中フラグを追加
-- 作成日: 2026-08-31
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 電カル等からの新規入院・退院の日次同期(11:00想定)で、
-- 退院済み患者を判定・除外するための土台。
-- 職員側は既存のis_active(在籍中フラグとして稼働中。ログイン可否や
-- 医師・看護師選択リストの絞り込みに使用中)をそのまま流用するため、
-- staffテーブルへの列追加は行わない。

ALTER TABLE patients
  ADD COLUMN is_admitted TINYINT(1) NOT NULL DEFAULT 1 COMMENT '入院中フラグ(0:退院済み)' AFTER synced_at;

-- 既存レコードは全件「入院中」として扱う
UPDATE patients SET is_admitted = 1;
