-- =====================================================================
-- 動作確認用DML: t_contract
-- 用途: 各顧客の保険契約データ
-- 件数: 50件
-- ID範囲: 2001 - 2050
-- 依存: 01_m_customer.sql（m_customer）
-- 関連DDL: config/ddl/tenant/t_contract.sql
-- 備考: sales_staff_id / office_staff_id は m_staff.id 参照。
--       m_staff にスタッフ id=1,2 が存在すること。
--       policy_no + policy_end_date の組み合わせは一意制約あり。
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 顧客1001（重要法人）: 2001-2006 ==========

-- 2001: 自動車保険（満期2026-05-01, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2001, 1001, NULL, 'TC001-2001-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2025-05-01', '2026-05-01', 180000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2002: 火災保険（満期2026-06-15）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2002, 1001, NULL, 'TC001-2002-FIRE',
  '損保ジャパン', '火災保険', '普通火災',
  '2025-06-15', '2026-06-15', 250000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2003: 傷害保険（満期2026-12-31）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2003, 1001, NULL, 'TC001-2003-CASU',
  '三井住友海上', '傷害保険', '普通傷害',
  '2025-12-31', '2026-12-31', 90000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2004: 企業総合保険（満期2026-10-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2004, 1001, NULL, 'TC001-2004-CMP',
  '東京海上日動', '企業総合保険', '企業総合',
  '2025-10-01', '2026-10-01', 420000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2005: 賠償責任保険（満期2026-04-20, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2005, 1001, NULL, 'TC001-2005-LIAB',
  '損保ジャパン', '賠償責任保険', '施設賠償',
  '2025-04-20', '2026-04-20', 75000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2006: 生命保険（満期2026-04-30, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2006, 1001, NULL, 'TC001-2006-LIFE',
  '日本生命', '生命保険', '定期保険',
  '2025-04-30', '2026-04-30', 360000,
  'monthly', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- ========== 顧客1002（運輸法人）: 2007-2011 ==========

-- 2007: 自動車保険（満期2026-04-15, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2007, 1002, NULL, 'TC002-2007-AUTO',
  '三井住友海上', '自動車保険', 'フリート',
  '2025-04-15', '2026-04-15', 650000,
  'annual', 'active', 1, 2,
  'フリート10台', 0, 1, 1
);

-- 2008: 火災保険（満期2026-06-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2008, 1002, NULL, 'TC002-2008-FIRE',
  '損保ジャパン', '火災保険', '店舗総合',
  '2025-06-01', '2026-06-01', 180000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2009: 傷害保険（満期2026-03-31, 最近更改完了済み）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2009, 1002, NULL, 'TC002-2009-CASU',
  '東京海上日動', '傷害保険', '団体傷害',
  '2025-03-31', '2026-03-31', 120000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2010: 企業賠償保険（満期2026-07-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2010, 1002, NULL, 'TC002-2010-LIAB',
  '損保ジャパン', '賠償責任保険', '企業賠償',
  '2025-07-01', '2026-07-01', 95000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2011: 新種保険（満期2025-09-01, 失注のため expired）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2011, 1002, NULL, 'TC002-2011-MISC',
  '三井住友海上', '新種保険', '運送保険',
  '2024-09-01', '2025-09-01', 45000,
  'annual', 'expired', 1, 2,
  '更改交渉中に失注', 0, 1, 1
);

-- ========== 顧客1003（小売法人）: 2012-2015 ==========

-- 2012: 自動車保険（満期2026-04-25, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2012, 1003, NULL, 'TC003-2012-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2025-04-25', '2026-04-25', 95000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- 2013: 火災保険（満期2026-08-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2013, 1003, NULL, 'TC003-2013-FIRE',
  '損保ジャパン', '火災保険', '普通火災',
  '2025-08-01', '2026-08-01', 140000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- 2014: 傷害保険（満期2026-04-10, 30日以内・waiting_payment）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2014, 1003, NULL, 'TC003-2014-CASU',
  '三井住友海上', '傷害保険', '普通傷害',
  '2025-04-10', '2026-04-10', 55000,
  'annual', 'renewal_pending', 2, 1,
  NULL, 0, 2, 2
);

