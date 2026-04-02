# デザイントークン定義

## 1. 目的

本ドキュメントは、全画面で共通して使用するデザイントークン（カラー・レイアウト・タイポグラフィ・コンポーネント）を正式定義する。

実装（`src/Presentation/View/Layout.php` の CSS 変数）および  
ワイヤーフレーム（`docs/screens/wireframe/insurance_wireframe.html` の `:root` CSS）は  
本ドキュメントの定義を正本とする。

両者に差分が生じた場合は、本ドキュメントを優先して是正する。

---

## 2. カラートークン

### 2-1. 背景色

| トークン名 | 値 | 主な用途 |
|---|---|---|
| `--bg-primary` | `#ffffff` | カード背景・ナビ背景・入力欄背景 |
| `--bg-secondary` | `#f4f3ee` | ページ全体の背景・テーブルヘッダー背景・メトリクスカード背景 |
| `--bg-tertiary` | `#eceae3` | 補助背景・選択状態の背景 |
| `--bg-info` | `#e6f1fb` | 情報バッジ背景・主操作ボタン背景 |
| `--bg-success` | `#eaf3de` | 成功バッジ背景 |
| `--bg-warning` | `#faeeda` | 警告バッジ背景・警告アラート背景 |
| `--bg-danger` | `#fcebeb` | 危険バッジ背景・危険ボタン背景 |

### 2-2. テキスト色

| トークン名 | 値 | 主な用途 |
|---|---|---|
| `--text-primary` | `#1a1a18` | 本文・見出し・テーブルセル |
| `--text-secondary` | `#6b6b67` | 補助テキスト・ラベル・プレースホルダー・非アクティブナビ項目 |
| `--text-info` | `#185fa5` | リンク・アクティブナビ項目・主操作ボタン文字色 |
| `--text-success` | `#3b6d11` | 成功バッジ文字色 |
| `--text-warning` | `#854f0b` | 警告バッジ文字色 |
| `--text-danger` | `#a32d2d` | 危険バッジ文字色・エラーメッセージ |

### 2-3. ボーダー色

| トークン名 | 値 | 主な用途 |
|---|---|---|
| `--border-light` | `rgba(0,0,0,0.10)` | カードボーダー・区切り線・テーブル行ボーダー |
| `--border-medium` | `rgba(0,0,0,0.18)` | ナビ下線・入力欄ボーダー・ボタンボーダー |
| `--border-info` | `#378add` | アクティブナビ下線・フォーカスリング・情報バッジボーダー |
| `--border-success` | `#639922` | 成功バッジボーダー |
| `--border-warning` | `#ba7517` | 警告バッジボーダー・警告アラートボーダー |
| `--border-danger` | `#e24b4a` | 危険バッジボーダー・危険ボタンボーダー |

### 2-4. 使用禁止

- 上記トークン以外のカラー値をナビ・ボタン・バッジ・ボーダーに直書きしない
- ティール系（`#1a8a7a` 等の緑がかった青）はトークン定義に存在しないため使用禁止
- 個別画面で独自カラーを追加しない。追加が必要な場合は本ドキュメントを改訂する

---

## 3. レイアウトトークン

### 3-1. 角丸

| トークン名 | 値 | 主な用途 |
|---|---|---|
| `--radius-md` | `8px` | ボタン・入力欄・バッジ・小カード |
| `--radius-lg` | `12px` | メインカード・ダイアログ |

### 3-2. ページコンテナ

全画面のページコンテナは以下の値を統一値とする（`docs/policies/06_common-ui-rules.md §11-1` との整合）。

| 項目 | 値 |
|---|---|
| クラス名 | `.page-container` |
| `max-width` | `1280px` |
| `margin` | `0 auto` |
| `padding` | `20px 16px` |
| スマホ時 `padding` | `16px 12px` |

個別画面で独自の `max-width` や左右余白を定義しない。

---

