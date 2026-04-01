CREATE TABLE IF NOT EXISTS m_activity_purpose_type (
  code          VARCHAR(30)      NOT NULL COMMENT '用件区分コード(t_activity.purpose_type に格納する値)',
  label         VARCHAR(50)      NOT NULL COMMENT '表示名',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '表示順',
  is_active     TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効/0=無効)',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

  PRIMARY KEY (code)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='活動用件区分マスタ';

-- シードデータ（Excel日報の実態から導出）
INSERT INTO m_activity_purpose_type
  (code, label, display_order, is_active)
VALUES
  ('renewal',       '満期対応',           1, 1),
  ('new_business',  '新規開拓',           2, 1),
  ('cross_sell',    'クロスセル提案',     3, 1),
  ('accident',      '事故対応',           4, 1),
  ('follow_up',     'フォロー',           5, 1),
  ('admin',         '内務・社内作業',     6, 1),
  ('meeting',       '会議・ミーティング', 7, 1),
  ('training',      '研修・勉強会',       8, 1),
  ('other',         'その他',             99, 1);
