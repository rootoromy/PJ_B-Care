-- B-Care DB migration: 預かり品マスタの is_default 列を削除
-- 作成日: 2026-09-09
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 2026-09-09_deposit_masters_order_default.sql で追加した is_default は
-- 「デフォルト」列としてmanager/deposit_management.phpに表示していたが、
-- Mobile側の預かり品登録画面には未連携で使い道がないため削除する。

ALTER TABLE deposit_item_masters
  DROP COLUMN is_default;
