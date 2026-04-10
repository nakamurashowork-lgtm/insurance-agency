-- =====================================================================
-- 動作確認用DML: t_sales_performance
-- 用途: 成績データ（FY2024〜FY2026 前年比確認用）
-- 件数: 84件 / ID範囲: 7001-7084
--
-- FY2024（7001-7038）全12ヶ月  合計 5,507千円
--   2024-04:   948千円  2024-05: 1,098千円  2024-06:  442千円
--   2024-07:   698千円  2024-08:   182千円  2024-09:  568千円
--   2024-10:   794千円  2024-11:   262千円  2024-12:  266千円
--   2025-01:    18千円  2025-02:    40千円  2025-03:  191千円
--
-- FY2025（7039-7080）全12ヶ月  合計 6,096千円
--   2025-04: 1,219千円  2025-05: 1,307千円  2025-06:  488千円
--   2025-07:   580千円  2025-08:   199千円  2025-09:  613千円
--   2025-10:   838千円  2025-11:   288千円  2025-12:  291千円
--   2026-01:    24千円  2026-02:    43千円  2026-03:  206千円
--
-- FY2026（7081-7084）4月のみ   合計   205千円
--   2026-04:   205千円（staff1: 117千円 / staff2: 88千円）
--
-- 前年比（FY2024→FY2025）: +10.7%（月別に増減あり）
-- =====================================================================

SET NAMES utf8mb4;

TRUNCATE TABLE t_sales_performance;

-- ========== FY2024 成績（7001-7038）==========

