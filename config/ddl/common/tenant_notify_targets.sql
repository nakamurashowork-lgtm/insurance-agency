CREATE TABLE IF NOT EXISTS tenant_notify_targets (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  tenant_code   VARCHAR(20)     NOT NULL COMMENT 'テナントコード(tenants.tenant_code)',
  provider_type VARCHAR(20)     NOT NULL COMMENT '通知プロバイダ(lineworks/slack/teams/google_chat)',
  destination_name VARCHAR(100) NOT NULL COMMENT '通知先名',
  webhook_url   VARCHAR(2000)   NOT NULL COMMENT 'Webhook URL',
  is_enabled    TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  is_deleted    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by    BIGINT UNSIGNED NOT NULL COMMENT '作成者(users.id)',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by    BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(users.id)',
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_tnd_01 (tenant_code, destination_name),
  KEY idx_tnd_01 (tenant_code),
  KEY idx_tnd_02 (provider_type),
  KEY idx_tnd_03 (is_enabled),
  KEY idx_tnd_04 (is_deleted),
  CONSTRAINT chk_tnd_01
    CHECK (provider_type IN ('lineworks', 'slack', 'teams', 'google_chat'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント通知先';
