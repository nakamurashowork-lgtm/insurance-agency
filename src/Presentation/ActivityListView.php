<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class ActivityListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array{id:int, name:string}> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        array $staffUsers,
        string $listUrl,
        string $newUrl,
        string $detailBaseUrl,
        string $dailyBaseUrl,
        string $customerDetailBaseUrl,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $allowedActivityTypes,
        bool $isAdmin,
        bool $forceFilterOpen,
        array $layoutOptions
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }
        if (is_string($errorMessage) && $errorMessage !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $perPage    = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $filterOpen = $forceFilterOpen;
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $dateFrom           = Layout::escape((string) ($criteria['activity_date_from'] ?? ''));
        $dateTo             = Layout::escape((string) ($criteria['activity_date_to'] ?? ''));
        $customerName       = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $activityType       = (string) ($criteria['activity_type'] ?? '');
        $staffUserId        = (string) ($criteria['staff_user_id'] ?? '');
        $dailyReportStatus  = (string) ($criteria['daily_report_status'] ?? '');

        // 活動種別セレクト
        $typeOptionsHtml = '<option value="">すべて</option>';
        foreach ($allowedActivityTypes as $val => $label) {
            $sel = $activityType === $val ? ' selected' : '';
            $typeOptionsHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        // 担当者セレクト
        $staffOptionsHtml = '<option value="">全員</option>';
        foreach ($staffUsers as $user) {
            $uid  = (int) ($user['id'] ?? 0);
            $name = (string) ($user['name'] ?? '');
            $sel  = $staffUserId === (string) $uid ? ' selected' : '';
            $staffOptionsHtml .= '<option value="' . $uid . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        // 行HTML
        $rowsHtml = '';
        foreach ($rows as $row) {
            $id        = (int) ($row['id'] ?? 0);
            $actDate   = (string) ($row['activity_date'] ?? '');
            $custId    = (int) ($row['customer_id'] ?? 0);
            $custName  = (string) ($row['customer_name'] ?? '');
            $type      = (string) ($row['activity_type'] ?? '');
            $typeLabel = $allowedActivityTypes[$type] ?? Layout::escape($type);
            $subject   = (string) ($row['subject'] ?? '');
            $summary   = (string) ($row['content_summary'] ?? '');
            $nextDate  = (string) ($row['next_action_date'] ?? '');
            $staffName = (string) ($row['staff_name'] ?? '');
            $staffUid  = (int) ($row['staff_user_id'] ?? 0);

            $isRowSubmitted = (int) ($row['daily_is_submitted'] ?? 0) === 1;

            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));
            $dailyUrl  = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $actDate, 'staff' => (string) $staffUid]));
            $custUrl   = $custId > 0 ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId])) : '';

            $custHtml = $custUrl !== ''
                ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                : Layout::escape($custName);

            $submittedBadge = $isRowSubmitted
                ? ' <span style="display:inline-block;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;background:#dcfce7;color:#166534;vertical-align:middle;">提出済み</span>'
                : '';

            $rowsHtml .=
                '<tr>'
                . '<td data-label="活動日"><a href="' . $dailyUrl . '" class="text-link">' . Layout::escape($actDate) . '</a>' . $submittedBadge . '</td>'
                . '<td data-label="活動種別">' . Layout::escape($typeLabel) . '</td>'
                . '<td data-label="顧客名">' . $custHtml . '</td>'
                . '<td data-label="件名"><span class="truncate">' . Layout::escape($subject) . '</span></td>'
                . '<td data-label="内容要約"><span class="truncate">' . Layout::escape(mb_strimwidth($summary, 0, 60, '…')) . '</span></td>'
                . '<td data-label="次回予定日">' . Layout::escape($nextDate) . '</td>'
                . '<td data-label="担当者">' . Layout::escape($staffName) . '</td>'
                . '<td data-label="操作" class="cell-action"><a href="' . $detailUrl . '" class="text-link">詳細を開く</a></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="8">該当する活動の記録がありません。</td></tr>';
        }

        $filterState = $filterOpen ? ' open' : '';
        $topToolbar  = self::renderToolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = self::renderBottomPager($listUrl, $criteria, $listState, $pager);
        $clearUrl    = Layout::escape(ListViewHelper::buildUrl($listUrl, ['filter_open' => '1']));

        $content =
            '<div class="list-page-frame">'
            . '<div class="list-page-header"><h1 class="title">営業活動一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<a href="' . Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => date('Y-m-d'), 'staff' => $staffUserId])) . '" class="btn btn-ghost btn-small">日報ビュー</a>'
            . '<a href="' . Layout::escape($newUrl) . '" class="btn btn-primary btn-small">＋ 活動登録</a>'
            . '</div></div>'
            . $noticeHtml
            . '<details class="card details-panel list-filter-card"' . $filterState . '>'
            . '<summary class="list-filter-toggle">'
            . '<span class="list-filter-toggle-label is-closed">検索条件を開く</span>'
            . '<span class="list-filter-toggle-label is-open">検索条件を閉じる</span>'
            . '</summary>'
            . '<form method="get" action="' . Layout::escape(self::buildFormAction($listUrl)) . '">'
            . self::renderRouteInput($listUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . self::renderHiddenInputs(self::buildListQueryParams([], $listState, false))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>活動日（開始）</span><input type="date" name="activity_date_from" value="' . $dateFrom . '"></label>'
            . '<label class="list-filter-field"><span>活動日（終了）</span><input type="date" name="activity_date_to" value="' . $dateTo . '"></label>'
            . '<label class="list-filter-field"><span>顧客名</span><input type="text" name="customer_name" value="' . $customerName . '" placeholder="顧客名で絞り込み"></label>'
            . '<label class="list-filter-field"><span>活動種別</span><select name="activity_type">' . $typeOptionsHtml . '</select></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_user_id">' . $staffOptionsHtml . '</select></label>'
            . ($isAdmin ? self::renderDailyReportStatusFilter($dailyReportStatus) : '')
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . $clearUrl . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</details>'
            . '<div class="card">'
            . $topToolbar
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-activity">'
            . '<colgroup>'
            . '<col class="list-col-date"><col style="width:90px"><col class="list-col-customer">'
            . '<col><col><col class="list-col-date"><col style="width:110px"><col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>活動日</th><th>活動種別</th><th>顧客名</th>'
            . '<th>件名</th><th>内容要約</th><th>次回予定日</th><th>担当者</th><th class="align-right">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $bottomPager
            . '</div>'
            . '</div>';

        return Layout::render('営業活動一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderToolbar(string $listUrl, array $criteria, array $listState, array $pager, int $totalCount, int $perPage): string
    {
        return '<div class="list-toolbar">'
            . '<div class="list-summary"><p class="summary-count">' . Layout::escape(self::renderSummaryText($totalCount, $pager)) . '</p></div>'
            . '<div class="list-toolbar-actions">'
            . self::renderPerPageForm($listUrl, $criteria, $listState, $perPage)
            . self::renderPager($listUrl, $criteria, $listState, $pager)
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderBottomPager(string $listUrl, array $criteria, array $listState, array $pager): string
    {
        $pagerHtml = self::renderPager($listUrl, $criteria, $listState, $pager);
        if ($pagerHtml === '') {
            return '';
        }

        return '<div class="list-toolbar list-toolbar-bottom"><div class="list-toolbar-actions">' . $pagerHtml . '</div></div>';
    }

    /**
     * @param array<string, mixed> $pager
     */
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
     * @param array<string, mixed> $pager
     */
    private static function renderPager(string $listUrl, array $criteria, array $listState, array $pager): string
    {
        if ((int) ($pager['totalPages'] ?? 0) <= 1) {
            return '';
        }

        $links = '';
        if (!empty($pager['hasPrevious'])) {
            $links .= self::renderPagerLink('前へ', (int) ($pager['previousPage'] ?? 1), $listUrl, $criteria, $listState);
        }
        foreach ((array) ($pager['pages'] ?? []) as $pageNumber) {
            $page = (int) $pageNumber;
            if ($page === (int) ($pager['currentPage'] ?? 1)) {
                $links .= '<span class="list-pager-link is-current">' . $page . '</span>';
                continue;
            }
            $links .= self::renderPagerLink((string) $page, $page, $listUrl, $criteria, $listState);
        }
        if (!empty($pager['hasNext'])) {
            $links .= self::renderPagerLink('次へ', (int) ($pager['nextPage'] ?? 1), $listUrl, $criteria, $listState);
        }

        return '<nav class="list-pager" aria-label="ページャー">' . $links . '</nav>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPagerLink(string $label, int $page, string $listUrl, array $criteria, array $listState): string
    {
        $params = self::buildListQueryParams($criteria, array_merge($listState, ['page' => (string) $page]));
        $url    = Layout::escape(ListViewHelper::buildUrl($listUrl, $params));

        return '<a class="list-pager-link" href="' . $url . '">' . Layout::escape($label) . '</a>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPerPageForm(string $listUrl, array $criteria, array $listState, int $perPage): string
    {
        $optionsHtml = '';
        foreach ([10, 50, 100] as $option) {
            $selected = $perPage === $option ? ' selected' : '';
            $optionsHtml .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }

        return '<form method="get" action="' . Layout::escape(self::buildFormAction($listUrl)) . '" class="list-per-page-form">'
            . self::renderRouteInput($listUrl)
            . self::renderHiddenInputs(self::buildListQueryParams($criteria, $listState, false))
            . '<label class="list-select-inline"><span>表示件数</span>'
            . '<select name="per_page" onchange="this.form.submit()">' . $optionsHtml . '</select></label>'
            . '<noscript><button class="btn btn-ghost btn-small" type="submit">更新</button></noscript>'
            . '</form>';
    }

    private static function renderDailyReportStatusFilter(string $current): string
    {
        $options = [
            ''             => '全て',
            'submitted'    => '提出済み',
            'not_submitted' => '未提出',
        ];
        $optHtml = '';
        foreach ($options as $val => $label) {
            $sel      = $current === $val ? ' selected' : '';
            $optHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        return '<label class="list-filter-field"><span>日報提出状態</span><select name="daily_report_status">' . $optHtml . '</select></label>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @return array<string, string>
     */
    private static function buildListQueryParams(array $criteria, array $listState, bool $includePage = true): array
    {
        $params = $criteria;

        if ($includePage && (int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }
        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }
        if (($listState['sort'] ?? '') !== '') {
            $params['sort']      = (string) $listState['sort'];
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
            if (trim((string) $value) === '') {
                continue;
            }
            $html .= '<input type="hidden" name="' . Layout::escape($name) . '" value="' . Layout::escape($value) . '">';
        }

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
