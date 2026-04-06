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

        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $productType  = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $status       = (string) ($criteria['status'] ?? '');
        $filterUserId = (int) ($criteria['assigned_staff_id'] ?? 0);

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

        // ステータスフィルター選択肢とバッジ用ラベルマップ
        $badgeLabelMap = [];
        if ($allStatuses !== []) {
            $statusFilterOptions = ['' => 'すべて'];
            foreach ($allStatuses as $sRow) {
                $code = (string) ($sRow['code'] ?? '');
                $statusFilterOptions[$code] = (string) ($sRow['display_name'] ?? $code);
                $badgeLabelMap[$code] = (string) ($sRow['display_name'] ?? $code);
            }
        } else {
            $statusFilterOptions = [
                ''             => 'すべて',
                'accepted'     => '受付',
                'linked'       => '保険会社連絡済み',
                'in_progress'  => '対応中',
                'waiting_docs' => '書類待ち',
                'resolved'     => '解決済み',
                'closed'       => '完了',
            ];
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
            $rowsHtml .= '<tr>'
                . '<td data-label="契約者名"><a class="text-link" href="' . $detailUrl . '"><strong class="truncate list-row-primary" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</strong></a></td>'
                . '<td data-label="事故日">' . Layout::escape((string) ($row['accident_date'] ?? '')) . '</td>'
                . '<td data-label="種目"><span class="truncate" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</span></td>'
                . '<td data-label="担当">' . Layout::escape($assignedName) . '</td>'
                . '<td data-label="状態">' . self::renderStatusBadge((string) ($row['status'] ?? ''), $badgeLabelMap) . '</td>'
                . '<td data-label="優先度">' . self::renderPriorityBadge((string) ($row['priority'] ?? '')) . '</td>'
                . '<td data-label="次回リマインド">' . $reminderHtml . '</td>'
                . '</tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当する事故案件はありません。</td></tr>';
        }

        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);

        $filterFormHtml =
            '<form method="get" action="' . Layout::escape(LP::formAction($searchUrl)) . '">'
            . LP::routeInput($searchUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>契約者名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>状態</span><select name="status">' . $statusHtml . '</select></label>'
            . '<label class="list-filter-field"><span>担当者</span>' . self::renderUserFilterSelect($staffUsers, $filterUserId) . '</label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($searchUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>';

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-accident">'
            . '<colgroup>'
            . '<col class="list-col-customer">'
            . '<col class="list-col-date">'
            . '<col class="list-col-product">'
            . '<col style="width:80px;">'
            . '<col class="list-col-status">'
            . '<col class="list-col-priority">'
            . '<col style="width:100px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('契約者名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>事故日</th>'
            . '<th>種目</th>'
            . '<th>担当</th>'
            . '<th>' . LP::sortLink('状態', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('優先度', 'priority', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>次回リマインド</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('事故案件一覧', '<button class="btn" type="button" data-open-dialog="accident-create-dialog">事故案件を追加</button>')
            . $noticeHtml
            . LP::filterCard($filterFormHtml, $filterOpen, $listErrorHtml)
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . self::renderCreateDialog($storeUrl, $createCsrf, $createDraft, $searchUrl, $customerOptions, $currentUser, $allStatuses)
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
    private static function renderCreateDialog(
        string $storeUrl,
        string $csrfToken,
        ?array $draft,
        string $returnTo,
        array $customerOptions,
        array $currentUser,
        array $allStatuses = []
    ): string {
        $currentStatus = (string) ($draft['status'] ?? 'accepted');
        $statusHtml = '';
        if ($allStatuses !== []) {
            foreach ($allStatuses as $sRow) {
                $s        = (string) ($sRow['code'] ?? '');
                $label    = (string) ($sRow['display_name'] ?? $s);
                $selected = $s === $currentStatus ? ' selected' : '';
                $statusHtml .= '<option value="' . Layout::escape($s) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
            }
        } else {
            $statusLabels = [
                'accepted'     => '受付',
                'linked'       => '保険会社連絡済み',
                'in_progress'  => '対応中',
                'waiting_docs' => '書類待ち',
                'resolved'     => '解決済み',
                'closed'       => '完了',
            ];
            foreach (array_keys($statusLabels) as $s) {
                $selected = $s === $currentStatus ? ' selected' : '';
                $statusHtml .= '<option value="' . Layout::escape($s) . '"' . $selected . '>' . Layout::escape($statusLabels[$s]) . '</option>';
            }
        }

        $customerId   = (string) ($draft['customer_id'] ?? '');
        $customerHtml = '<option value="">選択してください</option>';
        foreach ($customerOptions as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $selected     = $customerId !== '' && (int) $customerId === $id ? ' selected' : '';
            $label        = (string) ($row['customer_name'] ?? '');
            $customerHtml .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $defaultUserId   = (int) ($currentUser['id'] ?? 0);
        $assignedUserId  = (string) ($draft['assigned_staff_id'] ?? ($defaultUserId > 0 ? (string) $defaultUserId : ''));
        $assignedUserName = (string) ($currentUser['name'] ?? 'ログインユーザー');

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
            . '<label class="list-filter-field"><span>担当者 <strong class="required-mark">*</strong></span><select name="assigned_staff_id" required><option value="' . Layout::escape($assignedUserId) . '" selected>' . Layout::escape($assignedUserName) . '</option></select></label>'
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
     * @param array<string, string> $labelMap  code => display_name from master (may be empty)
     */
    private static function renderStatusBadge(string $status, array $labelMap = []): string
    {
        $class = match ($status) {
            'resolved', 'closed'      => 'badge-success',
            'in_progress', 'linked'   => 'badge-info',
            'waiting_docs'            => 'badge-danger',
            default                   => 'badge-gray',
        };

        if (isset($labelMap[$status])) {
            $label = $labelMap[$status];
        } else {
            $label = match ($status) {
                'accepted'     => '受付',
                'linked'       => '保険会社連絡済み',
                'in_progress'  => '対応中',
                'waiting_docs' => '書類待ち',
                'resolved'     => '解決済み',
                'closed'       => '完了',
                default        => '未設定',
            };
        }

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
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
        $md = (int) date('n', $ts) . '/' . (int) date('j', $ts);

        return Layout::escape($md . '（' . $weekdays[$w] . '）');
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 事故受付日';
        }

        $label = match ($sort) {
            'accident_no'   => '事故管理番号',
            'customer_name' => '契約者名',
            'status'        => '状態',
            'priority'      => '優先度',
            default         => '事故受付日',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }
}
