-- B-Care DB migration: 閲覧専用ロール(viewer)の追加
-- 作成日: 2026-09-09
-- 食事介助者など、患者情報の閲覧のみを行い、ピクトグラム変更・預かり品登録/返却・
-- 退院処理・ESLラベル割当/解除などの書き込み操作は一切行わせたくないユーザー向け。

INSERT INTO roles (role_key, role_name) VALUES ('viewer', '閲覧のみ');
