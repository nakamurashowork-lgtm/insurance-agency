-- =====================================================================
-- ダミーデータ: m_customer（顧客）
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : 動作確認用の顧客サンプル
-- 件数  : 12件
-- ID範囲: 1001 - 1012
-- 依存  : なし（最初に投入する）
-- 関連DDL: config/ddl/tenant/m_customer.sql
-- =====================================================================
-- カバレッジ:
--   法人 active   : 1001, 1002, 1003
--   個人 active   : 1004, 1005, 1006, 1007
--   prospect      : 1008(個人), 1009(法人)
--   inactive      : 1010(個人), 1011(法人)
--   closed        : 1012(法人)
-- =====================================================================

SET NAMES utf8mb4;

-- 1001: 法人・active（重要顧客、複数契約あり）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1001, 'corporate', '株式会社テストコーポレーション',
  '03-1111-2222', '100-0001',
  '東京都千代田区千代田1-1', 'テストビル5F',
  'active', '重要法人顧客。自動車フリート・火災・賠責ありの重点顧客。', 0, 1, 1
);

-- 1002: 法人・active（運輸フリート）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1002, 'corporate', 'テスト運輸株式会社',
  '06-2222-3333', '530-0001',
  '大阪府大阪市北区梅田1-2-3', NULL,
  'active', '運送業・フリート20台規模。', 0, 1, 1
);

-- 1003: 法人・active（担当者なし顧客 / 新規登録のみ）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1003, 'corporate', '株式会社名古屋商事',
  '052-333-4444', '460-0002',
  '愛知県名古屋市中区丸の内2-3-4', '名古屋商事ビル',
  'active', '担当者未アサイン。契約も未登録の新規法人。', 0, 1, 1
);

-- 1004: 個人・active（自動車メイン）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1004, 'individual', '山田 太郎',
  '1980-05-15', '090-1111-2222', '150-0001',
  '東京都渋谷区神宮前1-2', 'ルミエール渋谷301',
  'active', NULL, 0, 1, 1
);

-- 1005: 個人・active（家族名義で火災・自動車）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1005, 'individual', '佐藤 花子',
  '1975-11-03', '080-3333-4444', '231-0001',
  '神奈川県横浜市中区新港1-3', NULL,
  'active', NULL, 0, 1, 1
);

-- 1006: 個人・active（傷害・生保中心）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1006, 'individual', '高橋 健一',
  '1968-07-22', '080-5555-6666', '220-0001',
  '神奈川県横浜市西区北幸1-4-5', 'シティハイツ502',
  'active', '傷害・医療保険重視。', 0, 1, 1
);

-- 1007: 個人・active（最近契約更改、満期遠い）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1007, 'individual', '渡辺 真由美',
  '1985-02-18', '090-7777-8888', '600-8001',
  '京都府京都市下京区四条通烏丸東入', NULL,
  'active', NULL, 0, 1, 1
);

-- 1008: 個人・prospect（紹介案件）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1008, 'individual', '鈴木 一郎',
  '1990-03-20', '070-1111-2222', '460-0001',
  '愛知県名古屋市中区錦3-4', NULL,
  'prospect', '既存顧客 1001 からの紹介案件。自動車見積提案中。', 0, 1, 1
);

-- 1009: 法人・prospect（新規飛び込み）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1009, 'corporate', '株式会社東京建設',
  '03-5555-6666', '160-0002',
  '東京都新宿区四谷1-5-6', '新宿四谷ビル3F',
  'prospect', '新規開拓中。賠責・労災の提案予定。', 0, 1, 1
);

-- 1010: 個人・inactive（長期連絡なし）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  birth_date, phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1010, 'individual', '加藤 裕子',
  '1960-09-10', NULL, '810-0001',
  '福岡県福岡市中央区天神1-7-8', NULL,
  'inactive', '転居後連絡不通。2024年以降活動なし。', 0, 1, 1
);

-- 1011: 法人・inactive（事業縮小で契約停止）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1011, 'corporate', '株式会社旧コーポレート',
  '03-9999-0000', '104-0001',
  '東京都中央区銀座1-8-9', NULL,
  'inactive', '事業縮小。再開時に再アプローチ予定。', 0, 1, 1
);

-- 1012: 法人・closed（廃業）
INSERT INTO m_customer (
  id, customer_type, customer_name,
  phone, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1012, 'corporate', '旧テスト商店',
  '011-7777-8888', '060-0001',
  '北海道札幌市中央区北1条西1', NULL,
  'closed', '2025年3月末で廃業のため全契約終了。', 0, 1, 1
);
