-- =====================================================================
-- 動作確認用DML: t_audit_event_detail
-- 用途: 監査イベントの変更前後の値詳細
-- 件数: 100件
-- ID範囲: 11001 - 11100
-- 依存: 12_t_audit_event.sql（t_audit_event）
-- 関連DDL: config/ddl/tenant/t_audit_event_detail.sql
-- value_type: STRING/NUMBER/DATE/DATETIME/BOOLEAN/JSON/NULL
-- UNIQUE制約: (audit_event_id, field_key)
-- =====================================================================

SET NAMES utf8mb4;

-- ========== イベント10001: 顧客1001 作成 ==========

-- 11001: customer_name
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11001, 10001, 'customer_name', '顧客名', 'STRING',
  NULL, '株式会社テストコーポレーション', NULL, NULL
);

-- 11002: customer_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11002, 10001, 'customer_type', '顧客種別', 'STRING',
  NULL, 'corporate', NULL, NULL
);

-- 11003: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11003, 10001, 'status', 'ステータス', 'STRING',
  NULL, 'active', NULL, NULL
);

-- ========== イベント10002: 顧客1001 メールアドレス変更 ==========

-- 11004: email
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11004, 10002, 'email', 'メールアドレス', 'STRING',
  'old-info@test-corp.insurance-test.example.jp', 'info@test-corp.insurance-test.example.jp', NULL, NULL
);

-- ========== イベント10004: 顧客1011 作成（見込み）==========

-- 11005: customer_name
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11005, 10004, 'customer_name', '顧客名', 'STRING',
  NULL, '株式会社テスト見込商事', NULL, NULL
);

-- 11006: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11006, 10004, 'status', 'ステータス', 'STRING',
  NULL, 'prospect', NULL, NULL
);

-- ========== イベント10006: 顧客1012 ステータス変更 ==========

-- 11007: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11007, 10006, 'status', 'ステータス', 'STRING',
  'prospect', 'active', NULL, NULL
);

-- ========== イベント10007: 契約2001 作成 ==========

-- 11008: policy_no
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11008, 10007, 'policy_no', '証券番号', 'STRING',
  NULL, 'TC001-2001-AUTO', NULL, NULL
);

-- 11009: premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11009, 10007, 'premium_amount', '保険料', 'NUMBER',
  NULL, '180000', NULL, NULL
);

-- 11010: policy_start_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11010, 10007, 'policy_start_date', '始期日', 'DATE',
  NULL, '2025-05-01', NULL, NULL
);

-- ========== イベント10010: 契約2007 台数変更 ==========

-- 11011: remark
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11011, 10010, 'remark', '備考', 'STRING',
  'フリート10台', 'フリート8台（2台除外）', NULL, NULL
);

-- 11012: premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11012, 10010, 'premium_amount', '保険料', 'NUMBER',
  '650000', '520000', NULL, NULL
);

-- ========== イベント10011: 契約2011 expired更新 ==========

-- 11013: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11013, 10011, 'status', '契約状態', 'STRING',
  'active', 'expired', NULL, NULL
);

-- ========== イベント10013: 満期案件3012 ステータス更新 ==========

-- 11014: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11014, 10013, 'case_status', '案件状態', 'STRING',
  'not_started', 'quote_sent', NULL, NULL
);

-- 11015: last_contact_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11015, 10013, 'last_contact_at', '最終接触日時', 'DATETIME',
  NULL, '2026-04-05 10:00:00', NULL, NULL
);

-- 11016: remark
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11016, 10013, 'remark', '備考', 'STRING',
  NULL, '見積書メール送付済み。確認の返答待ち。', NULL, NULL
);

-- ========== イベント10014: 満期案件3014 更新 ==========

-- 11017: last_contact_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11017, 10014, 'last_contact_at', '最終接触日時', 'DATETIME',
  NULL, '2026-04-07 14:00:00', NULL, NULL
);

-- 11018: remark
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11018, 10014, 'remark', '備考', 'STRING',
  '満期まで8日。早急対応要。', '満期まで8日。早急対応要。4/7訪問にて意向確認済み。SJ手配中。', NULL, NULL
);

