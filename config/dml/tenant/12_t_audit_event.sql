-- =====================================================================
-- 動作確認用DML: t_audit_event
-- 用途: 各種操作の監査ログ
-- 件数: 50件
-- ID範囲: 10001 - 10050
-- 依存: 01_m_customer.sql, 03_t_contract.sql, 04_t_renewal_case.sql,
--       05_t_accident_case.sql, 10_t_activity.sql, 09_t_sales_performance.sql,
--       08_t_case_comment.sql
-- 関連DDL: config/ddl/tenant/t_audit_event.sql
-- entity_type: customer/contract/renewal_case/accident_case/activity/sales_performance/comment
-- action_type: INSERT/UPDATE/DELETE/IMPORT/SYSTEM_UPDATE
-- change_source: SCREEN/SJNET_IMPORT/BATCH/API
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 顧客操作ログ ==========

-- 10001: 顧客1001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10001, 'customer', 1001, 'INSERT', 'SCREEN',
  1, '2024-04-01 09:00:00', 'REQ-2024-04-001-001', '顧客新規登録'
);

-- 10002: 顧客1001 更新（メールアドレス変更）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10002, 'customer', 1001, 'UPDATE', 'SCREEN',
  1, '2025-01-15 14:30:00', 'REQ-2025-01-015-002', 'メールアドレス変更'
);

-- 10003: 顧客1002 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10003, 'customer', 1002, 'INSERT', 'SCREEN',
  1, '2024-04-01 09:30:00', 'REQ-2024-04-001-003', '顧客新規登録'
);

-- 10004: 顧客1011 作成（見込み顧客）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10004, 'customer', 1011, 'INSERT', 'SCREEN',
  1, '2026-03-20 14:00:00', 'REQ-2026-03-020-004', '見込み顧客新規登録'
);

-- 10005: 顧客1012 作成（紹介案件）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10005, 'customer', 1012, 'INSERT', 'SCREEN',
  2, '2026-02-05 16:00:00', 'REQ-2026-02-005-005', '紹介案件・顧客新規登録'
);

-- 10006: 顧客1012 ステータス更新（prospect→active、成約時）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10006, 'customer', 1012, 'UPDATE', 'SCREEN',
  2, '2026-02-12 11:00:00', 'REQ-2026-02-012-006', '成約に伴いステータス更新'
);

-- ========== 契約操作ログ ==========

-- 10007: 契約2001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10007, 'contract', 2001, 'INSERT', 'SCREEN',
  1, '2025-04-18 10:00:00', 'REQ-2025-04-018-007', '更改処理・新契約登録'
);

-- 10008: 契約2001 SJNET取込更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10008, 'contract', 2001, 'IMPORT', 'SJNET_IMPORT',
  1, '2025-05-01 08:00:00', NULL, 'SJNET自動取込'
);

-- 10009: 契約2007 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10009, 'contract', 2007, 'INSERT', 'SCREEN',
  1, '2025-04-05 11:00:00', 'REQ-2025-04-005-009', '更改処理・フリート契約'
);

-- 10010: 契約2007 台数変更
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10010, 'contract', 2007, 'UPDATE', 'SCREEN',
  1, '2026-02-01 10:30:00', 'REQ-2026-02-001-010', 'フリート台数変更（10台→8台）'
);

-- 10011: 契約2011 ステータス更新（expired）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10011, 'contract', 2011, 'UPDATE', 'SCREEN',
  1, '2025-09-05 09:00:00', 'REQ-2025-09-005-011', '満期失注・ステータス更新'
);

-- ========== 満期案件操作ログ ==========

-- 10012: 満期案件3002 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10012, 'renewal_case', 3002, 'INSERT', 'BATCH',
  1, '2026-02-01 03:00:00', NULL, '年度更新バッチによる満期案件自動生成'
);

-- 10013: 満期案件3012 ステータス更新（quote_sent）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10013, 'renewal_case', 3012, 'UPDATE', 'SCREEN',
  1, '2026-04-05 10:00:00', 'REQ-2026-04-005-013', 'ステータス更新: not_started → quote_sent'
);

-- 10014: 満期案件3014 担当者更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10014, 'renewal_case', 3014, 'UPDATE', 'SCREEN',
  1, '2026-04-07 13:30:00', 'REQ-2026-04-007-014', '担当者訪問後に内容更新'
);

-- 10015: 満期案件3018 完了処理
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10015, 'renewal_case', 3018, 'UPDATE', 'SCREEN',
  1, '2026-03-28 15:30:00', 'REQ-2026-03-028-015', '更改完了処理'
);

