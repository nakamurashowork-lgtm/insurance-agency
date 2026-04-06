# 共通UIルール

## 1. 目的

本ドキュメントは、全画面で共通して守る UI の上位ルールを定義する。

目的は以下のとおり。

* 画面ごとの見た目と操作作法のぶれを抑える
* 業務画面としての判断速度と可読性を優先する
* 一覧、詳細、ダイアログの責務を崩さない

本書は「見せ方・構造・用語・操作」の共通規約を扱い、業務責務そのものの変更は扱わない。
画面責務は `docs/foundations/03_screen-map.md` を正とする。

---

## 2. 基本原則

* 一覧は「探す・絞る・特定する」の画面
* 詳細は「確認する・判断する・更新する」の画面
* ダイアログは一覧起点の補助操作に限定する
* ホームは入口に徹し、実処理を持たせない
* 説明文で補うより、構造で分からせる
* 不要な注記、作り手目線の文言を置かない

---

## 3. 文言ルール

* 画面タイトルは業務用語で短くする
* 「主要導線」「補助導線」など設計者用語を画面本文に出さない
* 説明文は原則なし。必要時のみ 1 行まで
* 内部値や内部項目名を利用者向け文言に置換する

例:

* 監査ログ → 変更履歴
* 投稿者ID → 投稿者名
* accepted / in_progress → 受付 / 対応中

---

## 4. 一覧画面共通ルール

### 4-1. 構成

* タイトル
* 検索条件カード（折りたたみ可）
* 一覧カード（件数・並び順・表示件数・ページャー + テーブル）

### 4-2. 振る舞い

* 上下ページャーを基本とする
* 表示件数は 10 / 50 / 100 に統一する
* 検索条件、ページ、表示件数、並び順は URL で保持する
* 一覧から詳細へ遷移後、戻っても一覧状態を維持する

### 4-3. 表示

* 状態系はバッジで表示する
* 詳細導線は右端固定を基本とする
* 一覧に更新項目を詰め込みすぎない
* 主カラムは「識別」「日付」「状態」「操作」を優先する
* 長い識別子は省略表示し、詳細で全文確認させる

---

## 5. 詳細画面共通ルール

### 5-1. 構成

* ヘッダ（識別情報 + 戻る導線）
* 基本情報
* 更新エリア
* 補助情報（コメント、変更履歴など）

### 5-2. 原則

* 既存対象 1 件の確認と更新を 1 画面で成立させる
* ラベルは上、入力欄は下を基本とする
* 説明文は原則置かない
* 保存ボタンを主操作として視認できる位置に置く
* 関連情報は責務に応じて他画面へ切り分ける

---

## 6. ダイアログ共通ルール

### 6-1. 用途

* 一覧起点の補助操作で利用する
* 独立画面に分ける必要がない操作を対象とする

例:

* 事故案件追加
* CSV取込
* 実績追加

### 6-2. 構成

* 入力は 2 列グリッドを基本
* 備考は全幅
* セクション見出しを増やしすぎない
* 主ボタン / 副ボタンは右下にまとめる
* キャンセル導線を必ず置く

---

## 7. ボタン・リンクのルール

### 7-1. 種別

* 主操作ボタン: 保存、登録する、追加する
* 副操作ボタン: キャンセル、一覧へ戻る
* 補助遷移: テキストリンクを基本とする

### 7-2. 統一

* 画面ごとに主ボタンのサイズ、余白、角丸を変えない
* 一覧右上の主補助操作ボタンは同じ見た目ルールに統一する

対象例:

* CSV取込
* 実績を追加
* 事故案件を追加

---

## 8. ステータス・バッジのルール

* 内部値をそのまま表示しない
* 利用者に見せる文言は業務用語に統一する
* 色ルールは少数に絞り、一覧での識別性を優先する
* 色に意味を過剰に持たせず、文言と併用して判断させる

---

## 9. コメント・変更履歴・活動履歴の扱い

* コメントは追記方式とし、本文改行を保持する
* 表示は「投稿者名 + 投稿日時 + 本文」を基本とする
* 変更履歴は当該対象の更新履歴として表示する
* 活動履歴は顧客文脈なら顧客詳細側へ寄せる
* 0 件表示はコンパクトにし、縦長化を避ける

---

## 10. 適用対象と運用ルール

