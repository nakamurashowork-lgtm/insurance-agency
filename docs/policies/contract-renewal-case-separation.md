# t_contract と t_renewal_case の責務分離

## 1. 目的

契約情報を管理する `t_contract` と、満期対応業務を管理する `t_renewal_case` の
責務を明確に分離し、以下を実現する:

- SJNET からの CSV 取込時に、業務運用データを保護する
- 社内で編集可能な情報と、SJNET が原本の情報を明確に区別する
- 変更履歴がクリアに追跡できる
- 画面・Controller・Repository の各層で一貫したルールを保つ

## 2. 基本方針

### 2-1. t_contract は SJNET 同期専用

- **更新主体**: SJNET 取込サービスのみ
- **画面からの編集**: 禁止（参照のみ）
- **役割**: SJNET の契約情報を忠実に反映するミラーテーブル
- **更新頻度**: SJNET 取込時（月数回、1回あたり200〜1000件）

### 2-2. t_renewal_case は業務運用専用

- **更新主体**: 画面からのユーザー操作
- **CSV 取込の扱い**: 新規案件の INSERT のみ。既存案件は原則更新しない
- **役割**: 満期対応業務の実行データを保持する
- **更新頻度**: 日次（担当者の日常業務）

### 2-3. 例外: 満期日（maturity_date）の同期

SJNET で契約の終期が変更された場合は、既存の `t_renewal_case.maturity_date` も
更新する。これは業務上の満期対応日が変わるため例外的に同期する。

- 更新時は変更履歴（t_audit_event）に記録する
- 担当者が気づけるよう、履歴を辿れる状態にする

## 3. カラムの所属

### 3-1. t_contract に持つカラム

SJNET から受信する契約の技術情報:

| カラム | 意味 | SJNET CSV 列 |
|---|---|---|
| policy_no | 証券番号 | S |
| policy_start_date | 保険始期 | P |
| policy_end_date | 保険終期 | Q |
| product_type | 種目種類 | R |
| insurance_category | 保険種類 | - |
| premium_amount | 合計保険料 | W |
| payment_cycle | 払込方法 | T |
| sales_staff_id | SJNET 上の代理店担当者 | AR（代理店コード→マッピング） |

注: 保険会社カラム（insurer_name）は持たない。
本システムは損保ジャパン専用のため不要。詳細は `docs/foundations/01_canonical-schema.md` を参照。

### 3-2. t_renewal_case に持つカラム

社内の業務運用データ:

| カラム | 意味 | 更新主体 |
|---|---|---|
| contract_id | 関連する t_contract の ID | SJNET 取込で初期設定 |
| maturity_date | 業務上の満期対応日（CSV の Q列と同じ値を使用） | SJNET 取込で同期、社内調整可 |
| assigned_staff_id | 案件担当者（業務用） | 画面操作 |
| office_staff_id | 事務担当者 | 画面操作 |
| case_status | 対応状況 | 画面操作 |
| next_action_date | 次回アクション予定日 | 画面操作 |
| expected_premium_amount | 見込保険料 | 画面操作 |
| actual_premium_amount | 確定保険料 | 画面操作 |
| remark | 備考 | 画面操作 |

### 3-3. 担当者の2箇所問題の解決

`t_contract.sales_staff_id` と `t_renewal_case.assigned_staff_id` は **別の意味** を
持つカラムである。一致している必要はない。

| カラム | 意味 | 更新経路 |
|---|---|---|
| `t_contract.sales_staff_id` | SJNET 上の代理店担当者。SJNET の原本 | CSV 取込で常に上書き |
| `t_renewal_case.assigned_staff_id` | 満期案件の業務担当者。社内で自由に変更可能 | 画面操作のみ |

#### 業務シナリオ例

- SJNET では田中さんが契約 A の代理店担当
- 田中さんが退職し、佐藤さんが引き継いだ
- 社内の `t_renewal_case.assigned_staff_id` は佐藤さんに変更される
- SJNET の代理店担当は田中さんのまま（SJNET 側で変更するまで）
- 画面上で満期対応を行うのは佐藤さん

