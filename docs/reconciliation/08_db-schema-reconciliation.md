# DB スキーマ整合記録

## 1. 位置づけ

本文書は `config/ddl/tenant/` の canonical DDL と実DB（XServer MySQL）スキーマとの整合状態を追跡する記録である。

Phase 2 受入確認（2026-03-29）で判明した差分を起点とし、各差分の解消状態を管理する。

差分が解消された際は本文書を更新し、確認者・確認日時・確認方法を記録する。

---

## 2. 初回差分記録（Phase 2 受入確認より）

確認日: 2026-03-29
確認手段: `tmp/phase2_acceptance_check.php`（information_schema.tables による実在確認）

| 差分種別 | 対象 | 内容 | 解消状態 |
|---|---|---|---|
| テーブル不存在 | `xs000001_te001.t_contract` | DDLでは `t_contract` だが実DBに存在しない。`m_contract` が存在 | □ 未解消 |
| テーブル不存在 | `xs000001_te001.t_activity` | canonical DDL にあるが実DBに存在しない | □ 未解消 |
| テーブル不存在 | `xs000001_te001.m_customer_contact` | canonical DDL にあるが実DBに存在しない | □ 未解消 |
| 空スキーマ | `xs000001_te002` | 対象テーブルが一切存在しない | □ 未解消 |
| カラム名不一致 | `xs000001_te001.t_renewal_case` | `maturity_date` → 実DBは `renewal_due_date`、`case_status` → 実DBは `status`、`remark` → 実DBは `note` | □ 未解消 |

---

## 3. 解消確認手順

各差分の解消確認は以下の手順で行う。

1. `information_schema.tables` でテーブル存在を確認する
2. `information_schema.columns` でカラム名・型を canonical DDL と比較する
3. FK制約は `information_schema.key_column_usage` で確認する
4. 確認結果を下表「解消確認記録」に記録する

---

## 4. 解消確認記録

| 差分対象 | 解消方法 | 確認日時 | 確認者 | 確認コマンド/スクリプト |
|---|---|---|---|---|
| `t_contract` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `t_activity` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `m_customer_contact` 不存在 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `xs000001_te002` 空スキーマ | （記入欄） | （記入欄） | （記入欄） | （記入欄） |
| `t_renewal_case` カラム名不一致 | （記入欄） | （記入欄） | （記入欄） | （記入欄） |

---

## 5. 今後の差分記録ルール

- DDL変更（canonical DDL修正）を行った場合は本文書に差分を追記する
- マイグレーション実施後は「解消確認記録」に実施日時・担当者を記録する
- 本文書を更新せずにDDLのみ変更することを禁止する
- 実装計画書（`docs/plans/05_implementation-plan.md`）の受入確認で差分が判明した場合は本文書に転記する

---

## 6. wireframe照合対応によるDDL追加（2026-04-02）

| カラム追加 | 対象 | 内容 | DDL修正状態 | DB適用状態 |
|---|---|---|---|---|
| `t_renewal_case.early_renewal_deadline` | `xs000001_te001` | 早期更改締切日（DATE NULL）をmaturity_dateの直後に追加 | ■ 適用済み | ■ 適用済み |

---

## 7. 今後の追加差分（問題2・3のDDL修正に伴うもの）

本レビュー（2026-04-01）で判明した以下の修正を canonical DDL に適用する。
適用後、実DBへのマイグレーションが必要。

| 修正対象 | 修正内容 | DDL修正状態 | DB適用状態 |
|---|---|---|---|
| `t_sales_case` | `created_by/updated_by` 追加、FK制約（customer_id/contract_id）追加 | □ 未適用 | □ 未適用 |
| `t_accident_case` | `accident_no` の UNIQUE KEY 削除、通常INDEXに変更 | □ 未適用 | □ 未適用 |

---

## 8. 新規テーブル追加（2026-04-03）

| 差分種別 | 対象 | 内容 | 解消状態 |
|---|---|---|---|
| テーブル追加 | m_product_category | 種目マスタ新規追加 | ■ 適用済み |

---

## 9. テナント設定リファクタリングに伴うDDL変更（2026-04-03）

