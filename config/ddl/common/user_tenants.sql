CREATE TABLE IF NOT EXISTS user_tenants (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  user_id     BIGINT UNSIGNED NOT NULL COMMENT 'ユーザーID(users.id)',
  tenant_code VARCHAR(10)     NOT NULL COMMENT 'テナントコード(tenants.tenant_code)',
  role        VARCHAR(20)     NOT NULL COMMENT '権限(例: admin, member)',
  status      TINYINT         NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効,0=無効)',
  is_deleted  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ(1=削除)',

  created_by  BIGINT UNSIGNED NOT NULL COMMENT '作成者(users.id)',
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by  BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(users.id)',
  updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tenants_user_tenant (user_id, tenant_code),
  KEY idx_user_tenants_tenant_code (tenant_code),
  KEY idx_user_tenants_is_deleted (is_deleted),
  KEY idx_user_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;