* 本ルールは既存全画面に順次適用する
* 画面修正時は本書を参照し、差分がある場合は明記する
* 例外が必要な場合は各 screen docs に理由付きで記載する
* screen docs に重複記載せず、本書を正本として参照する
* 画面ごとの遵守判定、未対応、例外理由、着手順は `docs/policies/07_ui-compliance-checklist.md` で管理する

---

## 11. 全画面共通の横幅ルール

### 11-1. ページコンテナ

* 全画面のページコンテナは `.page-container` クラスを使用する
* `max-width: 1280px`、左右 `margin: 0 auto`、左右 `padding: 20px 16px` を統一値とする
* 個別画面で独自の `max-width` や左右余白を定義しない

### 11-2. テーブル

* 一覧テーブルは `.table-wrap` で囲み、`overflow-x: auto` を適用する
* ページ全体を横スクロールさせない
* テーブルのスクロールはコンテナ内に閉じる

### 11-3. スマホ対応

* スマホ幅では左右 `padding` のみを縮める（`16px 12px`）
* `max-width` 値はスマホでも変更しない
* レイアウトの崩しはメディアクエリで行い、コンテナ幅は変えない

### 11-4. ログイン画面

* ページコンテナは共通の 1280px とし、ログインカード（`.login-card`）がカードとして `max-width: 480px` で中央に表示される
* これはコンポーネントレベルの制約であり、ページコンテナのルールの例外ではない

### 11-5. 実装上の制約

* `Layout.php` に `.page-container` クラスを定義し、全画面の `.app-shell` に適用する
* `pageWidth` オプションは廃止済み。画面ごとにコンテナ幅を変更しない
* 各 screen docs にはコンテナ幅の記載をしない。本節を参照する

---

## 12. 一覧画面共通テンプレート（ListPageRenderer）

### 12-1. 概要

`src/Presentation/View/ListPageRenderer.php` に、全一覧画面で共通するHTML生成ロジックを集約している。  
各 `*ListView` クラスは画面固有の部分（フィルター内容・テーブル定義・ダイアログ）のみを持ち、
共通構造はすべて `ListPageRenderer`（エイリアス `LP`）に委譲する。

### 12-2. 対象画面

以下の6画面が `ListPageRenderer` を使用している。

| 画面 | Viewクラス |
|------|-----------|
| 満期一覧 | `RenewalCaseListView` |
| 顧客一覧 | `CustomerListView` |
| 事故案件一覧 | `AccidentCaseListView` |
| 実績管理一覧 | `SalesPerformanceListView` |
| 活動一覧 | `ActivityListView` |
| 見込案件一覧 | `SalesCaseListView` |

### 12-3. 基本的な使い方

```php
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

// 1. ページ状態を初期化
$perPage    = (int) ($listState['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE);
$pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $total);
$listState['page'] = (string) ($pager['currentPage'] ?? 1);

// 2. ツールバー・ページャーを生成
$topToolbar  = LP::toolbar($url, $criteria, $listState, $pager, $total, $perPage, $sortSummary);
$bottomPager = LP::bottomPager($url, $criteria, $listState, $pager);

// 3. フィルターフォームHTML・テーブルHTMLを画面固有で組み立て
$filterFormHtml = '<form method="get" ...> ... </form>';
$tableHtml      = '<div class="table-wrap"><table ...>...</table></div>';

// 4. ページ全体を組み立て
$content = '<div class="list-page-frame">'
    . LP::pageHeader('画面タイトル', '<button ...>ボタン</button>')
    . $noticeHtml
    . LP::filterCard($filterFormHtml, $filterOpen)
    . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
    . '</div>'
    . $dialogHtml       // ダイアログはフレームの外
    . '<script>...</script>';
```

### 12-4. 提供メソッド一覧

#### レイアウト部品

| メソッド | 説明 |
|---------|------|
| `pageHeader(title, actionsHtml)` | `list-page-header` ブロックを生成（`list-page-frame` の開閉は呼び出し側） |
| `filterCard(formHtml, filterOpen, errorHtml)` | 折りたたみ可能な検索条件カードを生成 |
| `tableCard(toolbarHtml, tableHtml, bottomPagerHtml)` | テーブルを包む `div.card` を生成 |

#### ツールバー・ページング

| メソッド | 説明 |
|---------|------|
| `toolbar(url, criteria, listState, pager, total, perPage, sortSummary)` | 件数表示 + 表示件数切替 + 上部ページャー。`sortSummary` は省略可 |
| `bottomPager(url, criteria, listState, pager)` | 下部ページャー（1ページのみなら空文字） |
| `pager(url, criteria, listState, pager)` | ページャーナビ単体 |
| `perPageForm(url, criteria, listState, perPage)` | 表示件数切替フォーム |
| `summaryText(total, pager)` | 件数表示テキスト（例: 「25件中 11-20件を表示」） |

