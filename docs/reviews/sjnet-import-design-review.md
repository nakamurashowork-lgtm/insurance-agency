# SJNET CSV 取込 設計レビュー報告書

| 項目 | 内容 |
|---|---|
| レビュー目的 | SjnetCsvImportService の実装品質向上と、Phase 2 テスト強化で発見した問題の文書化 |
| 対象ファイル | `src/Domain/Renewal/SjnetCsvImportService.php` |
| 関連 DDL | `config/ddl/tenant/t_contract.sql`, `t_renewal_case.sql`, `m_customer.sql` |
| 関連テスト | `tests/Integration/Domain/Renewal/SjnetCsvImportServiceIntegrationTest.php` |
| レビュー実施 | Phase 0〜2（調査・テスト強化フェーズ）で順次発見 |

---

## 指摘事項一覧

| # | タイトル | 深刻度 | 業務確認要否 | テスト固定済み |
|---|---|---|---|---|
| #1 | processRow にトランザクション境界がなく orphan customer が残る | 高 | Q6 | Y-D2 |
| #2 | 例外発生行が t_sjnet_import_row に記録されない | 中 | 不要 | Y-D2 |
| #3 | 1 行の PDOException でバッチ全体が中断される | 中 | 業務ポリシー確認 | Y-D2 |
| #4 | closeOldRenewalCases が wrong column フィルタで dead code | 高 | Q3 | Y-C2 |
| #5 | 負値保険料が 0 にサイレントフォールバックされる | 中 | 不要 | B5 |

---

## 指摘 #1: processRow にトランザクション境界がなく orphan customer が残る

### 現状の挙動

`import()` の処理ループ（67〜75行）は明示的なトランザクションを持たない。

```php
// src/Domain/Renewal/SjnetCsvImportService.php 66〜76行
foreach ($dataRows as $rowIndex => $cols) {
    $rowData = $this->processRow($cols, $rowNo, $counters); // ← 複数 INSERT が含まれる
    $importRepo->insertRow($batchId, $rowNo, $rowData);
}
```

`processRow()` 内部の処理順序:

1. `resolveCustomer()` → m_customer への INSERT（autocommit で即コミット）
2. `upsertContract()` → t_contract への INSERT（UNIQUE 違反等で PDOException）
3. `upsertRenewalCase()` → t_renewal_case への INSERT
4. （2 が失敗すると 3 以降は実行されない）

2 で失敗した時点で 1 のコミットはロールバックされない。

### 業務上の影響（事故シナリオ）

**シナリオ A: 孤立顧客の発生**
1. 「山田太郎」という新規顧客の CSV 行を取込む
2. m_customer に「山田太郎」が INSERT される（autocommit でコミット済み）
3. 何らかの理由で t_contract の INSERT が失敗する（例: soft-deleted 契約の UNIQUE 違反）
4. バッチが中断。m_customer には「山田太郎」が残る
5. 次回の CSV 取込で「山田太郎」を取込もうとすると ambiguous_customer エラーが発生し続ける
6. 担当者が手動で孤立顧客を削除または名寄せするまで復旧できない

**シナリオ B: 孤立顧客の検知が難しい**
- import_row に記録されないため（指摘 #2）、ログを見ても「どの行で顧客が作られたか」がわからない
- 顧客一覧に実態のない顧客が表示され、業務担当が混乱する

### トランザクション境界の設計論点

#### 論点 A: 「1 行単位」か「バッチ全体」か

| 粒度 | 利点 | 欠点 |
|---|---|---|
| **1 行単位** | 1 行のエラーが他の行の COMMIT 済みデータに影響しない | 指摘 #3（バッチ継続方針）の選択に依存する |
| **バッチ全体** | 最もシンプル。バッチが失敗したら何も残らない | 99 行正常処理後の 1 行エラーで全件ロールバック。実用的でない |

→ **「1 行単位」が現実的な選択肢。** バッチ全体トランザクションは業務上使いにくい。

#### 論点 B: 同一 CSV 内で同名顧客が複数行存在する場合（A5-2 シナリオ）