-- 10016: 満期案件3022 失注処理
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10016, 'renewal_case', 3022, 'UPDATE', 'SCREEN',
  1, '2025-08-25 16:00:00', 'REQ-2025-08-025-016', '失注処理'
);

-- 10017: 満期案件3028 waiting_payment更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10017, 'renewal_case', 3028, 'UPDATE', 'SCREEN',
  2, '2026-04-06 10:00:00', 'REQ-2026-04-006-017', 'ステータス更新: quote_sent → waiting_payment'
);

-- 10018: 満期案件3002 バッチによる次回対応日更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10018, 'renewal_case', 3002, 'SYSTEM_UPDATE', 'BATCH',
  1, '2026-04-01 03:00:00', NULL, '満期アラートバッチ: next_action_date自動更新'
);

-- 10019: 満期案件3004 SJ依頼ステータス更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10019, 'renewal_case', 3004, 'UPDATE', 'SCREEN',
  1, '2026-04-05 09:30:00', 'REQ-2026-04-005-019', 'ステータス更新: not_started → sj_requested'
);

-- 10020: 満期案件3016 書類準備ステータス更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10020, 'renewal_case', 3016, 'UPDATE', 'SCREEN',
  1, '2026-04-01 13:00:00', 'REQ-2026-04-001-020', 'ステータス更新: sj_requested → doc_prepared'
);

-- ========== 事故案件操作ログ ==========

-- 10021: 事故案件4001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10021, 'accident_case', 4001, 'INSERT', 'SCREEN',
  1, '2026-04-05 14:00:00', 'REQ-2026-04-005-021', '事故案件新規受付'
);

-- 10022: 事故案件4002 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10022, 'accident_case', 4002, 'INSERT', 'SCREEN',
  2, '2026-04-06 18:00:00', 'REQ-2026-04-006-022', '事故案件新規受付（緊急・対人）'
);

-- 10023: 事故案件4003 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10023, 'accident_case', 4003, 'INSERT', 'SCREEN',
  1, '2026-04-07 09:00:00', 'REQ-2026-04-007-023', '事故案件新規受付（建設工事飛散物）'
);

-- 10024: 事故案件4004 ステータス更新（accepted→linked）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10024, 'accident_case', 4004, 'UPDATE', 'SCREEN',
  1, '2026-03-15 15:00:00', 'REQ-2026-03-015-024', 'ステータス更新: accepted → linked'
);

-- 10025: 事故案件4007 ステータス更新（in_progress）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10025, 'accident_case', 4007, 'UPDATE', 'SCREEN',
  1, '2025-11-15 10:00:00', 'REQ-2025-11-015-025', 'ステータス更新: linked → in_progress'
);

-- 10026: 事故案件4013 resolved 更新
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10026, 'accident_case', 4013, 'UPDATE', 'SCREEN',
  2, '2025-11-20 15:00:00', 'REQ-2025-11-020-026', '示談成立・resolved 処理'
);

-- 10027: 事故案件4015 closed処理
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10027, 'accident_case', 4015, 'UPDATE', 'SCREEN',
  1, '2025-10-31 16:00:00', 'REQ-2025-10-031-027', '保険金支払完了・closed処理'
);

-- ========== 活動ログ ==========

-- 10028: 活動8001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10028, 'activity', 8001, 'INSERT', 'SCREEN',
  1, '2026-04-07 10:20:00', 'REQ-2026-04-007-028', '活動履歴登録'
);

-- 10029: 活動8005 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10029, 'activity', 8005, 'INSERT', 'SCREEN',
  1, '2026-03-22 14:45:00', 'REQ-2026-03-022-029', '活動履歴登録（事故対応）'
);

-- 10030: 活動8012 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10030, 'activity', 8012, 'INSERT', 'SCREEN',
  1, '2026-04-07 14:15:00', 'REQ-2026-04-007-030', '活動履歴登録（訪問）'
);

-- 10031: 活動8024 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10031, 'activity', 8024, 'INSERT', 'SCREEN',
  2, '2026-04-06 18:35:00', 'REQ-2026-04-006-031', '活動履歴登録（事故初期対応）'
);

-- 10032: 活動8026 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10032, 'activity', 8026, 'INSERT', 'SCREEN',
  1, '2026-03-20 15:45:00', 'REQ-2026-03-020-032', '活動履歴登録（新規開拓訪問）'
);

-- ========== 成績ログ ==========

-- 10033: 成績7001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10033, 'sales_performance', 7001, 'INSERT', 'SCREEN',
  1, '2025-04-10 16:00:00', 'REQ-2025-04-010-033', '成績計上'
);

