-- =====================================================================
-- 動作確認用DML: t_renewal_case
-- 用途: 各契約の満期案件データ（業務シナリオ網羅）
-- 件数: 100件
-- ID範囲: 3001 - 3100
-- 依存: 03_t_contract.sql（t_contract）
-- 関連DDL: config/ddl/tenant/t_renewal_case.sql
-- ステータス凡例:
--   not_started     = 未着手
--   sj_requested    = SJ依頼済み
--   doc_prepared    = 書類準備中
--   waiting_return  = 返却待ち
--   quote_sent      = 見積送付済み
--   waiting_payment = 払込待ち
--   completed       = 完了（renewal_result で結果を区別）
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 契約2001 (顧客1001, 自動車, 満期2026-05-01) ==========

-- 3001: 前年度（2025-05-01）完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3001, 2001, '2025-05-01', '2025-04-15',
  'completed', '2025-04-10 14:00:00', NULL,
  'renewed', NULL, 175000, 180000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-18',
  0, 1, 1
);

-- 3002: 今年度（2026-05-01）未対応（今日から30日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3002, 2001, '2026-05-01', '2026-04-17',
  'not_started', NULL, '2026-04-10',
  NULL, NULL, 180000, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2002 (顧客1001, 火災, 満期2026-06-15) ==========

-- 3003: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3003, 2002, '2025-06-15', '2025-06-01',
  'completed', '2025-05-20 10:00:00', NULL,
  'renewed', NULL, 240000, 250000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-06-05',
  0, 1, 1
);

-- 3004: 今年度 SJ依頼済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3004, 2002, '2026-06-15', '2026-06-01',
  'sj_requested', '2026-04-05 09:30:00', '2026-04-20',
  NULL, NULL, 250000, NULL,
  NULL, 1, 2,
  'SJ依頼書送付済み', NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2003 (顧客1001, 傷害, 満期2026-12-31) ==========

-- 3005: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3005, 2003, '2025-12-31', '2025-12-15',
  'completed', '2025-12-10 11:00:00', NULL,
  'renewed', NULL, 85000, 90000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-12-20',
  0, 1, 1
);

-- 3006: 今年度 未着手（7ヶ月先）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3006, 2003, '2026-12-31', '2026-12-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2004 (顧客1001, 企業総合, 満期2026-10-01) ==========

-- 3007: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3007, 2004, '2025-10-01', '2025-09-15',
  'completed', '2025-09-20 15:00:00', NULL,
  'renewed', NULL, 400000, 420000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-09-25',
  0, 1, 1
);

-- 3008: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3008, 2004, '2026-10-01', '2026-09-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2005 (顧客1001, 賠償, 満期2026-04-20) ==========

-- 3009: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3009, 2005, '2025-04-20', '2025-04-05',
  'completed', '2025-04-01 14:30:00', NULL,
  'renewed', NULL, 70000, 75000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-04-10',
  0, 1, 1
);

-- 3010: 今年度 未対応（今日から13日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3010, 2005, '2026-04-20', '2026-04-08',
  'not_started', NULL, '2026-04-08',
  NULL, NULL, 75000, NULL,
  NULL, 1, 2,
  '早期締切迫る', NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2006 (顧客1001, 生命, 満期2026-04-30) ==========

-- 3011: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3011, 2006, '2025-04-30', '2025-04-15',
  'completed', '2025-04-12 10:00:00', NULL,
  'renewed', NULL, 355000, 360000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-04-20',
  0, 1, 1
);

-- 3012: 今年度 見積送付済み（30日以内・対応中）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3012, 2006, '2026-04-30', '2026-04-15',
  'quote_sent', '2026-04-05 10:00:00', '2026-04-12',
  NULL, NULL, 360000, NULL,
  NULL, 1, 2,
  '見積書メール送付済み。確認の返答待ち。', NULL, 'direct', NULL,
  0, 1, 1
);

-- ========== 契約2007 (顧客1002, フリート自動車, 満期2026-04-15) ==========

-- 3013: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3013, 2007, '2025-04-15', '2025-04-01',
  'completed', '2025-03-28 11:00:00', NULL,
  'renewed', NULL, 630000, 650000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-05',
  0, 1, 1
);

