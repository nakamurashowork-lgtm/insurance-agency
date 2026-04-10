-- =====================================================================
-- 動作確認用DML: t_sales_case
-- 用途: 見込み顧客・既存顧客への営業案件パイプライン
-- 件数: 10件
-- ID範囲: 9001 - 9010
-- 依存: 01_m_customer.sql
-- 関連DDL: config/ddl/tenant/t_sales_case.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 9001: 顧客1011（法人見込み）自動車・火災保険乗り換え提案
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9001, 1011, NULL,
  '株式会社テスト見込商事 損保一括乗り換え提案', '新規', '自動車保険・火災保険・賠償責任保険', '提案中', 'B',
  380000, '2026-06',
  NULL, '2026-04-25', NULL, '現在の損保会社（大手直販）から当社への乗り換えを検討中。取締役会での承認待ち。見積書提出済み（4/5）。補償内容で高評価。',
  1, 1,
  0, 1, 1
);

-- 9002: 顧客1012（個人見込み）自動車保険新規（山田様紹介）→ 2月に成約済
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9002, 1012, NULL,
  '高橋良子 自動車保険乗り換え', '新規', '自動車保険', '成約', 'A',
  68000, '2026-03',
  '山田太郎（顧客1006）紹介', NULL, NULL, '2/12 成約。3/1 始期。成績7032計上済み。',
  2, 2,
  0, 1, 1
);

-- 9003: 顧客1013（個人見込み）生命保険新規 → 3月に成約済み
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9003, 1013, NULL,
  '中村浩 医療保険新規加入', '新規', '生命保険（医療保険）', '成約', 'A',
  36000, '2026-04',
  '銀行窓販（横浜中央銀行提携）', NULL, NULL, '3/10 申込完了。4/1 始期。成績7034計上済み。',
  2, 2,
  0, 2, 2
);

-- 9004: 顧客1001（重要法人）役員向け逓増定期保険
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9004, 1001, NULL,
  '株式会社テストコーポレーション 役員保険提案', 'クロスセル', '生命保険（逓増定期）', '提案中', 'A',
  3600000, '2026-06',
  NULL, '2026-04-15', NULL, '役員3名の退職金準備・節税目的。3/20 に提案書を説明済み。社長前向き。稟議提出結果待ち。',
  1, 1,
  0, 1, 1
);

-- 9005: 顧客1005（医療法人）医療機器保険提案
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9005, 1005, NULL,
  '医療法人テストクリニック 医療機器保険追加提案', 'クロスセル', '新種保険（動産総合）', 'ヒアリング中', 'B',
  85000, '2026-07',
  NULL, '2026-05-01', NULL, '新MRI装置導入に伴う動産保険の追加提案。既存火災保険の特約追加も検討中。',
  2, 2,
  0, 2, 2
);

-- 9006: 顧客1004（建設）工事保険増額提案
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9006, 1004, NULL,
  'テスト建設 大型工事案件対応 工事保険増額', 'アップセル', '新種保険（建設工事）', '提案中', 'B',
  250000, '2026-05',
  NULL, '2026-04-20', NULL, '大型土木工事の受注に伴い、工事保険の補償額増額を提案。現在の工事保険に上乗せ契約を検討中。',
  1, 1,
  0, 1, 1
);

-- 9007: 新規開拓（未登録）製造業見込み
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9007, NULL, NULL,
  'テスト精密機械株式会社 新規開拓', '新規開拓', '企業総合保険', 'アプローチ中', 'C',
  450000, NULL,
  '商工会議所経由', '2026-04-20', NULL, '商工会議所の紹介。現在アプローチ中。アポイント取得に向けて連絡を継続。',
  1, 1,
  0, 1, 1
);

-- 9008: 失注案件（顧客1002 新種保険、2025年9月）
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9008, 1002, 2011,
  'テスト運輸 運送保険 乗り換え阻止', '継続', '新種保険（運送保険）', '失注', NULL,
  45000, NULL,
  NULL, NULL, '競合他社がより安い保険料を提示。価格差約1万円で折り合えず。', '満期案件3022と連動。2025年9月に失注確定。来年度に再アプローチ予定。',
  1, 1,
  0, 1, 1
);

-- 9009: 既存顧客アップセル（顧客1020 店舗火災保険増額）
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9009, 1020, 2047,
  'テスト自動車販売 新ショールーム 火災保険増額', 'アップセル', '火災保険（店舗総合）', '提案中', 'A',
  320000, '2026-07',
  NULL, '2026-05-01', NULL, '新ショールーム開設に伴い火災保険の補償額を増額。建物評価書取得中。見積提出は5月予定。',
  1, 1,
  0, 1, 1
);

-- 9010: 見込み案件（個人・新規開拓）
INSERT INTO t_sales_case (
  id, customer_id, contract_id,
  case_name, case_type, product_type, status, prospect_rank,
  expected_premium, expected_contract_month,
  referral_source, next_action_date, lost_reason, memo,
  assigned_staff_id, staff_id,
  is_deleted, created_by, updated_by
) VALUES (
  9010, 1012, NULL,
  '高橋良子 傷害保険 追加提案', 'クロスセル', '傷害保険', '見込み', 'B',
  22000, '2026-07',
  NULL, '2026-06-01', NULL, '自動車保険成約後のクロスセル。傷害保険の案内を送付済み。夏頃に提案予定。',
  2, 2,
  0, 1, 1
);
