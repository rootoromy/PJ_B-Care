-- B-Care DB migration: pictogramsにID=0の空欄用ダミーレコードを追加
-- 作成日: 2026-08-20
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- AIMSへの配信データ(DATA_12以降)は、割当ピクトグラムが枠数に満たない場合
-- 0で埋める仕様。0がpictogramsマスタに存在しないとAIMS側で「該当なし」を
-- 表現できないため、pictogram_id=0の空欄レコードを追加する。
--
-- AUTO_INCREMENTカラムへ明示的に0を入れるには
-- NO_AUTO_VALUE_ON_ZERO が必要（セッション内のみ有効）。

SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_AUTO_VALUE_ON_ZERO');

INSERT INTO pictograms (pictogram_id, code, name, category, image_path)
VALUES (0, '', '', NULL, NULL);
