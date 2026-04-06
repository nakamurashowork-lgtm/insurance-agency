-- migration: users.display_name 追加 (2026-04-04)
-- 既存レコードは display_name = NULL のまま（name にフォールバック）
ALTER TABLE users
  ADD COLUMN display_name VARCHAR(100) NULL COMMENT '業務上の表示名。NULLの場合はnameにフォールバック'
  AFTER name;
