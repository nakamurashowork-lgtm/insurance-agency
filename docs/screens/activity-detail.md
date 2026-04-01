# 活動登録 / 活動詳細（SCR-ACTIVITY-NEW / SCR-ACTIVITY-DETAIL）

## 1. 責務の分離（重要）

この文書は2つの画面を扱うが、責務は明確に異なる。

| 画面 | ルート | 責務 |
|------|--------|------|
| 活動登録 | `activity/new` | **新規登録のみ**。フォームは空で開始。既存データの参照・編集には使わない。 |
| 活動詳細 | `activity/detail` | **既存活動の確認・編集・削除のみ**。`?id=` で活動IDを受け取る。新規登録の起点には使わない。 |

## 2. 活動登録（activity/new）

### 2-1. 画面の目的

新規の営業活動1件を登録する専用画面。登録後は活動一覧（activity/list）に戻る。

### 2-2. 到達経路

- 活動一覧の「＋活動登録」ボタン
- 顧客詳細の「活動登録」ボタン（customer_id を引き継ぎ）

### 2-3. 入力項目

| 項目 | カラム | 必須 | 備考 |
|------|--------|------|------|
| 顧客 | customer_id | **必須** | 顧客選択またはURLパラメータで引き継ぎ |
| 活動日 | activity_date | **必須** | デフォルト：今日 |
| 開始時刻 | start_time | 任意 | |
| 終了時刻 | end_time | 任意 | |
| 活動種別 | activity_type | **必須** | 訪問・電話・メール・オンライン等 |
| 用件区分 | purpose_type | 任意 | `m_activity_purpose_type` からの選択。フリーテキスト入力は不可 |
| 訪問先 | visit_place | 任意 | |
| 面談者 | interviewee_name | 任意 | |
| 件名 | subject | 任意 | |
| 内容要約 | content_summary | **必須** | 最大500文字 |
| 詳細内容 | detail_text | 任意 | テキストエリア |
| 次回予定日 | next_action_date | 任意 | |
| 次回アクション | next_action_note | 任意 | |
| 結果区分 | result_type | 任意 | |
| 満期案件 | renewal_case_id | 任意 | |
| 事故案件 | accident_case_id | 任意 | |
| 営業案件 | sales_case_id | **Phase C-Lite まで非表示** | DB上は NULL で保存 |
| 担当者 | staff_user_id | 任意 | デフォルト：ログインユーザー |

### 2-4. 登録後の動作

- 登録成功 → activity/list にリダイレクト
- バリデーションエラー → フォームを再表示（入力値を保持）

## 3. 活動詳細（activity/detail）

### 3-1. 画面の目的

既存の営業活動1件を確認し、内容の編集または論理削除を行う画面。新規登録には使わない。

### 3-2. 到達経路

- 活動一覧の「詳細」リンク（`?id={id}`）
- 顧客詳細の活動履歴セクションのリンク（`?id={id}`）

### 3-3. 表示項目

2-3 の入力項目と同一のフィールドを表示・編集可能。

| 追加表示項目 | 備考 |
|-------------|------|
| 顧客名リンク | customer/detail へのリンク |
| 作成日時 | created_at（表示のみ） |
| 更新日時 | updated_at（表示のみ） |

### 3-4. アクション

| アクション | 処理 | 備考 |
|-----------|------|------|
| 保存 | POST activity/update | 更新後は activity/detail?id={id} にリダイレクト |
| 削除 | POST activity/delete | is_deleted = 1 に更新（論理削除）。確認ダイアログを表示。削除後は activity/list にリダイレクト |

### 3-5. 存在確認

- `?id=` が指定されていない、または is_deleted = 1 のレコードの場合は activity/list にリダイレクト。

## 4. 実装上の制約

### 4-1. purpose_type はマスタ選択のみ（フリーテキスト入力禁止）

`purpose_type` は `m_activity_purpose_type.code` の値のみを格納する。  
プルダウン（セレクトボックス）でマスタから選択する形式とし、フリーテキスト入力は実装しない。  
マスタに存在しない値が送信された場合はバリデーションエラーとする。

選択肢はマスタの `is_active = 1` かつ `display_order ASC` で取得する。  
マスタの追加・変更はテナント設定のマスタ管理セクションで行う。

### 4-2. sales_case_id は Phase C-Lite まで NULL 固定

`sales_case_id` カラムは DDL に存在し、`t_sales_case` への外部キーとして保持される。  
しかし Phase A/B（現行実装）では、以下の制約を適用する。

- フォームに `sales_case_id` の入力項目を表示しない
- 新規登録・更新のいずれにおいても、PHP コード側で `PDO::PARAM_NULL` として明示的に NULL を保存する
- 既存レコードに `sales_case_id` が設定されていても、詳細画面では参照・変更できない

この制約は設計上の意図であり、バグではない。  
Phase C-Lite にて `t_sales_case` の CRUD と `t_activity.sales_case_id` の紐づけ UI を追加する際に解除する。

## 5. 権限・テナント分離

- ログイン必須。全ロール（admin/member）がアクセス可能。
- レコードの tenant DB 帰属はテナント接続単位で保証される。
- 他テナントのレコードには到達不能（tenant DB が分離されているため）。

## 6. 関連画面

| 画面 | 関係 |
|------|------|
| activity/list | 登録後・削除後のリダイレクト先。一覧の起点 |
| customer/detail | 顧客名リンクから遷移 |
