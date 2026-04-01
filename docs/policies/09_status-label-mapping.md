# ステータスラベル定義

## 1. 位置づけ

本文書は、DB内部値と画面表示ラベル、および現行 Excel 運用時のステータス語彙との対応を定義する。

実装者はDB値を画面に直接出力せず、本表に従って業務用語に変換する。

---

## 2. 満期案件ステータス（t_renewal_case.case_status）

### 2-1. DB値 → 画面表示ラベル

| DB値 | 画面表示ラベル | 説明 |
|---|---|---|
| `open` | 未対応 | 案件作成直後。まだ接触していない状態 |
| `contacted` | 連絡済み | 顧客への初回接触が完了した状態 |
| `quoted` | 見積提示済み | 見積書を送付・提示済みの状態 |
| `waiting` | 返送待ち | 書類の返送や顧客からの回答待ちの状態 |
| `renewed` | 更改完了 | 更改手続きが完了した状態 |
| `lost` | 失注 | 他社へ流出、または解約となった状態 |
| `closed` | 完了 | 対応が完了し、クローズした状態 |

### 2-2. Excel運用ステータスとの対応表

現行Excelの「対応状況」プルダウン値とDB値の対応。
SJNETインポート時および移行時の変換キーとして使用する。

| Excel表示値 | DB値（case_status） | 備考 |
|---|---|---|
| SJ依頼中 | `contacted` | SJNETへの更改依頼を行った状態 |
| 書類作成済 | `quoted` | 見積・申込書を作成した状態に相当 |
| 返送待ち | `waiting` | 書類返送を待っている状態 |
| 見積書送付済 | `quoted` | 見積書を送付した状態 |
| 入金待ち | `waiting` | 保険料の入金待ち状態 |
| 計上済・担当者→TL書類提出待ち | `waiting` | 社内書類提出を待っている状態 |
| 【売上高】担当者確認 | `waiting` | 売上高の確認中状態 |
| 確認依頼 | `contacted` | 社内または顧客への確認を依頼している状態 |
| 完了 | `renewed` または `closed` | 更改完了は `renewed`、その他完了は `closed` |

### 2-3. 運用上の注意

Excelの「完了」は業務文脈により `renewed` と `closed` のどちらに相当するかが異なる。
インポート時は「更改手続きが伴う完了」を `renewed`、「それ以外の完了」を `closed` として扱う。
判断が困難な場合は `closed` をデフォルトとし、担当者が詳細画面で修正する。

---

## 3. 事故案件ステータス（t_accident_case.status）

| DB値 | 画面表示ラベル |
|---|---|
| `accepted` | 受付 |
| `linked` | 保険会社連絡済み |
| `in_progress` | 対応中 |
| `waiting_docs` | 書類待ち |
| `resolved` | 解決済み |
| `closed` | 完了 |

## 4. 事故案件優先度（t_accident_case.priority）

| DB値 | 画面表示ラベル |
|---|---|
| `low` | 低 |
| `normal` | 通常 |
| `high` | 高 |
| `urgent` | 至急 |

---

## 5. 顧客ステータス（m_customer.status）

| DB値 | 画面表示ラベル |
|---|---|
| `prospect` | 見込み |
| `active` | 有効 |
| `inactive` | 非活性 |
| `closed` | 解約済み |

---

## 6. 実績区分（t_sales_performance.performance_type）

| DB値 | 画面表示ラベル |
|---|---|
| `new` | 新規 |
| `renewal` | 更改 |
| `addition` | 追加 |
| `change` | 変更 |
| `cancel_deduction` | 解約・等級訂正 |

## 7. 業務区分（t_sales_performance.source_type）

| DB値 | 画面表示ラベル |
|---|---|
| `non_life` | 損保 |
| `life` | 生保 |

## 8. 販売チャネル（t_sales_performance.sales_channel）

| DB値 | 画面表示ラベル |
|---|---|
| `direct` | 直接 |
| `motor_dealer` | ディーラー |
| `agency_referral` | 代理店紹介 |
| `customer_referral` | 顧客紹介 |
| `group` | 団体 |
| `other` | その他 |

---

## 9. 変更履歴 変更元（t_audit_event.change_source）

| DB値 | 画面表示ラベル |
|---|---|
| `SCREEN` | 画面操作 |
| `SJNET_IMPORT` | SJNET取込 |
| `BATCH` | バッチ処理 |
| `API` | API |
