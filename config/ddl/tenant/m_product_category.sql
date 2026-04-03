CREATE TABLE IF NOT EXISTS m_product_category (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'ID',
  csv_value    VARCHAR(100)     NOT NULL COMMENT 'CSV種目種類値（SJ-NET出力値）',
  display_name VARCHAR(100)     NOT NULL COMMENT '表示名（自動車・火災・積立等）',
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_csv_value (csv_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='種目マスタ（CSV種目種類→表示名マッピング）';
