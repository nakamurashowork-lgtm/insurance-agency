-- =====================================================================
-- ダミーデータ: t_renewal_case（満期案件）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : マスタ m_case_status(renewal) の全ステータスを網羅した満期案件
-- 件数  : 18件
-- ID範囲: 3001 - 3018
-- 依存  : 03_t_contract.sql
-- 関連DDL: config/ddl/tenant/t_renewal_case.sql
-- 基準日 : 2026-04-21
-- =====================================================================
-- カバレッジ（m_case_status where case_type='renewal'）:
--   未対応          : 3001, 3002, 3003
--   SJ依頼中        : 3004, 3005
--   書類作成済      : 3006
--   返送待ち        : 3007
--   見積送付済      : 3008, 3009
--   入金待ち        : 3010
--   完了(renewed)   : 3011, 3012, 3013
--   失注            : 3014, 3015
--   取り下げ        : 3016
--   解約            : 3017, 3018
-- =====================================================================

SET NAMES utf8mb4;

-- 3001: 未対応・contract 2001（法人フリート、満期4/25 = 4日後）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3001, 2001, '2026-04-25', '2026-04-10', '未対応',
  NULL, '2026-04-22', NULL, 1800000,
  1, 3, '重要法人フリート。至急対応。', '対面', '対面',
  0, 1, 1
);

-- 3002: 未対応・contract 2008（個人佐藤 自動車、満期4/28 = 7日後）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3002, 2008, '2026-04-28', '2026-04-14', '未対応',
  NULL, '2026-04-23', NULL, 68000,
  2, 3, NULL, '対面', '対面',
  0, 1, 1
);

-- 3003: 未対応・contract 2007（個人山田 傷害、満期6/20 = 60日先）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3003, 2007, '2026-06-20', '2026-06-05', '未対応',
  NULL, '2026-05-10', NULL, 22000,
  1, 3, NULL, '郵送', '署名・捺印',
  0, 1, 1
);

-- 3004: SJ依頼中・contract 2002（法人火災、満期5/1）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3004, 2002, '2026-05-01', '2026-04-17', 'SJ依頼中',
  '2026-04-10 10:30:00', '2026-04-24', NULL, 320000,
  1, 3, 'SJ に見積依頼済み。回答待ち。', '対面', '対面',
  0, 1, 1
);

-- 3005: SJ依頼中・contract 2006（個人山田 自動車、満期5/5）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3005, 2006, '2026-05-05', '2026-04-21', 'SJ依頼中',
  '2026-04-13 14:00:00', '2026-04-24', NULL, 82000,
  1, 3, NULL, '対面', '対面ナビ',
  0, 1, 1
);

-- 3006: 書類作成済・contract 2004（法人運輸フリート、満期5/10）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3006, 2004, '2026-05-10', '2026-04-25', '書類作成済',
  '2026-04-15 11:00:00', '2026-04-26', NULL, 2400000,
  2, 3, '運輸フリート。書類準備完了、訪問予約待ち。', '対面', '対面',
  0, 1, 1
);

-- 3007: 返送待ち・contract 2009（個人佐藤 火災、満期5/15）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3007, 2009, '2026-05-15', '2026-05-01', '返送待ち',
  '2026-04-08 15:30:00', '2026-04-24', NULL, 38000,
  2, 3, '郵送。顧客返送待ち。', '郵送', '署名・捺印',
  0, 1, 1
);

-- 3008: 見積送付済・contract 2003（法人賠責、満期6/10）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3008, 2003, '2026-06-10', '2026-05-25', '見積送付済',
  '2026-04-14 10:00:00', '2026-05-02', NULL, 150000,
  1, 3, '見積3パターン送付。顧客検討中。', '対面', '対面',
  0, 1, 1
);

-- 3009: 見積送付済・contract 2010（個人高橋 医療、満期7/15）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3009, 2010, '2026-07-15', '2026-06-30', '見積送付済',
  '2026-04-10 09:30:00', '2026-05-15', NULL, 48000,
  1, 3, '長期割引プラン比較提示済み。', '対面', '対面',
  0, 1, 1
);

-- 3010: 入金待ち・contract 2017（法人労災、満期8/1）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  is_deleted, created_by, updated_by
) VALUES (
  3010, 2017, '2026-08-01', '2026-07-15', '入金待ち',
  '2026-04-18 10:00:00', '2026-04-25', 'pending', 210000,
  1, 3, '契約書受領済み、入金待ち。', '対面', '対面',
  0, 1, 1
);

