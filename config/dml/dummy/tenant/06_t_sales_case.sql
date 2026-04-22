-- =====================================================================
-- ダミーデータ: t_sales_case（見込案件）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : マスタ m_sales_case_status の保護(is_protected=1)ステータスのみ使用
--         保護外(提案中/ヒアリング中/アプローチ中/見込み)は利用者カスタム扱いのため不使用
-- 件数  : 10件
-- ID範囲: 9001 - 9010
-- 依存  : 02_m_customer.sql, 01_m_staff.sql, m_sales_case_status（master）
-- 関連DDL: config/ddl/tenant/t_sales_case.sql
-- 基準日 : 2026-04-21
-- =====================================================================
-- カバレッジ（保護ステータスのみ）:
--   商談中  : 9001(既存顧客), 9002(新規prospect), 9003(法人prospect)
--   交渉中  : 9004(既存顧客), 9005(個人prospect 未登録)
--   保留    : 9006(既存顧客)
--   成約    : 9007, 9008
--   失注    : 9009, 9010
-- =====================================================================
-- case_type: SalesCaseRepository::ALLOWED_CASE_TYPES = new/renewal/cross_sell/up_sell/other
-- prospect_rank: SalesCaseRepository::ALLOWED_PROSPECT_RANKS = A/B/C
-- =====================================================================

SET NAMES utf8mb4;

-- 9001: 商談中・既存顧客1001（法人 追加契約提案、A ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9001, 1001, NULL, NULL, 'テストコーポ 役員向け生保追加提案', 'cross_sell', '終身保険',
  '商談中', 'A', 240000, '2026-06', '2026-04-25',
  '役員3名向けの生保提案。総務部と調整中。', 1, 1, 0, 1, 1
);

-- 9002: 商談中・個人prospect 1008（鈴木、自動車新規、B ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9002, 1008, NULL, NULL, '鈴木様 自動車新規見積', 'new', 'おとなの自動車',
  '商談中', 'B', 72000, '2026-06', '2026-04-28',
  '顧客1001からの紹介。他社からの乗換検討。', 2, 2, 0, 1, 1
);

-- 9003: 商談中・法人prospect 1009（東京建設 賠責、A ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9003, 1009, NULL, NULL, '東京建設 賠責新規パッケージ', 'new', '業務災害総合',
  '商談中', 'A', 380000, '2026-07', '2026-04-30',
  '新規開拓。初回提案資料送付済。', 1, 1, 0, 1, 1
);

-- 9004: 交渉中・既存顧客1002（運輸、追加車両アップセル、A ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9004, 1002, NULL, 2004, 'テスト運輸 追加車両10台 up_sell', 'up_sell', '事業用Ｋ・Ａ・Ｐ',
  '交渉中', 'A', 850000, '2026-07', '2026-04-26',
  '増車計画に合わせた提案。価格交渉段階。', 2, 2, 0, 1, 1
);

-- 9005: 交渉中・未登録顧客（飛び込み・prospect_name のみ、B ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9005, NULL, '小林 美咲（未登録）', NULL, '小林様 自動車乗換見積', 'new', 'GKクルマの保険・家庭用',
  '交渉中', 'B', 65000, '2026-05', '2026-04-24',
  '見込先として未登録。成約時に顧客登録予定。', 2, 2, 0, 1, 1
);

-- 9006: 保留・既存顧客1006（生保乗換、C ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9006, 1006, NULL, 2011, '高橋様 生保乗換検討', 'renewal', '終身医療',
  '保留', 'C', 90000, '2026-10', '2026-06-30',
  '顧客都合で一旦保留。秋に再開予定。', 1, 1, 0, 1, 1
);

-- 9007: 成約・既存顧客1004（クロスセル火災が成約、A ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9007, 1004, NULL, 2020, '山田様 火災クロスセル', 'cross_sell', 'すまいの保険',
  '成約', 'A', 44000, '2025-11', NULL,
  '2025/10 成約、t_contract 2020 として登録済み。', 1, 1, 0, 1, 1
);

-- 9008: 成約・法人1002（第2倉庫火災 成約、A ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9008, 1002, NULL, 2018, 'テスト運輸 第2倉庫火災新規', 'new', '倉庫物件火災',
  '成約', 'A', 135000, '2026-02', NULL,
  '2026/2 成約、t_contract 2018 として登録済み。', 2, 2, 0, 1, 1
);

-- 9009: 失注・個人prospect（競合負け、C ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  lost_reason, memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9009, NULL, '森 隆文（未登録）', NULL, '森様 自動車見積', 'new', 'おとなの自動車',
  '失注', 'C', 58000, '2026-03', NULL,
  '競合他社の方が保険料安く、価格面で負け。', NULL, 2, 2, 0, 1, 1
);

-- 9010: 失注・既存顧客1007（提案不採用、B ランク）
INSERT INTO t_sales_case (
  id, customer_id, prospect_name, contract_id, case_name, case_type, product_type,
  status, prospect_rank, expected_premium, expected_contract_month, next_action_date,
  lost_reason, memo, assigned_staff_id, staff_id, is_deleted, created_by, updated_by
) VALUES (
  9010, 1007, NULL, 2012, '渡辺様 傷害追加提案', 'cross_sell', '普通傷害',
  '失注', 'B', 18000, '2026-02', NULL,
  '既存プランで十分との顧客判断で見送り。', NULL, 2, 2, 0, 1, 1
);
