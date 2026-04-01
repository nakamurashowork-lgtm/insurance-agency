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
- `docs/03_screen-map.md`
- `docs/04_folder-structure.md`
- `docs/01_canonical-schema.md`
- `docs/02_navigation-policy.md`
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

- 画面責務は `docs/03_screen-map.md` に固定する。
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
- 編集と削除

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

---

## 5. 画面 / API / DB / バッチ / 権限 対応表

| 画面 | 画面系PHP | API系PHP | 主DBテーブル | バッチ関連 | 権限 |
|---|---|---|---|---|---|
| SCR-LOGIN | ログイン表示 | 認証開始/戻り/ログアウト | users, user_tenants, tenants | なし | 未認証可、利用可否判定必須 |
| SCR-DASHBOARD | ホーム表示 | サマリ取得 | t_renewal_case, t_accident_case, t_sales_performance | なし | ログイン必須、管理・設定導線のみ権限限定 |
| SCR-RENEWAL-LIST | 一覧表示 | 検索 | t_contract, t_renewal_case, m_customer | なし | ログイン必須、表示範囲制御 |
| SCR-RENEWAL-DETAIL | 詳細表示 | 詳細/更新/履歴 | t_contract, t_renewal_case, t_case_comment, t_audit_event, t_audit_event_detail | なし | ログイン必須、更新範囲制御 |
| SCR-CUSTOMER-LIST | 一覧表示 | 検索 | m_customer, m_customer_contact | なし | ログイン必須、表示範囲制御 |
| SCR-CUSTOMER-DETAIL | 詳細表示 | 取得/更新/保有契約/活動履歴 | m_customer, m_customer_contact, t_contract, t_activity | なし | ログイン必須、編集可否制御 |
| SCR-SALES-LIST | 一覧表示 | 検索/登録/CSV | t_sales_performance, m_customer, t_contract, t_renewal_case | CSV取込（画面起点） | ログイン必須、更新権限制御 |
| SCR-SALES-DETAIL | 詳細表示 | 取得/編集/削除 | t_sales_performance, m_customer, t_contract, t_renewal_case | なし | ログイン必須、更新権限制御 |
| SCR-ACCIDENT-LIST | 一覧表示 | 検索/更新 | t_accident_case | 事故通知条件参照 | ログイン必須、表示範囲制御 |
| SCR-ACCIDENT-DETAIL | 詳細表示 | 更新/コメント/監査 | t_accident_case, t_case_comment, t_audit_event, t_audit_event_detail | 事故通知条件参照 | ログイン必須、更新範囲制御 |
| SCR-ACTIVITY-LIST | 一覧表示 | 検索（日付・担当者・種別） | t_activity, m_customer, common.users | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-NEW | 登録フォーム | 新規登録 | t_activity, m_customer | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-DETAIL | 詳細表示 | 取得/更新/削除 | t_activity, m_customer | なし | ログイン必須、全ユーザー |
| SCR-ACTIVITY-DAILY | 日報ビュー | 当日活動取得/コメントupsert | t_activity, t_daily_report, common.users | なし | ログイン必須、全ユーザー |
| SCR-TENANT-SETTINGS | 設定表示（管理者補助） | 通知設定/マスタ設定更新 | tenant_notify_targets, tenant_notify_routes, m_renewal_reminder_phase | 通知実行前提設定 | 管理権限者のみ標準表示・更新 |

---

## 6. 実装フェーズ（XServer + MySQL + PHP）

現在の到達点（2026-03-29）:

- Phase 1-6 は受入完了。
- 以降は運用定着フェーズとして扱う。
- Phase 6 の最終受入範囲と判定は `27` から `30` を正とする。

## Gate 0（Phase 1 着手前の必須解消事項）

実施状態:

- 実施済み（2026-03-28）

目的:

- ログイン仕様差分を解消し、Phase 1 の実装正本を固定する。

必須解消事項（解消済み）:

- `docs/03_screen-map.md` のログイン記述を Google認証ベースへ修正済み。
- 初期実装の認証方式を Google認証として文書明記済み。

初期実装方針（確定）:

- `docs/screens/login.md` を正として、Google認証を初期実装の認証方式とする。

ゲート条件:

- 差分解消の文書反映が完了していること（本項目は充足）。

### Phase 1: 認証・セッション・テナント基盤

目的:

- ログイン成功時のセッション確立とホーム遷移を成立させる。

対象画面:

- SCR-LOGIN
- SCR-DASHBOARD（入口表示のみ）

対象PHPファイル:

- 入口ルーティング
- 認証開始/コールバック
- セッション作成/破棄
- 認証ガード

必要な共通部品:

- ルータ/ディスパッチ
- セッション管理
- common/tenant DB接続切替
- 権限判定（ユーザーID、表示名、テナントID、権限情報）

必要なDBテーブル:

- `users`
- `user_tenants`
- `tenants`

認証/権限の考慮:

- 複数テナント所属時は主所属テナントで自動ログイン
- 一般利用者には管理・設定導線のみ非表示とする

完了条件:

- 未認証アクセスはログインへ遷移
- 認証成功でセッション作成しホーム遷移
- ログアウトでセッション破棄

実装起点（現行）:

- Web公開入口は `public/index.php` のみ
- `public/index.php` から以下を読み込んで実行する
	- `require dirname(__DIR__) . '/src/bootstrap.php';`

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

Phase 1 A分類 実施結果（2026-03-28）:

1. 現行実行経路と現行ファイル集合の最終確認

- 実行起点は `public/index.php` のみ
- `public/index.php` から `src/bootstrap.php` を読み込み、ルーティング定義により以下の現行ファイル群を参照する
  - `src/EnvLoader.php`
  - `src/AppConfig.php`
  - `src/SessionManager.php`
  - `src/Infra/CommonConnectionFactory.php`
  - `src/Infra/TenantConnectionFactory.php`
  - `src/Domain/Auth/UserRepository.php`
  - `src/Domain/Auth/GoogleOAuthClient.php`
  - `src/Auth/TenantResolver.php`
  - `src/Auth/AuthService.php`
  - `src/Security/AuthGuard.php`
  - `src/Presentation/Controller/LoginController.php`
  - `src/Controller/AuthController.php`
  - `src/Controller/DashboardController.php`
  - `src/Http/Router.php`
  - `src/Http/Responses.php`
  - `src/Presentation/DashboardView.php`
  - `src/Presentation/View/LoginView.php`
  - `src/Presentation/View/Layout.php`
  - `src/Domain/Auth/AuthException.php`
  - `src/ConfigurationException.php`
- 検索対象ディレクトリは `public/`, `src/`, `config/`
- 同責務ファイル検索結果
  - `src/**/*Auth*Service*.php` = `src/Auth/AuthService.php` の 1 件
  - `src/**/*Tenant*Resolver*.php` = `src/Auth/TenantResolver.php` の 1 件
  - `src/Config/**/*.php` = 0 件
  - `src/Infra/Database/**/*.php` = 0 件
- `src/Config/` と `src/Infra/Database/` は空ディレクトリであり、現行実行経路で参照される PHP ファイルは存在しない
- 上記検索結果により、現行ファイルと同責務の旧 PHP ファイル残置は確認されなかった

2. `.env` と公開境界

- 実行確認済み
  - local `.env` は `APP_ENV`, `APP_URL`, `COMMON_DB_*`, `TENANT_DB_*`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `SESSION_COOKIE_NAME`, `SESSION_COOKIE_SECURE` を保持していた
  - `http://localhost/insurance-agency/public/?route=login` は `200 OK`
  - `http://localhost/insurance-agency/public/?route=dashboard` は未認証時に `302 Found` で `/?route=login` へ遷移
  - `http://localhost/insurance-agency/src/bootstrap.php` は `403 Forbidden`
  - `http://localhost/insurance-agency/config/.htaccess` は `403 Forbidden`
- 静的確認のみ
  - `.env.example` と `src/AppConfig.php` の設定キー整合
  - ソースコードに秘密情報の実値直書きがないこと
- 未確認
  - なし

3. 認証前提データ

- 実行確認済み
  - common DB の active users = 2
  - active tenants = 2
  - active memberships = 3
  - 単一所属ユーザー = 1
  - 複数所属ユーザー = 1
  - tenant admin 所属 = 1
  - `google_sub` を持つ有効ユーザー = 2
  - 一時ユーザーを投入して「所属 0 件」も `src/Auth/TenantResolver.php` で拒否されることを確認済み
- 静的確認のみ
  - なし
- 未確認
  - `is_system_admin = 1` の実ユーザーデータは local DB に存在しない

4. Google OAuth とログイン完了

