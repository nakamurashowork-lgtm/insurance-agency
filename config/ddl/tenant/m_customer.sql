CREATE TABLE IF NOT EXISTS m_customer (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '顧客ID',
  customer_type       VARCHAR(20)     NOT NULL COMMENT '顧客種別(individual/corporate)',
  customer_name       VARCHAR(200)    NOT NULL COMMENT '顧客名',
  customer_name_kana  VARCHAR(200)    NULL COMMENT '顧客名カナ',
  phone               VARCHAR(30)     NULL COMMENT '電話番号',
  email               VARCHAR(255)    NULL COMMENT 'メールアドレス',
  postal_code         VARCHAR(20)     NULL COMMENT '郵便番号',
  address1            VARCHAR(255)    NULL COMMENT '住所1',
  address2            VARCHAR(255)    NULL COMMENT '住所2',
  status              VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT '状態(prospect/active/inactive/closed)',
  note                TEXT            NULL COMMENT '備考',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_m_customer_01 (customer_name),
  KEY idx_m_customer_02 (status),
  KEY idx_m_customer_03 (is_deleted),
  CONSTRAINT chk_m_customer_01 CHECK (customer_type IN ('individual', 'corporate')),
  CONSTRAINT chk_m_customer_02 CHECK (status IN ('prospect', 'active', 'inactive', 'closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='顧客';