-- 2024-04（948千円 / staff1: 820千円 / staff2: 128千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7001, 1002, 2007, NULL,
 '2024-04-05', 'renewal', 'non_life',
 '三井住友海上', 'TC002-2007-AUTO', '2024-04-15', NULL,
 '自動車保険', 'フリート',
 650000, NULL, 'R24-04-0001', '2024-04',
 1, 'direct', NULL, 'フリート10台',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7002, 1001, 2001, NULL,
 '2024-04-18', 'renewal', 'non_life',
 '東京海上日動', 'TC001-2001-AUTO', '2024-05-01', NULL,
 '自動車保険', '一般自動車',
 170000, NULL, 'R24-04-0002', '2024-04',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7003, 1006, 2022, NULL,
 '2024-04-15', 'renewal', 'non_life',
 '三井住友海上', 'TC006-2022-AUTO', '2024-04-25', NULL,
 '自動車保険', '一般自動車',
 68000, NULL, 'R24-04-0003', '2024-04',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7004, 1007, 2025, NULL,
 '2024-04-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC007-2025-AUTO', '2024-05-07', NULL,
 '自動車保険', '一般自動車',
 60000, NULL, 'R24-04-0004', '2024-04',
 2, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-05（1,098千円 / staff1: 1,030千円 / staff2: 68千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7005, 1001, 2006, NULL,
 '2024-04-20', 'renewal', 'life',
 '日本生命', 'TC001-2006-LIFE', '2024-04-30', NULL,
 '生命保険', '定期保険',
 280000, 12, 'R24-05-0001', '2024-05',
 1, 'direct', NULL, '月払い',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7006, 1020, 2044, NULL,
 '2024-04-20', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2044-AUTO-A', '2024-04-30', NULL,
 '自動車保険', 'フリート',
 260000, NULL, 'R24-05-0002', '2024-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7007, 1020, 2045, NULL,
 '2024-04-22', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2045-AUTO-B', '2024-05-01', NULL,
 '自動車保険', 'フリート',
 180000, NULL, 'R24-05-0003', '2024-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7008, 1004, 2016, NULL,
 '2024-04-20', 'renewal', 'non_life',
 '損保ジャパン', 'TC004-2016-AUTO', '2024-04-30', NULL,
 '自動車保険', 'フリート',
 310000, NULL, 'R24-05-0004', '2024-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7009, 1010, 2032, NULL,
 '2024-04-22', 'renewal', 'non_life',
 '損保ジャパン', 'TC010-2032-AUTO', '2024-05-01', NULL,
 '自動車保険', '一般自動車',
 68000, NULL, 'R24-05-0005', '2024-05',
 2, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-06（442千円 / staff1: 390千円 / staff2: 52千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7010, 1001, 2002, NULL,
 '2024-06-05', 'renewal', 'non_life',
 '損保ジャパン', 'TC001-2002-FIRE', '2024-06-15', NULL,
 '火災保険', '普通火災',
 230000, NULL, 'R24-06-0001', '2024-06',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7011, 1002, 2008, NULL,
 '2024-05-20', 'renewal', 'non_life',
 '損保ジャパン', 'TC002-2008-FIRE', '2024-06-01', NULL,
 '火災保険', '店舗総合',
 160000, NULL, 'R24-06-0002', '2024-06',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7012, 1008, 2028, NULL,
 '2024-05-28', 'renewal', 'non_life',
 '三井住友海上', 'TC008-2028-AUTO', '2024-06-01', NULL,
 '自動車保険', '一般自動車',
 52000, NULL, 'R24-06-0003', '2024-06',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2024-07（698千円 / staff1: 428千円 / staff2: 270千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7013, 1002, 2010, NULL,
 '2024-06-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC002-2010-LIAB', '2024-07-01', NULL,
 '賠償責任保険', '企業賠償',
 88000, NULL, 'R24-07-0001', '2024-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7014, 1020, 2046, NULL,
 '2024-05-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC020-2046-AUTO-C', '2024-06-01', NULL,
 '自動車保険', 'フリート',
 145000, NULL, 'R24-07-0002', '2024-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7015, 1020, 2047, NULL,
 '2024-06-28', 'renewal', 'non_life',
 '東京海上日動', 'TC020-2047-FIRE', '2024-07-01', NULL,
 '火災保険', '店舗総合',
 195000, NULL, 'R24-07-0003', '2024-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7016, 1005, 2020, NULL,
 '2024-07-10', 'renewal', 'non_life',
 '東京海上日動', 'TC005-2020-MED', '2024-07-15', NULL,
 '賠償責任保険', '医師賠償責任',
 270000, NULL, 'R24-07-0004', '2024-07',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2024-08（182千円 / staff1: 28千円 / staff2: 154千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7017, 1003, 2013, NULL,
 '2024-07-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC003-2013-FIRE', '2024-08-01', NULL,
 '火災保険', '普通火災',
 130000, NULL, 'R24-08-0001', '2024-08',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7018, 1006, 2023, NULL,
 '2024-07-25', 'renewal', 'non_life',
 '東京海上日動', 'TC006-2023-CASU', '2024-08-01', NULL,
 '傷害保険', '普通傷害',
 24000, NULL, 'R24-08-0002', '2024-08',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7019, 1010, 2033, NULL,
 '2024-08-10', 'renewal', 'non_life',
 '三井住友海上', 'TC010-2033-FIRE', '2024-08-15', NULL,
 '火災保険', '住宅総合',
 28000, NULL, 'R24-08-0003', '2024-08',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-09（568千円 / staff1: 108千円 / staff2: 460千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7020, 1005, 2020, NULL,
 '2024-08-28', 'renewal', 'non_life',
 '東京海上日動', 'TC005-2020-MED', '2024-09-01', NULL,
 '賠償責任保険', '医師賠償責任',
 265000, NULL, 'R24-09-0001', '2024-09',
 2, 'agency_referral', '医師会紹介代理店', '医師会経由',
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7021, 1008, 2029, NULL,
 '2024-08-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC008-2029-CASU', '2024-09-01', NULL,
 '傷害保険', '普通傷害',
 20000, NULL, 'R24-09-0002', '2024-09',
 1, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7022, 1003, 2015, NULL,
 '2024-08-25', 'renewal', 'non_life',
 '東京海上日動', 'TC003-2015-STORE', '2024-09-01', NULL,
 '火災保険', '店舗総合',
 195000, NULL, 'R24-09-0003', '2024-09',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7023, 1020, 2049, NULL,
 '2024-09-10', 'renewal', 'non_life',
 '東京海上日動', 'TC020-2049-CASU', '2024-09-15', NULL,
 '傷害保険', '団体傷害',
 88000, NULL, 'R24-09-0004', '2024-09',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-10（794千円 / staff1: 760千円 / staff2: 34千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7024, 1001, 2004, NULL,
 '2024-09-25', 'renewal', 'non_life',
 '東京海上日動', 'TC001-2004-CMP', '2024-10-01', NULL,
 '企業総合保険', '企業総合',
 400000, NULL, 'R24-10-0001', '2024-10',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7025, 1007, 2026, NULL,
 '2024-09-25', 'renewal', 'non_life',
 '東京海上日動', 'TC007-2026-FIRE', '2024-10-01', NULL,
 '火災保険', '住宅総合',
 34000, NULL, 'R24-10-0002', '2024-10',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7026, 1020, 2048, NULL,
 '2024-09-28', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2048-CMP', '2024-10-01', NULL,
 '企業総合保険', '企業総合',
 360000, NULL, 'R24-10-0003', '2024-10',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-11（262千円 / staff1: 220千円 / staff2: 42千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7027, 1004, 2017, NULL,
 '2024-10-25', 'renewal', 'non_life',
 '三井住友海上', 'TC004-2017-CONST', '2024-11-01', NULL,
 '新種保険', '建設工事',
 170000, NULL, 'R24-11-0001', '2024-11',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7028, 1006, 2024, NULL,
 '2024-10-28', 'renewal', 'life',
 '日本生命', 'TC006-2024-LIFE', '2024-11-01', NULL,
 '生命保険', '終身保険',
 42000, 12, 'R24-11-0002', '2024-11',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7029, 1020, 2050, NULL,
 '2024-10-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC020-2050-MISC', '2024-11-01', NULL,
 '新種保険', '動産総合',
 50000, NULL, 'R24-11-0003', '2024-11',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2024-12（266千円 / staff1: 82千円 / staff2: 184千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7030, 1001, 2003, NULL,
 '2024-12-20', 'renewal', 'non_life',
 '三井住友海上', 'TC001-2003-CASU', '2024-12-31', NULL,
 '傷害保険', '普通傷害',
 82000, NULL, 'R24-12-0001', '2024-12',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7031, 1009, 2031, NULL,
 '2024-11-28', 'renewal', 'life',
 '住友生命', 'TC009-2031-LIFE', '2024-12-01', NULL,
 '生命保険', '定期保険',
 34000, 12, 'R24-12-0002', '2024-12',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7032, 1005, 2021, NULL,
 '2024-11-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC005-2021-FIRE', '2024-12-01', NULL,
 '火災保険', '普通火災',
 150000, NULL, 'R24-12-0003', '2024-12',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2025-01（18千円 / staff2: 18千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7033, 1007, 2027, NULL,
 '2024-12-28', 'renewal', 'life',
 '第一生命', 'TC007-2027-CANC', '2025-01-01', NULL,
 '生命保険', 'がん保険',
 18000, 12, 'R25-01-0001', '2025-01',
 2, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-02（40千円 / staff1: -22千円 / staff2: 62千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7034, 1012, NULL, NULL,
 '2025-02-10', 'new', 'non_life',
 '三井住友海上', 'NEW-1012-AUTO-FY24', '2025-03-01', NULL,
 '自動車保険', '一般自動車',
 62000, NULL, 'R25-02-0001', '2025-02',
 2, 'customer_referral', '紹介', NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7035, 1002, 2007, NULL,
 '2025-02-01', 'change', 'non_life',
 '三井住友海上', 'TC002-2007-AUTO', '2025-02-01', NULL,
 '自動車保険', 'フリート',
 -22000, NULL, NULL, '2025-02',
 1, 'direct', NULL, 'フリート台数変更による減額',
 0, 1, 1);

