-- =====================================================================
-- 動作確認用DML: t_accident_reminder_rule_weekday
-- 用途: 事故リマインドルールの通知曜日設定
-- 件数: 20件
-- 依存: 06_t_accident_reminder_rule.sql
-- 関連DDL: config/ddl/tenant/t_accident_reminder_rule_weekday.sql
-- 曜日コード: 0=日, 1=月, 2=火, 3=水, 4=木, 5=金, 6=土
-- 備考: (accident_reminder_rule_id, weekday_cd) に UNIQUE 制約あり
-- =====================================================================

SET NAMES utf8mb4;

-- ========== ルール5001（事故案件4007・週次）: 月曜・金曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5001, 1, 1, 1);

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5001, 5, 1, 1);

-- ========== ルール5002（事故案件4008・2週間ごと）: 水曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5002, 3, 1, 1);

-- ========== ルール5003（事故案件4009・4週間ごと）: 木曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5003, 4, 1, 1);

-- ========== ルール5004（事故案件4010・隔週）: 火曜・木曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5004, 2, 1, 1);

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5004, 4, 1, 1);

-- ========== ルール5005（事故案件4015・無効・週次）: 月曜・水曜・金曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5005, 1, 1, 1);

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5005, 3, 1, 1);

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5005, 5, 1, 1);

-- ========== ルール5006（事故案件4013・無効・隔週）: 火曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5006, 2, 1, 1);

-- ========== ルール5007（事故案件4014・無効・月次）: 月曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5007, 1, 1, 1);

-- ========== ルール5008（事故案件4015・無効・追加）: 水曜・金曜 ==========

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5008, 3, 1, 1);

INSERT INTO t_accident_reminder_rule_weekday (
  accident_reminder_rule_id, weekday_cd, created_by, updated_by
) VALUES (5008, 5, 1, 1);