| 変更種別 | 対象 | 内容 | DDL修正状態 | DB適用状態 |
|---|---|---|---|---|
| テーブル削除 | `m_sjnet_staff_mapping` | SJNETコード↔ユーザーマッピングマスタを廃止。担当者マスタ（m_staff_sjnet_mapping）に置換 | ■ DDLファイル削除済み | ■ DROP実行済み（2026-04-03） |
| テーブル追加 | `m_staff_sjnet_mapping` | 担当者マスタ（SJNETコード/担当者名の2カラム構成） | ■ 適用済み | ■ 適用済み（2026-04-03） |
| テーブル追加 | `m_renewal_case_status` | 更改案件対応状況マスタ（code/display_name/display_order/is_active/is_fixed） | ■ 適用済み | ■ 適用済み（2026-04-03）、初期7件投入済み |

---

## 10. 見込案件登録フォームの顧客任意化に伴う差分確認（2026-04-03）

### 確認事項

見込案件登録フォーム（`sales-case/new`）で顧客を任意項目に変更した。
これに伴い `t_sales_case.customer_id` に NULL を保存するケースが生じる。

### DDL確認結果

`config/ddl/tenant/t_sales_case.sql` を確認した結果、`customer_id` カラムは以下の通り。

```
customer_id  BIGINT UNSIGNED NOT NULL COMMENT '顧客ID(m_customer.id)',
```

**canonical DDL では `NOT NULL` であり、NULL保存はDBレベルで拒否される。**

### 対応状況

| 差分種別 | 対象 | 内容 | 対応状態 |
|---|---|---|---|
| DDL変更必要 | `t_sales_case.customer_id` | `NOT NULL` → `NULL` への変更が必要（顧客任意化のため） | □ 未対応（DDL・DB変更が必要） |

### 備考

Repository の `bindInputValues` はすでに `customer_id` を nullable として扱っている（`PDO::PARAM_NULL` 分岐あり）ため、DDLを `NULL` 許容に変更すれば動作する。
DDL変更と実DBへのマイグレーションを別途実施すること。

---

## 11. users.display_name カラム追加（2026-04-04）

### 変更内容

| 変更種別 | 対象テーブル | カラム | 内容 |
|---|---|---|---|
| カラム追加 | `users`（common DB） | `display_name VARCHAR(100) NULL` | 業務上の表示名。NULL の場合は `name` にフォールバック |

### マイグレーション

`config/ddl/common/migration_add_display_name.sql` を適用済み。実DB（xs000001_admin）に `ALTER TABLE users ADD COLUMN display_name` を実施。

### 実装への影響

- `src/Domain/Auth/UserRepository.php`: SELECT に `display_name` を追加
- `src/Auth/AuthService.php`: セッションの `display_name` を `COALESCE(display_name, name)` 相当に変更
- 各コントローラーの `fetchAssignableUsers` SQL: `u.name` → `COALESCE(u.display_name, u.name) AS name`
- テナント設定画面にユーザー管理タブを追加（管理者が `display_name` を編集可能）

---

## 12. 担当者マスタ統合・ステータスマスタ統合・カラムリネーム（2026-04-04）

### 変更概要

担当者管理を `m_staff` に一本化し、SJNETコードを担当者マスタ（`m_staff.sjnet_code`）に統合した。
また、更改・事故ステータスマスタを `m_case_status` に統合した。
これに伴い、各テーブルの担当者カラム名を `*_user_id` から `*_staff_id` に統一した。

### DDL変更一覧

