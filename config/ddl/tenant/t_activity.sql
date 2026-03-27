CREATE TABLE IF NOT EXISTS t_activity (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '活動履歴ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
  contract_id         BIGINT UNSIGNED NULL COMMENT '契約ID(t_contract.id)',
  renewal_case_id     BIGINT UNSIGNED NULL COMMENT '満期案件ID(t_renewal_case.id)',
  accident_case_id    BIGINT UNSIGNED NULL COMMENT '事故案件ID(t_accident_case.id)',
  activity_at         DATETIME        NOT NULL COMMENT '活動日時',
  activity_type       VARCHAR(30)     NOT NULL COMMENT '活動種別(call/visit/mail/quote_send/reminder/internal/other)',
  channel             VARCHAR(30)     NULL COMMENT 'チャネル(phone/mail/lineworks/visit/other)',
  subject             VARCHAR(200)    NULL COMMENT '件名',
  detail              TEXT            NOT NULL COMMENT '内容',
  outcome             VARCHAR(100)    NULL COMMENT '結果',
  next_action_date    DATE            NULL COMMENT '次回対応日',
  staff_user_id       BIGINT UNSIGNED NULL COMMENT '実施担当者(common.users.id)',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_t_activity_01 (customer_id, activity_at),
  KEY idx_t_activity_02 (contract_id, activity_at),
  KEY idx_t_activity_03 (renewal_case_id, activity_at),
  KEY idx_t_activity_04 (accident_case_id, activity_at),
  KEY idx_t_activity_05 (next_action_date),
  KEY idx_t_activity_06 (staff_user_id),
  KEY idx_t_activity_07 (is_deleted),
  CONSTRAINT fk_t_activity_01
    FOREIGN KEY (customer_id) REFERENCES m_customer(id),
  CONSTRAINT fk_t_activity_02
    FOREIGN KEY (contract_id) REFERENCES t_contract(id),
  CONSTRAINT fk_t_activity_03
    FOREIGN KEY (renewal_case_id) REFERENCES t_renewal_case(id),
  CONSTRAINT fk_t_activity_04
    FOREIGN KEY (accident_case_id) REFERENCES t_accident_case(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活動履歴';