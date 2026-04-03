CREATE TABLE IF NOT EXISTS m_staff_sjnet_mapping (
  id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'ID',
  sjnet_code       VARCHAR(20)      NOT NULL COMMENT 'SJNETコード（代理店コード: 満期一覧CSV列44）',
  staff_name       VARCHAR(100)     NOT NULL COMMENT 'SJNETでの担当者名（表示用参考）',
  user_id          BIGINT UNSIGNED  NULL     COMMENT '紐づけ先ユーザーID(common.users.id)。NULLは未紐づけ',
  is_active        TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効/0=無効)',
  note             VARCHAR(255)     NULL     COMMENT '備考',
  created_by       BIGINT UNSIGNED  NOT NULL COMMENT '作成者(common.users.id)',
  created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by       BIGINT UNSIGNED  NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_sjnet_code (sjnet_code),
  KEY idx_m_staff_sjnet_mapping_01 (user_id),
  KEY idx_m_staff_sjnet_mapping_02 (is_active)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='担当者マスタ（SJNETコード↔ユーザーマッピング）';