この状態は業務的に正しい。2つの担当者カラムの値が異なることは想定内である。

## 4. SJNET 取込時の動作ルール

具体的な処理フローは `docs/policies/sjnet-csv-import-spec.md` を参照。
本セクションでは **責務分離の観点から見た動作ルール** のみ記述する。

### 4-1. 新規契約の場合

1. `t_contract` に INSERT（全カラム）
   - `policy_end_date` = CSV の Q列（保険終期）
2. `t_renewal_case` にも INSERT
   - `contract_id` = 作成した契約 ID
   - `maturity_date` = `t_contract.policy_end_date` と同じ値（= CSV の Q列）
   - `assigned_staff_id` = SJNET の代理店担当者（初期値として）
   - `case_status` = 'not_started'

**重要**: CSV の B列（満期月）と C列（満期日）は参照しない。
Q列（保険終期）を満期日として扱う。これは業務 Excel の運用と一致する。

### 4-2. 既存契約の場合（UPDATE）

1. `t_contract` を UPDATE（全カラム上書き）
   - 変更があったカラムは `t_audit_event` に記録
2. `t_renewal_case` は原則 **更新しない**
   - 業務運用データを保護する
3. 例外: `t_contract.policy_end_date` が変更された場合のみ、
   `t_renewal_case.maturity_date` も更新する
   - 変更履歴に記録する（source='sjnet_import', actor_label='SJNET取込（満期日同期）'）

### 4-3. 変更履歴の source

SJNET 取込による更新の監査ログは、以下の情報を記録する:

- `source` = 'sjnet_import'
- `actor_user_id` = NULL
- `actor_label` = 'SJNET取込' または 'SJNET取込（満期日同期）'
- `source_ref_id` = 取込バッチ ID

## 5. 画面からの操作ルール

### 5-1. 満期一覧画面（SCR-RENEWAL-LIST）

- `t_renewal_case` から案件一覧を取得
- `t_contract` を JOIN して、証券番号・保険料などを補完表示
- 編集は不可（一覧なので）
- 担当者名は `t_renewal_case.assigned_staff_id` から取得（業務担当者）

### 5-2. 満期詳細画面（SCR-RENEWAL-DETAIL）

- **業務情報セクション**: `t_renewal_case` を表示・編集
  - 担当者、対応状況、次回アクション、コメント
- **契約情報セクション**: `t_contract` を表示（読取専用）
  - 証券番号、始期、終期、保険料
  - 編集ボタンは出さない
- 「契約情報は SJNET から取り込まれた情報です。変更は SJNET 側で行ってください」
  のような説明文を添える

### 5-3. 顧客詳細画面（SCR-CUSTOMER-DETAIL）

- 「保有契約」セクションで `t_contract` を表示
- 各契約から満期詳細（`t_renewal_case`）への導線を提供

### 5-4. 満期日の不一致の扱い

通常 `t_contract.policy_end_date` と `t_renewal_case.maturity_date` は一致する。
一致しない場合（SJNET 側で契約延長があったなど）は、画面上で警告表示する。

表示例:
```
⚠ 契約上の終期（t_contract）: 2026-04-30
  業務上の満期日（t_renewal_case）: 2026-05-31
  ※ SJNET との差異があります
```

## 6. Controller レイヤーの実装ルール

### 6-1. t_renewal_case の更新時は t_contract カラムを受け取らない

`RenewalCaseController::update()` などで、POST データから更新対象カラムを
明示的にホワイトリスト化する。

```php
$allowedColumns = [
    'assigned_staff_id',
    'office_staff_id',
    'case_status',
    'next_action_date',
    'expected_premium_amount',
    'actual_premium_amount',
    'remark',
    // t_contract のカラムは含めない
];

$updateData = array_intersect_key($_POST, array_flip($allowedColumns));
$this->renewalCaseRepo->update($id, $updateData);
```

これは画面レベルの読取専用化に加えて、サーバーレベルでの **二重防御** になる。