-- 3014: 今年度 未対応（今日から8日以内・緊急）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3014, 2007, '2026-04-15', '2026-04-01',
  'not_started', NULL, '2026-04-08',
  NULL, NULL, 650000, NULL,
  NULL, 1, 2,
  '満期まで8日。早急対応要。', NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2008 (顧客1002, 火災, 満期2026-06-01) ==========

-- 3015: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3015, 2008, '2025-06-01', '2025-05-15',
  'completed', '2025-05-10 09:00:00', NULL,
  'renewed', NULL, 175000, 180000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-05-20',
  0, 1, 1
);

-- 3016: 今年度 書類準備中
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3016, 2008, '2026-06-01', '2026-05-15',
  'doc_prepared', '2026-04-01 13:00:00', '2026-04-25',
  NULL, NULL, 180000, NULL,
  NULL, 1, 2,
  '建物評価書取得中', NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2009 (顧客1002, 傷害, 満期2026-03-31) ==========

-- 3017: 2年前完了・更改済み（2025-03-31）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3017, 2009, '2025-03-31', '2025-03-15',
  'completed', '2025-03-10 10:00:00', NULL,
  'renewed', NULL, 115000, 120000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-03-20',
  0, 1, 1
);

-- 3018: 最近完了・更改済み（2026-03-31, 先週完了）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3018, 2009, '2026-03-31', '2026-03-15',
  'completed', '2026-03-25 14:00:00', NULL,
  'renewed', NULL, 120000, 125000,
  NULL, 1, 2,
  '無事更改完了', 'renewal', 'sjnet', '2026-03-28',
  0, 1, 1
);

-- ========== 契約2010 (顧客1002, 企業賠償, 満期2026-07-01) ==========

-- 3019: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3019, 2010, '2025-07-01', '2025-06-15',
  'completed', '2025-06-20 10:00:00', NULL,
  'renewed', NULL, 90000, 95000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-06-25',
  0, 1, 1
);

-- 3020: 今年度 SJ依頼済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3020, 2010, '2026-07-01', '2026-06-15',
  'sj_requested', '2026-04-03 09:00:00', '2026-04-30',
  NULL, NULL, 95000, NULL,
  NULL, 1, 2,
  NULL, NULL, 'direct', NULL,
  0, 1, 1
);

-- ========== 契約2011 (顧客1002, 新種, 満期2025-09-01, 失注) ==========

-- 3021: 2年前完了・更改済み（2024-09-01）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3021, 2011, '2024-09-01', '2024-08-15',
  'completed', '2024-08-20 11:00:00', NULL,
  'renewed', NULL, 42000, 45000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2024-08-25',
  0, 1, 1
);

-- 3022: 失注（2025-09-01, 他社乗り換え）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3022, 2011, '2025-09-01', '2025-08-15',
  'completed', '2025-08-10 15:00:00', NULL,
  'lost', '競合他社の見積が大幅に安かった', 45000, NULL,
  NULL, 1, 2,
  '価格競争で敗北。フォロー継続予定。', NULL, NULL, '2025-08-25',
  0, 1, 1
);

-- ========== 契約2012 (顧客1003, 自動車, 満期2026-04-25) ==========

-- 3023: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3023, 2012, '2025-04-25', '2025-04-10',
  'completed', '2025-04-05 10:00:00', NULL,
  'renewed', NULL, 90000, 95000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-04-15',
  0, 2, 2
);

-- 3024: 今年度 未対応（今日から18日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3024, 2012, '2026-04-25', '2026-04-11',
  'not_started', NULL, '2026-04-10',
  NULL, NULL, 95000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2013 (顧客1003, 火災, 満期2026-08-01) ==========

-- 3025: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3025, 2013, '2025-08-01', '2025-07-15',
  'completed', '2025-07-20 14:00:00', NULL,
  'renewed', NULL, 135000, 140000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-07-25',
  0, 2, 2
);

-- 3026: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3026, 2013, '2026-08-01', '2026-07-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2014 (顧客1003, 傷害, 満期2026-04-10) ==========

