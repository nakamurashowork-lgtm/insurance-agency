-- =====================================================================
-- ダミーデータ: users
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : 動作確認用のテストユーザー
-- 件数  : 5件（システム管理者 / 営業×2 / 事務 / ローカル開発）
-- ID範囲: 1, 2, 3, 4, 99
-- 依存  : なし（最初に投入する）
-- 関連DDL: config/ddl/common/users.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1: システム管理者（テナント横断の保守用途）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  1, 'test-sysadmin-google-sub-000001', 'sysadmin@insurance-test.example.jp', 'システム管理者', 'システム管理者',
  1, 1, 0,
  NULL, 0, NULL,
  NULL, NULL
);

-- 2: 営業担当 中村 翔（テナント管理者）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  2, 'test-user-google-sub-000002', 'nakamura@insurance-test.example.jp', 'Nakamura Sho', '中村 翔',
  0, 1, 0,
  NULL, 0, NULL,
  1, 1
);

-- 3: 営業担当 田中 次郎（一般メンバー）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  3, 'test-user-google-sub-000003', 'tanaka@insurance-test.example.jp', 'Tanaka Jiro', '田中 次郎',
  0, 1, 0,
  NULL, 0, NULL,
  1, 1
);

-- 4: 事務担当 鈴木 花子（一般メンバー）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  4, 'test-user-google-sub-000004', 'suzuki@insurance-test.example.jp', 'Suzuki Hanako', '鈴木 花子',
  0, 1, 0,
  NULL, 0, NULL,
  1, 1
);

-- 99: ローカル開発用（APP_ENV=local 専用、本番DB投入禁止）
INSERT IGNORE INTO users (
  id, google_sub, email, name, display_name,
  is_system_admin, status, is_deleted,
  totp_secret, totp_enabled, totp_verified_at,
  created_by, updated_by
) VALUES (
  99, NULL, 'dev@local.test', 'Dev User', 'Dev User',
  0, 1, 0,
  NULL, 0, NULL,
  1, 1
);
