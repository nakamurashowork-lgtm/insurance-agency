# SJNET 取込 仕様確認事項（業務担当者向け）

> **用途**: 以下の各項目について業務担当者に Yes / No で回答いただき、実装・テスト方針を確定する。  
> **背景**: `src/Domain/Renewal/SjnetCsvImportService.php` のテスト強化に着手するにあたり、
> 仕様が曖昧な箇所をテストで固定すると「実装通り = 仕様」になってしまうリスクがある。
> 以下の事項はテストを書かずに残し、回答が得られた後に実装・テストを追加する。

---

## Q1. 既存顧客の住所を CSV で上書きすべきか？（F-1）

**現状の挙動**: 既存顧客（`m_customer.customer_name` で1件一致）が見つかった場合、
CSV に含まれる郵便番号・住所・電話番号は **一切更新しない**。
新規登録時のみ CSV の F列（郵便番号）・G列（住所）・H列（電話番号）を取り込む。

**確認内容**: 再取込のたびに住所を最新の CSV で上書きしてほしいか？

- [ ] **Yes** — CSV 取込のたびに postal_code / address1 / phone を上書きする（実装変更が必要）
- [ ] **No** — 現状通り、新規登録時のみ反映する（変更不要）

---

## Q2. `t_renewal_case` に UNIQUE 制約を追加してよいか？（F-2）

**現状の挙動**: `t_renewal_case` には `(contract_id, maturity_date)` の UNIQUE 制約が **ない**。
コード上は `LIMIT 1` で先着1件を取得するが、同時実行時に同一 `contract_id + maturity_date` の
行が複数 INSERT される可能性がある。

**確認内容**: `UNIQUE KEY (contract_id, maturity_date)` を DDL に追加してよいか？

- [ ] **Yes** — 追加する（DDL マイグレーションと既存データのチェックが必要）
- [ ] **No** — 追加しない（理由があれば記載:　　　　　　　　　　　）

---

## Q3. `closeOldRenewalCases` が進行中案件を残すのは意図通りか？（F-3）

**現状の挙動**: 新年度契約を INSERT した際、同一 `policy_no` の旧満期案件のうち
`case_status IN ('renewed', 'lost')` かつ `maturity_date < 新満期日` のものを `'closed'` にする。
`not_started` / `sj_requested` / `doc_prepared` 等の **進行中案件はクローズしない**。

**確認内容**: この挙動は意図通りか？

- [ ] **Yes** — 対応途中の案件を自動クローズしないのは正しい（変更不要）
- [ ] **No** — 全旧案件をクローズすべき（または別の条件を指定:　　　　　　　　）

---

## Q4. 同一 `policy_no` で `policy_end_date = NULL` の契約が複数存在することを許容するか？（F-5）

**現状の挙動**: `t_contract` には `UNIQUE KEY (policy_no, policy_end_date)` があるが、
MySQL では UNIQUE キーに NULL を含む場合は重複を許容するため、
`policy_end_date IS NULL` の同一 `policy_no` 契約が複数作成できてしまう。

**確認内容**: この状況を許容するか？許容しない場合、防止策を取ってよいか？

- [ ] **許容する** — NULL 終期の契約が複数あっても問題ない
- [ ] **許容しない** — NULL 終期を持ちうる CSV は存在しないため、スキップ or エラーにする
- [ ] **許容しない** — アプリ側でバリデーションを追加する（実装変更が必要）

---

## Q5. 顧客名寄せキーを「氏名のみ」から「氏名 + 生年月日」等に拡張すべきか？（新規）

**現状の挙動**: `resolveCustomer` は `customer_name` の完全一致のみで顧客を同定する。
SJNET CSV の E列（5列目）には **生年月日** が含まれているが、現在は「不使用」として読み飛ばしている。

同姓同名の顧客が存在する場合、`error_type = 'ambiguous_customer'` となり
その行の契約・満期案件が登録されない（手動対応が必要）。

**確認内容**: 顧客照合に生年月日を追加して同定精度を上げるべきか？

- [ ] **Yes** — `customer_name + birth_date` を照合キーとする（DDL への `birth_date` 追加と実装変更が必要）
- [ ] **No** — 氏名のみで十分（同姓同名は手動対応で許容する）

> **補足**: 現在 `m_customer` テーブルに `birth_date` カラムは存在しない。
> Yes の場合は DDL 変更も必要になる。

---

## Q6. `processRow` にトランザクションを導入してよいか？（新規）

**現状の挙動**: 1行の処理（顧客解決→契約登録→満期案件登録）は
**明示的なトランザクションで囲まれていない**。

例えば「顧客の新規 INSERT は成功したが、契約の INSERT でエラーが発生した」場合、
顧客のみが DB に残り、契約・満期案件が存在しない不整合状態になる可能性がある。

**確認内容**: 1行処理全体を `BEGIN / COMMIT / ROLLBACK` で囲んでよいか？

- [ ] **Yes** — 導入する（エラー時に顧客 INSERT も含めてロールバックされる）
- [ ] **No** — 顧客だけ残っても問題ない、現状維持でよい

---

## 参考: 現在の `ambiguous_customer` エラー発生状況

`t_sjnet_import_row.error_message LIKE '%ambiguous%'` の件数を以下のクエリで確認できる。

```sql
SELECT error_message, COUNT(*) AS cnt
FROM t_sjnet_import_row
WHERE error_message LIKE '%複数一致%'
GROUP BY error_message;
```

運用ログへのアクセス権がある担当者が確認し、発生頻度を記載すること。
（Q5 の方針決定に影響する）

---

## 参考: 仕様書 vs 実装の不整合（開発側で修正候補）

業務担当者の確認は不要だが、開発側で解決が必要な不整合を記録する。

