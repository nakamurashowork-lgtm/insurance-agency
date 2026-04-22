-- =====================================================================
-- ダミーデータ: t_accident_case（事故案件）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : マスタ m_case_status(accident) の全ステータスを網羅
--         priority は low/normal/high を分散配置
-- 件数  : 12件
-- ID範囲: 4001 - 4012
-- 依存  : 02_m_customer.sql, 03_t_contract.sql, 01_m_staff.sql
-- 関連DDL: config/ddl/tenant/t_accident_case.sql
-- 基準日 : 2026-04-21
-- =====================================================================
-- カバレッジ:
--   受付            : 4001(normal), 4002(high)
--   保険会社連絡済み: 4003(high)   ← 未登録顧客（prospect_name 使用）
--   対応中          : 4004(high), 4005(normal), 4006(low)
--   書類待ち        : 4007(normal)
--   解決済み        : 4008(normal), 4009(low)
--   完了            : 4010(normal), 4011(normal), 4012(low)
-- =====================================================================

SET NAMES utf8mb4;

-- 4001: 受付・normal・法人フリート1台が接触
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4001, 1001, 2001, 'ACC-2026-0001', '2026-04-18', '2026-04-17',
  '自動車', 'タフ・ビズ', '接触事故', '駐車場内での軽微な接触事故。相手車両軽い擦り傷。',
  '東京都千代田区千代田 社内駐車場', 1, '受付', 'normal',
  NULL, 1, 3, '相手方と連絡取れている。大事には至らず。',
  0, 1, 1
);

-- 4002: 受付・high・自宅駐車中の当て逃げ
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4002, 1004, 2006, 'ACC-2026-0002', '2026-04-19', '2026-04-18',
  '自動車', 'おとなの自動車', '当て逃げ', '自宅駐車中に当て逃げ。損傷大。警察届出済。',
  '東京都渋谷区神宮前 自宅駐車場', 0, '受付', 'high',
  NULL, 1, 3, '至急対応。被害者感情配慮。',
  0, 1, 1
);

-- 4003: 保険会社連絡済み・high・未登録顧客からの飛び込み相談
INSERT INTO t_accident_case (
  id, customer_id, prospect_name, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4003, NULL, '井上 健二（顧客未登録）', NULL, 'ACC-2026-0003', '2026-04-15', '2026-04-14',
  '自動車', NULL, '対人事故', '他社契約者からの相談受付。怪我人あり。当社契約ではないが紹介対応。',
  '東京都新宿区西新宿1-2', 1, '保険会社連絡済み', 'high',
  'SJ-CL-202604-001', 1, 3, '顧客未登録。他社契約。紹介先として対応。',
  0, 1, 1
);

-- 4004: 対応中・high・運輸フリート車両による対人事故
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, sc_staff_name, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4004, 1002, 2004, 'ACC-2026-0004', '2026-03-20', '2026-03-19',
  '自動車', '事業用Ｋ・Ａ・Ｐ', '対人事故', '運輸フリート車両で交差点接触。相手方負傷（軽傷）。',
  '大阪府大阪市北区梅田 交差点', 1, '対応中', 'high',
  'SJ-CL-202603-004', 2, 3, '山田 一郎', '相手方との示談交渉中。過失割合協議中。',
  0, 1, 1
);

-- 4005: 対応中・normal・個人の軽微な自損事故
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4005, 1005, 2008, 'ACC-2026-0005', '2026-04-05', '2026-04-04',
  '自動車', 'GKクルマの保険・家庭用', '自損事故', 'ガードレールに擦った軽微な自損。',
  '神奈川県横浜市中区', 0, '対応中', 'normal',
  'SJ-CL-202604-005', 2, 3, '修理工場手配済み。見積取得中。',
  0, 1, 1
);

-- 4006: 対応中・low・入院なしの軽い怪我（傷害）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4006, 1006, 2010, 'ACC-2026-0006', '2026-04-02', '2026-04-01',
  '傷害', '医療保険プレミアム', '通院事故', '自宅階段で転倒。通院中。',
  '神奈川県横浜市西区 自宅', 0, '対応中', 'low',
  'SJ-CL-202604-006', 1, 3, '通院日数確定後に請求予定。',
  0, 1, 1
);

-- 4007: 書類待ち・normal・法人火災の小破損
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4007, 1001, 2002, 'ACC-2026-0007', '2026-03-25', '2026-03-24',
  '火災', 'すまいの保険', '水濡れ', '上階漏水によるテナント天井破損。修繕見積取得中。',
  '東京都千代田区 本社ビル5F', 0, '書類待ち', 'normal',
  'SJ-CL-202603-007', 2, 3, '損害見積書・修繕見積書待ち。',
  0, 1, 1
);

-- 4008: 解決済み・normal・山田 軽微修理で完了近い
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, resolved_date, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4008, 1004, 2006, 'ACC-2026-0008', '2026-02-10', '2026-02-09',
  '自動車', 'おとなの自動車', '接触事故', '路肩停車中に接触された軽微事故。',
  '東京都渋谷区', 1, '解決済み', 'normal',
  'SJ-CL-202602-008', '2026-04-10', 1, 3, '相手方保険会社にて対応完了。支払済み。',
  0, 1, 1
);

-- 4009: 解決済み・low・渡辺 飛び石フロントガラス
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, resolved_date, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4009, 1007, 2012, 'ACC-2026-0009', '2026-03-05', '2026-03-04',
  '自動車', 'タフクル', '飛び石', '高速走行中に飛び石によりフロントガラス破損。',
  '京都府京都市南区', 0, '解決済み', 'low',
  'SJ-CL-202603-009', '2026-04-05', 2, 3, 'ガラス交換済み。完了書類送付済み。',
  0, 1, 1
);

-- 4010: 完了・normal・法人運輸 過去の事故
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, resolved_date, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4010, 1002, 2004, 'ACC-2025-0010', '2025-11-20', '2025-11-19',
  '自動車', '事業用Ｋ・Ａ・Ｐ', '対物事故', '配送車両による電柱接触。軽微損害。',
  '大阪府堺市', 0, '完了', 'normal',
  'SJ-CL-202511-010', '2026-01-15', 2, 3, '示談完了。',
  0, 1, 1
);

-- 4011: 完了・normal・個人佐藤 過去の完了事故
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, resolved_date, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4011, 1005, 2008, 'ACC-2025-0011', '2025-09-10', '2025-09-09',
  '自動車', 'GKクルマの保険・家庭用', '対物事故', 'コインパーキングのポール接触。',
  '神奈川県横浜市', 0, '完了', 'normal',
  'SJ-CL-202509-011', '2025-11-20', 2, 3, '完了済み。',
  0, 1, 1
);

-- 4012: 完了・low・法人の古い軽微事故
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no, accepted_date, accident_date,
  insurance_category, product_type, accident_type, accident_summary,
  accident_location, has_counterparty, status, priority,
  insurer_claim_no, resolved_date, assigned_staff_id, office_staff_id, remark,
  is_deleted, created_by, updated_by
) VALUES (
  4012, 1001, 2001, 'ACC-2025-0012', '2025-06-05', '2025-06-04',
  '自動車', 'タフ・ビズ', '自損事故', '社用車が壁に擦った軽微な自損。',
  '東京都千代田区 本社駐車場', 0, '完了', 'low',
  'SJ-CL-202506-012', '2025-08-10', 1, 3, '完了済み。',
  0, 1, 1
);
