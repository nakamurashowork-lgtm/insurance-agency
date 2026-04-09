# SJNET 満期一覧 CSV 取込仕様

## 1. 位置づけ

本文書は、SJ-NET からダウンロードした満期一覧 CSV を満期一覧画面（SCR-RENEWAL-LIST）から取り込み、`t_contract`・`t_renewal_case`・`m_customer` を自動登録・更新する処理の正式仕様である。

担当者マッピングの詳細は `docs/policies/sjnet_staff_mapping_spec.md` を参照する。本文書はそれを上位仕様として包含し、CSV カラム定義・取込ロジック・満期日決定ルールを追加定義する。

---

## 1-1. 関連仕様書

本文書は SJNET 取込の **具体的な処理フロー** を定義する。

`t_contract` と `t_renewal_case` の **責務分離の設計原則**（どのカラムをどちらのテーブルで持つか、画面からの編集可否、CSV 取込で更新してよいカラムとそうでないカラムの区別）については、`docs/policies/contract-renewal-case-separation.md` を参照する。

本文書は責務分離仕様の制約下で実装する。両者に不整合がある場合は、責務分離仕様を優先する。

---

## 2. 対象ファイル

- SJ-NET「満期進捗管理」から「契約一覧表」としてダウンロードした CSV
- 文字コード: Shift-JIS（BOM なし）を基本とする。UTF-8 も許容する
- ヘッダ行: 1行目がヘッダ。2行目以降がデータ行
- 列数: 44列固定

運用前提: 毎月、満期2ヶ月前の月初に該当月の満期データをダウンロードして取り込む。

---

## 3. CSV カラム定義（全44列）

カラムは **必須**・**任意**・**不使用** の3種に分類する。
必須カラムが1つでも欠けている場合、そのヘッダを持つCSVは取込できない。
任意カラムは存在すれば取り込み、なくてもエラーにしない。
不使用カラムは読み飛ばす。

### 必須カラム

| 列番号 | 列名 | 取込用途 | 対応カラム |
|---|---|---|---|
| 4 (D) | **顧客名** | 顧客名寄せ・新規登録 | `m_customer.customer_name` |
| 16 (P) | **保険始期** | 契約登録・更新 | `t_contract.policy_start_date` |
| 17 (Q) | **保険終期** | 契約の識別キー（policy_no との複合）・契約登録・更新・満期日 | `t_contract.policy_end_date` / `t_renewal_case.maturity_date` |
| 18 (R) | **種目種類** | 契約登録・更新 | `t_contract.product_type` |
| 19 (S) | **証券番号** | 契約の識別キー（policy_end_date との複合）| `t_contract.policy_no` |
| 23 (W) | **合計保険料** | 契約登録・更新 | `t_contract.premium_amount` |
| 44 (AR) | **代理店ｺｰﾄﾞ**（半角カタカナ） | 担当者マッピング主キー | `m_sjnet_staff_mapping` 経由で解決 |

### 任意カラム

| 列番号 | 列名 | 取込用途 | 対応カラム |
|---|---|---|---|
| 6 (F) | 郵便番号 | 顧客新規登録時のみ（既存顧客には反映しない） | `m_customer.postal_code` |
| 7 (G) | 住所 | 同上 | `m_customer.address1` |
| 8 (H) | ＴＥＬ | 同上 | `m_customer.phone` |
| 20 (T) | 払込方法 | 契約の補助情報 | `t_contract.payment_cycle` |
| 43 (AQ) | 担当者 | 参考保持のみ（担当者設定には代理店コードを使用） | `t_sjnet_import_row.sjnet_staff_name` |

### 不使用カラム（読み飛ばし）

