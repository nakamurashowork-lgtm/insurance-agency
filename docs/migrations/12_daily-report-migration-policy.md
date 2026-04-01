# 営業日報 Excel と DB 活動記録の対応方針

## 1. 問題

`t_activity` および `t_daily_report` が日報業務に対応するとされているが、
営業日報 Excel のフォーマット（`営業日報2026_01_1.xls`）と
DB カラムとの対応表がどこにも存在しない。

Excel 日報から DB への移行・並行運用時に業務混乱が生じるリスクがある。

## 2. Excel 営業日報の構造（現行）

Excel 営業日報は以下の記録単位で運用されている。

| Excel 記録単位 | 対応 DB テーブル | 対応カラム |
|---|---|---|
| 日付 | `t_activity` | `activity_date` |
| 担当者 | `t_activity` | `staff_user_id` |
| 顧客名 | `t_activity` | `customer_id`（`m_customer` 参照） |
| 活動種別（訪問・電話・メール等） | `t_activity` | `activity_type` |
| 用件区分（満期対応・新規等） | `t_activity` | `purpose_type` |
| 訪問先・面談場所 | `t_activity` | `visit_place` |
| 面談相手 | `t_activity` | `interviewee_name` |
| 件名・テーマ | `t_activity` | `subject` |
| 活動内容・メモ | `t_activity` | `content_summary` / `detail_text` |
| 次回予定 | `t_activity` | `next_action_date` / `next_action_note` |
| 結果（成約・継続等） | `t_activity` | `result_type` |
| 日報コメント（1日全体のまとめ） | `t_daily_report` | `comment_text` |

## 3. 移行・並行運用の方針

### 3-1. 基本方針

**段階的移行**とし、Excel 廃止を強制しない。

システム導入後は Excel 日報との並行運用を許容する期間を設け、
担当者ごとに入力先を切り替えるタイミングを個別に決める。

### 3-2. 移行フェーズ

| フェーズ | 期間目安 | 運用 |
|---|---|---|
| Phase 1（並行期） | 導入〜3か月 | Excel とシステムの両方に入力してよい。システム入力を推奨するが強制しない |
| Phase 2（移行期） | 3〜6か月 | システム入力を基本とし、Excel は補助記録のみ |
| Phase 3（完全移行） | 6か月以降 | Excel 日報を廃止し、システムのみで管理 |

フェーズの切り替え判断は管理者が行い、テナント設定や運用規程で明示する。

### 3-3. 過去 Excel データの扱い

過去 Excel 日報データの DB への一括インポートは**現フェーズでは行わない**。

理由：顧客名と `m_customer.id` の突合が手作業になるため、
インポートコストと精度リスクが移行メリットを上回る可能性がある。

必要に応じて将来フェーズで CSV インポート機能を検討する。

## 4. activity_type の標準値定義

Excel の「活動種別」列で使われてきた語彙と `activity_type` の対応を定義する。
`activity_type` はマスタ管理対象ではなく、アプリ側で固定値リストを持つ。

| 画面表示値 | DB格納値 | Excel対応語彙 |
|---|---|---|
| 訪問 | `visit` | 訪問・外訪 |
| 電話 | `phone` | 電話・TEL |
| メール | `email` | メール・FAX |
| オンライン | `online` | ZOOM・Web会議 |
| 社内対応 | `internal` | 内勤・社内作業 |
| その他 | `other` | その他 |

## 5. result_type の標準値定義

| 画面表示値 | DB格納値 |
|---|---|
| 継続対応 | `follow` |
| 見積依頼 | `quote_requested` |
| 申込受付 | `applied` |
| 成約 | `contracted` |
| 見送り | `postponed` |
| 失注 | `lost` |
| その他 | `other` |

## 6. 対応範囲外の項目

以下の Excel 記録項目はシステム DB に対応するカラムがなく、
現フェーズでは `detail_text`（詳細内容）に自由記述として包含する。

- 走行距離
- 交通費
- 同行者名

これらを独立カラムとして管理する必要が生じた場合は、
`t_activity` へのカラム追加を別途検討する。
