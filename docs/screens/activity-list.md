# 活動一覧（SCR-ACTIVITY-LIST）

## 1. 画面の目的

営業活動の記録を一覧で確認し、新規登録・詳細確認・日報ビューへの起点となる画面。

ナビゲーション上の「営業活動」から到達する主要入口。

## 2. 位置づけ

- main nav「営業活動」直結の正式入口画面
- 新規活動登録（activity/new）への起点
- 活動詳細（activity/detail）への起点
- 日報ビュー（activity/daily）への起点（日付リンク経由）

## 3. デフォルト表示

画面を開いた直後は以下の条件で表示する。

- 活動日：今日（システム日付）
- 担当者：ログインユーザー自身
- ページ：1ページ目

管理者ロールの場合は担当者フィルタを「全員」にしてもよい（将来対応可）。

## 4. 検索・フィルタ条件

| 項目 | 種別 | 備考 |
|------|------|------|
| 活動日（開始） | 日付 | activity_date の範囲検索（下限） |
| 活動日（終了） | 日付 | activity_date の範囲検索（上限） |
| 顧客名 | テキスト | m_customer.customer_name の部分一致 |
| 活動種別 | セレクト | activity_type の一致 |
| 担当者 | セレクト | staff_user_id の一致。自分のみ / 全員 |
| 日報提出状態 | セレクト | 全て / 提出済み / 未提出。t_daily_report.is_submitted を基準に絞り込む。管理者ロールのみ表示 |

## 5. 一覧表示項目

| 列 | 出力元 | 備考 |
|----|--------|------|
| 活動日 | t_activity.activity_date | 日報ビューへのリンク。日報が提出済みの場合は「提出済み」バッジを付与 |
| 活動種別 | t_activity.activity_type | 訪問・電話・メール等 |
| 顧客名 | m_customer.customer_name | 顧客詳細へのリンク |
| 件名 | t_activity.subject | |
| 内容要約 | t_activity.content_summary | 一部省略表示可 |
| 次回予定日 | t_activity.next_action_date | 期日が近いものを強調可 |
| 担当者 | common.users の表示名 | |
| 操作 | - | 詳細リンク |

## 6. アクション

| アクション | 遷移先 | 備考 |
|-----------|--------|------|
| ＋活動登録 | activity/new | customer_id なしで開始 |
| 行クリック（詳細） | activity/detail?id={id} | |
| 日付リンク | activity/daily?date={date}&staff={staff_user_id} | 日報ビューへ |
| 顧客名リンク | customer/detail?id={customer_id} | |

## 7. ページネーション

- 1ページあたりの件数：20件（他画面と統一）
- 総件数・現在ページ・次/前リンクを表示

## 8. 権限・テナント分離

- ログイン必須。全ロール（admin/member）がアクセス可能。
- クエリは tenant DB に閉じる。他テナントのデータは参照しない。
- staff_user_id フィルタで自分の活動のみ表示するのがデフォルト。
- 日報提出状態フィルタは管理者ロール（admin）のみ表示する。

## 9. 関連画面

| 画面 | 関係 |
|------|------|
| activity/new | 一覧の「＋活動登録」から遷移（新規専用） |
| activity/detail | 一覧の行から遷移（既存の確認・編集専用） |
| activity/daily | 一覧の日付リンクから遷移（日報ビュー） |
| customer/detail | 一覧の顧客名リンクから遷移 |