-- 2025-03（191千円 / staff1: 157千円 / staff2: 34千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7036, 1002, 2009, NULL,
 '2025-03-20', 'renewal', 'non_life',
 '東京海上日動', 'TC002-2009-CASU', '2025-03-31', NULL,
 '傷害保険', '団体傷害',
 115000, NULL, 'R25-03-0001', '2025-03',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7037, 1013, NULL, NULL,
 '2025-03-15', 'new', 'life',
 '住友生命', 'NEW-1013-LIFE-FY24', '2025-04-01', '2025-03-10',
 '生命保険', '医療保険',
 34000, 12, 'R25-03-0002', '2025-03',
 2, 'agency_referral', '銀行窓販', NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7038, 1001, 2004, NULL,
 '2025-03-10', 'addition', 'non_life',
 '東京海上日動', 'TC001-2004-CMP', '2025-03-10', NULL,
 '企業総合保険', '企業総合',
 42000, NULL, 'R25-03-0003', '2025-03',
 1, 'direct', NULL, '補償額増額分',
 0, 1, 1);

-- ========== FY2025 成績（7039-7080）==========
-- FY2024比: 全体 +10.7%（月別に増減あり、4月が特に大きく伸長）

-- 2025-04（1,219千円 / staff1: 905千円 / staff2: 314千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7039, 1001, 2005, 3009,
 '2025-04-10', 'renewal', 'non_life',
 '損保ジャパン', 'TC001-2005-LIAB', '2025-04-20', NULL,
 '賠償責任保険', '施設賠償',
 75000, NULL, 'R25-04-0001', '2025-04',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7040, 1001, 2001, 3001,
 '2025-04-18', 'renewal', 'non_life',
 '東京海上日動', 'TC001-2001-AUTO', '2025-05-01', NULL,
 '自動車保険', '一般自動車',
 180000, NULL, 'R25-04-0002', '2025-04',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7041, 1002, 2007, 3013,
 '2025-04-05', 'renewal', 'non_life',
 '三井住友海上', 'TC002-2007-AUTO', '2025-04-15', NULL,
 '自動車保険', 'フリート',
 650000, NULL, 'R25-04-0003', '2025-04',
 1, 'direct', NULL, 'フリート10台',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7042, 1006, 2022, 3043,
 '2025-04-15', 'renewal', 'non_life',
 '三井住友海上', 'TC006-2022-AUTO', '2025-04-25', NULL,
 '自動車保険', '一般自動車',
 72000, NULL, 'R25-04-0004', '2025-04',
 2, 'customer_referral', '山田太郎（顧客自身）', NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7043, 1007, 2025, 3049,
 '2025-04-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC007-2025-AUTO', '2025-05-07', NULL,
 '自動車保険', '一般自動車',
 65000, NULL, 'R25-04-0005', '2025-04',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7044, 1003, 2012, 3023,
 '2025-04-15', 'renewal', 'non_life',
 '東京海上日動', 'TC003-2012-AUTO', '2025-04-25', NULL,
 '自動車保険', '一般自動車',
 95000, NULL, 'R25-04-0006', '2025-04',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7045, 1009, 2030, 3059,
 '2025-04-05', 'renewal', 'non_life',
 '東京海上日動', 'TC009-2030-AUTO', '2025-04-15', NULL,
 '自動車保険', '一般自動車',
 82000, NULL, 'R25-04-0007', '2025-04',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2025-05（1,307千円 / staff1: 1,307千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7046, 1001, 2006, 3011,
 '2025-04-20', 'renewal', 'life',
 '日本生命', 'TC001-2006-LIFE', '2025-04-30', NULL,
 '生命保険', '定期保険',
 360000, 12, 'R25-05-0001', '2025-05',
 1, 'direct', NULL, '月払い',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7047, 1004, 2018, 3035,
 '2025-04-20', 'renewal', 'non_life',
 '東京海上日動', 'TC004-2018-CASU', '2025-05-01', NULL,
 '傷害保険', '団体傷害',
 78000, NULL, 'R25-05-0002', '2025-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7048, 1020, 2044, 3087,
 '2025-04-20', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2044-AUTO-A', '2025-04-30', NULL,
 '自動車保険', 'フリート',
 280000, NULL, 'R25-05-0003', '2025-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7049, 1020, 2045, 3089,
 '2025-04-22', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2045-AUTO-B', '2025-05-01', NULL,
 '自動車保険', 'フリート',
 195000, NULL, 'R25-05-0004', '2025-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7050, 1010, 2032, 3063,
 '2025-04-22', 'renewal', 'non_life',
 '損保ジャパン', 'TC010-2032-AUTO', '2025-05-01', NULL,
 '自動車保険', '一般自動車',
 74000, NULL, 'R25-05-0005', '2025-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7051, 1004, 2016, 3031,
 '2025-04-20', 'renewal', 'non_life',
 '損保ジャパン', 'TC004-2016-AUTO', '2025-04-30', NULL,
 '自動車保険', 'フリート',
 320000, NULL, 'R25-05-0006', '2025-05',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-06（488千円 / staff1: 430千円 / staff2: 58千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7052, 1001, 2002, 3003,
 '2025-06-05', 'renewal', 'non_life',
 '損保ジャパン', 'TC001-2002-FIRE', '2025-06-15', NULL,
 '火災保険', '普通火災',
 250000, NULL, 'R25-06-0001', '2025-06',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7053, 1002, 2008, 3015,
 '2025-05-20', 'renewal', 'non_life',
 '損保ジャパン', 'TC002-2008-FIRE', '2025-06-01', NULL,
 '火災保険', '店舗総合',
 180000, NULL, 'R25-06-0002', '2025-06',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7054, 1008, 2028, 3055,
 '2025-05-28', 'renewal', 'non_life',
 '三井住友海上', 'TC008-2028-AUTO', '2025-06-01', NULL,
 '自動車保険', '一般自動車',
 58000, NULL, 'R25-06-0003', '2025-06',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2025-07（580千円 / staff1: 580千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7055, 1002, 2010, 3019,
 '2025-06-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC002-2010-LIAB', '2025-07-01', NULL,
 '賠償責任保険', '企業賠償',
 95000, NULL, 'R25-07-0001', '2025-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7056, 1020, 2046, 3091,
 '2025-05-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC020-2046-AUTO-C', '2025-06-01', NULL,
 '自動車保険', 'フリート',
 155000, NULL, 'R25-07-0002', '2025-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7057, 1004, 2019, 3037,
 '2025-07-10', 'renewal', 'non_life',
 '損保ジャパン', 'TC004-2019-LIAB', '2025-07-15', NULL,
 '賠償責任保険', '請負業者賠償',
 110000, NULL, 'R25-07-0003', '2025-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7058, 1020, 2047, 3093,
 '2025-06-28', 'renewal', 'non_life',
 '東京海上日動', 'TC020-2047-FIRE', '2025-07-01', NULL,
 '火災保険', '店舗総合',
 220000, NULL, 'R25-07-0004', '2025-07',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-08（199千円 / staff1: 31千円 / staff2: 168千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7059, 1003, 2013, 3025,
 '2025-07-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC003-2013-FIRE', '2025-08-01', NULL,
 '火災保険', '普通火災',
 140000, NULL, 'R25-08-0001', '2025-08',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7060, 1006, 2023, 3045,
 '2025-07-25', 'renewal', 'non_life',
 '東京海上日動', 'TC006-2023-CASU', '2025-08-01', NULL,
 '傷害保険', '普通傷害',
 28000, NULL, 'R25-08-0002', '2025-08',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7061, 1010, 2033, 3065,
 '2025-08-10', 'renewal', 'non_life',
 '三井住友海上', 'TC010-2033-FIRE', '2025-08-15', NULL,
 '火災保険', '住宅総合',
 31000, NULL, 'R25-08-0003', '2025-08',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-09（613千円 / staff1: 118千円 / staff2: 495千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7062, 1005, 2020, 3039,
 '2025-08-28', 'renewal', 'non_life',
 '東京海上日動', 'TC005-2020-MED', '2025-09-01', NULL,
 '賠償責任保険', '医師賠償責任',
 285000, NULL, 'R25-09-0001', '2025-09',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7063, 1008, 2029, 3057,
 '2025-08-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC008-2029-CASU', '2025-09-01', NULL,
 '傷害保険', '普通傷害',
 22000, NULL, 'R25-09-0002', '2025-09',
 1, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7064, 1003, 2015, 3029,
 '2025-08-25', 'renewal', 'non_life',
 '東京海上日動', 'TC003-2015-STORE', '2025-09-01', NULL,
 '火災保険', '店舗総合',
 210000, NULL, 'R25-09-0003', '2025-09',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7065, 1020, 2049, 3097,
 '2025-09-10', 'renewal', 'non_life',
 '東京海上日動', 'TC020-2049-CASU', '2025-09-15', NULL,
 '傷害保険', '団体傷害',
 96000, NULL, 'R25-09-0004', '2025-09',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-10（838千円 / staff1: 800千円 / staff2: 38千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7066, 1001, 2004, 3007,
 '2025-09-25', 'renewal', 'non_life',
 '東京海上日動', 'TC001-2004-CMP', '2025-10-01', NULL,
 '企業総合保険', '企業総合',
 420000, NULL, 'R25-10-0001', '2025-10',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7067, 1007, 2026, 3051,
 '2025-09-25', 'renewal', 'non_life',
 '東京海上日動', 'TC007-2026-FIRE', '2025-10-01', NULL,
 '火災保険', '住宅総合',
 38000, NULL, 'R25-10-0002', '2025-10',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7068, 1020, 2048, 3095,
 '2025-09-28', 'renewal', 'non_life',
 '三井住友海上', 'TC020-2048-CMP', '2025-10-01', NULL,
 '企業総合保険', '企業総合',
 380000, NULL, 'R25-10-0003', '2025-10',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-11（288千円 / staff1: 240千円 / staff2: 48千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7069, 1004, 2017, 3033,
 '2025-10-25', 'renewal', 'non_life',
 '三井住友海上', 'TC004-2017-CONST', '2025-11-01', NULL,
 '新種保険', '建設工事',
 185000, NULL, 'R25-11-0001', '2025-11',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7070, 1006, 2024, 3047,
 '2025-10-28', 'renewal', 'life',
 '日本生命', 'TC006-2024-LIFE', '2025-11-01', NULL,
 '生命保険', '終身保険',
 48000, 12, 'R25-11-0002', '2025-11',
 2, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7071, 1020, 2050, 3099,
 '2025-10-28', 'renewal', 'non_life',
 '損保ジャパン', 'TC020-2050-MISC', '2025-11-01', NULL,
 '新種保険', '動産総合',
 55000, NULL, 'R25-11-0003', '2025-11',
 1, 'direct', NULL, NULL,
 0, 1, 1);

