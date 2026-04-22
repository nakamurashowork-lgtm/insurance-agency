CREATE TABLE IF NOT EXISTS m_product_category (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'ID',
  csv_value    VARCHAR(100)     NOT NULL COMMENT 'CSV種目種類値（SJ-NET出力値）',
  name         VARCHAR(100)     NOT NULL COMMENT '表示名（t_contract.product_type に格納される値 兼 画面表示。自動車・火災・積立等）',
  is_active    TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=プルダウン表示 / 0=非表示)',
  created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_category_csv_value (csv_value),
  KEY idx_product_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='種目マスタ（CSV種目種類→表示名マッピング。name は重複可（多対一）、csv_value のみ一意）';
