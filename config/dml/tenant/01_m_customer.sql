-- =====================================================================
-- 動作確認用DML: m_customer
-- 用途: 業務シナリオを網羅する顧客マスタ
-- 件数: 20件
-- ID範囲: 1001 - 1020
-- 依存: なし（最初に投入する）
-- 関連DDL: config/ddl/tenant/m_customer.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 法人顧客（active）1001-1005 ==========

-- 顧客1001: 重要法人顧客（複数契約6件・活動履歴・コメントあり）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1001, 'corporate', '株式会社テストコーポレーション', 'カブシキガイシャテストコーポレーション',
  '03-1111-2222', 'info@test-corp.insurance-test.example.jp', '100-0001',
  '東京都千代田区千代田1-1', 'テストビル5F',
  'active', '重要法人顧客（動作確認用）。複数保険契約・事故案件あり。', 0, 1, 1
);

-- 顧客1002: 運輸法人顧客（複数契約5件）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1002, 'corporate', 'テスト運輸株式会社', 'テストウンユカブシキガイシャ',
  '06-2222-3333', 'info@test-transport.insurance-test.example.jp', '530-0001',
  '大阪府大阪市北区梅田1-2-3', 'テスト梅田ビル2F',
  'active', '運送業・フリート契約あり。', 0, 1, 1
);

-- 顧客1003: 小売法人顧客（複数契約4件）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1003, 'corporate', '有限会社テスト商事', 'ユウゲンガイシャテストショウジ',
  '052-3333-4444', 'info@test-shoji.insurance-test.example.jp', '460-0001',
  '愛知県名古屋市中区栄2-3-4', NULL,
  'active', NULL, 0, 1, 1
);

-- 顧客1004: 建設法人顧客（複数契約4件）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1004, 'corporate', 'テスト建設株式会社', 'テストケンセツカブシキガイシャ',
  '03-4444-5555', 'info@test-kensetsu.insurance-test.example.jp', '135-0001',
  '東京都江東区青海1-2-3', 'テスト建設本社ビル',
  'active', '建設・土木業。工事保険・賠償責任保険あり。', 0, 1, 2
);

-- 顧客1005: 医療法人顧客（複数契約2件）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1005, 'corporate', '医療法人テストクリニック', 'イリョウホウジンテストクリニック',
  '03-5555-6666', 'office@test-clinic.insurance-test.example.jp', '160-0001',
  '東京都新宿区新宿3-4-5', 'テストビル1F',
  'active', '医師賠償責任保険加入。', 0, 2, 2
);

-- ========== 個人顧客（active）1006-1010 ==========

-- 顧客1006: 個人顧客・複数契約あり・活動履歴あり
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1006, 'individual', '山田 太郎', 'ヤマダ タロウ',
  '090-1111-2222', 'yamada.taro@insurance-test.example.jp', '150-0001',
  '東京都渋谷区神宮前1-2-3', 'ヤマダマンション201',
  'active', NULL, 0, 1, 1
);

-- 顧客1007: 個人顧客・複数契約あり
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1007, 'individual', '鈴木 花子', 'スズキ ハナコ',
  '080-2222-3333', 'suzuki.hanako@insurance-test.example.jp', '223-0001',
  '神奈川県横浜市港北区大倉山1-2-3', NULL,
  'active', NULL, 0, 1, 1
);

-- 顧客1008: 個人顧客
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1008, 'individual', '佐藤 健一', 'サトウ ケンイチ',
  '070-3333-4444', 'sato.kenichi@insurance-test.example.jp', '460-0001',
  '愛知県名古屋市中区栄3-4-5', 'サトウマンション302',
  'active', NULL, 0, 2, 2
);

-- 顧客1009: 個人顧客・事故案件あり
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1009, 'individual', '田中 美咲', 'タナカ ミサキ',
  '090-4444-5555', 'tanaka.misaki@insurance-test.example.jp', '530-0001',
  '大阪府大阪市北区中津2-3-4', NULL,
  'active', NULL, 0, 2, 2
);

-- 顧客1010: 個人顧客
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1010, 'individual', '伊藤 誠', 'イトウ マコト',
  '080-5555-6666', 'ito.makoto@insurance-test.example.jp', '812-0001',
  '福岡県福岡市博多区博多駅前1-2-3', 'イトウビル4F',
  'active', NULL, 0, 1, 1
);