同一 CSV に「山田太郎」が 2 行ある場合（証券番号が異なる別契約）:
1. 1 行目: `resolveCustomer('山田太郎')` → 0 件 → 新規 INSERT → customer_id = 100
2. 1 行目: processRow が COMMIT → 山田太郎（id=100）が確定
3. 2 行目: `resolveCustomer('山田太郎')` → 1 件（id=100）→ 既存顧客として再利用

これは **1 行単位トランザクションで正しく動作する**（各行が COMMIT された後に次の行が resolveCustomer を呼ぶため）。

「1 行単位トランザクション」では、1 行目 COMMIT 後に 2 行目が始まるため、同名顧客が 2 行あっても ambiguous_customer にはならない。現状の autocommit 挙動と同等の結果になる（A5-2 テスト: 1 顧客・2 契約・2 満期案件）。

#### 論点 C: Savepoint の利用

MySQL では `SAVEPOINT sp1; ... ROLLBACK TO SAVEPOINT sp1;` が使用できる。

1 行単位のトランザクションを外側の「バッチ進捗トランザクション」とは分離する場合に有用だが、現状の実装構造（外側に明示的な BEGIN なし、autocommit ON）では Savepoint を使うより 1 行単位の `BEGIN/COMMIT/ROLLBACK` の方がシンプル。

**Savepoint を使うべきケース**: 将来的に「バッチ全体で1つのトランザクション」が要件になった場合（行スキップしつつ全体をアトミックにしたい場合）。現時点では採用しない。

### 修正案

**案 A（推奨）: processRow 単位でトランザクション**
```
foreach (行) {
    BEGIN;
    resolveCustomer() → m_customer INSERT
    upsertContract()  → t_contract INSERT/UPDATE
    upsertRenewalCase() → t_renewal_case INSERT/UPDATE
    COMMIT;           ← 失敗時は ROLLBACK → 行全体を取り消し
}
```

推奨理由:
- orphan customer が発生しない
- 実装変更は `import()` の foreach ループ内に `$this->pdo->beginTransaction()` / `commit()` / `rollBack()` を追加するだけ
- A5-2 シナリオに問題なし（論点 B 参照）
- 影響範囲が `SjnetCsvImportService.php` の `import()` のみ

**案 B: resolveCustomer を分離し「先行フェーズ」で処理**
- 全行の顧客名寄せを先に行い、確定後に契約処理に進む
- 実装変更が大きく、A5-2 シナリオの処理順序の再設計が必要

**案 C: 現状維持（孤立顧客の検知・削除機能を追加）**
- 孤立顧客（契約なし・活動なし）を列挙する管理画面を追加
- トランザクション修正を回避したい場合の次善策だが、孤立を防ぐのではなく事後対処になる

### Q6-B 回答との依存関係

| Q6-B の回答 | 案 A への影響 |
|---|---|
| Yes（行スキップで継続） | ROLLBACK 後に `row_status='error'` として `insertRow()` を呼んで次の行へ（指摘 #2/#3 の修正と連動） |
| No（現状通り全体中断） | ROLLBACK 後に `throw RuntimeException` して外側 catch へ。実質的に現状に近いが orphan は防げる |

→ **Q6-B に関わらず案 A は着手可能。** Q6-B の回答は ROLLBACK 後の継続処理にのみ影響する。

### 修正難易度・影響範囲

- 難易度: 中
- 影響ファイル: `SjnetCsvImportService.php`（`import()` の foreach ループのみ）
- DDL 変更: 不要
- テスト変更: Y-D2 を「orphan customer が残らないこと」に書き換える

### 関連業務確認

Q6-A「processRow にトランザクションを導入してよいか？」の回答を待って着手。  
Q6-B の回答は「ROLLBACK 後に継続するか中断するか」の実装詳細に影響する。

### 関連テスト

`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`（Y-D2）

---

## 指摘 #2: 例外発生行が t_sjnet_import_row に記録されない

### 現状の挙動

```php
// src/Domain/Renewal/SjnetCsvImportService.php 70〜71行
$rowData = $this->processRow($cols, $rowNo, $counters); // PDOException が飛ぶ
$importRepo->insertRow($batchId, $rowNo, $rowData);    // ← 呼ばれない
```