- 実行確認済み
  - `/?route=auth/google/start` は `302 Found` で `accounts.google.com` へ遷移
  - `state` は 32 文字で生成されることを確認
  - 不正な `state` を付けた `/?route=auth/google/callback` は `302 Found` で `/?route=login` へ戻ることを確認
  - 正しい `state` + 偽の `code` を付けた `/?route=auth/google/callback` も `302 Found` で `/?route=login` へ戻ることを確認
  - 実 Google アカウントで認証完了し、ブラウザ上で `dashboard` まで到達する成功経路を確認
  - `src/Auth/AuthService.php` を直接実行し、既存 `google_sub` からセッション生成、権限格納、tenant DB 名の解決まで成立することを確認
- 静的確認のみ
  - 認証開始前の設定不足検知は `src/Controller/AuthController.php` に実装されている

5. セッション hardening と logout

- 実行確認済み
  - `/?route=login` の `Set-Cookie` は `HttpOnly` と `SameSite=Lax` を含み、local 設定により `Secure` は付与されない
  - `src/Auth/AuthService.php` の実行で `session_regenerate_id(true)` によりセッション ID が変化することを確認
  - 認証済みセッションで `dashboard` 表示時に logout 用 CSRF hidden input が出力されることを確認
  - 誤った CSRF トークンでの `POST /?route=logout` は `302 Found` で `/?route=dashboard` へ戻り、セッション継続を確認
  - 正しい CSRF トークンでの `POST /?route=logout` は `302 Found` で `/?route=login` へ遷移し、その後 `/?route=dashboard` は `302 Found` で `/?route=login` へ遷移
  - `GET /?route=logout` では logout は成立せず、ルータ上は `404 Not Found`
- 静的確認のみ
  - `session.use_only_cookies = 1`
  - `session.use_strict_mode = 1`
  - `session.cookie_httponly = 1`
  - `session.cookie_samesite = Lax`
  - `session.cookie_secure` は `.env` の `SESSION_COOKIE_SECURE` を参照
- 本番投入前確認
  - `SESSION_COOKIE_SECURE=true` を使う HTTPS 環境での `Secure` 属性付与

6. 権限制御と tenant DB 接続確認

- 実行確認済み
  - 未認証で `/?route=dashboard` へアクセスすると `/?route=login` へ遷移
  - 単一所属ユーザーは `src/Auth/TenantResolver.php` で `TE001` を返却
  - 複数所属ユーザーは `src/Auth/TenantResolver.php` で拒否
  - 所属 0 件ユーザーは `src/Auth/TenantResolver.php` で拒否
  - 認証済み admin セッションでは `dashboard` に管理者向け設定導線（`tenant/settings`）の HTML 断片 `card helper` が出現
  - 認証済み member セッションでは管理者向け設定導線（`tenant/settings`）が出現しない
  - admin / member いずれの認証済みセッションでも `xs000001_te001` への tenant DB 接続確認表示が成功
- 静的確認のみ
  - なし
- 未確認
  - 実ユーザーデータとして `is_system_admin = 1` を持つケースでの表示確認

7. 判定との整合整理

【Phase 1 受入完了で確認済み】

- 実 Google アカウントでの認証成功から `dashboard` 到達までのブラウザ経路

【Phase 1 実装で解消済み】

- root 相対 URL による誤遷移問題は解消済み
- `google_sub` 未登録ユーザー向け email フォールバック紐付けは実装済み

【本番投入前確認項目（ローカル Phase 1 完了条件には含めない）】

- HTTPS 環境での `SESSION_COOKIE_SECURE=true` による `Secure` 属性付与
  - XServer 等 HTTPS 環境で `.env` の `SESSION_COOKIE_SECURE=true` に変更して確認する
  - `src/SessionManager.php` にコードレベルの実装は済んでいる

【追加確認項目（Phase 1 完了のブロッカーにしない）】

- 実ユーザーデータとして `is_system_admin = 1` を持つケースでの表示確認
  - admin/member 間の `card helper` 表示差分はセッション注入で確認済み
  - 実 DB ユーザーへの適用は本番準備時に確認する

8. 完了判定

【Phase 1 開発完了】✅

- ルート保護、セッション hardening、CSRF 防御、tenant DB 接続、admin/member 表示分岐は実行確認済み
- root 相対 URL 問題の修正は反映済み
- `google_sub` 未登録ユーザー向け email フォールバック紐付けは反映済み
- Phase 1 実装コードは完成状態にある

【Phase 1 受入完了】✅

- 実 Google アカウントでのブラウザ認証成功経路の確認は完了

【本番投入前確認】一部未実施

- HTTPS 環境での `Secure` 属性付与
- XServer デプロイ後に実施する

補足:

- `is_system_admin = 1` 実データ確認は追加確認項目とし、Phase 1 完了のブロッカーにはしない

### Phase 2: 主導線（満期一覧/満期詳細）

目的:

- 日常業務の主導線を成立させる。

対象画面:

- SCR-RENEWAL-LIST
- SCR-RENEWAL-DETAIL

対象PHPファイル:

- 一覧表示/検索
- 詳細表示/更新
- コメント取得/登録
- 変更履歴取得

必要な共通部品:

- 検索条件バリデーション
- 一覧ページング
- 更新トランザクション

必要なDBテーブル:

- `t_contract`
- `t_renewal_case`
- `m_customer`
- `m_customer_contact`
- `t_case_comment`
- `t_audit_event`
- `t_audit_event_detail`

認証/権限の考慮:

- 表示範囲制御
- 更新可否制御

対象処理の責務分離:

- 顧客活動履歴表示: 顧客詳細側で `t_activity` の時系列表示
- コメント表示/登録: `t_case_comment` の取得と登録
- 変更履歴表示: `t_audit_event`, `t_audit_event_detail` の参照表示

完了条件:

- 一覧から詳細への遷移が成立
- 満期詳細で更新が成立
- 契約独立画面を作らずに要件を満たす

### Phase 3: 顧客導線（顧客一覧/顧客詳細）

目的:

- 顧客軸の独立導線を成立させる。

対象画面:

- SCR-CUSTOMER-LIST
- SCR-CUSTOMER-DETAIL

対象PHPファイル:

- 一覧表示/検索
- 詳細取得
- 顧客更新（要確認）
- 保有契約一覧取得
- 活動履歴取得

必要な共通部品:

- 顧客検索条件処理
- 更新バリデーション

必要なDBテーブル:

- `m_customer`
- `m_customer_contact`
- `t_contract`
- `t_activity`

認証/権限の考慮:

- 閲覧範囲制御
- 編集可否制御（要確認）

対象処理の責務分離:

- 活動履歴表示: `t_activity` を顧客文脈で表示
- コメント表示/登録: 本フェーズ対象外（コメントは案件文脈）
- 監査ログ表示: 本フェーズは表示対象外（必要時は別途要件化）

完了条件:

- 顧客一覧から顧客詳細へ遷移
- 顧客詳細から満期詳細への補助遷移成立
- 顧客詳細を契約処理画面化しない

### Phase 4A: 実績管理（一覧/検索/登録/編集/削除）

目的:

- 実績管理の基本業務を成立させる。

対象画面:

- SCR-SALES-LIST
- SCR-SALES-DETAIL

対象PHPファイル:

- 一覧表示
- 検索
- 登録
- 詳細表示
- 編集
- 削除

必要な共通部品:

- 入力バリデーション
- 一覧ページング
- 更新トランザクション

必要なDBテーブル:

- `t_sales_performance`
- `m_customer`
- `t_contract`
- `t_renewal_case`

認証/権限の考慮:

- 登録/編集/削除の権限制御

対象処理の責務分離:

- 活動履歴表示: 本フェーズ対象外
- コメント表示/登録: 本フェーズ対象外
- 監査ログ表示: 本フェーズ対象外（記録要否は要確認）

完了条件:

- 一覧、検索、登録、編集、削除が成立
- 実績一覧から実績詳細へ遷移できる
- 編集、削除は実績詳細画面起点で成立

### Phase 4B: 実績管理（CSV取込/エラー集約/取込結果表示）

目的:

- 実績CSV取込の運用を成立させる。

対象画面:

- SCR-SALES-LIST（CSV機能）

対象PHPファイル:

- CSV取込
- エラー集約
- 取込結果表示

必要な共通部品:

- CSVパーサ
- 一括登録トランザクション
- 失敗行集約と結果整形

必要なDBテーブル:

- `t_sales_performance`
- `m_customer`
- `t_contract`
- `t_renewal_case`

認証/権限の考慮:

- CSV取込実行権限制御

対象処理の責務分離:

- 活動履歴表示: 本フェーズ対象外
- コメント表示/登録: 本フェーズ対象外
- 監査ログ表示: 本フェーズ対象外（必要なら別途要件化）

完了条件:

- CSV取込、エラー集約、取込結果表示が成立

### Phase 5: 事故業務導線と管理・設定（**実装完了・標準業務**）

位置づけ:

本フェーズは実装完了済みであり、事故業務は満期・顧客・実績と並ぶ標準業務導線として正式に昇格している。

事故業務を通常主導線として提供する一方で、テナント設定などの管理・設定機能を権限付きで分離する。

詳細方針については、`2. 変更方針` で確定しており、`docs/02_navigation-policy.md` で正式に定義されている。

