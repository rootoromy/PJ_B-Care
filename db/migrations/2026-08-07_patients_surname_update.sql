-- B-Care DB migration: patients の苗字を全員「患者」（カンジャ）に統一
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 名（first_name / first_name_kana）はそのまま変更しない。
-- patient_kana は last_name_kana + first_name_kana の GENERATED 列のため自動的に追随する。

UPDATE patients
SET
  last_name      = '患者',
  last_name_kana = 'カンジャ',
  patient_name   = CONCAT('患者', ' ', first_name);
