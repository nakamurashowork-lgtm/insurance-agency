CREATE TABLE IF NOT EXISTS t_renewal_case (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '満期案件ID',
  contract_id             BIGINT UNSIGNED NOT NULL COMMENT '契約ID(t_contract.id)',
  maturity_date           DATE            NOT NULL COMMENT '満期日',
  early_renewal_deadline  DATE            NULL     COMMENT '早期更改締切日',
  case_status             VARCHAR(20)     NOT NULL DEFAULT 'not_started' COMMENT '案件状態(not_started/sj_requested/doc_prepared/waiting_return/quote_sent/waiting_payment/completed)',
  last_contact_at         DATETIME        NULL     COMMENT '最終接触日時',
  next_action_date        DATE            NULL     COMMENT '次回対応日',
  renewal_result          VARCHAR(20)     NULL     COMMENT '更改結果(renewed/cancelled/lost/pending)',
  lost_reason             VARCHAR(255)    NULL     COMMENT '失注理由',
  expected_premium_amount DECIMAL(12,0)   NULL     COMMENT '見込保険料',
  actual_premium_amount   DECIMAL(12,0)   NULL     COMMENT '確定保険料',
  renewed_contract_id     BIGINT UNSIGNED NULL     COMMENT '更改後契約ID(t_contract.id)',
  assigned_user_id        BIGINT UNSIGNED NULL     COMMENT '営業担当者(common.users.id)',
  office_user_id          BIGINT UNSIGNED NULL     COMMENT '事務担当者(common.users.id)',
  remark                  TEXT            NULL     COMMENT '備考',
  renewal_method          VARCHAR(50)     NULL     COMMENT '更改方法',
  procedure_method        VARCHAR(50)     NULL     COMMENT '手続方法',
  completed_date          DATE            NULL     COMMENT '対応完了日',
  is_deleted              TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by              BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by              BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),

  KEY idx_t_renewal_case_01 (maturity_date),
  KEY idx_t_renewal_case_02 (case_status),
  KEY idx_t_renewal_case_03 (next_action_date),
  KEY idx_t_renewal_case_04 (assigned_user_id),
  KEY idx_t_renewal_case_05 (is_deleted),
  KEY idx_t_renewal_case_06 (office_user_id),
  KEY idx_t_renewal_case_07 (office_user_id, case_status),
  KEY idx_t_renewal_case_08 (contract_id, maturity_date),

  CONSTRAINT fk_t_renewal_case_01
    FOREIGN KEY (contract_id) REFERENCES t_contract(id),
  CONSTRAINT fk_t_renewal_case_02
    FOREIGN KEY (renewed_contract_id) REFERENCES t_contract(id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='満期案件';
