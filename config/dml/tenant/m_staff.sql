-- m_staff テスト用 DML（ローカル開発環境用）
-- user_id は xs000001_admin.users.id と対応
SET NAMES utf8mb4;

INSERT INTO m_staff (id, staff_name, is_sales, is_office, user_id, sjnet_code, is_active, sort_order, created_by, updated_by)
VALUES
  (1, '中村 翔',    1, 0, 1, NULL, 1, 10, 1, 1),
  (2, 'テスト担当者', 1, 0, 2, NULL, 1, 20, 1, 1);
