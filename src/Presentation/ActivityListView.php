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

            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id, 'from' => 'list']));
            $dailyUrl  = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $actDate, 'staff' => (string) $staffUid]));
            $custUrl   = $custId > 0 ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId])) : '';

            $isNullCustomer = ($row['customer_id'] === null || $row['customer_id'] === '');
            $custHtml = $isNullCustomer
                ? '<span style="color:#888;font-size:12px;">（顧客なし）</span>'
                : ($custUrl !== ''
                    ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                    : Layout::escape($custName));

            $rowsHtml .=
                '<tr>'
                . '<td data-label="活動日" style="white-space:nowrap;">' . Layout::escape($actDate) . '</td>'
                . '<td class="cell-ellipsis" data-label="顧客名" title="' . Layout::escape($custName) . '">' . $custHtml . '</td>'
                . '<td class="cell-ellipsis" data-label="活動概要" title="' . Layout::escape($subject) . '"><a href="' . $detailUrl . '" class="text-link">' . Layout::escape($subject) . '</a></td>'
                . '<td class="cell-ellipsis" data-label="担当者" title="' . Layout::escape($staffName) . '">' . Layout::escape($staffName) . '</td>'
                . '<td data-label="活動種別">' . Layout::escape($typeLabel) . '</td>'
                . '<td data-label="次回予定日" style="white-space:nowrap;">' . Layout::escape($nextDate) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当する活動の記録がありません。</td></tr>';
        }

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-activity">'
            . '<colgroup>'
            . '<col class="list-col-date">'
            . '<col class="list-col-customer">'
            . '<col>'
            . '<col class="list-col-type">'
            . '<col class="list-col-user">'
            . '<col class="list-col-next">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>活動日</th><th>顧客名</th><th>活動概要</th>'
            . '<th>担当者</th><th>活動種別</th><th>次回予定日</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $filterPanelHtml =
            '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false))
            . '<div class="search-row">'
            . '<div class="search-field date-range"><span class="search-label">活動日</span><input type="date" name="activity_date_from" class="compact-input w-date" value="' . $dateFrom . '"><span class="search-sep">〜</span><input type="date" name="activity_date_to" class="compact-input w-date" value="' . $dateTo . '"></div>'
            . '<div class="search-field"><span class="search-label">顧客名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '</div>'
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">活動種別</span><select name="activity_type" class="compact-input w-md">' . $typeOptionsHtml . '</select></div>'
            . '<div class="search-field"><span class="search-label">担当者</span><select name="staff_id" class="compact-input w-md">' . $staffOptionsHtml . '</select></div>'
            . '<div class="search-actions">'
            . '<button class="btn btn-small" type="submit">検索</button>'
            . '<a class="btn btn-small btn-secondary" href="' . Layout::escape($listUrl) . '">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';

        $dailyViewUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => date('Y-m-d'), 'staff' => $staffUserId]));
        $headerActionsHtml = '<a href="' . $dailyViewUrl . '" class="btn">営業日報</a>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('営業活動一覧', $headerActionsHtml)
            . $noticeHtml
            . $filterPanelHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>';

        return Layout::render('営業活動一覧', $content, $layoutOptions);
    }

}
