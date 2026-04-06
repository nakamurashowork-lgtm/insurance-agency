-- Migration: Add TOTP columns to users table
-- Applied: 2026-04-04
-- Purpose: TOTP 2FA implementation (Google Authenticator)

ALTER TABLE users
  ADD COLUMN totp_secret      VARCHAR(64)  NULL     COMMENT 'TOTP秘密鍵（base32）。NULL=未設定'
    AFTER last_login_at,
  ADD COLUMN totp_enabled     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'TOTP有効フラグ(1=有効,0=未設定)'
    AFTER totp_secret,
  ADD COLUMN totp_verified_at DATETIME     NULL     COMMENT 'TOTP初回確認日時'
    AFTER totp_enabled;
