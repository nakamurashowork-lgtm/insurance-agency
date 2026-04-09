# Phase 2 テスト計画: テストケース仕分け表（修正版）

> **目的**: `SjnetCsvImportService` の統合テスト（+ Unit テスト追加）を
> 「今すぐ書けるもの」「現状固定」「業務確認待ち」に分類する。
>
> **参照**: `docs/policies/sjnet-import-open-questions.md` Q1〜Q6

---

## 判定基準

| 記号 | 意味 |
|---|---|
| **X** | 業務確認なしで今すぐ書ける。仕様書に明記済み、または「実装通りに動く」が自明 |
| **Y** | 現状実装を固定するテスト。回答後に書き換え予定。テスト名に `CurrentSpec_PendingQ?` または `KnownIssue_PendingQ?` を含め、docblock に「現状動作/既知不具合の固定テスト」「関連 Q 番号」「修正時はこのテストを削除して置き換える」を明記 |
| **Z** | 業務確認が来るまで書かない。方針が決まらないと期待値が決められない |

---

## グループ X: 今すぐ書ける（Unit + Integration）

### 0. decodeContent — Unit テスト追加（tests/Unit/）

現行テストは UTF-8 BOM と UTF-8 無 BOM の 2 件のみ。「本命」の SJIS ケースが欠けている。

| ID | テスト名（案） | 検証内容 | ファイル配置 |
|---|---|---|---|
| E1 | `testDecodeContent_Sjis_ConvertedToUtf8` | SJIS バイト列（ASCII + ひらがな）が UTF-8 に変換され、encoding が 'SJIS'/'SJIS-win'/'CP932' のいずれかになること | Unit |
| E2 | `testDecodeContent_Cp932VendorChars_NotGarbled` | CP932 固有文字（① NEC特殊文字 0x8740）が文字化けせずに変換されること。`mb_convert_encoding` が CP932 として処理されていることを encoding 返り値で確認 | Unit |
| E3 | `testDecodeContent_SjisBomAbsent_HeaderParsedCorrectly` | SJIS 文字列に BOM がないこと（BOM チェックをすり抜けて encoding 変換に到達すること） | Unit |

> **Phase 5 指摘 (E2 関連)**: 〜（WAVE DASH U+301C）は PHP の CP932 → UTF-8 変換で
> U+FF5E（FULLWIDTH TILDE）に化ける可能性がある。実際の SJNET CSV サンプルで検証推奨。

---

### 1. バッチ境界（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| S1 | `testImport_EmptyFile_BatchSucceedsWithZeroRows` | 0 バイトのファイル → バッチ正常終了（import_status='success'）、total=0 |
| S2 | `testImport_HeaderOnly_BatchSucceedsWithZeroRows` | ヘッダ行のみ（データ 0 行）→ バッチ正常終了、total=0 |

---

### 2. スキップ・エラー条件（Integration）

各条件は独立したテストにすること（まとめない）。

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| SK1 | `testProcessRow_EmptyPolicyNo_Skipped` | S列（証券番号）が空 → row_status='skip'、error_message に「証券番号が空」を含む。契約・顧客の INSERT なし |
| SK2 | `testProcessRow_EmptyCustomerName_Skipped` | D列（顧客名）が空 → row_status='skip'。証券番号は記録される |
| SK3 | `testProcessRow_EmptyEndDate_Skipped` | Q列（保険終期）が空 → row_status='skip' |
| SK4 | `testProcessRow_InvalidEndDate_Error` | Q列が不正日付（'2026/13/01'）→ row_status='error'、error_message に「日付が不正」を含む。counters['error']++ |

---