目的:

- 事故案件一覧/詳細を一般利用者の標準業務導線として安定運用する
- 管理・設定機能を権限者側に適切に分離する

対象画面:

- SCR-ACCIDENT-LIST
- SCR-ACCIDENT-DETAIL
- SCR-TENANT-SETTINGS（管理・設定）

対象PHPファイル:

- 事故一覧/詳細表示
- 事故更新
- 事故コメント取得/登録
- 事故監査ログ取得
- テナント設定表示/更新

必要な共通部品:

- 一般ログインユーザー向けの事故案件認可
- 管理・設定導線表示制御

必要なDBテーブル:

- `t_accident_case`
- `t_accident_reminder_rule`
- `t_accident_reminder_rule_weekday`
- `t_case_comment`
- `t_audit_event`
- `t_audit_event_detail`
- `common.tenant_notify_targets`
- `common.tenant_notify_routes`
- `m_renewal_reminder_phase`

実装状況（2026-03-30 確定）:

- 事故案件一覧/詳細: 実装完了、標準業務導線に昇格
- main nav に「事故管理」として掲載される
- ホームの日常業務カードに含まれている
- 一般利用者アクセス可、権限による表示制御無し
- テナント設定: 管理権限者に限定

認証/権限の考慮:

- 事故案件はログイン済み利用者の標準業務導線に含める ← **実装済み**
- 事故案件は main nav とホームの日常業務に含める ← **実装済み**
- テナント設定のみ管理権限者に限定して表示/更新する

対象処理の責務分離:

- 活動履歴表示: 事故案件で必要時のみ `t_activity` を補助参照（要件化時）
- コメント表示/登録: `t_case_comment`（事故案件文脈）
- 監査ログ表示: `t_audit_event`, `t_audit_event_detail`（事故更新・設定更新の追跡）

完了条件:

- ログイン済み利用者で事故案件の一覧/詳細/更新が利用可能
- 管理権限者のみテナント設定を利用可能

注記:

- `20` と `21` の受入確認ログは 2026-03-29 時点の実測記録であり、当時の admin 限定実装を前提としている。
- 2026-03-30 以降の正式方針は、本節および `3. 変更方針` を正とし、事故案件を通常業務導線へ昇格させる。

### Phase 6: バッチ・通知・運用強化

目的:

- 通知実行、配信結果、取込運用を安定化する。

対象画面:

- 必要に応じて既存画面へ補助表示

対象PHPファイル:

- 通知系CLIバッチ
- 運用確認API/表示

必要な共通部品:

- XServer cron 設定
- ログ出力
- 冪等制御

必要なDBテーブル:

- `t_notification_run`
- `t_notification_delivery`
- `t_sjnet_import_batch`
- `t_sjnet_import_row`

認証/権限の考慮:

- 手動実行・履歴閲覧は管理者中心

対象処理の責務分離:

- 活動履歴表示: 本フェーズ対象外
- コメント表示/登録: 本フェーズ対象外
- 監査ログ表示: バッチ運用に必要な範囲で参照（要件化）

完了条件:

- 定期実行、履歴確認、失敗時再実行手順が確立

---

## 7. 仕様不足・競合・未確定点

1. ログイン仕様差分
- `docs/03_screen-map.md` と `docs/screens/login.md` の認証方式が不整合。
- Gate 0 で必須解消。

2. API仕様の未確定
- URL、HTTPメソッド、入出力、エラー仕様が画面仕様で未確定。
- 要確認。

3. 通知先テーブル参照の整合
- `tenant_notify_routes`/`tenant_notify_targets` の参照先定義に不足がある可能性。
- DDL整合を要確認。

4. 顧客詳細の編集範囲
- 一般利用者の編集可能範囲が未確定。
- 要確認。

5. 監査ログ記録方針
- どの更新を必須記録とするか未確定。
- 要確認。

---

## 8. 最初に着手する1フェーズ（提案）

提案:

- まず Gate 0（ログイン仕様差分の必須解消）に着手する。

理由:

- 認証方式が未確定のまま Phase 1 を進めると、認証・セッション基盤の再実装リスクが高い。
- Gate 0 完了後に Phase 1 を開始することで、以降フェーズの実装前提を固定できる。

Gate 0 完了後の次フェーズ:

- Phase 1（認証・セッション・テナント基盤）

---

## 9. Phase 2 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
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
- `xs000001_te001.t_renewal_case`: canonical DDL と列名不一致（例: `maturity_date` / `case_status` / `remark` ではなく `renewal_due_date` / `status` / `note`）

投入結果（実測）:

- テスト投入プレフィックス: `PH2AT_`
- `xs000001_te001.m_customer` に 2 件挿入（`PH2AT_`）
- それ以外の Phase 2 対象テーブルは、テーブル不存在または列不一致により投入不可

画面挙動（認証済み）:

- `renewal/list`: HTTP 200 で表示されるが、画面に「満期一覧の取得に失敗しました。接続設定を確認してください。」を表示
- `renewal/detail&id=1`: `renewal/list` へ 302 リダイレクト
- `renewal/update`（CSRFなし POST）: `renewal/detail&id=1` へ 302 リダイレクト

受入判定（事実ベース）:

- 確認済み
  - 認証済みアクセス時のルーティング応答（HTTP 200/302）は取得済み
  - 一覧画面のエラー表示と空状態表示は確認済み
- 不具合
  - Phase 2 実装が参照するテーブル/カラムと、実DBスキーマが不一致であるため、一覧取得と詳細取得が正常系で成立しない
- 未実装（実DB起点）
  - canonical DDL 前提の `t_contract`, `t_activity`, `m_customer_contact` が未配備
- 未確認
  - 2テナント分の同等データ投入に基づく一覧/詳細/更新の正常系
  - 一覧検索の正常系絞り込み
  - 一覧→詳細遷移の正常系
  - 更新成功後の再表示反映
  - 他テナント detail 直打ち防止、update 直接叩き防止（正常系データ前提の厳密確認）

補足:

- 本結果は「実装追加なし」での受入確認実施結果であり、推測を含まない。
- 受入確認を完了させるには、まず `config/ddl/tenant/` と実DBスキーマの整合を取る必要がある。

---

## 10. Phase 2 受入確認（再実施: 実DB整合後） 2026-03-29

【投入したテストデータ】

- 対象テーブル
  - `m_customer`
  - `m_customer_contact`
  - `t_contract`
  - `t_renewal_case`
  - `t_activity`
  - `t_case_comment`
  - `t_audit_event`
  - `t_audit_event_detail`
- 投入件数（再実施本体）
  - tenantA(TE001):
    - `m_customer` 3
    - `m_customer_contact` 3
    - `t_contract` 3
    - `t_renewal_case` 3
    - `t_activity` 1
    - `t_case_comment` 1
    - `t_audit_event` 1
    - `t_audit_event_detail` 1
  - tenantB(TE002):
    - `m_customer` 3
    - `m_customer_contact` 3
    - `t_contract` 3
    - `t_renewal_case` 3
    - `t_activity` 1
    - `t_case_comment` 1
    - `t_audit_event` 1
    - `t_audit_event_detail` 1
- 追加投入（テナント分離の直打ち検証用）
  - tenantB(TE002):
    - `m_customer` 1
    - `t_contract` 1
    - `t_renewal_case` 1
- テナントごとの件数（再実施合計）
  - tenantA(TE001): 上記「再実施本体」のとおり
  - tenantB(TE002): 再実施本体 + 追加投入（`m_customer`+1, `t_contract`+1, `t_renewal_case`+1）
- 確認に使った代表レコード
  - プレフィックス `PH2AT2_20260329_103100`
  - tenantA policy_no:
    - `PH2AT2_20260329_103100_TENANT_A_POL_A`
    - `PH2AT2_20260329_103100_TENANT_A_POL_B`
    - `PH2AT2_20260329_103100_TENANT_A_POL_C`
  - tenantB policy_no:
    - `PH2AT2_20260329_103100_TENANT_B_POL_A`
    - `PH2AT2_20260329_103100_TENANT_B_POL_B`
    - `PH2AT2_20260329_103100_TENANT_B_POL_C`
  - テナント分離直打ち検証用 `tenantB_only_renewal_id = 4`（プレフィックス `PH2AT2B_20260329_103134`）

【確認できたこと】

- 画面表示
  - `renewal/list` は認証済みで HTTP 200 かつDB取得エラー表示なし
  - `renewal/detail` は認証済みで HTTP 200（対象案件）
  - 詳細画面に契約情報・満期情報・顧客サマリ表示を確認（`証券番号:`, `満期日:`, `顧客名:`）
- 検索
  - `policy_no` 指定で対象案件のみ表示
  - 別案件が除外されることを確認
  - 該当なし条件で「該当データはありません。」表示
- 一覧→詳細遷移
  - 一覧HTML上に `route=renewal/detail&id=` の詳細リンク存在を確認
  - 詳細リンク先で対象案件表示を確認
