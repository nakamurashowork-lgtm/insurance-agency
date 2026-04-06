CREATE TABLE m_staff (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_name    VARCHAR(100) NOT NULL,
  is_sales      TINYINT(1)  NOT NULL DEFAULT 1,
  is_office     TINYINT(1)  NOT NULL DEFAULT 0,
  user_id       BIGINT UNSIGNED NULL,
  sjnet_code    VARCHAR(20)  NULL,
  is_active     TINYINT(1)  NOT NULL DEFAULT 1,
  sort_order    INT          NOT NULL DEFAULT 0,
  created_by    BIGINT UNSIGNED NOT NULL,
  updated_by    BIGINT UNSIGNED NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_sjnet_code (sjnet_code),
  KEY idx_is_active (is_active),
  KEY idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
