<?php
declare(strict_types=1);

namespace App\Presentation\View;

/**
 * 一覧画面の共通HTML部品ジェネレーター
 *
 * 全6一覧画面で重複していたページング・フィルター・レイアウト構造を集約する。
 * 各 *ListView は画面固有の部分（フィルター内容・テーブル・ダイアログ）のみを持ち、
 * 共通構造はすべて本クラスに委譲する。
 *
 * 使用パターン:
 * ```php
 * use App\Presentation\View\ListPageRenderer as LP;
 *
 * $perPage = (int) ($listState['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE);
 * $pager   = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $total);
 * $listState['page'] = (string) ($pager['currentPage'] ?? 1);
 *
 * $topToolbar  = LP::toolbar($url, $criteria, $listState, $pager, $total, $perPage);
 * $bottomPager = LP::bottomPager($url, $criteria, $listState, $pager);
 * $filterCard  = LP::filterCard($filterFormHtml, $filterOpen);
 * $tableCard   = LP::tableCard($topToolbar, $tableHtml, $bottomPager);
 *
 * $content = '<div class="list-page-frame">'
 *     . LP::pageHeader('画面タイトル', '<button ...>ボタン</button>')
 *     . $noticeHtml
 *     . $filterCard
 *     . $tableCard
 *     . '</div>'
 *     . $dialogHtml
 *     . '<script>...</script>';
 * ```
 */
final class ListPageRenderer
{
    // ─────────────────────────────────────────────────────────────────
    // レイアウト部品
    // ─────────────────────────────────────────────────────────────────

    /**
     * list-page-header ブロックを生成する（list-page-frame の開閉は呼び出し側で行う）
     *
     * @param string $actionsHtml ヘッダー右側のボタン群HTML（生のHTML文字列、エスケープ済みであること）
     */
    public static function pageHeader(string $title, string $actionsHtml): string
    {
        return '<div class="list-page-header">'
            . '<h1 class="title">' . Layout::escape($title) . '</h1>'
            . '<div class="list-page-header-actions">' . $actionsHtml . '</div>'
            . '</div>';
    }

    /**
     * 折りたたみ可能な検索条件カードを生成する
     *
     * @param string $formHtml    フィルターフォームのHTML（<form>...</form> を含む完全なHTML）
     * @param bool   $filterOpen  true のときカードを開いた状態で出力する
     * @param string $errorHtml   フィルターカード内に表示するエラーHTML（任意）
     */
    public static function filterCard(string $formHtml, bool $filterOpen, string $errorHtml = ''): string
    {
        return '<details class="card details-panel list-filter-card"' . ($filterOpen ? ' open' : '') . '>'
            . '<summary class="list-filter-toggle">'
            . '<span class="list-filter-toggle-label is-closed">検索条件を開く</span>'
            . '<span class="list-filter-toggle-label is-open">検索条件を閉じる</span>'
            . '</summary>'
            . $errorHtml
            . $formHtml
            . '</details>';
    }

    /**
     * テーブルを包むカードを生成する
     *
     * @param string $toolbarHtml    上部ツールバーHTML（buildToolbar() の出力）
     * @param string $tableHtml      テーブルHTML（<div class="table-wrap"><table>...</table></div> を含む）
     * @param string $bottomPagerHtml 下部ページャーHTML（buildBottomPager() の出力）
     */
    public static function tableCard(string $toolbarHtml, string $tableHtml, string $bottomPagerHtml): string
    {
        return '<div class="card">'
            . $toolbarHtml
            . $tableHtml
            . $bottomPagerHtml
            . '</div>';
    }

    // ─────────────────────────────────────────────────────────────────
    // ツールバー・ページング
    // ─────────────────────────────────────────────────────────────────

    /**
     * 件数表示 + 表示件数切替 + 上部ページャーをまとめたツールバーを生成する
     */
    public static function toolbar(
        string $url,
        array $criteria,
        array $listState,
        array $pager,
        int $totalCount,
        int $perPage,
        string $sortSummary = ''
    ): string {
        return '<div class="list-toolbar">'
            . '<div class="list-summary"><p class="summary-count">' . Layout::escape(self::summaryText($totalCount, $pager)) . '</p></div>'
            . '<div class="list-toolbar-actions">'
            . self::perPageForm($url, $criteria, $listState, $perPage)
            . self::pager($url, $criteria, $listState, $pager)
            . '</div>'
            . '</div>';
    }

    /**
     * 下部ページャーを生成する（1ページのみのときは空文字を返す）
     */
    public static function bottomPager(string $url, array $criteria, array $listState, array $pager): string
    {
        $pagerHtml = self::pager($url, $criteria, $listState, $pager);
        if ($pagerHtml === '') {
            return '';
        }

        return '<div class="list-toolbar list-toolbar-bottom"><div class="list-toolbar-actions">' . $pagerHtml . '</div></div>';
    }

