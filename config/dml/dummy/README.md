# config/dml/dummy/ — ダミーデータ（業務サンプル）

## 概要

業務を模した動作確認用のサンプルデータ。
**検証環境のみ**に投入する。**本番環境には絶対に投入しないこと。**

画面動作・検索・詳細・更新・通知などを一通り検証できる網羅セット。

**基準日:** 2026-04-21（このデータは当日を前提に日付を配置している）

---

## 重要: 値の形式について

本 dummy データは、現行マスタ（`config/dml/master/tenant/`）のシード値 = **日本語ラベル**を直接使用している。したがって、master 投入後に dummy を投入すればそのまま整合する。

- `t_renewal_case.case_status` → `m_case_status(case_type='renewal')` の name（未対応 / SJ依頼中 / 書類作成済 / 返送待ち / 見積送付済 / 入金待ち / 完了 / 取り下げ / 失注 / 解約）
- `t_accident_case.status` → `m_case_status(case_type='accident')` の name（受付 / 保険会社連絡済み / 対応中 / 書類待ち / 解決済み / 完了）
- `t_sales_case.status` → `m_sales_case_status` の **protected name のみ**（商談中 / 交渉中 / 成約 / 失注 / 保留）
- `t_activity.activity_type` → `m_activity_type`（訪問 / 電話 / メール / オンライン / 会議 / 研修 / その他）
- `t_activity.purpose_type` → `m_activity_purpose_type`（満期対応 / 新規開拓 / クロスセル提案 / 事故対応 / 見積対応 / 保全対応 / 苦情対応 / その他）
- `t_renewal_case.renewal_method` / `procedure_method` → `m_renewal_method` / `m_procedure_method`（対面 / 郵送 / 署名・捺印 等）

コード定数系（dummy でも英字コード使用）:
- `t_accident_case.priority` → `low / normal / high`
- `m_customer.customer_type` → `individual / corporate`
- `m_customer.status` → `prospect / active / inactive / closed`
- `t_contract.status` → `active / renewal_pending / expired / cancelled / inactive`
- `t_sales_performance.performance_type` → `new / renewal / addition / change / cancel_deduction`
- `t_sales_performance.source_type` → `non_life / life`
- `t_sales_performance.sales_channel` → `direct / motor_dealer / agency_referral / customer_referral / group / other`
- `t_sales_case.case_type` → `new / renewal / cross_sell / up_sell / other`
- `t_sales_case.prospect_rank` → `A / B / C`

**旧 dummy が使っていた英字コード値（`not_started`, `accepted`, `open`, `visit` 等）は本版では使用しない。** `config/dml/migration/` の rename スクリプトは、旧値が残っている既存 DB 向けの一回限りの補正手段であり、本 dummy の投入前後に走らせる必要はない。

---

## フォルダ構成

```
config/dml/dummy/
├── README.md                          ← 本ファイル
├── common/                            ← common DB
│   ├── 01_users.sql
│   └── 02_user_tenants.sql
└── tenant/                            ← テナント DB
    ├── 00_cleanup.sql
    ├── 01_m_staff.sql
    ├── 02_m_customer.sql
    ├── 03_t_contract.sql
    ├── 04_t_renewal_case.sql
    ├── 05_t_accident_case.sql
    ├── 06_t_sales_case.sql
    ├── 07_t_activity.sql
    ├── 08_t_sales_performance.sql
    ├── 09_t_sales_target.sql
    └── 10_t_case_comment.sql
```

---

## 投入前提

1. 対象 DB に DDL が適用済みであること
2. `config/dml/master/` のマスターデータが投入済みであること  
   （`m_case_status`, `m_sales_case_status`, `m_activity_type`, `m_activity_purpose_type`, `m_renewal_method`, `m_procedure_method`, `m_product_category`, `seed_internal_customer` の 8 ファイル）
3. `common.tenants` に `TE001` レコードが存在すること

---

## ファイル一覧と投入順序

### common DB

| 順序 | ファイル | テーブル | 件数 | ID範囲 |
|---|---|---|---|---|
| 1 | `common/01_users.sql` | `users` | 5 | 1, 2, 3, 4, 99 |
| 2 | `common/02_user_tenants.sql` | `user_tenants` | 5 | — |

### テナント DB