- 更新
  - `renewal/update` 実行後、302で `renewal/detail` へ戻る
  - 詳細再表示で `remark` 更新値反映を確認
  - 詳細再表示で `case_status=quoted` の選択反映を確認
- 履歴系表示
  - 活動履歴あり案件で表示される
  - 活動履歴なし案件で「活動履歴はありません。」表示
  - コメントあり案件で表示される
  - コメントなし案件で「コメントはありません。」表示
  - 監査ログあり案件で表示される
  - 監査ログなし案件で「監査ログはありません。」表示
- テナント分離
  - tenantBデータがtenantAの一覧に表示されない
  - 他テナント案件ID直打ち（`tenantB_only_renewal_id=4`）で `renewal/list` へリダイレクト
  - 他テナント案件への `renewal/update` 直接POSTはリダイレクトし、tenantB側レコードは未更新
- エラー時挙動
  - 不正 `case_status` で保存要求時、更新されない
  - 空 `case_status` で保存要求時、更新されない
  - 存在しない `id` の詳細アクセスは `renewal/list` へリダイレクト

【不具合】

- 今回の再実施で再現した不具合はなし

【未実装】

- `t_audit_event_detail` は投入済みだが、画面表示は `t_audit_event` の一覧のみで詳細表示機能は未実装

補足（2026-03-30 追記）:

- 満期詳細のコメント登録（追記方式）は `renewal/comment` と詳細画面の「新規コメント」入力で実装済み

【未確認】

- なし（本再実施で要求された確認項目は実測完了）

---

## 11. Phase 2 受入判定（短縮版・クローズ用）

受入範囲:

- 本判定は Phase 2 最小実装範囲（`renewal/list`, `renewal/detail`, `renewal/update`）のみに限定する。
- 下記以外の項目は判定対象へ追加しない。

判定:

- 確認済み
  - 満期一覧表示
  - 検索
  - 一覧 → 詳細遷移
  - 詳細表示
  - 更新反映
  - 履歴あり/なし表示
  - 対象なし時挙動
  - テナント分離
  - detail直打ち遮断
  - update直接POST未更新
- 未実装
  - `t_audit_event_detail` の画面表示
- 未確認
  - なし

補足（判定対象外）:

- より細かい業務バリデーション
- UIの絞り込み改善

上記は Phase 2 の受入判定対象ではなく、次フェーズ検討事項として扱う。

Phase 2 判定結論:

- Phase 2（最小実装範囲）は受入完了としてクローズする。

---

## 12. Phase 3 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
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
- tenantA(TE001):
  - `m_customer` 3（主検証1 / 連絡先ゼロ1 / 契約ゼロ1）
  - `m_customer_contact` 2（主検証顧客へ2件）
  - `t_contract` 3（主検証顧客へ2件、連絡先ゼロ顧客へ1件）
  - `t_renewal_case` 4（主検証契約Aへ2件、契約Bへ1件、連絡先ゼロ顧客契約へ1件）
  - `t_activity` 1（主検証顧客へ1件）
- tenantB(TE002):
  - `m_customer` 2以上（他テナント非表示/直打ち遮断用。tenantA最大ID超えのIDを1件確保）
  - `t_contract` 1
  - `t_renewal_case` 1
- 代表ID:
  - tenantA main customer id = 4
  - tenantA no-contact customer id = 5
  - tenantA no-contract customer id = 6
  - tenantA renewal ids = older:4, latest:5, a2:6, no-contact:7
  - tenantB direct-check customer id = 7（tenantAには未存在）

【確認できたこと】

- 顧客一覧検索
  - `customer_name` 検索で対象のみ抽出
  - `phone` 検索で対象のみ抽出
  - `email` 検索で対象のみ抽出
  - `status=inactive` で inactive 顧客のみ抽出（active 顧客除外）
- 保有契約件数の正しさ
  - 主検証顧客（契約2件）が一覧上で `2` 件表示
- 顧客詳細の基本情報
  - 顧客名・メール・画面タイトル（顧客詳細）を確認
- 連絡先
  - 複数件: 2件表示を確認
  - 0件: 「連絡先はありません。」表示を確認
- 保有契約
  - 複数件: 2契約表示を確認
  - 0件: 「保有契約はありません。」表示を確認
- 活動履歴
  - あり: 投入した活動1件の表示を確認
  - なし: 「活動履歴はありません。」表示を確認
- 画面遷移
  - 顧客詳細 → 満期詳細リンクの存在を確認
  - 満期詳細 → 顧客詳細リンクの存在を確認
- テナント分離
  - tenantB 顧客は tenantA の顧客一覧に非表示
  - tenantB 専用顧客IDを tenantA で直打ちすると `customer/list` へ 302 リダイレクト
- 0件系の安全性
  - 一覧検索該当なしで「該当データはありません。」表示

【「最新の満期案件ID」選定基準と実測】

- 実装基準（`src/Domain/Customer/CustomerRepository.php`）:
  - 契約ごとに `t_renewal_case` を `ORDER BY maturity_date DESC, id DESC` で取得し `LIMIT 1` を採用
  - すなわち「満期日が最も新しい案件」を優先し、同一満期日の場合のみ `id` 降順で決定
- 実測:
  - 同一契約に対し `older(id=4, maturity=+30d)` と `latest(id=5, maturity=+120d)` を投入
  - 顧客詳細の契約行リンクは `id=5` を参照し、`id=4` は採用されないことを確認

【不具合】

- 今回の受入確認で再現した不具合はなし

【未実装】

- 顧客更新（`docs/screens/customer-detail.md` の更新系）は未実装のまま

【未確認】

- なし

【今後の仕様論点】

- 最新満期案件の選定基準を `maturity_date DESC, id DESC` 以外へ変更する必要があるか
- 現実装は「満期日優先」であり、`case_status` 優先ロジックは実装していない
- 優先順位を変更する場合は、`CustomerRepository::findContracts()` の選定条件を要件化して更新する

---

## 13. Phase 3 受入判定（短縮版・クローズ用）

受入範囲:

- 本判定は Phase 3 最小実装範囲（`customer/list`, `customer/detail`, 顧客↔満期の相互導線）に限定する。

判定:

- 確認済み
  - 顧客一覧表示
  - 検索（`customer_name` / `phone` / `email` / `status`）
  - 一覧の保有契約件数表示
  - 一覧 → 詳細遷移
  - 顧客詳細基本情報表示
  - 連絡先（複数件 / 0件）表示
  - 保有契約（複数件 / 0件）表示
  - 活動履歴（あり / なし）表示
  - 顧客詳細 → 満期詳細遷移
  - 満期詳細 → 顧客詳細遷移
  - 他テナント顧客の非表示
  - 他テナント顧客ID直打ち遮断
  - 最新満期案件ID選定（`maturity_date DESC, id DESC`）
- 未実装
  - 顧客更新
- 未確認
  - なし

今後の仕様論点:

- 最新満期案件の選定基準を `maturity_date DESC, id DESC` 以外へ変更する必要があるか

Phase 3 判定結論:

- Phase 3（最小実装範囲）は受入完了としてクローズする。

---

## 14. Phase 4A 実装着手（一覧/検索/登録/編集/削除） 2026-03-29

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
- `config/ddl/tenant/t_sales_performance.sql`

変更方針:

- Phase 4A の対象を「実績管理一覧 + 検索 + 登録 + 編集 + 削除」に限定する
- `docs/screens/sales-performance-list.md` 記載の CSV 取込は Phase 4B で実装し、本着手では追加しない
- 既存導線方針に合わせ、ダッシュボードから `sales/list` へ遷移可能にする

実装内容:

- 追加
  - `src/Domain/Sales/SalesPerformanceRepository.php`
    - 一覧検索（実績計上日From/To、契約者名、証券番号、種目、精算月）
    - 単票取得、登録、更新、論理削除
    - 入力フォーム用マスタ取得（顧客、契約、満期案件）
  - `src/Controller/SalesPerformanceController.php`
    - `list`, `create`, `update`, `delete`
    - CSRF検証、基本バリデーション、フラッシュメッセージ
  - `src/Presentation/SalesPerformanceListView.php`
    - 検索フォーム
    - 一覧テーブル
    - 実績追加フォーム
    - 実績編集フォーム（`edit_id` 指定時）
    - 削除ボタン（確認ダイアログ）
- 更新
  - `src/bootstrap.php`
    - ルート追加
      - `GET sales/list`
      - `POST sales/create`
      - `POST sales/update`
      - `POST sales/delete`
  - `src/Controller/DashboardController.php`
    - `DashboardView` へ `sales/list` URL を引き渡し
  - `src/Presentation/DashboardView.php`
    - 「実績管理一覧」カードを実リンク化

実動確認（スモーク）:

- 実施スクリプト: `tmp/phase4a_smoke_check.php`
- 確認結果
  - `dashboard` に `sales/list` 導線表示
  - `sales/list` 表示（HTTP 200）
  - 登録（POST）成功後のリダイレクト
  - 検索結果で対象表示
  - 編集（POST）反映
  - 削除（POST）で論理削除反映（`is_deleted=1`）
  - 削除後に一覧非表示

