<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class AccidentCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, mixed> $layoutOptions
     * @param array<int, array<string, mixed>> $allStatuses
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
        array $allStatuses = []
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

        $customerName   = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $productType    = Layout::escape((string) ($criteria['product_type'] ?? ''));
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

        $perPage    = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort       = (string) ($listState['sort'] ?? '');
        $direction  = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $listErrorHtml !== '';
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery  = LP::queryParams($criteria, $listState);

        // ステータスフィルター選択肢と完了扱い name セット
        $completedNames = [];
        $statusFilterOptions = ['' => 'すべて'];
        foreach ($allStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name === '') { continue; }
            $statusFilterOptions[$name] = $name;
            if ((int) ($sRow['is_completed'] ?? 0) === 1) {
                $completedNames[$name] = true;
            }
        }
        $statusHtml = '';
        foreach ($statusFilterOptions as $value => $label) {
            $selected = $status === $value ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $detailUrl    = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $assignedId   = (int) ($row['assigned_staff_id'] ?? 0);
            $assignedName = $assignedId > 0 ? ($userMap[$assignedId] ?? '-') : '-';
            $reminderHtml = self::formatReminderDate((string) ($row['next_reminder_date'] ?? ''));
            $deleteFormId = 'form-del-accident-' . $id;
            $displayCustomer = (string) ($row['display_customer'] ?? $row['customer_name'] ?? '');
            $deleteLabel  = $displayCustomer !== '' ? $displayCustomer : ('ID: ' . $id);
            $deleteForm = '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteActionUrl) . '" style="display:inline;">'
                . LP::routeInput($deleteActionUrl)
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrfToken) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . LP::hiddenInputs(LP::queryParams($criteria, $listState))
                . '<button type="button" class="btn-icon-delete" title="削除"'
                . ' data-delete-form="' . $deleteFormId . '"'
                . ' data-delete-label="' . Layout::escape($deleteLabel) . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                . '</button>'
                . '</form>';
            $rowStatusName = (string) ($row['status'] ?? '');
            $rowClass = isset($completedNames[$rowStatusName]) ? ' class="is-completed-row"' : '';
            $rowsHtml .= '<tr' . $rowClass . '>'
                . '<td class="cell-ellipsis" data-label="契約者名" title="' . Layout::escape($displayCustomer) . '"><a class="text-link" href="' . $detailUrl . '">' . Layout::escape($displayCustomer) . '</a></td>'
                . '<td data-label="事故日" style="white-space:nowrap;">' . Layout::escape((string) ($row['accident_date'] ?? '')) . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当" title="' . Layout::escape($assignedName) . '">' . Layout::escape($assignedName) . '</td>'
                . '<td class="td-pair" data-label="状態" style="white-space:nowrap;">' . self::renderStatusBadge((string) ($row['status'] ?? '')) . '</td>'
                . '<td class="td-pair" data-label="優先度" style="text-align:center;">' . self::renderPriorityBadge((string) ($row['priority'] ?? '')) . '</td>'
                . '<td data-label="次回リマインド" style="white-space:nowrap;">' . $reminderHtml . '</td>'
                . '<td>' . $deleteForm . '</td>'
                . '</tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="8">該当する事故案件はありません。</td></tr>';
        }

        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);

        $staffSelectHtml = str_replace('<select', '<select class="compact-input w-md"', self::renderUserFilterSelect($staffUsers, $filterUserId));
        $filterPanelHtml =
            '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($searchUrl)) . '">'
            . LP::routeInput($searchUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">契約者名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">種目</span><input type="text" name="product_type" class="compact-input w-md" value="' . $productType . '"></div>'
            . '<div class="search-field"><span class="search-label">状態</span><select name="status" class="compact-input w-sm">' . $statusHtml . '</select></div>'
            . '<div class="search-field"><span class="search-label">優先度</span><select name="priority" class="compact-input w-sm">'
            . '<option value="">すべて</option>'
            . '<option value="high"'   . ($priorityFilter === 'high'   ? ' selected' : '') . '>高</option>'
            . '<option value="normal"' . ($priorityFilter === 'normal' ? ' selected' : '') . '>中</option>'
            . '<option value="low"'    . ($priorityFilter === 'low'    ? ' selected' : '') . '>低</option>'
            . '</select></div>'
            . '<div class="search-field"><span class="search-label">担当者</span>' . $staffSelectHtml . '</div>'
            . '<div class="search-actions">'
            . '<button class="btn btn-small" type="submit">検索</button>'
            . '<a class="btn btn-small btn-secondary" href="' . Layout::escape($searchUrl) . '">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-accident">'
            . '<colgroup>'
            . '<col class="list-col-customer">'
            . '<col class="list-col-date">'
            . '<col class="list-col-product">'
            . '<col class="list-col-assigned">'
            . '<col class="list-col-status">'
            . '<col class="list-col-priority">'
            . '<col class="list-col-reminder">'
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('契約者名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('事故日', 'accepted_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>担当</th>'
            . '<th>' . LP::sortLink('状態', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th style="text-align:center;">' . LP::sortLink('優先度', 'priority', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>次回リマインド</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $deleteConfirmDialog =
            '<dialog id="dlg-delete-accident-confirm" class="modal-dialog">'
            . '<div class="modal-head"><h2>削除の確認</h2>'
            . '<button type="button" class="modal-close" id="dlg-delete-accident-close">×</button>'
            . '</div>'
            . '<p id="dlg-delete-accident-msg" style="margin:16px 0;"></p>'
            . '<div class="actions">'
            . '<button type="button" id="dlg-delete-accident-ok" class="btn btn-danger">削除する</button>'
            . '<button type="button" id="dlg-delete-accident-cancel" class="btn btn-ghost">キャンセル</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . '(function(){'
            . 'var dlg=document.getElementById("dlg-delete-accident-confirm");'
            . 'if(!dlg){return;}'
            . 'var msg=document.getElementById("dlg-delete-accident-msg");'
            . 'var pendingId=null;'
            . 'function closeDlg(){if(dlg.open){dlg.close();}pendingId=null;}'
            . 'document.querySelectorAll("[data-delete-form]").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'pendingId=btn.getAttribute("data-delete-form");'
            . 'var label=btn.getAttribute("data-delete-label")||"この件";'
            . 'msg.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";'
            . 'if(!dlg.open){dlg.showModal();}'
            . '});});'
            . 'document.getElementById("dlg-delete-accident-ok").addEventListener("click",function(){'
            . 'if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}'
            . '});'
            . 'document.getElementById("dlg-delete-accident-cancel").addEventListener("click",closeDlg);'
            . 'document.getElementById("dlg-delete-accident-close").addEventListener("click",closeDlg);'
            . '})();'
            . '</script>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('事故案件一覧', '<button class="btn" type="button" data-open-dialog="accident-create-dialog">事故案件を追加</button>')
            . $noticeHtml
            . $listErrorHtml
            . $filterPanelHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . self::renderCreateDialog($storeUrl, $createCsrf, $createDraft, $searchUrl, $customerOptions, $staffUsers, $currentUser, $allStatuses)
            . $deleteConfirmDialog
            . '<script>'
            . '(function(){const id="accident-create-dialog";const dlg=document.getElementById(id);if(!dlg||typeof dlg.showModal!=="function"){return;}const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const rect=dlg.getBoundingClientRect();const inside=rect.left<=e.clientX&&e.clientX<=rect.right&&rect.top<=e.clientY&&e.clientY<=rect.bottom;if(!inside&&dlg.open){dlg.close();}});if(' . ($openModal === 'create' ? 'true' : 'false') . '){dlg.showModal();}})()'
            . '</script>';

        return Layout::render('事故案件一覧', $content, $layoutOptions);
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
            if ($name === '') { continue; }
            if ($firstName === '') { $firstName = $name; }
            $selected = ($name === $currentStatus || ($currentStatus === '' && $name === $firstName)) ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($name) . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        // お客さまコンボボックス用 datalist
        $selectedCustomerId = (int) ($draft['customer_id'] ?? 0);
        $selectedProspectName = (string) ($draft['prospect_name'] ?? '');
        $selectedCustomerText = '';
        $customerDlId = 'accident-create-customer-dl';
        $customerDatalist = '';
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
        // 既存顧客が選ばれていない（NULL）かつ prospect_name が draft にある場合は、その値を表示
        if ($selectedCustomerText === '' && $selectedProspectName !== '') {
            $selectedCustomerText = $selectedProspectName;
        }

        // スタッフプルダウン共通生成
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
        // 表示名がそのまま DB 格納値。設定画面で自由に変更できるため中立色で表示。
        $label = $status !== '' ? $status : '未設定';
        return '<span class="badge badge-gray">' . Layout::escape($label) . '</span>';
    }

    private static function renderPriorityBadge(string $priority): string
    {
        $label = match ($priority) {
            'high'   => '高',
            'normal' => '中',
            'low'    => '低',
            default  => '-',
        };
        $class = match ($priority) {
            'high'   => 'priority-high',
            'normal' => 'priority-medium',
            'low'    => 'priority-low',
            default  => 'priority-none',
        };

        return '<span class="priority-badge ' . $class . '">' . Layout::escape($label) . '</span>';
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
