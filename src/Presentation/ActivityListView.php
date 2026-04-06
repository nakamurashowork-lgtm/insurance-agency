<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class ActivityListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $staffUsers
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

        $dateFrom          = Layout::escape((string) ($criteria['activity_date_from'] ?? ''));
        $dateTo            = Layout::escape((string) ($criteria['activity_date_to'] ?? ''));
        $customerName      = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $activityType      = (string) ($criteria['activity_type'] ?? '');
        $staffUserId       = (string) ($criteria['staff_id'] ?? '');
        $dailyReportStatus = (string) ($criteria['daily_report_status'] ?? '');

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
            $name = (string) ($user['staff_name'] ?? $user['name'] ?? '');
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
            $staffUid  = (int) ($row['staff_id'] ?? 0);

            $isRowSubmitted = (int) ($row['daily_is_submitted'] ?? 0) === 1;

            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));
            $dailyUrl  = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $actDate, 'staff' => (string) $staffUid]));
            $custUrl   = $custId > 0 ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId])) : '';

            $isNullCustomer = ($row['customer_id'] === null || $row['customer_id'] === '');
            $custHtml = $isNullCustomer
                ? '<span style="color:#888;font-size:12px;">（顧客なし）</span>'
                : ($custUrl !== ''
                    ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                    : Layout::escape($custName));

            $submittedBadge = $isRowSubmitted
                ? ' <span style="display:inline-block;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;background:#dcfce7;color:#166534;vertical-align:middle;">提出済み</span>'
                : '';

            $rowsHtml .=
                '<tr>'
                . '<td data-label="活動日"><a href="' . $dailyUrl . '" class="text-link">' . Layout::escape($actDate) . '</a>' . $submittedBadge . '</td>'
                . '<td data-label="活動種別">' . Layout::escape($typeLabel) . '</td>'
                . '<td data-label="顧客名">' . $custHtml . '</td>'
                . '<td data-label="件名"><a href="' . $detailUrl . '" class="text-link"><span class="truncate">' . Layout::escape($subject) . '</span></a></td>'
                . '<td data-label="内容要約"><span class="truncate">' . Layout::escape(mb_strimwidth($summary, 0, 60, '…')) . '</span></td>'
                . '<td data-label="次回予定日">' . Layout::escape($nextDate) . '</td>'
                . '<td data-label="担当者">' . Layout::escape($staffName) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当する活動の記録がありません。</td></tr>';
        }

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-activity">'
            . '<colgroup>'
            . '<col class="list-col-date"><col style="width:90px"><col class="list-col-customer">'
            . '<col><col><col class="list-col-date"><col style="width:110px">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>活動日</th><th>活動種別</th><th>顧客名</th>'
            . '<th>件名</th><th>内容要約</th><th>次回予定日</th><th>担当者</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $filterFormHtml =
            '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . LP::hiddenInputs(LP::queryParams([], $listState, false))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>活動日（開始）</span><input type="date" name="activity_date_from" value="' . $dateFrom . '"></label>'
            . '<label class="list-filter-field"><span>活動日（終了）</span><input type="date" name="activity_date_to" value="' . $dateTo . '"></label>'
            . '<label class="list-filter-field"><span>顧客名</span><input type="text" name="customer_name" value="' . $customerName . '" placeholder="顧客名で絞り込み"></label>'
            . '<label class="list-filter-field"><span>活動種別</span><select name="activity_type">' . $typeOptionsHtml . '</select></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptionsHtml . '</select></label>'
            . ($isAdmin ? self::renderDailyReportStatusFilter($dailyReportStatus) : '')
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($listUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>';

        $dailyViewUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => date('Y-m-d'), 'staff' => $staffUserId]));
        $headerActionsHtml = '<a href="' . $dailyViewUrl . '" class="btn">日報ビュー</a>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('営業活動一覧', $headerActionsHtml)
            . $noticeHtml
            . LP::filterCard($filterFormHtml, $filterOpen)
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>';

        return Layout::render('営業活動一覧', $content, $layoutOptions);
    }

    private static function renderDailyReportStatusFilter(string $current): string
    {
        $options = [
            ''              => '全て',
            'submitted'     => '提出済み',
            'not_submitted' => '未提出',
        ];
        $optHtml = '';
        foreach ($options as $val => $label) {
            $sel     = $current === $val ? ' selected' : '';
            $optHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        return '<label class="list-filter-field"><span>日報提出状態</span><select name="daily_report_status">' . $optHtml . '</select></label>';
    }
}
