-- B-Care DB migration draft: 患者予定（今日の予定・明日の予定）テーブルの再設計
-- 作成日: 2026-08-03
-- 実行前にレビューしてください。

-- ---------------------------------------------------
-- 背景
-- ---------------------------------------------------
-- 既存の patient_schedule（id, patient_id, content, display_order, created_at）には
-- 予定の日時カラムが無く、「今日の予定」「明日の予定」を区別できない作りだった。
-- また、どのマイグレーションでも管理されておらず、実装（block_schedule_today.php /
-- block_schedule_tomorrow.php）からも参照されていない（ダミー文言を表示しているだけ）。
--
-- 電カル側は「今日／明日」という単位ではなく、予定を一括で返してくる想定のため、
-- B-Care側も日付・時刻ごとに分けたテーブルは持たず、vitals テーブルと同じ考え方で
-- 1つのテーブルに実際の日時（scheduled_at）付きで保存し、「今日」「明日」は
-- アプリ側のクエリで WHERE DATE(scheduled_at) = ... と絞り込むだけにする。
-- ---------------------------------------------------

DROP TABLE IF EXISTS patient_schedule;

CREATE TABLE patient_schedule (
  schedule_id  INT NOT NULL AUTO_INCREMENT,
  patient_id   VARCHAR(20) NOT NULL COMMENT '電カル側の患者ID（patients.patient_id と同じ）',
  scheduled_at DATETIME NOT NULL COMMENT '予定日時（電カルから一括同期）',
  category     VARCHAR(50) DEFAULT NULL COMMENT '種別（検査・処置・リハビリなど、電カルから同期）',
  content      VARCHAR(255) NOT NULL COMMENT '予定内容',
  location     VARCHAR(100) DEFAULT NULL COMMENT '場所（採血室、リハビリ室など、電カルから同期）',
  synced_at    TIMESTAMP NULL DEFAULT NULL COMMENT '電カルからの最終同期日時',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (schedule_id),
  KEY idx_patient_schedule_patient_date (patient_id, scheduled_at),
  CONSTRAINT fk_patient_schedule_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
