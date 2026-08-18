-- B-Care DB migration: 患者にAIMS ESLラベルコードを紐付ける列を追加
-- 作成日: 2026-08-14
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 患者ベッドに設置されたESL(電子棚札)デバイスのlabelCodeを保持する。
-- 1患者につき常に1台のESLが紐付く想定のため、patientsテーブルに列追加する。
-- AIMS側の /labels/link/article/{stationCode} 呼び出し時にこの列を参照する。

ALTER TABLE patients
  ADD COLUMN esl_label_code VARCHAR(18) NULL COMMENT 'AIMS ESLラベルのlabelCode' AFTER qr_url;
