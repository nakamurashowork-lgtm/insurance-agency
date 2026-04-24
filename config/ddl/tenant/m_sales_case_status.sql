SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS m_sales_case_status (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT 'ID',
  name          VARCHAR(50)      NOT NULL                COMMENT '表示名（t_sales_case.status に格納する値 兼 画面表示ラベル）',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '表示順',
  is_active     TINYINT(1)       NOT NULL DEFAULT 1      COMMENT '有効フラグ(1=プルダウンに表示 / 0=非表示)',
  is_completed  TINYINT(1)       NOT NULL DEFAULT 0      COMMENT '完了扱いフラグ(1=完了として集計から除外)',
  is_protected  TINYINT(1)       NOT NULL DEFAULT 0      COMMENT '保護フラグ(1=削除・無効化不可)',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_case_status_name (name)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='見込案件ステータスマスタ（表示名=DB格納値。プルダウンの中身を自由に管理）';

-- シードデータは config/dml/master/ に分離（DDL はスキーマ定義のみ）
