# 日報ビュー（SCR-ACTIVITY-DAILY）

## 1. 画面の目的

指定日に記録された営業活動を集約表示し、その日の日報コメントを入力・保存する画面。

日報の提出操作もこの画面から行う。活動の新規登録・編集はここでは行わない。

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
| 提出状態 | 未提出 / 提出済み（submitted_at を表示） |
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

提出済みの場合、コメント欄は読み取り専用とし、保存ボタンを非表示にする。

### 4-4. 提出セクション

| 項目 | 内容 |
|------|------|
| 提出ボタン | 未提出の場合のみ表示。押下で POST activity/submit を送信 |
| 提出済み表示 | is_submitted=1 の場合、提出日時（submitted_at）を表示。ボタンは非表示 |

提出操作は取り消せない。提出後はコメント編集・再提出ができない。

管理者ロールが他担当者の日報を参照している場合、提出ボタンは表示しない（本人のみ提出可能）。

## 5. アクション

| アクション | 処理 | 備考 |
|-----------|------|------|
| コメント保存 | POST activity/comment | upsert。保存後は同画面にリダイレクト。提出済みの場合は操作不可 |
| 日報を提出する | POST activity/submit | is_submitted=1、submitted_at=NOW() をUPDATE。提出済みの場合はボタン非表示 |
| 活動の詳細リンク | GET activity/detail?id={id} | 活動詳細へ遷移（activity/daily は読み取り＋コメント＋提出のみ） |
| 顧客名リンク | GET customer/detail?id={id} | |
| 前日 / 翌日リンク | GET activity/daily?date=...&staff=... | |
| 戻る | GET activity/list | 活動一覧へ |

## 6. 権限・テナント分離

- ログイン必須。全ロール（admin/member）がアクセス可能。
- デフォルトはログインユーザー自身の日報を表示。
- 管理者ロール（admin）は staff パラメータを指定して他担当者の日報を参照できる。
- 提出操作は本人のみ可能。管理者が他担当者の日報を参照している場合も提出ボタンは表示しない。
- 他テナントのデータは参照不可。

## 7. ルート定義

| メソッド | ルート | 処理 |
|---------|--------|------|
| GET | activity/daily | 日報ビュー表示 |
| POST | activity/comment | コメント upsert |
| POST | activity/submit | 日報提出（is_submitted=1 / submitted_at=NOW()） |

## 8. 関連画面

| 画面 | 関係 |
|------|------|
| activity/list | 日付リンク経由で到達。戻り先。提出済み/未提出フィルタあり |
| activity/detail | 活動一覧の詳細リンクから遷移 |
| customer/detail | 顧客名リンクから遷移 |
