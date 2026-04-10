# 活動一覧（SCR-ACTIVITY-LIST）

## 1. 画面の目的

営業活動の記録を**期間・担当者・顧客名などで横断検索・集計するための画面**。

日常の活動確認・登録は日報ビュー（activity/daily）で行う。活動一覧は検索・集計用と位置づけ、メインナビの「営業活動」タップ後は日報ビューを優先し、一覧は「過去の活動を検索」などのサブリンク経由とする。

## 2. 位置づけ

- 活動の横断検索・集計用画面
- main nav「営業活動」は日報ビュー（activity/daily）が主要入口
- 日報ビューのヘッダーに「過去の活動を検索」リンクを設置して活動一覧へ誘導する
- 活動詳細（activity/detail）の「戻る」は遷移元（daily / list / customer）を動的に解決する

## 3. デフォルト表示

フィルタ未指定のまま開いた場合、絞り込み条件なしで全件を返す（デフォルトで当日に絞らない）。

## 4. 検索・フィルタ条件

| 項目 | 種別 | 備考 |
|------|------|------|
| 活動日（開始） | 日付 | activity_date の範囲検索（下限） |
| 活動日（終了） | 日付 | activity_date の範囲検索（上限） |
| 顧客名 | テキスト | m_customer.customer_name の部分一致 |
| 活動種別 | セレクト | activity_type の一致 |
| 担当者 | セレクト | staff_user_id の一致。自分のみ / 全員 |

## 5. 一覧表示項目

| 列 | 出力元 | 備考 |
|----|--------|------|
| 活動日 | t_activity.activity_date | 日報ビューへのリンク |
| 顧客名 | m_customer.customer_name | 顧客詳細へのリンク |
| 件名 | t_activity.subject | 活動詳細へのリンク |
| 担当者 | common.users の表示名 | |
| 活動種別 | t_activity.activity_type | 訪問・電話・メール等 |
| 次回予定日 | t_activity.next_action_date | |

## 6. アクション

| アクション | 遷移先 | 備考 |
|-----------|--------|------|
| ＋活動登録 | activity/new | customer_id なしで開始 |
| 日報ビュー | activity/daily?date={今日の日付}&staff={ログインユーザーID} | ページヘッダー右側。副操作ボタン（btn クラス）。「＋活動登録」ボタンの左隣に配置する |
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

## 9. 関連画面

| 画面 | 関係 |
|------|------|
| activity/new | 一覧の「＋活動登録」から遷移（新規専用） |
| activity/detail | 一覧の行から遷移（既存の確認・編集専用） |
| activity/daily | 一覧の日付リンクから遷移（日報ビュー） |
| customer/detail | 一覧の顧客名リンクから遷移 |