-- 2015: 店舗総合保険（満期2026-09-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2015, 1003, NULL, 'TC003-2015-STORE',
  '東京海上日動', '火災保険', '店舗総合',
  '2025-09-01', '2026-09-01', 210000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- ========== 顧客1004（建設法人）: 2016-2019 ==========

-- 2016: 自動車保険（満期2026-04-30, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2016, 1004, NULL, 'TC004-2016-AUTO',
  '損保ジャパン', '自動車保険', 'フリート',
  '2025-04-30', '2026-04-30', 320000,
  'annual', 'active', 1, 2,
  'フリート5台', 0, 1, 1
);

-- 2017: 工事保険（満期2026-11-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2017, 1004, NULL, 'TC004-2017-CONST',
  '三井住友海上', '新種保険', '建設工事',
  '2025-11-01', '2026-11-01', 185000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2018: 傷害保険（満期2026-05-01, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2018, 1004, NULL, 'TC004-2018-CASU',
  '東京海上日動', '傷害保険', '団体傷害',
  '2025-05-01', '2026-05-01', 78000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2019: 賠償保険（満期2026-07-15）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2019, 1004, NULL, 'TC004-2019-LIAB',
  '損保ジャパン', '賠償責任保険', '請負業者賠償',
  '2025-07-15', '2026-07-15', 110000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- ========== 顧客1005（医療法人）: 2020-2021 ==========

-- 2020: 医師賠償責任保険（満期2026-09-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2020, 1005, NULL, 'TC005-2020-MED',
  '東京海上日動', '賠償責任保険', '医師賠償責任',
  '2025-09-01', '2026-09-01', 285000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- 2021: 火災保険（満期2026-12-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2021, 1005, NULL, 'TC005-2021-FIRE',
  '損保ジャパン', '火災保険', '普通火災',
  '2025-12-01', '2026-12-01', 165000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- ========== 顧客1006（個人）: 2022-2024 ==========

-- 2022: 自動車保険（満期2026-04-25, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2022, 1006, NULL, 'TC006-2022-AUTO',
  '三井住友海上', '自動車保険', '一般自動車',
  '2025-04-25', '2026-04-25', 72000,
  'annual', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- 2023: 傷害保険（満期2026-08-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2023, 1006, NULL, 'TC006-2023-CASU',
  '東京海上日動', '傷害保険', '普通傷害',
  '2025-08-01', '2026-08-01', 28000,
  'annual', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- 2024: 生命保険（満期2026-11-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2024, 1006, NULL, 'TC006-2024-LIFE',
  '日本生命', '生命保険', '終身保険',
  '2025-11-01', '2026-11-01', 48000,
  'monthly', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- ========== 顧客1007（個人）: 2025-2027 ==========

-- 2025: 自動車保険（満期2026-05-07, 30日以内境界）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2025, 1007, NULL, 'TC007-2025-AUTO',
  '損保ジャパン', '自動車保険', '一般自動車',
  '2025-05-07', '2026-05-07', 65000,
  'annual', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- 2026: 火災保険（満期2026-10-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2026, 1007, NULL, 'TC007-2026-FIRE',
  '東京海上日動', '火災保険', '住宅総合',
  '2025-10-01', '2026-10-01', 38000,
  'annual', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- 2027: がん保険（満期2027-01-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2027, 1007, NULL, 'TC007-2027-CANC',
  '第一生命', '生命保険', 'がん保険',
  '2026-01-01', '2027-01-01', 24000,
  'monthly', 'active', 2, 1,
  NULL, 0, 1, 1
);

-- ========== 顧客1008（個人）: 2028-2029 ==========

