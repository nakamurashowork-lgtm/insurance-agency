CREATE TABLE IF NOT EXISTS m_sjnet_staff_mapping (
  id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'ID',

  -- SJNET側の識別子
  sjnet_agency_code   VARCHAR(20)      NOT NULL
    COMMENT 'SJNETの代理店コード(満期一覧列44。例: N8559000)。1担当者に1コードが割り当てられる',
  sjnet_staff_name    VARCHAR(100)     NULL
    COMMENT 'SJNETの担当者名(満期一覧列43)。表示・確認用。マッピングのキーには使用しない',

  -- システム側の対応ユーザー
  user_id             BIGINT UNSIGNED  NOT NULL
    COMMENT '対応するシステムユーザーID(common.users.id)',

  -- 管理
  is_active           TINYINT(1)       NOT NULL DEFAULT 1
    COMMENT '有効フラグ(1=有効/0=無効)。担当者が退職等で無効化する際に使用',
  note                VARCHAR(255)     NULL
    COMMENT '備考(三浦組団体など特殊コードの説明等)',

  created_by          BIGINT UNSIGNED  NOT NULL COMMENT '作成者(common.users.id)',
  created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by          BIGINT UNSIGNED  NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),

  -- 同一テナント内で代理店コードは一意
  UNIQUE KEY uq_m_sjnet_staff_mapping_01 (sjnet_agency_code),

  KEY idx_m_sjnet_staff_mapping_01 (user_id),
  KEY idx_m_sjnet_staff_mapping_02 (is_active)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='SJNETコード↔ユーザーマッピングマスタ';
