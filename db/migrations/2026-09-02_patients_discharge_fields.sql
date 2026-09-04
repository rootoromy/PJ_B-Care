-- B-Care DB migration: 退院処理の記録用カラムを追加
-- 作成日: 2026-09-02
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- is_admitted (2026-08-31追加) を0にする「退院処理」を実装するにあたり、
-- 誰がいつ退院処理を行ったかを patient_deposits.stored_at/stored_by と
-- 同じ命名慣習で記録する。

ALTER TABLE patients
  ADD COLUMN discharged_at TIMESTAMP NULL DEFAULT NULL COMMENT '退院処理日時' AFTER is_admitted,
  ADD COLUMN discharged_by VARCHAR(20) NULL DEFAULT NULL COMMENT '退院処理を行ったstaff_id' AFTER discharged_at;
