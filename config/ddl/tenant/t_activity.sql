CREATE TABLE IF NOT EXISTS t_activity (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
  contract_id         BIGINT UNSIGNED NULL     COMMENT '契約ID(t_contract.id)',
  renewal_case_id     BIGINT UNSIGNED NULL     COMMENT '満期案件ID(t_renewal_case.id)',
  accident_case_id    BIGINT UNSIGNED NULL     COMMENT '事故案件ID(t_accident_case.id)',
  sales_case_id       BIGINT UNSIGNED NULL     COMMENT '営業案件ID(t_sales_case.id)',
  activity_date       DATE            NOT NULL COMMENT '活動日',
  start_time          TIME            NULL     COMMENT '開始時刻',
  end_time            TIME            NULL     COMMENT '終了時刻',
  activity_type       VARCHAR(50)     NOT NULL COMMENT '活動種別',
  purpose_type        VARCHAR(50)     NULL     COMMENT '用件区分',
  visit_place         VARCHAR(200)    NULL     COMMENT '訪問先',
  interviewee_name    VARCHAR(100)    NULL     COMMENT '面談者',
  subject             VARCHAR(200)    NULL     COMMENT '件名',
  content_summary     VARCHAR(500)    NOT NULL COMMENT '内容要約',
  detail_text         TEXT            NULL     COMMENT '詳細内容',
  next_action_date    DATE            NULL     COMMENT '次回予定日',
  next_action_note    VARCHAR(500)    NULL     COMMENT '次回アクション',
  result_type         VARCHAR(50)     NULL     COMMENT '結果区分',
  staff_user_id       BIGINT UNSIGNED NULL     COMMENT '担当者ユーザーID(common.users.id)',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '論理削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  KEY idx_t_activity_01 (customer_id),
  KEY idx_t_activity_02 (contract_id),
  KEY idx_t_activity_03 (renewal_case_id),
  KEY idx_t_activity_04 (accident_case_id),
  KEY idx_t_activity_05 (sales_case_id),
  KEY idx_t_activity_06 (activity_date),
  KEY idx_t_activity_07 (activity_type),
  KEY idx_t_activity_08 (purpose_type),
  KEY idx_t_activity_09 (next_action_date),
  KEY idx_t_activity_10 (staff_user_id),
  KEY idx_t_activity_11 (customer_id, activity_date),
  KEY idx_t_activity_12 (staff_user_id, activity_date),
  KEY idx_t_activity_13 (is_deleted, activity_date)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='活動履歴';