PDOException が発生すると `insertRow()` に到達しない。
その行の情報（証券番号・顧客名・エラー内容）は `t_sjnet_import_row` に残らない。

**対比**: ambiguous_customer のような「業務エラー」は processRow() が error 配列を **return** するため、`insertRow()` が呼ばれて記録される。PDOException は記録されない。

### 業務上の影響

- 取込ログ（t_sjnet_import_row）を見ても、どの行でバッチが止まったかわからない
- 「N 行の取込が失敗した」という情報しか残らない
- 担当者が問題を特定するためにオリジナルの CSV を再確認する必要がある

### 修正案

**案 A: processRow 単位で try-catch を追加し、PDOException も insertRow に記録**
```php
try {
    $rowData = $this->processRow($cols, $rowNo, $counters);
} catch (\Throwable $e) {
    $counters['error']++;
    $rowData = [
        'raw'           => $cols,
        'policy_no'     => $cols[self::COL_POLICY_NO] ?? null,
        'row_status'    => 'error',
        'error_message' => 'システムエラー: ' . $e->getMessage(),
    ];
}
$importRepo->insertRow($batchId, $rowNo, $rowData);
```

この案は指摘 #3（バッチ全体中断）の修正と組み合わせると効果が高い。

**案 B: 現状維持（ログをアプリケーションログに書き出す）**
- DB ログではなくファイルログに行番号と例外内容を書き出す
- insertRow の変更不要

### 修正難易度・影響範囲

- 難易度: 小〜中（案 A は #3 とセットで実施するのが自然）
- 影響ファイル: `SjnetCsvImportService.php`（import() のループ部分のみ）
- DDL 変更: 不要

### 関連業務確認

業務確認は不要。ただし #3 とセットで修正方針を決めることを推奨。

### 関連テスト

`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`（Y-D2）  
修正後: 「error として記録されること」のアサートに書き換える。

---

## 指摘 #3: 1 行の PDOException でバッチ全体が中断される

### 現状の挙動

```php
// src/Domain/Renewal/SjnetCsvImportService.php 62〜77行
try {
    foreach ($dataRows as $rowIndex => $cols) {
        $rowData = $this->processRow(...);   // PDOException → catch に飛ぶ
        $importRepo->insertRow(...);
    }
} catch (Throwable) {
    $importRepo->failBatch($batchId);       // バッチ全体を failed に
    throw new \RuntimeException('CSV取込中に予期しないエラーが発生しました。');
    // ↑ 残りの行は処理されない
}
```

### 「業務エラー」と「システムエラー」の切り分け基準

現状の実装には、エラーの種別によって挙動が異なる非対称性がある。

| エラー種別 | 例 | 現状の処理 | import_row への記録 |
|---|---|---|---|
| **業務エラー** | ambiguous_customer（同姓同名） | processRow() が error 配列を **return** → ループ継続 | **記録される** |
| **業務エラー** | ヘッダ・カラム数不足などのバリデーション失敗 | processRow() が error 配列を **return** → ループ継続 | **記録される** |
| **システムエラー** | PDOException（UNIQUE 違反, 接続断等） | processRow() から **例外が伝播** → 外側 catch → バッチ全体中断 | **記録されない**（#2） |
| **システムエラー** | その他の Throwable（メモリ不足等） | 同上 | 記録されない |

#### 切り分けの原則案

「**業務ルールに起因するエラー**（バリデーション・名寄せ失敗等）は行スキップで継続。  
**システム/インフラに起因するエラー**（DB 接続断・ファイル I/O 等）はバッチ中断。  
**UNIQUE 違反等の予期可能な DB エラー**は業務エラーとして扱い行スキップで継続。」

ただし UNIQUE 違反には「データ不整合（soft-deleted 契約との衝突）」と「同時実行による競合」の 2 種類があり、前者はバグ起因のため区別が難しい。

### 修正案

**案 A（推奨）: PDOException も processRow 単位でキャッチし、行スキップで継続**

