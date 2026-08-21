-- B-Care DB migration: ESL自動配信のための最終同期日時列とトリガーを追加
-- 作成日: 2026-08-21
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 方針: patientsには updated_at (ON UPDATE CURRENT_TIMESTAMP) が既にあるため、
-- 「esl_synced_at より updated_at が新しい患者」を定期的に探して再配信すれば、
-- 直接SQLでの更新も含めてあらゆる変更を検知できる。
-- ただしpatient_pictogramsの追加・削除だけはpatients.updated_atを更新しないため、
-- トリガーでpatients.updated_atを touch するようにする。

ALTER TABLE patients
  ADD COLUMN esl_synced_at TIMESTAMP NULL DEFAULT NULL COMMENT 'ESLへ最後に自動配信した日時' AFTER esl_label_code;

DELIMITER $$

CREATE TRIGGER trg_patient_pictograms_after_insert
AFTER INSERT ON patient_pictograms
FOR EACH ROW
BEGIN
  UPDATE patients SET updated_at = CURRENT_TIMESTAMP WHERE patient_id = NEW.patient_id;
END$$

CREATE TRIGGER trg_patient_pictograms_after_delete
AFTER DELETE ON patient_pictograms
FOR EACH ROW
BEGIN
  UPDATE patients SET updated_at = CURRENT_TIMESTAMP WHERE patient_id = OLD.patient_id;
END$$

DELIMITER ;
