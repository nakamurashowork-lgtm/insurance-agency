CREATE TABLE IF NOT EXISTS t_sales_case (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  customer_id BIGINT UNSIGNED NOT NULL COMMENT '顧客ID',
  contract_id BIGINT UNSIGNED NULL COMMENT '契約ID',
  case_name VARCHAR(200) NOT NULL COMMENT '案件名',
  case_type VARCHAR(50) NOT NULL COMMENT '案件種別',
  product_type VARCHAR(100) NULL COMMENT '商品種別',
  status VARCHAR(50) NOT NULL COMMENT 'ステータス',
  prospect_rank VARCHAR(20) NULL COMMENT '見込み度',
  expected_premium DECIMAL(12,0) NULL COMMENT '想定保険料',
  expected_contract_month VARCHAR(7) NULL COMMENT '契約予定月(YYYY-MM)',
  referral_source VARCHAR(200) NULL COMMENT '紹介元',
  next_action_date DATE NULL COMMENT '次回予定日',
  lost_reason VARCHAR(500) NULL COMMENT '失注理由',
  memo TEXT NULL COMMENT 'メモ',
  staff_user_id BIGINT UNSIGNED NULL COMMENT '担当者ユーザーID',
  is_deleted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '論理削除フラグ',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (id),
  KEY idx_sales_case_customer_id (customer_id),
  KEY idx_sales_case_contract_id (contract_id),
  KEY idx_sales_case_case_type (case_type),
  KEY idx_sales_case_status (status),
  KEY idx_sales_case_next_action_date (next_action_date),
  KEY idx_sales_case_staff_user_id (staff_user_id),
  KEY idx_sales_case_expected_contract_month (expected_contract_month)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='営業案件';