現時点の扱い:

- 実装追加: 良い（Phase 4A 範囲内）
- 構文: OK
- 最小疎通: OK
- 受入: 未実施（Phase 4A は Phase 2/3 と同粒度の受入確認を別途実施）

未実装（Phase 4A範囲外）:

- CSV取込（Phase 4B）

---

## 15. Phase 4A 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
- `config/ddl/tenant/t_sales_performance.sql`

実施スクリプト:

- `tmp/phase4a_acceptance_check.php`

【投入したテストデータ】

- プレフィックス: `PH4AT_20260329_114430`
- tenantA(TE001)
  - `m_customer` 2
  - `t_contract` 2
  - `t_renewal_case` 2
  - `t_sales_performance` 2（初期）+ 1（create検証）
- tenantB(TE002)
  - `m_customer` 1
  - `t_contract` 1
  - `t_renewal_case` 1
  - `t_sales_performance` 1 + 直打ち検証用のID調整分

【確認できたこと】

- 一覧表示
  - `sales/list` は認証済みで HTTP 200
- 検索
  - `performance_date From/To` で対象抽出
  - 契約者名（実装パラメータ: `customer_name`）で対象抽出
  - `policy_no` で対象抽出
  - `product_type` で対象抽出
  - `settlement_month` で対象抽出
- 登録/更新/削除
  - 登録（create）後、DB件数が +1
  - 編集（update）後、`remark` / `performance_type` / `product_type` 反映
  - 削除（delete）後、`is_deleted=1` の論理削除反映
  - 削除後は一覧に非表示
- 0件系
  - 該当なし検索で「該当データはありません。」表示
- テナント分離
  - tenantB実績は tenantA一覧に非表示
  - tenantB専用IDでの `update` 直叩きは tenantBレコードに反映されない
  - tenantB専用IDでの `delete` 直叩きは tenantBレコードに反映されない
- 異常系/安全性
  - 不正値（不正日付、不正実績区分、負数保険料）create は保存されない
  - CSRFなし create / update / delete はいずれも処理されない

【不具合】

- 今回の受入確認で再現した不具合はなし

【未実装】

- CSV取込（Phase 4B）

【未確認】

- なし

---

## 16. Phase 4A 受入判定（短縮版・クローズ用）

受入範囲:

- 本判定は Phase 4A 最小実装範囲（`sales/list`, `sales/create`, `sales/update`, `sales/delete`）に限定する。

判定:

- 確認済み
  - 実績一覧表示
  - 検索（`performance_date_from/to`, `customer_name`, `policy_no`, `product_type`, `settlement_month`）
  - 登録反映
  - 編集反映
  - 論理削除反映と一覧非表示
  - 0件検索時表示
  - 他テナント実績の非表示
  - 他テナントID直叩き update / delete の未反映
  - 不正値保存防止
  - CSRFなし create / update / delete の拒否
- 未実装
  - CSV取込（Phase 4B）
- 未確認
  - なし

Phase 4A 判定結論:

- Phase 4A（最小実装範囲）は受入完了としてクローズする。

---

## 17. Phase 4B 実装着手（CSV取込/エラー集約/取込結果表示） 2026-03-29

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
- `docs/01_canonical-schema.md`
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
- 必須ヘッダ
  - `receipt_no`
  - `policy_no`
  - `customer_name`
  - `maturity_date`
  - `performance_date`
  - `performance_type`
  - `insurance_category`
  - `product_type`
  - `premium_amount`
  - `settlement_month`
  - `remark`
- 既存行更新キーは `receipt_no` とする
- 契約紐付けは `policy_no` 一致で行う
- 満期案件紐付けは `policy_no` で見つかった契約に対する `maturity_date` 一致で行う

実装内容:

- 追加
  - `src/Domain/Sales/SalesCsvImportService.php`
    - CSV読込
    - 文字コード判定/UTF-8正規化
    - ヘッダ検証
    - 行単位 insert/update/error 判定
    - batch/row 取込履歴記録
- 更新
  - `src/Domain/Sales/SalesPerformanceRepository.php`
    - 契約検索
    - 満期案件検索
    - `receipt_no` による既存行検索
    - batch/row 取込履歴の登録・取得
  - `src/Controller/SalesPerformanceController.php`
    - `import()` を追加
    - `list()` に取込結果表示読み込みを追加
  - `src/Presentation/SalesPerformanceListView.php`
    - CSV取込フォーム追加
    - 取込結果サマリ追加
    - 行別結果テーブル追加
  - `src/bootstrap.php`
    - `POST sales/import` 追加

現時点の扱い:

- 実装追加: 良い（Phase 4B 範囲内）
- 構文: OK
- 最小疎通: OK
- 受入: 実施済み（後述）

今後の仕様論点:

- `receipt_no` 以外を更新キーに採用する必要があるか
- 契約紐付け/満期案件紐付けの優先ルール詳細

---

## 18. Phase 4B 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/screens/sales-performance-list.md`
- `docs/03_screen-map.md`
- `docs/02_navigation-policy.md`
- `docs/01_canonical-schema.md`
- `config/ddl/tenant/t_sjnet_import_batch.sql`
- `config/ddl/tenant/t_sjnet_import_row.sql`
- `config/ddl/tenant/t_sales_performance.sql`

実施スクリプト:

- `tmp/phase4b_acceptance_check.php`

【投入したテストデータ】

- プレフィックス: `PH4BT_20260329_122533`
- tenantA(TE001)
  - `m_customer` 2
  - `t_contract` 2
  - `t_renewal_case` 2
  - `t_sales_performance` 1（既存更新対象）
  - 正常CSV 1本（2行: update 1 / insert 1）
  - 部分失敗CSV 1本（2行: insert 1 / error 1）
- tenantB(TE002)
  - `m_customer` 1
  - `t_contract` 1
  - `t_renewal_case` 1
  - 他テナント非混在確認用に件数監視

【確認できたこと】

- 正常CSV取込
  - 一括登録できる
  - `receipt_no` 一致の既存行更新が意図どおり動く
  - 取込後に一覧へ反映される
  - batch集計は `success`, `insert=1`, `update=1`, `error=0`
- 部分失敗CSV取込
  - 成功行と失敗行を分離できる
  - 失敗行だけを `t_sjnet_import_row` で識別できる
  - 結果画面に batch状態と行別エラーが表示され、読める
  - batch集計は `partial`, `insert=1`, `error=1`
- テナント分離
  - tenantA での CSV取込は tenantB 実績に混入しない
- Phase 4A 退行確認
  - CSV取込後も create が成立
  - CSV取込後も update が成立
  - CSV取込後も delete が成立

【不具合】

- 今回の受入確認で再現した不具合はなし

【未実装】

- なし（Phase 4B 最小実装範囲）

【未確認】

- なし

【今後の仕様論点】

- `receipt_no` 以外を更新キーに採用する必要があるか
- 契約紐付け/満期案件紐付けの優先ルール詳細

---

## 19. Phase 4B 受入判定（短縮版・クローズ用）

受入範囲:

- 本判定は Phase 4B 最小実装範囲（`sales/import`, エラー集約, 取込結果表示, Phase 4A CRUD退行なし）に限定する。

判定:

- 確認済み
  - 正常CSVでの一括登録
  - 既存行更新
  - 失敗行識別
  - エラー集約結果表示
  - 取込後一覧反映
  - 他テナント非混在
  - Phase 4A CRUD退行なし
- 未実装
  - なし
- 未確認
  - なし

今後の仕様論点:

- `receipt_no` 以外を更新キーに採用する必要があるか
- 契約紐付け/満期案件紐付けの優先ルール詳細

補足:

- CSV仕様の正式定義は `docs/screens/sales-performance-csv-import.md` に反映済み

Phase 4B 判定結論:

- Phase 4B（最小実装範囲）は受入完了としてクローズする。

---

## 20. Phase 5 受入確認（実動）結果 2026-03-29

参照した仕様/DDL:

- `docs/02_navigation-policy.md`
- `docs/03_screen-map.md`
- `docs/screens/accident-case-list.md`
- `docs/screens/accident-case-detail.md`
- `docs/screens/tenant-settings.md`
- `config/ddl/tenant/t_accident_case.sql`
- `config/ddl/tenant/t_case_comment.sql`
- `config/ddl/tenant/t_audit_event.sql`
- `config/ddl/tenant/m_renewal_reminder_phase.sql`
- `config/ddl/common/tenant_notify_targets.sql`
- `config/ddl/common/tenant_notify_routes.sql`

実施スクリプト:

- `tmp/phase5_acceptance_check.php`

【投入したテストデータ】

