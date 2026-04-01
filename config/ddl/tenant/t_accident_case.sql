CREATE TABLE IF NOT EXISTS t_accident_case (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '事故案件ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
  contract_id         BIGINT UNSIGNED NULL     COMMENT '契約ID(t_contract.id)',
  accident_no         VARCHAR(50)     NULL     COMMENT '事故管理番号',
  accepted_date       DATE            NOT NULL COMMENT '事故受付日',
  accident_date       DATE            NULL     COMMENT '事故発生日',
  insurance_category  VARCHAR(50)     NULL     COMMENT '保険種類',
  product_type        VARCHAR(50)     NULL     COMMENT '種目',
  accident_type       VARCHAR(50)     NULL     COMMENT '事故区分',
  accident_summary    TEXT            NULL     COMMENT '事故概要',
  accident_location   VARCHAR(255)    NULL     COMMENT '事故場所',
  has_counterparty    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '相手有無',
  status              VARCHAR(20)     NOT NULL DEFAULT 'accepted' COMMENT '状態(accepted/linked/in_progress/waiting_docs/resolved/closed)',
  priority            VARCHAR(20)     NOT NULL DEFAULT 'normal' COMMENT '優先度(low/normal/high/urgent)',
  insurer_claim_no    VARCHAR(100)    NULL     COMMENT '保険会社事故受付番号',
  resolved_date       DATE            NULL     COMMENT '解決日',
  assigned_user_id    BIGINT UNSIGNED NULL     COMMENT '主担当者(common.users.id)',
  remark              TEXT            NULL     COMMENT '備考',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_t_accident_case_01 (customer_id),
  KEY idx_t_accident_case_02 (contract_id),
  KEY idx_t_accident_case_03 (accepted_date),
  KEY idx_t_accident_case_04 (status),
  KEY idx_t_accident_case_05 (assigned_user_id),
  KEY idx_t_accident_case_06 (is_deleted),
  KEY idx_t_accident_case_07 (accident_no),

  CONSTRAINT chk_t_accident_case_01 CHECK (status IN ('accepted', 'linked', 'in_progress', 'waiting_docs', 'resolved', 'closed')),
  CONSTRAINT chk_t_accident_case_02 CHECK (priority IN ('low', 'normal', 'high', 'urgent'))

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='事故案件';
