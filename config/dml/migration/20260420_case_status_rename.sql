-- =====================================================================
-- 移行DML: m_case_status の code 廃止と name 統一
-- 日時  : 2026-04-20
-- 用途  :
--   旧スキーマ (code, display_name) → 新スキーマ (name) への移行。
--   t_renewal_case.case_status / t_accident_case.status の旧コード値を
--   新マスタの表示名に書き換える。
--
-- 前提  : 新スキーマの m_case_status が適用済み（name カラム存在）。
-- 対象  : 既存環境のみ（新規環境は seed で完結）。
-- =====================================================================

SET NAMES utf8mb4;

-- t_renewal_case.case_status
UPDATE t_renewal_case SET case_status = '未対応'     WHERE case_status = 'not_started'     AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = 'SJ依頼中'   WHERE case_status = 'sj_requested'    AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '書類作成済' WHERE case_status = 'doc_prepared'    AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '返送待ち'   WHERE case_status = 'waiting_return'  AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '見積送付済' WHERE case_status = 'quote_sent'      AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '入金待ち'   WHERE case_status = 'waiting_payment' AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '完了'       WHERE case_status = 'completed'       AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '取り下げ'   WHERE case_status = 'withdrawn'       AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '失注'       WHERE case_status = 'lost'            AND is_deleted = 0;
UPDATE t_renewal_case SET case_status = '解約'       WHERE case_status = 'cancelled'       AND is_deleted = 0;

-- t_accident_case.status
UPDATE t_accident_case SET status = '受付'             WHERE status = 'accepted'     AND is_deleted = 0;
UPDATE t_accident_case SET status = '保険会社連絡済み' WHERE status = 'linked'       AND is_deleted = 0;
UPDATE t_accident_case SET status = '対応中'           WHERE status = 'in_progress'  AND is_deleted = 0;
UPDATE t_accident_case SET status = '書類待ち'         WHERE status = 'waiting_docs' AND is_deleted = 0;
UPDATE t_accident_case SET status = '解決済み'         WHERE status = 'resolved'     AND is_deleted = 0;
UPDATE t_accident_case SET status = '完了'             WHERE status = 'closed'       AND is_deleted = 0;
