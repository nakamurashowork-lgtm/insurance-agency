CREATE TABLE IF NOT EXISTS m_renewal_case_status (
  id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'ID',
  code          VARCHAR(50)      NOT NULL COMMENT 'ステータスコード（システム内部値）',
  display_name  VARCHAR(100)     NOT NULL COMMENT '表示名',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '表示順',
  is_active     TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効/0=無効)',
  is_fixed      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '固定フラグ（1=削除・変更不可）',
  created_by    BIGINT UNSIGNED  NOT NULL COMMENT '作成者(common.users.id)',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by    BIGINT UNSIGNED  NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  KEY idx_display_order (display_order)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='更改案件対応状況マスタ';
