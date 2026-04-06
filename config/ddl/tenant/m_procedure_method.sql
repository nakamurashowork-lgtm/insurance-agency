SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS m_procedure_method (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT 'ID',
  label         VARCHAR(50)      NOT NULL                COMMENT '表示名（t_renewal_case.procedure_method に格納する値）',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '表示順',
  is_active     TINYINT(1)       NOT NULL DEFAULT 1      COMMENT '有効フラグ(1=有効/0=無効)',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_procedure_method_label (label)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='手続方法マスタ';

-- シードデータ（既存の固定値から移行）
INSERT INTO m_procedure_method (label, display_order, is_active) VALUES
  ('対面',       1, 1),
  ('対面ナビ',   2, 1),
  ('電話ナビ',   3, 1),
  ('電話募集',   4, 1),
  ('署名・捺印', 5, 1),
  ('ケータイOR', 6, 1),
  ('マイページ', 7, 1)
ON DUPLICATE KEY UPDATE display_order = VALUES(display_order);
