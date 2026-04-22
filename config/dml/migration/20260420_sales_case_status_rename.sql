-- =====================================================================
-- 移行DML: m_sales_case_status の code 廃止と name 統一
-- 日時  : 2026-04-20
-- 用途  :
--   旧スキーマ (code, display_name) → 新スキーマ (name) への移行。
--   t_sales_case.status に格納されている旧コード値 (open/negotiating/won/lost/on_hold)
--   を新マスタの表示名 (商談中/交渉中/成約/失注/保留) に書き換える。
--
-- 前提  : 新スキーマの m_sales_case_status が適用済み（name カラム存在）。
-- 対象  : 既存環境のみ（新規環境は seed だけで完結）。
-- =====================================================================

SET NAMES utf8mb4;

-- 旧 code → 新 name への UPDATE
UPDATE t_sales_case SET status = '商談中' WHERE status = 'open'        AND is_deleted = 0;
UPDATE t_sales_case SET status = '交渉中' WHERE status = 'negotiating' AND is_deleted = 0;
UPDATE t_sales_case SET status = '成約'   WHERE status = 'won'         AND is_deleted = 0;
UPDATE t_sales_case SET status = '失注'   WHERE status = 'lost'        AND is_deleted = 0;
UPDATE t_sales_case SET status = '保留'   WHERE status = 'on_hold'     AND is_deleted = 0;

-- 確認用: マスタに未登録の status 値があれば表示（以下のクエリはログ確認用）
-- SELECT DISTINCT sc.status
-- FROM t_sales_case sc
-- LEFT JOIN m_sales_case_status m ON m.name = sc.status
-- WHERE sc.is_deleted = 0 AND m.id IS NULL;
