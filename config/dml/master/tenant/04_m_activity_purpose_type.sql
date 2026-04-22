-- =====================================================================
-- マスターデータ: m_activity_purpose_type（活動用件区分マスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_activity.purpose_type に格納する表示名候補（name=DB格納値）。
--         設定画面で自由に追加/編集可能。重複名は UNIQUE で拒否。
-- =====================================================================

SET NAMES utf8mb4;

-- 廃止項目を削除（既存 DB に残っている場合）
DELETE FROM m_activity_purpose_type WHERE name IN (
  'フォロー',
  '内務・社内作業',
  '会議・ミーティング',
  '研修・勉強会'
);

INSERT INTO m_activity_purpose_type (name, display_order, is_active) VALUES
  ('満期対応',       1, 1),
  ('新規開拓',       2, 1),
  ('クロスセル提案', 3, 1),
  ('事故対応',       4, 1),
  ('見積対応',       5, 1),
  ('保全対応',       6, 1),
  ('苦情対応',       7, 1),
  ('その他',         8, 1)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order),
  is_active     = VALUES(is_active);
