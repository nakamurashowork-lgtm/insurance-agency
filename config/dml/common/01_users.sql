-- =====================================================================
-- 動作確認用DML: users
-- 用途: 動作確認に使う2名のテストユーザー
-- 件数: 2件
-- ID範囲: 1, 2
-- 依存: なし（最初に投入する）
-- 関連DDL: config/ddl/common/users.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ユーザーID=1: テスト管理者（システム管理者権限あり）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  1, 'test-admin-google-sub-000001', 'test-admin@insurance-test.example.jp', 'テスト管理者', 'テスト管理者',
  1, 1, 0,
  NULL, 0, NULL,
  NULL, NULL
);

-- ユーザーID=2: テスト一般ユーザー（担当者として使用）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  2, 'test-user-google-sub-000002', 'test-user@insurance-test.example.jp', 'テストユーザー', 'テスト担当者',
  0, 1, 0,
  NULL, 0, NULL,
  1, 1
);
