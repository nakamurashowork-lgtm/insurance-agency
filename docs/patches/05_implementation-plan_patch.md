# 05_implementation-plan.md 修正指示書
# DDL変更に伴う画面仕様反映後の追加修正箇所

本文書は 05_implementation-plan.md に対して行うべき修正を列挙する。
セクション番号は元ファイルに対応する。

---

## 【修正1】セクション4-2 SCR-DASHBOARD の依存テーブル追加

### 現行

```
依存:
- `t_renewal_case`
- `t_accident_case`
- `t_sales_performance`
```

### 修正後

```
依存:
- `t_renewal_case`
- `t_accident_case`
- `t_sales_performance`
- `t_sales_target`（目標対比ウィジェット用。問題8対応で新規追加）
```

---

## 【修正2】セクション4-8 SCR-SALES-DETAIL の責務追記

### 現行

```
責務:
- 実績1件の詳細確認
- 編集と削除
```

### 修正後

```
責務:
- 実績1件の詳細確認
- 編集と削除（sales_channel / referral_source を含む）
- 業務区分（source_type）に応じた表示項目の切り替え
- マイナス保険料（cancel_deduction）の赤字表示
```

---

## 【修正3】セクション4-10 SCR-TENANT-SETTINGS の依存テーブル追加

### 現行

```
依存:
- `common.tenant_notify_targets`
- `common.tenant_notify_routes`
- `m_renewal_reminder_phase`
```

### 修正後

```
依存:
- `common.tenant_notify_targets`
- `common.tenant_notify_routes`
- `m_renewal_reminder_phase`
- `m_sjnet_staff_mapping`（問題9対応。SJNETコード設定セクション用）
- `m_activity_purpose_type`（問題12対応。マスタ管理セクション用）
- `t_sales_target`（問題8対応。目標管理セクション用）
```

---

## 【修正4】セクション5 画面/API/DB対応表の更新

### SCR-DASHBOARD 行

```
現行の主DBテーブル: t_renewal_case, t_accident_case, t_sales_performance
修正後:             t_renewal_case, t_accident_case, t_sales_performance, t_sales_target
```

### SCR-SALES-DETAIL 行（セクション4-8）

```
現行の主DBテーブル: t_sales_performance, m_customer, t_contract, t_renewal_case
修正後:             t_sales_performance, m_customer, t_contract, t_renewal_case
（変更なし。ただし責務の説明を修正1・2に合わせて更新すること）
```

### SCR-ACTIVITY-NEW / SCR-ACTIVITY-DETAIL 行

```
現行の主DBテーブル: t_activity, m_customer
修正後:             t_activity, m_customer, m_activity_purpose_type
```

### SCR-TENANT-SETTINGS 行

```
現行の主DBテーブル: tenant_notify_targets, tenant_notify_routes, m_renewal_reminder_phase
修正後:             tenant_notify_targets, tenant_notify_routes, m_renewal_reminder_phase,
                   m_sjnet_staff_mapping, m_activity_purpose_type, t_sales_target
```

---

## 【修正5】Phase 4A 完了条件・検索条件の追記

Phase 4A 受入判定（セクション16）の「確認済み」リストに以下の注記を追加する。

```
注記（2026-04 DDL変更により追加確認が必要な項目）:
- sales_channel / referral_source の登録・編集・検索（Phase 4A 受入時点では未存在のカラム）
- 業務区分（source_type）フィルタ
- マイナス保険料の赤字表示
→ 上記3点は Phase 4A 受入後に追加されたカラムのため、別途確認フェーズを設けること。
```

---

## 【修正6】Phase 4B CSV取込仕様の全面改訂注記

Phase 4B 実装着手（セクション17）の冒頭に以下を追加する。

```
【重要】CSV取込仕様の改訂について

Phase 4B で実装した CSV 取込は、以下の前提に基づくヘッダ付き CSV 形式を対象としていた。

  必須ヘッダ: receipt_no / policy_no / customer_name / maturity_date /
              performance_date / performance_type / insurance_category /
              product_type / premium_amount / settlement_month / remark

本仕様はその後の業務分析（Excel 成績管理簿の実データ解析）により、
実際の入力フォーマットと乖離していることが判明した。

確定した仕様（詳細は sales-performance-list.md セクション18を参照）は以下の通り。

- 対象ファイル: 成績管理簿から出力した 26 列・和暦・損保生保混在 CSV
- 主な差分:
  - 和暦→西暦変換が必要（令和年 + 2018）
  - 損保・生保が同一行に混在し、最大 2 レコードに分割して登録する
  - 顧客名から m_customer を検索して customer_id を解決する
  - 生保の performance_date は申込日（列22）を使用する

Phase 4B の受入判定は「旧フォーマット版として完了」とする。
新フォーマット版（成績管理簿対応）は Phase 4C として別途実装・受入を行う。
```