-- 2028: 自動車保険（満期2026-06-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2028, 1008, NULL, 'TC008-2028-AUTO',
  '三井住友海上', '自動車保険', '一般自動車',
  '2025-06-01', '2026-06-01', 58000,
  'annual', 'active', 1, 2,
  NULL, 0, 2, 2
);

-- 2029: 傷害保険（満期2026-09-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2029, 1008, NULL, 'TC008-2029-CASU',
  '損保ジャパン', '傷害保険', '普通傷害',
  '2025-09-01', '2026-09-01', 22000,
  'annual', 'active', 1, 2,
  NULL, 0, 2, 2
);

-- ========== 顧客1009（個人・事故あり）: 2030-2031 ==========

-- 2030: 自動車保険（満期2026-04-15, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2030, 1009, NULL, 'TC009-2030-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2025-04-15', '2026-04-15', 82000,
  'annual', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- 2031: 生命保険（満期2026-12-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2031, 1009, NULL, 'TC009-2031-LIFE',
  '住友生命', '生命保険', '定期保険',
  '2025-12-01', '2026-12-01', 36000,
  'monthly', 'active', 2, 1,
  NULL, 0, 2, 2
);

-- ========== 顧客1010（個人）: 2032-2033 ==========

-- 2032: 自動車保険（満期2026-05-01, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2032, 1010, NULL, 'TC010-2032-AUTO',
  '損保ジャパン', '自動車保険', '一般自動車',
  '2025-05-01', '2026-05-01', 74000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2033: 火災保険（満期2026-08-15）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2033, 1010, NULL, 'TC010-2033-FIRE',
  '三井住友海上', '火災保険', '住宅総合',
  '2025-08-15', '2026-08-15', 31000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- ========== 顧客1014（法人休眠）: 2034-2035 ==========

-- 2034: 自動車保険（満期2025-03-31, 失注で inactive）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2034, 1014, NULL, 'TC014-2034-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2024-03-31', '2025-03-31', 125000,
  'annual', 'inactive', 1, 2,
  '2025年更改時に失注', 0, 1, 1
);

-- 2035: 火災保険（満期2025-06-01, 失注で inactive）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2035, 1014, NULL, 'TC014-2035-FIRE',
  '損保ジャパン', '火災保険', '普通火災',
  '2024-06-01', '2025-06-01', 88000,
  'annual', 'inactive', 1, 2,
  NULL, 0, 1, 1
);

-- ========== 顧客1015（個人休眠）: 2036 ==========

-- 2036: 自動車保険（満期2025-01-15, inactive）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2036, 1015, NULL, 'TC015-2036-AUTO',
  '三井住友海上', '自動車保険', '一般自動車',
  '2024-01-15', '2025-01-15', 62000,
  'annual', 'inactive', 2, 1,
  NULL, 0, 1, 1
);

-- ========== 顧客1016（個人休眠）: 2037 ==========

-- 2037: 傷害保険（満期2024-12-01, inactive）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2037, 1016, NULL, 'TC016-2037-CASU',
  '損保ジャパン', '傷害保険', '普通傷害',
  '2023-12-01', '2024-12-01', 18000,
  'annual', 'inactive', 2, 1,
  NULL, 0, 2, 2
);

-- ========== 顧客1017（法人解約済み）: 2038-2040 ==========

-- 2038: 自動車保険（満期2024-04-01, cancelled）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2038, 1017, NULL, 'TC017-2038-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2023-04-01', '2024-04-01', 95000,
  'annual', 'cancelled', 1, 2,
  '廃業のため解約', 0, 1, 1
);

-- 2039: 火災保険（満期2024-08-01, expired）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2039, 1017, NULL, 'TC017-2039-FIRE',
  '損保ジャパン', '火災保険', '普通火災',
  '2023-08-01', '2024-08-01', 72000,
  'annual', 'expired', 1, 2,
  '廃業のため更改せず失効', 0, 1, 1
);