## 4. ナビゲーション仕様

### 4-1. 構造

| 項目 | 値 |
|---|---|
| 背景色 | `--bg-primary`（`#ffffff`） |
| 高さ | `50px` 固定 |
| 下線 | `0.5px solid --border-medium` |
| ボックスシャドウ | `0 1px 3px rgba(0,0,0,0.06)` |
| 位置 | `sticky; top: 0; z-index: 100` |
| 配置 | 左端ロゴ → ナビ項目横並び → 右端ユーザー情報・ログアウト |

アバターアイコン（丸アイコン等）は **使用しない**。

### 4-2. ナビ項目

| 項目 | 値 |
|---|---|
| フォントサイズ | `12.5px` |
| 非アクティブ文字色 | `--text-secondary`（`#6b6b67`） |
| ホバー文字色 | `--text-primary`（`#1a1a18`） |
| アクティブ文字色 | `--text-info`（`#185fa5`） |
| アクティブ下線 | `border-bottom: 2.5px solid --border-info`（`#378add`） |
| アクティブ表現 | **下線のみ**。背景塗り・丸バッジは使用しない |
| `padding` | `0 14px` |

### 4-3. ナビ項目の正式定義

全画面共通のナビ項目名・遷移先・表示条件は以下とする。

| 表示名 | 遷移先ルート | 表示条件 |
|---|---|---|
| ホーム | `dashboard` | 全ログインユーザー |
| 満期一覧 | `renewal/list` | 全ログインユーザー |
| 顧客一覧 | `customer/list` | 全ログインユーザー |
| 事故案件 | `accident/list` | 全ログインユーザー |
| 実績管理 | `sales/list` | 全ログインユーザー |
| 営業活動 | `activity/list` | 全ログインユーザー |
| 設定 | `tenant/settings` | `admin` / `system_admin` のみ |
| 営業案件 | `sales-case/list` | **Phase C-Lite 完了まで非表示** |

項目名はこの表の「表示名」列を正とする。  
`満期管理` `顧客管理` `事故管理` `管理・設定` 等の表記は誤りであり使用しない。

---

## 5. タイポグラフィ

### 5-1. フォントファミリー
```
'Noto Sans JP', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif
```

全画面で統一する。個別画面でフォントを上書きしない。

### 5-2. フォントサイズ

| 用途 | サイズ |
|---|---|
| ベース本文 | `14px` |
| ページタイトル | `18px` / `font-weight: 500` |
| セクションタイトル | `11.5px` / `font-weight: 500` / `uppercase` / `letter-spacing: 0.4px` |
| テーブルヘッダー | `font-weight: 500` |
| テーブルセル | `12.5px` |
| バッジ | `11px` / `font-weight: 500` |
| 補助テキスト | `12px` |
| ナビ項目 | `12.5px` |
| ナビロゴ | `13px` / `font-weight: 600` |

---

## 6. コンポーネントトークン

### 6-1. ボタン

| 種別 | 背景 | 文字色 | ボーダー |
|---|---|---|---|
| 標準（btn） | `--bg-primary` | `--text-primary` | `0.5px solid --border-medium` |
| 主操作（btn-primary） | `--bg-info` | `--text-info` | `0.5px solid --border-info` |
| 危険（btn-danger） | `--bg-danger` | `--text-danger` | `0.5px solid --border-danger` |

共通: `padding: 6px 14px` / `border-radius: --radius-md` / `font-size: 12px`

### 6-2. カード

| 項目 | 値 |
|---|---|
| 背景 | `--bg-primary` |
| ボーダー | `0.5px solid --border-light` |
| 角丸 | `--radius-lg` |
| `padding` | `16px 18px` |
| `margin-bottom` | `14px` |

### 6-3. バッジ

