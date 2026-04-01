CREATE TABLE IF NOT EXISTS t_daily_report (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  report_date DATE NOT NULL COMMENT '日報日付',
  staff_user_id BIGINT UNSIGNED NOT NULL COMMENT '担当者ユーザーID',
  comment TEXT NULL COMMENT '日報コメント',
  is_submitted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '提出済フラグ',
  submitted_at DATETIME NULL COMMENT '提出日時',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '論理削除フラグ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (id),
  UNIQUE KEY uq_daily_report_date_staff (report_date, staff_user_id),
  KEY idx_daily_report_staff_user_id (staff_user_id),
  KEY idx_daily_report_report_date (report_date),
  KEY idx_daily_report_is_submitted (is_submitted)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='営業日報';
