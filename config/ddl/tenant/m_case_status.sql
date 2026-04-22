CREATE TABLE IF NOT EXISTS m_case_status (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_type     VARCHAR(20)     NOT NULL COMMENT 'renewal / accident',
  name          VARCHAR(50)     NOT NULL COMMENT '表示名（t_*.case_status / status に格納する値 兼 画面ラベル）',
  display_order INT             NOT NULL DEFAULT 0,
  is_active     TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=プルダウン表示 / 0=非表示)',
  is_completed  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '完了扱い(1=集計から除外・リマインダー停止)',
  is_protected  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '保護フラグ(1=削除・無効化不可)',
  created_by    BIGINT UNSIGNED NOT NULL,
  updated_by    BIGINT UNSIGNED NOT NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_m_case_status_01 (case_type, name),
  KEY idx_m_case_status_01 (case_type, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='対応状況マスタ（表示名=DB格納値。プルダウンの中身を自由に管理）';