-- 10034: 成績7003 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10034, 'sales_performance', 7003, 'INSERT', 'SCREEN',
  1, '2025-04-05 17:00:00', 'REQ-2025-04-005-034', '成績計上（フリート）'
);

-- 10035: 成績7033 作成（最近の更改完了）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10035, 'sales_performance', 7033, 'INSERT', 'SCREEN',
  1, '2026-03-28 16:00:00', 'REQ-2026-03-028-035', '成績計上（2026年3月更改）'
);

-- 10036: 成績7038 作成（台数変更・マイナス成績）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10036, 'sales_performance', 7038, 'INSERT', 'SCREEN',
  1, '2026-02-01 11:00:00', 'REQ-2026-02-001-036', '台数変更・差額精算成績'
);

-- ========== コメントログ ==========

-- 10037: コメント6001 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10037, 'comment', 6001, 'INSERT', 'SCREEN',
  1, '2026-04-07 10:20:00', 'REQ-2026-04-007-037', 'コメント投稿'
);

-- 10038: コメント6006 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10038, 'comment', 6006, 'INSERT', 'SCREEN',
  1, '2026-04-07 13:15:00', 'REQ-2026-04-007-038', 'コメント投稿（満期緊急案件）'
);

-- 10039: コメント6016 作成
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10039, 'comment', 6016, 'INSERT', 'SCREEN',
  1, '2026-04-05 14:30:00', 'REQ-2026-04-005-039', 'コメント投稿（事故受付）'
);

-- 10040: コメント6027 作成（クローズ報告）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10040, 'comment', 6027, 'INSERT', 'SCREEN',
  1, '2025-10-31 16:30:00', 'REQ-2025-10-031-040', 'コメント投稿（事故クローズ報告）'
);

-- ========== SJNET 取込ログ ==========

-- 10041: SJNET取込バッチ（契約2001）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10041, 'contract', 2001, 'IMPORT', 'SJNET_IMPORT',
  1, '2026-04-01 08:00:00', NULL, 'SJNET月次自動取込'
);

-- 10042: SJNET取込バッチ（契約2002）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10042, 'contract', 2002, 'IMPORT', 'SJNET_IMPORT',
  1, '2026-04-01 08:01:00', NULL, 'SJNET月次自動取込'
);

-- 10043: SJNET取込バッチ（契約2007）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10043, 'contract', 2007, 'IMPORT', 'SJNET_IMPORT',
  1, '2026-04-01 08:02:00', NULL, 'SJNET月次自動取込'
);

-- 10044: SJNET取込バッチ（契約2044）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10044, 'contract', 2044, 'IMPORT', 'SJNET_IMPORT',
  1, '2026-04-01 08:03:00', NULL, 'SJNET月次自動取込'
);

-- ========== バッチ処理ログ ==========

-- 10045: リマインドバッチ実行（ルール5001）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10045, 'accident_case', 4007, 'SYSTEM_UPDATE', 'BATCH',
  1, '2026-03-30 06:00:00', NULL, '事故リマインドバッチ: 週次通知送信'
);

-- 10046: リマインドバッチ実行（ルール5002）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10046, 'accident_case', 4008, 'SYSTEM_UPDATE', 'BATCH',
  1, '2026-03-25 06:00:00', NULL, '事故リマインドバッチ: 隔週通知送信'
);

-- 10047: 満期アラートバッチ（30日以内の未対応案件通知）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10047, 'renewal_case', 3010, 'SYSTEM_UPDATE', 'BATCH',
  1, '2026-04-07 06:00:00', NULL, '満期アラートバッチ: 30日以内通知'
);

-- 10048: 満期アラートバッチ（案件3014）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10048, 'renewal_case', 3014, 'SYSTEM_UPDATE', 'BATCH',
  1, '2026-04-07 06:00:00', NULL, '満期アラートバッチ: 10日以内通知'
);

-- 10049: 顧客1017 ステータス更新（active→closed）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10049, 'customer', 1017, 'UPDATE', 'SCREEN',
  1, '2024-03-01 10:00:00', 'REQ-2024-03-001-049', '廃業確認・ステータスclosed更新'
);

-- 10050: 顧客1020 更新（備考追加）
INSERT INTO t_audit_event (
  id, entity_type, entity_id, action_type, change_source,
  changed_by, changed_at, request_id, note
) VALUES (
  10050, 'customer', 1020, 'UPDATE', 'SCREEN',
  1, '2026-03-10 16:00:00', 'REQ-2026-03-010-050', '備考・担当者情報更新'
);