### 6-2. t_contract の UPDATE エンドポイントは作らない

Controller に `contract/update` のようなエンドポイントを作ってはならない。
`t_contract` の更新は SJNET 取込サービスのみが行う。

## 7. 禁止事項

### 7-1. 画面から t_contract を編集すること

`t_contract` は SJNET が原本なので、画面から編集してはならない。
誤って編集した場合、次の CSV 取込で元に戻される。

もし編集が必要なケースがあれば、SJNET 側で変更するか、
業務上の調整は `t_renewal_case` で行う。

### 7-2. SJNET 取込で t_renewal_case の業務カラムを更新すること

`t_renewal_case` は社内の業務運用データなので、CSV 取込で上書きしてはならない。
例外は新規案件の INSERT と、`maturity_date` の同期のみ。

特に以下のカラムは絶対に CSV 取込で更新しない:

- `assigned_staff_id`（業務担当者）
- `office_staff_id`（事務担当者）
- `case_status`（対応状況）
- `next_action_date`（次回予定）
- `expected_premium_amount` / `actual_premium_amount`
- `remark`（備考）

### 7-3. カラムの所属を変えること

一度決めた「どのカラムをどのテーブルに持つか」のルールは、
影響範囲が大きいので安易に変更しない。

変更が必要な場合は、本仕様書を更新し、全ての関連コードを同時に修正する。

## 8. 実装例

### 8-1. 満期一覧の SQL

```sql
SELECT
  rc.id AS renewal_case_id,
  rc.maturity_date,
  rc.assigned_staff_id,
  rc.case_status,
  c.id AS contract_id,
  c.policy_no,
  c.product_type,
  c.premium_amount,
  cust.customer_name,
  staff.staff_name AS assigned_staff_name
FROM t_renewal_case rc
INNER JOIN t_contract c ON c.id = rc.contract_id
INNER JOIN m_customer cust ON cust.id = c.customer_id
LEFT JOIN m_staff staff ON staff.id = rc.assigned_staff_id
WHERE rc.is_deleted = 0
ORDER BY rc.maturity_date ASC;
```

注: 担当者名は `rc.assigned_staff_id`（業務担当者）から取得する。
`c.sales_staff_id`（SJNET 担当者）ではない。

### 8-2. 満期詳細画面の構成

```
┌─ 契約情報（読取専用）──────────────┐
│ 証券番号: AB-12345                │
│ 保険始期: 2025-05-01              │
│ 保険終期: 2026-04-30              │
│ 種目: 自動車                       │
│ 保険料: 50,000 円                 │
│                                   │
│ ℹ この情報は SJNET から取り込まれ  │
│   ています。変更は SJNET 側で      │
│   行ってください。                 │
└────────────────────────────────┘

┌─ 業務情報（編集可）────────────────┐
│ 満期日: [2026-04-30]              │
│ 担当者: [佐藤 ▼]                  │
│ 対応状況: [対応中 ▼]              │
│ 次回予定: [2026-04-20]            │
│ 備考: [____________________]      │
│                                   │
│                         [保存]    │
└────────────────────────────────┘
```

## 9. 関連仕様書

| 仕様書 | 関係 |
|---|---|
| `docs/policies/sjnet-csv-import-spec.md` | SJNET 取込の具体的な処理フロー |
| `docs/foundations/01_canonical-schema.md` | 正規スキーマ定義 |
| `docs/screens/renewal-case-list.md` | 満期一覧画面の仕様 |
| `docs/screens/renewal-case-detail.md` | 満期詳細画面の仕様 |
| `docs/screens/customer-detail.md` | 顧客詳細画面の仕様 |

## 10. 本仕様書の位置づけ

本仕様書は、`t_contract` と `t_renewal_case` の責務分離に関する
**正本（Source of Truth）** である。

他の文書と矛盾を発見した場合は、本仕様書を優先し、他の文書を更新する。

## 11. 変更履歴

- 2026-04-09: 初版作成（`t_contract` / `t_renewal_case` の責務分離を明文化）
