-- B-Care DB migration: patients.doctor_name / primary_nurse を staff テーブルの
-- 医師・看護師データからラウンドロビンで割り当て
-- 作成日: 2026-08-07
-- 実行には --default-character-set=utf8mb4 を必ず指定すること
--
-- 本来は電カルから主治医・受持看護師の情報が同期される想定の列だが、
-- 現時点では未連携のため、staff テーブルの実データ（医師7名・看護師15名）を
-- 順番に割り当ててデモ表示できるようにする。

UPDATE patients SET doctor_name = '医療 太郎', primary_nurse = '医療 花子' WHERE patient_id = 'P001';
UPDATE patients SET doctor_name = '医療 次郎', primary_nurse = '医療 梅子' WHERE patient_id = 'P002';
UPDATE patients SET doctor_name = '医療 三郎', primary_nurse = '医療 桃子' WHERE patient_id = 'P003';
UPDATE patients SET doctor_name = '医療 四郎', primary_nurse = '医療 愛子' WHERE patient_id = 'P004';
UPDATE patients SET doctor_name = '医療 五郎', primary_nurse = '医療 恵美' WHERE patient_id = 'P005';
UPDATE patients SET doctor_name = '医療 六郎', primary_nurse = '医療 さくら' WHERE patient_id = 'P006';
UPDATE patients SET doctor_name = '医療 七郎', primary_nurse = '医療 陽子' WHERE patient_id = 'P007';
UPDATE patients SET doctor_name = '医療 太郎', primary_nurse = '医療 由美' WHERE patient_id = 'P008';
UPDATE patients SET doctor_name = '医療 次郎', primary_nurse = '医療 千夏' WHERE patient_id = 'P009';
UPDATE patients SET doctor_name = '医療 三郎', primary_nurse = '医療 直子' WHERE patient_id = 'P010';
UPDATE patients SET doctor_name = '医療 四郎', primary_nurse = '医療 真理' WHERE patient_id = 'P011';
UPDATE patients SET doctor_name = '医療 五郎', primary_nurse = '医療 美咲' WHERE patient_id = 'P012';
UPDATE patients SET doctor_name = '医療 六郎', primary_nurse = '医療 春香' WHERE patient_id = 'P013';
UPDATE patients SET doctor_name = '医療 七郎', primary_nurse = '医療 夏美' WHERE patient_id = 'P014';
UPDATE patients SET doctor_name = '医療 太郎', primary_nurse = '医療 秋穂' WHERE patient_id = 'P015';
UPDATE patients SET doctor_name = '医療 次郎', primary_nurse = '医療 花子' WHERE patient_id = 'P016';
UPDATE patients SET doctor_name = '医療 三郎', primary_nurse = '医療 梅子' WHERE patient_id = 'P017';
UPDATE patients SET doctor_name = '医療 四郎', primary_nurse = '医療 桃子' WHERE patient_id = 'P018';
UPDATE patients SET doctor_name = '医療 五郎', primary_nurse = '医療 愛子' WHERE patient_id = 'P019';
UPDATE patients SET doctor_name = '医療 六郎', primary_nurse = '医療 恵美' WHERE patient_id = 'P020';
UPDATE patients SET doctor_name = '医療 七郎', primary_nurse = '医療 さくら' WHERE patient_id = 'P021';
UPDATE patients SET doctor_name = '医療 太郎', primary_nurse = '医療 陽子' WHERE patient_id = 'P022';
UPDATE patients SET doctor_name = '医療 次郎', primary_nurse = '医療 由美' WHERE patient_id = 'P023';
