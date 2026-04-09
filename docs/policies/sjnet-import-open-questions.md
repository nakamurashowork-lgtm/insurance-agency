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
| I-1 | `sjnet-csv-import-spec.md` STEP 5 | `case_status = 'open'` | `'not_started'` を INSERT | 仕様書側を `'not_started'` に修正（DDL の DEFAULT 値と一致させる） |
| I-2 | `closeOldRenewalCases` | 仕様書に `case_status IN ('renewed', 'lost')` と明記 | 同左 | `t_renewal_case.case_status` の有効値に `'renewed'`/`'lost'` が含まれないように見える（コメントには記載なし）。実際の有効値リストを確定して DDL コメントを修正する |