| 変更種別 | 対象 | 内容 | DDL修正状態 | DB適用状態 |
|---|---|---|---|---|
| テーブル追加 | `m_staff` | 担当者マスタ新規作成。`is_sales`/`is_office`/`sjnet_code`/`user_id`/`is_active`/`sort_order` を持つ | ■ 適用済み | ■ 適用済み |
| テーブル削除 | `m_staff_sjnet_mapping` | `m_staff.sjnet_code` に統合のため廃止。DDLファイル削除済み | ■ 適用済み | ■ 適用済み |
| テーブル追加 | `m_case_status` | 満期・事故ステータスマスタを統合。`case_type` 列（`renewal`/`accident`）で識別 | ■ 適用済み | ■ 適用済み |
| テーブル削除 | `m_renewal_case_status` | `m_case_status` に統合のため廃止。DDLファイル削除済み | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `m_customer.assigned_staff_id` | `assigned_user_id` → `assigned_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_renewal_case.assigned_staff_id` | `assigned_user_id` → `assigned_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_renewal_case.office_staff_id` | `office_user_id` → `office_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_accident_case.assigned_staff_id` | `assigned_user_id` → `assigned_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_accident_case.office_staff_id` | `office_user_id` → `office_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_activity.staff_id` | `staff_user_id` → `staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_contract.sales_staff_id` | `sales_user_id` → `sales_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_contract.office_staff_id` | `office_user_id` → `office_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_sales_case.assigned_staff_id` | `assigned_user_id` → `assigned_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_sales_case.staff_id` | `staff_user_id` → `staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_sales_performance.staff_id` | `staff_user_id` → `staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |
| カラムリネーム | `t_sjnet_import_row.resolved_staff_id` | `resolved_staff_user_id` → `resolved_staff_id`（参照先: `m_staff.id`） | ■ 適用済み | ■ 適用済み |

### 実装への影響

- 全リポジトリ: 担当者カラム名を `*_staff_id` に統一済み
- `StaffRepository`（`src/Domain/Tenant/StaffRepository.php`）: `m_staff` への全アクセスを集約
- `CaseStatusRepository`（`src/Domain/Tenant/CaseStatusRepository.php`）: `m_case_status` への全アクセスを集約
- 各コントローラーの担当者プルダウン: `fetchAssignableUsers`（common DB の `users` テーブル参照）を廃止し、`StaffRepository.findActive()` に統一
- `SjnetCsvImportService`: `m_sjnet_staff_mapping` 参照を廃止し、`StaffRepository.findBySjnetCode()` に変更

---

## 13. TOTP 2FA カラム追加（2026-04-04）

### 変更内容

| 変更種別 | 対象テーブル | カラム | 内容 |
|---|---|---|---|
| カラム追加 | `users`（common DB） | `totp_secret VARCHAR(64) NULL` | TOTP秘密鍵（base32）。NULL=未設定 |
| カラム追加 | `users`（common DB） | `totp_enabled TINYINT(1) NOT NULL DEFAULT 0` | TOTP有効フラグ（1=有効、0=未設定） |
| カラム追加 | `users`（common DB） | `totp_verified_at DATETIME NULL` | TOTP初回確認日時 |

### マイグレーション

`config/ddl/common/migration_add_totp.sql` を適用済み。実DB（xs000001_admin）に `ALTER TABLE users ADD COLUMN totp_secret / totp_enabled / totp_verified_at` を実施。

### 実装への影響

- `src/Auth/Totp.php`: RFC 6238 TOTP スクラッチ実装（composerなし）。`generateSecret()` / `compute()` / `verify()` / `buildOtpAuthUri()`
- `src/Auth/AuthService.php`: `loginWithGoogleIdentity()` の戻り値を `['status' => ...]` 形式に変更。`system_admin=1` は即認証、一般ユーザーは `totp_pending` 状態へ。`completeLogin()` を追加
- `src/SessionManager.php`: `setTotpPendingUserId()` / `getTotpPendingUserId()` / `clearTotpPending()` を追加
- `src/Security/AuthGuard.php`: `requireTotpPending()` を追加
- `src/Domain/Auth/UserRepository.php`: `findActiveById()` / `saveTotpSecret()` / `enableTotp()` を追加。全 SELECT に `totp_secret` / `totp_enabled` / `totp_verified_at` を追加
- `src/Controller/AuthController.php`: `totpShow()` / `totpVerify()` / `totpSetupShow()` / `totpSetupVerify()` を追加
- `src/Presentation/View/TotpView.php`: TOTP確認画面（コード入力）
- `src/Presentation/View/TotpSetupView.php`: TOTP初期設定画面（QRコード表示＋コード入力）
- `src/bootstrap.php`: `auth/totp` / `auth/totp/verify` / `auth/totp-setup` / `auth/totp-setup/verify` ルートを追加