#### ソートリンク

| メソッド | 説明 |
|---------|------|
| `sortLink(label, column, url, criteria, listState)` | ソート可能なカラムヘッダーリンクを生成 |

#### クエリパラメータ・URL

| メソッド | 説明 |
|---------|------|
| `queryParams(criteria, listState, includePage, includeSort)` | 一覧URLのクエリパラメータ配列を組み立て |
| `hiddenInputs(params)` | パラメータ配列を `<input type="hidden">` 群に変換 |
| `formAction(url)` | URLのパス部分のみを返す（フォーム `action` 用） |
| `routeInput(url)` | URLのクエリ文字列パラメータを hidden input 群に変換（ルーティング維持用） |

### 12-5. HTML構造の標準パターン

一覧画面のHTML構造は以下に統一する。

```html
<div class="list-page-frame">
  <div class="list-page-header">
    <h1 class="title">画面タイトル</h1>
    <div class="list-page-header-actions"><!-- ボタン群 --></div>
  </div>
  <!-- フラッシュメッセージ -->
  <details class="card details-panel list-filter-card">
    <summary class="list-filter-toggle">...</summary>
    <!-- フィルターフォーム -->
  </details>
  <div class="card">
    <!-- ツールバー（件数 + 表示件数切替 + 上部ページャー） -->
    <div class="table-wrap"><table>...</table></div>
    <!-- 下部ページャー -->
  </div>
</div>
<!-- ダイアログ（フレームの外） -->
<!-- スクリプト -->
```

### 12-6. 新規一覧画面の追加手順

1. 新しい `*ListView` クラスを作成し、`use App\Presentation\View\ListPageRenderer as LP;` を追加
2. `render()` メソッド内で `LP::toolbar()` / `LP::bottomPager()` / `LP::filterCard()` / `LP::tableCard()` / `LP::pageHeader()` を使って骨格を組む
3. フィルターフォーム内容とテーブル定義のみ画面固有で実装する
4. ダイアログが必要な場合は `list-page-frame` の外に配置する

---

## 付録A. 現行画面との主な未整合メモ（初回整理）

* 一覧右上コントロール群の幅、余白にばらつきがある
* 一部画面で説明文が多く、構造で理解させる原則から外れている
* 一部画面で内部値のままの表示が残っている
* コメント、変更履歴、活動履歴の責務境界が画面間で揺れる箇所がある
* 一覧・詳細・ダイアログの責務分担が文書間で不一致の箇所がある

本付録は改善対象の見える化が目的であり、実装計画の代替ではない。

---

## 付録B. デザイントークンとの参照関係

カラー・レイアウト・コンポーネントの定量値は `docs/policies/08_design-tokens.md` を正本とする。  
本ドキュメントは「何をすべきか（構造・責務・文言）」を定め、  
`08_design-tokens.md` は「どう見せるか（値・寸法・色）」を定める。  
両者は対で参照する。

### ナビゲーション正式定義

ナビ項目名・遷移先・表示条件の正式定義は `docs/policies/08_design-tokens.md §4-3` を参照する。  
実装（`Layout.php`）および wireframe はその定義に従う。

### 一覧画面の必須構成要素（定量補足）

`docs/policies/06_common-ui-rules.md §4-1` の構成要件を以下のとおり定量化する。

- ページャーは一覧カード上部・下部の両方に配置する
- 表示件数の選択肢は `10` / `50` / `100` に統一する
- 件数表示フォーマット: `{全件数}件中 {開始}〜{終了}件を表示`
- テーブル最右列に「操作」列（詳細リンク）を右端固定で配置する
- 詳細導線を「操作」列以外の方法のみで実装することを禁止する

### page-subtitle 禁止ルールの明文化

§3「文言ルール」を補足する。  
`page-subtitle` に以下を記載することを禁止する。

- 設計者向けの説明文（例：「契約一覧を兼ねる — 絞り込みで対象を特定し、詳細で処理」）
- 運用制約の説明文（例：「当日の自分の活動がデフォルト表示」）
- 操作方法の説明文（例：「登録・編集は活動一覧から行う」）

`page-subtitle` を使用してよいのは、画面の対象範囲を示す短い業務用語のみ。  
原則として `page-subtitle` は使用しない。