- プレフィックス: `PH5AT_20260329_143602`
- tenantA(TE001)
  - `m_customer` 1
  - `t_contract` 1
  - `t_accident_case` 1
  - `t_audit_event` 1（事故案件監査ログ表示確認用）
  - `t_case_comment` 1（詳細画面コメント登録で追加）
  - `m_renewal_reminder_phase` は空の場合のみ 1件をseed
  - `tenant_notify_targets` / `tenant_notify_routes` は通知設定保存で upsert
- tenantB(TE002)
  - `m_customer` 1
  - `t_accident_case` 1（他テナント直打ち遮断確認用）
  - common 側通知設定件数は before/after 比較で非影響確認

【確認できたこと】

- 管理者向け設定導線の表示制御
  - admin セッションでは dashboard に `tenant/settings` が表示される
  - member セッションでは同導線が表示されない
- 一般ユーザー遮断
  - member で `accident/list` 直打ちは `dashboard` へ 302
  - member で `tenant/settings` 直打ちは `dashboard` へ 302
  - member で `tenant/settings/notify` 直接POSTも `dashboard` へ 302
- 事故案件（一覧→詳細）
  - admin で `accident/list` は 200、対象案件表示、詳細リンク表示
  - `accident/detail` は 200 で対象案件を表示
  - 詳細画面でコメント欄と監査ログ欄が表示され、seedした監査ノートが表示される
- 事故案件更新/コメント
  - 詳細画面のCSRFを用いた `accident/update` は詳細へ302し、DB反映（`status`, `priority`, `insurer_claim_no`, `remark`）を確認
  - 詳細画面のCSRFを用いた `accident/comment` は詳細へ302し、`t_case_comment` 反映を確認
- テナント設定
  - `tenant/settings` は 200、通知設定タブ表示
  - `tenant/settings/notify` はCSRF検証を通して保存でき、own tenant の `tenant_notify_targets/routes` に反映
  - `tenant/settings?tab=master` でフェーズフォーム表示
  - `tenant/settings/phase` はCSRF検証を通して保存でき、own tenant の `m_renewal_reminder_phase` 反映
- 他テナント遮断
  - 事故案件詳細の他テナントID相当直打ちは `accident/list` へ 302
  - 通知設定更新後も tenantB の `tenant_notify_routes` 件数は before/after 同一

【不具合】

- 今回の受入確認で再現した不具合はなし

【未実装】

- なし（Phase 5 最小実装範囲）

【未確認】

- なし

---

## 21. Phase 5 受入判定（短縮版・クローズ用）

受入範囲:

- 本判定は Phase 5 最小実装範囲（管理者向け設定導線の表示制御、事故案件一覧/詳細、事故更新、コメント登録、監査表示、テナント設定更新）に限定する。

判定:

- 確認済み
  - 一般ユーザーに管理者向け設定導線（`tenant/settings`）が表示されない
  - 事故案件導線は全ログインユーザーに表示され、テナント設定導線は管理者にのみ表示される
  - 一般ユーザーの `accident/*`, `tenant/settings*` 直打ち/直接POSTが遮断される
  - 事故案件は一覧→詳細の基本導線で動作する
  - 事故案件詳細でコメント登録と監査ログ表示が文脈付きで動作する
  - テナント設定（通知/マスタ）は所属テナント文脈で更新される
  - 他テナント設定への影響がない
- 未実装
  - なし
- 未確認
  - なし

Phase 5 判定結論:

- Phase 5（最小実装範囲）は受入完了としてクローズする。

---

注記:

- `22` から `26` は 2026-03-29 時点の実装進捗ログである。
- Phase 6 の最終受入範囲と判定は `27` から `30` を正とする。

## 22. Phase 6 着手（通知実行・運用強化） 2026-03-29

参照した仕様/DDL:

- `docs/05_implementation-plan.md`（Phase 6 方針）
- `config/ddl/tenant/t_notification_run.sql`
- `config/ddl/tenant/t_notification_delivery.sql`
- `config/ddl/tenant/t_renewal_case.sql`
- `config/ddl/tenant/m_renewal_reminder_phase.sql`
- `config/ddl/common/tenant_notify_targets.sql`
- `config/ddl/common/tenant_notify_routes.sql`

実装内容（Phase 6 初手）:

- 追加
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`
    - `t_notification_run` 作成/完了更新
    - `m_renewal_reminder_phase` 取得
    - `t_renewal_case` 対象抽出（残日数フェーズ一致）
    - `t_notification_delivery` への success/skipped/failed 記録（INSERT IGNORE）
  - `src/Domain/Notification/RenewalNotificationBatchService.php`
    - renewal 通知実行本体
    - 処理件数集計（processed/success/skip/fail）
    - 冪等再実行時の skip 集計
  - `tools/batch/run_renewal_notification.php`
    - CLI 実行入口
    - `--date`, `--tenant`, `--executed-by` を受け取り
    - common DB から通知ルート有効判定を取得し tenant 実行へ反映
  - `tools/batch/README.md`
    - 実行方法と運用メモ

実動確認（実測）:

- 実行1:
  - `php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1`
  - 結果: `result=success`, `processed_count=10`, `success_count=10`, `skip_count=0`, `fail_count=0`
- 実行2（同条件 再実行）:
  - `php tools/batch/run_renewal_notification.php --date=2026-03-29 --tenant=TE001 --executed-by=1`
  - 結果: `result=success`, `processed_count=10`, `success_count=0`, `skip_count=10`, `fail_count=0`
  - 判定: delivery の一意制約を利用した冪等動作を確認

確認できたこと:

- `t_notification_run` に実行単位が記録される
- `t_notification_delivery` に個別通知実績が記録される
- 同日同対象の再実行は skip 集計になり、重複配信レコードを増やさない
- common/tenant の責務分離
  - ルート判定: common (`tenant_notify_routes`, `tenant_notify_targets`)
  - 対象抽出/実績記録: tenant (`t_renewal_case`, `m_renewal_reminder_phase`, `t_notification_*`)

未実装（当時時点の Phase 6 継続項目）:

- `accident` 通知タイプのバッチ実装
- 配信失敗時の再試行ポリシー（backoff / 最大回数）
- cron 定期実行と運用監視ログの定義

次ステップ（Phase 6 本体）:

- renewal/accident の 2 系統を同一運用で回せるランナーへ拡張
- 失敗再実行フローと運用手順を docs に固定

---

## 23. Phase 6 拡張（accident追加 + retry方針） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/AccidentNotificationBatchRepository.php`
    - `t_notification_run`（accident）作成/完了更新
    - `t_accident_reminder_rule` + `t_accident_reminder_rule_weekday` 対象抽出
    - `t_notification_delivery` success/skipped/failed 記録
    - failed delivery 取得 / retry 更新
  - `src/Domain/Notification/AccidentNotificationBatchService.php`
    - accident 通知実行本体
    - 実行日・曜日・interval週・期間・last_notified_on による対象判定
    - retry 実行（`retry_failed_run_id` 指定時）
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`
    - failed delivery 取得
    - retry 更新
  - `src/Domain/Notification/RenewalNotificationBatchService.php`
    - `retry_failed_run_id` 指定時の再実行分岐
  - `tools/batch/run_renewal_notification.php`
    - `--type=renewal|accident|all`
    - `--retry-failed-run-id=<runId>`
    - summary 出力へ `notification_type` を統一追加
  - `tools/batch/README.md`
    - type/retry 実行例を追記
  - `tools/acceptance/acceptance-suites.json`
    - full suite に Phase 6 チェックを追加
  - `tools/acceptance/suites/full/phase6_notification_acceptance_check.php`
    - all 実行と renewal/accident retry 実行の受入チェック

実動確認（実測）:

- 単体スモーク
  - `--type=all` 実行で renewal + accident の2結果が返る
  - `--type=renewal --retry-failed-run-id=<runId>` 実行で retry 参照IDが保持される
  - `--type=accident --retry-failed-run-id=<runId>` 実行で retry 参照IDが保持される
- 受入スクリプト
  - `tools/acceptance/suites/full/phase6_notification_acceptance_check.php` は `all_passed=true`
- full suite 統合
  - `php tools/acceptance/run-suite.php --suite=full` は `all_passed=true`

確認できたこと:

- renewal/accident を同一ランナーで運用できる
- retry 指定時に失敗再処理フローへ切り替わる
- retry 実行結果が `retry_failed_run_id` でトレースできる
- full suite に Phase 6 通知系チェックを常設できた

未実装（当時時点の Phase 6 継続項目）:

- backoff 間隔や最大再試行回数のポリシー固定
- cron 運用時の通知失敗アラート設計

---

## 24. Phase 6 継続（retry運用ポリシー固定） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/NotificationRetryPolicy.php`
    - retry 最大試行回数
    - 最短再試行待機分
    - failed `error_message` からの attempt 番号解釈
    - backoff / max attempts 判定
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`
    - failed delivery 取得時に `notified_at`, `created_at`, `error_message` を返却
    - failed 記録時に `notified_at=NOW()` を保持
  - `src/Domain/Notification/AccidentNotificationBatchRepository.php`
    - failed delivery 取得時に `notified_at`, `created_at`, `error_message` を返却
    - failed 記録時に `notified_at=NOW()` を保持
  - `src/Domain/Notification/RenewalNotificationBatchService.php`
    - retry 時に backoff / max attempts 判定を適用
    - failed `error_message` を `[attempt:n] ...` 形式で保持
    - summary に `retry_policy` を返却
  - `src/Domain/Notification/AccidentNotificationBatchService.php`
    - renewal と同等の retry ポリシー適用
    - summary に `retry_policy` を返却
  - `tools/batch/run_renewal_notification.php`
    - `--retry-max-attempts=<n>`
    - `--retry-minutes=<n>`
    - summary ルートに retry policy を返却
  - `tools/batch/README.md`
    - retry policy 実行例と運用メモ追記
  - `tools/acceptance/suites/full/phase6_notification_acceptance_check.php`
    - renewal: retry backoff による skip
    - accident: max attempts 到達による skip
    - 制約解除後の retry success

実動確認（実測）:

- `phase6_notification_acceptance_check.php`
  - renewal で `--retry-minutes=60` 指定時、対象 failed delivery は retryされず `skip_count=1`
  - renewal で `--retry-minutes=0` に戻すと retry success へ遷移
  - accident で `--retry-max-attempts=3` 指定時、attempt:3 の failed delivery は retryされず `skip_count=1`
  - accident で `--retry-max-attempts=4` に緩和すると retry success へ遷移

確認できたこと:

- retry 実行の上限回数を CLI で固定できる
- retry 間隔の backoff を CLI で固定できる
- delivery 側に「最後に試行した時刻」と attempt 情報を残して判定できる
- 制約により retryしなかった対象は既存 failed 状態を維持し、run 集計上は `skip` として扱える

未実装（当時時点の Phase 6 継続項目）:

- XServer cron 実運用でのジョブ定義固定
- 通知失敗アラートと運用監視手順の明文化

---

## 25. Phase 6 継続（Webhook実送信反映） 2026-03-29

実装内容（今回追加）:

- 追加
  - `src/Domain/Notification/WebhookNotificationSender.php`
    - `provider_type=lineworks` の webhook 送信処理
    - HTTP 2xx 判定
    - 非2xx/例外を RuntimeException 化
- 更新
  - `src/Domain/Notification/RenewalNotificationBatchService.php`
    - route 有効時に webhook 実送信を実行
    - 送信成功時のみ `success` 記録
    - 送信失敗時は `failed` 記録
  - `src/Domain/Notification/AccidentNotificationBatchService.php`
    - renewal と同様の webhook 実送信フローを適用
  - `tools/batch/run_renewal_notification.php`
    - route 取得で `provider_type`, `destination_name`, `webhook_url` をサービスへ受け渡し
  - `tools/batch/README.md`
    - webhook 実送信時の success/failed 判定を明記

確認できたこと:

- DB 記録のみで success 扱いする挙動を排除し、HTTP 2xx を success 条件に変更した
- route 無効時は従来どおり skipped を維持
- idempotency（再実行 skip）の制御は既存の delivery 一意制約ベースを維持

## 26. Phase 6 継続（LINE WORKS本文業務化） 2026-03-29

実装内容（今回追加）:

- 更新
  - `src/AppConfig.php`
    - `APP_PUBLIC_URL` を追加し、LINE WORKS ボタンURLの基底値として利用
    - `APP_PUBLIC_URL` 未設定時は従来の `APP_URL` をfallback するが、localhost系は通知送信時に reject
  - `src/Domain/Notification/WebhookNotificationSender.php`
    - LINE WORKS Incoming Webhook の payload を `title` / `body.text` / `button.label` / `button.url` で検証
    - `body.text` 必須とボタンURL妥当性を sender 側で担保
  - `src/Domain/Notification/lineworks_payload_helpers.php`
    - `build_lineworks_absolute_url`
    - `format_lineworks_short_date`
    - `build_lineworks_renewal_alert_body_text`
    - `build_lineworks_accident_reminder_body_text`
    - `build_lineworks_renewal_alert_payload`
    - `build_lineworks_accident_reminder_payload`
  - `src/Domain/Notification/RenewalNotificationBatchRepository.php`
    - 契約者名・証券番号付きで 28日前 / 14日前の対象抽出を取得
    - retry 用に満期日・契約者名・証券番号・残日数を取得
  - `src/Domain/Notification/RenewalNotificationBatchService.php`
    - 満期通知を `【満期案件通知（早期）】` と `【満期案件通知（直前）】` の固定2種に整理
    - 1案件1通ではなく、通知種別ごとに対象件数と一覧をまとめて送信
    - 本文に action prompt / 対象件数 / 対象満期案件一覧 / 省略時 `ほかN件` を追加
    - ボタンは `一覧` ラベルで満期一覧へ遷移
    - 本文から internal id / phase番号 / 英語疎通文面を除去
  - `src/Domain/Notification/AccidentNotificationBatchRepository.php`
    - 事故日・契約者名を含む通知対象取得に変更
  - `src/Domain/Notification/AccidentNotificationBatchService.php`
    - 事故通知を `【事故対応リマインド】` 1通に集約し、対象件数と一覧を送信
    - ボタンは `一覧` ラベルで事故案件一覧へ遷移
  - `tools/batch/README.md`
    - 新payload仕様、APP_PUBLIC_URL ルール、28日前/14日前固定通知を追記

確認できたこと:

- renewal は 28日前と14日前で別メッセージ送信になった
- accident は既存ルール一致案件を 1メッセージに集約して送信できた
- タイトル、本文、ボタンの payload 構造を helper と sender で分離できた
- ボタンURLは `APP_PUBLIC_URL` ベースの絶対URLで生成される
- run / delivery 記録方式と idempotency は維持された

---

## 27. Phase 6 受入範囲の固定

Phase 6 の受入範囲は、通知バッチ機能の追加そのものではなく、定期実行、履歴確認、失敗時再実行の運用が再現可能な定義として固定されていることに限定する。

確認対象は以下とする。

- cron 実運用ジョブ定義
- 失敗検知条件とアラート導線
- 監視手順
- 一次確認手順
- 再実行手順
- Phase 6 受入判定（短縮版）の明記

本フェーズでは、監視ダッシュボード化、運用SOPの詳細化、障害一次切り分けの高度化までは受入範囲に含めない。これらは後続フェーズへ分離して扱う。

---

## 28. Phase 6 運用定義の固定

### 28-1. cron 実運用ジョブ定義

Phase 6 で本番運用に固定する cron ジョブは以下とする。

- 満期通知バッチ
  - 目的: 満期通知対象の抽出と配信
  - 実行頻度: 毎営業日 1 回
  - 実行時刻: 午前の業務開始前を基本とする
- 事故通知バッチ
  - 目的: 事故通知対象の抽出と配信
  - 実行頻度: 毎営業日 1 回
  - 実行時刻: 満期通知と分離した時刻を基本とする

実運用で固定するコマンドは、通知バッチ本体 `tools/batch/run_renewal_notification.php` を使用する。

例（パスは環境に合わせて置換）:

- 満期通知
  - `/usr/bin/php /home/your_account/insurance-agency/tools/batch/run_renewal_notification.php --date=$(date +\%F) --tenant=TE001 --executed-by=1 --type=renewal >> /home/your_account/logs/renewal_notification.log 2>&1`
- 事故通知
  - `/usr/bin/php /home/your_account/insurance-agency/tools/batch/run_renewal_notification.php --date=$(date +\%F) --tenant=TE001 --executed-by=1 --type=accident >> /home/your_account/logs/accident_notification.log 2>&1`

補足:

- 終了コード 0 を成功、0 以外を失敗として扱う
- 標準出力・標準エラーの保存先を固定する
- 多重実行を避ける前提（実行時刻分離、運用手順での二重起動防止）を明示する

定義時の必須条件は以下とする。

- 実行コマンドがリポジトリ内の正式バッチ実装を指していること
- 標準出力、標準エラー出力の保存先が明示されていること
- 多重実行を避ける前提が運用上説明できること
- 失敗時に検知可能な終了状態を残すこと

### 28-2. 通知失敗の検知条件

通知失敗は、少なくとも以下のいずれかに該当する場合と定義する。

- バッチプロセス自体が異常終了した場合
- 通知対象の抽出に失敗した場合
- 配信処理でエラーが返却された場合
- 実行履歴が `failed` または `partial` となった場合
- 送信対象件数に対して配信成功件数が不足した場合

`partial` は一部失敗として扱い、運用上は成功扱いにしない。確認対象とする。

### 28-3. アラート導線

通知失敗時のアラート導線は以下を固定とする。

- 誰に通知するか
  - 管理者ユーザー
  - 運用確認担当者
- 何で通知するか
  - 既定の運用連絡手段 1 系統
  - 必要に応じて補助手段 1 系統
- どの条件で通知するか
  - バッチ異常終了
  - 実行履歴 `failed`
  - 実行履歴 `partial`
  - 配信件数不整合

アラート文面には最低限以下を含める。

- バッチ種別
- 実行日時
- 実行結果
- 失敗件数または不整合件数
- 確認対象ログまたは履歴の参照先

### 28-4. 監視手順

毎日の監視手順は以下とする。

1. 対象バッチが当日分として起動していることを確認する
2. `t_notification_run` に当日実行履歴が記録されていることを確認する
3. 実行結果が `success` であることを確認する
4. `partial` または `failed` がある場合は対象 run を特定する
5. `t_notification_delivery` で失敗対象の有無と件数を確認する
6. 必要に応じてログ出力を確認する
7. 再実行が必要かを判断する

監視は「実行されたか」だけで終わらせず、「結果が正常か」まで確認することを前提とする。

### 28-5. 一次確認手順

失敗時の一次確認は以下の順で実施する。

1. 対象バッチ種別を特定する
2. 実行日時と対象 run を特定する
3. `t_notification_run` の status、件数、エラーメッセージを確認する
4. `t_notification_delivery` で失敗対象、対象案件、配信結果を確認する
5. アプリケーションログまたは cron ログで異常終了有無を確認する
6. 設定不備か、データ不備か、外部配信失敗かを切り分ける
7. 再実行可否を判断する

一次確認の目的は、その場で深掘りしすぎることではなく、再実行可能な障害か、設定修正が必要な障害かを切り分けることにある。

### 28-6. 再実行手順

再実行は、原因が解消済みであり、重複送信防止条件を満たせる場合に限って実施する。

再実行手順は以下とする。

1. 対象 run と対象通知種別を特定する
2. 前回失敗原因が解消済みであることを確認する
3. 対象バッチを手動実行する
4. 実行後、新しい `t_notification_run` が記録されることを確認する
5. 実行結果が `success` であることを確認する
6. `partial` の場合は未解消として継続確認対象とする
7. `t_notification_delivery` の失敗件数が解消していることを確認する
8. 必要に応じて運用記録へ再実行結果を残す

再実行コマンドは以下を正式コマンドとして固定する。

- 満期通知の再実行
  - `php tools/batch/run_renewal_notification.php --date=YYYY-MM-DD --tenant=TE001 --executed-by=1 --type=renewal --retry-failed-run-id=対象RunID`
- 事故通知の再実行
  - `php tools/batch/run_renewal_notification.php --date=YYYY-MM-DD --tenant=TE001 --executed-by=1 --type=accident --retry-failed-run-id=対象RunID`
- 必要時の再試行ポリシー指定
  - `--retry-max-attempts=回数`
  - `--retry-minutes=待機分`

引数の定義は `tools/batch/run_renewal_notification.php` と `tools/batch/README.md` の記載に一致させる。
成功判定は、終了コードだけでなく、run / delivery の結果確認まで含める。

---

## 29. Phase 6 受入判定（短縮版）

### 確認済み

- 通知バッチの実装が存在し、定期実行を前提とした構成になっている
- 通知実行履歴を確認する前提テーブルが存在する
- 配信結果を確認する前提テーブルが存在する
- cron 実運用ジョブ定義を固定対象として明記した
- 失敗検知条件を明記した
- アラート導線を明記した
- 監視手順を明記した
- 一次確認手順を明記した
- 再実行手順を明記した

### 未実装

- 監視ダッシュボード化
- 運用SOPの詳細テンプレート化
- 障害一次切り分けの高度化
- 自動エスカレーションや二次通知の仕組み

### 未確認

- 本番 cron 環境での長期連続運転実績
- 実障害発生時の運用フロー一巡
- 運用担当者交代時の手順引継ぎ妥当性

### Phase 6 判定結論

Phase 6 は、通知バッチ運用を再現可能な定義として固定する範囲において受入完了とする。

本判定は、通知機能の拡張余地や運用改善余地が残ることを否定しない。
ただし、定期実行、履歴確認、失敗時再実行の運用定義が明文化されたことで、Phase 6 の完了条件は満たしたものと判断する。

---

## 30. 次フェーズへの分離

Phase 6 で受け入れない残課題は、後続の運用定着フェーズへ分離する。

### 運用定着フェーズ

対象は以下とする。

- 監視ダッシュボード化
- 運用SOP詳細化
- 障害一次切り分け手順の高度化
- 運用記録テンプレート整備
- アラート経路の強化
- 運用レビューサイクルの整備

---

## 31. 営業活動管理 Phase A — Activity CRUD（追加フェーズ）

### 目的

顧客起点の営業活動（訪問・電話・メール等）を記録し、一覧・詳細・編集・削除を可能にする。

### 前提

- `t_activity` DDL は追加済み。変更禁止。
- `t_daily_report` / `t_sales_case` DDL も追加済み。フェーズA時点では `t_daily_report` は未使用、`t_sales_case` は紐づけフィールドのみ保持（UI非表示）。
- テナント分離は既存パターン（TenantConnectionFactory）に完全準拠。

### 対象画面

- SCR-ACTIVITY-LIST（活動一覧）
- SCR-ACTIVITY-NEW（活動登録）
- SCR-ACTIVITY-DETAIL（活動詳細）

### 対象PHPファイル（新規）

- `src/Domain/Activity/ActivityRepository.php`
- `src/Controller/ActivityController.php`
- `src/Presentation/ActivityListView.php`
- `src/Presentation/ActivityDetailView.php`

### 対象PHPファイル（変更）

- `src/bootstrap.php`：ActivityController DI登録、ルート追加（計6本）
  - `GET  activity/list`
  - `GET  activity/new`
  - `GET  activity/detail`
  - `POST activity/store`
  - `POST activity/update`
  - `POST activity/delete`
- `src/Presentation/View/Layout.php`：navLinks に「営業活動」追加
- `src/Domain/Customer/CustomerRepository.php`：`findActivities()` のカラム名不整合修正（activity_at→activity_date, detail→detail_text, outcome→result_type）、取得フィールド拡充
- `src/Presentation/CustomerDetailView.php`：活動履歴セクションに活動詳細リンクと活動登録ボタン追加

### 必要なDBテーブル

- `t_activity`（主）
- `m_customer`（顧客名表示）
- `common.users`（担当者名表示）

### 既存不整合（修正対象）

`CustomerRepository::findActivities()` が存在しないカラムを参照している。

| クエリ内カラム名 | DDL実際のカラム名 |
|---|---|
| `activity_at` | `activity_date` |
| `detail` | `detail_text` |
| `outcome` | `result_type` |

このメソッドはフェーズA実装時に同時修正する。

### 完了条件

- 活動を新規登録できる（customer_id 必須）
- 活動一覧で日付・担当者・活動種別でフィルタできる
- 活動詳細で内容を確認・編集・削除できる
- 顧客詳細の活動履歴セクションから活動詳細へ遷移できる
- 顧客詳細から活動登録（customer_id 引き継ぎ）へ遷移できる
- sales_case_id は DB保存可能だが UI は非表示
- 他テナントのデータが参照されないこと

---

## 32. 営業活動管理 Phase B — Daily Report View（追加フェーズ）

### 目的

指定日の活動を集約表示し、日報コメントを入力・保存できる日報ビューを追加する。

### 前提

- Phase A 完了後に着手。
- `t_daily_report` DDL は追加済み。UNIQUE KEY(report_date, staff_user_id)。変更禁止。
- 日報コメントの upsert は INSERT ON DUPLICATE KEY UPDATE で実装。

### 対象画面

- SCR-ACTIVITY-DAILY（日報ビュー）

### 対象PHPファイル（新規）

- `src/Domain/Activity/DailyReportRepository.php`
- `src/Presentation/ActivityDailyView.php`

### 対象PHPファイル（変更）

- `src/Controller/ActivityController.php`：`daily()` / `saveComment()` メソッド追加
- `src/bootstrap.php`：ルート追加（計2本）
  - `GET  activity/daily`
  - `POST activity/comment`

### 必要なDBテーブル

- `t_daily_report`（日報コメント）
- `t_activity`（その日の活動一覧）
- `common.users`（担当者名表示）

### 完了条件

- 活動一覧の日付リンクから日報ビューへ遷移できる
- 日報ビューで指定日の活動が一覧表示される（自分の活動のみ。管理者は担当者切替可能）
- 日報コメントを入力・保存できる（1スタッフ1日1件。再保存で上書き）
- is_submitted フラグは将来用途とし、現時点では常に 0 のまま保存

---

> **ルート追加の総計（Phase A + Phase B）**: 8 本（Phase A: 6本 + Phase B: 2本）

---

## 33. 営業活動管理 Phase C — Sales Case CRUD（予定フェーズ）

Phase A/B 完了後に別途着手予定。`t_sales_case` DDL は追加済みだが、Phase C まで UI実装は行わない。

このフェーズは、通知機能の開発完了後に、実運用を安定して継続するための改善フェーズとして扱う。