-- ========== イベント10015: 満期案件3018 完了処理 ==========

-- 11019: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11019, 10015, 'case_status', '案件状態', 'STRING',
  'waiting_payment', 'completed', NULL, NULL
);

-- 11020: renewal_result
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11020, 10015, 'renewal_result', '更改結果', 'STRING',
  NULL, 'renewed', NULL, NULL
);

-- 11021: actual_premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11021, 10015, 'actual_premium_amount', '確定保険料', 'NUMBER',
  NULL, '125000', NULL, NULL
);

-- 11022: completed_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11022, 10015, 'completed_date', '対応完了日', 'DATE',
  NULL, '2026-03-28', NULL, NULL
);

-- ========== イベント10016: 満期案件3022 失注処理 ==========

-- 11023: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11023, 10016, 'case_status', '案件状態', 'STRING',
  'quote_sent', 'completed', NULL, NULL
);

-- 11024: renewal_result
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11024, 10016, 'renewal_result', '更改結果', 'STRING',
  NULL, 'lost', NULL, NULL
);

-- 11025: lost_reason
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11025, 10016, 'lost_reason', '失注理由', 'STRING',
  NULL, '競合他社の見積が大幅に安かった', NULL, NULL
);

-- ========== イベント10017: 満期案件3028 waiting_payment更新 ==========

-- 11026: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11026, 10017, 'case_status', '案件状態', 'STRING',
  'quote_sent', 'waiting_payment', NULL, NULL
);

-- 11027: expected_premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11027, 10017, 'expected_premium_amount', '見込保険料', 'NUMBER',
  '55000', '58000', NULL, NULL
);

-- ========== イベント10019: 満期案件3004 SJ依頼 ==========

-- 11028: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11028, 10019, 'case_status', '案件状態', 'STRING',
  'not_started', 'sj_requested', NULL, NULL
);

-- 11029: last_contact_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11029, 10019, 'last_contact_at', '最終接触日時', 'DATETIME',
  NULL, '2026-04-05 09:30:00', NULL, NULL
);

-- ========== イベント10020: 満期案件3016 書類準備 ==========

-- 11030: case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11030, 10020, 'case_status', '案件状態', 'STRING',
  'sj_requested', 'doc_prepared', NULL, NULL
);

-- ========== イベント10021: 事故案件4001 作成 ==========

-- 11031: accepted_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11031, 10021, 'accepted_date', '事故受付日', 'DATE',
  NULL, '2026-04-05', NULL, NULL
);

-- 11032: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11032, 10021, 'status', '状態', 'STRING',
  NULL, 'accepted', NULL, NULL
);

-- 11033: priority
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11033, 10021, 'priority', '優先度', 'STRING',
  NULL, 'normal', NULL, NULL
);

-- ========== イベント10022: 事故案件4002 作成（緊急）==========

-- 11034: accepted_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11034, 10022, 'accepted_date', '事故受付日', 'DATE',
  NULL, '2026-04-06', NULL, NULL
);

-- 11035: priority
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11035, 10022, 'priority', '優先度', 'STRING',
  NULL, 'high', NULL, NULL
);

-- 11036: has_counterparty
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11036, 10022, 'has_counterparty', '相手有無', 'BOOLEAN',
  NULL, '1', NULL, NULL
);

-- ========== イベント10024: 事故案件4004 linked更新 ==========

-- 11037: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11037, 10024, 'status', '状態', 'STRING',
  'accepted', 'linked', NULL, NULL
);

-- 11038: insurer_claim_no
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11038, 10024, 'insurer_claim_no', '保険会社事故受付番号', 'STRING',
  NULL, 'TK-2026-03-7890', NULL, NULL
);

-- ========== イベント10025: 事故案件4007 in_progress ==========

-- 11039: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11039, 10025, 'status', '状態', 'STRING',
  'linked', 'in_progress', NULL, NULL
);

-- ========== イベント10026: 事故案件4013 resolved ==========

-- 11040: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11040, 10026, 'status', '状態', 'STRING',
  'waiting_docs', 'resolved', NULL, NULL
);

