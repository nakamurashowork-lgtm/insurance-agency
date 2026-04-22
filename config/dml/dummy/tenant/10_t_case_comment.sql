-- =====================================================================
-- ダミーデータ: t_case_comment（案件コメント）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : 満期案件・事故案件のコメント機能（時系列スレッド）の確認
-- 件数  : 12件（満期案件 7件 / 事故案件 5件）
-- ID範囲: 6001 - 6012
-- 依存  : 04_t_renewal_case.sql, 05_t_accident_case.sql
-- 関連DDL: config/ddl/tenant/t_case_comment.sql
-- =====================================================================
-- target_type + _id の排他制約に注意:
--   renewal_case : target_type='renewal_case',  renewal_case_id NOT NULL
--   accident_case: target_type='accident_case', accident_case_id NOT NULL
-- =====================================================================

SET NAMES utf8mb4;

-- 満期案件 3001（未対応 フリート）のコメントスレッド
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6001, 'renewal_case', 3001, NULL,
 '至急対応案件。昨年の条件ベースで試算しておきます。', 0,
 1, '2026-04-18 09:00:00', 1, '2026-04-18 09:00:00'),
(6002, 'renewal_case', 3001, NULL,
 '車両2台増車予定との事前情報あり。見積に反映。', 0,
 1, '2026-04-19 10:30:00', 1, '2026-04-19 10:30:00'),
(6003, 'renewal_case', 3001, NULL,
 '4/20 訪問にて総務課と一次確認済み。見積3パターン送付予定。', 0,
 1, '2026-04-20 16:00:00', 1, '2026-04-20 16:00:00');

-- 満期案件 3004（SJ依頼中）のコメント
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6004, 'renewal_case', 3004, NULL,
 'SJ から回答あり。条件通りで問題なし。提案書作成開始。', 0,
 1, '2026-04-11 14:00:00', 1, '2026-04-11 14:00:00'),
(6005, 'renewal_case', 3004, NULL,
 '提案書ドラフト完了。上長確認中。', 0,
 3, '2026-04-15 11:00:00', 3, '2026-04-15 11:00:00');

-- 満期案件 3010（入金待ち 労災）のコメント
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6006, 'renewal_case', 3010, NULL,
 '契約書記名押印済み受領。請求書を本日送付。', 0,
 1, '2026-04-01 11:00:00', 1, '2026-04-01 11:00:00'),
(6007, 'renewal_case', 3010, NULL,
 '4/25 入金予定との連絡あり。予定通り。', 0,
 1, '2026-04-18 10:30:00', 1, '2026-04-18 10:30:00');

-- 事故案件 4002（当て逃げ 高優先）のコメント
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6008, 'accident_case', NULL, 4002,
 '警察届出番号確認済み。SJ 事故受付番号取得待ち。', 0,
 1, '2026-04-19 17:00:00', 1, '2026-04-19 17:00:00'),
(6009, 'accident_case', NULL, 4002,
 '被害写真受領。損害額の概算出せる見込み。', 0,
 1, '2026-04-20 09:30:00', 1, '2026-04-20 09:30:00');

-- 事故案件 4004（対人示談 高優先）のコメント
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6010, 'accident_case', NULL, 4004,
 '相手方治療経過の報告書受領。', 0,
 2, '2026-04-05 10:00:00', 2, '2026-04-05 10:00:00'),
(6011, 'accident_case', NULL, 4004,
 '過失割合 8:2 で合意の方向。示談書ドラフト作成中。', 0,
 2, '2026-04-18 14:30:00', 2, '2026-04-18 14:30:00');

-- 事故案件 4007（水濡れ 書類待ち）のコメント
INSERT INTO t_case_comment (id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, created_at, updated_by, updated_at) VALUES
(6012, 'accident_case', NULL, 4007,
 '修繕業者より見積書ドラフト受領。損害見積書は4/30 着予定。', 0,
 2, '2026-04-15 13:00:00', 2, '2026-04-15 13:00:00');
