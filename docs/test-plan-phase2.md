# Phase 2 テスト計画: テストケース仕分け表

> **目的**: `SjnetCsvImportService` の統合テストを「今すぐ書けるもの」と「業務確認後に書くもの」に分類する。
> 仕様が曖昧なままテストを書くと「実装 = 仕様」に化けるリスクがあるため、仕分けを先行する。
>
> **参照**: `docs/policies/sjnet-import-open-questions.md` の Q1〜Q6

---

## 判定基準

| 記号 | 意味 |
|---|---|
| **X** | 業務確認なしで今すぐ書ける。仕様書に明記済み、または「実装通りに動く」が自明 |
| **Y** | 現状実装を固定するテスト。「壊れているかもしれないが現状こう動く」を明示的に記録する。回答後に書き換え予定 |
| **Z** | 業務確認が来るまで書かない。方針が決まらないと期待値が決められない |

---

## グループ X: 今すぐ書ける

### A. 顧客解決（resolveCustomer）

| ID | テスト名（案） | 検証内容 | 依存する Q |
|---|---|---|---|
| A1 | `testResolveCustomer_NewCustomer_InsertsCorrectFields` | 顧客が存在しない → INSERT。customer_name / postal_code / address1 / phone / customer_type='individual' / status='active' が正しく入ること | なし |
| A2 | `testResolveCustomer_ExistingCustomer_ReusesId` | 同名顧客が1件存在 → INSERT せずに既存 id を返す。customer_insert カウンタが増えないこと | なし |
| A5-1 | `testResolveCustomer_AmbiguousCustomer_ReturnsError` | 同名顧客が2件以上存在 → error。error_message に 'ambiguous_customer' が含まれること。契約・満期案件は INSERT されないこと | なし（仕様書に明記） |
| A5-2 | `testProcessRow_SameNameTwiceInCsv_SecondRowReusesFirstCustomer` | 同一 CSV 内で同名が2行 → 1行目で顧客を INSERT、2行目で再利用（LIMIT 3 で 1件ヒット）。customer_insert=1、insert=2 になること | なし |

### B. 契約（upsertContract）

| ID | テスト名（案） | 検証内容 | 依存する Q |
|---|---|---|---|
| B1 | `testUpsertContract_NewContract_InsertsWithCorrectCustomerId` | policy_no + policy_end_date が存在しない → INSERT。customer_id / policy_no / policy_end_date / product_type / premium_amount / status='active' が正しく入ること | なし |
| B2 | `testUpsertContract_ExistingContract_Updates` | policy_no + policy_end_date が既存 → UPDATE。policy_start_date / product_type / premium_amount / last_sjnet_imported_at が更新されること | なし |
| B3-1 | `testUpsertContract_Update_DoesNotChangeCustomerId` | UPDATE 時に customer_id は変わらないこと（別顧客が CSV に来ても customer_id を保護） | なし（仕様書に明記） |
| B3-2 | `testUpsertContract_Update_DoesNotOverwriteSalesStaffIdIfAlreadySet` | UPDATE 時、sales_staff_id が既に設定されていれば上書きしないこと | なし |
| B3-3 | `testUpsertContract_Update_SetsSalesStaffIdIfNull` | UPDATE 時、sales_staff_id が NULL なら resolved_user_id で上書きすること | なし |
| B5 | `testUpsertContract_NegativePremium_StoredAsZero` | parsePremium が負値で null → INSERT/UPDATE 時に 0 としてフォールバックすること（DDL の CHECK `premium_amount >= 0`） | なし |

### C. 満期案件（upsertRenewalCase）

| ID | テスト名（案） | 検証内容 | 依存する Q |
|---|---|---|---|
| C1 | `testUpsertRenewalCase_NewCase_InsertsWithNotStartedStatus` | contract_id + maturity_date が存在しない → INSERT。case_status='not_started'、office_staff_id が t_contract から引き継がれること | なし |
| C3-1 | `testUpsertRenewalCase_ExistingCase_DoesNotOverwriteCaseStatus` | 既存満期案件が存在 → UPDATE 時に case_status は変更されないこと | なし |
| C3-2 | `testUpsertRenewalCase_ExistingCase_DoesNotOverwriteAssignedStaffIfSet` | 既存案件に assigned_staff_id が設定済み → 上書きしないこと | なし |
| C3-3 | `testUpsertRenewalCase_ExistingCase_SetsAssignedStaffIfNull` | 既存案件の assigned_staff_id が NULL → resolvedUserId で上書きすること | なし |

### D. バッチ全体・冪等性

