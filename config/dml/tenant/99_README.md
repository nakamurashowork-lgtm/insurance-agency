# config/dml/tenant/ — 動作確認用 DML（テナントDB）

## 概要

テナント DB（xs000001_te001 等）に投入するテストデータ。  
業務シナリオを網羅した少量データセットで、画面・検索・詳細・更新・通知バッチを一通り検証できる。

---

## ファイル一覧と投入順序

| 順序 | ファイル | テーブル | 件数 | ID範囲 |
|---|---|---|---|---|
| 1 | 00_cleanup.sql | （全テーブル） | — | 一括削除用 |
| 2 | 01_m_customer.sql | m_customer | 20 | 1001-1020 |
| 3 | ~~02_m_customer_contact.sql~~ | ~~m_customer_contact~~ | **DDL未存在のためスキップ** | — |
| 4 | 03_t_contract.sql | t_contract | 50 | 2001-2050 |
| 5 | 04_t_renewal_case.sql | t_renewal_case | 100 | 3001-3100 |
| 6 | 05_t_accident_case.sql | t_accident_case | 15 | 4001-4015 |
| 7 | 06_t_accident_reminder_rule.sql | t_accident_reminder_rule | 8 | 5001-5008 |
| 8 | 07_t_accident_reminder_rule_weekday.sql | t_accident_reminder_rule_weekday | 20 | — |
| 9 | 08_t_case_comment.sql | t_case_comment | 30 | 6001-6030 |
| 10 | 09_t_sales_performance.sql | t_sales_performance | 50 | 7001-7050 |
| 11 | 10_t_activity.sql | t_activity | 50 | 8001-8050 |
| 12 | 11_t_sales_case.sql | t_sales_case | 10 | 9001-9010 |
| 13 | 12_t_audit_event.sql | t_audit_event | 50 | 10001-10050 |
| 14 | 13_t_audit_event_detail.sql | t_audit_event_detail | 100 | 11001-11100 |

**備考:** `m_customer_contact` は DDL（`config/ddl/tenant/m_customer_contact.sql`）が存在しないためスキップ。DDL 追加後に DML も整備すること。

---

## 投入前提

- tenant DB が存在し、canonical DDL が適用済みであること
- common DB の users テーブルに `id=1`（管理者）、`id=2`（一般ユーザー）が存在すること  
  → `config/dml/common/01_users.sql` で投入可能
- tenant DB の m_staff テーブルに `id=1`、`id=2` が存在すること  
  → `assigned_staff_id` / `office_staff_id` / `staff_id` の FK 参照先として必要  
  → DDL 上は外部キー制約なし（コメントのみ参照）のため、未存在でも INSERT は成功するが、画面表示が不正になる可能性あり

---

## phpMyAdmin での投入手順

1. phpMyAdmin を開く
2. 対象テナント DB（例: `xs000001_te001`）を選択
3. 「SQL」タブを開く
4. 各ファイルの内容を上記順序でコピペして「実行」
5. または「インポート」タブでファイルをアップロード

---

## クリーンアップ

```sql
-- tenant DB で実行
source config/dml/tenant/00_cleanup.sql
```

または phpMyAdmin の SQL タブで `00_cleanup.sql` の内容を貼り付けて実行。

ID 範囲指定で削除するため、**既存データへの影響はない。**

---

## 再投入

クリーンアップ → 投入の順で実行すれば何度でも再現できる。

```
1. 00_cleanup.sql を実行（既存テストデータを削除）
2. 01_m_customer.sql ～ 13_t_audit_event_detail.sql を順に実行
```

---

## 業務シナリオの確認ポイント

### 満期一覧（満期案件）

| シナリオ | 対象案件 ID | 確認内容 |
|---|---|---|
| 未対応（30日以内）| 3002, 3010, 3014, 3024, 3032, 3036, 3044, 3050, 3060, 3088 | 件数 10件、today+30 以内に絞り込み |
| 対応中（SJ依頼済み）| 3004, 3020, 3064, 3092 | sj_requested |
| 対応中（書類準備）| 3016, 3056, 3094 | doc_prepared |
| 対応中（見積送付）| 3012 | quote_sent |
| 対応中（返却待ち）| 3090 | waiting_return |
| 対応中（払込待ち）| 3028 | waiting_payment |
| 最近完了（更改済み）| 3018 | completed + renewed + 2026-03 |
| 失注 | 3022, 3068, 3072, 3078, 3082 | completed + lost |

### 事故案件一覧

| シナリオ | 対象案件 ID | 確認内容 |
|---|---|---|
| 未対応 | 4001, 4002, 4003 | accepted |
| 保険会社連絡済み | 4004, 4005, 4006 | linked |
| 対応中（リマインドあり）| 4007, 4008, 4009 | in_progress + reminder enabled |
| 書類待ち | 4010, 4011, 4012 | waiting_docs |
| 解決済み | 4013, 4014 | resolved |
| 完了（リマインド無効）| 4015 | closed + reminder disabled |

### 顧客一覧

| シナリオ | 顧客 ID | 確認内容 |
|---|---|---|
| 重要法人（複数契約）| 1001, 1020 | 契約6〜7件、活動・コメント多数 |
| 見込み顧客 | 1011, 1012, 1013 | prospect、営業案件あり |
| 解約済み | 1017, 1018, 1019 | closed、新規案件なし |

---

## 既存データへの影響範囲

| テーブル | 影響範囲 |
|---|---|
| m_customer | id 1001〜1020 のみ（既存の id=1 等には触れない） |
| t_contract | id 2001〜2050 のみ |
| t_renewal_case | id 3001〜3100 のみ |
| t_accident_case | id 4001〜4015 のみ |
| t_accident_reminder_rule | id 5001〜5008 のみ |
| t_accident_reminder_rule_weekday | accident_reminder_rule_id 5001〜5008 に紐付くもののみ |
| t_case_comment | id 6001〜6030 のみ |
| t_sales_performance | id 7001〜7050 のみ |
| t_activity | id 8001〜8050 のみ |
| t_sales_case | id 9001〜9010 のみ |
| t_audit_event | id 10001〜10050 のみ |
| t_audit_event_detail | id 11001〜11100 のみ |