-- 2025-12（291千円 / staff1: 90千円 / staff2: 201千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7072, 1001, 2003, 3005,
 '2025-12-20', 'renewal', 'non_life',
 '三井住友海上', 'TC001-2003-CASU', '2025-12-31', NULL,
 '傷害保険', '普通傷害',
 90000, NULL, 'R25-12-0001', '2025-12',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7073, 1009, 2031, 3061,
 '2025-11-28', 'renewal', 'life',
 '住友生命', 'TC009-2031-LIFE', '2025-12-01', NULL,
 '生命保険', '定期保険',
 36000, 12, 'R25-12-0002', '2025-12',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7074, 1005, 2021, 3041,
 '2025-11-25', 'renewal', 'non_life',
 '損保ジャパン', 'TC005-2021-FIRE', '2025-12-01', NULL,
 '火災保険', '普通火災',
 165000, NULL, 'R25-12-0003', '2025-12',
 2, 'direct', NULL, NULL,
 0, 2, 2);

-- 2026-01（24千円 / staff2: 24千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7075, 1007, 2027, 3053,
 '2025-12-28', 'renewal', 'life',
 '第一生命', 'TC007-2027-CANC', '2026-01-01', NULL,
 '生命保険', 'がん保険',
 24000, 12, 'R26-01-0001', '2026-01',
 2, 'direct', NULL, NULL,
 0, 1, 1);

