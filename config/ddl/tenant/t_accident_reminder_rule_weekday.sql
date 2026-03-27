CREATE TABLE IF NOT EXISTS t_accident_reminder_rule_weekday (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '事故リマインドルール曜日ID',
  accident_reminder_rule_id BIGINT UNSIGNED NOT NULL COMMENT '事故リマインドルールID(t_accident_reminder_rule.id)',
  weekday_cd          TINYINT UNSIGNED NOT NULL COMMENT '曜日コード(0-6)',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_t_accident_reminder_rule_weekday_01 (accident_reminder_rule_id, weekday_cd),
  KEY idx_t_accident_reminder_rule_weekday_01 (accident_reminder_rule_id),
  CONSTRAINT fk_t_accident_reminder_rule_weekday_01
    FOREIGN KEY (accident_reminder_rule_id) REFERENCES t_accident_reminder_rule(id),
  CONSTRAINT chk_t_accident_reminder_rule_weekday_01 CHECK (weekday_cd BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='事故リマインドルール曜日';