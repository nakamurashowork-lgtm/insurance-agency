-- =====================================================================
-- マスターデータ: m_case_status（対応状況マスタ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : t_renewal_case.case_status / t_accident_case.status に格納する
--        表示名の候補集合（表示名=DB格納値）。
--        is_completed=1 は「完了扱い」としてダッシュボード集計・通知バッチ・
--        リマインダー処理から除外される。
--        is_protected=1 は削除・無効化不可。
-- 依存  : なし
-- 関連DDL: config/ddl/tenant/m_case_status.sql
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_case_status
  (case_type, name, display_order, is_active, is_completed, is_protected, created_by, updated_by)
VALUES
  -- 満期案件（renewal）
  ('renewal',  '未対応',           10, 1, 0, 1, 1, 1),
  ('renewal',  'SJ依頼中',         20, 1, 0, 0, 1, 1),
  ('renewal',  '書類作成済',       30, 1, 0, 0, 1, 1),
  ('renewal',  '返送待ち',         40, 1, 0, 0, 1, 1),
  ('renewal',  '見積送付済',       50, 1, 0, 0, 1, 1),
  ('renewal',  '入金待ち',         60, 1, 0, 0, 1, 1),
  ('renewal',  '完了',             70, 1, 1, 1, 1, 1),
  ('renewal',  '取り下げ',         75, 1, 1, 1, 1, 1),
  ('renewal',  '失注',             80, 1, 1, 0, 1, 1),
  ('renewal',  '解約',             85, 1, 1, 0, 1, 1),
  -- 事故案件（accident）
  ('accident', '受付',             10, 1, 0, 1, 1, 1),
  ('accident', '保険会社連絡済み', 20, 1, 0, 0, 1, 1),
  ('accident', '対応中',           30, 1, 0, 0, 1, 1),
  ('accident', '書類待ち',         40, 1, 0, 0, 1, 1),
  ('accident', '解決済み',         50, 1, 0, 0, 1, 1),
  ('accident', '完了',             60, 1, 1, 1, 1, 1)
ON DUPLICATE KEY UPDATE
  display_order = VALUES(display_order),
  is_completed  = VALUES(is_completed),
  is_protected  = VALUES(is_protected);