### 3. 担当者解決（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| R1 | `testResolveStaff_ValidAgencyCode_SetsAssignedStaffId` | m_staff に sjnet_code='A001' が存在 → t_renewal_case.assigned_staff_id にそのスタッフの id が入ること |
| R2 | `testResolveStaff_UnknownAgencyCode_ContractStillInserted` | sjnet_code が m_staff にない → staff_mapping_status='unresolved'。ただし契約・満期案件は正常に INSERT されること（エラーにならない） |
| R3 | `testResolveStaff_EmptyAgencyCode_ContractStillInserted` | AR列（代理店コード）が空 → staff_mapping_status=null。契約・満期案件は正常 INSERT |

---

### 4. 顧客解決（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| A1 | `testResolveCustomer_NewCustomer_InsertsCorrectFields` | 顧客が存在しない → INSERT。customer_name / postal_code / address1 / phone / customer_type='individual' / status='active' が正しく入ること |
| A2 | `testResolveCustomer_ExistingCustomer_ReusesId` | 同名顧客が 1 件存在 → INSERT せず既存 id を返す。customer_insert カウンタが増えないこと |
| A5-1 | `testResolveCustomer_AmbiguousCustomer_ReturnsError` | 同名顧客が 2 件以上存在 → row_status='error'、error_message に 'ambiguous_customer' を含む。契約・満期案件の INSERT なし |
| A5-2 | `testResolveCustomer_SameNameTwiceInCsv_SecondRowReusesFirstCustomer` | 同一 CSV 内で同名 2 行 → 1 行目が顧客 INSERT（autocommit ON で即コミット）、2 行目の SELECT で 1 件ヒットして再利用。customer_insert=1、insert=2 |

> **A5-2 の前提**: PDO は autocommit ON（`beginTransaction()` なし）。逐次処理のため 1 行目 INSERT が 2 行目 SELECT に即座に見える。この挙動はテストで確定できる。

---

### 5. 契約（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| B1 | `testUpsertContract_NewContract_InsertsWithCorrectFields` | policy_no + policy_end_date が存在しない → INSERT。customer_id / policy_no / policy_end_date / product_type / premium_amount / status='active' / last_sjnet_imported_at が正しく入ること |
| B2 | `testUpsertContract_ExistingContract_UpdatesMutableFields` | policy_no + policy_end_date が既存 → UPDATE。policy_start_date / product_type / premium_amount / last_sjnet_imported_at が更新されること |
| B3-1 | `testUpsertContract_Update_DoesNotChangeCustomerId` | UPDATE 時に customer_id は変わらないこと |
| B3-2 | `testUpsertContract_Update_DoesNotOverwriteSalesStaffIdIfAlreadySet` | UPDATE 時、sales_staff_id が設定済みなら維持 |
| B3-3 | `testUpsertContract_Update_SetsSalesStaffIdIfNull` | UPDATE 時、sales_staff_id が NULL なら resolved_user_id で上書き |
| B5 | `testUpsertContract_NegativePremium_StoredAsZero_NoError` | parsePremium が null（負値）を返した場合 → premium_amount に **0** が入る。row_status は 'insert'/'update'（エラーにならない）。**サイレントなデータ変換**であることをコメントに明記 |

> **B5 の挙動確定**（実装確認済み）:  
> `parsePremium` が負値 → `null`。`upsertContract` は `$premiumAmount ?? 0` で **0 をサイレントに書き込む**。  
> INSERT / UPDATE どちらの経路も同一。row_status は 'error' にならず、import_row の error_message も null のまま。  
> DDL の `CHECK (premium_amount >= 0)` は満たされる（0 >= 0）。  
> 意図的設計（parsePremium のコメントに明記）だが、UI にも import_row にも警告が出ない。  
> **→ Phase 5 指摘事項**: 「負値保険料の 0 フォールバックは UI/import_row に警告なし。運用上問題になるケースがあれば row_status='warning' 追加を検討」

---

