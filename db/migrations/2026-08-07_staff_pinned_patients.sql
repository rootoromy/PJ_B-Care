-- B-Care DB migration: 患者一覧のピックアップ（ピン留め）をスタッフ単位で管理するテーブル
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 端末・ブラウザ単位の localStorage では、ナースステーションの共有端末で
-- 複数スタッフがログインし直した際にピン留め状態が混ざってしまうため、
-- staff_id 単位でDBに保存する方式に変更する。

CREATE TABLE IF NOT EXISTS staff_pinned_patients (
  staff_id   VARCHAR(20) NOT NULL,
  patient_id VARCHAR(20) NOT NULL,
  pinned_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_id, patient_id),
  CONSTRAINT fk_pinned_staff   FOREIGN KEY (staff_id)   REFERENCES staff(staff_id)     ON DELETE CASCADE,
  CONSTRAINT fk_pinned_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