-- ========== 見込み顧客（prospect）1011-1013 ==========

-- 顧客1011: 法人見込み顧客（契約なし・営業案件あり）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1011, 'corporate', '株式会社テスト見込商事', 'カブシキガイシャテストミコミショウジ',
  '03-6666-7777', 'info@test-prospect.insurance-test.example.jp', '101-0001',
  '東京都千代田区神田神保町1-2-3', NULL,
  'prospect', '既存の損保から乗り換え検討中。2026年度中の成約見込。', 0, 1, 1
);

-- 顧客1012: 個人見込み顧客（紹介案件）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1012, 'individual', '高橋 良子', 'タカハシ ヨシコ',
  '090-6666-7777', 'takahashi.yoshiko@insurance-test.example.jp', '162-0001',
  '東京都新宿区市谷田町2-3-4', 'タカハシマンション101',
  'prospect', '顧客1006の紹介。自動車保険の見積もり依頼済み。', 0, 2, 2
);

-- 顧客1013: 個人見込み顧客
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1013, 'individual', '中村 浩', 'ナカムラ ヒロシ',
  '080-7777-8888', NULL, '231-0001',
  '神奈川県横浜市中区山下町3-4-5', NULL,
  'prospect', '生命保険の相談あり。', 0, 2, 2
);

-- ========== 休眠顧客（inactive）1014-1016 ==========

-- 顧客1014: 法人休眠顧客（過去契約あり・現在失注）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1014, 'corporate', '旧テスト工業株式会社', 'キュウテストコウギョウカブシキガイシャ',
  '03-7777-8888', NULL, '143-0001',
  '東京都大田区蒲田1-2-3', NULL,
  'inactive', '2025年に他社へ切り替え。満期案件で失注。', 0, 1, 1
);

-- 顧客1015: 個人休眠顧客
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1015, 'individual', '小林 幸子', 'コバヤシ サチコ',
  '070-8888-9999', NULL, '330-0001',
  '埼玉県さいたま市大宮区高鼻町1-2-3', NULL,
  'inactive', NULL, 0, 1, 1
);

-- 顧客1016: 個人休眠顧客
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1016, 'individual', '加藤 一郎', 'カトウ イチロウ',
  '080-8888-9999', NULL, '420-0001',
  '静岡県静岡市葵区呉服町1-2-3', NULL,
  'inactive', NULL, 0, 2, 2
);

-- ========== 解約済み顧客（closed）1017-1019 ==========

-- 顧客1017: 法人解約済み顧客（過去契約3件あり・新規案件なし）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1017, 'corporate', '解約済みテスト商店株式会社', 'カイヤクズミテストショウテンカブシキガイシャ',
  '03-8888-9999', NULL, '170-0001',
  '東京都豊島区池袋1-2-3', NULL,
  'closed', '2024年に廃業。全契約解約済み。', 0, 1, 1
);

-- 顧客1018: 個人解約済み顧客（過去契約2件あり）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1018, 'individual', '渡辺 徹', 'ワタナベ トオル',
  NULL, NULL, '552-0001',
  '大阪府大阪市港区市岡1-2-3', NULL,
  'closed', '転居・連絡不通のため解約。', 0, 1, 1
);

-- 顧客1019: 個人解約済み顧客（過去契約1件あり）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1019, 'individual', '松本 恵子', 'マツモト ケイコ',
  NULL, NULL, '060-0001',
  '北海道札幌市中央区北1条西1丁目', NULL,
  'closed', '2023年解約。', 0, 2, 2
);

-- ========== 法人顧客（active）1020 ==========

-- 顧客1020: 自動車販売法人（フリート契約7件・重要顧客）
INSERT INTO m_customer (
  id, customer_type, customer_name, customer_name_kana,
  phone, email, postal_code, address1, address2,
  status, note, is_deleted, created_by, updated_by
) VALUES (
  1020, 'corporate', 'テスト自動車販売株式会社', 'テストジドウシャハンバイカブシキガイシャ',
  '03-9999-0000', 'info@test-car.insurance-test.example.jp', '108-0001',
  '東京都港区芝浦1-2-3', 'テストカービル3F',
  'active', '自動車販売業。フリート契約・展示車保険等複数契約。', 0, 1, 1
);
