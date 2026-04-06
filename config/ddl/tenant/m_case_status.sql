CREATE TABLE IF NOT EXISTS m_case_status (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_type     VARCHAR(20)     NOT NULL COMMENT 'renewal / accident',
  code          VARCHAR(50)     NOT NULL COMMENT 'ステータスコード',
  display_name  VARCHAR(100)    NOT NULL COMMENT '表示名',
  display_order INT             NOT NULL DEFAULT 0,
  is_system     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1=システム固定（削除不可）',
  is_active     TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効/0=無効)',
  created_by    BIGINT UNSIGNED NOT NULL,
  updated_by    BIGINT UNSIGNED NOT NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_m_case_status_01 (case_type, code),
  KEY idx_m_case_status_01 (case_type, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='対応状況マスタ';
