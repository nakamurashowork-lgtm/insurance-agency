CREATE TABLE IF NOT EXISTS users (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ユーザーID',
  google_sub       VARCHAR(64)     NULL     COMMENT 'GoogleログインID(sub)',
  email            VARCHAR(255)    NOT NULL COMMENT 'メールアドレス',
  name             VARCHAR(100)    NOT NULL COMMENT 'ユーザー名（Googleアカウント名）',
  display_name     VARCHAR(100)    NULL     COMMENT '業務上の表示名。NULLの場合はnameにフォールバック',
  is_system_admin  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'システム管理者フラグ(1=管理者,0=一般)',
  status           TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ(1=有効,0=無効)',
  is_deleted       TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ(1=削除)',
  last_login_at    DATETIME        NULL COMMENT '最終ログイン日時',

  totp_secret      VARCHAR(64)     NULL     COMMENT 'TOTP秘密鍵（base32）。NULL=未設定',
  totp_enabled     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'TOTP有効フラグ(1=有効,0=未設定)',
  totp_verified_at DATETIME        NULL     COMMENT 'TOTP初回確認日時',

  created_by       BIGINT UNSIGNED NULL COMMENT '作成者(users.id)',
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  updated_by       BIGINT UNSIGNED NULL COMMENT '最終更新者(users.id)',
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_users_google_sub (google_sub),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_is_system_admin (is_system_admin),
  KEY idx_users_status (status),
  KEY idx_users_is_deleted (is_deleted),
  KEY idx_users_last_login_at (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ユーザー';