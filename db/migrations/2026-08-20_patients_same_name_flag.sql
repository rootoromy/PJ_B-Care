-- B-Care DB migration: 患者に同姓同名有フラグを追加
-- 作成日: 2026-08-20
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 院内に同姓同名の患者が存在する場合、画面上で注意喚起表示を出すためのフラグ。
-- 自動判定はせず、運用側（登録・更新時のチェック）でON/OFFを設定する想定。

ALTER TABLE patients
  ADD COLUMN has_namesake TINYINT(1) NOT NULL DEFAULT 0 COMMENT '同姓同名有フラグ' AFTER patient_kana;
