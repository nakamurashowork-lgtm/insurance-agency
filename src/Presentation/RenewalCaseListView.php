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
     * @param array<int, string> $allUsers
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
        string $deleteActionUrl,
        string $deleteCsrfToken,
        ?string $importFlashError,
        ?string $importFlashSuccess,
        ?array $importBatch,
        array $importRows,
        bool $openImportDialog,
        ?string $errorMessage,
        bool $forceFilterOpen,
        array $allUsers,
        array $layoutOptions,
        ?string $pageSuccessMessage = null
    ): string
    {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $pageSuccessHtml = '';
        if (is_string($pageSuccessMessage) && $pageSuccessMessage !== '') {
            $pageSuccessHtml = '<div class="notice">' . Layout::escape($pageSuccessMessage) . '</div>';
        }

        $customerName      = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo          = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $caseStatus        = (string) ($criteria['case_status'] ?? '');
        $maturityWindow    = (string) ($criteria['maturity_window'] ?? '30');
        $filterUserId      = (string) ($criteria['assigned_user_id'] ?? '');
        $filterProductType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort = (string) ($listState['sort'] ?? '');
        $direction = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters(array_diff_key($criteria, ['maturity_window' => true])) || $errorHtml !== '';
        $pager = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery = self::buildListQueryParams($criteria, $listState);

        $rowsHtml = '';
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $id               = (int) ($row['renewal_case_id'] ?? 0);
            $status           = (string) ($row['case_status'] ?? '');
            $customerText     = (string) ($row['customer_name'] ?? '');
            $policyText       = (string) ($row['policy_no'] ?? '');
            $productType      = (string) ($row['product_type'] ?? '');
            $maturityDate     = (string) ($row['maturity_date'] ?? '');
            $earlyDeadline    = (string) ($row['early_renewal_deadline'] ?? '');
            $assignedUserName = (string) ($row['assigned_user_name'] ?? '');
            $detailUrl        = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $rowClass         = self::isCompletedStatus($status) ? ' class="is-completed-row"' : '';

            $deleteForm = '<form method="post" action="' . Layout::escape($deleteActionUrl) . '" style="display:inline;">'
                . self::renderRouteInput($deleteActionUrl)
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrfToken) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . self::renderHiddenInputs(self::buildListQueryParams($criteria, $listState))
                . '<button type="submit" class="btn-icon-delete" title="削除" onclick="return confirm(\'この満期案件を削除しますか？\')">🗑</button>'
                . '</form>';

            $rowsHtml .= '<tr' . $rowClass . '>'
                . '<td data-label="証券番号"><a class="text-link list-policy-text" href="' . $detailUrl . '" title="' . Layout::escape($policyText) . '">' . Layout::escape($policyText) . '</a></td>'
                . '<td data-label="顧客名"><strong class="truncate list-row-primary" title="' . Layout::escape($customerText) . '">' . Layout::escape($customerText) . '</strong></td>'
                . '<td data-label="種目">' . Layout::escape($productType) . '</td>'
                . '<td data-label="満期日">' . self::renderMaturityDate($maturityDate, $status, $today) . '</td>'
                . '<td data-label="残日数">' . self::renderDaysRemaining($maturityDate, $status, $today) . '</td>'
                . '<td data-label="早期更改締切">' . self::renderEarlyDeadline($earlyDeadline, $status, $today) . '</td>'
                . '<td data-label="営業担当">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : '<span class="muted">−</span>') . '</td>'
                . '<td data-label="対応状況">' . self::renderStatusBadge($status) . '</td>'
                . '<td>' . $deleteForm . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="9">該当データはありません。</td></tr>';
        }

        $statuses = ['' => 'すべて', 'not_started' => '未対応', 'sj_requested' => 'SJ依頼中', 'doc_prepared' => '書類作成済', 'waiting_return' => '返送待ち', 'quote_sent' => '見積送付済', 'waiting_payment' => '入金待ち', 'completed' => '完了'];
        $statusOptions = '';
        foreach ($statuses as $value => $label) {
            $selected = $caseStatus === $value ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $userOptions = '<option value="">全担当者</option>';
        foreach ($allUsers as $uid => $uname) {
            $selected = $filterUserId === (string) $uid ? ' selected' : '';
            $userOptions .= '<option value="' . Layout::escape((string) $uid) . '"' . $selected . '>' . Layout::escape($uname) . '</option>';
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
            . $pageSuccessHtml
            . '<div class="list-page-frame">'
            . '<div class="list-page-header">'
            . '<h1 class="title">満期一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<button class="btn btn-primary" type="button" data-open-dialog="renewal-import-dialog">+ CSV取込</button>'
            . '</div>'
            . '</div>'
            . '<dialog id="renewal-import-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>SJNET満期データ取込</h2></div>'
            . $importErrorHtml
            . $importSuccessHtml
            . $importResultHtml
            . '<form method="post" action="' . Layout::escape($csvImportActionUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csvImportCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($importReturnToUrl) . '">'
            . '<p class="muted" style="margin-bottom:10px;font-size:12.5px;">SJ-NET「満期進捗管理」→「契約一覧表」からダウンロードしたCSVを選択してください。文字コードはShift-JIS・UTF-8どちらも対応しています。</p>'
            . '<label class="list-filter-field"><span>CSVファイル（44列）</span><input type="file" name="csv_file" accept=".csv,text/csv" required></label>'
            . '<div class="actions" style="margin-top:12px;">'
            . '<button class="btn btn-primary" type="submit">取込を実行する</button>'
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
            . '<label class="list-filter-field"><span>担当者</span><select name="assigned_user_id">' . $userOptions . '</select></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $filterProductType . '"></label>'
            . '<label class="list-filter-field"><span>対応状況</span><select name="case_status">' . $statusOptions . '</select></label>'
            . '<label class="list-filter-field"><span>満期日</span><select name="maturity_window">'
            . self::renderWindowOptions($maturityWindow)
            . '</select></label>'
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
            . '<col class="list-col-policy">'
            . '<col class="list-col-customer">'
            . '<col class="list-col-product">'
            . '<col class="list-col-date">'
            . '<col class="list-col-days">'
            . '<col class="list-col-early">'
            . '<col class="list-col-user">'
            . '<col class="list-col-status">'
            . '<col style="width:40px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . self::renderSortLink('証券番号', 'policy_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('満期日', 'maturity_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>残日数</th>'
            . '<th>' . self::renderSortLink('早期更改締切', 'early_renewal_deadline', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>営業担当</th>'
            . '<th>' . self::renderSortLink('対応状況', 'case_status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th></th>'
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
        [$label, $badgeClass] = match ($status) {
            'not_started'    => ['未対応',    'badge-gray'],
            'sj_requested'   => ['SJ依頼中',  'badge-info'],
            'doc_prepared'   => ['書類作成済', 'badge-info'],
            'waiting_return' => ['返送待ち',  'badge-warn'],
            'quote_sent'     => ['見積送付済', 'badge-info'],
            'waiting_payment' => ['入金待ち', 'badge-warn'],
            'completed'      => ['完了',      'badge-success'],
            default          => ['未設定',    'badge-gray'],
        };

        return '<span class="badge ' . $badgeClass . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderNextActionDate(string $nextActionDate, string $status, string $today): string
    {
        $normalized = trim($nextActionDate);
        if ($normalized === '') {
            return '<span class="muted">未設定</span>';
        }

        $isClosed = $status === 'completed';
        if (!$isClosed && $normalized < $today) {
            return '<div class="cell-stack"><span class="badge badge-danger">期限超過</span><span class="warning-text">' . Layout::escape($normalized) . '</span></div>';
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
            'customer_name'          => '顧客名',
            'policy_no'              => '証券番号',
            'maturity_date'          => '満期日',
            'case_status'            => '対応状況',
            'next_action_date'       => '次回対応予定日',
            'product_type'           => '種目',
            'early_renewal_deadline' => '早期更改締切',
            default => '業務優先順',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    private static function isCompletedStatus(string $status): bool
    {
        return $status === 'completed';
    }

    private static function renderMaturityDate(string $maturityDate, string $status, string $today): string
    {
        if ($maturityDate === '') {
            return '<span class="muted">−</span>';
        }
        if ($status !== 'completed' && $maturityDate < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($maturityDate) . '</span>';
        }
        return Layout::escape($maturityDate);
    }

    private static function renderDaysRemaining(string $maturityDate, string $status, string $today): string
    {
        if ($maturityDate === '') {
            return '<span class="muted">−</span>';
        }
        $diff = (int) round((strtotime($maturityDate) - strtotime($today)) / 86400);
        if ($status === 'completed') {
            return Layout::escape((string) $diff . '日');
        }
        if ($diff < 0) {
            return '<span style="color:var(--text-danger);">' . Layout::escape((string) $diff . '日') . '</span>';
        }
        return Layout::escape((string) $diff . '日');
    }

    private static function renderEarlyDeadline(string $earlyDeadline, string $status, string $today): string
    {
        if ($earlyDeadline === '') {
            return '<span class="muted">−</span>';
        }
        if ($status !== 'completed' && $earlyDeadline < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($earlyDeadline) . '</span>';
        }
        if ($status !== 'completed' && $earlyDeadline <= date('Y-m-d', strtotime($today . ' +7 days'))) {
            return '<span style="color:var(--text-warning);">' . Layout::escape($earlyDeadline) . '</span>';
        }
        return Layout::escape($earlyDeadline);
    }

    private static function renderWindowOptions(string $current): string
    {
        $options = ['30' => '満期：今後30日', '60' => '今後60日', '90' => '今後90日', 'all' => '全期間'];
        $html = '';
        foreach ($options as $value => $label) {
            $valueStr = (string) $value;
            $selected = $current === $valueStr ? ' selected' : '';
            $html .= '<option value="' . Layout::escape($valueStr) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }
        return $html;
    }

    private static function renderStatusMetric(string $label, int $count): string
    {
        return '<div class="metric">'
            . '<div class="metric-label">' . Layout::escape($label) . '</div>'
            . '<div class="metric-value" style="font-size:20px;">' . $count . '</div>'
            . '</div>';
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

        $status           = (string) ($importBatch['import_status'] ?? '-');
        $totalRows        = (int) ($importBatch['total_row_count'] ?? 0);
        $insertCount      = (int) ($importBatch['insert_count'] ?? 0);
        $updateCount      = (int) ($importBatch['update_count'] ?? 0);
        $customerInsert   = (int) ($importBatch['customer_insert_count'] ?? 0);
        $skipCount        = (int) ($importBatch['duplicate_skip_count'] ?? 0);
        $errorCount       = (int) ($importBatch['error_count'] ?? 0);

        $statusLabel = match ($status) {
            'success' => '完了',
            'partial' => '一部エラーあり',
            'failed'  => '失敗',
            default   => $status,
        };
        $statusClass = match ($status) {
            'success' => 'badge-success',
            'partial' => 'badge-warn',
            default   => 'badge-danger',
        };

        // 担当者マッピング集計（渡されたrows から計算）
        $resolvedCount   = 0;
        $unresolvedCount = 0;
        $inactiveCount   = 0;
        foreach ($importRows as $row) {
            $ms = (string) ($row['staff_mapping_status'] ?? '');
            if ($ms === 'resolved')       $resolvedCount++;
            elseif ($ms === 'unresolved') $unresolvedCount++;
            elseif ($ms === 'inactive')   $inactiveCount++;
        }

        $summary = '<div class="modal-result" style="margin-bottom:14px;">'
            . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">'
            . '<span style="font-weight:600;">取込結果</span>'
            . '<span class="badge ' . $statusClass . '">' . Layout::escape($statusLabel) . '</span>'
            . '</div>'
            . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:12.5px;">'
            . '<div><span class="muted">処理行数</span><span style="margin-left:8px;font-weight:500;">' . $totalRows . '行</span></div>'
            . '<div><span class="muted">契約 新規登録</span><span style="margin-left:8px;font-weight:500;">' . $insertCount . '件</span></div>'
            . '<div><span class="muted">契約 更新</span><span style="margin-left:8px;font-weight:500;">' . $updateCount . '件</span></div>'
            . '<div><span class="muted">顧客 自動登録</span><span style="margin-left:8px;font-weight:500;">' . $customerInsert . '件</span></div>'
            . '<div><span class="muted">スキップ</span><span style="margin-left:8px;">' . $skipCount . '行</span></div>'
            . '<div><span class="muted">エラー</span><span style="margin-left:8px;' . ($errorCount > 0 ? 'color:var(--text-danger);font-weight:500;' : '') . '">' . $errorCount . '行</span></div>'
            . '</div>'
            . '<hr style="margin:10px 0;border:none;border-top:1px solid var(--border-color);">'
            . '<div style="font-size:12.5px;font-weight:600;margin-bottom:4px;">担当者マッピング</div>'
            . '<div style="display:flex;flex-direction:column;gap:3px;font-size:12.5px;">'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">解決済み</span><span style="font-weight:500;">' . $resolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">コード未登録</span><span style="' . ($unresolvedCount > 0 ? 'color:var(--text-warning);font-weight:500;' : '') . '">' . $unresolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">無効コード</span><span>' . $inactiveCount . '件</span></div>'
            . '</div>'
            . '</div>';

        // 警告メッセージ
        $warnings = '';
        if ($unresolvedCount > 0) {
            $warnings .= '<div class="error" style="margin-bottom:10px;font-size:12.5px;">'
                . Layout::escape($unresolvedCount . '件の代理店コードがマッピング未登録です。テナント設定 > SJNETコード設定 で登録してください。')
                . '</div>';
        }

        // エラー行一覧
        $ambiguousCount = 0;
        $errorRowsHtml  = '';
        foreach ($importRows as $row) {
            if ((string) ($row['row_status'] ?? '') !== 'error') {
                continue;
            }
            $errMsg = (string) ($row['error_message'] ?? '');
            if (str_contains($errMsg, 'ambiguous_customer')) {
                $ambiguousCount++;
            }
            $errorRowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['row_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['policy_no'] ?? '−')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td><span class="truncate">' . Layout::escape($errMsg) . '</span></td>'
                . '</tr>';
        }

        if ($ambiguousCount > 0) {
            $warnings .= '<div class="error" style="margin-bottom:10px;font-size:12.5px;">'
                . Layout::escape($ambiguousCount . '件の顧客名が複数一致しました。該当行の契約・満期案件は登録されていません。顧客一覧で名寄せを行ってから、手動で登録してください。')
                . '</div>';
        }

        if ($errorRowsHtml === '') {
            return $summary . $warnings;
        }

        return $summary
            . $warnings
            . '<details class="details-panel modal-help" open>'
            . '<summary>エラー行を確認（' . $errorCount . '件）</summary>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card">'
            . '<thead><tr><th>行</th><th>証券番号</th><th>顧客名</th><th>エラー内容</th></tr></thead>'
            . '<tbody>' . $errorRowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</details>';
    }
}