-- =====================================================================
-- ダミーデータ: t_contract（契約）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : 満期日が分散した契約サンプル（満期直前／中期／遠い／過去／失効／解約）
-- 件数  : 20件
-- ID範囲: 2001 - 2020
-- 依存  : 02_m_customer.sql, 01_m_staff.sql
-- 関連DDL: config/ddl/tenant/t_contract.sql
-- 基準日 : 2026-04-21
-- =====================================================================
-- 満期日分布:
--   7日以内       : 2001(4/25), 2008(4/28)
--   14日以内      : 2002(5/01), 2006(5/05)
--   30日以内      : 2004(5/10), 2009(5/15)
--   60日以内      : 2003(6/10), 2007(6/20)
--   90日以内      : 2010(7/15)
--   遠い(90日超)  : 2011(10/01), 2012(2027-01-01), 2017(8/01), 2018(2027-02-01)
--                   2019(9/01), 2020(11/01)
--   過去・完了済  : 2005(1/10/26), 2013(5/01/25), 2014(3/01/26)
--   expired       : 2013, 2014
--   cancelled     : 2015, 2016
-- =====================================================================

SET NAMES utf8mb4;

-- 2001: 1001(法人)・自動車フリート・active・7日以内満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2001, 1001, 1001, 'POL-2025-000001', '自動車', 'タフ・ビズ',
  '2025-04-25', '2026-04-25', 1800000, 'annual', 'active',
  1, 3, 'フリート20台。重点満期。', 0, 1, 1
);

-- 2002: 1001(法人)・火災・active・14日以内満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2002, 1001, 1001, 'POL-2025-000002', '火災', 'すまいの保険',
  '2025-05-01', '2026-05-01', 320000, 'annual', 'active',
  1, 3, '本社ビル・テナント', 0, 1, 1
);

-- 2003: 1001(法人)・賠責・active・60日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2003, 1001, 1001, 'POL-2025-000003', '賠責', '業務災害総合',
  '2025-06-10', '2026-06-10', 150000, 'annual', 'active',
  1, 3, NULL, 0, 1, 1
);

-- 2004: 1002(法人運輸)・自動車フリート・active・30日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2004, 1002, 1002, 'POL-2025-000004', '自動車', '事業用Ｋ・Ａ・Ｐ',
  '2025-05-10', '2026-05-10', 2400000, 'annual', 'active',
  2, 3, '運輸フリート30台規模。', 0, 1, 1
);

-- 2005: 1002(法人運輸)・火災・active・過去満期(処理済)
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2005, 1002, 1002, 'POL-2024-000005', '火災', '倉庫物件火災',
  '2025-01-10', '2026-01-10', 95000, 'annual', 'active',
  2, 3, '倉庫。2026/1/10 更改済み。', 0, 1, 1
);

-- 2006: 1004(個人山田)・自動車・active・14日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2006, 1004, 1004, 'POL-2025-000006', '自動車', 'おとなの自動車',
  '2025-05-05', '2026-05-05', 82000, 'annual', 'active',
  1, 3, NULL, 0, 1, 1
);

-- 2007: 1004(個人山田)・傷害・active・60日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2007, 1004, 1004, 'POL-2025-000007', '傷害', '普通傷害',
  '2025-06-20', '2026-06-20', 22000, 'annual', 'active',
  1, 3, NULL, 0, 1, 1
);

-- 2008: 1005(個人佐藤)・自動車・active・7日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2008, 1005, 1005, 'POL-2025-000008', '自動車', 'GKクルマの保険・家庭用',
  '2025-04-28', '2026-04-28', 68000, 'annual', 'active',
  2, 3, NULL, 0, 1, 1
);

-- 2009: 1005(個人佐藤)・火災・active・30日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2009, 1005, 1005, 'POL-2025-000009', '火災', 'ホームアシスト',
  '2025-05-15', '2026-05-15', 38000, 'annual', 'active',
  2, 3, NULL, 0, 1, 1
);

-- 2010: 1006(個人高橋)・傷害・active・90日以内
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2010, 1006, 1006, 'POL-2025-000010', '傷害', '医療保険プレミアム',
  '2025-07-15', '2026-07-15', 48000, 'monthly', 'active',
  1, 3, NULL, 0, 1, 1
);

-- 2011: 1006(個人高橋)・生保・active・遠い満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2011, 1006, 1006, 'POL-2025-000011', '生保', '終身医療',
  '2025-10-01', '2026-10-01', 96000, 'monthly', 'active',
  1, 3, '生保契約（長期）。', 0, 1, 1
);

-- 2012: 1007(個人渡辺)・自動車・active・最近更改したて
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2012, 1007, 1007, 'POL-2026-000012', '自動車', 'タフクル',
  '2026-01-01', '2027-01-01', 72000, 'annual', 'active',
  2, 3, '2026/01 更改済み。', 0, 1, 1
);

-- 2013: 1010(個人inactive)・自動車・expired（連絡不通で失効）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2013, 1010, 1010, 'POL-2024-000013', '自動車', 'おとなの自動車',
  '2024-05-01', '2025-05-01', 70000, 'annual', 'expired',
  2, 3, '連絡不通により失効。', 0, 1, 1
);

-- 2014: 1011(法人inactive)・火災・expired
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2014, 1011, 1011, 'POL-2024-000014', '火災', 'テナント火災',
  '2025-03-01', '2026-03-01', 60000, 'annual', 'expired',
  1, 3, '事業縮小により失効。', 0, 1, 1
);

-- 2015: 1012(法人closed)・自動車・cancelled（廃業による中途解約）
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2015, 1012, 1012, 'POL-2024-000015', '自動車', 'タフ・ビズ',
  '2024-04-01', '2025-04-01', 180000, 'annual', 'cancelled',
  1, 3, '廃業により中途解約。', 0, 1, 1
);

-- 2016: 1012(法人closed)・賠責・cancelled
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2016, 1012, 1012, 'POL-2024-000016', '賠責', '業務災害総合',
  '2024-06-01', '2025-06-01', 48000, 'annual', 'cancelled',
  1, 3, '廃業による解約。', 0, 1, 1
);

-- 2017: 1001(法人)・労災・active・8月満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2017, 1001, 1001, 'POL-2025-000017', '労災', '法定外労災上乗せ',
  '2025-08-01', '2026-08-01', 210000, 'annual', 'active',
  1, 3, NULL, 0, 1, 1
);

-- 2018: 1002(法人運輸)・火災・active・遠い満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2018, 1002, 1002, 'POL-2026-000018', '火災', '倉庫物件火災',
  '2026-02-01', '2027-02-01', 135000, 'annual', 'active',
  2, 3, '第2倉庫。2026/02 更改済み。', 0, 1, 1
);

-- 2019: 1005(個人佐藤)・生保・active・遠い満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2019, 1005, 1005, 'POL-2025-000019', '生保', '定期死亡',
  '2025-09-01', '2026-09-01', 36000, 'monthly', 'active',
  2, 3, NULL, 0, 1, 1
);

-- 2020: 1004(個人山田)・火災・active・11月満期
INSERT INTO t_contract (
  id, customer_id, insured_customer_id, policy_no, insurance_category, product_type,
  policy_start_date, policy_end_date, premium_amount, payment_cycle, status,
  sales_staff_id, office_staff_id, remark, is_deleted, created_by, updated_by
) VALUES (
  2020, 1004, 1004, 'POL-2025-000020', '火災', 'すまいの保険',
  '2025-11-01', '2026-11-01', 44000, 'annual', 'active',
  1, 3, '持家物件。', 0, 1, 1
);