-- 3027: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3027, 2014, '2025-04-10', '2025-03-27',
  'completed', '2025-03-25 11:00:00', NULL,
  'renewed', NULL, 52000, 55000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-04-01',
  0, 2, 2
);

-- 3028: 今年度 払込待ち（3日以内に満期！）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3028, 2014, '2026-04-10', '2026-03-27',
  'waiting_payment', '2026-04-06 10:00:00', '2026-04-08',
  NULL, NULL, 55000, 58000,
  NULL, 2, 1,
  '見積確認済み。払込書発送済み。入金確認待ち。', 'renewal', 'direct', NULL,
  0, 2, 2
);

-- ========== 契約2015 (顧客1003, 店舗総合, 満期2026-09-01) ==========

-- 3029: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3029, 2015, '2025-09-01', '2025-08-15',
  'completed', '2025-08-20 09:00:00', NULL,
  'renewed', NULL, 200000, 210000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-08-25',
  0, 2, 2
);

-- 3030: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3030, 2015, '2026-09-01', '2026-08-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2016 (顧客1004, フリート, 満期2026-04-30) ==========

-- 3031: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3031, 2016, '2025-04-30', '2025-04-15',
  'completed', '2025-04-10 14:00:00', NULL,
  'renewed', NULL, 310000, 320000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-20',
  0, 1, 1
);

-- 3032: 今年度 未対応（23日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3032, 2016, '2026-04-30', '2026-04-16',
  'not_started', NULL, '2026-04-12',
  NULL, NULL, 320000, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2017 (顧客1004, 工事, 満期2026-11-01) ==========

-- 3033: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3033, 2017, '2025-11-01', '2025-10-15',
  'completed', '2025-10-20 10:00:00', NULL,
  'renewed', NULL, 180000, 185000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-10-25',
  0, 1, 1
);

-- 3034: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3034, 2017, '2026-11-01', '2026-10-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2018 (顧客1004, 傷害, 満期2026-05-01) ==========

-- 3035: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3035, 2018, '2025-05-01', '2025-04-15',
  'completed', '2025-04-10 09:00:00', NULL,
  'renewed', NULL, 75000, 78000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-20',
  0, 1, 1
);

-- 3036: 今年度 未対応（今日から24日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3036, 2018, '2026-05-01', '2026-04-17',
  'not_started', NULL, '2026-04-15',
  NULL, NULL, 78000, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2019 (顧客1004, 賠償, 満期2026-07-15) ==========

-- 3037: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3037, 2019, '2025-07-15', '2025-07-01',
  'completed', '2025-07-01 11:00:00', NULL,
  'renewed', NULL, 105000, 110000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-07-10',
  0, 1, 1
);

-- 3038: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3038, 2019, '2026-07-15', '2026-07-01',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2020 (顧客1005, 医師賠償, 満期2026-09-01) ==========

-- 3039: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3039, 2020, '2025-09-01', '2025-08-15',
  'completed', '2025-08-20 10:00:00', NULL,
  'renewed', NULL, 275000, 285000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-08-28',
  0, 2, 2
);

-- 3040: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3040, 2020, '2026-09-01', '2026-08-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2021 (顧客1005, 火災, 満期2026-12-01) ==========

-- 3041: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3041, 2021, '2025-12-01', '2025-11-15',
  'completed', '2025-11-20 14:00:00', NULL,
  'renewed', NULL, 160000, 165000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-11-25',
  0, 2, 2
);

-- 3042: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3042, 2021, '2026-12-01', '2026-11-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2022 (顧客1006, 自動車, 満期2026-04-25) ==========

-- 3043: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3043, 2022, '2025-04-25', '2025-04-10',
  'completed', '2025-04-08 10:00:00', NULL,
  'renewed', NULL, 68000, 72000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-04-15',
  0, 1, 1
);

-- 3044: 今年度 未対応（今日から18日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3044, 2022, '2026-04-25', '2026-04-11',
  'not_started', NULL, '2026-04-12',
  NULL, NULL, 72000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2023 (顧客1006, 傷害, 満期2026-08-01) ==========

-- 3045: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3045, 2023, '2025-08-01', '2025-07-15',
  'completed', '2025-07-15 10:00:00', NULL,
  'renewed', NULL, 25000, 28000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-07-25',
  0, 1, 1
);

