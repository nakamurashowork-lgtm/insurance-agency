# customer-detail.md 追記パッチ
# 対象: docs/screens/customer-detail.md
# 追記場所: セクション8「権限」の直後（セクション9として挿入）

---

## 9. 顧客更新仕様

### 9-1. 更新可能項目

顧客詳細画面から更新できる項目は以下に限定する。

| 項目 | カラム | 備考 |
|---|---|---|
| 顧客名 | customer_name | 必須 |
| 顧客名カナ | customer_name_kana | 任意 |
| 顧客区分 | customer_type | individual / corporate |
| 電話番号 | phone | 任意 |
| メールアドレス | email | 任意 |
| 郵便番号 | postal_code | 任意 |
| 住所1 | address1 | 任意 |
| 住所2 | address2 | 任意 |
| 主担当者 | assigned_user_id | テナント所属ユーザーから選択 |
| ステータス | status | prospect / active / inactive / closed |
| 備考 | note | 任意 |

### 9-2. 更新不可項目

以下の項目は顧客詳細画面から変更しない。

- `id`（顧客ID）
- `created_by` / `created_at`

### 9-3. 権限

- ログイン済みの全ロール（admin / member）が更新可能とする。
- ロールによる更新可否の制御は現フェーズでは行わない。

### 9-4. バリデーション

- `customer_name` は必須。空文字および空白のみは不可。
- `customer_type` は `individual` または `corporate` のみ許容する。
- `status` は `prospect` / `active` / `inactive` / `closed` のみ許容する。
- `assigned_user_id` に指定する場合は、テナント所属ユーザーのIDであることをアプリ側で確認する。

### 9-5. 更新後の動作

- 更新成功 → 同じ顧客詳細画面を再表示し、成功メッセージを表示する。
- バリデーションエラー → 詳細画面を再表示し、エラーメッセージと入力値を保持する。

### 9-6. 成功・失敗メッセージ

- 更新成功: 顧客情報を更新しました。
- 更新失敗: 顧客情報の更新に失敗しました。

### 9-7. エンドポイント

- `POST customer/update`（CSRF検証必須）

### 9-8. 連絡先の更新

`m_customer_contact`（連絡先）の登録・編集・削除は、顧客詳細画面内のインライン操作として扱う。

| 操作 | エンドポイント |
|---|---|
| 連絡先を追加 | POST customer/contact/store |
| 連絡先を更新 | POST customer/contact/update |
| 連絡先を削除 | POST customer/contact/delete |

主連絡先（is_primary = 1）は1顧客につき1件のみ設定可能とする。
既に主連絡先が設定されている場合に別の連絡先を主連絡先に変更すると、既存の主連絡先は自動的に `is_primary = 0` に更新される。