| 種別 | 背景 | 文字色 |
|---|---|---|
| info | `--bg-info` | `--text-info` |
| success | `--bg-success` | `--text-success` |
| warning | `--bg-warning` | `--text-warning` |
| danger | `--bg-danger` | `--text-danger` |
| gray | `--bg-secondary` | `--text-secondary` |

共通: `padding: 2px 9px` / `border-radius: 999px` / `font-size: 11px` / `font-weight: 500`

### 6-4. テーブル

| 項目 | 値 |
|---|---|
| ヘッダー背景 | `--bg-secondary` |
| ヘッダー文字色 | `--text-secondary` |
| セル `padding` | `8px 12px` |
| 行ボーダー | `0.5px solid --border-light` |
| ホバー背景 | `#f8f7f3` |
| ラッパー | `.table-wrap { overflow-x: auto; }` |

### 6-5. 入力欄・セレクト

| 項目 | 値 |
|---|---|
| 背景 | `--bg-primary` |
| ボーダー | `0.5px solid --border-medium` |
| 角丸 | `--radius-md` |
| `padding` | `8px 11px` |
| フォントサイズ | `13px` |
| フォーカスボーダー | `--border-info` |
| フォーカスリング | `box-shadow: 0 0 0 2px rgba(55,138,221,0.12)` |

### 6-6. アラート

| 種別 | 背景 | 文字色 | ボーダー |
|---|---|---|---|
| warn | `--bg-warning` | `--text-warning` | `0.5px solid --border-warning` |

共通: `padding: 10px 16px` / `border-radius: --radius-md` / `font-size: 12.5px`

---

## 7. 一覧画面の構造トークン

本節は `docs/policies/06_common-ui-rules.md §4` を補完し、構造要件を定量化する。

### 7-1. 必須構成要素

全一覧画面は以下の要素を必ず持つ。省略不可。

1. ページヘッダー（タイトル + 右端ボタン群）
2. 検索条件カード（折りたたみ可）
3. 一覧カード
   - 上部: 件数表示 + 表示件数セレクト + ページャー
   - テーブル本体
   - 下部: 件数表示 + 表示件数セレクト + ページャー
4. テーブル最右列: 「操作」列（詳細リンク）固定

### 7-2. ページャー仕様

| 項目 | 値 |
|---|---|
| 表示件数の選択肢 | `10` / `50` / `100` |
| 初期表示件数 | `10` |
| 配置 | 一覧上部・下部の両方 |
| 件数表示フォーマット | `{全件数}件中 {開始}〜{終了}件を表示` |
| 0件時 | ページャーを非表示にし「該当データはありません」を表示 |
| 条件変更時 | 先頭ページへ戻す |
| 状態保持 | 検索条件・ページ・表示件数・並び順を URL クエリで保持 |

### 7-3. 禁止事項

- `page-subtitle` への説明文・設計者用語の掲載（`06_common-ui-rules.md §3` 参照）
- 一覧画面への登録・更新操作の直接混入（ホーム画面も同様）
- 詳細導線を右端「操作」列以外の方法のみで実装すること

---

## 8. 適用・更新ルール

- 本ドキュメントは `docs/policies/06_common-ui-rules.md` と対で参照する
- 実装（`Layout.php`）または wireframe を変更する場合、本ドキュメントとの整合を先に確認する
- カラー・レイアウト値を変更する場合は本ドキュメントを先に改訂し、実装・wireframe を後から更新する
- 個別画面で例外が必要な場合は、各 `docs/screens/` の画面設計書に理由付きで記載する

---

## 9. 参照元

| 参照先 | 参照内容 |
|---|---|
| `insurance_wireframe.html` | `:root` カラートークン・`.nav` CSS・コンポーネント CSS |
| `docs/policies/06_common-ui-rules.md §11` | ページコンテナ横幅ルール |
| `docs/foundations/02_navigation-policy.md` | ナビ項目の表示条件・Phase C-Lite 制約 |
| `docs/screens/activity-list.md §5` | 活動一覧テーブルカラム定義 |