```php
foreach ($dataRows as $rowIndex => $cols) {
    try {
        $rowData = $this->processRow($cols, $rowNo, $counters);
    } catch (\PDOException $e) {
        $counters['error']++;
        $rowData = [
            'policy_no'     => $cols[self::COL_POLICY_NO] ?? null,
            'row_status'    => 'error',
            'error_type'    => 'system_error',           // ← 業務エラーと区別可能
            'error_message' => 'DBエラー: ' . $e->getMessage(),
        ];
    }
    $importRepo->insertRow($batchId, $rowNo, $rowData); // 常に記録される（#2 も解消）
}
```

- `error_type` フィールドで業務エラー（`ambiguous_customer`）とシステムエラーを区別
- 取込ログを見れば「何行目で何が起きたか」が記録される（#2 も同時解消）
- 指摘 #1（トランザクション）と組み合わせると、PDOException でロールバック後に `insertRow` を呼ぶ構成になる

**DB 接続断等の深刻なシステムエラーの扱い**:  
PDOException の中でも「接続断」は次の行の処理も失敗し続けるため、再度 PDOException が発生する。  
→ ループが全行「DBエラー」として記録されるが、すべての行を試みる挙動になる。  
接続断を専用に検出してバッチ中断したい場合は `$e->getCode()` でエラーコードを判定できる。

**案 B: 現状維持（DB エラーはデータ整合性の問題として全体中断）**
- DB エラーは「予期しない状態」として全件再取込を要求する運用フローを明確にする
- 担当者が原因を特定して CSV を修正してから再実行する

### error_type の構造化記録案

`t_sjnet_import_row.error_message` は現状 VARCHAR（フリーテキスト）。  
システムエラーと業務エラーを UI で区別できるよう、以下の値を定義することを提案:

| error_type 値（案） | 意味 |
|---|---|
| `ambiguous_customer` | 同姓同名顧客が複数存在 |
| `skip_no_policy_no` | 証券番号が空（スキップ対象） |
| `skip_no_customer_name` | 顧客名が空（スキップ対象） |
| `system_error` | PDOException 等のシステム起因エラー |

> DDL 変更なし（既存の `error_message` に格納。将来的に `error_type` カラム分離を検討）。

### 修正難易度・影響範囲

- 難易度: 中（#1 + #2 とセットで修正すると影響範囲を最小化できる）
- 影響ファイル: `SjnetCsvImportService.php`（`import()` のループ部分のみ）
- DDL 変更: なし（error_type カラムを追加する場合は小規模な DDL 変更が必要）
- Q6-B「行スキップで継続するか」の業務回答に依存する

### 関連業務確認

Q6-B「1 行のシステムエラーで取込全体を止めてよいか、残りは続けてほしいか」の回答を待って案 A/B を選択。

### 関連テスト

`testImport_OrphanCustomer_WhenContractInsertFails_KnownIssue_PendingQ6`（Y-D2）  
修正後（案 A）: バッチが最後まで走り、エラー行が `row_status='error'` / `error_type='system_error'` として記録されることを期待するテストに書き換える。

---

## 指摘 #4: closeOldRenewalCases が wrong column フィルタで dead code

### 現状の挙動

```php
// src/Domain/Renewal/SjnetCsvImportService.php 487〜502行
private function closeOldRenewalCases(...): void
{
    $stmt = $this->pdo->prepare(
        'UPDATE t_renewal_case rc
           JOIN t_contract c ON c.id = rc.contract_id
         SET rc.case_status = \'closed\', ...
         WHERE ...
           AND rc.case_status IN (\'renewed\', \'lost\')  ← ← ← wrong column
           ...'
    );
```

`'renewed'` / `'lost'` は `t_renewal_case.renewal_result` カラムの値（更改結果）であり、
`case_status` の有効値（not_started / sj_requested / doc_prepared / ...）には含まれない。

この UPDATE は毎回実行されるが、条件にヒットする行が存在しないため **常に 0 件更新**。
実際の DB（xs000001_te001）で `case_status` の分布を確認:
```
completed:65, not_started:25, sj_requested:4, doc_prepared:3, waiting_payment:1, quote_sent:1, waiting_return:1
→ 'renewed' / 'lost' は 0 件
```

