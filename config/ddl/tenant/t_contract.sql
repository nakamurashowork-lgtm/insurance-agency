CREATE TABLE IF NOT EXISTS t_contract (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '契約ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '契約者顧客ID(m_customer.id)',
  insured_customer_id BIGINT UNSIGNED NULL COMMENT '被保険者顧客ID(m_customer.id)',
  policy_no           VARCHAR(50)     NOT NULL COMMENT '証券番号',
  insurer_name        VARCHAR(100)    NOT NULL COMMENT '保険会社名',
  insurance_category  VARCHAR(50)     NULL COMMENT '保険種類',
  product_type        VARCHAR(50)     NULL COMMENT '種目',
  policy_start_date   DATE            NULL COMMENT '始期日',
  policy_end_date     DATE            NULL COMMENT '終期日',
  premium_amount      DECIMAL(12,0)   NOT NULL DEFAULT 0 COMMENT '保険料',
  payment_cycle       VARCHAR(20)     NULL COMMENT '払込区分',
  status              VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT '状態(active/renewal_pending/expired/cancelled/inactive)',
  sales_staff_id      BIGINT UNSIGNED NULL COMMENT '営業担当(m_staff.id)',
  office_staff_id     BIGINT UNSIGNED NULL COMMENT '事務担当(m_staff.id)',
  last_sjnet_imported_at DATETIME     NULL COMMENT '最終SJNET取込日時',
  remark              TEXT            NULL COMMENT '備考',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_t_contract_02 (policy_no, policy_end_date),
  KEY idx_t_contract_01 (customer_id),
  KEY idx_t_contract_02 (insured_customer_id),
  KEY idx_t_contract_03 (policy_end_date),
  KEY idx_t_contract_04 (status),
  KEY idx_t_contract_05 (sales_staff_id),
  KEY idx_t_contract_06 (is_deleted),
  CONSTRAINT fk_t_contract_01
    FOREIGN KEY (customer_id) REFERENCES m_customer(id),
  CONSTRAINT fk_t_contract_02
    FOREIGN KEY (insured_customer_id) REFERENCES m_customer(id),
  CONSTRAINT chk_t_contract_01 CHECK (status IN ('active', 'renewal_pending', 'expired', 'cancelled', 'inactive')),
  CONSTRAINT chk_t_contract_02 CHECK (premium_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='契約';