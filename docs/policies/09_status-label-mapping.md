# ステータスラベル定義

## 1. 位置づけ

本文書は、DB 内部値と画面表示ラベル、および現行 Excel 運用時のステータス語彙との対応を定義する。

**重要:** 本システムのマスタ管理方針は、ラベル名（＝画面表示用の日本語文字列）をそのまま DB 値として格納する方式である。したがって満期案件・事故案件・見込案件の `case_status / status` に関しては「DB 値 = 画面表示ラベル」であり、コード側での変換は不要。

コード定数系（priority, customer_type, performance_type 等）については従来通り英字コードを DB に格納し、画面側でラベルへ変換する。

---

## 2. 満期案件ステータス（t_renewal_case.case_status）

**格納先マスタ:** `m_case_status` where `case_type='renewal'`  
**DB 値:** マスタの `name` をそのまま格納（＝画面表示ラベル）

### 2-1. マスタ収録値

| 表示ラベル = DB 値 | display_order | is_completed | is_protected | 備考 |
|---|---|---|---|---|
| 未対応 | 10 | 0 | 0 | 案件作成直後。既定値 |
| SJ依頼中 | 20 | 0 | 0 | SJ への更改依頼を行った状態 |
| 書類作成済 | 30 | 0 | 0 | 見積・申込書を作成した状態 |
| 返送待ち | 40 | 0 | 0 | 書類の返送や顧客回答待ち |
| 見積送付済 | 50 | 0 | 0 | 見積書を送付した状態 |
| 入金待ち | 60 | 0 | 0 | 保険料の入金待ち |
| 完了 | 70 | 1 | 1 | 更改・継続・失注含む最終確定 |
| 取り下げ | 75 | 1 | 1 | 案件を取り下げた状態 |
| 失注 | 80 | 1 | 0 | 他社流出・更改断念 |
| 解約 | 85 | 1 | 0 | 契約解約で終了 |

### 2-2. Excel 運用時のステータスとの対応（移行参考）

| Excel 表示値 | 新 DB 値 | 備考 |
|---|---|---|
| SJ依頼中 | `SJ依頼中` | そのまま |
| 書類作成済 | `書類作成済` | そのまま |
| 返送待ち | `返送待ち` | そのまま |
| 見積書送付済 | `見積送付済` | ラベル微調整 |
| 入金待ち | `入金待ち` | そのまま |
| 完了 | `完了` | 更改結果は `renewal_result` 列で判別 |

### 2-3. 運用上の注意

「完了」は最終結果が複数あり得るため、`renewal_result`（`renewed` / `cancelled` / `lost` / `pending`）と併せて解釈する。失注は `失注`、解約は `解約` として別ステータスが用意されているため、原則そちらを使用する。

---

## 3. 事故案件ステータス（t_accident_case.status）

**格納先マスタ:** `m_case_status` where `case_type='accident'`  
**DB 値:** マスタの `name` をそのまま格納

| 表示ラベル = DB 値 | display_order | is_completed | is_protected |
|---|---|---|---|
| 受付 | 10 | 0 | 0 |
| 保険会社連絡済み | 20 | 0 | 0 |
| 対応中 | 30 | 0 | 0 |
| 書類待ち | 40 | 0 | 0 |
| 解決済み | 50 | 0 | 0 |
| 完了 | 60 | 1 | 1 |

## 4. 事故案件優先度（t_accident_case.priority）

**DB 値はコード（英字）。画面側で変換。**

| DB 値 | 画面表示ラベル |
|---|---|
| `low` | 低 |
| `normal` | 中 |
| `high` | 高 |

---

## 5. 顧客ステータス（m_customer.status）

**DB 値はコード（英字）。画面側で変換。**

| DB 値 | 画面表示ラベル |
|---|---|
| `prospect` | 見込み |
| `active` | 有効 |
| `inactive` | 非活性 |
| `closed` | 解約済み |

---

## 6. 見込案件ステータス（t_sales_case.status）

**格納先マスタ:** `m_sales_case_status`  
**DB 値:** マスタの `name` をそのまま格納

