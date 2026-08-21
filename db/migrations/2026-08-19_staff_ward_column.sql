-- B-Care DB migration: staff テーブルに所属部署・病棟を追加
-- 作成日: 2026-08-19
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- user_management.php（ユーザー管理画面）をstaffテーブルに接続するにあたり、
-- 画面側で管理している「所属部署・病棟」に対応する列がstaffテーブルに無いため追加する。
-- patients.ward_name と同じく自由入力（マスタ化しない）。

ALTER TABLE staff
  ADD COLUMN ward_name VARCHAR(50) DEFAULT NULL COMMENT '所属部署・病棟' AFTER position;

-- 既存デモ職員データに所属部署・病棟を仮で割り当て（確認用）
UPDATE staff SET ward_name = ELT(1+(CAST(SUBSTRING(staff_id,2) AS UNSIGNED) % 4), '3階東病棟','4階西病棟','2階南病棟','事務部') WHERE ward_name IS NULL;
