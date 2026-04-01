# 05_implementation-plan.md 追記パッチ
# 対象: docs/plans/05_implementation-plan.md
# 追記場所: Phase 営業活動管理 Phase C（セクション33）の直後

---

## 34. 変更履歴詳細表示（audit_event_detail 表示対応）

### 目的

満期詳細・事故案件詳細の変更履歴領域に、項目単位の変更前後の値を表示できるようにする。

Phase 2 受入判定で「未実装」として残存していた `t_audit_event_detail` の画面表示を確定実装する。

### 前提

- `t_audit_event` および `t_audit_event_detail` DDL は追加済み。変更禁止。
- Phase 2 時点で `t_audit_event` の一覧（日時・変更者・操作種別）は実装済み。
- 本フェーズでは `t_audit_event_detail` の内容（項目名・変更前・変更後）を追加表示する。

### 対象画面

- SCR-RENEWAL-DETAIL（満期詳細の変更履歴領域）
- SCR-ACCIDENT-DETAIL（事故案件詳細の変更履歴領域）

### 表示仕様

変更履歴の各イベント行を展開すると、以下を表示する。

| 表示項目 | 取得元カラム |
|---|---|
| 項目名 | t_audit_event_detail.field_label |
| 変更前の値 | t_audit_event_detail.before_value_text |
| 変更後の値 | t_audit_event_detail.after_value_text |

`before_value_text` または `after_value_text` が NULL の場合は「未設定」と表示する。
`value_type` が `JSON` の場合は `before_value_json` / `after_value_json` を参照する。

### 対象PHPファイル（変更）

- `src/Domain/Renewal/RenewalCaseRepository.php`：`findAuditEventDetails(audit_event_id)` を追加
- `src/Domain/Accident/AccidentCaseRepository.php`：同上
- `src/Presentation/RenewalCaseDetailView.php`：変更履歴領域にイベント詳細の展開表示を追加
- `src/Presentation/AccidentCaseDetailView.php`：同上

### 完了条件

- 満期詳細の変更履歴で、各イベントの変更項目・変更前後の値が確認できる
- 事故案件詳細でも同様に確認できる
- `t_audit_event_detail` が0件のイベントでは詳細行を表示しない（エラーにしない）
- 他テナントのデータが参照されないこと

