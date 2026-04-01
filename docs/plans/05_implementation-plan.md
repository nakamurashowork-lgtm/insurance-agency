# 実装計画（XServer + MySQL + PHP）

## 1. 位置づけ

本計画は、以下を正本として作成する。

- 仕様の正: `docs/`
- DDLの正: `config/ddl/common/`, `config/ddl/tenant/`

前提:

- 配置先は XServer
- DB は MySQL
- 実装言語は PHP（素のPHP前提）
- `public/` は Web入口
- `src/` は実装本体
- 推測で新画面、新機能、新テーブル、新カラムを追加しない
- DDL未定義テーブルを参照しない
- docs と DDL に不整合があれば、実装せず差分として列挙する

維持する絶対条件:

- 満期一覧 = 契約一覧
- 満期詳細 = 契約詳細
- 契約一覧の独立画面は作らない
- 契約詳細の独立画面は作らない
- ホームは入口画面に徹し、処理画面化しない
- 顧客一覧、顧客詳細は独立画面として維持する
- ログイン成功時はセッションを作成し、ホームへ遷移する
- セッションにはユーザーID、表示名、テナントID、権限情報を保持する

---

## 2. 参照した仕様とDDL

### 2-1. 参照した docs

- `docs/00_overview.md`
- `docs/foundations/03_screen-map.md`
- `docs/foundations/04_folder-structure.md`
- `docs/foundations/01_canonical-schema.md`
- `docs/foundations/02_navigation-policy.md`
- `docs/screens/login.md`
- `docs/screens/dashboard.md`
- `docs/screens/customer-list.md`
- `docs/screens/customer-detail.md`
- `docs/screens/renewal-case-list.md`
- `docs/screens/renewal-case-detail.md`
- `docs/screens/accident-case-list.md`
- `docs/screens/accident-case-detail.md`
- `docs/screens/sales-performance-list.md`
- `docs/screens/sales-performance-detail.md`
- `docs/screens/tenant-settings.md`
- `docs/screens/activity-list.md`
- `docs/screens/activity-detail.md`
- `docs/screens/activity-daily.md`

### 2-2. 参照した DDL

- `config/ddl/common/tenants.sql`
- `config/ddl/common/users.sql`
- `config/ddl/common/user_tenants.sql`
- `config/ddl/common/tenant_notify_targets.sql`
- `config/ddl/common/tenant_notify_routes.sql`
- `config/ddl/tenant/m_customer.sql`
- `config/ddl/tenant/m_customer_contact.sql`
- `config/ddl/tenant/m_renewal_reminder_phase.sql`
- `config/ddl/tenant/t_contract.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/t_accident_case.sql`
- `config/ddl/tenant/t_accident_reminder_rule.sql`
- `config/ddl/tenant/t_accident_reminder_rule_weekday.sql`
- `config/ddl/tenant/t_activity.sql`
- `config/ddl/tenant/t_daily_report.sql`
- `config/ddl/tenant/t_sales_case.sql`
- `config/ddl/tenant/t_case_comment.sql`
- `config/ddl/tenant/t_sales_performance.sql`
- `config/ddl/tenant/t_audit_event.sql`
- `config/ddl/tenant/t_audit_event_detail.sql`
- `config/ddl/tenant/t_notification_run.sql`
- `config/ddl/tenant/t_notification_delivery.sql`
- `config/ddl/tenant/t_sjnet_import_batch.sql`
- `config/ddl/tenant/t_sjnet_import_row.sql`

### 2-3. 非採用・廃止候補として扱う文書

- `contract-detail.md` は本計画の参照対象に含めない。
- 理由: 契約詳細の独立画面は正式画面として採用しないため。
- 扱い: 非採用文書（廃止候補）として管理し、正式導線・実装計画に取り込まない。

---

## 3. 変更方針

- 画面責務は `docs/foundations/03_screen-map.md` に固定する。
- 契約業務は満期画面系に統合し、契約独立画面を作らない。
- ホームは入口に徹し、登録・更新・処理を持たせない。
- 事故案件一覧/詳細は通常業務の主要入口として実装する。
- 事故案件一覧/詳細は一般利用者の標準業務導線に含める。
- 事故案件一覧/詳細は main nav とホームの日常業務に含める。
- テナント設定は管理・設定として分離し、権限者のみ表示する。
- `public/` には入口とエンドポイントだけを置き、業務実装は `src/` に集約する。

---

## 4. 画面ごとの必要PHPファイル、責務、依存関係

注記:

- ここでの「ファイル」は実装単位であり、具体パスは詳細設計で確定する。
- APIのURL/HTTPメソッド/入出力項目は docs で未確定のため「要確認」。

### 4-1. SCR-LOGIN

必要PHPファイル:

- ログイン画面表示
- 認証開始
- 認証コールバック
- ログアウト

責務:

- 未認証ユーザー入口
- 認証成功時のセッション作成
- 主所属テナントでログイン確立
- ホーム遷移

依存:

- `common.users`
- `common.user_tenants`
- `common.tenants`

### 4-2. SCR-DASHBOARD（表示名: ホーム）

必要PHPファイル:

- ホーム画面表示
- サマリ取得

責務:

- 入口表示のみ
- 日常業務への導線提示
- 管理・設定導線の表示制御

依存:

- `t_renewal_case`
- `t_accident_case`
- `t_sales_performance`
- `t_sales_target`（目標対比ウィジェット用）

### 4-3. SCR-RENEWAL-LIST（契約一覧を兼ねる）

必要PHPファイル:

- 一覧画面表示
- 検索取得

責務:

- 契約探索、絞込、優先度把握
- 一覧から満期詳細へ遷移

依存:

- `t_contract`
- `t_renewal_case`
- `m_customer`

### 4-4. SCR-RENEWAL-DETAIL（契約詳細を兼ねる）

必要PHPファイル:

- 詳細画面表示
- 詳細取得
- 満期対応更新
- コメント取得/登録
- 変更履歴取得

責務:

- 契約内容確認
- 満期対応更新
- 顧客情報サマリ表示
- コメント運用、変更履歴確認

依存:

- `t_contract`
- `t_renewal_case`
- `m_customer`
- `m_customer_contact`
- `t_case_comment`
- `t_audit_event`
- `t_audit_event_detail`

### 4-5. SCR-CUSTOMER-LIST

必要PHPファイル:

- 一覧画面表示
- 検索取得
- 新規登録処理（POST customer/create）

責務:

- 顧客軸の探索
- 一覧から顧客詳細へ遷移
- 新規顧客の登録（補助操作）
- 登録成功後に顧客詳細へ遷移

依存:

- `m_customer`
- `m_customer_contact`
- `common.users`（担当者選択のため）

### 4-6. SCR-CUSTOMER-DETAIL

必要PHPファイル:

- 詳細画面表示
- 詳細取得
- 顧客更新（要確認）
- 保有契約一覧取得
- 活動履歴取得

責務:

- 顧客全体像表示（既存顧客の確認・更新が中心）
- 顧客一覧の新規登録後の到達先としても利用される
- 顧客活動履歴の時系列確認
- 保有契約から満期詳細へ遷移
- 契約処理画面化しない

依存:

- `m_customer`
- `m_customer_contact`
- `t_contract`
- `t_activity`

### 4-7. SCR-SALES-LIST

必要PHPファイル:

- 一覧画面表示
- 検索取得
- 登録
- CSV取込
- 取込結果表示

責務:

- 実績の検索と対象特定
- 一覧から実績詳細への遷移
- 登録とCSV取込の補助操作

依存:

- `t_sales_performance`
- `m_customer`
- `t_contract`
- `t_renewal_case`

### 4-8. SCR-SALES-DETAIL

必要PHPファイル:

- 詳細画面表示
- 詳細取得
- 編集
- 削除

責務:

- 実績1件の詳細確認
- 編集と削除（sales_channel / referral_source を含む）
- 業務区分（source_type）に応じた表示項目の切り替え
- マイナス保険料（cancel_deduction）の赤字表示

依存:

- `t_sales_performance`
- `m_customer`
- `t_contract`
- `t_renewal_case`

### 4-9. SCR-ACCIDENT-LIST / SCR-ACCIDENT-DETAIL

必要PHPファイル:

- 一覧画面表示
- 詳細画面表示
- 更新
- コメント取得/登録
- 監査ログ取得

責務:

- 日常業務の事故対応導線として運用
- 事故案件の一覧確認と詳細更新

依存:

- `t_accident_case`
- `t_accident_reminder_rule`
- `t_accident_reminder_rule_weekday`
- `t_case_comment`
- `t_audit_event`
- `t_audit_event_detail`

### 4-10. SCR-TENANT-SETTINGS（管理・設定）

必要PHPファイル:

- 設定画面表示
- 通知設定取得/更新
- マスタ設定取得/更新

責務:

- テナント運用設定管理
- 管理者のみ更新可

依存:

- `common.tenant_notify_targets`
- `common.tenant_notify_routes`
- `m_renewal_reminder_phase`
- `m_sjnet_staff_mapping`（SJNETコード設定セクション用）
- `m_activity_purpose_type`（マスタ管理セクション用）
- `t_sales_target`（目標管理セクション用）

---

## 5. 画面 / API / DB / バッチ / 権限 対応表

| 画面 | 画面系PHP | API系PHP | 主DBテーブル | バッチ関連 | 権限 |
|---|---|---|---|---|---|
| SCR-LOGIN | ログイン表示 | 認証開始/戻り/ログアウト | users, user_tenants, tenants | なし | 未認証可、利用可否判定必須 |
| SCR-DASHBOARD | ホーム表示 | サマリ取得 | t_renewal_case, t_accident_case, t_sales_performance, t_sales_target | なし | ログイン必須、管理・設定導線のみ権限限定 |
| SCR-RENEWAL-LIST | 一覧表示 | 検索 | t_contract, t_renewal_case, m_customer | なし | ログイン必須、表示範囲制御 |
| SCR-RENEWAL-DETAIL | 詳細表示 | 詳細/更新/履歴 | t_contract, t_renewal_case, t_case_comment, t_audit_event, t_audit_event_detail | なし | ログイン必須、更新範囲制御 |
| SCR-CUSTOMER-LIST | 一覧表示 | 検索 | m_customer, m_customer_contact | なし | ログイン必須、表示範囲制御 |
| SCR-CUSTOMER-DETAIL | 詳細表示 | 取得/更新/保有契約/活動履歴 | m_customer, m_customer_contact, t_contract, t_activity | なし | ログイン必須、編集可否制御 |
| SCR-SALES-LIST | 一覧表示 | 検索/登録/CSV | t_sales_performance, m_customer, t_contract, t_renewal_case | CSV取込（画面起点） | ログイン必須、更新権限制御 |
| SCR-SALES-DETAIL | 詳細表示 | 取得/編集/削除 | t_sales_performance, m_customer, t_contract, t_renewal_case | なし | ログイン必須、更新権限制御 |
| SCR-ACCIDENT-LIST | 一覧表示 | 検索/更新 | t_accident_case | 事故通知条件参照 | ログイン必須、表示範囲制御 |
| SCR-ACCIDENT-DETAIL | 詳細表示 | 更新/コメント/監査 | t_accident_case, t_case_comment, t_audit_event, t_audit_event_detail | 事故通知条件参照 | ログイン必須、更新範囲制御 |
| SCR-ACTIVITY-LIST | 一覧表示 | 検索（日付・担当者・種別） | t_activity, m_customer, common.users | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-NEW | 登録フォーム | 新規登録 | t_activity, m_customer, m_activity_purpose_type | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-DETAIL | 詳細表示 | 取得/更新/削除 | t_activity, m_customer, m_activity_purpose_type | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-DAILY | 日報ビュー | 当日活動取得/コメントupsert | t_activity, t_daily_report, common.users | なし | ログイン必須、全ユーザー |
| SCR-TENANT-SETTINGS | 設定表示（管理者補助） | 通知設定/マスタ設定更新 | tenant_notify_targets, tenant_notify_routes, m_renewal_reminder_phase, m_sjnet_staff_mapping, m_activity_purpose_type, t_sales_target | 通知実行前提設定 | 管理権限者のみ標準表示・更新 |

---

## 6. 実装フェーズ（XServer + MySQL + PHP）

現在の到達点（2026-03-29）:

- Phase 1-6 は受入完了。
- 以降は運用定着フェーズとして扱う。
- Phase 6 の最終受入範囲と判定は `27` から `30` を正とする。

## Gate 0（Phase 1 着手前の必須解消事項）

実施状態: 実施済み（2026-03-28）

目的: ログイン仕様差分を解消し、Phase 1 の実装正本を固定する。

必須解消事項（解消済み）:

- `docs/foundations/03_screen-map.md` のログイン記述を Google認証ベースへ修正済み。
- 初期実装の認証方式を Google認証として文書明記済み。

初期実装方針（確定）:

- `docs/screens/login.md` を正として、Google認証を初期実装の認証方式とする。

ゲート条件: 差分解消の文書反映が完了していること（本項目は充足）。

### Phase 1: 認証・セッション・テナント基盤

目的: ログイン成功時のセッション確立とホーム遷移を成立させる。

対象画面: SCR-LOGIN、SCR-DASHBOARD（入口表示のみ）

必要なDBテーブル: `users`, `user_tenants`, `tenants`

認証/権限の考慮:
- 複数テナント所属時は主所属テナントで自動ログイン
- 一般利用者には管理・設定導線のみ非表示とする

完了条件:
- 未認証アクセスはログインへ遷移
- 認証成功でセッション作成しホーム遷移
- ログアウトでセッション破棄

Phase 1 現行実装ファイル（2026-03-28 整理後）:

- `public/index.php`
- `public/.htaccess`
- `src/bootstrap.php`
- `src/EnvLoader.php`
- `src/AppConfig.php`
- `src/ConfigurationException.php`
- `src/SessionManager.php`
- `src/Security/AuthGuard.php`
- `src/Http/Router.php`
- `src/Http/Responses.php`
- `src/Infra/CommonConnectionFactory.php`
- `src/Infra/TenantConnectionFactory.php`
- `src/Domain/Auth/AuthException.php`
- `src/Domain/Auth/GoogleOAuthClient.php`
- `src/Domain/Auth/UserRepository.php`
- `src/Auth/TenantResolver.php`
- `src/Auth/AuthService.php`
- `src/Controller/AuthController.php`
- `src/Controller/DashboardController.php`
- `src/Presentation/Controller/LoginController.php`
- `src/Presentation/DashboardView.php`
- `src/Presentation/View/Layout.php`
- `src/Presentation/View/LoginView.php`
- `src/.htaccess`
- `config/.htaccess`
- `.env.example`

### Phase 2: 主導線（満期一覧/満期詳細）

目的: 日常業務の主導線を成立させる。

対象画面: SCR-RENEWAL-LIST、SCR-RENEWAL-DETAIL

必要なDBテーブル: `t_contract`, `t_renewal_case`, `m_customer`, `m_customer_contact`, `t_case_comment`, `t_audit_event`, `t_audit_event_detail`

完了条件:
- 一覧から詳細への遷移が成立
- 満期詳細で更新が成立
- 契約独立画面を作らずに要件を満たす

### Phase 3: 顧客導線（顧客一覧/顧客詳細）

目的: 顧客軸の独立導線を成立させる。