| # | 箇所 | 仕様書の記述 | 実装の挙動 | 対応方針 |
|---|---|---|---|---|
| I-1 | `sjnet-csv-import-spec.md` STEP 5 | `case_status = 'open'` | `'not_started'` を INSERT | 仕様書側を `'not_started'` に修正済み（コミット e8c3c07） |
| I-2 | `closeOldRenewalCases` | `case_status IN ('renewed', 'lost')` でクローズ | 永久に 0 件更新（dead code） | 下記 Phase 5 指摘 #3 を参照 |

---

## Phase 5 指摘事項（開発側で修正が必要な実装上の問題）

> テスト強化（Phase 2）で判明した実装上の問題を記録する。
> 各項目は独立して修正可能。修正前に Q1〜Q6 の業務確認結果を考慮すること。
> 「Y-D2 で固定済み」は統合テスト `testImport_..._KnownIssue_PendingQ6` で
> 現状挙動がテストコードに記録されていることを意味する。

---

### Phase 5 指摘 #1: processRow にトランザクション境界がなく orphan customer が残る

**ファイル**: `src/Domain/Renewal/SjnetCsvImportService.php` — `import()` / `processRow()`

**問題**:
`processRow()` は「顧客解決 → 契約登録 → 満期案件登録」を順に実行するが、
明示的な `BEGIN / COMMIT / ROLLBACK` で囲まれていない。
顧客の新規 INSERT が成功した後、契約 INSERT が例外（UNIQUE 違反等）で失敗すると、
顧客だけ DB に残る（orphan customer）。

**再現条件**:
同一 `(policy_no, policy_end_date)` の soft-deleted 契約が既に存在する状態で
同一キーの新規 CSV 行を取込む → 顧客 INSERT 成功 → 契約 INSERT が UNIQUE 違反。

**影響**:
- 手動で顧客を削除しない限り、次回同名顧客の取込で `ambiguous_customer` エラーが発生し続ける
- 孤立した顧客が顧客一覧に表示される

**対応方針**: Q6「processRow にトランザクションを導入してよいか？」の回答を待って判断する。

**テストで固定済み**: Y-D2 (`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`)

---

### Phase 5 指摘 #2: 例外発生行は t_sjnet_import_row に記録されない

**ファイル**: `src/Domain/Renewal/SjnetCsvImportService.php` — `import()` ループ（67〜75行）

**問題**:
```php
$rowData = $this->processRow($cols, $rowNo, $counters);
$importRepo->insertRow($batchId, $rowNo, $rowData);  // processRow 成功後にのみ呼ばれる
```
`processRow()` 内で PDOException が発生すると、`insertRow()` が呼ばれる前に
catch ブロック（75行）に飛ぶため、その行の記録が `t_sjnet_import_row` に残らない。

**対比**: ambiguous_customer 等の「業務エラー」は `processRow()` が error 配列を返すため
`insertRow()` が呼ばれて記録される。PDOException は記録されない。

**影響**:
- 取込ログを見ても「何行目の何が原因でバッチが中断したか」がわからない
- デバッグ・運用上の可視性が低下する

**対応方針**: try-catch を `processRow()` 単位に変更し、PDOException も
`row_status='error'` として insertRow() に記録するか検討する。

**テストで固定済み**: Y-D2 (`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`)

---

### Phase 5 指摘 #3: 1 行の PDOException でバッチ全体が中断される（D1 と非対称）

**ファイル**: `src/Domain/Renewal/SjnetCsvImportService.php` — `import()` 67〜77行

**問題**:
D1 テストで確認したように、`ambiguous_customer` 等の業務エラーは
「その行をスキップして次の行を続行」する。
しかし PDOException は `import()` の外側の catch で `failBatch` を呼んでバッチ全体を中断する。

1 行の DB エラーが全体を止める挙動は業務上の問題になりうる。

**対応方針**:
- 1 行の PDOException も processRow 単位でキャッチして `row_status='error'` として継続するか
- それとも DB エラーはデータ整合性の問題として全体中断を維持するか
  業務ポリシーの確認が必要

**テストで固定済み**: Y-D2 (`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`)

---

### Phase 5 指摘 #4: closeOldRenewalCases が wrong column フィルタで dead code

**ファイル**: `src/Domain/Renewal/SjnetCsvImportService.php` — `closeOldRenewalCases()` 495行

**問題**:
```sql
AND rc.case_status IN ('renewed', 'lost')
```
`'renewed'`/`'lost'` は `t_renewal_case.renewal_result` カラムの値であって
`case_status` の有効値には存在しない。
この UPDATE は実行されるが常に 0 件更新する（dead code）。

**対応方針**: Q3「closeOldRenewalCases が not_started/sj_requested を残すのは意図通りか？」
の回答を踏まえ、条件を `renewal_result IN ('renewed', 'lost')` に変更するか
別の自動クローズ条件を設計するか判断する。

**テストで固定済み**: Y-C2 (`testCloseOldRenewalCases_CurrentlyDoesNothing_KnownIssue_DeadCode`)

---

### Phase 5 指摘 #5: 負値保険料が 0 にサイレントフォールバックされる

**ファイル**: `src/Domain/Renewal/SjnetCsvImportService.php` — `upsertContract()` 432/463行

**問題**:
`parsePremium()` が負値で `null` を返した場合、`$premiumAmount ?? 0` で
`premium_amount = 0` が DB に書かれる。`row_status` は `'insert'`/`'update'` のまま、
`error_message` も `null`。UI にも `t_sjnet_import_row` にも警告が出ない。

**対応方針**: 運用上問題になる場合は `row_status='warning'` 相当の記録追加を検討する。

**テストで固定済み**: B5 (`testUpsertContract_NegativePremium_StoredAsZero_NoError`)