-- 2040: 傷害保険（満期2023-12-01, cancelled）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2040, 1017, NULL, 'TC017-2040-CASU',
  '三井住友海上', '傷害保険', '団体傷害',
  '2022-12-01', '2023-12-01', 38000,
  'annual', 'cancelled', 1, 2,
  NULL, 0, 1, 1
);

-- ========== 顧客1018（個人解約済み）: 2041-2042 ==========

-- 2041: 自動車保険（満期2024-06-01, expired）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2041, 1018, NULL, 'TC018-2041-AUTO',
  '東京海上日動', '自動車保険', '一般自動車',
  '2023-06-01', '2024-06-01', 58000,
  'annual', 'expired', 2, 1,
  '連絡不通により失効', 0, 1, 1
);

-- 2042: 生命保険（満期2024-01-01, cancelled）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2042, 1018, NULL, 'TC018-2042-LIFE',
  '日本生命', '生命保険', '定期保険',
  '2023-01-01', '2024-01-01', 48000,
  'annual', 'cancelled', 2, 1,
  NULL, 0, 1, 1
);

-- ========== 顧客1019（個人解約済み）: 2043 ==========

-- 2043: 自動車保険（満期2023-09-01, cancelled）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2043, 1019, NULL, 'TC019-2043-AUTO',
  '損保ジャパン', '自動車保険', '一般自動車',
  '2022-09-01', '2023-09-01', 45000,
  'annual', 'cancelled', 2, 1,
  '2023年解約', 0, 2, 2
);

-- ========== 顧客1020（自動車販売法人・重要顧客）: 2044-2050 ==========

-- 2044: 自動車保険A（満期2026-04-30, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2044, 1020, NULL, 'TC020-2044-AUTO-A',
  '三井住友海上', '自動車保険', 'フリート',
  '2025-04-30', '2026-04-30', 280000,
  'annual', 'active', 1, 2,
  'フリートA グループ', 0, 1, 1
);

-- 2045: 自動車保険B（満期2026-05-01, 30日以内）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2045, 1020, NULL, 'TC020-2045-AUTO-B',
  '三井住友海上', '自動車保険', 'フリート',
  '2025-05-01', '2026-05-01', 195000,
  'annual', 'active', 1, 2,
  'フリートB グループ', 0, 1, 1
);

-- 2046: 自動車保険C（満期2026-06-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2046, 1020, NULL, 'TC020-2046-AUTO-C',
  '損保ジャパン', '自動車保険', 'フリート',
  '2025-06-01', '2026-06-01', 155000,
  'annual', 'active', 1, 2,
  '展示車・試乗車', 0, 1, 1
);

-- 2047: 火災保険（満期2026-07-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2047, 1020, NULL, 'TC020-2047-FIRE',
  '東京海上日動', '火災保険', '店舗総合',
  '2025-07-01', '2026-07-01', 220000,
  'annual', 'active', 1, 2,
  '本社ショールーム', 0, 1, 1
);

-- 2048: 企業総合保険（満期2026-10-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2048, 1020, NULL, 'TC020-2048-CMP',
  '三井住友海上', '企業総合保険', '企業総合',
  '2025-10-01', '2026-10-01', 380000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2049: 傷害保険（満期2026-09-15）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2049, 1020, NULL, 'TC020-2049-CASU',
  '東京海上日動', '傷害保険', '団体傷害',
  '2025-09-15', '2026-09-15', 96000,
  'annual', 'active', 1, 2,
  NULL, 0, 1, 1
);

-- 2050: 新種保険（満期2026-11-01）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no,
  insurer_name, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount,
  payment_cycle, status, sales_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  2050, 1020, NULL, 'TC020-2050-MISC',
  '損保ジャパン', '新種保険', '動産総合',
  '2025-11-01', '2026-11-01', 55000,
  'annual', 'active', 1, 2,
  '在庫車両動産保険', 0, 1, 1
);