### 業務上の影響

- 「新年度契約を取込んだ際に旧年度の満期案件を自動クローズする」という
  設計上の意図が完全に機能していない
- 業務担当が手動で旧案件をクローズし続ける必要がある（または気付かず放置）
- 満期案件一覧に対応不要な旧年度案件が残り続ける

### 根本問い: closeOldRenewalCases 自体が必要か？

この関数を「修正して動かす」か「そもそも削除する」かは、Q3 の回答によって決まる。  
**Q3 が「No（自動クローズしない）」だった場合、この関数ごと削除する選択肢が最もシンプル。**

### Q3 回答パターン別の修正方針

#### パターン 1: Q3 = **「No（自動クローズしない）」**

`closeOldRenewalCases()` を **関数ごと削除**する。

- 呼び出し元（`upsertContract()` 内の新規 INSERT パス）からの呼び出しも削除
- Y-C2 テストは「この関数が存在しない」または「呼ばれない」ことを確認するテストに変更
- これが最もシンプルで副作用ゼロの対応

#### パターン 2: Q3 = **「Yes（完了済みのみ）」**

`renewal_result` ベースの条件に修正する。

```sql
-- 修正前（dead code）
AND rc.case_status IN ('renewed', 'lost')

-- 修正後
AND rc.renewal_result IN ('renewed', 'lost')
AND rc.case_status != 'closed'
```

- 「更改済み」「失注」の旧案件のみをクローズ。対応中（not_started 等）は手動対応
- 難易度: 小（SQL 条件の変更のみ）
- Y-C2 テストを「renewal_result='renewed' の旧案件が 'closed' になること」に書き換える

#### パターン 3: Q3 = **「Yes（全件）」**

ステータスにかかわらず旧年度の案件をすべてクローズする。

```sql
-- renewal_result / case_status 条件を除去
WHERE c.policy_no = :policyNo
  AND rc.maturity_date < :newMaturityDate
  AND rc.case_status != 'closed'   -- 既にクローズ済みは対象外
```

- 「対応中（not_started / sj_requested 等）の案件が意図せずクローズされる」リスクを業務側が受け入れる前提
- 実装変更は小規模だが、**業務上のインパクトが大きいため要確認**

#### パターン 4: Q3 = **「Yes（その他条件）」**

業務担当者との相談結果に基づいてケースバイケースで実装。Q3 回答票の補足欄を確認してから設計する。

### 修正難易度・影響範囲

| パターン | 難易度 | 主な変更 |
|---|---|---|
| No（削除） | 小 | 関数削除 + 呼び出し削除 |
| 完了済みのみ | 小 | SQL 条件 1 行変更 |
| 全件 | 小 | SQL 条件削除（1 箇所） |
| その他条件 | 中〜大 | 要件確認後に設計 |

- 影響ファイル: `SjnetCsvImportService.php`（`closeOldRenewalCases()` のみ）
- DDL 変更: 不要
- テスト変更: Y-C2 をパターンに応じて書き換える

### 関連業務確認

Q3「閉じてほしい旧案件のステータスはどれか？（自動クローズ不要なら関数ごと削除する）」

### 関連テスト

`testCloseOldRenewalCases_CurrentlyDoesNothing_KnownIssue_DeadCode`（Y-C2）

---

## 指摘 #5: 負値保険料が 0 にサイレントフォールバックされる

### 現状の挙動

```php
// src/Domain/Renewal/SjnetCsvImportService.php 230行
$premiumAmount = $this->parsePremium(trim($cols[self::COL_PREMIUM_AMOUNT]));

// upsertContract 内 INSERT/UPDATE の両経路（432行 / 463行）
$stmt->bindValue(':premium', $premiumAmount ?? 0, PDO::PARAM_INT);
//                                             ↑ null → 0 に無言で変換
```

`parsePremium()` のコメントには「呼び出し側で INSERT/UPDATE 時に 0 へフォールバックさせる」と
明記されており、設計上の意図はある。しかし以下の状況では運用上の問題になりうる。

