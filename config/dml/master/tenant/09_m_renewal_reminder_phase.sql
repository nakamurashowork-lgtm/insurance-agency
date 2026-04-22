-- =====================================================================
-- マスターデータ: m_renewal_reminder_phase（満期通知フェーズ）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : 満期リマインド通知の発火タイミング定義。
--        バッチは from_days_before = to_days_before の行のみを
--        トリガーとして使用する。
--        初期投入時は from/to が範囲値（from≠to）のため発火しない。
--        テナント設定画面で日数を選択・保存することで単日トリガーに
--        書き換えられ、バッチが発火するようになる。
-- 冪等  : phase_code (UNIQUE) で重複検知。
--        from_days_before / to_days_before / is_enabled は
--        テナントが UI で設定した値を保持するため ON DUPLICATE では更新しない。
-- 依存  : なし
-- 関連DDL: config/ddl/tenant/m_renewal_reminder_phase.sql
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_renewal_reminder_phase
  (phase_code, phase_name, from_days_before, to_days_before,
   is_enabled, display_order, created_by, updated_by)
VALUES
  ('EARLY',  '早期通知', 90, 61, 1, 1, 1, 1),
  ('URGENT', '直前通知', 30,  0, 1, 3, 1, 1)
ON DUPLICATE KEY UPDATE
  phase_name    = VALUES(phase_name),
  display_order = VALUES(display_order),
  updated_by    = VALUES(updated_by);
