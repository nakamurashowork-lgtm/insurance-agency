# sales-performance-list.md セクション18 追記パッチ
# 対象: docs/screens/sales-performance-list.md
# 追記場所: セクション18-7「customer_id の解決ルール」の直後

---

## 18-8. staff_user_id の解決ルール

損保・生保それぞれのレコードに対して、SJNET担当者コードから `staff_user_id` を解決する。

解決には `m_sjnet_staff_mapping` テーブルを使用する。

```
1. CSV の担当者コード（拠点コード + 担当者コード）で m_sjnet_staff_mapping を検索
2. 1件ヒット → マッピング先の staff_user_id を使用して登録
3. 0件ヒット → staff_user_id = NULL で登録し、エラー種別 unmapped_staff として
              エラー行バッファに追加（登録は続行する）
4. is_active = 0 のマッピング → 0件ヒットと同様に扱う
```

`staff_user_id` が解決できない場合でも、レコードの登録は続行する。
`unmapped_staff` エラーは登録失敗ではなく警告として扱い、取込結果パネルに一覧表示する。

担当者マッピングの登録・管理はテナント設定のSJNETコード設定セクションで行う。
マッピング未登録が多い場合は、取込結果からテナント設定へ直接遷移できる導線を表示することが望ましい。

---

## 18-9. エラー種別一覧

取込結果パネルで表示するエラー種別と利用者向けメッセージを定義する。

| エラー種別 | 意味 | 利用者向け表示 |
|---|---|---|
| `missing_customer` | 顧客名でm_customerを検索したが0件 | 顧客が見つかりません。顧客を登録してから再取込してください。 |
| `ambiguous_customer` | 顧客名で2件以上ヒット | 同名の顧客が複数存在します。顧客名を確認してください。 |
| `unmapped_staff` | 担当者コードがm_sjnet_staff_mappingに未登録 | 担当者コードが未設定です。テナント設定でSJNETコードを登録してください。 |
| `missing_date` | 生保のperformance_dateが特定できない | 計上日が取得できません。申込日または始期日を確認してください。 |
| `invalid_date` | 日付変換に失敗（不正な年月日） | 日付の形式が正しくありません。 |

`missing_customer` および `ambiguous_customer` はレコード登録不可のため、対象行はスキップする。
`unmapped_staff` および `missing_date`（生保）は登録を試行するが、取込結果に警告として記録する。
