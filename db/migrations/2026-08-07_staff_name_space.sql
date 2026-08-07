-- B-Care DB migration: staff.name の姓「医療」と名の間に半角スペースを挿入
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- staff には patients のような last_name/first_name の分割列がないため、
-- 全員が「医療」始まりであることを前提に、先頭2文字の直後にスペースを挿入する。

UPDATE staff
SET name = CONCAT('医療', ' ', SUBSTRING(name, 3))
WHERE name LIKE '医療%' AND name NOT LIKE '医療 %';
