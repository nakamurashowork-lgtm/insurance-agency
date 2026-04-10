# 日報ビュー（SCR-ACTIVITY-DAILY）

## 1. 画面の目的

指定日に記録された営業活動を集約表示し、その日の日報コメントを入力・保存する画面。

活動の新規登録・編集はここでは行わない。

## 2. 位置づけ

- main nav には掲載しない（活動一覧の補助画面）
- 活動一覧（activity/list）の「日付リンク」から遷移する

## 3. 到達経路

- 活動一覧の日付リンク：`activity/daily?date={YYYY-MM-DD}`
- 日付・担当者指定：`activity/daily?date={YYYY-MM-DD}&staff={staff_user_id}`

## 4. 表示内容

### 4-1. ヘッダー情報

| 項目 | 内容 |
|------|------|
| 表示日付 | URLパラメータの date |
| 担当者 | URLパラメータの staff_user_id（省略時はログインユーザー） |
| 前日 / 翌日リンク | 同担当者・前後日の日報ビューへ |

### 4-2. 活動一覧セクション

その日（activity_date）・担当者（staff_user_id）に一致する活動を時刻順（start_time ASC, id ASC）で一覧表示。

| 列 | 出力元 | 備考 |
|----|--------|------|
| 時刻 | start_time ～ end_time | null の場合は空欄 |
| 活動種別 | activity_type | |
| 顧客名 | m_customer.customer_name | 顧客詳細へのリンク |
| 件名 | subject | |
| 内容要約 | content_summary | |
| 次回予定日 | next_action_date | |
| 詳細リンク | - | activity/detail?id={id} へ |

活動が0件の場合は「この日の活動記録はありません」と表示する。

### 4-3. 日報コメントセクション

t_daily_report（report_date × staff_user_id の UNIQUE レコード）のコメントを表示・編集する。

| 項目 | カラム | 備考 |
|------|--------|------|
| コメント | t_daily_report.comment | テキストエリア。1スタッフ1日1件 |

保存ボタン押下で POST activity/comment に送信。サーバー側は INSERT ON DUPLICATE KEY UPDATE で upsert する。

コメントはいつでも編集・保存できる。

### 4-4. 提出機能（削除済み）

日報提出機能は本フェーズでは UI から削除されている。

理由: 承認フロー（上司確認 → 承認/差し戻し）が未実装のため、提出の業務的意味がない。

DDL（`t_daily_report.is_submitted`, `submitted_at`）、Repository（`DailyReportRepository::submit()`）、
ルート（`POST activity/submit`）は将来の承認フロー実装に備えて残してある。

## 5. アクション

| アクション | 処理 | 備考 |
|-----------|------|------|
| コメント保存 | POST activity/comment | upsert。保存後は同画面にリダイレクト |
| 活動の詳細リンク | GET activity/detail?id={id} | 活動詳細へ遷移 |
| 顧客名リンク | GET customer/detail?id={id} | |
| 前日 / 翌日リンク | GET activity/daily?date=...&staff=... | |
| 戻る | GET activity/list | 活動一覧へ |

## 6. 権限・テナント分離

- ログイン必須。全ロール（admin/member）がアクセス可能。
- デフォルトはログインユーザー自身の日報を表示。
- 管理者ロール（admin）は staff パラメータを指定して他担当者の日報を参照できる。
- 他テナントのデータは参照不可。

## 7. ルート定義

| メソッド | ルート | 処理 |
|---------|--------|------|
| GET | activity/daily | 日報ビュー表示 |
| POST | activity/comment | コメント upsert |
| POST | activity/submit | 日報提出（UI 非表示、将来の承認フロー実装用に予約） |

## 8. 関連画面

| 画面 | 関係 |
|------|------|
| activity/list | 日付リンク経由で到達。戻り先 |
| activity/detail | 活動一覧の詳細リンクから遷移 |
| customer/detail | 顧客名リンクから遷移 |
