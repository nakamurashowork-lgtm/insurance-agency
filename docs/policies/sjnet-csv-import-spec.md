# SJNET 満期一覧 CSV 取込仕様

## 1. 位置づけ

本文書は、SJ-NET からダウンロードした満期一覧 CSV を満期一覧画面（SCR-RENEWAL-LIST）から取り込み、`t_contract`・`t_renewal_case`・`m_customer` を自動登録・更新する処理の正式仕様である。

担当者マッピングの詳細は `docs/policies/sjnet_staff_mapping_spec.md` を参照する。本文書はそれを上位仕様として包含し、CSV カラム定義・取込ロジック・年決定ルールを追加定義する。

---

## 2. 対象ファイル

- SJ-NET「満期進捗管理」から「契約一覧表」としてダウンロードした CSV
- 文字コード: Shift-JIS（BOM なし）を基本とする。UTF-8 も許容する
- ヘッダ行: 1行目がヘッダ。2行目以降がデータ行
- 列数: 44列固定

運用前提: 毎月、満期2ヶ月前の月初に該当月の満期データをダウンロードして取り込む。

---

## 3. CSV カラム定義（全44列）

| 列番号 | 列名 | 取込用途 | 対応カラム |
|---|---|---|---|
| 1 (A) | 満期契約の識別 | 不使用 | — |
| 2 (B) | 満期日（月） | 満期日組み立てに使用 | `t_renewal_case.maturity_date`（月） |
| 3 (C) | 満期日（日） | 満期日組み立てに使用 | `t_renewal_case.maturity_date`（日） |
| 4 (D) | 顧客名 | 顧客名寄せ・新規登録 | `m_customer.customer_name` |
| 5 (E) | 生年月日 | 不使用 | — |
| 6 (F) | 郵便番号 | 顧客新規登録時のみ使用 | `m_customer.postal_code` |
| 7 (G) | 住所 | 顧客新規登録時のみ使用 | `m_customer.address1` |
| 8 (H) | ＴＥＬ | 顧客新規登録時のみ使用 | `m_customer.phone` |
| 9 (I) | ＦＡＸ | 不使用 | — |
| 10 (J) | 携帯ＴＥＬ | 不使用 | — |
| 11 (K) | 更改状況 | 不使用 | — |
| 12 (L) | 世帯主名 | 不使用 | — |
| 13 (M) | 続柄 | 不使用 | — |
| 14 (N) | （空列） | スキップ | — |
| 15 (O) | 保険会社 | 契約登録・更新 | `t_contract.insurer_name` |
| 16 (P) | 保険始期 | 契約登録・更新 | `t_contract.policy_start_date` |
| 17 (Q) | 保険終期 | 契約登録・更新 | `t_contract.policy_end_date` |
| 18 (R) | 種目種類 | 契約登録・更新 | `t_contract.product_type` |
| 19 (S) | 証券番号 | **取込の主キー** | `t_contract.policy_no` |
| 20 (T) | 払込方法 | 契約登録・更新 | `t_contract.payment_cycle` |
| 21 (U) | 回数 | 不使用 | — |
| 22 (V) | 件数 | 不使用 | — |
| 23 (W) | 合計保険料 | 契約登録・更新 | `t_contract.premium_amount` |
| 24 (X) | 満期返れい金 | 不使用 | — |
| 25〜30 | 保険金額1〜3・単位 | 不使用 | — |
| 31 (AE) | 契約状況 | 不使用 | — |
| 32〜36 | 事故・入替・質権・共保・代分 | 不使用 | — |
| 37 (AK) | 保険の対象等 | 不使用 | — |
| 38 (AL) | （空ラベル） | スキップ | — |
| 39 (AM) | （空列） | スキップ | — |
| 40 (AN) | 拠点コード | 不使用（参考） | — |
| 41 (AO) | 拠点 | 不使用 | — |
| 42 (AP) | 担当者コード | 不使用（参考） | `t_sjnet_import_row` に保持 |
| 43 (AQ) | 担当者 | 参考保持のみ | `t_sjnet_import_row.sjnet_staff_name` |
| 44 (AR) | 代理店コード | **担当者マッピング主キー** | `m_sjnet_staff_mapping` 経由で解決 |

---

## 4. 満期年の決定ルール

SJNETのCSVには満期日の「月」と「日」のみが含まれ、「年」が含まれない。
以下のルールで取込実行日から満期年を決定する。

```
満期月日 = 列2（月） + 列3（日）

候補A = 取込実行年 - 満期月日
候補B = 取込実行年 + 1 - 満期月日

取込実行日 <= 候補A → 候補A（当年）を満期日とする
取込実行日 >  候補A → 候補B（翌年）を満期日とする
```

**例（取込日: 2026-02-01）**

| CSVの月日 | 候補A | 判定 | 採用 |
|---|---|---|---|
| 4月30日 | 2026-04-30 | 取込日≦候補A | 2026-04-30（当年） |
| 1月15日 | 2026-01-15 | 取込日＞候補A | 2027-01-15（翌年） |

この判定は「毎月、満期2ヶ月前の月初に取り込む」という現行運用と完全に整合する。

---

## 5. 取込ロジック

### 5-1. 行単位の処理フロー

