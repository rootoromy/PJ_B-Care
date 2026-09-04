-- B-Care DB migration: ESL管理画面で非表示にしたラベルを記録するテーブル
-- 作成日: 2026-09-02
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- AIMSダッシュボードで削除したはずのラベルが、AIMSのGET /labels APIには
-- 残り続けるケース(2026-09-02に確認、AIMS側のUIとAPIのデータソースの不整合)
-- があり、B-Care側の一覧にゴーストとして表示され続けてしまう。
-- AIMS側の不整合が直るまでの間、B-Care側で個別に非表示にできるようにする。

CREATE TABLE esl_hidden_labels (
  label_code VARCHAR(18) NOT NULL PRIMARY KEY,
  hidden_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hidden_by  VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
