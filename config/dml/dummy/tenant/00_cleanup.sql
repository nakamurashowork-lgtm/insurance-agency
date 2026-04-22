-- =====================================================================
-- ダミーデータ: クリーンアップ
-- 投入先: 検証環境のみ（本番禁止）
-- 用途  : ダミーデータのみを一括削除する（ID範囲指定）
--         master 側の投入データ（m_case_status の is_system=1 /
--         m_product_category / 「社内・顧客なし」顧客 等）には影響しない。
-- =====================================================================
-- 削除順は FK/論理依存の子→親で。ダミーファイルの投入順と逆になるよう揃える。
-- =====================================================================

SET NAMES utf8mb4;

DELETE FROM t_case_comment        WHERE id BETWEEN 6001 AND 6099;
DELETE FROM t_sales_target        WHERE id BETWEEN 12001 AND 12099;
DELETE FROM t_sales_performance   WHERE id BETWEEN 7001 AND 7099;
DELETE FROM t_activity            WHERE id BETWEEN 8001 AND 8099;
DELETE FROM t_sales_case          WHERE id BETWEEN 9001 AND 9099;
DELETE FROM t_accident_case       WHERE id BETWEEN 4001 AND 4099;
DELETE FROM t_renewal_case        WHERE id BETWEEN 3001 AND 3099;
DELETE FROM t_contract            WHERE id BETWEEN 2001 AND 2099;
DELETE FROM m_customer            WHERE id BETWEEN 1001 AND 1099;
DELETE FROM m_staff               WHERE id BETWEEN 1 AND 4;
