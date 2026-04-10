-- m_case_status 初期データ（ローカル開発環境用）
-- is_system=1: システム固定（設定画面から削除不可）
SET NAMES utf8mb4;

INSERT INTO m_case_status (case_type, code, display_name, display_order, is_system, created_by, updated_by) VALUES
  ('renewal', 'not_started',    '未対応',       10, 1, 1, 1),
  ('renewal', 'sj_requested',   'SJ依頼中',     20, 1, 1, 1),
  ('renewal', 'doc_prepared',   '書類作成済',   30, 1, 1, 1),
  ('renewal', 'waiting_return', '返送待ち',     40, 1, 1, 1),
  ('renewal', 'quote_sent',     '見積送付済',   50, 1, 1, 1),
  ('renewal', 'waiting_payment','入金待ち',     60, 1, 1, 1),
  ('renewal', 'completed',      '完了',         70, 1, 1, 1),
  ('accident', 'accepted',      '受付',         10, 1, 1, 1),
  ('accident', 'linked',        '保険会社連絡済み', 20, 1, 1, 1),
  ('accident', 'in_progress',   '対応中',       30, 1, 1, 1),
  ('accident', 'waiting_docs',  '書類待ち',     40, 1, 1, 1),
  ('accident', 'resolved',      '解決済み',     50, 1, 1, 1),
  ('accident', 'closed',        '完了',         60, 1, 1, 1)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), display_order = VALUES(display_order);