### 6. 満期案件（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| C1 | `testUpsertRenewalCase_NewCase_InsertsWithNotStartedStatus` | contract_id + maturity_date が存在しない → INSERT。case_status='not_started'、office_staff_id が t_contract から引き継がれること |
| C3-1 | `testUpsertRenewalCase_ExistingCase_DoesNotOverwriteCaseStatus` | 既存案件が存在 → UPDATE 時に case_status は変更されないこと |
| C3-2 | `testUpsertRenewalCase_ExistingCase_DoesNotOverwriteAssignedStaffIfSet` | 既存案件に assigned_staff_id が設定済み → 上書きしないこと |
| C3-3 | `testUpsertRenewalCase_ExistingCase_SetsAssignedStaffIfNull` | 既存案件の assigned_staff_id が NULL → resolvedUserId で上書き |

---

### 7. バッチ全体・冪等性（Integration）

| ID | テスト名（案） | 検証内容 |
|---|---|---|
| D1 | `testImport_OneErrorRow_OtherRowsContinue` | 100 行中 1 行が ambiguous_customer エラー → 残り 99 行が処理される。counters['error']=1 |
| D3 | `testImport_Idempotency_SecondImportProducesAllUpdates` | 同一 CSV を 2 回取込 → 2 回目は insert=0、update=N。新 batch_id が生成されること |
| D4-1 | `testImport_SummaryCounters_MatchActualDbState` | counters の insert / update / customer_insert / skip / error が実際の DB 状態と一致 |
| D4-2 | `testImport_BatchFinishedWithSuccessStatus` | 正常完了時に t_sjnet_import_batch.import_status='success' |

> **D3 の注釈**: 単発実行下の冪等性のみ検証。並行実行下の冪等性（同時 INSERT による重複）は Q2（UNIQUE 制約追加可否）の回答待ち → グループ Z。

---

## グループ Y: 現状実装を固定するテスト

> **命名規約**: テスト名に `CurrentSpec_PendingQ?` または `KnownIssue_PendingQ?` を含めること。  
> docblock 冒頭: 「これは現状動作/既知不具合の固定テスト」「関連 Q 番号」「修正時はこのテストを削除して置き換える」を必ず記載。

| ID | テスト名（案） | 固定する現状挙動 | 関連 Q |
|---|---|---|---|
| Y-A3 | `testResolveCustomer_ExistingCustomer_DoesNotUpdateAddress_CurrentSpec_PendingQ1` | 既存顧客の postal_code / address1 / phone は CSV に異なる値があっても更新されない | Q1 |
| Y-C2 | `testCloseOldRenewalCases_CurrentlyDoesNothing_KnownIssue_DeadCode` | `closeOldRenewalCases` は `case_status IN ('renewed', 'lost')` を参照するが、この値は `renewal_result` カラムの値であり `case_status` には存在しない → **永久にヒットせず何もしない（dead code）**。修正時はこのテストを削除して置き換える | Q3 + 修正判断 |
| Y-D2 | `testImport_CustomerInsertedButContractFails_CustomerRemainsInDb_KnownIssue_PendingQ6` | customer INSERT 成功後に contract INSERT が失敗した場合、customer だけ DB に残る（ロールバックなし）。修正時はこのテストを削除して置き換える | Q6 |

---

## グループ Z: 業務確認が来るまで書かない

| ID | 内容 | 関連 Q |
|---|---|---|
| Z-Q1 | 既存顧客の住所上書きテスト（Y-A3 の書き換え対象） | Q1 |
| Z-Q2 | `(contract_id, maturity_date)` UNIQUE 制約追加後の重複 INSERT テスト | Q2 |
| Z-Q2b | 並行実行下の冪等性テスト（D3 の拡張） | Q2 |
| Z-Q5 | 生年月日を追加した照合キー拡張テスト（m_customer に birth_date 追加が必要） | Q5 |
| Z-Q6 | トランザクション導入後の「1 行失敗で全ロールバック」テスト（Y-D2 の書き換え対象） | Q6 |

---

## 件数まとめと工数感