-- 2026-02（43千円 / staff1: -25千円 / staff2: 68千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7076, 1012, NULL, NULL,
 '2026-02-15', 'new', 'non_life',
 '三井住友海上', 'NEW-1012-AUTO-001', '2026-03-01', NULL,
 '自動車保険', '一般自動車',
 68000, NULL, 'R26-02-0001', '2026-02',
 2, 'customer_referral', '山田太郎（顧客1006）', '顧客1006からの紹介',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7077, 1002, 2007, NULL,
 '2026-02-01', 'change', 'non_life',
 '三井住友海上', 'TC002-2007-AUTO', '2026-02-01', NULL,
 '自動車保険', 'フリート',
 -25000, NULL, NULL, '2026-02',
 1, 'direct', NULL, 'フリート台数変更（10台→8台）による保険料減額',
 0, 1, 1);

-- 2026-03（206千円 / staff1: 170千円 / staff2: 36千円）
INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7078, 1002, 2009, 3018,
 '2026-03-28', 'renewal', 'non_life',
 '東京海上日動', 'TC002-2009-CASU', '2026-03-31', NULL,
 '傷害保険', '団体傷害',
 125000, NULL, 'R26-03-0001', '2026-03',
 1, 'direct', NULL, NULL,
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7079, 1013, NULL, NULL,
 '2026-03-20', 'new', 'life',
 '住友生命', 'NEW-1013-LIFE-001', '2026-04-01', '2026-03-10',
 '生命保険', '医療保険',
 36000, 12, 'R26-03-0002', '2026-03',
 2, 'agency_referral', '横浜中央銀行（提携）', '銀行窓販経由',
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7080, 1001, 2004, NULL,
 '2026-03-15', 'addition', 'non_life',
 '東京海上日動', 'TC001-2004-CMP', '2026-03-15', NULL,
 '企業総合保険', '企業総合',
 45000, NULL, 'R26-03-0003', '2026-03',
 1, 'direct', NULL, '補償額増額分の追加保険料',
 0, 1, 1);