```
【STEP 1】証券番号（列19）を取得する
  → 空の場合: row_status = 'skip' としてスキップ

【STEP 2】顧客の解決（列4: 顧客名）
  → m_customer.customer_name で完全一致検索（is_deleted = 0）
  → 1件ヒット: そのcustomer_idを使用
  → 0件ヒット: m_customer に新規 INSERT
      customer_name = 列4
      postal_code   = 列6（空の場合はNULL）
      address1      = 列7（空の場合はNULL）
      phone         = 列8（空の場合はNULL）
      customer_type = 'individual'（デフォルト）
      status        = 'active'
      created_by / updated_by = システムユーザーID（取込専用）
  → 2件以上ヒット: この行は row_status = 'error'、error_type = 'ambiguous_customer' として記録する
    STEP 4・STEP 5 は実行せず、この行の契約・満期案件の登録・更新をスキップする
    取込全体は続行する（他の行に影響しない）

【STEP 3】担当者の解決（列44: 代理店コード）
  → docs/policies/sjnet_staff_mapping_spec.md のルールに従って解決

【STEP 4】契約の登録・更新（列19: 証券番号）
  ※ STEP 2 で ambiguous_customer となった行はこのステップを実行しない
  → t_contract.policy_no で検索（is_deleted = 0）
  → ヒット（UPDATE):
      insurer_name       = 列15
      policy_start_date  = 列16（日付変換）
      policy_end_date    = 列17（日付変換）
      product_type       = 列18
      payment_cycle      = 列20
      premium_amount     = 列23（数値変換）
      sales_user_id      = resolved_staff_user_id（NULL の場合は上書きしない）
      last_sjnet_imported_at = 取込実行日時
      ※ customer_id・customer_type は UPDATE しない（既存の顧客紐づけを維持する）
  → ミス（INSERT):
      customer_id        = STEP 2 で解決した customer_id
      policy_no          = 列19
      insurer_name       = 列15
      policy_start_date  = 列16
      policy_end_date    = 列17
      product_type       = 列18
      payment_cycle      = 列20
      premium_amount     = 列23
      sales_user_id      = resolved_staff_user_id
      status             = 'active'
      last_sjnet_imported_at = 取込実行日時

【STEP 5】満期案件の登録・更新
  → contract_id + maturity_date（STEP 4 の contract_id と STEP から決定した満期日）で
    t_renewal_case を検索（is_deleted = 0）
  → ヒット（UPDATE):
      assigned_user_id = resolved_staff_user_id（既に設定済みの場合は上書きしない）
  → ミス（INSERT):
      contract_id      = STEP 4 の contract_id
      maturity_date    = 満期年決定ルールで算出した DATE
      case_status      = 'open'
      assigned_user_id = resolved_staff_user_id
      office_user_id   = t_contract.office_user_id を引き継ぐ

【STEP 6】t_sjnet_import_row に結果を記録する
  row_status = 'insert' / 'update' / 'skip' / 'error'
```

### 5-2. スキップ対象

以下の行はエラーにせずスキップする。

- 証券番号（列19）が空の行
- 顧客名（列4）が空の行
- 満期月（列2）または満期日（列3）が空の行

---

## 6. 取込結果の表示

`docs/policies/sjnet_staff_mapping_spec.md` セクション5 の担当者マッピング結果表示に加え、以下を表示する。

```
【取込結果サマリ】
  処理行数:           N 行
  契約 新規登録:      N 件
  契約 更新:          N 件
  顧客 自動登録:      N 件
  スキップ:           N 行
  エラー:             N 行（顧客名重複を含む）

【担当者マッピング】（sjnet_staff_mapping_spec.md 準拠）
  担当者解決済み:     N 件
  マッピング未登録:   N 件
  無効コード:         N 件
```

エラー行がある場合、サマリの下にエラー行一覧を表示する。

| 行番号 | 証券番号 | 顧客名 | エラー種別 | 対応方法 |
|---|---|---|---|---|
| 3 | AB1234567 | 山田 太郎 | 顧客名重複 | 顧客一覧で名寄せ後、手動で契約を登録してください |
| 7 | — | 鈴木 花子 | 顧客名空欄 | スキップされました |

顧客名重複（ambiguous_customer）が1件以上ある場合:
> 「N件の顧客名が複数一致しました。該当行の契約・満期案件は登録されていません。顧客一覧で名寄せを行ってから、手動で登録してください。」
> → 顧客一覧へのリンクを付与する

---

## 7. 画面仕様（SCR-RENEWAL-LIST への追加）

`docs/screens/renewal-case-list.md` セクション4-1 に記載の「CSV取込ボタン（補助操作）」に対応する。

### 7-1. CSV取込ダイアログ

- タイトル: SJNET満期データ取込
- 入力: CSVファイル選択（1ファイル）
- 実行ボタン: 取込を実行する
- キャンセル: ダイアログを閉じる

### 7-2. 取込結果パネル（ダイアログ内）

取込完了後にダイアログ内に表示する。

- サマリ（セクション6 の内容）
- 顧客名重複の警告（該当がある場合）
- マッピング未登録の警告（該当がある場合）

---

## 8. 参照ドキュメント

- `docs/policies/sjnet_staff_mapping_spec.md` — 担当者マッピング仕様（本仕様の下位仕様）
- `docs/screens/renewal-case-list.md` — 満期一覧画面仕様
- `config/ddl/tenant/t_contract.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/m_customer.sql`
- `config/ddl/tenant/t_sjnet_import_batch.sql`
- `config/ddl/tenant/t_sjnet_import_row.sql`

---

## 9. 廃止事項

以下は本仕様の確定に伴い廃止する。

- `docs/screens/sales-performance-list.md` セクション18（成績管理簿 CSV 取込仕様）
  → 成績管理簿 CSV 取込は業務上不要のため削除する
- `docs/plans/05_implementation-plan.md` Phase 4C（成績管理簿対応 CSV 取込フェーズ）
  → 同上の理由により廃止する
