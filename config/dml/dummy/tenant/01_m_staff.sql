-- =====================================================================
-- ダミーデータ: m_staff（担当者マスタ）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : 動作確認用の担当者（営業×2 / 事務 / 非活性）
-- 件数  : 4件
-- ID範囲: 1 - 4
-- 依存  : common DB の users (id=2, 3, 4) が投入済みであること
-- 関連DDL: config/ddl/tenant/m_staff.sql
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_staff
  (id, staff_name,       is_sales, is_office, user_id, sjnet_code,
   is_active, sort_order, created_by, updated_by)
VALUES
  (1, '中村 翔',         1, 0, 2,    'SJ001', 1, 10, 1, 1),
  (2, '田中 次郎',       1, 0, 3,    'SJ002', 1, 20, 1, 1),
  (3, '鈴木 花子',       0, 1, 4,    NULL,    1, 30, 1, 1),
  (4, '退職者 佐々木',   1, 0, NULL, NULL,    0, 99, 1, 1)
ON DUPLICATE KEY UPDATE
  staff_name = VALUES(staff_name),
  is_sales   = VALUES(is_sales),
  is_office  = VALUES(is_office),
  user_id    = VALUES(user_id),
  is_active  = VALUES(is_active),
  sort_order = VALUES(sort_order),
  updated_by = VALUES(updated_by);
