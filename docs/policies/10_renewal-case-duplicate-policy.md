# t_renewal_case 再案件対応方針

## 1. 問題

`t_renewal_case` の UNIQUE KEY `uq_t_renewal_case_01 (contract_id, maturity_date)` は、
同一契約・同一満期日の案件を1件のみ許容する制約である。

通常運用では1契約1満期日につき1案件のため問題が生じないが、
以下のケースで重複 INSERT が発生し制約違反となる。

- 失注後、翌年同一満期日で再アプローチが必要になった場合
- SJNETの満期日を誤登録・訂正した後に正しい満期日で再取込した場合（旧案件が残っている状態）
- 旧案件が `is_deleted=1` の状態であっても UNIQUE KEY が有効なため、INSERT できない

## 2. 方針決定

### 2-1. 基本方針

UNIQUE KEY `uq_t_renewal_case_01 (contract_id, maturity_date)` を廃止し、
**アプリ層での重複制御**に切り替える。

理由は以下のとおり。

- `is_deleted=1` のレコードを含む制約は業務実態と合わない
- 再案件（失注後の翌年対応）はレアケースだが実際に発生し得る正当な業務操作である
- UNIQUE KEY を残したまま論理削除と共存させると、削除フラグを参照した複雑な回避実装が必要になる

### 2-2. アプリ層での制御ルール

新規の満期案件を作成する際、以下のチェックをアプリ側で行う。

```
同一 contract_id かつ同一 maturity_date かつ is_deleted = 0 のレコードが存在する場合、
重複エラーとして INSERT を拒否する。
```

論理削除済み（`is_deleted = 1`）のレコードは重複チェックの対象外とする。
これにより、旧案件をクローズしてから同一満期日の再案件を作成できる。

### 2-3. SJNET 再取込時の扱い

SJNET から同一証券番号・同一満期日のデータが再取込された場合、
既存の `is_deleted = 0` 案件が存在すれば **更新（UPDATE）** を行い、
新規 INSERT は行わない。

これにより再取込による重複案件の発生を防ぐ。

## 3. DDL 変更内容

`UNIQUE KEY uq_t_renewal_case_01 (contract_id, maturity_date)` を削除する。
既存の個別インデックス `idx_t_renewal_case_01 (maturity_date)` は維持する。
`contract_id` 単体のインデックスを追加する（重複チェッククエリの性能確保）。

変更後の関連部分：

```sql
-- 削除
-- UNIQUE KEY uq_t_renewal_case_01 (contract_id, maturity_date),

-- 追加
KEY idx_t_renewal_case_08 (contract_id, maturity_date),
```

## 4. 影響範囲

| 対象 | 対応内容 |
|---|---|
| `t_renewal_case.sql` | UNIQUE KEY 削除、複合インデックス追加 |
| `RenewalCaseRepository.php` | 新規作成時の重複チェックロジック追加 |
| SJNET取込バッチ | 同一証券番号・同一満期日の既存案件チェックとUPDATE処理の確認 |

## 5. t_contract 年度別複数レコード設計との整合

`t_contract` の UNIQUE KEY を `(policy_no)` から `(policy_no, policy_end_date)` に変更したことにより、
同一証券番号で終期日が異なる複数の契約レコードが存在するようになった（`docs/policies/sjnet-csv-import-spec.md` 参照）。

この設計変更と本方針の整合は以下のとおり。

- SJNET 取込時の `t_renewal_case` 重複チェックは `contract_id + maturity_date` で行う
  → 新年度は新しい `contract_id` で INSERT されるため、重複は発生しない
- 同一年度の再取込（contract UPDATE）では、`contract_id` が変わらないため、
  既存の `t_renewal_case` レコードがそのまま UPDATE される（本方針の 2-3 と一致する）
- 旧年度の完了済み案件（renewed / lost）は、新年度契約 INSERT 時に `closed` へ自動遷移する
  これにより、満期一覧のデフォルト表示（未完了案件のみ）で旧年度案件が混入しない