### 6-1. protected ステータス（システムロジックから参照される正式値）

| 表示ラベル = DB 値 | display_order | is_completed | is_protected |
|---|---|---|---|
| 商談中 | 1 | 0 | 1 |
| 交渉中 | 2 | 0 | 1 |
| 成約 | 3 | 1 | 1 |
| 失注 | 4 | 1 | 1 |
| 保留 | 5 | 0 | 1 |

### 6-2. 非 protected ステータス（利用者カスタム扱い、削除・無効化可能）

| 表示ラベル = DB 値 | display_order |
|---|---|
| 提案中 | 6 |
| ヒアリング中 | 7 |
| アプローチ中 | 8 |
| 見込み | 9 |

dummy/サンプルデータおよび新規案件の既定値としては protected な 5 値のみを使用する。

## 7. 見込案件 案件種別（t_sales_case.case_type）

**DB 値はコード（英字）。画面側で変換。** `SalesCaseRepository::ALLOWED_CASE_TYPES` 参照。

| DB 値 | 画面表示ラベル |
|---|---|
| `new` | 新規 |
| `renewal` | 更新 |
| `cross_sell` | クロスセル |
| `up_sell` | アップセル |
| `other` | その他 |

## 8. 見込案件 見込ランク（t_sales_case.prospect_rank）

**DB 値はコード（英字）。** `SalesCaseRepository::ALLOWED_PROSPECT_RANKS = ['A', 'B', 'C']`。

---

## 9. 成績区分（t_sales_performance.performance_type）

**DB 値はコード（英字）。画面側で変換。**

| DB 値 | 画面表示ラベル |
|---|---|
| `new` | 新規 |
| `renewal` | 更改 |
| `addition` | 追加 |
| `change` | 変更 |
| `cancel_deduction` | 解約・等級訂正 |

## 10. 業務区分（t_sales_performance.source_type）

**DB 値はコード（英字）。**

| DB 値 | 画面表示ラベル |
|---|---|
| `non_life` | 損保 |
| `life` | 生保 |

## 11. 販売チャネル（t_sales_performance.sales_channel）

**DB 値はコード（英字）。**

| DB 値 | 画面表示ラベル |
|---|---|
| `direct` | 直接 |
| `motor_dealer` | ディーラー |
| `agency_referral` | 代理店紹介 |
| `customer_referral` | 顧客紹介 |
| `group` | 団体 |
| `other` | その他 |

---

## 12. 契約状態（t_contract.status）

**DB 値はコード（英字）。画面側で変換。**

| DB 値 | 画面表示ラベル |
|---|---|
| `active` | 有効 |
| `renewal_pending` | 更改待ち |
| `expired` | 失効 |
| `cancelled` | 解約 |
| `inactive` | 停止 |

---

## 13. 活動種別・用件区分（t_activity）

いずれも tenant 個別マスタで管理するため、値は変動する。dummy データおよび標準シードでは以下を使用する（DB 値 = 画面表示ラベル）。

### 13-1. 活動種別（m_activity_type）

訪問 / 電話 / メール / オンライン / 会議 / 研修 / その他

### 13-2. 用件区分（m_activity_purpose_type）

満期対応 / 新規開拓 / クロスセル提案 / 事故対応 / 見積対応 / 保全対応 / 苦情対応 / その他

---

## 14. 更改方法・手続方法（t_renewal_case）

tenant 個別マスタで管理。標準シードでは以下（DB 値 = 画面表示ラベル）。

### 14-1. 更改方法（m_renewal_method）

対面 / 郵送 / 電話募集

### 14-2. 手続方法（m_procedure_method）

対面 / 対面ナビ / 電話ナビ / 電話募集 / 署名・捺印 / ケータイOR / マイページ

---

## 15. 変更履歴 変更元（t_audit_event.change_source）

**DB 値はコード（英字）。画面側で変換。**

| DB 値 | 画面表示ラベル |
|---|---|
| `SCREEN` | 画面操作 |
| `SJNET_IMPORT` | SJNET取込 |
| `BATCH` | バッチ処理 |
| `API` | API |
