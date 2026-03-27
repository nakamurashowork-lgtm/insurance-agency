CREATE TABLE IF NOT EXISTS m_renewal_reminder_phase (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '満期通知フェーズID',
  phase_code          VARCHAR(20)     NOT NULL COMMENT 'フェーズコード(EARLY/URGENT/CUSTOM)',
  phase_name          VARCHAR(50)     NOT NULL COMMENT 'フェーズ名',
  from_days_before    INT             NOT NULL COMMENT '開始残日数',
  to_days_before      INT             NOT NULL COMMENT '終了残日数',
  is_enabled          TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  display_order       INT             NOT NULL COMMENT '表示順',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_m_renewal_reminder_phase_01 (phase_code),
  UNIQUE KEY uq_m_renewal_reminder_phase_02 (display_order),
  KEY idx_m_renewal_reminder_phase_01 (is_enabled),
  KEY idx_m_renewal_reminder_phase_02 (is_deleted),
  CONSTRAINT chk_m_renewal_reminder_phase_01 CHECK (from_days_before >= to_days_before),
  CONSTRAINT chk_m_renewal_reminder_phase_02 CHECK (to_days_before >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='満期通知フェーズ設定';