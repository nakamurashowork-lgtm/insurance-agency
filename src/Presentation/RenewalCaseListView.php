<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class RenewalCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        string $searchUrl,
        string $detailBaseUrl,
        string $csvImportActionUrl,
        string $csvImportCsrfToken,
        ?string $importFlashError,
        ?string $importFlashSuccess,
        ?array $importBatch,
        array $importRows,
        bool $openImportDialog,
        ?string $errorMessage,
        bool $forceFilterOpen,
        array $layoutOptions
    ): string
    {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $caseStatus = (string) ($criteria['case_status'] ?? '');
        $from = Layout::escape((string) ($criteria['maturity_date_from'] ?? ''));
        $to = Layout::escape((string) ($criteria['maturity_date_to'] ?? ''));
        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort = (string) ($listState['sort'] ?? '');
        $direction = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $errorHtml !== '';
        $pager = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery = self::buildListQueryParams($criteria, $listState);

        $rowsHtml = '';
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $id = (int) ($row['renewal_case_id'] ?? 0);
            $status = (string) ($row['case_status'] ?? '');
            $customerText = (string) ($row['customer_name'] ?? '');
            $policyText = (string) ($row['policy_no'] ?? '');
            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $rowClass = self::isCompletedStatus($status) ? ' class="is-completed-row"' : '';
            $rowsHtml .= '<tr' . $rowClass . '>'
                . '<td data-label="顧客名">'
                . '<div class="cell-stack list-row-stack">'
                . '<strong class="truncate list-row-primary" title="' . Layout::escape($customerText) . '">' . Layout::escape($customerText) . '</strong>'
                . '</div>'
                . '</td>'
                . '<td data-label="証券番号"><span class="list-policy-text" title="' . Layout::escape($policyText) . '">' . Layout::escape($policyText) . '</span></td>'
                . '<td data-label="満期日">' . Layout::escape((string) ($row['maturity_date'] ?? '')) . '</td>'
                . '<td data-label="対応ステータス">' . self::renderStatusBadge($status) . '</td>'
                . '<td data-label="次回対応予定">' . self::renderNextActionDate((string) ($row['next_action_date'] ?? ''), $status, $today) . '</td>'
                . '<td data-label="操作" class="cell-action"><a class="text-link" href="' . $detailUrl . '">詳細を開く</a></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当データはありません。</td></tr>';
        }

        $statuses = ['' => 'すべて', 'open' => '未対応', 'contacted' => '対応中', 'quoted' => '見積提示', 'waiting' => '回答待ち', 'renewed' => '完了', 'lost' => '失注', 'closed' => '終了'];
        $statusOptions = '';
        foreach ($statuses as $value => $label) {
            $selected = $caseStatus === $value ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar = self::renderToolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = self::renderBottomPager($searchUrl, $criteria, $listState, $pager);
        $importResultHtml = self::renderImportResult($importBatch, $importRows);
        $importErrorHtml = '';
        if (is_string($importFlashError) && $importFlashError !== '') {
            $importErrorHtml = '<div class="error">' . Layout::escape($importFlashError) . '</div>';
        }
        $importSuccessHtml = '';
        if (is_string($importFlashSuccess) && $importFlashSuccess !== '') {
            $importSuccessHtml = '<div class="notice">' . Layout::escape($importFlashSuccess) . '</div>';
        }
        $importReturnToUrl = ListViewHelper::buildUrl($searchUrl, array_merge($listQuery, ['import_dialog' => '1']));

        $content = ''
            . '<div class="list-page-frame">'
            . '<div class="list-page-header">'
            . '<h1 class="title">満期一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<button class="btn btn-aux" type="button" data-open-dialog="renewal-import-dialog">CSV取込</button>'
            . '</div>'
            . '</div>'
            . '<dialog id="renewal-import-dialog" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>CSV取込</h2></div>'
            . '<p class="muted">ヘッダ付きCSVを取り込みます。必要な列を含むCSVを選択してください。</p>'
            . $importErrorHtml
            . $importSuccessHtml
            . $importResultHtml
            . '<form method="post" action="' . Layout::escape($csvImportActionUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csvImportCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($importReturnToUrl) . '">'
            . '<label class="list-filter-field"><span>CSVファイル</span><input type="file" name="csv_file" accept=".csv,text/csv" required></label>'
            . '<details class="details-panel modal-help"><summary>必須ヘッダを確認</summary><p class="muted">receipt_no, policy_no, customer_name, maturity_date, performance_date, performance_type, insurance_category, product_type, premium_amount, settlement_month, remark</p></details>'
            . '<div class="actions">'
            . '<button class="btn" type="submit">取込を実行</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="renewal-import-dialog">閉じる</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<details class="card details-panel list-filter-card"' . ($filterOpen ? ' open' : '') . '>'
            . '<summary class="list-filter-toggle"><span class="list-filter-toggle-label is-closed">検索条件を開く</span><span class="list-filter-toggle-label is-open">検索条件を閉じる</span></summary>'
            . $errorHtml
            . '<form method="get" action="' . Layout::escape(self::buildFormAction($searchUrl)) . '">'
            . self::renderRouteInput($searchUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . self::renderHiddenInputs(self::buildListQueryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>顧客名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>対応ステータス</span><select name="case_status">' . $statusOptions . '</select></label>'
            . '<label class="list-filter-field is-date"><span>満期日From</span><input type="date" name="maturity_date_from" value="' . $from . '"></label>'
            . '<label class="list-filter-field is-date"><span>満期日To</span><input type="date" name="maturity_date_to" value="' . $to . '"></label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button>'
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($searchUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</details>'
            . '<div class="card">'
            . $topToolbar
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-renewal">'
            . '<colgroup>'
            . '<col class="list-col-customer">'
            . '<col class="list-col-policy">'
            . '<col class="list-col-date">'
            . '<col class="list-col-status">'
            . '<col class="list-col-next">'
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . self::renderSortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('証券番号', 'policy_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('満期日', 'maturity_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('対応ステータス', 'case_status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('次回対応予定日', 'next_action_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th class="align-right">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $bottomPager
            . '</div>'
            . '</div>'
            . '<script>'
            . '(function(){const id="renewal-import-dialog";const dlg=document.getElementById(id);if(!dlg||typeof dlg.showModal!=="function"){return;}const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const rect=dlg.getBoundingClientRect();const inside=rect.left<=e.clientX&&e.clientX<=rect.right&&rect.top<=e.clientY&&e.clientY<=rect.bottom;if(!inside&&dlg.open){dlg.close();}});if(' . ($openImportDialog ? 'true' : 'false') . '){dlg.showModal();}})();'
            . '</script>';

        return Layout::render('満期一覧', $content, $layoutOptions);
    }

    private static function renderStatusBadge(string $status): string
    {
        $label = match ($status) {
            'open' => '未対応',
            'contacted' => '対応中',
            'quoted' => '見積提示',
            'waiting' => '回答待ち',
            'renewed' => '完了',
            'lost' => '失注',
            'closed' => '終了',
            default => '未設定',
        };
        $class = match ($status) {
            'renewed', 'closed' => 'status-done',
            'contacted', 'quoted', 'waiting' => 'status-progress',
            'lost' => 'status-inactive',
            default => 'status-open',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderNextActionDate(string $nextActionDate, string $status, string $today): string
    {
        $normalized = trim($nextActionDate);
        if ($normalized === '') {
            return '<span class="muted">未設定</span>';
        }

        $isClosed = in_array($status, ['renewed', 'lost', 'closed'], true);
        if (!$isClosed && $normalized < $today) {
            return '<div class="cell-stack"><span class="status-badge status-open">期限超過</span><span class="warning-text">' . Layout::escape($normalized) . '</span></div>';
        }

        return Layout::escape($normalized);
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @return array<string, string>
     */
    private static function buildListQueryParams(array $criteria, array $listState, bool $includePage = true, bool $includeSort = true): array
    {
        $params = $criteria;

        if ($includePage && (int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }

        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }

        if ($includeSort && ($listState['sort'] ?? '') !== '') {
            $params['sort'] = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return $params;
    }

    /**
     * @param array<string, string> $params
     */
    private static function renderHiddenInputs(array $params): string
    {
        $html = '';
        foreach ($params as $name => $value) {
            if (trim($value) === '') {
                continue;
            }

            $html .= '<input type="hidden" name="' . Layout::escape($name) . '" value="' . Layout::escape($value) . '">';
        }

        return $html;
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderToolbar(string $searchUrl, array $criteria, array $listState, array $pager, int $totalCount, int $perPage, string $sortSummary): string
    {
        return '<div class="list-toolbar">'
            . '<div class="list-summary">'
            . '<p class="summary-count">' . Layout::escape(self::renderSummaryText($totalCount, $pager)) . '</p>'
            . '</div>'
            . '<div class="list-toolbar-actions">'
            . '<p class="muted list-sort-summary">' . Layout::escape($sortSummary) . '</p>'
            . self::renderPerPageForm($searchUrl, $criteria, $listState, $perPage)
            . self::renderPager($searchUrl, $criteria, $listState, $pager)
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderBottomPager(string $searchUrl, array $criteria, array $listState, array $pager): string
    {
        $pagerHtml = self::renderPager($searchUrl, $criteria, $listState, $pager);
        if ($pagerHtml === '') {
            return '';
        }

        return '<div class="list-toolbar list-toolbar-bottom"><div class="list-toolbar-actions">' . $pagerHtml . '</div></div>';
    }

    private static function renderSummaryText(int $totalCount, array $pager): string
    {
        if ($totalCount <= 0) {
            return '0件';
        }

        return $totalCount . '件中 ' . (int) ($pager['start'] ?? 0) . '-' . (int) ($pager['end'] ?? 0) . '件を表示';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPerPageForm(string $searchUrl, array $criteria, array $listState, int $perPage): string
    {
        $optionsHtml = '';
        foreach ([10, 50, 100] as $option) {
            $selected = $perPage === $option ? ' selected' : '';
            $optionsHtml .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }

        return '<form method="get" action="' . Layout::escape(self::buildFormAction($searchUrl)) . '" class="list-per-page-form">'
            . self::renderRouteInput($searchUrl)
            . self::renderHiddenInputs(self::buildListQueryParams($criteria, $listState, false))
            . '<label class="list-select-inline"><span>表示件数</span><select name="per_page" onchange="this.form.submit()">' . $optionsHtml . '</select></label>'
            . '<noscript><button class="btn btn-ghost btn-small" type="submit">更新</button></noscript>'
            . '</form>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderPager(string $searchUrl, array $criteria, array $listState, array $pager): string
    {
        if ((int) ($pager['totalPages'] ?? 0) <= 1) {
            return '';
        }

        $links = '';
        if (!empty($pager['hasPrevious'])) {
            $links .= self::renderPagerLink('前へ', (int) ($pager['previousPage'] ?? 1), $searchUrl, $criteria, $listState);
        }

        foreach ((array) ($pager['pages'] ?? []) as $pageNumber) {
            $page = (int) $pageNumber;
            if ($page === (int) ($pager['currentPage'] ?? 1)) {
                $links .= '<span class="list-pager-link is-current">' . $page . '</span>';
                continue;
            }

            $links .= self::renderPagerLink((string) $page, $page, $searchUrl, $criteria, $listState);
        }

        if (!empty($pager['hasNext'])) {
            $links .= self::renderPagerLink('次へ', (int) ($pager['nextPage'] ?? 1), $searchUrl, $criteria, $listState);
        }

        return '<nav class="list-pager" aria-label="ページャー">' . $links . '</nav>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPagerLink(string $label, int $page, string $searchUrl, array $criteria, array $listState): string
    {
        $params = self::buildListQueryParams($criteria, array_merge($listState, ['page' => (string) $page]));
        $url = Layout::escape(ListViewHelper::buildUrl($searchUrl, $params));

        return '<a class="list-pager-link" href="' . $url . '">' . Layout::escape($label) . '</a>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderSortLink(string $label, string $column, string $searchUrl, array $criteria, array $listState): string
    {
        $isCurrent = ($listState['sort'] ?? '') === $column;
        $nextDirection = $isCurrent && ($listState['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        $params = self::buildListQueryParams($criteria, array_merge($listState, ['sort' => $column, 'direction' => $nextDirection]));
        $url = Layout::escape(ListViewHelper::buildUrl($searchUrl, $params));
        $indicator = '';
        if ($isCurrent) {
            $indicator = '<span class="list-sort-indicator">' . (($listState['direction'] ?? 'asc') === 'asc' ? '&#9650;' : '&#9660;') . '</span>';
        }

        return '<a class="list-sort-link' . ($isCurrent ? ' is-active' : '') . '" href="' . $url . '">' . Layout::escape($label) . $indicator . '</a>';
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 業務優先順';
        }

        $label = match ($sort) {
            'customer_name' => '顧客名',
            'policy_no' => '証券番号',
            'maturity_date' => '満期日',
            'case_status' => '対応ステータス',
            'next_action_date' => '次回対応予定日',
            default => '業務優先順',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    private static function isCompletedStatus(string $status): bool
    {
        return $status === 'renewed';
    }

    private static function buildFormAction(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        return $path !== '' ? $path : '';
    }

    private static function renderRouteInput(string $url): string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $route = trim((string) ($params['route'] ?? ''));
        if ($route === '') {
            return '';
        }

        return '<input type="hidden" name="route" value="' . Layout::escape($route) . '">';
    }

    /**
     * @param array<string, mixed>|null $importBatch
     * @param array<int, array<string, mixed>> $importRows
     */
    private static function renderImportResult(?array $importBatch, array $importRows): string
    {
        if (!is_array($importBatch)) {
            return '';
        }

        $summary = '<div class="modal-result">'
            . '<p class="summary-count">取込結果: ' . Layout::escape((string) ($importBatch['import_status'] ?? '-')) . '</p>'
            . '<p class="muted">新規: ' . Layout::escape((string) ($importBatch['insert_count'] ?? '0'))
            . ' / 更新: ' . Layout::escape((string) ($importBatch['update_count'] ?? '0'))
            . ' / エラー: ' . Layout::escape((string) ($importBatch['error_count'] ?? '0')) . '</p>'
            . '</div>';

        if ($importRows === []) {
            return $summary;
        }

        $rowsHtml = '';
        foreach ($importRows as $row) {
            if ((string) ($row['row_status'] ?? '') !== 'error') {
                continue;
            }

            $rowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['row_no'] ?? '')) . '</td>'
                . '<td><span class="truncate" title="' . Layout::escape((string) ($row['policy_no'] ?? '')) . '">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</span></td>'
                . '<td><span class="truncate" title="' . Layout::escape((string) ($row['error_message'] ?? '')) . '">' . Layout::escape((string) ($row['error_message'] ?? '')) . '</span></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            return $summary;
        }

        return $summary
            . '<details class="details-panel modal-help">'
            . '<summary>エラー詳細を確認</summary>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card">'
            . '<thead><tr><th>行</th><th>証券番号</th><th>エラー内容</th></tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</details>';
    }
}