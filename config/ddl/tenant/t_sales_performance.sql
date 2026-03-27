CREATE TABLE IF NOT EXISTS t_sales_performance (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '実績ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
  contract_id         BIGINT UNSIGNED NULL COMMENT '契約ID(t_contract.id)',
  renewal_case_id     BIGINT UNSIGNED NULL COMMENT '満期案件ID(t_renewal_case.id)',
  performance_date    DATE            NOT NULL COMMENT '実績計上日',
  performance_type    VARCHAR(20)     NOT NULL COMMENT '実績区分(new/renewal/addition/change/cancel_deduction)',
  insurance_category  VARCHAR(50)     NULL COMMENT '保険種類',
  product_type        VARCHAR(50)     NULL COMMENT '種目',
  premium_amount      DECIMAL(12,0)   NOT NULL DEFAULT 0 COMMENT '保険料',
  receipt_no          VARCHAR(50)     NULL COMMENT '領収証番号',
  settlement_month    VARCHAR(7)      NULL COMMENT '精算月(YYYY-MM)',
  staff_user_id       BIGINT UNSIGNED NULL COMMENT '担当者(common.users.id)',
  remark              TEXT            NULL COMMENT '備考',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_t_sales_performance_01 (customer_id, performance_date),
  KEY idx_t_sales_performance_02 (contract_id),
  KEY idx_t_sales_performance_03 (renewal_case_id),
  KEY idx_t_sales_performance_04 (staff_user_id),
  KEY idx_t_sales_performance_05 (settlement_month),
  KEY idx_t_sales_performance_06 (is_deleted),
  CONSTRAINT fk_t_sales_performance_01
    FOREIGN KEY (customer_id) REFERENCES m_customer(id),
  CONSTRAINT fk_t_sales_performance_02
    FOREIGN KEY (contract_id) REFERENCES t_contract(id),
  CONSTRAINT fk_t_sales_performance_03
    FOREIGN KEY (renewal_case_id) REFERENCES t_renewal_case(id),
  CONSTRAINT chk_t_sales_performance_01 CHECK (performance_type IN ('new', 'renewal', 'addition', 'change', 'cancel_deduction')),
  CONSTRAINT chk_t_sales_performance_02 CHECK (premium_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='実績';