対象画面: SCR-CUSTOMER-LIST、SCR-CUSTOMER-DETAIL

必要なDBテーブル: `m_customer`, `m_customer_contact`, `t_contract`, `t_activity`

完了条件:
- 顧客一覧から顧客詳細へ遷移
- 顧客詳細から満期詳細への補助遷移成立
- 顧客詳細を契約処理画面化しない

### Phase 4A: 実績管理（一覧/検索/登録/編集/削除）

目的: 実績管理の基本業務を成立させる。

対象画面: SCR-SALES-LIST、SCR-SALES-DETAIL

必要なDBテーブル: `t_sales_performance`, `m_customer`, `t_contract`, `t_renewal_case`

完了条件:
- 一覧、検索、登録、編集、削除が成立
- 実績一覧から実績詳細へ遷移できる
- 編集、削除は実績詳細画面起点で成立

### Phase 4B: 実績管理（CSV取込/エラー集約/取込結果表示）

目的: 実績CSV取込の運用を成立させる。

対象画面: SCR-SALES-LIST（CSV機能）

必要なDBテーブル: `t_sales_performance`, `m_customer`, `t_contract`, `t_renewal_case`

完了条件: CSV取込、エラー集約、取込結果表示が成立

### Phase 5: 事故業務導線と管理・設定（実装完了・標準業務）

位置づけ: 本フェーズは実装完了済みであり、事故業務は満期・顧客・実績と並ぶ標準業務導線として正式に昇格している。

対象画面: SCR-ACCIDENT-LIST、SCR-ACCIDENT-DETAIL、SCR-TENANT-SETTINGS（管理・設定）

実装状況（2026-03-30 確定）:
- 事故案件一覧/詳細: 実装完了、標準業務導線に昇格
- main nav に「事故管理」として掲載される
- ホームの日常業務カードに含まれている
- 一般利用者アクセス可、権限による表示制御無し
- テナント設定: 管理権限者に限定

完了条件:
- ログイン済み利用者で事故案件の一覧/詳細/更新が利用可能
- 管理権限者のみテナント設定を利用可能

注記: `20` と `21` の受入確認ログは 2026-03-29 時点の実測記録であり、当時の admin 限定実装を前提としている。2026-03-30 以降の正式方針は本節を正とし、事故案件を通常業務導線へ昇格させる。

### Phase 6: バッチ・通知・運用強化

目的: 通知実行、配信結果、取込運用を安定化する。

必要なDBテーブル: `t_notification_run`, `t_notification_delivery`, `t_sjnet_import_batch`, `t_sjnet_import_row`

完了条件: 定期実行、履歴確認、失敗時再実行手順が確立

---

## 7. 仕様不足・競合・未確定点

1. ログイン仕様差分 → Gate 0 で解消済み。
2. API仕様の未確定 → URL、HTTPメソッド、入出力、エラー仕様が画面仕様で未確定。要確認。
3. 通知先テーブル参照の整合 → `tenant_notify_routes`/`tenant_notify_targets` の参照先定義に不足がある可能性。DDL整合を要確認。
4. 顧客詳細の編集範囲 → 一般利用者の編集可能範囲が未確定。要確認。
5. 監査ログ記録方針 → どの更新を必須記録とするか未確定。要確認。

---

## 8. 最初に着手する1フェーズ（提案）

提案: まず Gate 0（ログイン仕様差分の必須解消）に着手する。

理由: 認証方式が未確定のまま Phase 1 を進めると、認証・セッション基盤の再実装リスクが高い。Gate 0 完了後に Phase 1 を開始することで、以降フェーズの実装前提を固定できる。

Gate 0 完了後の次フェーズ: Phase 1（認証・セッション・テナント基盤）

---

## 9. Phase 2 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/foundations/03_screen-map.md`
- `docs/foundations/02_navigation-policy.md`
- `docs/screens/renewal-case-list.md`
- `docs/screens/renewal-case-detail.md`
- `config/ddl/tenant/t_contract.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/m_customer.sql`
- `config/ddl/tenant/m_customer_contact.sql`
- `config/ddl/tenant/t_activity.sql`
- `config/ddl/tenant/t_case_comment.sql`
- `config/ddl/tenant/t_audit_event.sql`
- `config/ddl/tenant/t_audit_event_detail.sql`

実施内容（実測）:

- 認証済みセッションを作成し、`renewal/list`, `renewal/detail`, `renewal/update` へ HTTP アクセスした。
- common.tenants に登録された 2 テナント DB（`xs000001_te001`, `xs000001_te002`）の実テーブルを調査した。
- Phase 2 必須テーブルの実在確認を `information_schema.tables` で実施した。
- テストデータ投入を試行したが、DDL 想定テーブル不足により途中停止した。

実DBと canonical DDL の差分（実測）:

- `t_contract`: どの DB にも存在しない（`xs000001_te001` には `m_contract` が存在）
- `t_activity`: どの DB にも存在しない
- `m_customer_contact`: どの DB にも存在しない
- `xs000001_te002`: 空スキーマ（対象テーブルなし）
- `xs000001_te001.t_renewal_case`: canonical DDL と列名不一致

投入結果（実測）:

- テスト投入プレフィックス: `PH2AT_`
- `xs000001_te001.m_customer` に 2 件挿入（`PH2AT_`）
- それ以外の Phase 2 対象テーブルは、テーブル不存在または列不一致により投入不可

画面挙動（認証済み）:

- `renewal/list`: HTTP 200 で表示されるが「満期一覧の取得に失敗しました。接続設定を確認してください。」を表示
- `renewal/detail&id=1`: `renewal/list` へ 302 リダイレクト
- `renewal/update`（CSRFなし POST）: `renewal/detail&id=1` へ 302 リダイレクト

受入判定（事実ベース）:

- 確認済み: 認証済みアクセス時のルーティング応答（HTTP 200/302）、一覧画面のエラー表示と空状態表示
- 不具合: Phase 2 実装が参照するテーブル/カラムと、実DBスキーマが不一致
- 未実装（実DB起点）: canonical DDL 前提の `t_contract`, `t_activity`, `m_customer_contact` が未配備

---

## 10. Phase 2 受入確認（再実施: 実DB整合後） 2026-03-29

【投入したテストデータ】

- 投入件数（再実施本体）
  - tenantA(TE001): `m_customer` 3、`m_customer_contact` 3、`t_contract` 3、`t_renewal_case` 3、`t_activity` 1、`t_case_comment` 1、`t_audit_event` 1、`t_audit_event_detail` 1
  - tenantB(TE002): 同数 + 追加投入（テナント分離の直打ち検証用）
- 確認に使った代表レコードプレフィックス: `PH2AT2_20260329_103100`

【確認できたこと】

- `renewal/list`: 認証済みで HTTP 200 かつDB取得エラー表示なし
- `renewal/detail`: 認証済みで HTTP 200（対象案件）
- 詳細画面に契約情報・満期情報・顧客サマリ表示を確認
- `policy_no` 指定で対象案件のみ抽出
- 満期詳細 `remark` 更新値反映、`case_status=quoted` 選択反映を確認
- 活動履歴あり/なし、コメントあり/なし、監査ログあり/なしの各表示を確認
- tenantBデータがtenantAの一覧に非表示
- 他テナント案件ID直打ち・update直接POST遮断を確認

【不具合】: 今回の再実施で再現した不具合はなし

【未実装】: `t_audit_event_detail` は投入済みだが、画面表示は `t_audit_event` の一覧のみで詳細表示機能は未実装

補足（2026-03-30 追記）: 満期詳細のコメント登録（追記方式）は `renewal/comment` と詳細画面の「新規コメント」入力で実装済み

---

## 11. Phase 2 受入判定（短縮版・クローズ用）

