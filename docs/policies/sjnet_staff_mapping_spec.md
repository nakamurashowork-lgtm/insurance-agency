# SJNET取込フロー：担当者マッピング統合仕様

## 1. 設計の目的

SJ-NETの満期一覧データには「代理店コード」（例: N8559000）と「担当者名」（例: 飯田）が含まれる。
これをシステムの `m_staff.id` に変換しなければ、取り込んだ契約・満期案件に担当者を自動設定できない。

本仕様は、`m_staff.sjnet_code` カラムを介して代理店コードをスタッフIDに解決し、
取込時に `t_contract.sales_staff_id` および `t_renewal_case.assigned_staff_id` を自動設定するフローを定義する。

---

## 2. SJNETデータに含まれる担当者情報

満期一覧データ（SJ-NETからダウンロードするCSV）の列構造のうち、担当者に関係する列は以下の通り。

| 列番号 | 項目名 | 内容 | マッピング用途 |
|--------|--------|------|----------------|
| 列40 | 拠点コード | 拠点の識別コード | 使用しない（参考） |
| 列41 | 拠点 | 拠点名 | 使用しない（参考） |
| 列42 | 担当者コード | 個人識別コード（SJNETの内部コード） | 参考保持のみ |
| 列43 | 担当者 | 担当者名（テキスト） | `sjnet_staff_name` として保持。マッピングのキーには使用しない |
| 列44 | 代理店コード | 8桁コード（例: N8559000）。担当者1人に1コード割り当て | **マッピングの主キーとして使用** |

**マッピングキーに代理店コード（列44）を使う理由：**
- 担当者名（列43）は表記揺れ（「飯田（ササキ）」等）があり名寄せに使えない
- 担当者コード（列42）はSJNET内部コードであり、テナント管理者が把握しにくい
- 代理店コード（列44）はExcelの選択リストで既にテナントが管理しており、業務実態と一致している

---

## 3. マッピング解決ルール

取込処理の各行に対して以下の順で担当者を解決する。

```
【STEP 1】代理店コード（列44）を取得する
  → 空または取得不可 → staff_mapping_status = NULL でスキップ（担当者なし行として扱う）

【STEP 2】m_staff で sjnet_code = 代理店コード を検索する
  → is_active = 1 のレコードが1件ヒット
      → resolved_staff_id = m_staff.id
      → staff_mapping_status = 'resolved'

  → ヒット0件（マッピング未登録）
      → resolved_staff_id = NULL
      → staff_mapping_status = 'unresolved'
      → 行はエラーにしない（担当者未設定のまま取込を続行する）

  → is_active = 0 のレコードのみヒット（無効化済み）
      → resolved_staff_id = NULL
      → staff_mapping_status = 'inactive'
      → 行はエラーにしない（担当者未設定のまま取込を続行する）

【STEP 3】解決結果を t_sjnet_import_row に記録する
  sjnet_agency_code  = SJNETデータの列44の値
  sjnet_staff_name   = SJNETデータの列43の値
  resolved_staff_id  = 解決したm_staff.id（未解決はNULL）
  staff_mapping_status = 'resolved' / 'unresolved' / 'inactive' / NULL
```

---

## 4. 解決結果の契約・満期案件への反映

マッピング解決後の `resolved_staff_id` を、契約および満期案件の担当者フィールドに設定する。

```
【t_contract への反映】
  新規INSERT時:
    sales_staff_id = resolved_staff_id（NULLの場合はNULLのまま）

  既存UPDATE時:
    sales_staff_id が現在NULLの場合のみ resolved_staff_id で上書きする
    sales_staff_id が既に設定済みの場合は上書きしない（手動設定を優先する）

【t_renewal_case への反映】
  新規INSERT時:
    assigned_staff_id = resolved_staff_id（NULLの場合はNULLのまま）
    office_staff_id   = t_contract.office_staff_id を引き継ぐ（SJNETには事務担当情報がないため）

  既存UPDATE時:
    assigned_staff_id が現在NULLの場合のみ resolved_staff_id で上書きする
    assigned_staff_id が既に設定済みの場合は上書きしない（手動設定を優先する）
```

---

## 5. 取込結果サマリへの追加表示

`t_sjnet_import_batch` の結果表示に、担当者マッピング状況を追加する。

```
【取込結果サマリ（追加項目）】
  担当者解決済み:   N件（staff_mapping_status = 'resolved'）
  マッピング未登録: N件（staff_mapping_status = 'unresolved'）
  無効コード:       N件（staff_mapping_status = 'inactive'）
```

`unresolved` が0件以上ある場合、以下のメッセージを取込結果に表示する。

```
「N件の代理店コードがマッピング未登録です。テナント設定 > 担当者 で sjnet_code を登録してください。」
  → テナント設定へのリンクを付与する
```

---

## 6. マッピング未設定時の業務影響

マッピングが未設定の場合でも取込は成功する。ただし以下の影響が生じる。

| 影響箇所 | 具体的な影響 |
|----------|--------------|
| t_contract.sales_staff_id | NULL のまま登録される |
| t_renewal_case.assigned_staff_id | NULL のまま登録される |
| 満期案件一覧の担当者フィルタ | 「担当者未設定」として表示される |
| 担当者別集計 | その案件が集計から漏れる |

マッピング未設定の状態で運用を開始すると、満期対応の漏れが生じるリスクがある。
初回SJNET取込前に、全代理店コードのマッピング登録（`m_staff.sjnet_code`）を完了させることを強く推奨する。

---

## 7. テナント設定での管理

SJNETコードは `m_staff` テーブルの `sjnet_code` カラムで管理する。
テナント設定画面の「担当者」タブから登録・編集が可能。

- 1人の担当者に1つのsjnet_codeを割り当てる（UNIQUE KEY）
- `is_active = 0` に設定すると取込解決から除外される（`inactive`扱い）

---

## 8. 取込フロー全体（担当者マッピング統合後）

```
SJNETデータ（CSV）
  ↓
t_sjnet_import_batch を INSERT（running）
  ↓
行ループ
  ├─ 列44（代理店コード）を取得
  ├─ m_staff.sjnet_code で検索 → resolved_staff_id を解決
  ├─ 列19（証券番号）で t_contract を検索
  │    → ヒット: UPDATE（sales_staff_idが未設定の場合のみ上書き）
  │    → ミス:   INSERT（sales_staff_id = resolved_staff_id）
  ├─ 列2-3（満期日）で t_renewal_case を検索（contract_id + maturity_date）
  │    → ヒット: UPDATE（assigned_staff_idが未設定の場合のみ上書き）
  │    → ミス:   INSERT（assigned_staff_id = resolved_staff_id）
  └─ t_sjnet_import_row を INSERT
       sjnet_agency_code       = 列44
       sjnet_staff_name        = 列43
       resolved_staff_id       = 解決結果（m_staff.id）
       staff_mapping_status    = 'resolved'/'unresolved'/'inactive'/NULL
       matched_contract_id     = 紐づけ結果
       matched_renewal_case_id = 紐づけ結果
       row_status              = 'insert'/'update'/'skip'/'error'
  ↓
全行完了 → t_sjnet_import_batch を UPDATE（success/partial/failed）
  ↓
取込結果表示
  ├─ サマリ（insert/update/skip/error/resolved/unresolved/inactive件数）
  └─ unresolved が1件以上の場合: テナント設定へのリンク付きメッセージ
```
