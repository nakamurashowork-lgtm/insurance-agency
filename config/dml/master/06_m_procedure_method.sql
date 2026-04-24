-- =====================================================================
-- マスターデータ: m_procedure_method（手続方法マスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_renewal_case.procedure_method に格納する表示名候補（name=DB格納値）。
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_procedure_method (name, display_order, is_active) VALUES
  ('対面',       1, 1),
  ('対面ナビ',   2, 1),
  ('電話ナビ',   3, 1),
  ('電話募集',   4, 1),
  ('署名・捺印', 5, 1),
  ('ケータイOR', 6, 1),
  ('マイページ', 7, 1)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order),
  is_active     = VALUES(is_active);