| ID | テスト名（案） | 検証内容 | 依存する Q |
|---|---|---|---|
| D1 | `testImport_OneErrorRow_ContinuesOtherRows` | 100行中1行が ambiguous_customer エラー → 残り99行は処理が続くこと。error=1、insert/update=99 | なし（仕様書に「取込全体は続行する」と明記） |
| D3 | `testImport_Idempotency_SecondImportProducesAllUpdates` | 同一 CSV を2回取込 → 2回目は insert=0、update=N（同一 policy_no + policy_end_date がすべてヒット）。新 batch_id が生成されること | なし |
| D4-1 | `testImport_SummaryCounters_MatchActualDbState` | counters の insert / update / customer_insert / skip / error が実際の DB 状態と一致すること | なし |
| D4-2 | `testImport_BatchFinishedWithSuccessStatus` | 正常完了時に t_sjnet_import_batch.import_status = 'success' になること | なし |

---

## グループ Y: 現状実装を固定するテスト（回答後に書き換え予定）

> テスト名と docblock に「**現状動作の固定**」「**Q? 回答後に再評価**」を明記する。
> 業務確認で方針が変われば、テスト自体を書き換えること（「削除して新テストを書く」）。

| ID | テスト名（案） | 固定する現状挙動 | 関連 Q | 将来の変更方向 |
|---|---|---|---|---|
| Y-A3 | `testResolveCustomer_ExistingCustomer_DoesNotUpdateAddress_CURRENT_BEHAVIOR` | 既存顧客の postal_code / address1 / phone は CSV に異なる値があっても**更新されない** | Q1 | Q1=Yes なら「更新される」テストに書き換え |
| Y-C2 | `testCloseOldRenewalCases_CurrentlyDoesNothing_DueToWrongColumnFilter` | `closeOldRenewalCases` は `case_status IN ('renewed', 'lost')` を参照するが、この値は `renewal_result` カラムの値であり `case_status` には存在しない → **永久にヒットせず何もしない（dead code）** | Q3 (+ 修正判断) | 修正時は `renewal_result IN ('renewed', 'lost')` または別条件に変更し、このテストを書き換える |
| Y-D2 | `testImport_CustomerInsertedButContractFails_CustomerRemainsInDb_CURRENT_BEHAVIOR` | customer INSERT 成功後、contract INSERT で意図的に失敗（policy_no+policy_end_date UNIQUE 違反）させた場合、**customer だけ DB に残る**（ロールバックされない） | Q6 | Q6=Yes でトランザクション導入後は「customer も残らない」テストに書き換え |

---

## グループ Z: 業務確認が来るまで書かない

| ID | 理由 | 関連 Q |
|---|---|---|
| Z-Q1 | 既存顧客の住所上書き挙動のテスト（Y-A3 の書き換え対象） | Q1 |
| Z-Q2 | `(contract_id, maturity_date)` UNIQUE 制約追加後の重複 INSERT テスト | Q2 |
| Z-Q5 | 同姓同名 + 生年月日一致で正しく名寄せされるテスト（m_customer に birth_date 追加が必要） | Q5 |
| Z-Q6 | トランザクション導入後の「1行失敗で全ロールバック」テスト（Y-D2 の書き換え対象） | Q6 |

---

## 実装順序（承認後）

```
Phase 2-X:
  1. A1, A2       顧客解決・基本フロー
  2. B1, B2, B3   契約 INSERT / UPDATE / 保護カラム
  3. C1, C3       満期案件 INSERT / 保護カラム
  4. A5-1, A5-2   ambiguous_customer
  5. B5           premium フォールバック
  6. D4, D3       サマリ・冪等性

Phase 2-Y:
  7. Y-A3         住所上書きなし（固定）
  8. Y-C2         closeOldRenewalCases dead code（固定）
  9. Y-D2         トランザクション不在（固定）

Phase 2-Z:
  保留（Q1/Q2/Q5/Q6 の回答後）
```

---

## 補足: import() 経由 vs processRow 直接呼び出し

| テスト区分 | 呼び出し方 | 理由 |
|---|---|---|
| A1〜C3 の単体的な挙動 | `import()` を CSV ファイル経由で呼び出す（tmpfile を作成） | `processRow` は private のため。import() 経由が自然な統合テストの粒度 |
| D1（エラー継続） | `import()` 経由 | 複数行の挙動確認のため |
| D3（冪等性） | `import()` を2回呼び出す | バッチ ID の独立性も同時検証 |
| Y-D2（partial failure） | `import()` 経由 + UNIQUE 違反を起こす CSV | 実際の失敗経路を通す |

---

## 未解決の実装上の懸念

1. **`import()` は `$filePath` を受け取る**: テストでは `tmpfile()` + `fwrite()` で一時 CSV ファイルを作成し、パスを渡す方式を採用する。`SjnetCsvBuilder::toCsvString()` の出力をそのまま書き込む。

2. **`SjnetImportRepository` が `$this->pdo` を使う**: `SjnetCsvImportService` に注入した PDO が `SjnetImportRepository` にも渡されるため、同一トランザクション内（現状はトランザクションなし）で動く。テスト用 PDO を1つ渡せば両方をカバーできる。

3. **`StaffRepository` の PDO**: `resolveStaff()` 内で `new StaffRepository($this->pdo)` と同一 PDO を使っているため、外部注入不要。
