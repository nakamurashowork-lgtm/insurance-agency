CREATE TABLE IF NOT EXISTS t_sales_performance (
  id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT '成績ID',

  customer_id         BIGINT UNSIGNED  NULL     COMMENT '顧客ID(m_customer.id)。未登録の場合は NULL。',
  prospect_name       VARCHAR(100)     NULL     COMMENT '未登録顧客名。customer_id が NULL のときに使用。',
  contract_id         BIGINT UNSIGNED  NULL     COMMENT '契約ID(t_contract.id)',
  renewal_case_id     BIGINT UNSIGNED  NULL     COMMENT '満期案件ID(t_renewal_case.id)',

  performance_date    DATE             NOT NULL COMMENT '成績計上日',
  performance_type    VARCHAR(20)      NOT NULL COMMENT '成績区分(new/renewal/addition/change/cancel_deduction)',

  source_type         VARCHAR(20)      NULL     COMMENT '業務区分(non_life/life)',
  policy_no           VARCHAR(50)      NULL     COMMENT '証券番号',
  policy_start_date   DATE             NULL     COMMENT '始期日',
  application_date    DATE             NULL     COMMENT '申込日(主に生保)',

  insurance_category  VARCHAR(50)      NULL     COMMENT '保険種類',
  product_type        VARCHAR(50)      NULL     COMMENT '種目',
  premium_amount      DECIMAL(12,0)    NOT NULL DEFAULT 0 COMMENT '保険料',
  installment_count   TINYINT UNSIGNED NULL     COMMENT '分割回数',
  receipt_no          VARCHAR(50)      NULL     COMMENT '領収証番号',
  settlement_month    VARCHAR(7)       NULL     COMMENT '精算月(YYYY-MM)',

  staff_id            BIGINT UNSIGNED  NULL     COMMENT '担当者(m_staff.id)',
  sales_channel       VARCHAR(20)      NULL     COMMENT '販売チャネル(direct/motor_dealer/agency_referral/customer_referral/group/other)',
  referral_source     VARCHAR(100)     NULL     COMMENT '紹介元名称(ディーラー名・紹介者名等。sales_channel が direct 以外の場合に入力)',
  remark              TEXT             NULL     COMMENT '備考',
  is_deleted          TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED  NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED  NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),

  KEY idx_t_sales_performance_01 (customer_id, performance_date),
  KEY idx_t_sales_performance_02 (contract_id),
  KEY idx_t_sales_performance_03 (renewal_case_id),
  KEY idx_t_sales_performance_04 (staff_id),
  KEY idx_t_sales_performance_05 (settlement_month),
  KEY idx_t_sales_performance_06 (is_deleted),
  KEY idx_t_sales_performance_07 (policy_no),
  KEY idx_t_sales_performance_08 (performance_date),
  KEY idx_t_sales_performance_09 (source_type),
  KEY idx_t_sales_performance_10 (sales_channel),
  KEY idx_t_sales_performance_11 (sales_channel, performance_date)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='成績';