| グループ | 件数 | 備考 |
|---|---|---|
| X（Unit 追加） | 3 件 | decodeContent SJIS テスト。SjnetCsvBuilder の SJIS 出力を使う。既存テストクラスへの追記なので軽い |
| X（Integration） | 27 件 | S1-S2, SK1-SK4, R1-R3, A1/A2/A5-1/A5-2, B1/B2/B3-1-3/B5, C1/C3-1-3, D1/D3/D4-1-2 |
| Y | 3 件 | 意図的 dead code テストを含む。セットアップが Integration と同じなので追加コストは小さい |
| **合計** | **33 件** | |

**工数感**: 既存 44 件に対して 33 件追加。Integration テストは DB リセット込みで 1 本あたり 2〜4 秒程度（Phase 1 実測 5 本で 4 秒）。実装コードは SjnetCsvBuilder + writeTempCsv で骨格が揃っているため、1 本あたりの書き下しは 10〜20 行程度。**1 日（実作業 4〜5 時間）で X グループを書き切れる見込み**。Y グループはその後 1〜2 時間。

---

## 実装順序（承認後）

```
Phase 2-X:
  Step 1. E1-E3      decodeContent SJIS (Unit, SjnetCsvBuilder 活用)
  Step 2. S1-S2      バッチ境界
  Step 3. SK1-SK4    スキップ・エラー条件（独立テスト）
  Step 4. R1-R3      担当者解決
  Step 5. A1/A2      顧客解決・基本フロー
  Step 6. B1/B2/B3   契約 INSERT / UPDATE / 保護カラム
  Step 7. B5         premium 0 フォールバック（サイレント固定）
  Step 8. C1/C3      満期案件 INSERT / 保護カラム
  Step 9. A5-1/A5-2  ambiguous_customer
  Step 10. D1/D3/D4  部分エラー継続・冪等性・カウンタ検証

Phase 2-Y:
  Step 11. Y-A3      住所上書きなし（現状固定）
  Step 12. Y-C2      closeOldRenewalCases dead code（現状固定）
  Step 13. Y-D2      トランザクション不在（現状固定）

Phase 2-Z:
  保留（Q1/Q2/Q5/Q6 の回答後）
```

---

## 補足: テスト実装上の共通事項

### import() 経由 vs processRow 直接

全テストを `import()` 経由（tmpfile を使った CSV ファイル渡し）で統一する。
`processRow` は private のため、public な `import()` を通じて正式な入口から呼ぶことで
実際の使われ方を再現できる。

### 一時 CSV ファイルの作成

```php
$csv = SjnetCsvBuilder::row()
    ->withPolicyNo('P001')
    ->withCustomerName('山田太郎')
    ->toCsvString();
$path = $this->writeTempCsv($csv);  // tearDown で自動削除
$service = new SjnetCsvImportService($this->pdo, self::TEST_EXECUTED_BY, new DateTimeImmutable('2026-04-01'));
$result = $service->import($path, 'test.csv');
```

### PDO 共有

`SjnetCsvImportService($this->pdo, ...)` に渡した同一 PDO が
`SjnetImportRepository` と `StaffRepository` にも使われる（コンストラクタ内で `new` している）。
外部注入不要でそのまま動く。

---

## Phase 5 指摘事項（この計画で判明したもの）

1. **closeOldRenewalCases の dead code**: `case_status IN ('renewed', 'lost')` は永久にヒットしない。`renewal_result` カラムで条件を組み替えるか、別の自動クローズロジックを設計する必要がある
2. **B5 の警告なしフォールバック**: 負値保険料が 0 に置き換わるが、UI / import_row に警告が出ない。運用上問題になる場合は `row_status='warning'` 相当の記録を追加する
3. **E2 の CP932 〜 問題**: WAVE DASH（U+301C）が FULLWIDTH TILDE（U+FF5E）に変換される可能性。実際の SJNET CSV サンプルで検証が必要
