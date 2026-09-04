-- B-Care DB migration: vitalsテーブルのカラム型修正
-- 作成日: 2026-08-31
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- vitalsテーブルは他テーブルと異なりmigration管理外で作成されており、
-- 全カラムがvarchar(255)のまま運用されていた(要件定義書の棚卸しで判明)。
-- 数値・日時であるべきカラムを適切な型に修正する。既存データは全件
-- 有効な数値・日時文字列であることを確認済みのため、MODIFY COLUMNでの
-- 型変換のみ行い、値そのものは変更しない。
-- patient_idは元々正しい型(文字列)のため対象外。PK/FK/created_at等の
-- テーブル構造そのものの見直しは、電カル連携時にまとめて検討する。

ALTER TABLE vitals
  MODIFY COLUMN measured_at DATETIME NULL COMMENT '測定日時',
  MODIFY COLUMN temperature DECIMAL(3,1) NULL COMMENT '体温(℃)',
  MODIFY COLUMN systolic_bp SMALLINT UNSIGNED NULL COMMENT '血圧(収縮期)',
  MODIFY COLUMN diastolic_bp SMALLINT UNSIGNED NULL COMMENT '血圧(拡張期)',
  MODIFY COLUMN pulse SMALLINT UNSIGNED NULL COMMENT '脈拍',
  MODIFY COLUMN spo2 TINYINT UNSIGNED NULL COMMENT '血中酸素飽和度(%)',
  MODIFY COLUMN respiratory_rate TINYINT UNSIGNED NULL COMMENT '呼吸数';
