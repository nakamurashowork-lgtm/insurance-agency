<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;
use App\Presentation\View\StatusBadge;

final class AccidentCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, mixed> $layoutOptions
     * @param array<int, array<string, mixed>> $allStatuses
     * @param array<string, int> $quickFilterCounts
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
        string $deleteActionUrl,
        string $deleteCsrfToken,
        ?array $createDraft,
        string $openModal,
        array $customerOptions,
        array $staffUsers,
        array $currentUser,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess,
        bool $forceFilterOpen,
        array $layoutOptions,
        array $allStatuses = [],
        array $quickFilterCounts = []
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

        $productType    = (string) ($criteria['product_type'] ?? '');
        $status         = (string) ($criteria['status'] ?? '');
        $priorityFilter = (string) ($criteria['priority'] ?? '');
        $filterUserId   = (int) ($criteria['assigned_staff_id'] ?? 0);

        $userMap = [];
        foreach ($staffUsers as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid > 0) {
                $userMap[$uid] = (string) ($u['staff_name'] ?? $u['name'] ?? '');
            }
        }

        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $pager   = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery = LP::queryParams($criteria, $listState);

        // ステータスフィルター選択肢と完了扱い name セット
        $completedNames = [];
        $statusFilterOptions = ['' => 'すべて'];
        foreach ($allStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $statusFilterOptions[$name] = $name;
            if ((int) ($sRow['is_completed'] ?? 0) === 1) {
                $completedNames[$name] = true;
            }
        }

        // 絞り込みバッジ件数（customer_name 以外で適用中）
        $advancedFilterCount = 0;
        foreach (['product_type', 'status', 'priority', 'assigned_staff_id'] as $k) {
            if ((string) ($criteria[$k] ?? '') !== '') {
                $advancedFilterCount++;
            }
        }

        // フィルタダイアログ用 HTML パーツ
        $statusSelectHtml = '<select name="status">';
        foreach ($statusFilterOptions as $value => $label) {
            $sel = $status === (string) $value ? ' selected' : '';
            $statusSelectHtml .= '<option value="' . Layout::escape((string) $value) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }
        $statusSelectHtml .= '</select>';

        $prioritySelectHtml = '<select name="priority">'
            . '<option value="">すべて</option>'
            . '<option value="high"'   . ($priorityFilter === 'high'   ? ' selected' : '') . '>高</option>'
            . '<option value="normal"' . ($priorityFilter === 'normal' ? ' selected' : '') . '>中</option>'
            . '<option value="low"'    . ($priorityFilter === 'low'    ? ' selected' : '') . '>低</option>'
            . '</select>';

        // PC テーブル行
        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= self::buildTableRowHtml(
                $row, $detailBaseUrl, $listQuery,
                $userMap, $completedNames
            );
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当する事故案件はありません。</td></tr>';
        }

        $tableHtml =
            '<div class="table-wrap list-pc-only">'
            . '<table class="table-fixed list-table list-table-accident">'
            . '<colgroup>'
            . '<col class="list-col-priority">'
            . '<col class="list-col-date">'
            . '<col class="list-col-customer">'
            . '<col class="list-col-assigned">'
            . '<col class="list-col-status">'
            . '<col class="list-col-reminder">'
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th style="text-align:center;">' . LP::sortLink('優先度', 'priority', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('事故日', 'accident_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('契約者名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>担当</th>'
            . '<th>' . LP::sortLink('状態', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>次回リマインド</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . LP::mobileCardList(
                $rows,
                fn (array $row): string => self::buildMobileCardHtml($row, $detailBaseUrl, $listQuery, $userMap),
                '事故案件一覧（モバイル表示）'
            );

        $toolbarHtml = LP::searchToolbar([
            'searchUrl'         => $searchUrl,
            'searchParam'       => 'customer_name',
            'searchValue'       => (string) ($criteria['customer_name'] ?? ''),
            'searchPlaceholder' => '契約者名で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'accident-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'headerActions'     => '<button class="btn" type="button" data-open-dialog="accident-create-dialog">事故案件を追加</button>',
        ]);

        // クイックフィルタタブ（すべて / 高優先度・未完了 / 未完了 / 自分 / 完了）
        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => (string) ($criteria['quick_filter'] ?? ''),
            'tabs' => [
                ''          => ['label' => 'すべて',         'countKey' => 'all'],
                'high_open' => ['label' => '高優先度・未完了', 'countKey' => 'high_open'],
                'open'      => ['label' => '未完了',         'countKey' => 'open'],
                'mine'      => ['label' => '自分',           'countKey' => 'mine'],
                'completed' => ['label' => '完了',           'countKey' => 'completed'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $searchUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
            'mobileVisibleCount' => 3,
        ]);

        $filterDialogHtml = LP::filterDialog([
            'id'        => 'accident-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $searchUrl,
            'listState' => $listState,
            'preserveCriteria' => [
                'quick_filter' => (string) ($criteria['quick_filter'] ?? ''),
            ],
            'fields'    => [
                ['label' => '種目',   'html' => '<input type="text" name="product_type" value="' . Layout::escape($productType) . '" placeholder="部分一致">'],
                ['label' => '状態',   'html' => $statusSelectHtml],
                ['label' => '優先度', 'html' => $prioritySelectHtml],
                ['label' => '担当者', 'html' => self::renderUserFilterSelect($staffUsers, $filterUserId)],
            ],
            'clearUrl'  => $searchUrl,
        ]);

        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);

        $autoOpenId = $openModal === 'create'
            ? 'accident-create-dialog'
            : ($forceFilterOpen ? 'accident-filter-dialog' : null);

        $deleteDialogHtml = LP::deleteConfirmDialog([
            'deleteUrl' => $deleteActionUrl,
            'csrfToken' => $deleteCsrfToken,
            'listQuery' => $listQuery,
        ]);

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('事故案件一覧', '')
            . $noticeHtml
            . $listErrorHtml
            . $toolbarHtml
            . $quickFilterTabsHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . self::renderCreateDialog($storeUrl, $createCsrf, $createDraft, $searchUrl, $customerOptions, $staffUsers, $currentUser, $allStatuses)
            . $deleteDialogHtml
            . $filterDialogHtml
            . LP::dialogScript(['accident-create-dialog', 'accident-filter-dialog'], $autoOpenId);

        return Layout::render('事故案件一覧', $content, $layoutOptions);
    }

    /**
     * PC テーブル 1 行の HTML を生成する。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $listQuery
     * @param array<int, string> $userMap
     * @param array<string, bool> $completedNames
     */
    private static function buildTableRowHtml(
        array $row,
        string $detailBaseUrl,
        array $listQuery,
        array $userMap,
        array $completedNames
    ): string {
        $id              = (int) ($row['id'] ?? 0);
        $displayCustomer = (string) ($row['display_customer'] ?? $row['customer_name'] ?? '');
        $detailUrl       = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
        $assignedId      = (int) ($row['assigned_staff_id'] ?? 0);
        $assignedName    = $assignedId > 0 ? ($userMap[$assignedId] ?? '-') : '-';
        $reminderHtml    = self::formatReminderDate((string) ($row['next_reminder_date'] ?? ''));
        $deleteLabel     = $displayCustomer !== '' ? $displayCustomer : ('ID: ' . $id);

        $priorityCtx       = StatusBadge::renderByPriority((string) ($row['priority'] ?? ''));
        $priorityBadgeHtml = '<span class="badge ' . $priorityCtx['badge'] . '">' . Layout::escape($priorityCtx['label']) . '</span>';

        $rowStatusName = (string) ($row['status'] ?? '');
        $rowClass = isset($completedNames[$rowStatusName]) ? ' class="is-completed-row"' : '';

        $accidentDate = (string) ($row['accident_date'] ?? '');
        $productType  = (string) ($row['product_type'] ?? '');
        $secondaryHtml = $productType !== ''
            ? '<div class="list-row-secondary">' . Layout::escape($productType) . '</div>'
            : '';

        return '<tr' . $rowClass . ' data-urgency="' . Layout::escape($priorityCtx['urgency']) . '">'
            . '<td class="td-pair" data-label="優先度" style="text-align:center;">' . $priorityBadgeHtml . '</td>'
            . '<td class="cell-date" data-label="事故日" style="white-space:nowrap;">' . Layout::escape($accidentDate) . '</td>'
            . '<td data-label="契約者名">'
            . '<div class="list-row-stack">'
            . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($displayCustomer) . '">' . Layout::escape($displayCustomer) . '</a>'
            . $secondaryHtml
            . '</div>'
            . '</td>'
            . '<td class="cell-ellipsis" data-label="担当" title="' . Layout::escape($assignedName) . '">' . Layout::escape($assignedName) . '</td>'
            . '<td class="td-pair" data-label="状態" style="white-space:nowrap;">' . self::renderStatusBadge((string) ($row['status'] ?? '')) . '</td>'
            . '<td data-label="次回リマインド" style="white-space:nowrap;">' . $reminderHtml . '</td>'
            . '<td>' . LP::deleteButton($id, $deleteLabel) . '</td>'
            . '</tr>';
    }

    /**
     * モバイル用 list-card の HTML を生成する（LP::mobileCardList から closure で呼ばれる）。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $listQuery
     * @param array<int, string> $userMap
     */
    private static function buildMobileCardHtml(
        array $row,
        string $detailBaseUrl,
        array $listQuery,
        array $userMap
    ): string {
        $id              = (int) ($row['id'] ?? 0);
        $displayCustomer = (string) ($row['display_customer'] ?? $row['customer_name'] ?? '');
        $detailUrl       = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
        $productType     = (string) ($row['product_type'] ?? '');
        $accidentDate    = (string) ($row['accident_date'] ?? '');
        $accidentNo      = (string) ($row['accident_no'] ?? '');
        $accidentSummary = (string) ($row['accident_summary'] ?? '');
        $assignedId      = (int) ($row['assigned_staff_id'] ?? 0);
        $assignedName    = $assignedId > 0 ? ($userMap[$assignedId] ?? '−') : '−';
        $statusLabel     = (string) ($row['status'] ?? '');

        $priorityCtx  = StatusBadge::renderByPriority((string) ($row['priority'] ?? ''));
        $priorityBadge = '<span class="badge ' . $priorityCtx['badge'] . '">' . Layout::escape($priorityCtx['label']) . '</span>';
        $statusBadge   = '<span class="badge badge-gray">' . Layout::escape($statusLabel !== '' ? $statusLabel : '未設定') . '</span>';

        $iconCalendar = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconTag      = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
        $iconUser     = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

        return '<li class="list-card with-stripe" data-urgency="' . Layout::escape($priorityCtx['urgency']) . '">'
            . '<span class="list-card-stripe ' . Layout::escape($priorityCtx['stripe']) . '" aria-hidden="true"></span>'
            . '<a class="list-card-link" href="' . $detailUrl . '">'
            . '<div class="list-card-top">'
            . '<span class="list-card-top-left">' . $priorityBadge
            . ($productType !== '' ? '<span class="list-card-product">' . Layout::escape($productType) . '</span>' : '')
            . '</span>'
            . '<span class="list-card-top-right">' . $statusBadge . '</span>'
            . '</div>'
            . '<div class="list-card-customer">' . Layout::escape($displayCustomer !== '' ? $displayCustomer : '（顧客なし）') . '</div>'
            . '<div class="list-card-policy">事故番号: ' . ($accidentNo !== '' ? Layout::escape($accidentNo) : '−') . '</div>'
            . ($accidentSummary !== ''
                ? '<div class="list-card-summary">' . Layout::escape($accidentSummary) . '</div>'
                : '')
            . '<div class="list-card-meta">'
            . '<span class="list-card-meta-item">' . $iconCalendar . '<span class="list-card-meta-value">' . ($accidentDate !== '' ? Layout::escape($accidentDate) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconTag . '<span class="list-card-meta-value">' . ($productType !== '' ? Layout::escape($productType) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconUser . '<span class="list-card-meta-value">' . Layout::escape($assignedName) . '</span></span>'
            . '</div>'
            . '</a>'
            . '</li>';
    }

    /**
     * @param array<string, mixed>|null $draft
     * @param array<int, array<string, mixed>> $customerOptions
     * @param array<string, mixed> $currentUser
     * @param array<int, array<string, mixed>> $allStatuses
     */
    /**
     * @param array<int, array<string, mixed>> $staffUsers
     */
    private static function renderCreateDialog(
        string $storeUrl,
        string $csrfToken,
        ?array $draft,
        string $returnTo,
        array $customerOptions,
        array $staffUsers,
        array $currentUser,
        array $allStatuses = []
    ): string {
        $currentStatus = (string) ($draft['status'] ?? '');
        $statusHtml = '';
        $firstName = '';
        foreach ($allStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if ($firstName === '') {
                $firstName = $name;
            }
            $selected = ($name === $currentStatus || ($currentStatus === '' && $name === $firstName)) ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($name) . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        // お客さまコンボボックス用 datalist
        $selectedCustomerId   = (int) ($draft['customer_id'] ?? 0);
        $selectedProspectName = (string) ($draft['prospect_name'] ?? '');
        $selectedCustomerText = '';
        $customerDlId         = 'accident-create-customer-dl';
        $customerDatalist     = '';
        foreach ($customerOptions as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = (string) ($row['customer_name'] ?? '');
            if ($id === $selectedCustomerId) {
                $selectedCustomerText = $label;
            }
            $customerDatalist .= '<option value="' . Layout::escape($label) . '" data-id="' . $id . '">';
        }
        if ($selectedCustomerText === '' && $selectedProspectName !== '') {
            $selectedCustomerText = $selectedProspectName;
        }

        $defaultUserId  = (int) ($currentUser['id'] ?? 0);
        $assignedUserId = (int) ($draft['assigned_staff_id'] ?? $defaultUserId);
        $scStaffName    = Layout::escape((string) ($draft['sc_staff_name'] ?? ''));

        $assignedStaffHtml = '<option value="">未設定</option>';
        foreach ($staffUsers as $s) {
            $sid   = (int) ($s['id'] ?? 0);
            $sname = Layout::escape(trim((string) ($s['staff_name'] ?? $s['name'] ?? '')));
            if ($sid <= 0 || $sname === '') {
                continue;
            }
            $selA = $sid === $assignedUserId ? ' selected' : '';
            $assignedStaffHtml .= '<option value="' . $sid . '"' . $selA . '>' . $sname . '</option>';
        }

        $accidentDate      = Layout::escape((string) ($draft['accident_date'] ?? date('Y-m-d')));
        $insuranceCategory = Layout::escape((string) ($draft['insurance_category'] ?? ''));
        $intakeBranch      = Layout::escape((string) ($draft['accident_location'] ?? ($currentUser['default_branch'] ?? '')));
        $remark            = Layout::escape((string) ($draft['remark'] ?? ''));

        return ''
            . '<dialog id="accident-create-dialog" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>事故案件を追加</h2></div>'
            . '<p class="muted">新規事故案件を登録します。登録後は詳細画面で対応状況を管理できます。</p>'
            . '<form method="post" action="' . Layout::escape($storeUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<input type="hidden" name="customer_id" id="accident-create-customer-id" value="' . ($selectedCustomerId > 0 ? $selectedCustomerId : '') . '">'
            . '<input type="hidden" name="prospect_name" id="accident-create-prospect-name" value="' . Layout::escape($selectedCustomerId > 0 ? '' : $selectedProspectName) . '">'
            . '<datalist id="' . $customerDlId . '">' . $customerDatalist . '</datalist>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">受付基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>事故日 <strong class="required-mark">*</strong></span><input type="date" name="accident_date" value="' . $accidentDate . '" required></label>'
            . '<label class="list-filter-field"><span>状態 <strong class="required-mark">*</strong></span><select name="status" required>' . $statusHtml . '</select></label>'
            . '<label class="list-filter-field"><span>保険種類 <strong class="required-mark">*</strong></span><input type="text" name="insurance_category" value="' . $insuranceCategory . '" required></label>'
            . '<label class="list-filter-field"><span>お客さま名 <strong class="required-mark">*</strong></span><input type="text" list="' . $customerDlId . '" id="accident-create-customer-text" autocomplete="off" value="' . Layout::escape($selectedCustomerText) . '" placeholder="既存顧客から選択 または 依頼者名を入力" required></label>'
            . '<label class="list-filter-field"><span>担当拠点 <strong class="required-mark">*</strong></span><input type="text" name="intake_branch" value="' . $intakeBranch . '" required></label>'
            . '<label class="list-filter-field"><span>SC担当者</span><input type="text" name="sc_staff_name" value="' . $scStaffName . '" maxlength="100"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">担当情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>担当者 <strong class="required-mark">*</strong></span><select name="assigned_staff_id" required>' . $assignedStaffHtml . '</select></label>'
            . '</div>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="accident-create-dialog">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '<script>(function(){'
            . 'var txt=document.getElementById("accident-create-customer-text");'
            . 'var hid=document.getElementById("accident-create-customer-id");'
            . 'var pros=document.getElementById("accident-create-prospect-name");'
            . 'var dl=document.getElementById("' . $customerDlId . '");'
            . 'if(!txt||!hid||!pros||!dl){return;}'
            . 'function sync(){var v=txt.value;var opts=dl.querySelectorAll("option");var found=false;'
            . 'for(var i=0;i<opts.length;i++){if(opts[i].value===v){hid.value=opts[i].getAttribute("data-id")||"";found=true;break;}}'
            . 'if(found){pros.value="";}else{hid.value="";pros.value=v;}}'
            . 'txt.addEventListener("input",sync);txt.addEventListener("change",sync);'
            . '})();</script>'
            . '</dialog>';
    }

    private static function renderStatusBadge(string $status): string
    {
        $label = $status !== '' ? $status : '未設定';
        return '<span class="badge badge-gray">' . Layout::escape($label) . '</span>';
    }

    /**
     * @param array<int, array<string, mixed>> $staffUsers
     */
    private static function renderUserFilterSelect(array $staffUsers, int $currentUserId): string
    {
        $html = '<select name="assigned_staff_id"><option value="">全担当者</option>';
        foreach ($staffUsers as $u) {
            $uid      = (int) ($u['id'] ?? 0);
            $uname    = Layout::escape((string) ($u['staff_name'] ?? $u['name'] ?? ''));
            $selected = $currentUserId === $uid ? ' selected' : '';
            $html .= '<option value="' . $uid . '"' . $selected . '>' . $uname . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private static function formatReminderDate(string $date): string
    {
        if ($date === '') {
            return '<span class="muted">—</span>';
        }

        $ts = strtotime($date);
        if ($ts === false) {
            return '<span class="muted">—</span>';
        }

        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $w  = (int) date('w', $ts);
        $md = date('Y', $ts) . '/' . (int) date('n', $ts) . '/' . (int) date('j', $ts);

        return Layout::escape($md . '（' . $weekdays[$w] . '）');
    }
}