| 順序 | ファイル | テーブル | 件数 | ID範囲 |
|---|---|---|---|---|
| 0 | `tenant/00_cleanup.sql` | （全テーブル） | — | 再投入時に先に実行 |
| 1 | `tenant/01_m_staff.sql` | `m_staff` | 4 | 1 – 4 |
| 2 | `tenant/02_m_customer.sql` | `m_customer` | 12 | 1001 – 1012 |
| 3 | `tenant/03_t_contract.sql` | `t_contract` | 20 | 2001 – 2020 |
| 4 | `tenant/04_t_renewal_case.sql` | `t_renewal_case` | 18 | 3001 – 3018 |
| 5 | `tenant/05_t_accident_case.sql` | `t_accident_case` | 12 | 4001 – 4012 |
| 6 | `tenant/06_t_sales_case.sql` | `t_sales_case` | 10 | 9001 – 9010 |
| 7 | `tenant/07_t_activity.sql` | `t_activity` | 30 | 8001 – 8030 |
| 8 | `tenant/08_t_sales_performance.sql` | `t_sales_performance` | 40 | 7001 – 7040 |
| 9 | `tenant/09_t_sales_target.sql` | `t_sales_target` | 28 | 12001 – 12028 |
| 10 | `tenant/10_t_case_comment.sql` | `t_case_comment` | 12 | 6001 – 6012 |

---

## 投入手順

### phpMyAdmin

1. common DB（例: `xs000001_admin`）を選択して `common/01_users.sql`, `02_user_tenants.sql` を順に実行
2. テナント DB（例: `xs000001_te001`）を選択して `tenant/00_cleanup.sql` 以降を順に実行

### コマンドライン（MySQL CLI）

```bash
# common DB
mysql --default-character-set=utf8mb4 -u root xs000001_admin < config/dml/dummy/common/01_users.sql
mysql --default-character-set=utf8mb4 -u root xs000001_admin < config/dml/dummy/common/02_user_tenants.sql

# tenant DB
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/00_cleanup.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/01_m_staff.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/02_m_customer.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/03_t_contract.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/04_t_renewal_case.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/05_t_accident_case.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/06_t_sales_case.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/07_t_activity.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/08_t_sales_performance.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/09_t_sales_target.sql
mysql --default-character-set=utf8mb4 -u root xs000001_te001 < config/dml/dummy/tenant/10_t_case_comment.sql
```

---

## 再投入（クリーンアップ → 再適用）

```
1. tenant/00_cleanup.sql を実行（ID範囲指定でダミーデータのみ削除）
2. tenant/01 〜 10 を順に実行
```

`00_cleanup.sql` は **ID 範囲指定** の DELETE のため、master のマスターデータや手動で追加した別 ID のレコードには影響しない。

---

## サンプルの業務シナリオ

### 顧客（12件）

| ID | 種別 | ステータス | 内容 |
|---|---|---|---|
| 1001 | 法人 | active | 重要顧客（フリート・火災・賠責・労災） |
| 1002 | 法人 | active | 運輸フリート |
| 1003 | 法人 | active | 担当者なし・契約なし（新規登録のみ） |
| 1004 | 個人 | active | 山田（自動車・傷害・火災） |
| 1005 | 個人 | active | 佐藤（自動車・火災・生保） |
| 1006 | 個人 | active | 高橋（傷害・医療・生保） |
| 1007 | 個人 | active | 渡辺（最近更改） |
| 1008 | 個人 | prospect | 紹介案件 |
| 1009 | 法人 | prospect | 新規開拓中 |
| 1010 | 個人 | inactive | 連絡不通 |
| 1011 | 法人 | inactive | 事業縮小 |
| 1012 | 法人 | closed | 廃業 |

### 満期案件（18件、全 case_status を網羅）

| ID | case_status | 契約 | 満期日 | 備考 |
|---|---|---|---|---|
| 3001 | 未対応 | 2001 | 2026-04-25 | 7日以内・法人フリート |
| 3002 | 未対応 | 2008 | 2026-04-28 | 7日以内・個人自動車 |
| 3003 | 未対応 | 2007 | 2026-06-20 | 60日先 |
| 3004 | SJ依頼中 | 2002 | 2026-05-01 | |
| 3005 | SJ依頼中 | 2006 | 2026-05-05 | |
| 3006 | 書類作成済 | 2004 | 2026-05-10 | 運輸フリート |
| 3007 | 返送待ち | 2009 | 2026-05-15 | |
| 3008 | 見積送付済 | 2003 | 2026-06-10 | |
| 3009 | 見積送付済 | 2010 | 2026-07-15 | |
| 3010 | 入金待ち | 2017 | 2026-08-01 | |
| 3011 | 完了 (renewed) | 2005 | 2026-01-10 | FY2025完了 |
| 3012 | 完了 (renewed) | 2012 | 2026-01-01 | FY2025完了 |
| 3013 | 完了 (renewed) | 2018 | 2026-02-01 | FY2025完了 |
| 3014 | 失注 | 2013 | 2025-05-01 | 連絡不通 |
| 3015 | 失注 | 2014 | 2026-03-01 | 事業縮小 |
| 3016 | 取り下げ | 2020 | 2026-11-01 | 重複申込 |
| 3017 | 解約 | 2015 | 2025-04-01 | 廃業 |
| 3018 | 解約 | 2016 | 2025-06-01 | 廃業 |

### 事故案件（12件、全 status × 3 優先度を網羅）

