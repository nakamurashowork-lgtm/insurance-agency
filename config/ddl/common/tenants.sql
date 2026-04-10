CREATE TABLE IF NOT EXISTS tenants (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  tenant_code   VARCHAR(10)     NOT NULL COMMENT 'テナントコード(例: TE001)',
  tenant_name   VARCHAR(100)    NOT NULL COMMENT 'テナント名',
  db_name       VARCHAR(100)    NOT NULL COMMENT 'テナントDB名(例: sonpo_tenant_TE001)',
  status        TINYINT         NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効,0=無効)',
  is_deleted    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ(1=削除)',

  created_by    BIGINT UNSIGNED NOT NULL COMMENT '作成者(users.id)',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by    BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(users.id)',
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_tenants_tenant_code (tenant_code),
  UNIQUE KEY uq_tenants_db_name (db_name),
  KEY idx_tenants_status (status),
  KEY idx_tenants_is_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント';
