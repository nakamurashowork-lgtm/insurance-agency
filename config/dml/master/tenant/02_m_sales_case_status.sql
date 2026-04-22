-- =====================================================================
-- マスターデータ: m_sales_case_status（見込案件ステータスマスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_sales_case.status に格納する表示名の候補集合
--        （表示名=DB格納値。設定画面で自由に追加/編集可能。
--         is_completed=1 の名前は「完了扱い」として集計から除外される。
--         is_protected=1 の名前は削除/無効化不可。）
-- 件数  : 9件（標準5 + 実データに合わせた追加4）
-- 依存  : なし
-- 関連DDL: config/ddl/tenant/m_sales_case_status.sql
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_sales_case_status (name, display_order, is_active, is_completed, is_protected) VALUES
  -- 標準5種（旧 code ベースの初期値に相当）
  ('商談中',       1, 1, 0, 1),
  ('交渉中',       2, 1, 0, 1),
  ('成約',         3, 1, 1, 1),
  ('失注',         4, 1, 1, 1),
  ('保留',         5, 1, 0, 1),
  -- 実データに合わせた追加
  ('提案中',       6, 1, 0, 0),
  ('ヒアリング中', 7, 1, 0, 0),
  ('アプローチ中', 8, 1, 0, 0),
  ('見込み',       9, 1, 0, 0)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order),
  is_completed  = VALUES(is_completed),
  is_protected  = VALUES(is_protected);
