-- =====================================================================
-- ダミーデータ: user_tenants
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : テストユーザーをテナント TE001 に紐付ける
-- 件数  : 5件
-- 依存  : 01_users.sql, tenants（TE001 が存在すること）
-- 関連DDL: config/ddl/common/user_tenants.sql
-- =====================================================================
--
-- システム管理者は user_tenants に登録しない運用もあり得るが、ここでは
-- テナント側機能も操作できるよう admin 権限で紐付けておく。
-- =====================================================================

SET NAMES utf8mb4;

-- 1: システム管理者 → TE001 admin
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  1, 'TE001', 'admin', 1, 0,
  1, 1
);

-- 2: 中村 翔（営業）→ TE001 admin
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  2, 'TE001', 'admin', 1, 0,
  1, 1
);

-- 3: 田中 次郎（営業）→ TE001 member
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  3, 'TE001', 'member', 1, 0,
  1, 1
);

-- 4: 鈴木 花子（事務）→ TE001 member
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  4, 'TE001', 'member', 1, 0,
  1, 1
);

-- 99: Dev User → TE001 admin（ローカル用）
INSERT IGNORE INTO user_tenants (
  user_id, tenant_code, role, status, is_deleted,
  created_by, updated_by
) VALUES (
  99, 'TE001', 'admin', 1, 0,
  1, 1
);
