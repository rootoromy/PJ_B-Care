-- B-Care DB migration draft: 職員管理 / 預かり品管理
-- 作成日: 2026-07-31
-- 実行前にレビューしてください。まだ本番DBには適用していません。

-- ---------------------------------------------------
-- 1. patients: 電カル同期用の列を追加
--    patient_id 自体が電カル側の患者IDをそのまま使う想定のため、
--    突合用の別列(emr_patient_id)は不要。synced_at のみ追加する。
-- ---------------------------------------------------
ALTER TABLE patients
  ADD COLUMN synced_at TIMESTAMP NULL DEFAULT NULL COMMENT '電カルからの最終同期日時' AFTER updated_at;

-- ---------------------------------------------------
-- 2. staff: 職員情報（ログインアカウント + 電カル同期）
--    staff_id は電カル側の職員IDをそのまま使う（patients と同じ考え方）
--    login_id / password_hash は B-Care 独自のログイン用情報
--    position（職種）は電カルから同期する想定のため自由入力（マスタ化しない）
--    role（admin/user）は B-Care 独自の権限で、電カル同期の対象外
-- ---------------------------------------------------
CREATE TABLE staff (
  staff_id      VARCHAR(20)  NOT NULL COMMENT '電カル側の職員ID',
  login_id      VARCHAR(50)  NOT NULL COMMENT 'B-Careログイン用ID',
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(100) NOT NULL,
  position      VARCHAR(50)  DEFAULT NULL COMMENT '職種（電カルから同期）',
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  synced_at     TIMESTAMP    NULL DEFAULT NULL COMMENT '電カルからの最終同期日時',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_id),
  UNIQUE KEY uq_staff_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 3. storage_locations: 保管場所マスタ（B-Care独自、電カル連携なし）
-- ---------------------------------------------------
CREATE TABLE storage_locations (
  location_id INT NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id),
  UNIQUE KEY uq_storage_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 4. deposit_item_masters: 預かり品名マスタ（B-Care独自、電カル連携なし）
--    unit: '個' or '円'（現金用）。登録画面のステッパー/金額入力の出し分けに使う
-- ---------------------------------------------------
CREATE TABLE deposit_item_masters (
  item_master_id INT NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  unit           VARCHAR(10) NOT NULL DEFAULT '個',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (item_master_id),
  UNIQUE KEY uq_deposit_item_masters_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期データ（登録画面のマスタ一覧に出ていた品目）
INSERT INTO deposit_item_masters (name, unit) VALUES
  ('財布', '個'),
  ('スマートフォン', '個'),
  ('義歯（上下）', '個'),
  ('補聴器', '個'),
  ('眼鏡', '個'),
  ('腕時計', '個'),
  ('指輪', '個'),
  ('ネックレス', '個'),
  ('現金', '円'),
  ('鍵', '個'),
  ('コンタクトレンズ', '個');

-- ---------------------------------------------------
-- 5. patient_deposits: 患者の預かり品（B-Care独自、電カル連携なし）
--    1行 = 1つの預かり品の状態（預かり中 or 返却済み）
--    item_master_id が NULL の場合は「その他（新規登録・今回のみ）」で入力された自由入力品目
--    item_name はマスタ選択時も含めて常にスナップショットとして保存
--    （マスタの名前が後から変わっても過去の記録は変わらないようにするため）
-- ---------------------------------------------------
CREATE TABLE patient_deposits (
  deposit_id          INT NOT NULL AUTO_INCREMENT,
  patient_id          VARCHAR(20) NOT NULL,
  item_master_id      INT DEFAULT NULL,
  item_name           VARCHAR(100) NOT NULL,
  quantity            INT NOT NULL DEFAULT 1 COMMENT '数量、現金の場合は金額',
  condition_note       VARCHAR(255) DEFAULT NULL COMMENT '状態（傷あり、動作良好など）',
  storage_location_id INT NOT NULL,
  status              ENUM('stored','returned') NOT NULL DEFAULT 'stored',
  stored_at           DATETIME NOT NULL,
  stored_by           VARCHAR(20) NOT NULL COMMENT '預かった職員(staff_id)',
  returned_at         DATETIME DEFAULT NULL,
  returned_by         VARCHAR(20) DEFAULT NULL COMMENT '返却した職員(staff_id)',
  return_to           ENUM('本人','家族','その他') DEFAULT NULL,
  return_to_other     VARCHAR(100) DEFAULT NULL COMMENT 'return_to=その他 の場合の自由入力',
  remarks             VARCHAR(200) DEFAULT NULL COMMENT '預かり時の備考',
  return_remarks      VARCHAR(200) DEFAULT NULL COMMENT '返却時の備考',
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (deposit_id),
  KEY idx_patient_deposits_patient (patient_id),
  KEY idx_patient_deposits_item_master (item_master_id),
  KEY idx_patient_deposits_storage_location (storage_location_id),
  KEY idx_patient_deposits_stored_by (stored_by),
  KEY idx_patient_deposits_returned_by (returned_by),
  CONSTRAINT fk_patient_deposits_patient
    FOREIGN KEY (patient_id) REFERENCES patients (patient_id),
  CONSTRAINT fk_patient_deposits_item_master
    FOREIGN KEY (item_master_id) REFERENCES deposit_item_masters (item_master_id),
  CONSTRAINT fk_patient_deposits_storage_location
    FOREIGN KEY (storage_location_id) REFERENCES storage_locations (location_id),
  CONSTRAINT fk_patient_deposits_stored_by
    FOREIGN KEY (stored_by) REFERENCES staff (staff_id),
  CONSTRAINT fk_patient_deposits_returned_by
    FOREIGN KEY (returned_by) REFERENCES staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
