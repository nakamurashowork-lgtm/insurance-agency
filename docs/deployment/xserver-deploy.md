# XServer デプロイ手順

## Context

ローカル開発済みの `insurance-agency` を XServer (xs679743.xsrv.jp) に配置する。XServer 側には `insurance-agency/` (非公開領域) と `public_html/insurance-agency/` が作成済み。DB と DDL/DML は適用済み。今回の作業はファイル配置と疎通確認のみ。

**既存配置の注意:** 過去に旧ネスト型レイアウト (`public_html/insurance-agency/public/assets/...` / 非公開側に `public/` と `src/` が両方入っている形) でデプロイされた痕跡が残っている場合がある。今回採用する分割配置モデルとは構造が違うので、再デプロイ前に **両フォルダの中身を全消し** してから展開すること（手順 3）。

**採用する配置モデル（分割配置）:**
機密コード (`src/`, `config/`, `.env`) は非公開領域、Web 入口 (`public/*`) のみ `public_html/` に置く XServer 定番構成。リポジトリ内 [index.php.staging](../../index.php.staging) は既にこの分割モデル前提で書かれており、`/home/xs679743/xs679743.xsrv.jp/insurance-agency/src/bootstrap.php` を絶対パスで参照する。

**なぜこの形か:**
- [CLAUDE.md](../../CLAUDE.md) の責務分離方針（`public/` は Web 入口のみ、`src/` は業務実装本体）に一致
- [src/.htaccess](../../src/.htaccess) の `Require all denied` に頼らず、そもそも Web ルート外に置くのが堅い
- [src/bootstrap.php](../../src/bootstrap.php) が独自の `spl_autoload_register` を持つため、実行時に `vendor/` は不要（`vendor/` は PHPUnit のみ）

---

## 配置先マッピング

| ローカル                              | XServer 配置先                                                  | 公開/非公開 |
| ------------------------------------- | --------------------------------------------------------------- | ----------- |
| `src/**`                              | `/home/xs679743/xs679743.xsrv.jp/insurance-agency/src/`         | 非公開      |
| `config/**`                           | `/home/xs679743/xs679743.xsrv.jp/insurance-agency/config/`      | 非公開      |
| `.env` (≒ `.env.sta` を加工)          | `/home/xs679743/xs679743.xsrv.jp/insurance-agency/.env`         | 非公開      |
| `composer.json`, `composer.lock`      | `/home/xs679743/xs679743.xsrv.jp/insurance-agency/`             | 非公開      |
| `public/assets/**`                    | `/home/xs679743/xs679743.xsrv.jp/public_html/insurance-agency/assets/` | 公開        |
| `index.php.staging` → `index.php`     | `/home/xs679743/xs679743.xsrv.jp/public_html/insurance-agency/index.php` | 公開        |

**持ち込まないもの** (本番に不要):
`.git/`, `.claude/`, `.vscode/`, `docs/`, `tests/`, `tmp/`, `tools/`, `node_modules/`, `vendor/`, `*.log`, `.phpunit.result.cache`, `phpunit.xml`, `composer.phar`, `package.json`, `insurance-agency.zip`, `.env`, `.env.example`, `.env.sta`（加工後の `.env` に差し替えるため）

---

## 事前作業（ローカル）

### 1. 本番用 `.env` を作成

[.env.sta](../../.env.sta) をベースに、分割配置モデル用の URL に修正したものを `deploy/.env` として手元で作る。既存 `.env.sta` の URL は旧レイアウト (`/insurance-agency/public`) 向けなので **要修正**。

```ini
APP_ENV=production
APP_URL=https://xs679743.xsrv.jp/insurance-agency
APP_PUBLIC_URL=https://xs679743.xsrv.jp/insurance-agency

COMMON_DB_HOST=localhost
COMMON_DB_PORT=3306
COMMON_DB_NAME=xs679743_admin
COMMON_DB_USER=xs679743_puser
COMMON_DB_PASSWORD=（.env.sta の値）

TENANT_DB_HOST=localhost
TENANT_DB_PORT=3306
TENANT_DB_USER=xs679743_puser
TENANT_DB_PASSWORD=（.env.sta の値）

GOOGLE_CLIENT_ID=（.env.sta の値）
GOOGLE_CLIENT_SECRET=（.env.sta の値）
GOOGLE_REDIRECT_URI=https://xs679743.xsrv.jp/insurance-agency/?route=auth/google/callback

SESSION_COOKIE_NAME=INS_AGENCY_SESSID
SESSION_COOKIE_SECURE=true
```

