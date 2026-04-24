-- =====================================================================
-- マスターデータ: m_customer（「社内・顧客なし」ダミー顧客）
-- 投入先: 検証環境・本番環境 両方
-- 用途  : 顧客未登録時の暫定参照先として使う内部顧客レコード
--        （契約・活動・案件で customer_id を NOT NULL にできない場合に使用）
-- 件数  : 1件
-- 関連DDL: config/ddl/tenant/m_customer.sql
-- 備考  : 同名レコードが既に存在する場合は挿入をスキップする。
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO m_customer
  (customer_type, customer_name,
   status, is_deleted, created_by, updated_by)
SELECT 'individual', '（社内・顧客なし）',
       'active', 0, 1, 1
WHERE NOT EXISTS (
  SELECT 1 FROM m_customer
   WHERE customer_name = '（社内・顧客なし）'
     AND is_deleted = 0
);