-- 11041: resolved_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11041, 10026, 'resolved_date', '解決日', 'DATE',
  NULL, '2025-11-20', NULL, NULL
);

-- ========== イベント10027: 事故案件4015 closed ==========

-- 11042: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11042, 10027, 'status', '状態', 'STRING',
  'resolved', 'closed', NULL, NULL
);

-- 11043: remark
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11043, 10027, 'remark', '備考', 'STRING',
  '示談交渉中', '保険金支払完了。対人：250,000円、対物：180,000円。全手続き完了。', NULL, NULL
);

-- ========== イベント10028: 活動8001 作成 ==========

-- 11044: activity_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11044, 10028, 'activity_date', '活動日', 'DATE',
  NULL, '2026-04-07', NULL, NULL
);

-- 11045: activity_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11045, 10028, 'activity_type', '活動種別', 'STRING',
  NULL, 'phone', NULL, NULL
);

-- 11046: content_summary
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11046, 10028, 'content_summary', '内容要約', 'STRING',
  NULL, '満期日が5/1に迫っているため確認の電話。「今年も同条件で継続したい」との意向確認。', NULL, NULL
);

-- ========== イベント10033: 成績7001 作成 ==========

-- 11047: performance_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11047, 10033, 'performance_date', '成績計上日', 'DATE',
  NULL, '2025-04-10', NULL, NULL
);

-- 11048: premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11048, 10033, 'premium_amount', '保険料', 'NUMBER',
  NULL, '75000', NULL, NULL
);

-- 11049: performance_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11049, 10033, 'performance_type', '成績区分', 'STRING',
  NULL, 'renewal', NULL, NULL
);

-- ========== イベント10035: 成績7033 作成 ==========

-- 11050: performance_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11050, 10035, 'performance_date', '成績計上日', 'DATE',
  NULL, '2026-03-28', NULL, NULL
);

-- 11051: premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11051, 10035, 'premium_amount', '保険料', 'NUMBER',
  NULL, '125000', NULL, NULL
);

-- ========== イベント10036: 成績7038 マイナス成績 ==========

-- 11052: premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11052, 10036, 'premium_amount', '保険料', 'NUMBER',
  NULL, '-25000', NULL, NULL
);

-- 11053: performance_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11053, 10036, 'performance_type', '成績区分', 'STRING',
  NULL, 'change', NULL, NULL
);

-- ========== イベント10037-10040: コメント操作 ==========

-- 11054: イベント10037 comment_body
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11054, 10037, 'comment_body', 'コメント本文', 'STRING',
  NULL, '4/7 顧客に電話連絡。満期の件を伝えたところ「今年も続けたい」とのこと。見積もり準備中。', NULL, NULL
);

-- 11055: イベント10037 target_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11055, 10037, 'target_type', '対象種別', 'STRING',
  NULL, 'renewal_case', NULL, NULL
);

-- 11056: イベント10038 comment_body
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11056, 10038, 'comment_body', 'コメント本文', 'STRING',
  NULL, '満期まで残り8日。顧客に電話するも不在。折り返し待ち。至急対応要。', NULL, NULL
);

-- 11057: イベント10039 comment_body
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11057, 10039, 'comment_body', 'コメント本文', 'STRING',
  NULL, '4/5 顧客より事故報告受付。警察への届出済みとのこと。東京海上日動へ連絡して事故受付番号を取得する。', NULL, NULL
);

-- 11058: イベント10040 comment_body
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11058, 10040, 'comment_body', 'コメント本文', 'STRING',
  NULL, '10/31 示談成立・保険金支払い完了。対人250,000円・対物180,000円。顧客への説明・承認取得済み。', NULL, NULL
);

-- ========== イベント10041-10044: SJNET取込ログ詳細 ==========

-- 11059: イベント10041 last_sjnet_imported_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11059, 10041, 'last_sjnet_imported_at', '最終SJNET取込日時', 'DATETIME',
  '2026-03-01 08:00:00', '2026-04-01 08:00:00', NULL, NULL
);

-- 11060: イベント10042 last_sjnet_imported_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11060, 10042, 'last_sjnet_imported_at', '最終SJNET取込日時', 'DATETIME',
  '2026-03-01 08:01:00', '2026-04-01 08:01:00', NULL, NULL
);