変更点:
- `APP_ENV`: `staging` → `production`
- `APP_URL` / `APP_PUBLIC_URL`: 末尾の `/public` を削除（`src/Presentation/DashboardView.php:347` が `{APP_PUBLIC_URL}/assets/js/dashboard.js` を生成するため、アセット配置パスと一致させる必要あり）
- `GOOGLE_REDIRECT_URI`: `/public/` を削除（**Google Cloud Console 側のリダイレクト URI 登録も要更新**）

### 2. ZIP を 2 つ作る

**A. 非公開領域用: `app.zip`**
- 中身: `src/`, `config/`, `composer.json`, `composer.lock`, 加工済み `.env`
- ルートに `insurance-agency/` を含めない（XServer 側で既存 `insurance-agency/` の中身として展開する）

**B. public 領域用: `pub.zip`**
- 中身: `public/assets/` 配下一式 + `index.php`（= ローカルの `index.php.staging` をリネーム）
- 元の `public/index.php`（開発用 `dirname(__DIR__)` 参照版）は含めない

---

## XServer 側作業

### 3. 既存フォルダ中身を全消し

ファイルマネージャで以下 2 箇所の**中身**（フォルダ自体は残す）を削除:
- `xs679743.xsrv.jp/insurance-agency/`
- `xs679743.xsrv.jp/public_html/insurance-agency/`

旧ネスト型レイアウト (`public_html/insurance-agency/public/assets/...` や、非公開側の `public/`) が残っていると、新しい分割配置と混ざって 404 や 500 の原因になる。必ず全消ししてから展開する。

### 4. ZIP アップロード & 展開

1. `app.zip` を `xs679743.xsrv.jp/insurance-agency/` にアップロード → ファイルマネージャの「展開」で解凍 → `app.zip` 削除
2. `pub.zip` を `xs679743.xsrv.jp/public_html/insurance-agency/` にアップロード → 同様に展開 → `pub.zip` 削除

### 5. パーミッション確認

- フォルダ: `705` または `755`
- ファイル: `644`
- **`.env`**: `600` 推奨（非公開領域にあるが機密情報を含むため最小権限）

XServer ファイルマネージャで右クリック →「パーミッション変更」から設定可能。

---

## 検証手順（疎通確認）

1. `https://xs679743.xsrv.jp/insurance-agency/` にブラウザでアクセス
   - ログイン画面が表示されれば index.php → bootstrap.php のロードは成功
2. ログイン後、ダッシュボードが表示されれば DB 接続 OK
3. 代表画面を巡回:
   - 顧客一覧 / 顧客詳細
   - 満期一覧（= 契約一覧） / 満期詳細（= 契約詳細）
   - 事故案件一覧
4. Google OAuth ログインが動くか（`GOOGLE_REDIRECT_URI` 更新の反映確認）
5. `https://xs679743.xsrv.jp/insurance-agency/src/` に直接アクセスして **403** が返ることを確認（公開漏れ防止）
6. ブラウザ DevTools でアセット (`/insurance-agency/assets/...`) が 200 で返ることを確認

**もし 500 が出たら:**
- XServer の PHP エラーログ（サーバーパネル → エラーログ）で原因確認
- 典型: `.env` の DB パスワード誤り / 絶対パス (`/home/xs679743/...`) の打ち間違え / パーミッション不足

---

## 重要な修正が必要な既存ファイル（ローカル）

- **[index.php.staging](../../index.php.staging) の絶対パス** (`/home/xs679743/xs679743.xsrv.jp/insurance-agency/src/bootstrap.php`) はサーバーのホームディレクトリ名が変わると壊れる。XServer サーバーパネルで現在のホームパスを再確認してから使うこと。

## 次回以降のデプロイを楽にする改善案（今回は対象外）

- `tools/build-deploy-zip.sh` 的なスクリプトで ZIP 2 つを自動生成する
- `.env.sta` の URL を新レイアウトに合わせて修正しコミット（今回の `.env` 作成作業を省略）
- XServer の SSH 有効化 + `git pull` 運用に切り替え