-- 3046: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3046, 2023, '2026-08-01', '2026-07-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2024 (顧客1006, 生命, 満期2026-11-01) ==========

-- 3047: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3047, 2024, '2025-11-01', '2025-10-15',
  'completed', '2025-10-20 14:00:00', NULL,
  'renewed', NULL, 45000, 48000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-10-28',
  0, 1, 1
);

-- 3048: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3048, 2024, '2026-11-01', '2026-10-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2025 (顧客1007, 自動車, 満期2026-05-07) ==========

-- 3049: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3049, 2025, '2025-05-07', '2025-04-23',
  'completed', '2025-04-20 11:00:00', NULL,
  'renewed', NULL, 62000, 65000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-04-28',
  0, 1, 1
);

-- 3050: 今年度 未対応（今日ちょうど30日後 = 境界値テスト）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3050, 2025, '2026-05-07', '2026-04-23',
  'not_started', NULL, '2026-04-15',
  NULL, NULL, 65000, NULL,
  NULL, 2, 1,
  '30日境界値ケース', NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2026 (顧客1007, 火災, 満期2026-10-01) ==========

-- 3051: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3051, 2026, '2025-10-01', '2025-09-15',
  'completed', '2025-09-15 10:00:00', NULL,
  'renewed', NULL, 36000, 38000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-09-25',
  0, 1, 1
);

-- 3052: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3052, 2026, '2026-10-01', '2026-09-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2027 (顧客1007, がん保険, 満期2027-01-01) ==========

-- 3053: 前年度完了・更改済み（2026-01-01, 今年1月完了）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3053, 2027, '2026-01-01', '2025-12-15',
  'completed', '2025-12-20 10:00:00', NULL,
  'renewed', NULL, 22000, 24000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-12-28',
  0, 1, 1
);

-- 3054: 今年度 未着手（2027-01-01）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3054, 2027, '2027-01-01', '2026-12-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2028 (顧客1008, 自動車, 満期2026-06-01) ==========

-- 3055: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3055, 2028, '2025-06-01', '2025-05-15',
  'completed', '2025-05-20 11:00:00', NULL,
  'renewed', NULL, 55000, 58000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-05-28',
  0, 2, 2
);

-- 3056: 今年度 書類準備中
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3056, 2028, '2026-06-01', '2026-05-15',
  'doc_prepared', '2026-03-28 15:00:00', '2026-04-20',
  NULL, NULL, 58000, NULL,
  NULL, 1, 2,
  '車検証コピー待ち', NULL, 'sjnet', NULL,
  0, 2, 2
);

-- ========== 契約2029 (顧客1008, 傷害, 満期2026-09-01) ==========

-- 3057: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3057, 2029, '2025-09-01', '2025-08-15',
  'completed', '2025-08-20 09:30:00', NULL,
  'renewed', NULL, 20000, 22000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-08-28',
  0, 2, 2
);

-- 3058: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3058, 2029, '2026-09-01', '2026-08-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2030 (顧客1009, 自動車, 満期2026-04-15) ==========

-- 3059: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3059, 2030, '2025-04-15', '2025-04-01',
  'completed', '2025-03-30 10:00:00', NULL,
  'renewed', NULL, 78000, 82000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2025-04-05',
  0, 2, 2
);

-- 3060: 今年度 未対応（8日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3060, 2030, '2026-04-15', '2026-04-01',
  'not_started', NULL, '2026-04-08',
  NULL, NULL, 82000, NULL,
  NULL, 2, 1,
  '事故処理中のため慎重対応', NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2031 (顧客1009, 生命, 満期2026-12-01) ==========

-- 3061: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3061, 2031, '2025-12-01', '2025-11-15',
  'completed', '2025-11-20 10:00:00', NULL,
  'renewed', NULL, 33000, 36000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2025-11-28',
  0, 2, 2
);

-- 3062: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3062, 2031, '2026-12-01', '2026-11-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, NULL,
  0, 2, 2
);

-- ========== 契約2032 (顧客1010, 自動車, 満期2026-05-01) ==========

