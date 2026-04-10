-- =====================================================================
-- 動作確認用DML: user_tenants
-- 用途: テストユーザーをテナント TE001 に紐付ける
-- 件数: 2件
-- 依存: 01_users.sql, tenants（TE001 が存在すること）
-- 関連DDL: config/ddl/common/user_tenants.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ユーザー1（管理者）→ TE001 に admin 権限で所属
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  1, 'TE001', 'admin', 1, 0,
  1, 1
);

-- ユーザー2（一般）→ TE001 に member 権限で所属
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  2, 'TE001', 'member', 1, 0,
  1, 1
);
