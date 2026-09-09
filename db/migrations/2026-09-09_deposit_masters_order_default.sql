-- B-Care DB migration: 預かり品マスタ・保管場所マスタに表示順/デフォルト列を追加
-- 作成日: 2026-09-09
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- manager/deposit_management.php（預かり品管理画面）で、マスタの並び順を
-- 手動管理できるようにするための display_order と、Mobile側の預かり品登録
-- 画面でデフォルト候補として出す品目を示す is_default（品目マスタのみ）を追加する。

ALTER TABLE deposit_item_masters
  ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Mobile預かり品登録画面のデフォルト候補' AFTER unit,
  ADD COLUMN display_order INT NOT NULL DEFAULT 0 COMMENT '表示順' AFTER is_default;

ALTER TABLE storage_locations
  ADD COLUMN display_order INT NOT NULL DEFAULT 0 COMMENT '表示順' AFTER name;

-- 既存データに現在のID順で表示順を割り振る
SET @order := 0;
UPDATE deposit_item_masters SET display_order = (@order := @order + 1) ORDER BY item_master_id;

SET @order := 0;
UPDATE storage_locations SET display_order = (@order := @order + 1) ORDER BY location_id;

-- よく使う品目をデフォルト候補としてマークしておく（運用開始後は画面から変更可能）
UPDATE deposit_item_masters SET is_default = 1 WHERE name IN ('財布', 'スマートフォン', '義歯（上下）', '補聴器');
