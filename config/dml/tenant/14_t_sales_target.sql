-- =====================================================================
-- 動作確認用DML: t_sales_target
-- 用途: 目標管理テストデータ（年度目標 + 月次目標）
-- 対象年度: 2026年度（2026-04 〜 2027-03）
-- 構成:
--   ID 1-3: 年度目標（annual, target_month IS NULL）
--     1: チーム全体 50,000,000円 (50,000千円)
--     2: 中村 翔 (user_id=1) 30,000,000円 (30,000千円)
--     3: テスト担当者 (user_id=2) 20,000,000円 (20,000千円)
--   ID 4-6: 月次目標 4月（target_month=4）
--     4: チーム全体 4,000,000円 (4,000千円)
--     5: 中村 翔 (user_id=1) 2,500,000円 (2,500千円)
--     6: テスト担当者 (user_id=2) 1,500,000円 (1,500千円)
-- 注意: created_by / updated_by は common.users.id = 1
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 2026年度 年度目標（target_month IS NULL）==========

-- 1: チーム全体目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  1, 2026, NULL, NULL,
  'premium_total', 50000000,
  0, 1, 1
);

-- 2: 中村 翔（user_id=1）個人目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  2, 2026, NULL, 1,
  'premium_total', 30000000,
  0, 1, 1
);

-- 3: テスト担当者（user_id=2）個人目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  3, 2026, NULL, 2,
  'premium_total', 20000000,
  0, 1, 1
);

-- ========== 2026年度 4月次目標（target_month=4）==========

-- 4: チーム全体 4月目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  4, 2026, 4, NULL,
  'premium_total', 4000000,
  0, 1, 1
);

-- 5: 中村 翔（user_id=1）4月目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  5, 2026, 4, 1,
  'premium_total', 2500000,
  0, 1, 1
);

-- 6: テスト担当者（user_id=2）4月目標
INSERT INTO t_sales_target (
  id, fiscal_year, target_month, staff_user_id,
  target_type, target_amount,
  is_deleted, created_by, updated_by
) VALUES (
  6, 2026, 4, 2,
  'premium_total', 1500000,
  0, 1, 1
);
