-- =====================================================================
-- 移行DML: Phase 3 マスタ系の code → name 移行
-- 日時  : 2026-04-20
-- 用途  :
--   - t_activity.activity_type: 旧コード(visit/call/...) → 日本語 name
--   - t_activity.purpose_type : 旧コード(renewal/new_business/...) → 日本語 name
--   - m_product_category.display_name → name（カラム名変更のみ、データはそのまま）
-- =====================================================================

SET NAMES utf8mb4;

-- t_activity.activity_type
UPDATE t_activity SET activity_type = '訪問'       WHERE activity_type = 'visit'   AND is_deleted = 0;
UPDATE t_activity SET activity_type = '電話'       WHERE activity_type IN ('call', 'phone') AND is_deleted = 0;
UPDATE t_activity SET activity_type = 'メール'     WHERE activity_type = 'email'   AND is_deleted = 0;
UPDATE t_activity SET activity_type = 'オンライン' WHERE activity_type = 'online'  AND is_deleted = 0;
UPDATE t_activity SET activity_type = '会議'       WHERE activity_type = 'meeting' AND is_deleted = 0;
UPDATE t_activity SET activity_type = '研修'       WHERE activity_type = 'seminar' AND is_deleted = 0;
UPDATE t_activity SET activity_type = 'その他'     WHERE activity_type = 'other'   AND is_deleted = 0;

-- t_activity.purpose_type
UPDATE t_activity SET purpose_type = '満期対応'           WHERE purpose_type = 'renewal'      AND is_deleted = 0;
UPDATE t_activity SET purpose_type = '新規開拓'           WHERE purpose_type = 'new_business' AND is_deleted = 0;
UPDATE t_activity SET purpose_type = 'クロスセル提案'     WHERE purpose_type = 'cross_sell'   AND is_deleted = 0;
UPDATE t_activity SET purpose_type = '事故対応'           WHERE purpose_type = 'accident'     AND is_deleted = 0;
UPDATE t_activity SET purpose_type = 'フォロー'           WHERE purpose_type = 'follow_up'    AND is_deleted = 0;
UPDATE t_activity SET purpose_type = '内務・社内作業'     WHERE purpose_type = 'admin'        AND is_deleted = 0;
UPDATE t_activity SET purpose_type = '会議・ミーティング' WHERE purpose_type = 'meeting'      AND is_deleted = 0;
UPDATE t_activity SET purpose_type = '研修・勉強会'       WHERE purpose_type = 'training'     AND is_deleted = 0;
UPDATE t_activity SET purpose_type = 'その他'             WHERE purpose_type = 'other'        AND is_deleted = 0;
