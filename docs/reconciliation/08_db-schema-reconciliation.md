# DB スキーマ整合記録

## 1. 位置づけ

本文書は `config/ddl/tenant/` の canonical DDL と実DB（XServer MySQL）スキーマとの整合状態を追跡する記録である。

Phase 2 受入確認（2026-03-29）で判明した差分を起点とし、各差分の解消状態を管理する。

差分が解消された際は本文書を更新し、確認者・確認日時・確認方法を記録する。

---

## 2. 初回差分記録（Phase 2 受入確認より）

確認日: 2026-03-29
確認手段: `tmp/phase2_acceptance_check.php`（information_schema.tables による実在確認）

| 差分種別 | 対象 | 内容 | 解消状態 |
|---|---|---|---|
| テーブル不存在 | `xs000001_te001.t_contract` | DDLでは `t_contract` だが実DBに存在しない。`m_contract` が存在 | □ 未解消 |
| テーブル不存在 | `xs000001_te001.t_activity` | canonical DDL にあるが実DBに存在しない | □ 未解消 |
| テーブル不存在 | `xs000001_te001.m_customer_contact` | canonical DDL にあるが実DBに存在しない | □ 未解消 |
| 空スキーマ | `xs000001_te002` | 対象テーブルが一切存在しない | □ 未解消 |
| カラム名不一致 | `xs000001_te001.t_renewal_case` | `maturity_date` → 実DBは `renewal_due_date`、`case_status` → 実DBは `status`、`remark` → 実DBは `note` | □ 未解消 |

---

## 3. 解消確認手順

各差分の解消確認は以下の手順で行う。

1. `information_schema.tables` でテーブル存在を確認する
2. `information_schema.columns` でカラム名・型を canonical DDL と比較する
3. FK制約は `information_schema.key_column_usage` で確認する
4. 確認結果を下表「解消確認記録」に記録する

---

## 4. 解消確認記録

| 差分対象 | 解消方法 | 確認日時 | 確認者 | 確認コマンド/スクリプト |
|---|---|---|---|---|
| `t_contract` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `t_activity` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `m_customer_contact` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `xs000001_te002` 空スキーマ | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `t_renewal_case` カラム名不一致 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |

---

## 5. 今後の差分記録ルール

- DDL変更（canonical DDL修正）を行った場合は本文書に差分を追記する
- マイグレーション実施後は「解消確認記録」に実施日時・担当者を記録する
- 本文書を更新せずにDDLのみ変更することを禁止する
- 実装計画書（`docs/plans/05_implementation-plan.md`）の受入確認で差分が判明した場合は本文書に転記する

---

## 6. 今後の追加差分（問題2・3のDDL修正に伴うもの）

本レビュー（2026-04-01）で判明した以下の修正を canonical DDL に適用する。
適用後、実DBへのマイグレーションが必要。

| 修正対象 | 修正内容 | DDL修正状態 | DB適用状態 |
|---|---|---|---|
| `t_sales_case` | `created_by/updated_by` 追加、FK制約（customer_id/contract_id）追加 | □ 未適用 | □ 未適用 |
| `t_accident_case` | `accident_no` の UNIQUE KEY 削除、通常INDEXに変更 | □ 未適用 | □ 未適用 |