    /**
     * ページャーナビゲーションを生成する（1ページのみのときは空文字を返す）
     */
    public static function pager(string $url, array $criteria, array $listState, array $pager): string
    {
        if ((int) ($pager['totalPages'] ?? 0) <= 1) {
            return '';
        }

        $links = '';
        if (!empty($pager['hasPrevious'])) {
            $links .= self::pagerLink('前へ', (int) ($pager['previousPage'] ?? 1), $url, $criteria, $listState);
        }

        foreach ((array) ($pager['pages'] ?? []) as $pageNumber) {
            $page = (int) $pageNumber;
            if ($page === (int) ($pager['currentPage'] ?? 1)) {
                $links .= '<span class="list-pager-link is-current">' . $page . '</span>';
                continue;
            }
            $links .= self::pagerLink((string) $page, $page, $url, $criteria, $listState);
        }

        if (!empty($pager['hasNext'])) {
            $links .= self::pagerLink('次へ', (int) ($pager['nextPage'] ?? 1), $url, $criteria, $listState);
        }

        return '<nav class="list-pager" aria-label="ページャー">' . $links . '</nav>';
    }

    /**
     * 表示件数切替フォームを生成する
     */
    public static function perPageForm(string $url, array $criteria, array $listState, int $perPage): string
    {
        $optionsHtml = '';
        foreach ([10, 50, 100] as $option) {
            $selected = $perPage === $option ? ' selected' : '';
            $optionsHtml .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }

        return '<form method="get" action="' . Layout::escape(self::formAction($url)) . '" class="list-per-page-form">'
            . self::routeInput($url)
            . self::hiddenInputs(self::queryParams($criteria, $listState, false))
            . '<label class="list-select-inline"><span>表示件数</span>'
            . '<select name="per_page" onchange="this.form.submit()">' . $optionsHtml . '</select></label>'
            . '<noscript><button class="btn btn-ghost btn-small" type="submit">更新</button></noscript>'
            . '</form>';
    }

    /**
     * 件数表示テキストを生成する（例:「25件中 11-20件を表示」）
     */
    public static function summaryText(int $totalCount, array $pager): string
    {
        if ($totalCount <= 0) {
            return '0件';
        }

        return $totalCount . '件中 ' . (int) ($pager['start'] ?? 0) . '-' . (int) ($pager['end'] ?? 0) . '件を表示';
    }

    // ─────────────────────────────────────────────────────────────────
    // ソートリンク
    // ─────────────────────────────────────────────────────────────────

    /**
     * ソート可能なカラムヘッダーリンクを生成する
     */
    public static function sortLink(string $label, string $column, string $url, array $criteria, array $listState): string
    {
        $isCurrent    = ($listState['sort'] ?? '') === $column;
        $nextDirection = $isCurrent && ($listState['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        $params = self::queryParams($criteria, array_merge($listState, ['sort' => $column, 'direction' => $nextDirection]));
        $href   = Layout::escape(ListViewHelper::buildUrl($url, $params));
        $indicator = '';
        if ($isCurrent) {
            $indicator = '<span class="list-sort-indicator">'
                . (($listState['direction'] ?? 'asc') === 'asc' ? '&#9650;' : '&#9660;')
                . '</span>';
        }

        return '<a class="list-sort-link' . ($isCurrent ? ' is-active' : '') . '" href="' . $href . '">'
            . Layout::escape($label) . $indicator . '</a>';
    }

    // ─────────────────────────────────────────────────────────────────
    // クエリパラメータ・URL ユーティリティ
    // ─────────────────────────────────────────────────────────────────

    /**
     * 一覧URLのクエリパラメータ配列を組み立てる
     *
     * @param array<string, string> $criteria    検索条件
     * @param array<string, string> $listState   ページ・表示件数・ソート状態
     * @param bool $includePage                  true のときページ番号を含める（1の場合は省略）
     * @param bool $includeSort                  true のときソートパラメータを含める
     * @return array<string, string>
     */
    public static function queryParams(
        array $criteria,
        array $listState,
        bool $includePage = true,
        bool $includeSort = true
    ): array {
        $params = $criteria;

        if ($includePage && (int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }

        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }

        if ($includeSort && ($listState['sort'] ?? '') !== '') {
            $params['sort']      = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return $params;
    }

    /**
     * パラメータ配列を hidden input 群に変換する（空値は出力しない）
     *
     * @param array<string, string> $params
     */
    public static function hiddenInputs(array $params): string
    {
        $html = '';
        foreach ($params as $name => $value) {
            if (trim((string) $value) === '') {
                continue;
            }
            $html .= '<input type="hidden" name="' . Layout::escape($name) . '" value="' . Layout::escape($value) . '">';
        }

        return $html;
    }

    /**
     * URLのパス部分のみを返す（フォームの action 属性用）
     */
    public static function formAction(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        return $path !== '' ? $path : '';
    }

    /**
     * URLのクエリ文字列部分にあるパラメータを hidden input 群に変換する
     *
     * フォームをサブミットしたときにルーティングパラメータ（route 等）が失われないように
     * フォーム内に埋め込んで使用する。
     */
    public static function routeInput(string $url): string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $html = '';
        foreach ($params as $name => $value) {
            $html .= '<input type="hidden" name="' . Layout::escape((string) $name) . '" value="' . Layout::escape((string) $value) . '">';
        }

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────
    // プライベートヘルパー
    // ─────────────────────────────────────────────────────────────────

    private static function pagerLink(string $label, int $page, string $url, array $criteria, array $listState): string
    {
        $params = self::queryParams($criteria, array_merge($listState, ['page' => (string) $page]));
        $href   = Layout::escape(ListViewHelper::buildUrl($url, $params));

        return '<a class="list-pager-link" href="' . $href . '">' . Layout::escape($label) . '</a>';
    }
}
