-- =============================================================
-- CSV取込 E2Eテスト用データセットアップ
-- 対象DB : xs000001_te001（テナントDB）
-- 実行方法: mysql -u root xs000001_te001 --default-character-set=utf8mb4 < tests/fixtures/csv_import_setup.sql
-- 冪等性 : 既存テストデータを削除してから再投入するため再実行可能
--
-- テストケース別の前提データ
--   TC-01 : 前提データなし（新規顧客・新規契約）
--   TC-02 : 既存顧客 1件（CSV取込TC02テスト / 1975-06-20）
--   TC-03 : 同名顧客 2件（CSV取込TC03テスト / 生年月日なし）
--   TC-04 : 既存顧客 + 既存契約 + 既存案件
--   TC-05 : 前提データなし（スキップ・エラー混在）
--   TC-06 : 前提データなし（必須ヘッダー欠落）
--   TC-07 : スタッフ（sjnet_code = CSV-TC07-S1）
--   TC-08 : TC-01 と同一CSVを2回目取込（TC-01 の結果を利用）
-- =============================================================

USE xs000001_te001;
-- Windows 環境で mysql コマンドから実行する際に文字コードを統一する
SET NAMES utf8mb4;

-- ──────────────────────────────────────────────────────────────
-- 1. クリーンアップ（FK 制約順：子テーブルから削除）
-- ──────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM t_renewal_case
WHERE contract_id IN (
    SELECT id FROM t_contract WHERE policy_no LIKE 'CSV-TC%'
);

DELETE FROM t_contract
WHERE policy_no LIKE 'CSV-TC%';

DELETE FROM m_customer
WHERE customer_name LIKE 'CSV取込TC%';

DELETE FROM m_staff
WHERE sjnet_code = 'CSV-TC07-S1';

SET FOREIGN_KEY_CHECKS = 1;

-- ──────────────────────────────────────────────────────────────
-- 2. TC-02 用：既存顧客1件（名前+生年月日で1件ヒット）
--    CSV: CSV取込TC02テスト / 1975-06-20 → 1件マッチ → 顧客自動登録なし
-- ──────────────────────────────────────────────────────────────
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, postal_code, address1, address2,
     status, note, is_deleted, created_by, updated_by)
VALUES
    ('individual', 'CSV取込TC02テスト', '1975-06-20',
     NULL, NULL, NULL, NULL,
     'active', 'TC02用テストデータ', 0, 1, 1);

-- ──────────────────────────────────────────────────────────────
-- 3. TC-03 用：同名顧客2件（生年月日なし → 複数マッチ → 未紐づけ）
--    CSV: CSV取込TC03テスト / 生年月日なし → 2件ヒット → customer_id=NULL
-- ──────────────────────────────────────────────────────────────
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, postal_code, address1, address2,
     status, note, is_deleted, created_by, updated_by)
VALUES
    ('individual', 'CSV取込TC03テスト', NULL,
     '090-3333-0001', NULL, NULL, NULL,
     'active', 'TC03用テストデータ（1人目）', 0, 1, 1),
    ('individual', 'CSV取込TC03テスト', NULL,
     '090-3333-0002', NULL, NULL, NULL,
     'active', 'TC03用テストデータ（2人目）', 0, 1, 1);

-- ──────────────────────────────────────────────────────────────
-- 4. TC-04 用：既存顧客 + 既存契約 + 既存案件（更新テスト）
--    CSV: CSV-TC04-001 / 2026-09-30 → 契約・案件が既存 → update=1
-- ──────────────────────────────────────────────────────────────
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, postal_code, address1, address2,
     status, note, is_deleted, created_by, updated_by)
VALUES
    ('individual', 'CSV取込TC04テスト', '1982-11-05',
     NULL, NULL, NULL, NULL,
     'active', 'TC04用テストデータ', 0, 1, 1);

SET @tc04_customer_id = LAST_INSERT_ID();

INSERT INTO t_contract
    (customer_id, policy_no, product_type,
     policy_start_date, policy_end_date, premium_amount, payment_cycle,
     status, sales_staff_id, office_staff_id, last_sjnet_imported_at,
     is_deleted, created_by, updated_by)
VALUES
    (@tc04_customer_id, 'CSV-TC04-001', '一般自動車',
     '2025-10-01', '2026-09-30', 85000, '一時払',
     'active', NULL, NULL, NULL,
     0, 1, 1);

SET @tc04_contract_id = LAST_INSERT_ID();

INSERT INTO t_renewal_case
    (contract_id, maturity_date, case_status,
     assigned_staff_id, office_staff_id,
     is_deleted, created_by, updated_by)
VALUES
    (@tc04_contract_id, '2026-09-30', 'not_started',
     NULL, NULL,
     0, 1, 1);

-- ──────────────────────────────────────────────────────────────
-- 5. TC-07 用：スタッフ（sjnet_code が CSV に記載されているコード）
--    CSV: 代理店ｺｰﾄﾞ = CSV-TC07-S1 → 解決済み 1件
-- ──────────────────────────────────────────────────────────────
INSERT INTO m_staff
    (staff_name, is_sales, is_office, user_id, sjnet_code,
     is_active, sort_order, created_by, updated_by)
VALUES
    ('CSV取込テスト担当者', 1, 0, NULL, 'CSV-TC07-S1',
     1, 0, 1, 1);

-- ──────────────────────────────────────────────────────────────
-- 確認クエリ
-- ──────────────────────────────────────────────────────────────
SELECT '=== TC02/03/04 顧客 ===' AS info;
SELECT id, customer_name, birth_date, note
FROM m_customer
WHERE customer_name LIKE 'CSV取込TC%'
ORDER BY id;

SELECT '=== TC04 契約・案件 ===' AS info;
SELECT c.id AS contract_id, c.policy_no, c.policy_end_date,
       r.id AS renewal_case_id, r.case_status
FROM t_contract c
LEFT JOIN t_renewal_case r ON r.contract_id = c.id
WHERE c.policy_no LIKE 'CSV-TC%'
ORDER BY c.id;

SELECT '=== TC07 スタッフ ===' AS info;
SELECT id, staff_name, sjnet_code, is_active
FROM m_staff
WHERE sjnet_code LIKE 'CSV-%'
ORDER BY id;