-- 3063: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3063, 2032, '2025-05-01', '2025-04-15',
  'completed', '2025-04-15 14:00:00', NULL,
  'renewed', NULL, 70000, 74000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-22',
  0, 1, 1
);

-- 3064: 今年度 SJ依頼済み（30日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3064, 2032, '2026-05-01', '2026-04-17',
  'sj_requested', '2026-04-04 10:00:00', '2026-04-18',
  NULL, NULL, 74000, NULL,
  NULL, 1, 2,
  'SJ依頼書FAX送信済み', NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2033 (顧客1010, 火災, 満期2026-08-15) ==========

-- 3065: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3065, 2033, '2025-08-15', '2025-08-01',
  'completed', '2025-08-01 09:00:00', NULL,
  'renewed', NULL, 28000, 31000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-08-10',
  0, 1, 1
);

-- 3066: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3066, 2033, '2026-08-15', '2026-08-01',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2034 (顧客1014, 自動車, 満期2025-03-31, 失注→inactive) ==========

-- 3067: 2024年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3067, 2034, '2024-03-31', '2024-03-15',
  'completed', '2024-03-20 10:00:00', NULL,
  'renewed', NULL, 120000, 125000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2024-03-25',
  0, 1, 1
);

-- 3068: 2025年度 失注（競合他社へ）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3068, 2034, '2025-03-31', '2025-03-15',
  'completed', '2025-03-10 14:00:00', NULL,
  'lost', 'ネット損保に切り替えると顧客から連絡あり', 125000, NULL,
  NULL, 1, 2,
  'インターネット損保に乗り換え。フォローアップ継続。', NULL, NULL, '2025-03-20',
  0, 1, 1
);

-- ========== 契約2035 (顧客1014, 火災, 満期2025-06-01, cancelled) ==========

-- 3069: 2024年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3069, 2035, '2024-06-01', '2024-05-15',
  'completed', '2024-05-20 10:00:00', NULL,
  'renewed', NULL, 85000, 88000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2024-05-28',
  0, 1, 1
);

-- 3070: 2025年度 解約（廃業のため）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3070, 2035, '2025-06-01', '2025-05-15',
  'completed', '2025-04-15 10:00:00', NULL,
  'cancelled', '廃業のため解約', 88000, NULL,
  NULL, 1, 2,
  '廃業届受領。解約手続き完了。', NULL, NULL, '2025-04-20',
  0, 1, 1
);

-- ========== 契約2036 (顧客1015, 自動車, 満期2025-01-15, inactive) ==========

-- 3071: 2024年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3071, 2036, '2024-01-15', '2024-01-01',
  'completed', '2024-01-05 10:00:00', NULL,
  'renewed', NULL, 60000, 62000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2024-01-10',
  0, 1, 1
);

-- 3072: 2025年度 失注
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3072, 2036, '2025-01-15', '2025-01-01',
  'completed', '2025-01-05 14:00:00', NULL,
  'lost', '家族が他の代理店で加入済みとのこと', 62000, NULL,
  NULL, 2, 1,
  '他代理店に切り替え済み。', NULL, NULL, '2025-01-10',
  0, 1, 1
);

-- ========== 契約2037 (顧客1016, 傷害, 満期2024-12-01, inactive) ==========

-- 3073: 2023年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3073, 2037, '2023-12-01', '2023-11-15',
  'completed', '2023-11-20 10:00:00', NULL,
  'renewed', NULL, 16000, 18000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2023-11-25',
  0, 2, 2
);

-- 3074: 2024年度 解約（内容変更の要望のため解約）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3074, 2037, '2024-12-01', '2024-11-15',
  'completed', '2024-11-10 15:00:00', NULL,
  'cancelled', '保険内容の見直しを希望。別商品への切り替え交渉中。', 18000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, '2024-11-20',
  0, 2, 2
);

-- ========== 契約2038 (顧客1017, 自動車, 満期2024-04-01, cancelled) ==========

-- 3075: 2023年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3075, 2038, '2023-04-01', '2023-03-15',
  'completed', '2023-03-20 10:00:00', NULL,
  'renewed', NULL, 90000, 95000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2023-03-28',
  0, 1, 1
);

