-- =====================================================================
-- 動作確認用DML: t_accident_case
-- 用途: 事故案件データ（各ステータスを網羅）
-- 件数: 15件
-- ID範囲: 4001 - 4015
-- 依存: 01_m_customer.sql, 03_t_contract.sql
-- 関連DDL: config/ddl/tenant/t_accident_case.sql
-- ステータス:
--   accepted      = 受付済み（未対応）
--   linked        = 保険会社連絡済み
--   in_progress   = 対応中（リマインドあり）
--   waiting_docs  = 書類待ち（対応中）
--   resolved      = 解決済み
--   closed        = 完了（リマインドは無効化済み）
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 未対応（accepted）3件 ==========

-- 4001: 受付済み・未対応（顧客1001, 自動車事故）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4001, 1001, 2001, 'ACC-2026-001',
  '2026-04-05', '2026-04-04',
  '自動車保険', '一般自動車', '車両損傷',
  '駐車場にて当て逃げ被害。リアバンパー損傷。', '東京都千代田区',
  0, 'accepted', 'normal',
  NULL, NULL,
  1, 2,
  '相手車両不明。警察への届出済み。', 0, 1, 1
);

-- 4002: 受付済み・未対応（顧客1009, 自動車事故・相手あり）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4002, 1009, 2030, 'ACC-2026-002',
  '2026-04-06', '2026-04-05',
  '自動車保険', '一般自動車', '対物・対人',
  '交差点での追突事故。相手方1名に頸部打撲。', '大阪府大阪市北区',
  1, 'accepted', 'high',
  NULL, NULL,
  2, 1,
  '相手方より治療費請求の可能性あり。早急対応要。', 0, 2, 2
);

-- 4003: 受付済み・未対応（顧客1004, 建設工事事故）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4003, 1004, 2017, 'ACC-2026-003',
  '2026-04-07', '2026-04-06',
  '新種保険', '建設工事', '第三者損害',
  '工事現場付近の民家外壁に飛散物が当たりひびが入った。', '東京都江東区',
  1, 'accepted', 'urgent',
  NULL, NULL,
  1, 2,
  '近隣住民からクレームあり。現地調査を急ぐ。', 0, 1, 1
);

-- ========== 保険会社連絡済み（linked）3件 ==========

-- 4004: 保険会社連絡済み（顧客1002, 自動車フリート事故）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4004, 1002, 2007, 'ACC-2026-004',
  '2026-03-15', '2026-03-14',
  '自動車保険', 'フリート', '対物',
  'トラックが配送先倉庫の外壁フェンスに接触。フェンス一部損壊。', '大阪府大阪市住之江区',
  1, 'linked', 'normal',
  'TK-2026-03-7890', NULL,
  1, 2,
  '保険会社事故受付番号取得済み。アジャスター派遣待ち。', 0, 1, 1
);

-- 4005: 保険会社連絡済み（顧客1001, 施設賠償）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4005, 1001, 2005, 'ACC-2026-005',
  '2026-03-20', '2026-03-18',
  '賠償責任保険', '施設賠償', '第三者身体損害',
  'オフィスビル入口で来訪者が転倒して負傷。', '東京都千代田区',
  1, 'linked', 'high',
  'SJ-2026-03-4567', NULL,
  1, 2,
  '被害者より治療費・慰謝料請求が見込まれる。', 0, 1, 1
);

-- 4006: 保険会社連絡済み（顧客1020, フリート車両損傷）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4006, 1020, 2044, 'ACC-2026-006',
  '2026-03-25', '2026-03-24',
  '自動車保険', 'フリート', '車両損傷',
  '試乗車が縁石に乗り上げ、右前輪タイヤ・ホイール破損。', '東京都港区',
  0, 'linked', 'normal',
  'MS-2026-03-1122', NULL,
  1, 2,
  '試乗中の事故。修理見積もり取得中。', 0, 1, 1
);

-- ========== 対応中（in_progress）リマインド有効 3件 ==========

-- 4007: 対応中（顧客1002, フリート重傷事故）リマインドID=5001
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4007, 1002, 2007, 'ACC-2025-101',
  '2025-11-10', '2025-11-09',
  '自動車保険', 'フリート', '対人',
  '幹線道路での追突事故。相手方2名が入院（1名重傷）。', '大阪府大阪市北区',
  1, 'in_progress', 'urgent',
  'MS-2025-11-5566', NULL,
  1, 2,
  '示談交渉中。週次で保険会社担当に確認要。', 0, 1, 1
);

-- 4008: 対応中（顧客1001, 企業総合・水濡れ損害）リマインドID=5002
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4008, 1001, 2004, 'ACC-2025-102',
  '2025-12-01', '2025-11-28',
  '企業総合保険', '企業総合', '水濡れ・漏水',
  'オフィス天井からの漏水により、電子機器・什器が損傷。', '東京都千代田区',
  0, 'in_progress', 'high',
  'TK-2025-12-3344', NULL,
  1, 2,
  '損害査定中。修理業者複数社に見積依頼済み。隔週で状況確認。', 0, 1, 1
);

