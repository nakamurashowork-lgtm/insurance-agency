<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class AccidentCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        string $searchUrl,
        string $detailBaseUrl,
        string $storeUrl,
        string $createCsrf,
        ?array $createDraft,
        string $openModal,
        array $customerOptions,
        array $currentUser,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess,
        bool $forceFilterOpen,
        array $layoutOptions
    ): string {
        $listErrorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $listErrorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $acceptedDateFrom = Layout::escape((string) ($criteria['accepted_date_from'] ?? ''));
        $acceptedDateTo = Layout::escape((string) ($criteria['accepted_date_to'] ?? ''));
        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $productType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $status = (string) ($criteria['status'] ?? '');
        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort = (string) ($listState['sort'] ?? '');
        $direction = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $listErrorHtml !== '';
        $pager = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery = self::buildListQueryParams($criteria, $listState);

        $statusOptions = [
            '' => 'すべて',
            'accepted' => '受付',
            'linked' => '対応開始',
            'in_progress' => '対応中',
            'waiting_docs' => '保留',
            'resolved' => '解決',
            'closed' => 'クローズ',
        ];
        $statusHtml = '';
        foreach ($statusOptions as $value => $label) {
            $selected = $status === $value ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $rowsHtml .= '<tr>'
                . '<td data-label="事故管理番号"><span class="truncate" title="' . Layout::escape((string) ($row['accident_no'] ?? '')) . '">' . Layout::escape((string) ($row['accident_no'] ?? '')) . '</span></td>'
                . '<td data-label="契約者名"><strong class="truncate list-row-primary" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</strong></td>'
                . '<td data-label="証券番号"><span class="truncate" title="' . Layout::escape((string) ($row['policy_no'] ?? '')) . '">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</span></td>'
                . '<td data-label="種目"><span class="truncate" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</span></td>'
                . '<td data-label="事故受付日">' . Layout::escape((string) ($row['accepted_date'] ?? '')) . '</td>'
                . '<td data-label="状態">' . self::renderStatusBadge((string) ($row['status'] ?? '')) . '</td>'
                . '<td data-label="優先度">' . self::renderPriorityBadge((string) ($row['priority'] ?? '')) . '</td>'
                . '<td data-label="完了日">' . Layout::escape((string) ($row['resolved_date'] ?? '')) . '</td>'
                . '<td data-label="操作" class="cell-action"><a class="text-link" href="' . $detailUrl . '">詳細を開く</a></td>'
                . '</tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="9">該当する事故案件はありません。</td></tr>';
        }

        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar = self::renderToolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = self::renderBottomPager($searchUrl, $criteria, $listState, $pager);

        $content = ''
            . '<div class="list-page-frame">'
            . '<div class="list-page-header">'
            . '<h1 class="title">事故案件一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<button class="btn" type="button" data-open-dialog="accident-create-dialog">事故案件を追加</button>'
            . '</div>'
            . '</div>'
            . $noticeHtml
            . '<details class="card details-panel list-filter-card"' . ($filterOpen ? ' open' : '') . '>'
            . '<summary class="list-filter-toggle"><span class="list-filter-toggle-label is-closed">検索条件を開く</span><span class="list-filter-toggle-label is-open">検索条件を閉じる</span></summary>'
            . $listErrorHtml
            . '<form method="get" action="' . Layout::escape(self::buildFormAction($searchUrl)) . '">'
            . self::renderRouteInput($searchUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . self::renderHiddenInputs(self::buildListQueryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>事故受付日From</span><input type="date" name="accepted_date_from" value="' . $acceptedDateFrom . '"></label>'
            . '<label class="list-filter-field"><span>事故受付日To</span><input type="date" name="accepted_date_to" value="' . $acceptedDateTo . '"></label>'
            . '<label class="list-filter-field"><span>契約者名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>状態</span><select name="status">' . $statusHtml . '</select></label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($searchUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</details>'
            . '<div class="card">'
            . $topToolbar
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-accident">'
            . '<colgroup>'
            . '<col class="list-col-policy">'
            . '<col class="list-col-customer">'
            . '<col class="list-col-policy">'
            . '<col class="list-col-product">'
            . '<col class="list-col-date">'
            . '<col class="list-col-status">'
            . '<col class="list-col-priority">'
            . '<col class="list-col-date">'
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . self::renderSortLink('事故管理番号', 'accident_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('契約者名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('証券番号', 'policy_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>種目</th>'
            . '<th>' . self::renderSortLink('事故受付日', 'accepted_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('状態', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('優先度', 'priority', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('完了日', 'resolved_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th class="align-right">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $bottomPager
            . '</div>'
            . '</div>'
            . self::renderCreateDialog($storeUrl, $createCsrf, $createDraft, $searchUrl, $customerOptions, $currentUser)
            . '<script>'
            . '(function(){const id="accident-create-dialog";const dlg=document.getElementById(id);if(!dlg||typeof dlg.showModal!=="function"){return;}const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const rect=dlg.getBoundingClientRect();const inside=rect.left<=e.clientX&&e.clientX<=rect.right&&rect.top<=e.clientY&&e.clientY<=rect.bottom;if(!inside&&dlg.open){dlg.close();}});if(' . ($openModal === 'create' ? 'true' : 'false') . '){dlg.showModal();}})()'
            . '</script>';

        return Layout::render('事故案件一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed>|null $draft
     * @param array<int, array<string, mixed>> $customerOptions
     * @param array<string, mixed> $currentUser
     */
    private static function renderCreateDialog(
        string $storeUrl,
        string $csrfToken,
        ?array $draft,
        string $returnTo,
        array $customerOptions,
        array $currentUser
    ): string
    {
        $statusOptions = ['accepted', 'linked', 'in_progress', 'waiting_docs', 'resolved', 'closed'];
        $currentStatus = (string) ($draft['status'] ?? 'accepted');
        $statusHtml = '';
        $statusLabels = [
            'accepted' => '受付',
            'linked' => '対応開始',
            'in_progress' => '対応中',
            'waiting_docs' => '保留',
            'resolved' => '解決',
            'closed' => 'クローズ',
        ];
        foreach ($statusOptions as $s) {
            $selected = $s === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($s) . '"' . $selected . '>' . Layout::escape((string) ($statusLabels[$s] ?? $s)) . '</option>';
        }

        $customerId = (string) ($draft['customer_id'] ?? '');
        $customerHtml = '<option value="">選択してください</option>';
        foreach ($customerOptions as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $selected = $customerId !== '' && (int) $customerId === $id ? ' selected' : '';
            $label = (string) ($row['customer_name'] ?? '');
            $customerHtml .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $defaultUserId = (int) ($currentUser['id'] ?? 0);
        $assignedUserId = (string) ($draft['assigned_user_id'] ?? ($defaultUserId > 0 ? (string) $defaultUserId : ''));
        $assignedUserName = (string) ($currentUser['name'] ?? 'ログインユーザー');

        $accidentDate = Layout::escape((string) ($draft['accident_date'] ?? date('Y-m-d')));
        $insuranceCategory = Layout::escape((string) ($draft['insurance_category'] ?? ''));
        $intakeBranch = Layout::escape((string) ($draft['accident_location'] ?? ($currentUser['default_branch'] ?? '')));
        $remark = Layout::escape((string) ($draft['remark'] ?? ''));

        return ''
            . '<dialog id="accident-create-dialog" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>事故案件を追加</h2></div>'
            . '<p class="muted">新規事故案件を登録します。登録後は詳細画面で対応状況を管理できます。</p>'
            . '<form method="post" action="' . Layout::escape($storeUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">受付基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>事故日 <strong class="required-mark">*</strong></span><input type="date" name="accident_date" value="' . $accidentDate . '" required></label>'
            . '<label class="list-filter-field"><span>状態 <strong class="required-mark">*</strong></span><select name="status" required>' . $statusHtml . '</select></label>'
            . '<label class="list-filter-field"><span>保険種類 <strong class="required-mark">*</strong></span><input type="text" name="insurance_category" value="' . $insuranceCategory . '" required></label>'
            . '<label class="list-filter-field"><span>お客さま名 <strong class="required-mark">*</strong></span><select name="customer_id" required>' . $customerHtml . '</select></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">担当情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>担当拠点 <strong class="required-mark">*</strong></span><input type="text" name="intake_branch" value="' . $intakeBranch . '" required></label>'
            . '<label class="list-filter-field"><span>担当者 <strong class="required-mark">*</strong></span><select name="assigned_user_id" required><option value="' . Layout::escape($assignedUserId) . '" selected>' . Layout::escape($assignedUserName) . '</option></select></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">備考</h3>'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="5" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="accident-create-dialog">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';
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

    private static function renderStatusBadge(string $status): string
    {
        $label = match ($status) {
            'accepted' => '受付',
            'linked' => 'リンク',
            'in_progress' => '対応中',
            'waiting_docs' => '保留',
            'resolved' => '解決',
            'closed' => 'クローズ',
            default => '未設定',
        };
        $class = match ($status) {
            'resolved', 'closed' => 'status-done',
            'in_progress', 'linked' => 'status-progress',
            'waiting_docs' => 'status-open',
            default => 'status-inactive',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderPriorityBadge(string $priority): string
    {
        $label = match ($priority) {
            'high' => '高',
            'normal' => '中',
            'low' => '低',
            default => '-',
        };
        $class = match ($priority) {
            'high' => 'priority-high',
            'normal' => 'priority-medium',
            'low' => 'priority-low',
            default => 'priority-none',
        };

        return '<span class="priority-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 事故受付日';
        }

        $label = match ($sort) {
            'accident_no' => '事故管理番号',
            'customer_name' => '契約者名',
            'policy_no' => '証券番号',
            'accepted_date' => '事故受付日',
            'status' => '状態',
            'priority' => '優先度',
            'resolved_date' => '完了日',
            default => '事故受付日',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
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
}