---

## 【新規追加】Phase 4C: CSV取込 成績管理簿対応（新規フェーズ）

セクション17（Phase 4B）の末尾に、以下のフェーズを追記する。

---

### Phase 4C: 実績管理 CSV取込 成績管理簿対応（追加フェーズ）

#### 目的

成績管理簿（Excel）から出力した CSV ファイルを直接取り込み、損保・生保を自動分割して `t_sales_performance` に登録できるようにする。

#### 前提

- Phase 4B 完了後に着手。
- CSV フォーマット仕様は `docs/screens/sales-performance-list.md` セクション18 を正本とする。
- `m_customer` への顧客名解決が必要。未解決はエラー行として取込結果に表示する。
- 損保・生保の分割登録は独立トランザクションとし、一方の失敗が他方をロールバックしない。

#### 対象画面

- SCR-SALES-LIST（CSV取込ダイアログの差し替え）

#### 対象PHPファイル（変更）

- `src/Domain/Sales/SalesCsvImportService.php`
  - 26列・和暦形式の CSV パーサに全面書き換え
  - 損保・生保の行分割ロジック追加
  - 顧客名解決ロジック追加（missing_customer / ambiguous_customer エラー分類）
  - 和暦→西暦変換ロジック追加
- `src/Presentation/SalesPerformanceListView.php`
  - 取込結果パネルの差し替え（サマリ: 損保登録数・生保登録数・エラー行数）
  - エラー行一覧（エラー種別・行番号・契約者名・対応方法）の追加

#### 完了条件

- 成績管理簿 CSV（26列・和暦）を選択して取込を実行できる
- 損保のみの行は損保1件として登録される
- 損保・生保混在の行は自動的に2件に分割して登録される
- 和暦年を西暦年に正しく変換して登録される
- 顧客名解決に失敗した行はエラー行として取込結果に表示される
- 取込結果サマリに「損保 N件・生保 N件・エラー N行」が表示される
- エラー行一覧にエラー種別・行番号・契約者名・対応方法が表示される
- 他テナントのデータへの影響がないこと

---

## 【新規追加】Phase 設定フェーズ: SJNETコード設定・目標管理・用件区分マスタ

以下を新規Phaseとして追記する（Phase A/B/C-Lite 完了後に着手）。

---

### Phase 設定A: テナント設定拡張（SJNETコード設定 / 目標管理 / 用件区分マスタ）

#### 目的

テナント設定画面に以下の3機能を追加する。

1. SJNETコード設定：代理店コード↔ユーザーマッピングの CRUD
2. 目標管理：年度・月別・担当者別目標値の登録・更新
3. 用件区分マスタ：m_activity_purpose_type の CRUD

#### 必要なDBテーブル

- `m_sjnet_staff_mapping`（新規。SJNETコード設定用）
- `t_sales_target`（新規。目標管理用）
- `m_activity_purpose_type`（新規。用件区分マスタ用）

#### 対象PHPファイル（変更）

- `src/Controller/TenantSettingsController.php`：SJNETコード設定・目標管理・用件区分の CRUD メソッド追加
- `src/Presentation/TenantSettingsView.php`：3セクションの表示・フォーム追加
- `src/Domain/Tenant/SjnetStaffMappingRepository.php`：新規作成
- `src/Domain/Tenant/SalesTargetRepository.php`：新規作成
- `src/Domain/Tenant/ActivityPurposeTypeRepository.php`：新規作成
- `src/bootstrap.php`：ルート追加

#### 完了条件

- SJNETコード設定：代理店コードの追加・編集・無効化ができる
- 目標管理：年度・月別・担当者別目標額を登録・更新できる
- 用件区分マスタ：用件区分の追加・編集・無効化ができる
- いずれも他テナントのデータへの影響がないこと
- SJNET取込後に「マッピング未登録」がある場合、取込結果からテナント設定へ遷移できる