-- 11061: イベント10043 premium_amount（フリート保険料更新）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11061, 10043, 'premium_amount', '保険料', 'NUMBER',
  '650000', '520000', NULL, NULL
);

-- 11062: イベント10043 last_sjnet_imported_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11062, 10043, 'last_sjnet_imported_at', '最終SJNET取込日時', 'DATETIME',
  '2026-03-01 08:02:00', '2026-04-01 08:02:00', NULL, NULL
);

-- 11063: イベント10044 last_sjnet_imported_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11063, 10044, 'last_sjnet_imported_at', '最終SJNET取込日時', 'DATETIME',
  '2026-03-01 08:03:00', '2026-04-01 08:03:00', NULL, NULL
);

-- ========== イベント10045-10048: バッチ処理ログ詳細 ==========

-- 11064: イベント10045 リマインド通知送信（last_notified_on更新）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11064, 10045, 'last_notified_on', '最終通知日', 'DATE',
  '2026-03-23', '2026-03-30', NULL, NULL
);

-- 11065: イベント10046 リマインド通知（last_notified_on）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11065, 10046, 'last_notified_on', '最終通知日', 'DATE',
  '2026-03-11', '2026-03-25', NULL, NULL
);

-- 11066: イベント10047 満期アラート（next_action_date更新）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11066, 10047, 'next_action_date', '次回対応日', 'DATE',
  NULL, '2026-04-07', NULL, NULL
);

-- 11067: イベント10048 満期アラート（case_status情報）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11067, 10048, 'next_action_date', '次回対応日', 'DATE',
  NULL, '2026-04-08', NULL, NULL
);

-- ========== イベント10049: 顧客1017 closed更新 ==========

-- 11068: status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11068, 10049, 'status', 'ステータス', 'STRING',
  'active', 'closed', NULL, NULL
);

-- 11069: note
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11069, 10049, 'note', '備考', 'STRING',
  NULL, '2024年に廃業。全契約解約済み。', NULL, NULL
);

-- ========== イベント10050: 顧客1020 更新 ==========

-- 11070: note
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11070, 10050, 'note', '備考', 'STRING',
  NULL, '自動車販売業。フリート契約・展示車保険等複数契約。', NULL, NULL
);

-- ========== 追加の詳細ログ（11071-11100）==========

-- 11071: イベント10003 顧客1002作成 customer_name
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11071, 10003, 'customer_name', '顧客名', 'STRING',
  NULL, 'テスト運輸株式会社', NULL, NULL
);

-- 11072: イベント10003 status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11072, 10003, 'status', 'ステータス', 'STRING',
  NULL, 'active', NULL, NULL
);

-- 11073: イベント10005 顧客1012 customer_name
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11073, 10005, 'customer_name', '顧客名', 'STRING',
  NULL, '高橋 良子', NULL, NULL
);

-- 11074: イベント10005 status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11074, 10005, 'status', 'ステータス', 'STRING',
  NULL, 'prospect', NULL, NULL
);

-- 11075: イベント10008 last_sjnet_imported_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11075, 10008, 'last_sjnet_imported_at', '最終SJNET取込日時', 'DATETIME',
  NULL, '2025-05-01 08:00:00', NULL, NULL
);

-- 11076: イベント10009 契約2007 policy_no
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11076, 10009, 'policy_no', '証券番号', 'STRING',
  NULL, 'TC002-2007-AUTO', NULL, NULL
);

-- 11077: イベント10009 premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11077, 10009, 'premium_amount', '保険料', 'NUMBER',
  NULL, '650000', NULL, NULL
);

-- 11078: イベント10012 満期案件3002 バッチ生成
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11078, 10012, 'maturity_date', '満期日', 'DATE',
  NULL, '2026-05-01', NULL, NULL
);

-- 11079: イベント10012 case_status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11079, 10012, 'case_status', '案件状態', 'STRING',
  NULL, 'not_started', NULL, NULL
);

