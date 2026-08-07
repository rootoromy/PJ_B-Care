-- B-Care DB migration: ユーザー管理画面確認用デモ職員データの追加
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- password はいずれも login_id と同じ文字列をハッシュ化したもの（既存シードと同じ方式）。
-- admin: user、医師: 看護師 がそれぞれ少数派になるよう配分（既存S001/S002と合わせて
-- admin 5名 / user 17名、医師 7名 / 看護師 15名）。

INSERT IGNORE INTO staff
  (staff_id, login_id, password_hash, name, gender, age, position, role_id, is_active)
VALUES
('S003', 's003', '$2y$10$xIvo5x4WX0JWiRJoN.Hls.hL6gKpJTwLTvX2YDEdi6aWUB1DFKfOO', '医療次郎', '男性', 42, '医師', (SELECT role_id FROM roles WHERE role_key = 'admin'), 1),
('S004', 's004', '$2y$10$acS.4fSdCGK5WABbUp/1buOS5fDTzYddQL8pmiZr7cSPTcOjmekdS', '医療三郎', '男性', 55, '医師', (SELECT role_id FROM roles WHERE role_key = 'admin'), 1),
('S005', 's005', '$2y$10$qN3zljKs3OodxIqc3aNCj.vRqHt4n9FzuOQbKOb683WTNQcMY6OKq', '医療四郎', '男性', 33, '医師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S006', 's006', '$2y$10$O7mTsrr6kyA1Yq5jQT1oBe.4O0nU2PxQeU5VpRntDWcx2hOjxDZVK', '医療五郎', '男性', 47, '医師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S007', 's007', '$2y$10$sMcu8rvFZDciuwVoGHm3FexLxoKbiRQO/CEv0jLlc78a0W454W0yq', '医療六郎', '男性', 29, '医師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S008', 's008', '$2y$10$0dV7P1V4igr58JGHRR.pDO85vaKexe0WJmx35q65h9oo8Ixhc36Ba', '医療七郎', '男性', 38, '医師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S009', 's009', '$2y$10$Wdc5qAItNqmWewjEJ6RHMO1/3MLSEqR1PB.a.JbZzXn2lmtEOex/.', '医療梅子', '女性', 31, '看護師', (SELECT role_id FROM roles WHERE role_key = 'admin'), 1),
('S010', 's010', '$2y$10$rkNJ4uWqipiF8KF2e5vbXOWIcNdtq/3ARaGBTvTudyM60sTj1uxbi', '医療桃子', '女性', 26, '看護師', (SELECT role_id FROM roles WHERE role_key = 'admin'), 1),
('S011', 's011', '$2y$10$viytjhfASQoPf11corwOX.EUKezzazniPNGhfNvpJpi.QgitdX26a', '医療愛子', '女性', 45, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S012', 's012', '$2y$10$H9w/3zXtA92nk248ZSJeiunBc3FH.l/DZn/BjMorDwumpO2B.qLD2', '医療恵美', '女性', 34, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S013', 's013', '$2y$10$vj5c9.DqIdI6SyTPrCOsxesN2SzFW1lF4jx5IL46opjSy4H/8QIs6', '医療さくら', '女性', 24, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S014', 's014', '$2y$10$zbAyR7IfNKocm5Wwh0gIded0iayG/p8ln0BCYVIoEyRNwTVmy/5EC', '医療陽子', '女性', 41, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S015', 's015', '$2y$10$XgXI8KBxkXVfu.cb1G7vpeY.u7XYhEvqsFK9bYpJwgIp.kcaMFmL.', '医療由美', '女性', 37, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S016', 's016', '$2y$10$SaZW6UysKPgqE9kAhBmu7eup05hId9CqEluK2MOsraiuCFLFtDVPW', '医療千夏', '女性', 28, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S017', 's017', '$2y$10$uMBBQflLxRG2aCPynwNT6O5B8y.rAY8tAUDalUFs9gmVvKvvw8WCy', '医療直子', '女性', 52, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S018', 's018', '$2y$10$2Z4Tl.Lc/9weQqDqQsYMpeJEfo4HNJ3JUk4NQs/mMx3Zt2h0hLGUS', '医療真理', '女性', 39, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S019', 's019', '$2y$10$WvuzBKxBDxQIyyMIMtDae.Uy3W8A0i5MCHjfKKchlAUt/wfpSSFhC', '医療美咲', '女性', 25, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S020', 's020', '$2y$10$iILCyanyk6P/gAawc.7FLOo5uOxn5wmE5jhED4LGt3wLv/zZrfVIO', '医療春香', '女性', 30, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S021', 's021', '$2y$10$RrDE79m//B2/L1SMLwXOAOhbNVd80sG05LAfu.h294iuyPBrsrVxe', '医療夏美', '女性', 44, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1),
('S022', 's022', '$2y$10$6IgsdHFq3pJAVZ4in8T2OOCZJo49fhYm6YlF/gc8On/UFUzYJaFrK', '医療秋穂', '女性', 27, '看護師', (SELECT role_id FROM roles WHERE role_key = 'user'), 1);