-- ========== FY2026 成績（7081-7084）==========
-- 2026-04のみ（205千円 / staff1: 117千円 / staff2: 88千円）

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7081, 1003, 2014, 3028,
 '2026-04-06', 'renewal', 'non_life',
 '三井住友海上', 'TC003-2014-CASU', '2026-04-10', NULL,
 '傷害保険', '普通傷害',
 58000, NULL, 'R26-04-0001', '2026-04',
 2, 'direct', NULL, NULL,
 0, 2, 2);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7082, 1020, NULL, NULL,
 '2026-04-01', 'new', 'non_life',
 '三井住友海上', 'NEW-1020-AUTO-001', '2026-04-05', NULL,
 '自動車保険', '一般自動車',
 72000, NULL, 'R26-04-0002', '2026-04',
 1, 'motor_dealer', 'テスト自動車販売（顧客1020）', '販売台数増加分の新規',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7083, 1001, NULL, NULL,
 '2026-04-03', 'new', 'life',
 '第一生命', 'L2026-04-0001', '2026-05-01', '2026-04-03',
 '生命保険', '終身保険',
 45000, 12, NULL, '2026-04',
 1, 'direct', NULL, 'FY2026生保テスト(staff1)',
 0, 1, 1);

INSERT INTO t_sales_performance (
  id, customer_id, contract_id, renewal_case_id,
  performance_date, performance_type, source_type,
  insurer_name, policy_no, policy_start_date, application_date,
  insurance_category, product_type,
  premium_amount, installment_count, receipt_no, settlement_month,
  staff_id, sales_channel, referral_source, remark,
  is_deleted, created_by, updated_by
) VALUES
(7084, 1002, NULL, NULL,
 '2026-04-05', 'new', 'life',
 '日本生命', 'L2026-04-0002', '2026-05-01', '2026-04-05',
 '生命保険', '定期保険',
 30000, 12, NULL, '2026-04',
 2, 'direct', NULL, 'FY2026生保テスト(staff2)',
 0, 1, 1);