-- 11080: イベント10018 バッチ next_action_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11080, 10018, 'next_action_date', '次回対応日', 'DATE',
  NULL, '2026-04-10', NULL, NULL
);

-- 11081: イベント10023 事故案件4003 accepted_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11081, 10023, 'accepted_date', '事故受付日', 'DATE',
  NULL, '2026-04-07', NULL, NULL
);

-- 11082: イベント10023 priority (urgent)
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11082, 10023, 'priority', '優先度', 'STRING',
  NULL, 'urgent', NULL, NULL
);

-- 11083: イベント10029 活動8005 activity_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11083, 10029, 'activity_date', '活動日', 'DATE',
  NULL, '2026-03-22', NULL, NULL
);

-- 11084: イベント10029 activity_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11084, 10029, 'activity_type', '活動種別', 'STRING',
  NULL, 'visit', NULL, NULL
);

-- 11085: イベント10030 活動8012 activity_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11085, 10030, 'activity_date', '活動日', 'DATE',
  NULL, '2026-04-07', NULL, NULL
);

-- 11086: イベント10031 活動8024 activity_type
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11086, 10031, 'activity_type', '活動種別', 'STRING',
  NULL, 'phone', NULL, NULL
);

-- 11087: イベント10032 活動8026 content_summary
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11087, 10032, 'content_summary', '内容要約', 'STRING',
  NULL, '現在の損保会社への不満をヒアリング。保険料の高さと対応速度に不満とのこと。', NULL, NULL
);

-- 11088: イベント10034 成績7003 performance_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11088, 10034, 'performance_date', '成績計上日', 'DATE',
  NULL, '2025-04-05', NULL, NULL
);

-- 11089: イベント10034 成績7003 premium_amount
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11089, 10034, 'premium_amount', '保険料', 'NUMBER',
  NULL, '650000', NULL, NULL
);

-- 11090: イベント10034 sales_channel
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11090, 10034, 'sales_channel', '販売チャネル', 'STRING',
  NULL, 'direct', NULL, NULL
);

-- 11091-11100: 追加の変更詳細

-- 11091: イベント10011 contract 2011 remark更新
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11091, 10011, 'remark', '備考', 'STRING',
  NULL, '更改交渉中に失注', NULL, NULL
);

-- 11092: イベント10007 status
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11092, 10007, 'status', '契約状態', 'STRING',
  NULL, 'active', NULL, NULL
);

-- 11093: イベント10015 renewal_method
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11093, 10015, 'renewal_method', '更改方法', 'STRING',
  NULL, 'renewal', NULL, NULL
);

-- 11094: イベント10016 completed_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11094, 10016, 'completed_date', '対応完了日', 'DATE',
  NULL, '2025-08-25', NULL, NULL
);

-- 11095: イベント10025 insurer_claim_no
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11095, 10025, 'insurer_claim_no', '保険会社事故受付番号', 'STRING',
  NULL, 'MS-2025-11-5566', NULL, NULL
);

-- 11096: イベント10026 remark
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11096, 10026, 'remark', '備考', 'STRING',
  '示談交渉中', '示談成立。保険金85,000円支払い完了。相手方も納得済み。案件クローズ。', NULL, NULL
);

-- 11097: イベント10050 email
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11097, 10050, 'email', 'メールアドレス', 'STRING',
  NULL, 'info@test-car.insurance-test.example.jp', NULL, NULL
);

-- 11098: イベント10049 is_deleted（廃業での論理整理）
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11098, 10049, 'is_deleted', '削除フラグ', 'BOOLEAN',
  '0', '0', NULL, NULL
);

-- 11099: イベント10002 updated_at
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11099, 10002, 'updated_at', '最終更新日時', 'DATETIME',
  '2024-04-01 09:00:00', '2025-01-15 14:30:00', NULL, NULL
);

-- 11100: イベント10017 next_action_date
INSERT INTO t_audit_event_detail (
  id, audit_event_id, field_key, field_label, value_type,
  before_value_text, after_value_text, before_value_json, after_value_json
) VALUES (
  11100, 10017, 'next_action_date', '次回対応日', 'DATE',
  '2026-04-08', '2026-04-08', NULL, NULL
);