-- 3076: 2024年度 解約（廃業）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3076, 2038, '2024-04-01', '2024-03-15',
  'completed', '2024-02-20 10:00:00', NULL,
  'cancelled', '廃業のため', 95000, NULL,
  NULL, 1, 2,
  '廃業届提出確認済み。全契約解約。', NULL, NULL, '2024-02-28',
  0, 1, 1
);

-- ========== 契約2039 (顧客1017, 火災, 満期2024-08-01, expired) ==========

-- 3077: 2023年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3077, 2039, '2023-08-01', '2023-07-15',
  'completed', '2023-07-20 11:00:00', NULL,
  'renewed', NULL, 68000, 72000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2023-07-28',
  0, 1, 1
);

-- 3078: 2024年度 失注（廃業で連絡不通）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3078, 2039, '2024-08-01', '2024-07-15',
  'completed', '2024-07-01 10:00:00', NULL,
  'lost', '廃業後、連絡不通により更改できず失効', 72000, NULL,
  NULL, 1, 2,
  '廃業後連絡不通。失効扱い。', NULL, NULL, '2024-07-20',
  0, 1, 1
);

-- ========== 契約2040 (顧客1017, 傷害, 満期2023-12-01, cancelled) ==========

-- 3079: 2022年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3079, 2040, '2022-12-01', '2022-11-15',
  'completed', '2022-11-20 10:00:00', NULL,
  'renewed', NULL, 35000, 38000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2022-11-28',
  0, 1, 1
);

-- 3080: 2023年度 解約（廃業予定による）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3080, 2040, '2023-12-01', '2023-11-15',
  'completed', '2023-11-05 10:00:00', NULL,
  'cancelled', '翌年廃業予定のため解約', 38000, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, '2023-11-10',
  0, 1, 1
);

-- ========== 契約2041 (顧客1018, 自動車, 満期2024-06-01, expired) ==========

-- 3081: 2023年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3081, 2041, '2023-06-01', '2023-05-15',
  'completed', '2023-05-20 10:00:00', NULL,
  'renewed', NULL, 55000, 58000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2023-05-28',
  0, 1, 1
);

-- 3082: 2024年度 失注（連絡不通）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3082, 2041, '2024-06-01', '2024-05-15',
  'completed', '2024-05-01 14:00:00', NULL,
  'lost', '転居後連絡不通。満期日経過により失効。', 58000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, '2024-06-10',
  0, 1, 1
);

-- ========== 契約2042 (顧客1018, 生命, 満期2024-01-01, cancelled) ==========

-- 3083: 2023年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3083, 2042, '2023-01-01', '2022-12-15',
  'completed', '2022-12-20 10:00:00', NULL,
  'renewed', NULL, 45000, 48000,
  NULL, 2, 1,
  NULL, 'renewal', 'direct', '2022-12-28',
  0, 1, 1
);

-- 3084: 2024年度 解約
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3084, 2042, '2024-01-01', '2023-12-15',
  'completed', '2023-12-10 10:00:00', NULL,
  'cancelled', '解約申請書受領。解約返戻金精算済み。', 48000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, '2023-12-20',
  0, 1, 1
);

-- ========== 契約2043 (顧客1019, 自動車, 満期2023-09-01, cancelled) ==========

-- 3085: 2022年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3085, 2043, '2022-09-01', '2022-08-15',
  'completed', '2022-08-20 10:00:00', NULL,
  'renewed', NULL, 42000, 45000,
  NULL, 2, 1,
  NULL, 'renewal', 'sjnet', '2022-08-28',
  0, 2, 2
);

-- 3086: 2023年度 解約
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3086, 2043, '2023-09-01', '2023-08-15',
  'completed', '2023-08-10 14:00:00', NULL,
  'cancelled', '北海道へ転居のため解約。', 45000, NULL,
  NULL, 2, 1,
  NULL, NULL, NULL, '2023-08-15',
  0, 2, 2
);

-- ========== 契約2044 (顧客1020, フリートA, 満期2026-04-30) ==========

-- 3087: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3087, 2044, '2025-04-30', '2025-04-15',
  'completed', '2025-04-10 14:00:00', NULL,
  'renewed', NULL, 270000, 280000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-20',
  0, 1, 1
);