-- 3011: 完了・renewed・contract 2005（法人運輸倉庫火災、前回満期2026/1/10）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, actual_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  completed_date, is_deleted, created_by, updated_by
) VALUES (
  3011, 2005, '2026-01-10', '2025-12-20', '完了',
  '2025-12-15 11:00:00', NULL, 'renewed', 95000,
  2, 3, '前年度から継続更改。', '対面', '対面',
  '2025-12-28', 0, 1, 1
);

-- 3012: 完了・renewed・contract 2012（個人渡辺 自動車、前回満期2026/1/1）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, actual_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  completed_date, is_deleted, created_by, updated_by
) VALUES (
  3012, 2012, '2026-01-01', '2025-12-15', '完了',
  '2025-12-10 14:00:00', NULL, 'renewed', 72000,
  2, 3, NULL, '対面', '対面',
  '2025-12-22', 0, 1, 1
);

-- 3013: 完了・renewed・contract 2018（法人運輸 第2倉庫火災、前回満期2026/2/1）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, actual_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  completed_date, is_deleted, created_by, updated_by
) VALUES (
  3013, 2018, '2026-02-01', '2026-01-15', '完了',
  '2026-01-20 10:30:00', NULL, 'renewed', 135000,
  2, 3, NULL, '対面', '対面',
  '2026-01-28', 0, 1, 1
);

-- 3014: 失注・contract 2013（個人加藤 inactive、連絡不通）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, lost_reason,
  expected_premium_amount, assigned_staff_id, office_staff_id, remark,
  renewal_method, procedure_method, completed_date, is_deleted, created_by, updated_by
) VALUES (
  3014, 2013, '2025-05-01', '2025-04-15', '失注',
  '2025-04-05 10:00:00', NULL, 'lost', '連絡不通により更改不可。',
  70000, 2, 3, NULL,
  NULL, NULL, '2025-05-10', 0, 1, 1
);

-- 3015: 失注・contract 2014（法人旧コーポ inactive、事業縮小）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, lost_reason,
  expected_premium_amount, assigned_staff_id, office_staff_id, remark,
  renewal_method, procedure_method, completed_date, is_deleted, created_by, updated_by
) VALUES (
  3015, 2014, '2026-03-01', '2026-02-15', '失注',
  '2026-02-20 15:00:00', NULL, 'lost', '事業縮小により更改見送り。',
  60000, 1, 3, NULL,
  NULL, NULL, '2026-03-10', 0, 1, 1
);

-- 3016: 取り下げ・contract 2020（個人山田 火災、重複申込で取り下げ）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, expected_premium_amount,
  assigned_staff_id, office_staff_id, remark, renewal_method, procedure_method,
  completed_date, is_deleted, created_by, updated_by
) VALUES (
  3016, 2020, '2026-11-01', '2026-10-15', '取り下げ',
  '2026-04-05 13:00:00', NULL, NULL, 44000,
  1, 3, '重複申込のため本案件は取り下げ。別案件で対応継続。', NULL, NULL,
  '2026-04-15', 0, 1, 1
);

-- 3017: 解約・contract 2015（法人旧テスト商店 自動車、廃業による中途解約）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, lost_reason,
  expected_premium_amount, assigned_staff_id, office_staff_id, remark,
  renewal_method, procedure_method, completed_date, is_deleted, created_by, updated_by
) VALUES (
  3017, 2015, '2025-04-01', '2025-03-15', '解約',
  '2025-03-08 11:30:00', NULL, 'cancelled', '廃業による中途解約。',
  180000, 1, 3, NULL,
  NULL, NULL, '2025-03-25', 0, 1, 1
);

-- 3018: 解約・contract 2016（法人旧テスト商店 賠責、廃業による解約）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline, case_status,
  last_contact_at, next_action_date, renewal_result, lost_reason,
  expected_premium_amount, assigned_staff_id, office_staff_id, remark,
  renewal_method, procedure_method, completed_date, is_deleted, created_by, updated_by
) VALUES (
  3018, 2016, '2025-06-01', '2025-05-15', '解約',
  '2025-05-05 10:00:00', NULL, 'cancelled', '廃業による解約。',
  48000, 1, 3, NULL,
  NULL, NULL, '2025-05-20', 0, 1, 1
);
