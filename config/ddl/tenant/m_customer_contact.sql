CREATE TABLE IF NOT EXISTS m_customer_contact (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '顧客連絡先ID',
  customer_id         BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
  contact_name        VARCHAR(100)    NOT NULL COMMENT '連絡先氏名',
  department          VARCHAR(100)    NULL     COMMENT '部署',
  position_name       VARCHAR(100)    NULL     COMMENT '役職',
  phone               VARCHAR(30)     NULL     COMMENT '電話番号',
  email               VARCHAR(255)    NULL     COMMENT 'メールアドレス',
  is_primary          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '主連絡先フラグ',
  sort_order          INT             NOT NULL DEFAULT 1 COMMENT '表示順',
  note                TEXT            NULL     COMMENT '備考',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  -- 生成カラム: is_primary=1 の時のみ 1 を返し、0 の時は NULL を返す。
  -- MySQL の UNIQUE KEY は NULL 同士を別値として扱うため、
  -- (customer_id, primary_guard) の UNIQUE KEY により
  -- 1顧客に対して is_primary=1 のレコードを1件のみ許容する制約をDB側で保証する。
  primary_guard       TINYINT AS (IF(is_primary = 1, 1, NULL)) STORED COMMENT '主連絡先一意ガード(生成カラム)',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_m_customer_contact_primary (customer_id, primary_guard),
  KEY idx_m_customer_contact_01 (customer_id),
  KEY idx_m_customer_contact_02 (customer_id, is_primary),
  KEY idx_m_customer_contact_03 (is_deleted),
  CONSTRAINT fk_m_customer_contact_01
    FOREIGN KEY (customer_id) REFERENCES m_customer(id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='顧客連絡先';
