CREATE TABLE IF NOT EXISTS t_accident_reminder_rule (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '事故リマインドルールID',
  accident_case_id    BIGINT UNSIGNED NOT NULL COMMENT '事故案件ID(t_accident_case.id)',
  is_enabled          TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  interval_weeks      INT             NOT NULL DEFAULT 1 COMMENT '通知間隔(週)',
  base_date           DATE            NOT NULL COMMENT '週計算の基準日',
  start_date          DATE            NULL COMMENT '通知開始日',
  end_date            DATE            NULL COMMENT '通知終了日',
  last_notified_on    DATE            NULL COMMENT '最終通知日',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_t_accident_reminder_rule_01 (accident_case_id),
  KEY idx_t_accident_reminder_rule_02 (is_enabled),
  KEY idx_t_accident_reminder_rule_03 (is_deleted),
  CONSTRAINT fk_t_accident_reminder_rule_01
    FOREIGN KEY (accident_case_id) REFERENCES t_accident_case(id),
  CONSTRAINT chk_t_accident_reminder_rule_01 CHECK (interval_weeks >= 1),
  CONSTRAINT chk_t_accident_reminder_rule_02 CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='事故リマインドルール';