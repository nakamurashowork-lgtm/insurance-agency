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
        array $layoutOptions,
        array $quickFilterCounts = []
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

        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $pager   = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $dateFrom     = Layout::escape((string) ($criteria['activity_date_from'] ?? ''));
        $dateTo       = Layout::escape((string) ($criteria['activity_date_to'] ?? ''));
        $customerName = (string) ($criteria['customer_name'] ?? '');
        $activityType = (string) ($criteria['activity_type'] ?? '');
        $staffUserId  = (string) ($criteria['staff_id'] ?? '');

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

        // PC テーブル行
        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= self::buildTableRowHtml($row, $detailBaseUrl, $customerDetailBaseUrl, $allowedActivityTypes);
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5">該当する活動の記録がありません。</td></tr>';
        }

        $tableHtml =
            '<div class="table-wrap list-pc-only">'
            . '<table class="table-fixed list-table list-table-activity">'
            . '<colgroup>'
            . '<col class="list-col-date">'
            . '<col class="list-col-type">'
            . '<col>'
            . '<col class="list-col-user">'
            . '<col class="list-col-next">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('活動日時', 'activity_date', $listUrl, $criteria, $listState) . '</th>'
            . '<th>種別</th>'
            . '<th>活動内容／顧客</th>'
            . '<th>担当者</th>'
            . '<th>' . LP::sortLink('次回予定日', 'next_action_date', $listUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . LP::mobileCardList(
                $rows,
                fn (array $row): string => self::buildMobileCardHtml($row, $detailBaseUrl, $customerDetailBaseUrl, $allowedActivityTypes),
                '営業活動一覧（モバイル表示）'
            );

        // 絞り込みボタンのバッジ件数（customer_name 以外で適用中）
        $advancedFilterCount = 0;
        foreach (['activity_date_from', 'activity_date_to', 'activity_type', 'staff_id'] as $k) {
            if ((string) ($criteria[$k] ?? '') !== '') {
                $advancedFilterCount++;
            }
        }

        // ツールバー（検索バー + 絞込ボタン）
        // ページヘッダ: 営業日報ボタン（ツールバー右側に配置）
        $dailyViewUrl      = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => date('Y-m-d'), 'staff' => $staffUserId]));
        $headerActionsHtml = '<button type="button" class="btn btn-primary" onclick="location.href=\'' . $dailyViewUrl . '\'">日報を表示</button>';

        $toolbarBarHtml = LP::searchToolbar([
            'searchUrl'         => $listUrl,
            'searchParam'       => 'customer_name',
            'searchValue'       => $customerName,
            'searchPlaceholder' => '顧客名で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'activity-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'headerActions'     => $headerActionsHtml,
        ]);

        $currentQuickFilter = (string) ($criteria['quick_filter'] ?? '');
        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => $currentQuickFilter,
            'tabs' => [
                ''        => ['label' => 'すべて',   'countKey' => 'all'],
                'today'   => ['label' => '今日',     'countKey' => 'today'],
                'week'    => ['label' => '今週',     'countKey' => 'week'],
                'mine'    => ['label' => '自分',     'countKey' => 'mine'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $listUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
        ]);

        // フィルタダイアログ
        $filterDialogHtml = LP::filterDialog([
            'id'        => 'activity-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $listUrl,
            'listState' => $listState,
            'fields' => [
                ['label' => '活動日（開始）', 'html' => '<input type="date" name="activity_date_from" value="' . $dateFrom . '">'],
                ['label' => '活動日（終了）', 'html' => '<input type="date" name="activity_date_to" value="' . $dateTo . '">'],
                ['label' => '活動種別', 'html' => '<select name="activity_type">' . $typeOptionsHtml . '</select>'],
                ['label' => '担当者',   'html' => '<select name="staff_id">' . $staffOptionsHtml . '</select>'],
            ],
            'preserveCriteria' => ['quick_filter' => $currentQuickFilter],
            'clearUrl' => $listUrl,
        ]);

        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('営業活動一覧', '')
            . $noticeHtml
            . $toolbarBarHtml
            . $quickFilterTabsHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . $filterDialogHtml
            . LP::dialogScript(['activity-filter-dialog']);

        return Layout::render('営業活動一覧', $content, $layoutOptions);
    }

    /**
     * PC テーブル 1 行の HTML を生成する。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $allowedActivityTypes
     */
    private static function buildTableRowHtml(
        array $row,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        array $allowedActivityTypes
    ): string {
        $id        = (int) ($row['id'] ?? 0);
        $actDate   = (string) ($row['activity_date'] ?? '');
        $custId    = (int) ($row['customer_id'] ?? 0);
        $custName  = (string) ($row['customer_name'] ?? '');
        $type      = (string) ($row['activity_type'] ?? '');
        $typeLabel = $allowedActivityTypes[$type] ?? $type;
        $subject   = (string) ($row['subject'] ?? '');
        $nextDate  = (string) ($row['next_action_date'] ?? '');
        $staffName = (string) ($row['staff_name'] ?? '');

        $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id, 'from' => 'list']));

        $isNullCustomer = ($row['customer_id'] === null || $row['customer_id'] === '');
        $custLabel = $isNullCustomer ? '（顧客なし）' : $custName;

        $secondaryHtml = $custLabel !== ''
            ? '<div class="list-row-secondary">' . Layout::escape($custLabel) . '</div>'
            : '';

        $primaryLabel = $subject !== '' ? $subject : '（件名なし）';

        return '<tr>'
            . '<td class="cell-date" data-label="活動日時" style="white-space:nowrap;">' . Layout::escape($actDate) . '</td>'
            . '<td class="cell-ellipsis" data-label="種別" title="' . Layout::escape($typeLabel) . '">' . Layout::escape($typeLabel) . '</td>'
            . '<td data-label="活動内容／顧客">'
            . '<div class="list-row-stack">'
            . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($primaryLabel) . '">' . Layout::escape($primaryLabel) . '</a>'
            . $secondaryHtml
            . '</div>'
            . '</td>'
            . '<td class="cell-ellipsis" data-label="担当者" title="' . Layout::escape($staffName) . '">' . Layout::escape($staffName) . '</td>'
            . '<td class="cell-date" data-label="次回予定日">' . Layout::escape($nextDate) . '</td>'
            . '</tr>';
    }

    /**
     * モバイル用 list-card の HTML を生成する（LP::mobileCardList から closure で呼ばれる）。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $allowedActivityTypes
     */
    private static function buildMobileCardHtml(
        array $row,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        array $allowedActivityTypes
    ): string {
        $id        = (int) ($row['id'] ?? 0);
        $actDate   = (string) ($row['activity_date'] ?? '');
        $custId    = (int) ($row['customer_id'] ?? 0);
        $custName  = (string) ($row['customer_name'] ?? '');
        $type      = (string) ($row['activity_type'] ?? '');
        $typeLabel = $allowedActivityTypes[$type] ?? $type;
        $subject   = (string) ($row['subject'] ?? '');
        $nextDate  = (string) ($row['next_action_date'] ?? '');
        $staffName = (string) ($row['staff_name'] ?? '');

        $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id, 'from' => 'list']));

        $customerLabel = $custId > 0 && $custName !== ''
            ? $custName
            : '（顧客なし）';

        $summary = (string) ($row['content_summary'] ?? '');

        $typeBadge = '<span class="badge badge-gray">' . Layout::escape($typeLabel !== '' ? $typeLabel : '活動') . '</span>';
        $nextBadge = $nextDate !== ''
            ? '<span class="badge badge-gray">次回 ' . Layout::escape($nextDate) . '</span>'
            : '';

        $iconCalendar = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconTag      = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
        $iconUser     = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

        return '<li class="list-card with-stripe">'
            . '<span class="list-card-stripe stripe-gray" aria-hidden="true"></span>'
            . '<a class="list-card-link" href="' . $detailUrl . '">'
            . '<div class="list-card-top">'
            . '<span class="list-card-top-left">' . $typeBadge . '</span>'
            . '<span class="list-card-top-right">' . $nextBadge . '</span>'
            . '</div>'
            . '<div class="list-card-customer">' . Layout::escape($customerLabel) . '</div>'
            . '<div class="list-card-policy">' . Layout::escape($subject !== '' ? $subject : '（件名なし）') . '</div>'
            . ($summary !== ''
                ? '<div class="list-card-summary">' . Layout::escape($summary) . '</div>'
                : '')
            . '<div class="list-card-meta">'
            . '<span class="list-card-meta-item">' . $iconCalendar . '<span class="list-card-meta-value">' . ($actDate !== '' ? Layout::escape($actDate) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconTag . '<span class="list-card-meta-value">' . ($typeLabel !== '' ? Layout::escape($typeLabel) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconUser . '<span class="list-card-meta-value">' . Layout::escape($staffName !== '' ? $staffName : '−') . '</span></span>'
            . '</div>'
            . '</a>'
            . '</li>';
    }
}