-- 4009: 対応中（顧客1004, 建設賠償・近隣騒音）リマインドID=5003
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4009, 1004, 2019, 'ACC-2026-007',
  '2026-02-01', '2026-01-25',
  '賠償責任保険', '請負業者賠償', '第三者財物損害',
  '工事振動により近隣家屋の壁にひびが発生。住民からの賠償請求。', '東京都江東区',
  1, 'in_progress', 'high',
  'SJ-2026-02-9988', NULL,
  1, 2,
  '建物調査完了。損害額協議中。月次で状況報告要。', 0, 1, 1
);

-- ========== 書類待ち（waiting_docs）対応中 3件 ==========

-- 4010: 書類待ち（顧客1006, 自動車修理中）リマインドID=5004
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4010, 1006, 2022, 'ACC-2026-008',
  '2026-03-01', '2026-02-28',
  '自動車保険', '一般自動車', '対物',
  'コンビニ駐車場での接触事故。相手方車両の修理費を請求中。', '東京都渋谷区',
  1, 'waiting_docs', 'normal',
  'MS-2026-03-7711', NULL,
  2, 1,
  '相手方修理完了書・領収書の送付待ち。', 0, 1, 1
);

-- 4011: 書類待ち（顧客1005, 医師賠償）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4011, 1005, 2020, 'ACC-2026-009',
  '2026-02-15', '2026-01-30',
  '賠償責任保険', '医師賠償責任', '医療過誤',
  '手術後の後遺症について患者から損害賠償請求が提起された。', '東京都新宿区',
  1, 'waiting_docs', 'urgent',
  'TK-2026-02-6655', NULL,
  2, 1,
  '患者側弁護士より訴訟提起の通知受領。保険会社弁護士費用補償検討中。診療記録の提出準備中。', 0, 2, 2
);

-- 4012: 書類待ち（顧客1008, 自動車修理）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4012, 1008, 2028, 'ACC-2026-010',
  '2026-03-10', '2026-03-09',
  '自動車保険', '一般自動車', '車両損傷',
  '雹（ひょう）による車両損傷。ボンネット・ルーフに多数の凹み。', '愛知県名古屋市',
  0, 'waiting_docs', 'normal',
  'MS-2026-03-3322', NULL,
  1, 2,
  '板金修理の見積書提出待ち。', 0, 2, 2
);

-- ========== 解決済み（resolved）3件 ==========

-- 4013: 解決済み（顧客1007, 自動車事故）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4013, 1007, 2025, 'ACC-2025-201',
  '2025-09-10', '2025-09-08',
  '自動車保険', '一般自動車', '対物',
  '駐車時の接触による相手方車両損傷。修理費用支払い完了。', '神奈川県横浜市',
  1, 'resolved', 'normal',
  'SJ-2025-09-1144', '2025-11-20',
  2, 1,
  '示談成立。保険金支払い完了。対物損害：85,000円。', 0, 1, 1
);

-- 4014: 解決済み（顧客1010, 火災損害）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4014, 1010, 2033, 'ACC-2025-202',
  '2025-07-01', '2025-06-30',
  '火災保険', '住宅総合', '風災',
  '台風による屋根瓦の飛散・雨漏り被害。', '福岡県福岡市',
  0, 'resolved', 'high',
  'MS-2025-07-8899', '2025-09-15',
  1, 2,
  '保険金600,000円支払完了。修繕工事完了確認済み。', 0, 1, 1
);

-- ========== 完了（closed）リマインド無効化済み 1件 ==========

-- 4015: 完了・クローズ済み（顧客1002, フリート事故・長期案件完了）リマインドID=5005-5008（is_enabled=0）
INSERT INTO t_accident_case (
  id, customer_id, contract_id, accident_no,
  accepted_date, accident_date,
  insurance_category, product_type, accident_type,
  accident_summary, accident_location,
  has_counterparty, status, priority,
  insurer_claim_no, resolved_date,
  assigned_staff_id, office_staff_id,
  remark, is_deleted, created_by, updated_by
) VALUES (
  4015, 1002, 2007, 'ACC-2025-001',
  '2025-03-01', '2025-02-28',
  '自動車保険', 'フリート', '対人・対物',
  'トラックと乗用車の衝突事故。相手方1名怪我（打撲）、車両損傷。示談成立しクローズ。', '大阪府吹田市',
  1, 'closed', 'high',
  'MS-2025-03-0055', '2025-10-31',
  1, 2,
  '保険金支払完了。対人：250,000円、対物：180,000円。全手続き完了。', 0, 1, 1
);
