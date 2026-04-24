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
        int $perPage
    ): string {
        return '<div class="list-toolbar">'
            . '<div class="list-summary">'
            . '<p class="summary-count">' . Layout::escape(self::summaryText($totalCount, $pager)) . '</p>'
            . '</div>'
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
     * 表示件数切替をセグメントボタン（10 / 50 / 100）で生成する。
     * wireframe .seg 準拠: bg-tertiary 背景に白いアクティブタイル。
     * GET リンクなので JS 不要、per_page 以外のパラメータは保持（page は 1 にリセット）。
     */
    public static function perPageForm(string $url, array $criteria, array $listState, int $perPage): string
    {
        $buttons = '';
        foreach ([10, 50, 100] as $option) {
            $isActive = $perPage === $option;
            // per_page 変更時は page=1 にリセット
            $targetState = array_merge($listState, ['per_page' => (string) $option, 'page' => '1']);
            $href = Layout::escape(ListViewHelper::buildUrl($url, self::queryParams($criteria, $targetState)));

            if ($isActive) {
                $buttons .= '<span class="list-per-page-btn is-active" aria-current="true">' . $option . '</span>';
            } else {
                $buttons .= '<a class="list-per-page-btn" href="' . $href . '">' . $option . '</a>';
            }
        }

        return '<div class="list-per-page" role="group" aria-label="表示件数">'
            . '<span class="list-per-page-label">表示</span>'
            . '<div class="list-per-page-seg">' . $buttons . '</div>'
            . '</div>';
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
    // wireframe 準拠の共通 UI 部品（1 行ツールバー / タブ / ダイアログ / モバイルカード）
    // ─────────────────────────────────────────────────────────────────

    /**
     * 1 行ツールバー（検索バー + 絞込ボタン + 任意の追加ボタン）を生成する。
     * wireframe の `.list-toolbar-bar` パターン。検索バーは入力後 500ms で自動送信される
     * （JavaScript は dialogScript() で配信される共通スクリプトが担当）。
     *
     * @param array{
     *   searchUrl: string,
     *   searchParam: string,
     *   searchValue: string,
     *   searchPlaceholder?: string,
     *   criteria: array<string, string>,
     *   listState: array<string, string>,
     *   filterDialogId?: string,
     *   advancedCount?: int,
     *   extraButtons?: array<int, array{label: string, icon?: string, dialogId?: string, href?: string}>,
     *   headerActions?: string,
     * } $config
     */
    public static function searchToolbar(array $config): string
    {
        $searchUrl         = (string) ($config['searchUrl'] ?? '');
        $searchParam       = (string) ($config['searchParam'] ?? 'customer_name');
        $searchValue       = (string) ($config['searchValue'] ?? '');
        $searchPlaceholder = (string) ($config['searchPlaceholder'] ?? '検索');
        $criteria          = is_array($config['criteria'] ?? null) ? $config['criteria'] : [];
        $listState         = is_array($config['listState'] ?? null) ? $config['listState'] : [];
        $filterDialogId    = (string) ($config['filterDialogId'] ?? '');
        $advancedCount     = (int) ($config['advancedCount'] ?? 0);
        $extraButtons      = is_array($config['extraButtons'] ?? null) ? $config['extraButtons'] : [];
        $headerActions     = (string) ($config['headerActions'] ?? '');

        // 検索バーの form は検索パラメータ以外を hidden で温存
        $criteriaForHidden = $criteria;
        unset($criteriaForHidden[$searchParam]);

        // 左側（検索バー + 絞込ボタン）
        $leftHtml = '<div class="list-toolbar-left">'
            . '<form class="list-toolbar-search-form" method="get" action="' . Layout::escape(self::formAction($searchUrl)) . '" role="search" aria-label="' . Layout::escape($searchPlaceholder) . '" data-auto-submit>'
            . self::routeInput($searchUrl)
            . self::hiddenInputs(self::queryParams($criteriaForHidden, $listState, false, true))
            . '<div class="list-toolbar-search">'
            . '<span class="list-toolbar-search-icon">' . self::ICON_SEARCH . '</span>'
            . '<input type="text" name="' . Layout::escape($searchParam) . '" placeholder="' . Layout::escape($searchPlaceholder) . '" value="' . Layout::escape($searchValue) . '" aria-label="' . Layout::escape($searchPlaceholder) . '" autocomplete="off">'
            . '</div>'
            . '</form>';

        if ($filterDialogId !== '') {
            $activeCls = $advancedCount > 0 ? ' has-active' : '';
            $leftHtml .= '<button type="button" class="filter-btn' . $activeCls
                . '" data-open-dialog="' . Layout::escape($filterDialogId) . '" aria-label="絞り込み条件を開く">'
                . self::ICON_FILTER
                . '<span>絞込</span>'
                . ($advancedCount > 0 ? '<span class="filter-btn-count">' . $advancedCount . '</span>' : '')
                . '</button>';
        }

        $leftHtml .= '</div>';

        // 右側（extraButtons + headerActions）
        $rightHtml = '';
        foreach ($extraButtons as $btn) {
            $label    = (string) ($btn['label'] ?? '');
            $iconName = (string) ($btn['icon'] ?? '');
            $dialogId = (string) ($btn['dialogId'] ?? '');
            $href     = (string) ($btn['href'] ?? '');
            if ($label === '') {
                continue;
            }
            $iconSvg = self::iconSvg($iconName);
            $inner   = $iconSvg . '<span>' . Layout::escape($label) . '</span>';

            if ($dialogId !== '') {
                $rightHtml .= '<button type="button" class="filter-btn filter-btn-csv" data-open-dialog="'
                    . Layout::escape($dialogId) . '" aria-label="' . Layout::escape($label) . '">'
                    . $inner . '</button>';
            } elseif ($href !== '') {
                $rightHtml .= '<a class="filter-btn filter-btn-csv" href="' . Layout::escape($href) . '">'
                    . $inner . '</a>';
            }
        }

        if ($headerActions !== '') {
            $rightHtml .= $headerActions;
        }

        $html = '<div class="list-toolbar-bar">' . $leftHtml;
        if ($rightHtml !== '') {
            $html .= '<div class="list-toolbar-right">' . $rightHtml . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * クイックフィルタタブ（`.quick-filter-tabs` ピル型チップ）を生成する。
     * 「すべて」含む複数タブを同じ行に並べ、アクティブタブは青背景・件数バッジ付き。
     *
     * @param array{
     *   currentKey: string,
     *   tabs: array<string, array{label: string, countKey?: string}>,
     *   counts?: array<string, int>,
     *   paramName?: string,
     *   searchUrl: string,
     *   criteria: array<string, string>,
     *   listState: array<string, string>,
     * } $config
     */
    public static function quickFilterTabs(array $config): string
    {
        $currentKey = (string) ($config['currentKey'] ?? '');
        $tabs       = is_array($config['tabs'] ?? null) ? $config['tabs'] : [];
        $counts     = is_array($config['counts'] ?? null) ? $config['counts'] : [];
        $paramName  = (string) ($config['paramName'] ?? 'quick_filter');
        $searchUrl  = (string) ($config['searchUrl'] ?? '');
        $criteria   = is_array($config['criteria'] ?? null) ? $config['criteria'] : [];
        $listState  = is_array($config['listState'] ?? null) ? $config['listState'] : [];

        if ($tabs === []) {
            return '';
        }

        $html = '<nav class="quick-filter-tabs" role="tablist" aria-label="クイックフィルタ">';
        foreach ($tabs as $key => $def) {
            $keyStr   = (string) $key;
            $label    = (string) ($def['label'] ?? $keyStr);
            $countKey = (string) ($def['countKey'] ?? ($keyStr === '' ? 'all' : $keyStr));

            // アクティブ判定: '' と 'all' は同一視
            $isActive = ($currentKey === $keyStr)
                || ($keyStr === '' && $currentKey === 'all')
                || ($keyStr === 'all' && $currentKey === '');

            // URL: 現 criteria + paramName 置換、page=1 リセット
            $targetCriteria = array_merge($criteria, [$paramName => $keyStr]);
            $targetState    = array_merge($listState, ['page' => '1']);
            $tabUrl         = ListViewHelper::buildUrl(
                $searchUrl,
                self::queryParams($targetCriteria, $targetState)
            );

            $count = (int) ($counts[$countKey] ?? 0);

            $html .= '<a class="quick-filter-tab' . ($isActive ? ' is-active' : '')
                . '" href="' . Layout::escape($tabUrl) . '"'
                . ' role="tab" aria-selected="' . ($isActive ? 'true' : 'false') . '">'
                . '<span class="quick-filter-tab-label">' . Layout::escape($label) . '</span>'
                . '<span class="quick-filter-tab-count">' . $count . '</span>'
                . '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * フィルタダイアログ `<dialog>` を生成する。
     * fields は `label + html` のペア配列で受け取り、`.filter-form-grid` の 2 カラムに並べる。
     *
     * @param array{
     *   id: string,
     *   title?: string,
     *   searchUrl: string,
     *   listState: array<string, string>,
     *   preserveCriteria?: array<string, string>,
     *   fields: array<int, array{label: string, html: string, full?: bool}>,
     *   clearUrl?: string,
     *   applyLabel?: string,
     *   clearLabel?: string,
     * } $config
     */
    public static function filterDialog(array $config): string
    {
        $id               = (string) ($config['id'] ?? '');
        if ($id === '') {
            return '';
        }
        $title            = (string) ($config['title'] ?? '絞り込み条件');
        $searchUrl        = (string) ($config['searchUrl'] ?? '');
        $listState        = is_array($config['listState'] ?? null) ? $config['listState'] : [];
        $preserveCriteria = is_array($config['preserveCriteria'] ?? null) ? $config['preserveCriteria'] : [];
        $fields           = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $clearUrl         = (string) ($config['clearUrl'] ?? $searchUrl);
        $applyLabel       = (string) ($config['applyLabel'] ?? '適用');
        $clearLabel       = (string) ($config['clearLabel'] ?? 'クリア');

        $fieldsHtml = '';
        foreach ($fields as $field) {
            $label = (string) ($field['label'] ?? '');
            $inner = (string) ($field['html']  ?? '');
            $full  = (bool)   ($field['full']  ?? false);
            $cls   = 'filter-form-field' . ($full ? ' filter-form-field--full' : '');
            $fieldsHtml .= '<label class="' . $cls . '">'
                . '<span class="filter-form-field-label">' . Layout::escape($label) . '</span>'
                . $inner
                . '</label>';
        }

        // preserveCriteria は form 内 hidden として送信（quick_filter などタブ選択保持用）
        $preserveHtml = '';
        foreach ($preserveCriteria as $name => $value) {
            $v = (string) $value;
            if ($v === '') {
                continue;
            }
            $preserveHtml .= '<input type="hidden" name="' . Layout::escape((string) $name) . '" value="' . Layout::escape($v) . '">';
        }

        return '<dialog id="' . Layout::escape($id) . '" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form">'
            . '<button type="submit" class="modal-close" aria-label="閉じる">×</button>'
            . '</form>'
            . '<div class="modal-head"><h2>' . Layout::escape($title) . '</h2></div>'
            . '<form method="get" action="' . Layout::escape(self::formAction($searchUrl)) . '">'
            . self::routeInput($searchUrl)
            . self::hiddenInputs(self::queryParams([], $listState, false, true))
            . $preserveHtml
            . '<div class="filter-form-grid">' . $fieldsHtml . '</div>'
            . '<div class="dialog-actions" style="margin-top:16px;">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($clearUrl) . '">' . Layout::escape($clearLabel) . '</a>'
            . '<button class="btn btn-primary" type="submit">' . Layout::escape($applyLabel) . '</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';
    }

    /**
     * 複数のダイアログを一括で制御する共通 JavaScript を生成する。
     * `data-open-dialog="id"` と `data-close-dialog="id"` を自動検出。
     * 背景クリック / ESC / 閉じるボタンに対応、オプションで auto-open ID 指定可能。
     * 同時に検索バー自動送信（data-auto-submit form + autofocus 復元）と
     * CSV dropzone（data-csv-form）ハンドラも含める（共通 JS バンドル）。
     *
     * @param array<int, string> $dialogIds
     */
    public static function dialogScript(array $dialogIds, ?string $autoOpenId = null): string
    {
        $bindCalls = '';
        foreach ($dialogIds as $dlgId) {
            $dlgId = (string) $dlgId;
            if ($dlgId === '') {
                continue;
            }
            $bindCalls .= 'bind(document.getElementById(' . json_encode($dlgId) . '));';
        }

        $autoOpenJs = '';
        if ($autoOpenId !== null && $autoOpenId !== '') {
            $autoOpenJs = 'var d=document.getElementById(' . json_encode($autoOpenId) . ');'
                . 'if(d&&typeof d.showModal==="function")d.showModal();';
        }

        return '<script>'
            // 共通ダイアログハンドラ
            . '(function(){function bind(dlg){if(!dlg||typeof dlg.showModal!=="function")return;var id=dlg.id;'
            . 'document.querySelectorAll("[data-open-dialog=\""+id+"\"]").forEach(function(b){b.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]").forEach(function(b){b.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . 'dlg.addEventListener("click",function(e){var r=dlg.getBoundingClientRect();var inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open)dlg.close();});}'
            . $bindCalls
            . $autoOpenJs
            . '})();'
            // 検索バー自動送信（data-auto-submit）
            . '(function(){var form=document.querySelector("form[data-auto-submit]");if(!form)return;'
            . 'var input=form.querySelector("input[type=\"text\"]");if(!input)return;var timer=null;'
            . 'input.addEventListener("input",function(){if(timer)clearTimeout(timer);timer=setTimeout(function(){'
            . 'var af=form.querySelector("input[name=\"autofocus\"]");if(!af){af=document.createElement("input");af.type="hidden";af.name="autofocus";form.appendChild(af);}af.value="search";'
            . 'form.submit();'
            . '},500);});'
            . 'try{var u=new URL(window.location.href);if(u.searchParams.get("autofocus")==="search"){input.focus();var v=input.value;input.value="";input.value=v;u.searchParams.delete("autofocus");history.replaceState(null,"",u.toString());}}catch(e){}'
            . '})();'
            // CSV ドラッグ&ドロップ + ファイル選択 UI（data-csv-form がある画面のみ）
            . '(function(){var form=document.querySelector("form[data-csv-form]");if(!form)return;'
            . 'var drop=form.querySelector("[data-csv-dropzone]");var input=form.querySelector("[data-csv-input]");'
            . 'var selected=form.querySelector("[data-csv-selected]");var selectedName=form.querySelector("[data-csv-selected-name]");'
            . 'var submitBtn=form.querySelector("[data-csv-submit]");if(!drop||!input)return;'
            . 'function updateSelected(){if(input.files&&input.files.length>0){var f=input.files[0];if(selectedName)selectedName.textContent=f.name;if(selected)selected.hidden=false;drop.classList.add("has-file");if(submitBtn)submitBtn.disabled=false;}else{if(selected)selected.hidden=true;drop.classList.remove("has-file");if(submitBtn)submitBtn.disabled=true;}}'
            . 'input.addEventListener("change",updateSelected);'
            . '["dragenter","dragover"].forEach(function(ev){drop.addEventListener(ev,function(e){e.preventDefault();e.stopPropagation();drop.classList.add("is-dragover");});});'
            . '["dragleave","drop"].forEach(function(ev){drop.addEventListener(ev,function(e){e.preventDefault();e.stopPropagation();drop.classList.remove("is-dragover");});});'
            . 'drop.addEventListener("drop",function(e){var dt=e.dataTransfer;if(!dt||!dt.files||dt.files.length===0)return;'
            . 'var f=dt.files[0];var nm=(f.name||"").toLowerCase();if(!nm.endsWith(".csv")){alert("CSVファイルを選択してください");return;}'
            . 'input.files=dt.files;updateSelected();});'
            . 'updateSelected();'
            . '})();'
            . '</script>';
    }

    /**
     * モバイル用のカードリスト `<ol class="list-card-list list-mobile-only">` を生成する。
     * $cardBuilder は 1 行を受け取り、`.list-card` の内部 HTML（<li> は自動包む）を返す関数。
     *
     * @param array<int, array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $cardBuilder
     */
    public static function mobileCardList(array $rows, callable $cardBuilder, string $ariaLabel = '一覧（モバイル表示）'): string
    {
        if ($rows === []) {
            return '<ol class="list-card-list list-mobile-only" aria-label="' . Layout::escape($ariaLabel) . '">'
                . '<li class="list-card"><div class="muted" style="text-align:center;padding:14px;">該当データはありません。</div></li>'
                . '</ol>';
        }

        $items = '';
        foreach ($rows as $row) {
            $items .= $cardBuilder($row);
        }

        return '<ol class="list-card-list list-mobile-only" aria-label="' . Layout::escape($ariaLabel) . '">'
            . $items
            . '</ol>';
    }

    // ─────────────────────────────────────────────────────────────────
    // 削除確認ダイアログ
    // ─────────────────────────────────────────────────────────────────

    /**
     * 行削除用のゴミ箱ボタンを生成する。LP::deleteConfirmDialog() と対で使用する。
     * data-delete-id に行 ID、data-delete-name に確認メッセージ用の識別名を持つ。
     */
    public static function deleteButton(int $id, string $name): string
    {
        return '<button type="button" class="btn-icon-delete" title="削除"'
            . ' data-delete-id="' . $id . '"'
            . ' data-delete-name="' . Layout::escape($name) . '">'
            . self::ICON_TRASH
            . '</button>';
    }

    /**
     * 共通削除確認ダイアログ + 自己完結 JS を生成する。
     * 各行の LP::deleteButton() が data-delete-id / data-delete-name を持ち、
     * JS が hidden フォームの id フィールドを書き換えて submit する。
     * LP::dialogScript() とは独立して動作する。
     *
     * $config = [
     *   'deleteUrl'  => '?route=accident/delete',   // フォーム action（必須）
     *   'csrfToken'  => $deleteCsrfToken,            // CSRF トークン（必須）
     *   'listQuery'  => $listQuery,                  // 一覧戻り用パラメータ（任意、デフォルト []）
     *   'title'      => '削除の確認',                // ダイアログ見出し（任意）
     * ]
     *
     * @param array{deleteUrl: string, csrfToken: string, listQuery?: array<string,string>, title?: string} $config
     */
    public static function deleteConfirmDialog(array $config): string
    {
        $deleteUrl = (string) ($config['deleteUrl'] ?? '');
        $csrfToken = (string) ($config['csrfToken'] ?? '');
        $listQuery = is_array($config['listQuery'] ?? null) ? $config['listQuery'] : [];
        $title     = (string) ($config['title'] ?? '削除の確認');

        if ($deleteUrl === '' || $csrfToken === '') {
            return '';
        }

        return '<dialog id="lp-delete-confirm" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form">'
            . '<button type="submit" class="modal-close" aria-label="閉じる">×</button>'
            . '</form>'
            . '<div class="modal-head"><h2>' . Layout::escape($title) . '</h2></div>'
            . '<p id="lp-delete-confirm-msg" style="margin:16px 0;"></p>'
            . '<form method="post" action="' . Layout::escape(self::formAction($deleteUrl)) . '" id="lp-delete-confirm-form">'
            . self::routeInput($deleteUrl)
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="id" id="lp-delete-confirm-id" value="">'
            . self::hiddenInputs($listQuery)
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-ghost" id="lp-delete-confirm-cancel">キャンセル</button>'
            . '<button type="submit" class="btn btn-danger">削除する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("lp-delete-confirm");'
            . 'var msg=document.getElementById("lp-delete-confirm-msg");'
            . 'var idIn=document.getElementById("lp-delete-confirm-id");'
            . 'var cancel=document.getElementById("lp-delete-confirm-cancel");'
            . 'if(!dlg||!msg||!idIn){return;}'
            . 'function closeDlg(){if(dlg.open){dlg.close();}}'
            . 'document.querySelectorAll("[data-delete-id]").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'idIn.value=btn.getAttribute("data-delete-id")||"";'
            . 'var name=btn.getAttribute("data-delete-name")||"この件";'
            . 'msg.textContent="「"+name+"」を削除しますか？この操作は取り消せません。";'
            . 'if(!dlg.open){dlg.showModal();}'
            . '});});'
            . 'if(cancel){cancel.addEventListener("click",closeDlg);}'
            . 'dlg.addEventListener("click",function(e){'
            . 'var r=dlg.getBoundingClientRect();'
            . 'var inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;'
            . 'if(!inside&&dlg.open){dlg.close();}'
            . '});'
            . '})();</script>';
    }

    // ─────────────────────────────────────────────────────────────────
    // SVG アイコン定数（ツールバー・ボタン等で共有）
    // ─────────────────────────────────────────────────────────────────

    private const ICON_SEARCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
    private const ICON_FILTER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>';
    private const ICON_UPLOAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
    private const ICON_PLUS   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
    private const ICON_DOWNLOAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    private const ICON_TRASH    = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';

    /**
     * アイコン名から SVG 文字列を取得する。未知の名前は空文字を返す。
     */
    private static function iconSvg(string $name): string
    {
        return match ($name) {
            'search'   => self::ICON_SEARCH,
            'filter'   => self::ICON_FILTER,
            'upload'   => self::ICON_UPLOAD,
            'plus'     => self::ICON_PLUS,
            'download' => self::ICON_DOWNLOAD,
            'trash'    => self::ICON_TRASH,
            default    => '',
        };
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
