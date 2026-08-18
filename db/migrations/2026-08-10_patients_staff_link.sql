-- B-Care DB migration: 患者ごとの主治医・受持看護師を staff テーブルと正式に紐付ける
-- 作成日: 2026-08-10
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- これまで patients.doctor_name / primary_nurse は自由入力の文字列列で、
-- staff テーブルとの実体的な関連（FK）を持たず、スタッフ側の改名等が反映されなかった。
-- 患者 1人につき 主治医1名・受持看護師1名 を staff の実レコードに紐付ける
-- patients_staff 中間テーブルを新設し、旧列は廃止する。

-- ---------------------------------------------------
-- 1. patients_staff: 患者-職員の役割付き関連テーブル
--    PRIMARY KEY (patient_id, role) により、患者ごとに
--    doctor / nurse それぞれ最大1名までしか登録できない
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS patients_staff (
  patient_id  VARCHAR(20) NOT NULL,
  staff_id    VARCHAR(20) NOT NULL,
  role        ENUM('doctor','nurse') NOT NULL COMMENT 'doctor=主治医, nurse=受持看護師',
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (patient_id, role),
  KEY idx_patients_staff_staff (staff_id),
  CONSTRAINT fk_patients_staff_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  CONSTRAINT fk_patients_staff_staff   FOREIGN KEY (staff_id)   REFERENCES staff(staff_id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 2. 既存の patients.doctor_name / primary_nurse (文字列) を
--    staff.name と突き合わせて patients_staff へ移行
-- ---------------------------------------------------
INSERT IGNORE INTO patients_staff (patient_id, staff_id, role)
SELECT p.patient_id, s.staff_id, 'doctor'
FROM patients p
JOIN staff s ON s.name = p.doctor_name AND s.position = '医師'
WHERE p.doctor_name IS NOT NULL AND p.doctor_name <> '';

INSERT IGNORE INTO patients_staff (patient_id, staff_id, role)
SELECT p.patient_id, s.staff_id, 'nurse'
FROM patients p
JOIN staff s ON s.name = p.primary_nurse AND s.position = '看護師'
WHERE p.primary_nurse IS NOT NULL AND p.primary_nurse <> '';

-- ---------------------------------------------------
-- 3. 旧列を廃止
-- ---------------------------------------------------
ALTER TABLE patients
  DROP COLUMN doctor_name,
  DROP COLUMN primary_nurse;