受入範囲: Phase 2 最小実装範囲（`renewal/list`, `renewal/detail`, `renewal/update`）のみに限定する。

判定:

- 確認済み: 満期一覧表示、検索、一覧→詳細遷移、詳細表示、更新反映、履歴あり/なし表示、対象なし時挙動、テナント分離、detail直打ち遮断、update直接POST未更新
- 未実装: `t_audit_event_detail` の画面表示
- 未確認: なし

Phase 2 判定結論: Phase 2（最小実装範囲）は受入完了としてクローズする。

---

## 12. Phase 3 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/foundations/03_screen-map.md`
- `docs/screens/customer-list.md`
- `docs/screens/customer-detail.md`
- `docs/screens/renewal-case-detail.md`
- `config/ddl/tenant/m_customer.sql`
- `config/ddl/tenant/m_customer_contact.sql`
- `config/ddl/tenant/t_contract.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/t_activity.sql`

【投入したテストデータ】

- 実施スクリプト: `tmp/phase3_acceptance_check.php`
- プレフィックス: `PH3AT_20260329_112506`
- tenantA(TE001): `m_customer` 3、`m_customer_contact` 2、`t_contract` 3、`t_renewal_case` 4、`t_activity` 1
- tenantB(TE002): `m_customer` 2以上（他テナント非表示/直打ち遮断用）

【確認できたこと】

- `customer_name` / `phone` / `email` / `status=inactive` 検索で対象のみ抽出
- 保有契約件数の正しさ確認
- 顧客詳細: 基本情報、連絡先（複数件/0件）、保有契約（複数件/0件）、活動履歴（あり/なし）
- 顧客詳細 → 満期詳細リンク、満期詳細 → 顧客詳細リンクの存在確認
- tenantB 顧客は tenantA 一覧に非表示、直打ち遮断

【最新の満期案件ID選定基準と実測】

- 実装基準: `ORDER BY maturity_date DESC, id DESC` の `LIMIT 1`
- 実測: `id=5（maturity=+120d）` が採用され、`id=4（maturity=+30d）` は採用されないことを確認

【未実装】: 顧客更新（`docs/screens/customer-detail.md` の更新系）

---

## 13. Phase 3 受入判定（短縮版・クローズ用）

受入範囲: Phase 3 最小実装範囲（`customer/list`, `customer/detail`, 顧客↔満期の相互導線）

判定:

- 確認済み: 顧客一覧表示、検索（`customer_name` / `phone` / `email` / `status`）、一覧の保有契約件数表示、一覧→詳細遷移、顧客詳細基本情報表示、連絡先（複数件/0件）表示、保有契約（複数件/0件）表示、活動履歴（あり/なし）表示、顧客詳細→満期詳細遷移、満期詳細→顧客詳細遷移、他テナント顧客の非表示、他テナント顧客ID直打ち遮断、最新満期案件ID選定（`maturity_date DESC, id DESC`）
- 未実装: 顧客更新
- 未確認: なし

今後の仕様論点: 最新満期案件の選定基準を `maturity_date DESC, id DESC` 以外へ変更する必要があるか

Phase 3 判定結論: Phase 3（最小実装範囲）は受入完了としてクローズする。

---

## 14. Phase 4A 実装着手（一覧/検索/登録/編集/削除） 2026-03-29

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `docs/foundations/03_screen-map.md`
- `docs/foundations/02_navigation-policy.md`
- `config/ddl/tenant/t_sales_performance.sql`

変更方針:

- Phase 4A の対象を「実績管理一覧 + 検索 + 登録 + 編集 + 削除」に限定する
- `docs/screens/sales-performance-list.md` 記載の CSV 取込は Phase 4B で実装し、本着手では追加しない
- 既存導線方針に合わせ、ダッシュボードから `sales/list` へ遷移可能にする

実装内容:

- 追加
  - `src/Domain/Sales/SalesPerformanceRepository.php`
  - `src/Controller/SalesPerformanceController.php`（`list`, `create`, `update`, `delete`）
  - `src/Presentation/SalesPerformanceListView.php`
- 更新
  - `src/bootstrap.php`（ルート: `GET sales/list`, `POST sales/create`, `POST sales/update`, `POST sales/delete`）
  - `src/Controller/DashboardController.php`
  - `src/Presentation/DashboardView.php`（「実績管理一覧」カードを実リンク化）

実動確認（スモーク）: 実施スクリプト `tmp/phase4a_smoke_check.php` で一覧/登録/検索/編集/削除を確認済み

未実装: CSV取込（Phase 4B）

---

## 15. Phase 4A 受入確認（実動）結果 2026-03-29

実施スクリプト: `tmp/phase4a_acceptance_check.php`

プレフィックス: `PH4AT_20260329_114430`

【確認できたこと】

- 一覧表示（HTTP 200）
- 検索: `performance_date From/To`、`customer_name`、`policy_no`、`product_type`、`settlement_month`
- 登録（+1件）、編集（remark/performance_type/product_type反映）、論理削除（`is_deleted=1`、一覧非表示）
- 0件検索時「該当データはありません。」表示
- tenantB実績は tenantA一覧に非表示
- 他テナントID直叩き update / delete の未反映
- 不正値保存防止、CSRFなし処理拒否

【不具合】: なし

【未実装】: CSV取込（Phase 4B）

---

## 16. Phase 4A 受入判定（短縮版・クローズ用）

受入範囲: Phase 4A 最小実装範囲（`sales/list`, `sales/create`, `sales/update`, `sales/delete`）

判定:

- 確認済み: 実績一覧表示、検索、登録反映、編集反映、論理削除反映と一覧非表示、0件検索時表示、他テナント実績の非表示、他テナントID直叩き update / delete の未反映、不正値保存防止、CSRFなし create / update / delete の拒否
- 未実装: CSV取込（Phase 4B）
- 未確認: なし

注記（2026-04 DDL変更により追加確認が必要な項目）:

- sales_channel / referral_source の登録・編集・検索（Phase 4A 受入時点では未存在のカラム）
- 業務区分（source_type）フィルタ
- マイナス保険料の赤字表示
→ 上記3点は Phase 4A 受入後に追加されたカラムのため、別途確認フェーズを設けること。

Phase 4A 判定結論: Phase 4A（最小実装範囲）は受入完了としてクローズする。

---

## 17. Phase 4B 実装着手（CSV取込/エラー集約/取込結果表示） 2026-03-29

【重要】CSV取込仕様の改訂について

Phase 4B で実装した CSV 取込は、ヘッダ付き CSV 形式（必須ヘッダ: receipt_no / policy_no / customer_name / maturity_date / performance_date / performance_type / insurance_category / product_type / premium_amount / settlement_month / remark）を前提としていた。

本仕様はその後の業務分析（Excel 成績管理簿の実データ解析）により、実際の入力フォーマットと乖離していることが判明した。

確定した仕様（詳細は `docs/screens/sales-performance-list.md` セクション18 を参照）:

- 対象ファイル: 成績管理簿から出力した 26 列・和暦・損保生保混在 CSV
- 主な差分:
  - 和暦→西暦変換が必要（令和年 + 2018）
  - 損保・生保が同一行に混在し、最大 2 レコードに分割して登録する
  - 顧客名から m_customer を検索して customer_id を解決する
  - 生保の performance_date は申込日（列22）を使用する

Phase 4B の受入判定は「旧フォーマット版として完了」とする。新フォーマット版（成績管理簿対応）は Phase 4C として別途実装・受入を行う。

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `config/ddl/tenant/t_sjnet_import_batch.sql`
- `config/ddl/tenant/t_sjnet_import_row.sql`
- `config/ddl/tenant/t_sales_performance.sql`

変更方針:

- CSV取込は `SCR-SALES-LIST` の画面内機能として追加する
- Phase 4A の一覧/検索/CRUD を壊さないことを最優先とする
- 取込履歴は `t_sjnet_import_batch`, 行結果は `t_sjnet_import_row` に記録する
- CSV仕様は docs 未確定のため、Phase 4B では実装最小前提を固定して扱う

実装上の固定前提（Phase 4B 最小仕様）:

- ヘッダ付き CSV を対象とする
- 必須ヘッダ: `receipt_no`, `policy_no`, `customer_name`, `maturity_date`, `performance_date`, `performance_type`, `insurance_category`, `product_type`, `premium_amount`, `settlement_month`, `remark`
- 既存行更新キーは `receipt_no` とする
- 契約紐付けは `policy_no` 一致で行う
- 満期案件紐付けは `policy_no` で見つかった契約に対する `maturity_date` 一致で行う

実装内容:

- 追加
  - `src/Domain/Sales/SalesCsvImportService.php`（CSV読込、文字コード判定、ヘッダ検証、行単位判定、履歴記録）
- 更新
  - `src/Domain/Sales/SalesPerformanceRepository.php`（契約/満期案件/receipt_no検索、履歴登録/取得）
  - `src/Controller/SalesPerformanceController.php`（`import()` 追加）
  - `src/Presentation/SalesPerformanceListView.php`（CSV取込フォーム、サマリ、行別結果テーブル追加）
  - `src/bootstrap.php`（`POST sales/import` 追加）

今後の仕様論点:

- `receipt_no` 以外を更新キーに採用する必要があるか
- 契約紐付け/満期案件紐付けの優先ルール詳細

### Phase 4C: 実績管理 CSV取込 成績管理簿対応（追加フェーズ）

目的: 成績管理簿（Excel）から出力した CSV ファイルを直接取り込み、損保・生保を自動分割して `t_sales_performance` に登録できるようにする。

前提:

- Phase 4B 完了後に着手。
- CSV フォーマット仕様は `docs/screens/sales-performance-list.md` セクション18 を正本とする。
- `m_customer` への顧客名解決が必要。未解決はエラー行として取込結果に表示する。
- 損保・生保の分割登録は独立トランザクションとし、一方の失敗が他方をロールバックしない。

対象画面: SCR-SALES-LIST（CSV取込ダイアログの差し替え）

対象PHPファイル（変更）:

- `src/Domain/Sales/SalesCsvImportService.php`（26列・和暦形式の CSV パーサに全面書き換え、損保・生保の行分割ロジック、顧客名解決ロジック、和暦→西暦変換ロジック追加）
- `src/Presentation/SalesPerformanceListView.php`（取込結果パネルの差し替え）

完了条件:

- 成績管理簿 CSV（26列・和暦）を選択して取込を実行できる
- 損保のみの行は損保1件として登録される
- 損保・生保混在の行は自動的に2件に分割して登録される
- 和暦年を西暦年に正しく変換して登録される
- 顧客名解決に失敗した行はエラー行として取込結果に表示される
- 取込結果サマリに「損保 N件・生保 N件・エラー N行」が表示される
- エラー行一覧にエラー種別・行番号・契約者名・対応方法が表示される
- 他テナントのデータへの影響がないこと

---

## 18. Phase 4B 受入確認（実動）結果 2026-03-29

実施スクリプト: `tmp/phase4b_acceptance_check.php`

プレフィックス: `PH4BT_20260329_122533`

【確認できたこと】

- 正常CSV取込: 一括登録、`receipt_no` 一致の既存行更新、取込後一覧反映、batch集計（`success`, `insert=1`, `update=1`, `error=0`）
- 部分失敗CSV取込: 成功行と失敗行の分離、`t_sjnet_import_row` 識別、結果画面表示（`partial`, `insert=1`, `error=1`）
- テナント分離: tenantA の CSV取込は tenantB 実績に混入しない
- Phase 4A 退行確認: CSV取込後も create / update / delete が成立

【不具合】: なし

今後の仕様論点:

- `receipt_no` 以外を更新キーに採用する必要があるか
- 契約紐付け/満期案件紐付けの優先ルール詳細

補足: CSV仕様の正式定義は `docs/screens/sales-performance-csv-import.md` に反映済み

---

## 19. Phase 4B 受入判定（短縮版・クローズ用）

受入範囲: Phase 4B 最小実装範囲（`sales/import`, エラー集約, 取込結果表示, Phase 4A CRUD退行なし）

判定:

- 確認済み: 正常CSVでの一括登録、既存行更新、失敗行識別、エラー集約結果表示、取込後一覧反映、他テナント非混在、Phase 4A CRUD退行なし
- 未実装: なし
- 未確認: なし

Phase 4B 判定結論: Phase 4B（最小実装範囲）は受入完了としてクローズする。

---

## 20. Phase 5 受入確認（実動）2026-03-29

注記: 本セクション（20）および次セクション（21）は 2026-03-29 時点の実測記録であり、当時の admin 限定実装を前提としている。正式方針は本ファイルセクション6のPhase 5定義を参照すること。

受入確認内容（当時の admin 限定実装）:

- 一般ユーザーに `tenant/settings` が非表示であることを確認
- 事故案件導線が admin ユーザーのみに表示されることを確認（当時の実装）
- 一般ユーザーの `accident/*`, `tenant/settings*` 直打ち/直接POSTが遮断されることを確認
- 事故案件一覧→詳細の基本導線が動作することを確認
- 事故案件詳細でコメント登録と監査ログ表示が文脈付きで動作することを確認
- テナント設定（通知/マスタ）が所属テナント文脈で更新されることを確認
- 他テナント設定への影響がないことを確認

---

## 21. Phase 5 受入判定（短縮版・クローズ用）

受入範囲: Phase 5 最小実装範囲（管理者向け設定導線の表示制御、事故案件一覧/詳細、事故更新、コメント登録、監査表示、テナント設定更新）

判定:

- 確認済み: 一般ユーザーに管理者向け設定導線（`tenant/settings`）が表示されない、事故案件導線は全ログインユーザーに表示され管理者にのみテナント設定導線が表示される（2026-03-30以降の正式方針）、一般ユーザーの直打ち/直接POST遮断、事故案件一覧→詳細の基本導線、事故案件詳細でのコメント登録と監査ログ表示、テナント設定の文脈更新、他テナント設定への影響なし
- 未実装: なし
- 未確認: なし

Phase 5 判定結論: Phase 5（最小実装範囲）は受入完了としてクローズする。

---

注記:

- `22` から `26` は 2026-03-29 時点の実装進捗ログである。
- Phase 6 の最終受入範囲と判定は `27` から `30` を正とする。

---

## 22. Phase 6 着手（通知実行・運用強化） 2026-03-29

参照した仕様/DDL:

- `config/ddl/tenant/t_notification_run.sql`
- `config/ddl/tenant/t_notification_delivery.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/m_renewal_reminder_phase.sql`
- `config/ddl/common/tenant_notify_targets.sql`
- `config/ddl/common/tenant_notify_routes.sql`

実装内容（Phase 6 初手）:

- 追加
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`（`t_notification_run` 作成/完了更新、`m_renewal_reminder_phase` 取得、`t_renewal_case` 対象抽出、`t_notification_delivery` 記録）
  - `src/Domain/Notification/RenewalNotificationBatchService.php`（renewal 通知実行本体、処理件数集計、冪等再実行時の skip 集計）
  - `tools/batch/run_renewal_notification.php`（CLI 実行入口）
  - `tools/batch/README.md`

実動確認（実測）:

- 実行1: `result=success`, `processed_count=10`, `success_count=10`, `skip_count=0`, `fail_count=0`
- 実行2（同条件 再実行）: `result=success`, `processed_count=10`, `success_count=0`, `skip_count=10`, `fail_count=0`
- 判定: delivery の一意制約を利用した冪等動作を確認

確認できたこと:

- `t_notification_run` に実行単位が記録される
- `t_notification_delivery` に個別通知実績が記録される
- 同日同対象の再実行は skip 集計になり、重複配信レコードを増やさない
- common/tenant の責務分離が成立（ルート判定: common、対象抽出/実績記録: tenant）

未実装（当時時点の継続項目）:

- `accident` 通知タイプのバッチ実装
- 配信失敗時の再試行ポリシー（backoff / 最大回数）
- cron 定期実行と運用監視ログの定義

---

## 23. Phase 6 拡張（accident追加 + retry方針） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/AccidentNotificationBatchRepository.php`（`t_notification_run`（accident）、`t_accident_reminder_rule` + `t_accident_reminder_rule_weekday` 対象抽出、delivery 記録/retry更新）
  - `src/Domain/Notification/AccidentNotificationBatchService.php`（accident 通知実行本体、retry 実行）
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`（failed delivery 取得 / retry 更新）
  - `src/Domain/Notification/RenewalNotificationBatchService.php`（`retry_failed_run_id` 指定時の再実行分岐）
  - `tools/batch/run_renewal_notification.php`（`--type=renewal|accident|all`、`--retry-failed-run-id=<runId>`）
  - `tools/batch/README.md`（type/retry 実行例を追記）
  - `tools/acceptance/suites/full/phase6_notification_acceptance_check.php`（all実行とretry実行の受入チェック）

実動確認（実測）:

- `--type=all` 実行で renewal + accident の2結果が返る
- retry 参照IDが保持される
- `tools/acceptance/suites/full/phase6_notification_acceptance_check.php` は `all_passed=true`
- `php tools/acceptance/run-suite.php --suite=full` は `all_passed=true`

---

## 24. Phase 6 継続（retry運用ポリシー固定） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/NotificationRetryPolicy.php`（retry 最大試行回数、最短再試行待機分、backoff / max attempts 判定）
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`（failed delivery 取得時に `notified_at`, `created_at`, `error_message` を返却）
  - `src/Domain/Notification/AccidentNotificationBatchRepository.php`（同上）
  - `src/Domain/Notification/RenewalNotificationBatchService.php`（retry 時に backoff / max attempts 判定を適用）
  - `src/Domain/Notification/AccidentNotificationBatchService.php`（renewal と同等の retry ポリシー適用）
  - `tools/batch/run_renewal_notification.php`（`--retry-max-attempts=<n>`、`--retry-minutes=<n>`）

実動確認（実測）:

- `--retry-minutes=60` 指定時、対象 failed delivery は retryされず `skip_count=1`
- `--retry-minutes=0` に戻すと retry success へ遷移
- `--retry-max-attempts=3` 指定時、attempt:3 の failed delivery はretryされず `skip_count=1`
- `--retry-max-attempts=4` に緩和すると retry success へ遷移

---

## 25. Phase 6 継続（Webhook実送信反映） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/WebhookNotificationSender.php`（`provider_type=lineworks` の webhook 送信処理、HTTP 2xx 判定、非2xx/例外を RuntimeException 化）
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchService.php`（route 有効時に webhook 実送信を実行、送信成功時のみ `success` 記録）
  - `src/Domain/Notification/AccidentNotificationBatchService.php`（renewal と同様の webhook 実送信フロー）
  - `tools/batch/run_renewal_notification.php`（route 取得で `provider_type`, `destination_name`, `webhook_url` をサービスへ受け渡し）

確認できたこと:

- DB 記録のみで success 扱いする挙動を排除し、HTTP 2xx を success 条件に変更した
- route 無効時は従来どおり skipped を維持
- idempotency（再実行 skip）の制御は既存の delivery 一意制約ベースを維持

---

## 26. Phase 6 継続（LINE WORKS本文業務化） 2026-03-29

実装内容（今回追加）:

- 更新
  - `src/AppConfig.php`（`APP_PUBLIC_URL` を追加し、LINE WORKS ボタンURLの基底値として利用）
  - `src/Domain/Notification/WebhookNotificationSender.php`（payload 検証強化）
  - `src/Domain/Notification/lineworks_payload_helpers.php`（payload ヘルパー関数群）
  - `src/Domain/Notification/RenewalNotificationBatchService.php`（満期通知を `【満期案件通知（早期）】` と `【満期案件通知（直前）】` の固定2種に整理、対象件数と一覧をまとめて送信）
  - `src/Domain/Notification/AccidentNotificationBatchService.php`（事故通知を `【事故対応リマインド】` 1通に集約）

確認できたこと:

- renewal は 28日前と14日前で別メッセージ送信になった
- accident は既存ルール一致案件を 1メッセージに集約して送信できた
- ボタンURLは `APP_PUBLIC_URL` ベースの絶対URLで生成される
- run / delivery 記録方式と idempotency は維持された

---

## 27. Phase 6 受入範囲の固定

Phase 6 の受入範囲は、通知バッチ機能の追加そのものではなく、定期実行、履歴確認、失敗時再実行の運用が再現可能な定義として固定されていることに限定する。

確認対象:

- cron 実運用ジョブ定義
- 失敗検知条件とアラート導線
- 監視手順
- 一次確認手順
- 再実行手順
- Phase 6 受入判定（短縮版）の明記

本フェーズでは、監視ダッシュボード化、運用SOPの詳細化、障害一次切り分けの高度化までは受入範囲に含めない。

---

## 28. Phase 6 運用定義の固定

### 28-1. cron 実運用ジョブ定義

Phase 6 で本番運用に固定する cron ジョブ:

- 満期通知バッチ: 毎営業日 1 回、業務開始前
- 事故通知バッチ: 毎営業日 1 回、満期通知と分離した時刻

実運用コマンド例（パスは環境に合わせて置換）:

- 満期通知: `/usr/bin/php /home/your_account/insurance-agency/tools/batch/run_renewal_notification.php --date=$(date +\%F) --tenant=TE001 --executed-by=1 --type=renewal >> /home/your_account/logs/renewal_notification.log 2>&1`
- 事故通知: `/usr/bin/php /home/your_account/insurance-agency/tools/batch/run_renewal_notification.php --date=$(date +\%F) --tenant=TE001 --executed-by=1 --type=accident >> /home/your_account/logs/accident_notification.log 2>&1`

必須条件: 終了コード 0 を成功・0 以外を失敗として扱う。多重実行を避ける前提を明示する。

### 28-2. 通知失敗の検知条件

通知失敗は、以下のいずれかに該当する場合と定義する:

- バッチプロセス自体が異常終了した場合
- 通知対象の抽出に失敗した場合
- 配信処理でエラーが返却された場合
- 実行履歴が `failed` または `partial` となった場合
- 送信対象件数に対して配信成功件数が不足した場合

`partial` は一部失敗として扱い、運用上は成功扱いにしない。確認対象とする。

### 28-3. アラート導線

通知失敗時のアラート通知先: 管理者ユーザー / 運用確認担当者

アラート文面に含める最低限の情報: バッチ種別、実行日時、実行結果、失敗件数または不整合件数、確認対象ログまたは履歴の参照先

### 28-4. 監視手順

毎日の監視手順:

1. 対象バッチが当日分として起動していることを確認する
2. `t_notification_run` に当日実行履歴が記録されていることを確認する
3. 実行結果が `success` であることを確認する
4. `partial` または `failed` がある場合は対象 run を特定する
5. `t_notification_delivery` で失敗対象の有無と件数を確認する
6. 必要に応じてログ出力を確認する
7. 再実行が必要かを判断する

### 28-5. 一次確認手順

失敗時の一次確認:

1. 対象バッチ種別を特定する
2. 実行日時と対象 run を特定する
3. `t_notification_run` の status、件数、エラーメッセージを確認する
4. `t_notification_delivery` で失敗対象、対象案件、配信結果を確認する
5. アプリケーションログまたは cron ログで異常終了有無を確認する
6. 設定不備か、データ不備か、外部配信失敗かを切り分ける
7. 再実行可否を判断する

### 28-6. 再実行手順

再実行手順:

1. 対象 run と対象通知種別を特定する
2. 前回失敗原因が解消済みであることを確認する
3. 対象バッチを手動実行する
4. 実行後、新しい `t_notification_run` が記録されることを確認する
5. 実行結果が `success` であることを確認する
6. `partial` の場合は未解消として継続確認対象とする
7. `t_notification_delivery` の失敗件数が解消していることを確認する
8. 必要に応じて運用記録へ再実行結果を残す

正式コマンド:

- 満期通知の再実行: `php tools/batch/run_renewal_notification.php --date=YYYY-MM-DD --tenant=TE001 --executed-by=1 --type=renewal --retry-failed-run-id=対象RunID`
- 事故通知の再実行: `php tools/batch/run_renewal_notification.php --date=YYYY-MM-DD --tenant=TE001 --executed-by=1 --type=accident --retry-failed-run-id=対象RunID`
- 必要時の再試行ポリシー指定: `--retry-max-attempts=回数`、`--retry-minutes=待機分`

---

## 29. Phase 6 受入判定（短縮版）

確認済み:

- 通知バッチの実装が存在し、定期実行を前提とした構成になっている
- 通知実行履歴を確認する前提テーブルが存在する
- 配信結果を確認する前提テーブルが存在する
- cron 実運用ジョブ定義を固定対象として明記した
- 失敗検知条件を明記した
- アラート導線を明記した
- 監視手順を明記した
- 一次確認手順を明記した
- 再実行手順を明記した

未実装:

- 監視ダッシュボード化
- 運用SOPの詳細テンプレート化
- 障害一次切り分けの高度化
- 自動エスカレーションや二次通知の仕組み

未確認:

- 本番 cron 環境での長期連続運転実績
- 実障害発生時の運用フロー一巡
- 運用担当者交代時の手順引継ぎ妥当性

Phase 6 判定結論: Phase 6 は、通知バッチ運用を再現可能な定義として固定する範囲において受入完了とする。

---

## 30. 次フェーズへの分離

Phase 6 で受け入れない残課題は、後続の運用定着フェーズへ分離する。

運用定着フェーズの対象:

- 監視ダッシュボード化
- 運用SOP詳細化
- 障害一次切り分け手順の高度化
- 運用記録テンプレート整備
- アラート経路の強化
- 運用レビューサイクルの整備

---

## 31. 営業活動管理 Phase A — Activity CRUD（追加フェーズ）

目的: 顧客起点の営業活動（訪問・電話・メール等）を記録し、一覧・詳細・編集・削除を可能にする。

前提:

- `t_activity` DDL は追加済み。変更禁止。
- `t_daily_report` / `t_sales_case` DDL も追加済み。フェーズA時点では `t_daily_report` は未使用、`t_sales_case` は紐づけフィールドのみ保持（UI非表示）。

対象画面: SCR-ACTIVITY-LIST、SCR-ACTIVITY-NEW、SCR-ACTIVITY-DETAIL

対象PHPファイル（新規）:

- `src/Domain/Activity/ActivityRepository.php`
- `src/Controller/ActivityController.php`
- `src/Presentation/ActivityListView.php`
- `src/Presentation/ActivityDetailView.php`

対象PHPファイル（変更）:

- `src/bootstrap.php`: ActivityController DI登録、ルート追加（計6本: `GET activity/list`, `GET activity/new`, `GET activity/detail`, `POST activity/store`, `POST activity/update`, `POST activity/delete`）
- `src/Presentation/View/Layout.php`: navLinks に「営業活動」追加
- `src/Domain/Customer/CustomerRepository.php`: `findActivities()` のカラム名不整合修正（activity_at→activity_date, detail→detail_text, outcome→result_type）
- `src/Presentation/CustomerDetailView.php`: 活動履歴セクションに活動詳細リンクと活動登録ボタン追加

既存不整合（修正対象）: `CustomerRepository::findActivities()` が存在しないカラムを参照している（`activity_at`→`activity_date`、`detail`→`detail_text`、`outcome`→`result_type`）

完了条件:

- 活動を新規登録できる（customer_id 必須）
- 活動一覧で日付・担当者・活動種別でフィルタできる
- 活動詳細で内容を確認・編集・削除できる
- 顧客詳細の活動履歴セクションから活動詳細へ遷移できる
- 顧客詳細から活動登録（customer_id 引き継ぎ）へ遷移できる
- sales_case_id は DB保存可能だが UI は非表示
- 他テナントのデータが参照されないこと

---

## 32. 営業活動管理 Phase B — Daily Report View + 提出フロー（追加フェーズ）

目的: 指定日の活動を集約表示し、日報コメントを入力・保存できる日報ビューを追加する。あわせて、日報提出操作（is_submitted / submitted_at の更新）と、管理者向けの未提出確認フィルタを実装する。

前提:

- Phase A 完了後に着手。
- `t_daily_report` DDL は追加済み。UNIQUE KEY(report_date, staff_user_id)。変更禁止。
- 日報コメントの upsert は INSERT ON DUPLICATE KEY UPDATE で実装。
- 日報提出は本人のみ実行可能。管理者が他担当者の日報を参照中は提出ボタンを表示しない。
- 提出後の取り消しはできない。提出済みレコードのコメント編集も不可とする。

対象画面:

- SCR-ACTIVITY-DAILY（日報ビュー）
- SCR-ACTIVITY-LIST（活動一覧）: 日報提出状態フィルタを追加

対象PHPファイル（新規）:

- `src/Domain/Activity/DailyReportRepository.php`
- `src/Presentation/ActivityDailyView.php`

対象PHPファイル（変更）:

- `src/Controller/ActivityController.php`（`daily()` / `saveComment()` / `submit()` メソッド追加）
- `src/bootstrap.php`（ルート追加3本: `GET activity/daily`, `POST activity/comment`, `POST activity/submit`）
- `src/Domain/Activity/ActivityRepository.php`（一覧検索に日報提出状態フィルタ条件を追加）
- `src/Presentation/ActivityListView.php`（管理者ロール時に日報提出状態フィルタを表示。日付リンクに「提出済み」バッジを付与）

必要なDBテーブル:

- `t_daily_report`（日報コメント・提出フラグ）
- `t_activity`（その日の活動一覧）
- `common.users`（担当者名表示）

完了条件:

- 活動一覧の日付リンクから日報ビューへ遷移できる
- 日報ビューで指定日の活動が一覧表示される（自分の活動のみ。管理者は担当者切替可能）
- 日報コメントを入力・保存できる（1スタッフ1日1件。再保存で上書き）
- 未提出の場合のみ「日報を提出する」ボタンが表示される
- 提出ボタン押下で is_submitted=1 / submitted_at=NOW() が記録される
- 提出済みの場合、コメント欄は読み取り専用になり、提出ボタンは非表示になる
- 提出済みの場合、提出日時（submitted_at）がヘッダーに表示される
- 管理者ロールが他担当者の日報を参照中は提出ボタンを表示しない
- 活動一覧で管理者ロール時に日報提出状態フィルタ（全て / 提出済み / 未提出）が表示される
- 提出済み日報の日付リンクに「提出済み」バッジが付与される
- 他テナントのデータが参照されないこと

> **ルート追加の総計（Phase A + Phase B）**: 9 本（Phase A: 6本 + Phase B: 3本）

---

## 33. 営業活動管理 Phase C-Lite — Sales Case 最小実装（追加フェーズ）

目的: Excel「日報」の「見込み」シートで月次管理されていた営業パイプライン業務をWebに移行する。見込案件の登録・確認・編集・削除の最小実装を行い、活動記録との紐づけを有効化する。パイプライン集計・分析・ファネル管理は Phase C-Full で扱う。

前提:

- Phase A/B 完了後に着手。
- `t_sales_case` DDL は追加済み。変更禁止。
- Phase A で「sales_case_id は UI 非表示・DB は NULL 固定」としていた制限を本フェーズで解除する。
- 本フェーズ完了後、`02_navigation-policy.md` の指定に従い、見込案件一覧を main nav に追加する。
- テナント分離は既存パターン（TenantConnectionFactory）に完全準拠。

対象画面:

- SCR-SALES-CASE-LIST（見込案件一覧）
- SCR-SALES-CASE-NEW（見込案件登録）
- SCR-SALES-CASE-DETAIL（見込案件詳細）

対象PHPファイル（新規）:

- `src/Domain/SalesCase/SalesCaseRepository.php`
- `src/Controller/SalesCaseController.php`
- `src/Presentation/SalesCaseListView.php`
- `src/Presentation/SalesCaseDetailView.php`

対象PHPファイル（変更）:

- `src/bootstrap.php`（SalesCaseController DI登録、ルート追加6本: `GET sales-case/list`, `GET sales-case/new`, `GET sales-case/detail`, `POST sales-case/store`, `POST sales-case/update`, `POST sales-case/delete`）
- `src/Presentation/View/Layout.php`（navLinks に「営業案件」追加。Phase C-Lite 完了後）
- `src/Controller/ActivityController.php`（活動登録・更新時の sales_case_id 受け取りを有効化）
- `src/Presentation/ActivityDetailView.php`（sales_case_id プルダウンを表示・選択可能にする）
- `src/Presentation/CustomerDetailView.php`（顧客に紐づく見込案件一覧セクションを追加）

必要なDBテーブル:

- `t_sales_case`（主）
- `m_customer`（顧客名表示・選択）
- `t_contract`（契約紐づけ。任意）
- `common.users`（担当者名表示）
- `t_activity`（見込案件詳細での紐づき活動一覧表示）

一覧表示項目（最小構成）:

| 項目 | カラム | 備考 |
|---|---|---|
| 顧客名 | m_customer.customer_name | 顧客詳細へのリンク |
| 案件種別 | case_type | 新規・更新・クロスセル等 |
| 種目 | product_type | |
| 見込保険料 | expected_premium | |
| 見込度 | probability | A / B / C |
| 成約予定月 | expected_close_month | |
| ステータス | status | |
| 担当者 | common.users の表示名 | |
| 操作 | - | 詳細リンク |

完了条件:

- 見込案件を新規登録できる（顧客必須・種目・見込保険料・見込度・成約予定月）
- 見込案件一覧で顧客名・担当者・ステータス・見込度でフィルタできる
- 見込案件詳細で内容を確認・編集・削除できる
- 見込案件詳細から紐づく活動履歴を参照できる
- 活動登録・編集画面で sales_case_id を見込案件から選択できる（プルダウン）
- 顧客詳細に「この顧客の見込案件」セクションが表示される
- 見込案件一覧が main nav「営業案件」から到達できる
- 他テナントのデータが参照されないこと

> **ルート追加の総計（Phase A + Phase B + Phase C-Lite）**: 15 本

---

## 34. 営業活動管理 Phase C-Full — Sales Case パイプライン管理（予定フェーズ）

Phase C-Lite 完了後に別途スコープを定義する。

対象となる業務機能は以下を想定するが、着手時に改めて確定する:

- 見込案件のパイプライン集計・ファネル分析
- 担当者別・種目別・月別の見込保険料サマリ
- ダッシュボードへの見込案件要約ウィジェット追加
- 成約・失注の結果記録と実績との突合
- 見込案件からの実績登録導線

本フェーズの実装計画・完了条件は Phase C-Lite 完了後に追記する。

---

## 35. 変更履歴詳細表示（audit_event_detail 表示対応）

目的: 満期詳細・事故案件詳細の変更履歴領域に、項目単位の変更前後の値を表示できるようにする。Phase 2 受入判定で「未実装」として残存していた `t_audit_event_detail` の画面表示を確定実装する。

前提:

- `t_audit_event` および `t_audit_event_detail` DDL は追加済み。変更禁止。
- Phase 2 時点で `t_audit_event` の一覧（日時・変更者・操作種別）は実装済み。
- 本フェーズでは `t_audit_event_detail` の内容（項目名・変更前・変更後）を追加表示する。

対象画面:

- SCR-RENEWAL-DETAIL（満期詳細の変更履歴領域）
- SCR-ACCIDENT-DETAIL（事故案件詳細の変更履歴領域）

表示仕様:

変更履歴の各イベント行を展開すると、以下を表示する。

| 表示項目 | 取得元カラム |
|---|---|
| 項目名 | t_audit_event_detail.field_label |
| 変更前の値 | t_audit_event_detail.before_value_text |
| 変更後の値 | t_audit_event_detail.after_value_text |

`before_value_text` または `after_value_text` が NULL の場合は「未設定」と表示する。
`value_type` が `JSON` の場合は `before_value_json` / `after_value_json` を参照する。

対象PHPファイル（変更）:

- `src/Domain/Renewal/RenewalCaseRepository.php`: `findAuditEventDetails(audit_event_id)` を追加
- `src/Domain/Accident/AccidentCaseRepository.php`: 同上
- `src/Presentation/RenewalCaseDetailView.php`: 変更履歴領域にイベント詳細の展開表示を追加
- `src/Presentation/AccidentCaseDetailView.php`: 同上

完了条件:

- 満期詳細の変更履歴で、各イベントの変更項目・変更前後の値が確認できる
- 事故案件詳細でも同様に確認できる
- `t_audit_event_detail` が0件のイベントでは詳細行を表示しない（エラーにしない）
- 他テナントのデータが参照されないこと

---

## Phase 設定A: テナント設定拡張（SJNETコード設定 / 目標管理 / 用件区分マスタ）

目的: テナント設定画面に以下の3機能を追加する。

1. SJNETコード設定: 代理店コード↔ユーザーマッピングの CRUD
2. 目標管理: 年度・月別・担当者別目標値の登録・更新
3. 用件区分マスタ: m_activity_purpose_type の CRUD

必要なDBテーブル:

- `m_sjnet_staff_mapping`（SJNETコード設定用）
- `t_sales_target`（目標管理用）
- `m_activity_purpose_type`（用件区分マスタ用）

対象PHPファイル（変更）:

- `src/Controller/TenantSettingsController.php`: SJNETコード設定・目標管理・用件区分の CRUD メソッド追加
- `src/Presentation/TenantSettingsView.php`: 3セクションの表示・フォーム追加
- `src/Domain/Tenant/SjnetStaffMappingRepository.php`: 新規作成
- `src/Domain/Tenant/SalesTargetRepository.php`: 新規作成
- `src/Domain/Tenant/ActivityPurposeTypeRepository.php`: 新規作成
- `src/bootstrap.php`: ルート追加

完了条件:

- SJNETコード設定: 代理店コードの追加・編集・無効化ができる
- 目標管理: 年度・月別・担当者別目標額を登録・更新できる
- 用件区分マスタ: 用件区分の追加・編集・無効化ができる
- いずれも他テナントのデータへの影響がないこと
- SJNET取込後に「マッピング未登録」がある場合、取込結果からテナント設定へ遷移できる
