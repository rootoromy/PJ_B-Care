-- B-Care DB migration: 保管場所マスタの追加データ
-- 作成日: 2026-07-31
-- 実行には --default-character-set=utf8mb4 を必ず指定すること

INSERT IGNORE INTO storage_locations (name) VALUES
  ('病棟金庫'),
  ('病棟保管庫'),
  ('ナースステーション'),
  ('病室備付金庫'),
  ('患者専用ロッカー'),
  ('義歯保管庫'),
  ('薬剤部'),
  ('医事課金庫'),
  ('外来・救急保管庫'),
  ('その他');