| ID | status | priority | 顧客 | 契約 |
|---|---|---|---|---|
| 4001 | 受付 | normal | 1001 | 2001 |
| 4002 | 受付 | high | 1004 | 2006 |
| 4003 | 保険会社連絡済み | high | （未登録） | — |
| 4004 | 対応中 | high | 1002 | 2004 |
| 4005 | 対応中 | normal | 1005 | 2008 |
| 4006 | 対応中 | low | 1006 | 2010 |
| 4007 | 書類待ち | normal | 1001 | 2002 |
| 4008 | 解決済み | normal | 1004 | 2006 |
| 4009 | 解決済み | low | 1007 | 2012 |
| 4010 | 完了 | normal | 1002 | 2004 |
| 4011 | 完了 | normal | 1005 | 2008 |
| 4012 | 完了 | low | 1001 | 2001 |

### 見込案件（10件、protected status のみ）

| ID | status | 顧客 | rank | 備考 |
|---|---|---|---|---|
| 9001 | 商談中 | 1001 | A | クロスセル・役員生保 |
| 9002 | 商談中 | 1008 | B | 新規（紹介）|
| 9003 | 商談中 | 1009 | A | 新規（飛び込み）|
| 9004 | 交渉中 | 1002 | A | up_sell・追加車両 |
| 9005 | 交渉中 | （未登録） | B | 新規・飛び込み |
| 9006 | 保留 | 1006 | C | 生保乗換検討 |
| 9007 | 成約 | 1004 | A | 契約 2020 として成約 |
| 9008 | 成約 | 1002 | A | 契約 2018 として成約 |
| 9009 | 失注 | （未登録） | C | 競合負け |
| 9010 | 失注 | 1007 | B | 提案不採用 |

> `m_sales_case_status` には `提案中` / `ヒアリング中` / `アプローチ中` / `見込み` も存在するが、これらは旧データ互換用で protected=0。dummy データでは使用しない。

### 活動履歴（30件）

- activity_type 分布: 訪問 / 電話 / メール / オンライン / 会議 / その他 を網羅
- purpose_type 分布: 満期対応 / 新規開拓 / クロスセル提案 / 事故対応 / 見積対応 / 保全対応 / その他 を網羅
- 直近 30 日（2026-03-22〜2026-04-21）中心に分散、複数日・空日混在
- 担当者 staff_id = 1 / 2 / 3 に分散
- 紐付け先: 顧客のみ / 契約 / 満期案件 / 事故案件 / 見込案件 / 未紐付け（社内会議）を含む

### 成績（40件）

- FY2025 （2025-04 〜 2026-03）24 件 + FY2026 （2026-04）16 件
- performance_type: new / renewal / addition / change / cancel_deduction を全て使用
- source_type: non_life / life 両方
- sales_channel: direct / motor_dealer / agency_referral / customer_referral / group / other を全て使用
- 未登録顧客（`prospect_name` のみ）を 3 件含む

### 営業目標（28件）

- FY2026 (2026) の `premium_total` 目標
- チーム全体 年度目標 1 件 + 月次 12 件
- 中村 翔（user_id=2）年度目標 1 件 + 月次 12 件
- 田中 次郎（user_id=3）年度目標 1 件 + 当月分 1 件

### 案件コメント（12件）

- 満期案件（3001, 3004, 3010）に 3〜3〜2 件のスレッド
- 事故案件（4002, 4004, 4007）に 2〜2〜1 件のスレッド

---

## 本番環境への誤投入防止

- 全ファイルに `投入先: 検証環境のみ（本番禁止）` コメントを明記
- ID は 1000 番台以降で固定（users=1桁, 顧客=1001-, 契約=2001-, 満期=3001-, 事故=4001-, 見込=9001-, 活動=8001-, 成績=7001-, 目標=12001-, コメント=6001-）
- 本番環境に誤投入した場合の復旧は `00_cleanup.sql` で削除可能だが、関連する監査ログ・通知履歴まではクリーンアップされないため、注意深く運用すること

---

## 既存データへの影響範囲

| テーブル | 影響範囲 |
|---|---|
| `users` | id 1, 2, 3, 4, 99 のみ（INSERT IGNORE） |
| `user_tenants` | (user_id, tenant_code) 重複はスキップ（INSERT IGNORE） |
| `m_staff` | id 1–4 のみ（ON DUPLICATE KEY UPDATE） |
| `m_customer` | id 1001–1012 のみ |
| `t_contract` | id 2001–2020 のみ |
| `t_renewal_case` | id 3001–3018 のみ |
| `t_accident_case` | id 4001–4012 のみ |
| `t_sales_case` | id 9001–9010 のみ |
| `t_activity` | id 8001–8030 のみ |
| `t_sales_performance` | id 7001–7040 のみ |
| `t_sales_target` | id 12001–12028 のみ |
| `t_case_comment` | id 6001–6012 のみ |