**null を返す条件**:
1. 空文字 → null（保険料なし = 0 は合理的）
2. 負値（'-5,000'）→ null → **0 フォールバック**（サイレント）
3. 全角数字（'１２０，０００'）→ `/[^\d]/` で全バイト除去 → '' → **null → 0 フォールバック**（サイレント、全角数字テスト未実装）

2 と 3 は本来 120,000 円の保険料が 0 円に書き変わるが、UI にも `t_sjnet_import_row` にも警告が出ない。

### 業務上の影響

- 保険料 0 円の契約が大量に発生する可能性がある
- 成績管理（SalesPerformance）等で集計に誤りが生じる
- 「なぜこの契約の保険料が 0 なのか」の追跡が困難（import_row を見ても error_message は null）

### 修正案

**案 A（推奨）: row_status に 'warning' を追加し記録する**
- 0 フォールバックは維持しつつ、import_row に警告を記録
- `error_message = '保険料の変換に失敗したため 0 円で登録しました'` 等

**案 B: null を 0 フォールバックせず、行を error にする**
- 保険料不正な行は取込しない
- 業務上の許容度（保険料なしで取込を続けてよいか）の確認が必要

**案 C: 現状維持（0 フォールバックは仕様として文書化）**
- parsePremium のコメントに全角数字の挙動も追記して明示

### 修正難易度・影響範囲

- 難易度: 小（processRow での null チェック追加）
- 影響ファイル: `SjnetCsvImportService.php`（processRow の premium 処理部分）
- テスト変更: B5 を「warning が記録されること」に更新（案 A の場合）

### 関連業務確認

業務確認なしで即時着手可能（案 A または案 C）。案 B の場合は確認が必要。

### 関連テスト

`testUpsertContract_NegativePremium_StoredAsZero_NoError`（B5）

---

## 追加発見（指摘 #1〜#5 以外）

### 追加 A: 全角数字の保険料が null→0 になる（指摘 #5 の延長）

`parsePremium('１２０，０００')` は `/[^\d]/` が全角数字の UTF-8 バイトを非数字として全除去するため、
`''` → null → 0 フォールバックになる。テストが未実装。Phase 4 レビュー結果参照。

### 追加 B: resolveCustomer の照合キーが customer_name のみ

同姓同名が 2 件存在すると ambiguous_customer エラーになり、その行の契約が永久に取込めない。
E列（生年月日）が CSV に存在するが不使用。詳細は Q5。

### 追加 C: t_renewal_case に UNIQUE 制約がない

`(contract_id, maturity_date)` に UNIQUE 制約がないため、並行実行時に重複 INSERT が起きうる。
現状はコード側で `LIMIT 1` により先着1件を使うが、ロックなしのため理論的には問題になりうる。詳細は Q2。

---

## レビュー過程で良かった設計判断（維持すべき部分）

1. **業務エラーは継続、例外は中断の分離**（現状は #3 の問題があるが設計思想は正しい）  
   ambiguous_customer 等のビジネスルール違反は「その行をスキップして続行」が正しい設計。

2. **upsertContract が customer_id を保護する**（B3-1 で確認）  
   UPDATE 時に customer_id を変更しない設計は、過去の顧客紐づけを壊さない重要な保護。

3. **upsertRenewalCase が case_status を保護する**（C3-1 で確認）  
   取込によって進行中の対応ステータスが上書きされない設計は業務上正しい。

4. **sales_staff_id / assigned_staff_id の「null のみ上書き」ポリシー**（B3-2/3, C3-2/3 で確認）  
   担当者を手動設定した後に CSV 取込で上書きされない設計は運用上重要。

5. **parsePremium の多様なフォーマット対応**  
   カンマ区切り・円記号・全角円記号に対応した実装は実際の CSV 運用を考慮している。

6. **decodeContent の SJIS/CP932 対応**（E1-E3 で確認）  
   `$detected` をそのまま変換元エンコーディングとして渡す実装により、
   CP932 固有文字（①等）も正しく変換される。

7. **取込日（importDate）をコンストラクタで注入**  
   DateTimeImmutable を外部から注入することでテスト時に日付を固定できる良い設計。
