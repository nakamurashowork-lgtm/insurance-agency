-- =====================================================================
-- 動作確認データ クリーンアップ
-- 用途: 動作確認用DMLで投入したデータを全削除する
-- 対象: ID範囲指定による削除（既存データへの影響なし）
--
-- 実行方法: tenant DB に対して実行する
-- 実行前提: FOREIGN_KEY_CHECKS を一時的に無効化して削除する
-- 注意: 削除はFK逆順（参照される側を後に削除する）で実施
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ① 監査イベント詳細（t_audit_event を参照）
DELETE FROM t_audit_event_detail WHERE id BETWEEN 11001 AND 11100;

-- ② 監査イベント
DELETE FROM t_audit_event WHERE id BETWEEN 10001 AND 10050;

-- ③ 営業案件
DELETE FROM t_sales_case WHERE id BETWEEN 9001 AND 9010;

-- ④ 活動履歴
DELETE FROM t_activity WHERE id BETWEEN 8001 AND 8050;

-- ⑤ 成績
DELETE FROM t_sales_performance WHERE id BETWEEN 7001 AND 7050;

-- ⑥ 案件コメント
DELETE FROM t_case_comment WHERE id BETWEEN 6001 AND 6030;

-- ⑦ 事故リマインドルール曜日（accident_reminder_rule を参照）
DELETE FROM t_accident_reminder_rule_weekday
  WHERE accident_reminder_rule_id BETWEEN 5001 AND 5008;

-- ⑧ 事故リマインドルール
DELETE FROM t_accident_reminder_rule WHERE id BETWEEN 5001 AND 5008;

-- ⑨ 事故案件
DELETE FROM t_accident_case WHERE id BETWEEN 4001 AND 4015;

-- ⑩ 満期案件
DELETE FROM t_renewal_case WHERE id BETWEEN 3001 AND 3100;

-- ⑪ 契約
DELETE FROM t_contract WHERE id BETWEEN 2001 AND 2050;

-- ⑫ 顧客
DELETE FROM m_customer WHERE id BETWEEN 1001 AND 1020;

SET FOREIGN_KEY_CHECKS = 1;

-- ===== 完了確認クエリ（削除後に件数が 0 であることを確認）=====
-- SELECT COUNT(*) AS remaining_customers FROM m_customer WHERE id BETWEEN 1001 AND 1020;
-- SELECT COUNT(*) AS remaining_contracts FROM t_contract WHERE id BETWEEN 2001 AND 2050;
-- SELECT COUNT(*) AS remaining_renewal_cases FROM t_renewal_case WHERE id BETWEEN 3001 AND 3100;
-- SELECT COUNT(*) AS remaining_accident_cases FROM t_accident_case WHERE id BETWEEN 4001 AND 4015;
