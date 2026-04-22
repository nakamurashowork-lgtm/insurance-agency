-- =====================================================================
-- マスターデータ: m_renewal_method（更改方法マスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_renewal_case.renewal_method に格納する表示名候補（name=DB格納値）。
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_renewal_method (name, display_order, is_active) VALUES
  ('対面',     1, 1),
  ('郵送',     2, 1),
  ('電話募集', 3, 1)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order),
  is_active     = VALUES(is_active);
