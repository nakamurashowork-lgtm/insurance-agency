CREATE TABLE IF NOT EXISTS t_notification_delivery (
  id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '通知実績ID',
  notification_run_id       BIGINT UNSIGNED NOT NULL COMMENT '通知実行ID(t_notification_run.id)',
  notification_type         VARCHAR(20)     NOT NULL COMMENT '通知種別(renewal/accident)',
  renewal_case_id           BIGINT UNSIGNED NULL COMMENT '満期案件ID(t_renewal_case.id)',
  accident_case_id          BIGINT UNSIGNED NULL COMMENT '事故案件ID(t_accident_case.id)',
  renewal_reminder_phase_id BIGINT UNSIGNED NULL COMMENT '満期通知フェーズID(m_renewal_reminder_phase.id)',
  accident_reminder_rule_id BIGINT UNSIGNED NULL COMMENT '事故リマインドルールID(t_accident_reminder_rule.id)',
  scheduled_date            DATE            NOT NULL COMMENT '通知対象日',
  notified_at               DATETIME        NULL COMMENT '通知実行日時',
  delivery_status           VARCHAR(20)     NOT NULL COMMENT '通知結果(success/failed/skipped)',
  error_message             VARCHAR(1000)   NULL COMMENT 'エラーメッセージ',
  created_at                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_t_notification_delivery_01 (renewal_case_id, renewal_reminder_phase_id, scheduled_date),
  UNIQUE KEY uq_t_notification_delivery_02 (accident_case_id, accident_reminder_rule_id, scheduled_date),
  KEY idx_t_notification_delivery_01 (notification_run_id),
  KEY idx_t_notification_delivery_02 (scheduled_date),
  KEY idx_t_notification_delivery_03 (delivery_status),
  CONSTRAINT chk_t_notification_delivery_01 CHECK (notification_type IN ('renewal', 'accident')),
  CONSTRAINT chk_t_notification_delivery_02 CHECK (delivery_status IN ('success', 'failed', 'skipped')),
  CONSTRAINT chk_t_notification_delivery_03 CHECK (
    (notification_type = 'renewal'
      AND renewal_case_id IS NOT NULL
      AND renewal_reminder_phase_id IS NOT NULL
      AND accident_case_id IS NULL
      AND accident_reminder_rule_id IS NULL)
    OR
    (notification_type = 'accident'
      AND accident_case_id IS NOT NULL
      AND accident_reminder_rule_id IS NOT NULL
      AND renewal_case_id IS NULL
      AND renewal_reminder_phase_id IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='個別通知実績';