-- 3088: 今年度 未対応（23日以内）
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3088, 2044, '2026-04-30', '2026-04-16',
  'not_started', NULL, '2026-04-10',
  NULL, NULL, 280000, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2045 (顧客1020, フリートB, 満期2026-05-01) ==========

-- 3089: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3089, 2045, '2025-05-01', '2025-04-15',
  'completed', '2025-04-12 11:00:00', NULL,
  'renewed', NULL, 190000, 195000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-04-22',
  0, 1, 1
);

-- 3090: 今年度 返却待ち
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3090, 2045, '2026-05-01', '2026-04-17',
  'waiting_return', '2026-04-02 10:00:00', '2026-04-15',
  NULL, NULL, 195000, NULL,
  NULL, 1, 2,
  '継続承認書類を顧客に送付済み。返送待ち。', NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2046 (顧客1020, フリートC, 満期2026-06-01) ==========

-- 3091: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3091, 2046, '2025-06-01', '2025-05-15',
  'completed', '2025-05-20 14:00:00', NULL,
  'renewed', NULL, 150000, 155000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-05-28',
  0, 1, 1
);

-- 3092: 今年度 SJ依頼済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3092, 2046, '2026-06-01', '2026-05-15',
  'sj_requested', '2026-04-06 09:00:00', '2026-04-25',
  NULL, NULL, 155000, NULL,
  NULL, 1, 2,
  NULL, NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2047 (顧客1020, 火災, 満期2026-07-01) ==========

-- 3093: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3093, 2047, '2025-07-01', '2025-06-15',
  'completed', '2025-06-20 10:00:00', NULL,
  'renewed', NULL, 215000, 220000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-06-28',
  0, 1, 1
);

-- 3094: 今年度 書類準備中
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3094, 2047, '2026-07-01', '2026-06-15',
  'doc_prepared', '2026-04-01 11:00:00', '2026-05-01',
  NULL, NULL, 220000, NULL,
  NULL, 1, 2,
  '新ショールーム建物評価再算定中', NULL, 'sjnet', NULL,
  0, 1, 1
);

-- ========== 契約2048 (顧客1020, 企業総合, 満期2026-10-01) ==========

-- 3095: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3095, 2048, '2025-10-01', '2025-09-15',
  'completed', '2025-09-20 14:00:00', NULL,
  'renewed', NULL, 370000, 380000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-09-28',
  0, 1, 1
);

-- 3096: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3096, 2048, '2026-10-01', '2026-09-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2049 (顧客1020, 傷害, 満期2026-09-15) ==========

-- 3097: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3097, 2049, '2025-09-15', '2025-09-01',
  'completed', '2025-09-05 10:00:00', NULL,
  'renewed', NULL, 92000, 96000,
  NULL, 1, 2,
  NULL, 'renewal', 'sjnet', '2025-09-10',
  0, 1, 1
);

-- 3098: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3098, 2049, '2026-09-15', '2026-09-01',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);

-- ========== 契約2050 (顧客1020, 新種, 満期2026-11-01) ==========

-- 3099: 前年度完了・更改済み
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3099, 2050, '2025-11-01', '2025-10-15',
  'completed', '2025-10-20 10:00:00', NULL,
  'renewed', NULL, 52000, 55000,
  NULL, 1, 2,
  NULL, 'renewal', 'direct', '2025-10-28',
  0, 1, 1
);

-- 3100: 今年度 未着手
INSERT INTO t_renewal_case (
  id, contract_id, maturity_date, early_renewal_deadline,
  case_status, last_contact_at, next_action_date,
  renewal_result, lost_reason, expected_premium_amount, actual_premium_amount,
  renewed_contract_id, assigned_staff_id, office_staff_id,
  remark, renewal_method, procedure_method, completed_date,
  is_deleted, created_by, updated_by
) VALUES (
  3100, 2050, '2026-11-01', '2026-10-15',
  'not_started', NULL, NULL,
  NULL, NULL, NULL, NULL,
  NULL, 1, 2,
  NULL, NULL, NULL, NULL,
  0, 1, 1
);
