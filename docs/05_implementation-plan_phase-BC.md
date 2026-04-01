## 32. 営業活動管理 Phase B — Daily Report View + 提出フロー（追加フェーズ）

### 目的

指定日の活動を集約表示し、日報コメントを入力・保存できる日報ビューを追加する。
あわせて、日報提出操作（is_submitted / submitted_at の更新）と、管理者向けの未提出確認フィルタを実装する。

### 前提

- Phase A 完了後に着手。
- `t_daily_report` DDL は追加済み。UNIQUE KEY(report_date, staff_user_id)。変更禁止。
- 日報コメントの upsert は INSERT ON DUPLICATE KEY UPDATE で実装。
- 日報提出は本人のみ実行可能。管理者が他担当者の日報を参照中は提出ボタンを表示しない。
- 提出後の取り消しはできない。提出済みレコードのコメント編集も不可とする。

### 対象画面

- SCR-ACTIVITY-DAILY（日報ビュー）
- SCR-ACTIVITY-LIST（活動一覧）：日報提出状態フィルタを追加

### 対象PHPファイル（新規）

- `src/Domain/Activity/DailyReportRepository.php`
- `src/Presentation/ActivityDailyView.php`

### 対象PHPファイル（変更）

- `src/Controller/ActivityController.php`：`daily()` / `saveComment()` / `submit()` メソッド追加
- `src/bootstrap.php`：ルート追加（計3本）
  - `GET  activity/daily`
  - `POST activity/comment`
  - `POST activity/submit`
- `src/Domain/Activity/ActivityRepository.php`：一覧検索に日報提出状態フィルタ条件を追加
- `src/Presentation/ActivityListView.php`：管理者ロール時に日報提出状態フィルタを表示。日付リンクに「提出済み」バッジを付与

### 必要なDBテーブル

- `t_daily_report`（日報コメント・提出フラグ）
- `t_activity`（その日の活動一覧）
- `common.users`（担当者名表示）

### 完了条件

- 活動一覧の日付リンクから日報ビューへ遷移できる
- 日報ビューで指定日の活動が一覧表示される（自分の活動のみ。管理者は担当者切替可能）
- 日報コメントを入力・保存できる（1スタッフ1日1件。再保存で上書き）
- 未提出の場合のみ「日報を提出する」ボタンが表示される
- 提出ボタン押下で is_submitted=1 / submitted_at=NOW() が記録される
- 提出済みの場合、コメント欄は読み取り専用になり、提出ボタンは非表示になる
- 提出済みの場合、提出日時（submitted_at）がヘッダーに表示される
- 管理者ロールが他担当者の日報を参照中は提出ボタンを表示しない
- 活動一覧で管理者ロール時に日報提出状態フィルタ（全て / 提出済み / 未提出）が表示される
- 提出済み日報の日付リンクに「提出済み」バッジが付与される
- 他テナントのデータが参照されないこと

---

> **ルート追加の総計（Phase A + Phase B）**: 9 本（Phase A: 6本 + Phase B: 3本）

---

## 33. 営業活動管理 Phase C-Lite — Sales Case 最小実装（追加フェーズ）

### 目的

Excel「日報」の「見込み」シートで月次管理されていた営業パイプライン業務をWebに移行する。
見込案件の登録・確認・編集・削除の最小実装を行い、活動記録との紐づけを有効化する。
パイプライン集計・分析・ファネル管理は Phase C-Full で扱う。

### 前提

- Phase A/B 完了後に着手。
- `t_sales_case` DDL は追加済み。変更禁止。
- Phase A で「sales_case_id は UI 非表示・DB は NULL 固定」としていた制限を本フェーズで解除する。
- 本フェーズ完了後、`02_navigation-policy.md` の指定に従い、見込案件一覧を main nav に追加する。
- テナント分離は既存パターン（TenantConnectionFactory）に完全準拠。

### 対象画面

- SCR-SALES-CASE-LIST（見込案件一覧）
- SCR-SALES-CASE-NEW（見込案件登録）
- SCR-SALES-CASE-DETAIL（見込案件詳細）

### 対象PHPファイル（新規）

- `src/Domain/SalesCase/SalesCaseRepository.php`
- `src/Controller/SalesCaseController.php`
- `src/Presentation/SalesCaseListView.php`
- `src/Presentation/SalesCaseDetailView.php`

### 対象PHPファイル（変更）

- `src/bootstrap.php`：SalesCaseController DI登録、ルート追加（計6本）
  - `GET  sales-case/list`
  - `GET  sales-case/new`
  - `GET  sales-case/detail`
  - `POST sales-case/store`
  - `POST sales-case/update`
  - `POST sales-case/delete`
- `src/Presentation/View/Layout.php`：navLinks に「営業案件」追加（Phase C-Lite 完了後）
- `src/Controller/ActivityController.php`：活動登録・更新時の sales_case_id 受け取りを有効化
- `src/Presentation/ActivityDetailView.php`：sales_case_id プルダウンを表示・選択可能にする
- `src/Presentation/CustomerDetailView.php`：顧客に紐づく見込案件一覧セクションを追加

### 必要なDBテーブル

- `t_sales_case`（主）
- `m_customer`（顧客名表示・選択）
- `t_contract`（契約紐づけ。任意）
- `common.users`（担当者名表示）
- `t_activity`（見込案件詳細での紐づき活動一覧表示）

### 一覧表示項目（最小構成）

| 項目 | カラム | 備考 |
|------|--------|------|
| 顧客名 | m_customer.customer_name | 顧客詳細へのリンク |
| 案件種別 | case_type | 新規・更新・クロスセル等 |
| 種目 | product_type | |
| 見込保険料 | expected_premium | |
| 見込度 | probability | A / B / C |
| 成約予定月 | expected_close_month | |
| ステータス | status | |
| 担当者 | common.users の表示名 | |
| 操作 | - | 詳細リンク |

### 完了条件

- 見込案件を新規登録できる（顧客必須・種目・見込保険料・見込度・成約予定月）
- 見込案件一覧で顧客名・担当者・ステータス・見込度でフィルタできる
- 見込案件詳細で内容を確認・編集・削除できる
- 見込案件詳細から紐づく活動履歴を参照できる
- 活動登録・編集画面で sales_case_id を見込案件から選択できる（プルダウン）
- 顧客詳細に「この顧客の見込案件」セクションが表示される
- 見込案件一覧が main nav「営業案件」から到達できる
- 他テナントのデータが参照されないこと

---

> **ルート追加の総計（Phase A + Phase B + Phase C-Lite）**: 15 本

---

## 34. 営業活動管理 Phase C-Full — Sales Case パイプライン管理（予定フェーズ）

Phase C-Lite 完了後に別途スコープを定義する。

対象となる業務機能は以下を想定するが、着手時に改めて確定する。

- 見込案件のパイプライン集計・ファネル分析
- 担当者別・種目別・月別の見込保険料サマリ
- ダッシュボードへの見込案件要約ウィジェット追加
- 成約・失注の結果記録と実績との突合
- 見込案件からの実績登録導線

本フェーズの実装計画・完了条件は Phase C-Lite 完了後に追記する。
