-- =============================================================
-- フェーズA 動作確認用テストデータ投入スクリプト
-- 対象DB: xs000001_te001（テナントDB）
-- 実行前提: ALTER TABLE 3本が適用済みであること
-- クリーンアップ: 末尾の DELETE/ROLLBACK 用SQLを参照
-- =============================================================
USE xs000001_te001;

-- ----- シナリオB用: 既存顧客1件（生年月日あり）-----
-- CSV行の「フェーズBテスト太郎 / 1980-01-15」と1件マッチさせる
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, postal_code, address1, address2,
     status, note, created_by, updated_by)
VALUES
    ('individual', 'フェーズBテスト太郎', '1980-01-15',
     '090-1111-0001', NULL, '東京都テスト区テスト1-1', NULL,
     'active', 'シナリオB用テストデータ', 1, 1);
-- ↑ AUTO_INCREMENT で払い出された ID を後で確認
-- SELECT LAST_INSERT_ID(); で確認可

-- ----- シナリオC用: 同姓同名2件（生年月日なし）-----
-- CSV行の「フェーズC同姓同名」と複数マッチ → customer_id=NULL になるはず
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, status, note, created_by, updated_by)
VALUES
    ('individual', 'フェーズC同姓同名', NULL,
     '090-2222-0001', 'active', 'シナリオC用 1人目', 1, 1),
    ('individual', 'フェーズC同姓同名', NULL,
     '090-2222-0002', 'active', 'シナリオC用 2人目', 1, 1);

-- ----- シナリオD用: 既存顧客（1983年生まれ）-----
-- CSV行は「フェーズDテスト山田 / 1984-05-21」→ 生年月日が違うので 0件ヒット → 新規INSERT
INSERT INTO m_customer
    (customer_type, customer_name, birth_date,
     phone, status, note, created_by, updated_by)
VALUES
    ('individual', 'フェーズDテスト山田', '1983-05-21',
     '090-4444-0001', 'active', 'シナリオD用テストデータ（1983年生まれ）', 1, 1);

-- ----- 投入確認 -----
SELECT id, customer_name, birth_date, phone, note
FROM m_customer
WHERE customer_name LIKE 'フェーズ%'
ORDER BY id;
