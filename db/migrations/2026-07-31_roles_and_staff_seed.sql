-- B-Care DB migration: 権限（roles）テーブルの追加 と staff.role の置き換え、職員データ投入
-- 作成日: 2026-07-31
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--（前回の移行で文字コード未指定のまま実行し、COMMENT文字列が化けたため、本ファイルで併せて修正する）

-- ---------------------------------------------------
-- 1. roles: 権限マスタ（admin / user）
-- ---------------------------------------------------
CREATE TABLE roles (
  role_id    INT NOT NULL AUTO_INCREMENT,
  role_key   VARCHAR(20) NOT NULL COMMENT 'コード内で参照するキー（admin / user）',
  role_name  VARCHAR(50) NOT NULL COMMENT '画面表示用の名称',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_role_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_key, role_name) VALUES
  ('admin', '管理者'),
  ('user', '一般ユーザー');

-- ---------------------------------------------------
-- 2. staff: role(ENUM) を role_id(FK) に置き換え、gender/age を追加
--    合わせて前回文字化けした COMMENT を修正
-- ---------------------------------------------------
ALTER TABLE staff
  MODIFY COLUMN staff_id VARCHAR(20) NOT NULL COMMENT '電カル側の職員ID',
  MODIFY COLUMN login_id VARCHAR(50) NOT NULL COMMENT 'B-Careログイン用ID',
  MODIFY COLUMN position VARCHAR(50) DEFAULT NULL COMMENT '職種（電カルから同期）',
  MODIFY COLUMN synced_at TIMESTAMP NULL DEFAULT NULL COMMENT '電カルからの最終同期日時',
  ADD COLUMN gender VARCHAR(10) DEFAULT NULL COMMENT '性別' AFTER name,
  ADD COLUMN age INT DEFAULT NULL COMMENT '年齢' AFTER gender,
  ADD COLUMN role_id INT NOT NULL COMMENT '権限(roles参照)' AFTER role,
  ADD CONSTRAINT fk_staff_role FOREIGN KEY (role_id) REFERENCES roles (role_id);

ALTER TABLE staff DROP COLUMN role;

-- ---------------------------------------------------
-- 3. 職員データ投入（テスト用）
--    password はいずれも login_id と同じ文字列（admin / user）をハッシュ化したもの
--    staff_id は電カル未連携のため仮ID（S001, S002）。EmrSyncService実装後、電カルの実IDに置き換わる想定
-- ---------------------------------------------------
INSERT INTO staff (staff_id, login_id, password_hash, name, gender, age, position, role_id, is_active)
VALUES
  ('S001', 'admin', '$2y$10$v0GVwvsHRykBbbVBTcmf.elGHfZH8sj4DZjiDvJlpA52f1Y/0bdVa', '医療太郎', '男性', 30, '医師',   (SELECT role_id FROM roles WHERE role_key = 'admin'), 1),
  ('S002', 'user',  '$2y$10$js./Lnw0QHIuAPJSNLXerONtZBTLjqjCA8UMbpp/JlEkUjqRD7nvW', '医療花子', '女性', 28, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'),  1);
