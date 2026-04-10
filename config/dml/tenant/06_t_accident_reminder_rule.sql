-- =====================================================================
-- 動作確認用DML: t_accident_reminder_rule
-- 用途: 事故案件のリマインドルール
-- 件数: 8件
-- ID範囲: 5001 - 5008
-- 依存: 05_t_accident_case.sql（t_accident_case）
-- 関連DDL: config/ddl/tenant/t_accident_reminder_rule.sql
-- 備考:
--   5001-5004: 有効（in_progress/waiting_docs の案件に紐付け）
--   5005-5008: 無効（closed の案件に紐付け）
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 有効なリマインドルール（is_enabled=1）==========

-- 5001: 事故案件4007（対人重傷・対応中）週次リマインド・毎週月曜
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5001, 4007, 1,
  1, '2025-11-10', '2025-11-17', NULL, '2026-03-30',
  0, 1, 1
);

-- 5002: 事故案件4008（水濡れ損害・対応中）2週間ごとリマインド・毎週水曜
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5002, 4008, 1,
  2, '2025-12-01', '2025-12-15', NULL, '2026-03-25',
  0, 1, 1
);

-- 5003: 事故案件4009（建設賠償・対応中）月次リマインド（4週間ごと）
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5003, 4009, 1,
  4, '2026-02-01', '2026-02-08', NULL, '2026-03-08',
  0, 1, 1
);

-- 5004: 事故案件4010（書類待ち・隔週リマインド）
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5004, 4010, 1,
  2, '2026-03-01', '2026-03-08', '2026-06-30', '2026-03-22',
  0, 1, 1
);

-- ========== 無効なリマインドルール（is_enabled=0、完了案件に対応）==========

-- 5005: 事故案件4015（完了済み）週次だったが無効化
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5005, 4015, 0,
  1, '2025-03-01', '2025-03-08', '2025-10-31', '2025-10-27',
  0, 1, 1
);

-- 5006: 事故案件4013（解決済み）リマインド終了後に無効化
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5006, 4013, 0,
  2, '2025-09-10', '2025-09-24', '2025-11-20', '2025-11-17',
  0, 1, 1
);

-- 5007: 事故案件4014（解決済み）月次リマインド・無効化
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5007, 4014, 0,
  4, '2025-07-01', '2025-07-28', '2025-09-15', '2025-09-08',
  0, 1, 1
);

-- 5008: 事故案件4015（完了済み）追加リマインドルール・無効化
INSERT INTO t_accident_reminder_rule (
  id, accident_case_id, is_enabled,
  interval_weeks, base_date, start_date, end_date, last_notified_on,
  is_deleted, created_by, updated_by
) VALUES (
  5008, 4015, 0,
  2, '2025-03-01', '2025-03-15', '2025-10-31', '2025-10-20',
  0, 1, 1
);
