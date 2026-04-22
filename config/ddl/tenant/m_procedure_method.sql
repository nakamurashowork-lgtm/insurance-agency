SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS m_procedure_method (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT 'ID',
  name          VARCHAR(50)      NOT NULL                COMMENT '表示名（t_renewal_case.procedure_method に格納する値）',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '表示順',
  is_active     TINYINT(1)       NOT NULL DEFAULT 1      COMMENT '有効フラグ(1=プルダウン表示 / 0=非表示)',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_procedure_method_name (name)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='手続方法マスタ（表示名=DB格納値）';
