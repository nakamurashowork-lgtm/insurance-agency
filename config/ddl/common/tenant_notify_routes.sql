CREATE TABLE IF NOT EXISTS tenant_notify_routes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  tenant_code       VARCHAR(20)     NOT NULL COMMENT 'テナントコード(tenants.tenant_code)',
  notification_type ENUM('renewal','accident') NOT NULL COMMENT '通知種別',
  destination_id    BIGINT UNSIGNED NOT NULL COMMENT '通知先ID(tenant_notify_targets.id)',
  is_enabled        TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  is_deleted        TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by        BIGINT UNSIGNED NOT NULL COMMENT '作成者(users.id)',
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by        BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(users.id)',
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_tnr_01 (tenant_code, notification_type),
  KEY idx_tnr_01 (destination_id),
  KEY idx_tnr_02 (is_enabled),
  KEY idx_tnr_03 (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント通知振り分け';
