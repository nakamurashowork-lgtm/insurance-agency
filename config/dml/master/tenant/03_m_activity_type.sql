-- =====================================================================
-- マスターデータ: m_activity_type（活動種別マスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_activity.activity_type に格納する表示名候補（name=DB格納値）。
--         設定画面で自由に追加/編集可能。重複名は UNIQUE で拒否。
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_activity_type (name, display_order, is_active) VALUES
  ('訪問',       1,  1),
  ('電話',       2,  1),
  ('メール',     3,  1),
  ('オンライン', 4,  1),
  ('会議',       5,  1),
  ('研修',       6,  1),
  ('その他',     99, 1)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order);
