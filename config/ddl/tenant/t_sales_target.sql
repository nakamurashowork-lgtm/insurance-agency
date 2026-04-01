CREATE TABLE IF NOT EXISTS t_sales_target (
  id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT COMMENT '目標ID',

  fiscal_year     SMALLINT UNSIGNED NOT NULL COMMENT '年度(西暦。例: 2025 は令和7年度 2025-04〜2026-03)',
  target_month    TINYINT UNSIGNED  NULL     COMMENT '対象月(1-12。NULL は年度目標)',

  staff_user_id   BIGINT UNSIGNED   NULL     COMMENT '担当者ID(common.users.id。NULL はチーム全体目標)',

  target_type     VARCHAR(20)       NOT NULL COMMENT '目標種別(premium_non_life/premium_life/premium_total/case_count)',
  target_amount   DECIMAL(14,0)     NOT NULL DEFAULT 0 COMMENT '目標値(保険料は円単位/件数は件数)',

  is_deleted      TINYINT(1)        NOT NULL DEFAULT 0 COMMENT '削除フラグ',
  created_by      BIGINT UNSIGNED   NOT NULL COMMENT '作成者(common.users.id)',
  created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by      BIGINT UNSIGNED   NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),

  UNIQUE KEY uq_t_sales_target_01 (fiscal_year, target_month, staff_user_id, target_type),

  KEY idx_t_sales_target_01 (fiscal_year, target_month),
  KEY idx_t_sales_target_02 (staff_user_id),
  KEY idx_t_sales_target_03 (fiscal_year, staff_user_id),
  KEY idx_t_sales_target_04 (is_deleted)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='目標管理（年度・月別・担当者別）';
