<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class CustomerListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed>|null $createDraft
     * @param array<int, array{id: int, name: string}> $staffUsers
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        string $searchUrl,
        string $detailBaseUrl,
        ?string $errorMessage,
        bool $forceFilterOpen,
        string $createUrl,
        string $createCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        string $openModal,
        ?array $createDraft,
        array $staffUsers,
        array $layoutOptions
    ): string
    {
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

        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $phone = Layout::escape((string) ($criteria['phone'] ?? ''));
        $email = Layout::escape((string) ($criteria['email'] ?? ''));
        $filterUserId = (int) ($criteria['assigned_user_id'] ?? 0);
        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort = (string) ($listState['sort'] ?? '');
        $direction = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $listErrorHtml !== '';
        $pager = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery = self::buildListQueryParams($criteria, $listState);

        $activeModal = $openModal === 'create' ? 'create' : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = 'create';
        }

        $userMap = [];
        foreach ($staffUsers as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid > 0) {
                $userMap[$uid] = (string) ($u['name'] ?? '');
            }
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $assignedId = (int) ($row['assigned_user_id'] ?? 0);
            $assignedText = $assignedId > 0 ? ($userMap[$assignedId] ?? '-') : '-';
            $kana = Layout::escape((string) ($row['customer_name_kana'] ?? ''));
            $updatedRaw = (string) ($row['updated_at'] ?? '');
            $updatedTs = $updatedRaw !== '' ? strtotime($updatedRaw) : false;
            $updatedDisplay = $updatedTs !== false ? date('Y/m/d', $updatedTs) : '';

            $rowsHtml .= '<tr>'
                . '<td data-label="顧客名"><a class="text-link" href="' . $detailUrl . '"><strong class="truncate list-row-primary" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</strong></a></td>'
                . '<td data-label="よみがな"><span class="truncate muted">' . $kana . '</span></td>'
                . '<td data-label="電話番号"><span class="truncate" title="' . Layout::escape((string) ($row['phone'] ?? '')) . '">' . Layout::escape((string) ($row['phone'] ?? '')) . '</span></td>'
                . '<td data-label="メール"><span class="truncate" title="' . Layout::escape((string) ($row['email'] ?? '')) . '">' . Layout::escape((string) ($row['email'] ?? '')) . '</span></td>'
                . '<td data-label="担当者">' . Layout::escape($assignedText) . '</td>'
                . '<td data-label="契約件数">' . Layout::escape((string) ($row['contract_count'] ?? '0')) . '</td>'
                . '<td data-label="最終更新">' . Layout::escape($updatedDisplay) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当データはありません。</td></tr>';
        }

        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar = self::renderToolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = self::renderBottomPager($searchUrl, $criteria, $listState, $pager);
        $createForm  = self::renderCreateForm($createDraft, $staffUsers, $createUrl, $createCsrf, $searchUrl);

        $content = ''
            . '<div class="list-page-frame">'
            . '<div class="list-page-header">'
            . '<h1 class="title">顧客一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<button class="btn" type="button" data-open-dialog="customer-create-dialog">顧客を追加</button>'
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
            . '<label class="list-filter-field"><span>顧客名・よみがな</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>電話番号</span><input type="text" name="phone" value="' . $phone . '"></label>'
            . '<label class="list-filter-field"><span>メール</span><input type="text" name="email" value="' . $email . '"></label>'
            . '<label class="list-filter-field"><span>担当者</span>' . self::renderUserFilterSelect($staffUsers, $filterUserId) . '</label>'
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
            . '<table class="table-fixed table-card list-table">'
            . '<thead><tr>'
            . '<th>' . self::renderSortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>よみがな</th>'
            . '<th>' . self::renderSortLink('電話番号', 'phone', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>メール</th>'
            . '<th>' . self::renderSortLink('担当者', 'assigned_user_id', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('契約件数', 'contract_count', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('最終更新', 'updated_at', $searchUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $bottomPager
            . '</div>'
            . '<dialog id="customer-create-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . $createForm
            . '</dialog>'
            . '</div>'
            . '<script>'
            . '(function(){'
            . 'const dlg=document.getElementById("customer-create-dialog");'
            . 'if(!dlg)return;'
            . 'const openBtn=document.querySelector("[data-open-dialog=\"customer-create-dialog\"]");'
            . 'if(openBtn){openBtn.addEventListener("click",function(){if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});}'
            . 'dlg.addEventListener("click",function(e){const r=dlg.getBoundingClientRect();const inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});'
            . 'const initial=' . ($activeModal === 'create' ? '"customer-create-dialog"' : '""') . ';'
            . 'if(initial!==""){if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}}'
            . '})();'
            . '</script>';

        return Layout::render('顧客一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed>|null $draft
     * @param array<int, array{id: int, name: string}> $staffUsers
     */
    private static function renderCreateForm(
        ?array $draft,
        array $staffUsers,
        string $createUrl,
        string $createCsrf,
        string $returnTo
    ): string {
        $draftType    = Layout::escape((string) ($draft['customer_type'] ?? ''));
        $draftName    = Layout::escape((string) ($draft['customer_name'] ?? ''));
        $draftKana    = Layout::escape((string) ($draft['customer_name_kana'] ?? ''));
        $draftPhone   = Layout::escape((string) ($draft['phone'] ?? ''));
        $draftEmail   = Layout::escape((string) ($draft['email'] ?? ''));
        $draftPostal  = Layout::escape((string) ($draft['postal_code'] ?? ''));
        $draftAddr1   = Layout::escape((string) ($draft['address1'] ?? ''));
        $draftAddr2   = Layout::escape((string) ($draft['address2'] ?? ''));
        $draftUserId  = (int) ($draft['assigned_user_id'] ?? 0);
        $draftNote    = Layout::escape((string) ($draft['note'] ?? ''));

        $typeOptions = '';
        foreach (['individual' => '個人', 'corporate' => '法人'] as $val => $label) {
            $selected = $draftType === $val ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($val) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $userOptions = '<option value="">（未設定）</option>';
        foreach ($staffUsers as $user) {
            $uid = (int) ($user['id'] ?? 0);
            $uname = Layout::escape((string) ($user['name'] ?? ''));
            $selected = $draftUserId === $uid ? ' selected' : '';
            $userOptions .= '<option value="' . $uid . '"' . $selected . '>' . $uname . '</option>';
        }

        $action = Layout::escape(self::buildFormAction($createUrl));
        $routeInput = self::renderRouteInput($createUrl);

        return '<form method="post" action="' . $action . '" id="customer-create-form" class="customer-create-form">'
            . $routeInput
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($createCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<h2 class="modal-title">顧客を追加</h2>'
            . '<div class="customer-create-grid">'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客区分</span>'
            . '<select name="customer_type" required>'
            . '<option value="">選択してください</option>'
            . $typeOptions
            . '</select></label>'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客名</span>'
            . '<input type="text" name="customer_name" value="' . $draftName . '" required maxlength="200" placeholder="例：山田太郎"></label>'
            . '<label class="form-field"><span class="form-field-label">顧客名カナ</span>'
            . '<input type="text" name="customer_name_kana" value="' . $draftKana . '" maxlength="200" placeholder="例：ヤマダタロウ"></label>'
            . '<label class="form-field"><span class="form-field-label">電話番号</span>'
            . '<input type="text" name="phone" value="' . $draftPhone . '" maxlength="30" placeholder="例：03-1234-5678"></label>'
            . '<label class="form-field"><span class="form-field-label">メールアドレス</span>'
            . '<input type="email" name="email" value="' . $draftEmail . '" maxlength="255" placeholder="例：example@example.com"></label>'
            . '<label class="form-field"><span class="form-field-label">郵便番号</span>'
            . '<input type="text" name="postal_code" value="' . $draftPostal . '" maxlength="20" placeholder="例：100-0001"></label>'
            . '<label class="form-field"><span class="form-field-label">住所1</span>'
            . '<input type="text" name="address1" value="' . $draftAddr1 . '" maxlength="255" placeholder="例：東京都千代田区千代田1-1"></label>'
            . '<label class="form-field"><span class="form-field-label">住所2</span>'
            . '<input type="text" name="address2" value="' . $draftAddr2 . '" maxlength="255" placeholder="例：○○ビル3F"></label>'
            . '<label class="form-field"><span class="form-field-label">主担当者</span>'
            . '<select name="assigned_user_id">' . $userOptions . '</select></label>'
            . '<div class="form-field form-field--spacer" aria-hidden="true"></div>'
            . '<label class="form-field form-field--full"><span class="form-field-label">備考</span>'
            . '<textarea name="note" rows="4" maxlength="2000">' . $draftNote . '</textarea></label>'
            . '</div>'
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-secondary" onclick="(function(){var d=document.getElementById(\'customer-create-dialog\');if(d&&d.open)d.close();})()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">登録する</button>'
            . '</div>'
            . '</form>';
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
            return '並び順: 更新順';
        }

        $label = match ($sort) {
            'customer_name'    => '顧客名',
            'phone'            => '電話番号',
            'assigned_user_id' => '担当者',
            'contract_count'   => '契約件数',
            'updated_at'       => '最終更新',
            default            => '更新順',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    /**
     * @param array<int, array{id: int, name: string}> $staffUsers
     */
    private static function renderUserFilterSelect(array $staffUsers, int $currentUserId): string
    {
        $html = '<select name="assigned_user_id"><option value="">全担当者</option>';
        foreach ($staffUsers as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $uname = Layout::escape((string) ($u['name'] ?? ''));
            $selected = $currentUserId === $uid ? ' selected' : '';
            $html .= '<option value="' . $uid . '"' . $selected . '>' . $uname . '</option>';
        }
        $html .= '</select>';
        return $html;
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