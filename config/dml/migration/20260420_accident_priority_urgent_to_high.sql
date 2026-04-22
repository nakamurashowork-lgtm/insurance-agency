-- =====================================================================
-- 移行DML: t_accident_case.priority の 'urgent' を 'high' に統合
-- 日時  : 2026-04-20
-- 経緯  : 優先度仕様が low/normal/high/urgent の4区分から low/normal/high の
--        3区分に統一された際、既存レコードの urgent が残っていたため、
--        高優先度として high にまとめる。
-- =====================================================================

UPDATE t_accident_case SET priority = 'high' WHERE priority = 'urgent' AND is_deleted = 0;