| 列番号 | 列名 | 理由 |
|---|---|---|
| 1 (A) | 満期契約の識別 | |
| 2 (B) | 満期日（月） | Q列（保険終期）で代替するため不要 |
| 3 (C) | 満期日（日） | Q列（保険終期）で代替するため不要 |
| 5 (E) | 生年月日 | |
| 9 (I) | ＦＡＸ | |
| 10 (J) | 携帯ＴＥＬ | |
| 11 (K) | 更改状況 | |
| 12 (L) | 世帯主名 | |
| 13 (M) | 続柄 | |
| 14 (N) | （空列） | |
| 15 (O) | 保険会社 | 損保ジャパン専用のため不要 |
| 21 (U) | 回数 | |
| 22 (V) | 件数 | |
| 24 (X) | 満期返れい金 | |
| 25〜30 | 保険金額1〜3・単位 | |
| 31 (AE) | 契約状況 | |
| 32〜36 | 事故・入替・質権・共保・代分 | |
| 37 (AK) | 保険の対象等 | |
| 38〜39 | （空列） | |
| 40 (AN) | 拠点コード | |
| 41 (AO) | 拠点 | |
| 42 (AP) | 担当者コード | |

---

## 4. 満期日の決定ルール

**Q列（保険終期 = `policy_end_date`）をそのまま満期日として使用する。**

```
t_contract.policy_end_date    = CSV の Q列（YYYY-MM-DD）
t_renewal_case.maturity_date  = CSV の Q列（同じ値）
```

この方式は、業務 Excel の運用（保険終期 = 満期日）と一致する。B列（満期月）・C列（満期日）から年を動的算出する旧ロジックは廃止した。

**廃止した旧ロジック（参考）**  
以前は B列の月・C列の日から「取込日を基準に当年または翌年」を判定して満期日を算出していた。この算出は業務 Excel の運用と乖離しており、かつ不必要な複雑さを持っていたため廃止した。

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

【STEP 4】契約の登録・更新（列19: 証券番号 + 列17: 保険終期）
  ※ STEP 2 で ambiguous_customer となった行はこのステップを実行しない
  → t_contract を policy_no = 列19 AND policy_end_date = 列17（日付変換）AND is_deleted = 0 で検索
    ※ 同一証券番号でも終期日が異なれば別の契約年度として扱う
  → ヒット（UPDATE）: 同一年度の再取込
      policy_start_date  = 列16（日付変換）
      policy_end_date    = 列17（日付変換）
      product_type       = 列18
      payment_cycle      = 列20
      premium_amount     = 列23（数値変換）
      sales_user_id      = resolved_staff_user_id（NULL の場合は上書きしない）
      last_sjnet_imported_at = 取込実行日時
      ※ customer_id は UPDATE しない（既存の顧客紐づけを維持する）
      ※ 保険会社（列15: O列）は参照しない（損保ジャパン専用のため）
  → ミス（INSERT）: 新年度の契約として新規作成
      customer_id        = STEP 2 で解決した customer_id
      policy_no          = 列19
      policy_start_date  = 列16
      policy_end_date    = 列17
      product_type       = 列18
      payment_cycle      = 列20
      premium_amount     = 列23
      sales_user_id      = resolved_staff_user_id
      status             = 'active'
      last_sjnet_imported_at = 取込実行日時
      ※ INSERT 後、同一 policy_no の旧満期案件のうち
        case_status IN ('renewed', 'lost') かつ maturity_date < 新案件の maturity_date
        のものを case_status = 'closed' に自動更新する
        対応途中（not_started / sj_requested 等）の旧案件は自動クローズしない

【STEP 5】満期案件の登録・更新
  → contract_id + maturity_date（STEP 4 の contract_id と列17の保険終期）で
    t_renewal_case を検索（is_deleted = 0）
  → ヒット（UPDATE):
      assigned_user_id = resolved_staff_user_id（既に設定済みの場合は上書きしない）
  → ミス（INSERT):
      contract_id      = STEP 4 の contract_id
      maturity_date    = 列17（保険終期）と同じ値（YYYY-MM-DD）
      case_status      = 'not_started'
      assigned_user_id = resolved_staff_user_id
      office_user_id   = t_contract.office_user_id を引き継ぐ

【STEP 6】t_sjnet_import_row に結果を記録する
  row_status = 'insert' / 'update' / 'skip' / 'error'
```

### 5-2. スキップ対象

以下の行はエラーにせずスキップする。

- 証券番号（列19）が空の行
- 顧客名（列4）が空の行
- 保険終期（列17）が空の行

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
