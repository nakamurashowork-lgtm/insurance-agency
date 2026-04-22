-- =============================================================
-- フェーズA 動作確認用 検証クエリ集
-- CSV取込後に順番に実行して期待値と照合する
-- =============================================================
USE xs000001_te001;

-- ============================================================
-- 【1】取込バッチ結果の確認
--     期待: insert=3 or 4, unlinked_count=1 (シナリオC)
--           customer_insert_count=2 (シナリオA新規 + シナリオD新規)
-- ============================================================
SELECT id, import_status, total_row_count,
       insert_count, update_count,
       customer_insert_count, unlinked_count,
       duplicate_skip_count, error_count,
       imported_at
FROM t_sjnet_import_batch
ORDER BY id DESC
LIMIT 3;

-- ============================================================
-- 【2】各シナリオの顧客マッチング結果確認
-- ============================================================

-- シナリオA: 新規顧客が登録されているか
-- 期待: birth_date=NULL, note は空 or NULL（CSV登録なので）
SELECT id, customer_name, birth_date, phone, address1
FROM m_customer
WHERE customer_name = 'フェーズAテスト花子'
  AND is_deleted = 0;

-- シナリオB: 既存顧客がそのまま（住所・電話が変わっていないか）
-- 期待: birth_date='1980-01-15', phone='090-1111-0001' のまま
SELECT id, customer_name, birth_date, phone, address1
FROM m_customer
WHERE customer_name = 'フェーズBテスト太郎'
  AND is_deleted = 0;

-- シナリオC: 契約の customer_id が NULL であること
-- 期待: customer_id IS NULL, sjnet_customer_name='フェーズC同姓同名'
SELECT id, customer_id, sjnet_customer_name, policy_no
FROM t_contract
WHERE policy_no = 'TEST-PHASE-C-001'
  AND is_deleted = 0;

-- シナリオD: 1983年と1984年の2件が存在すること（1984年が新規INSERT）
-- 期待: 2行 (birth_date='1983-05-21' と '1984-05-21')
SELECT id, customer_name, birth_date, note
FROM m_customer
WHERE customer_name = 'フェーズDテスト山田'
  AND is_deleted = 0
ORDER BY birth_date;

-- ============================================================
-- 【3】取込行ステータスの確認
--     期待: A=insert/update, B=insert/update, C=unlinked, D=insert/update
-- ============================================================
SELECT r.policy_no, r.customer_name, r.row_status, r.error_message
FROM t_sjnet_import_row r
JOIN t_sjnet_import_batch b ON b.id = r.sjnet_import_batch_id
WHERE b.id = (SELECT MAX(id) FROM t_sjnet_import_batch)
  AND r.policy_no LIKE 'TEST-PHASE-%'
ORDER BY r.row_no;

-- ============================================================
-- 【4】顧客紐づけ操作後の監査ログ確認
--     （シナリオC の契約を手動紐づけした後に実行）
-- ============================================================
SELECT ae.id, ae.entity_type, ae.entity_id, ae.action_type,
       ae.change_source, ae.changed_by, ae.changed_at, ae.note,
       aed.field_key, aed.before_value_text, aed.after_value_text
FROM t_audit_event ae
JOIN t_audit_event_detail aed ON aed.audit_event_id = ae.id
WHERE ae.entity_type = 'contract'
  AND ae.entity_id = (
      SELECT id FROM t_contract WHERE policy_no = 'TEST-PHASE-C-001' AND is_deleted = 0 LIMIT 1
  )
ORDER BY ae.id DESC;

-- ============================================================
-- 【5】クリーンアップ（確認後に実行）
-- ============================================================
/*
DELETE FROM t_sjnet_import_row
WHERE sjnet_import_batch_id IN (
    SELECT id FROM t_sjnet_import_batch WHERE original_file_name = 'phase_a_test.csv'
);
DELETE FROM t_sjnet_import_batch WHERE original_file_name = 'phase_a_test.csv';

DELETE FROM t_renewal_case
WHERE contract_id IN (
    SELECT id FROM t_contract WHERE policy_no LIKE 'TEST-PHASE-%'
);
DELETE FROM t_audit_event_detail
WHERE audit_event_id IN (
    SELECT ae.id FROM t_audit_event ae
    JOIN t_contract c ON c.id = ae.entity_id AND ae.entity_type = 'contract'
    WHERE c.policy_no LIKE 'TEST-PHASE-%'
);
DELETE FROM t_audit_event
WHERE entity_type = 'contract'
  AND entity_id IN (SELECT id FROM t_contract WHERE policy_no LIKE 'TEST-PHASE-%');
DELETE FROM t_contract WHERE policy_no LIKE 'TEST-PHASE-%';
DELETE FROM m_customer WHERE customer_name LIKE 'フェーズ%テスト%' OR customer_name LIKE 'フェーズ%同姓同名';
*/
