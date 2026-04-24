<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Domain\SalesCase\SalesCaseRepository;
use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;
use App\Presentation\View\StatusBadge;

final class SalesCaseListView
{
    /**
     * @param array<int, array<string, mixed>>      $rows
     * @param array<string, string>                 $criteria
     * @param array<string, string>                 $listState
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, mixed>                  $layoutOptions
     */
    /**
     * @param array<int, array<string, mixed>> $salesCaseStatuses
     */
    public static function render(
        array $rows,
        int $total,
        array $criteria,
        array $listState,
        array $staffUsers,
        array $customers,
        array $productCategories,
        int $loginStaffId,
        string $listUrl,
        string $storeUrl,
        string $storeCsrf,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        string $deleteUrl,
        string $deleteCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $layoutOptions,
        array $salesCaseStatuses = [],
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

        $perPage    = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $total);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $total, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        // 絞込バッジ件数（case_name=検索バー以外）
        $advancedFilterCount = 0;
        foreach (['customer_name', 'status', 'prospect_rank', 'staff_id'] as $k) {
            if ((string) ($criteria[$k] ?? '') !== '') {
                $advancedFilterCount++;
            }
        }

        $toolbarHtml = LP::searchToolbar([
            'searchUrl'         => $listUrl,
            'searchParam'       => 'case_name',
            'searchValue'       => (string) ($criteria['case_name'] ?? ''),
            'searchPlaceholder' => '見込案件名で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'scase-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'headerActions'     => '<button class="btn btn-primary" type="button" data-open-dialog="sales-case-create-dialog">＋ 見込案件登録</button>',
        ]);

        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => (string) ($criteria['quick_filter'] ?? ''),
            'tabs' => [
                ''          => ['label' => 'すべて',          'countKey' => 'all'],
                'high_open' => ['label' => '高見込度・未成約', 'countKey' => 'high_open'],
                'open'      => ['label' => '未成約',          'countKey' => 'open'],
                'mine'      => ['label' => '自分',            'countKey' => 'mine'],
                'completed' => ['label' => '成約',            'countKey' => 'completed'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $listUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
        ]);

        $filterDialogHtml = self::renderFilterDialog($criteria, $listUrl, $staffUsers, $listState, $salesCaseStatuses);

        $tableHtml = '<div class="table-wrap list-pc-only">'
            . self::renderTable($rows, $detailBaseUrl, $customerDetailBaseUrl, $deleteUrl, $deleteCsrf, $listUrl, $criteria, $listState, $salesCaseStatuses)
            . '</div>'
            . LP::mobileCardList(
                $rows,
                fn (array $row): string => self::buildMobileCardHtml($row, $detailBaseUrl),
                '見込案件一覧（モバイル表示）'
            );


        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('見込案件一覧', '')
            . $noticeHtml
            . $toolbarHtml
            . $quickFilterTabsHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . LP::deleteConfirmDialog([
                'deleteUrl' => $deleteUrl,
                'csrfToken' => $deleteCsrf,
                'listQuery' => LP::queryParams($criteria, $listState),
            ])
            . $filterDialogHtml
            . self::renderCreateDialog($storeUrl, $storeCsrf, $customers, $staffUsers, $productCategories, $loginStaffId, $listUrl, $salesCaseStatuses)
            . LP::dialogScript(['sales-case-create-dialog', 'scase-filter-dialog']);

        return Layout::render('見込案件一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string>                 $criteria
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, string>                 $listState
     * @param array<int, array<string, mixed>> $salesCaseStatuses
     */
    private static function renderFilterDialog(
        array $criteria,
        string $listUrl,
        array $staffUsers,
        array $listState,
        array $salesCaseStatuses = []
    ): string {
        $customerName = Layout::escape($criteria['customer_name'] ?? '');
        $selStatus    = $criteria['status'] ?? '';
        $selRank      = $criteria['prospect_rank'] ?? '';
        $selStaff     = $criteria['staff_id'] ?? '';

        $statusOptions = '<option value="">— 対応状況 —</option>';
        foreach (self::buildStatusOptions($salesCaseStatuses) as $name) {
            $sel = $selStatus === $name ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($name) . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        $rankOptions = '<option value="">— 見込度 —</option>';
        foreach (SalesCaseRepository::ALLOWED_PROSPECT_RANKS as $rank) {
            $sel = $selRank === $rank ? ' selected' : '';
            $rankOptions .= '<option value="' . Layout::escape($rank) . '"' . $sel . '>' . Layout::escape($rank) . '</option>';
        }

        $staffOptions = '<option value="">全員</option>';
        foreach ($staffUsers as $u) {
            $uid  = (int) ($u['id'] ?? 0);
            $name = Layout::escape((string) ($u['staff_name'] ?? $u['name'] ?? ''));
            $sel  = $selStaff === (string) $uid ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $sel . '>' . $name . '</option>';
        }

        return LP::filterDialog([
            'id'        => 'scase-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $listUrl,
            'listState' => $listState,
            'preserveCriteria' => [
                'quick_filter' => (string) ($criteria['quick_filter'] ?? ''),
            ],
            'fields'    => [
                ['label' => '顧客名',   'html' => '<input type="text" name="customer_name" value="' . $customerName . '" placeholder="部分一致">'],
                ['label' => '担当者',   'html' => '<select name="staff_id">' . $staffOptions . '</select>'],
                ['label' => '対応状況', 'html' => '<select name="status">' . $statusOptions . '</select>'],
                ['label' => '見込度',   'html' => '<select name="prospect_rank">' . $rankOptions . '</select>'],
            ],
            'clearUrl'  => $listUrl,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderTable(
        array $rows,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        string $deleteUrl,
        string $deleteCsrf,
        string $searchUrl = '',
        array $criteria = [],
        array $listState = [],
        array $salesCaseStatuses = []
    ): string {
        $completedNames = [];
        foreach ($salesCaseStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name !== '' && (int) ($sRow['is_completed'] ?? 0) === 1) {
                $completedNames[$name] = true;
            }
        }
        $colgroup = '<colgroup>'
            . '<col class="list-col-rank">'
            . '<col class="list-col-name">'
            . '<col class="list-col-product">'
            . '<col class="list-col-status">'
            . '<col class="list-col-staff">'
            . '<col class="list-col-action">'
            . '</colgroup>';

        $thead =
            '<thead><tr>'
            . '<th style="text-align:center;white-space:nowrap;">' . LP::sortLink('見込度', 'prospect_rank', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('見込案件名', 'case_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>種目</th>'
            . '<th>' . LP::sortLink('対応状況', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>担当者</th>'
            . '<th></th>'
            . '</tr></thead>';

        if ($rows === []) {
            return '<table class="table-fixed table-card list-table list-table-scase">' . $colgroup . $thead
                . '<tbody><tr><td colspan="6">該当する見込案件はありません。</td></tr></tbody></table>';
        }

        $tbody = '<tbody>';
        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $customerName = (string) ($row['customer_name'] ?? '') ?: (string) ($row['prospect_name'] ?? '');
            $caseName     = (string) ($row['case_name'] ?? '');
            $caseType     = (string) ($row['case_type'] ?? '');
            $rank         = (string) ($row['prospect_rank'] ?? '');
            $status       = (string) ($row['status'] ?? '');
            $staffName    = (string) ($row['staff_name'] ?? '');

            $caseTypeLabel = SalesCaseRepository::ALLOWED_CASE_TYPES[$caseType] ?? $caseType;

            $detailUrl  = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));
            $deleteLabel = $caseName !== '' ? $caseName : ('ID: ' . $id);

            $secondaryHtml = $customerName !== ''
                ? '<div class="list-row-secondary">' . Layout::escape($customerName) . '</div>'
                : '';

            $rankCtx   = StatusBadge::renderByRank($rank);
            $rankBadge = $rank !== ''
                ? '<span class="badge ' . $rankCtx['badge'] . '">' . Layout::escape($rankCtx['label']) . '</span>'
                : '<span class="muted">-</span>';

            $rowClass = isset($completedNames[$status]) ? ' class="is-completed-row"' : '';
            $tbody .= '<tr' . $rowClass . ' data-urgency="' . Layout::escape($rankCtx['urgency']) . '">'
                . '<td class="td-pair" data-label="見込度" style="text-align:center;">' . $rankBadge . '</td>'
                . '<td data-label="見込案件名">'
                . '<div class="list-row-stack">'
                . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($caseName) . '">' . Layout::escape($caseName) . '</a>'
                . $secondaryHtml
                . '</div>'
                . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape($caseTypeLabel) . '">' . Layout::escape($caseTypeLabel) . '</td>'
                . '<td class="td-pair" data-label="対応状況" style="white-space:nowrap;">' . self::statusBadge($status) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当者" title="' . Layout::escape($staffName) . '">' . Layout::escape($staffName) . '</td>'
                . '<td style="text-align:center;">' . LP::deleteButton($id, $deleteLabel) . '</td>'
                . '</tr>';
        }
        $tbody .= '</tbody>';

        return '<table class="table-fixed table-card list-table list-table-scase">' . $colgroup . $thead . $tbody . '</table>';
    }

    /**
     * モバイル用 list-card HTML（事故案件カードと同じ構成）。
     *
     * @param array<string, mixed> $row
     */
    private static function buildMobileCardHtml(array $row, string $detailBaseUrl): string
    {
        $id              = (int) ($row['id'] ?? 0);
        $customerName    = (string) ($row['customer_name'] ?? '') ?: (string) ($row['prospect_name'] ?? '');
        $caseName        = (string) ($row['case_name'] ?? '');
        $caseType        = (string) ($row['case_type'] ?? '');
        $caseTypeLabel   = SalesCaseRepository::ALLOWED_CASE_TYPES[$caseType] ?? $caseType;
        $rank            = (string) ($row['prospect_rank'] ?? '');
        $status          = (string) ($row['status'] ?? '');
        $staffName       = (string) ($row['staff_name'] ?? '');
        $expectedMonth   = (string) ($row['expected_contract_month'] ?? '');
        $detailUrl       = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));

        $rankCtx   = StatusBadge::renderByRank($rank);
        $rankBadge = $rank !== ''
            ? '<span class="badge ' . $rankCtx['badge'] . '">' . Layout::escape($rankCtx['label']) . '</span>'
            : '';
        $statusBadge = '<span class="badge badge-gray">' . Layout::escape($status !== '' ? $status : '未設定') . '</span>';

        $iconCalendar = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconTag      = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
        $iconUser     = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

        return '<li class="list-card with-stripe" data-urgency="' . Layout::escape($rankCtx['urgency']) . '">'
            . '<span class="list-card-stripe ' . Layout::escape($rankCtx['stripe']) . '" aria-hidden="true"></span>'
            . '<a class="list-card-link" href="' . $detailUrl . '">'
            . '<div class="list-card-top">'
            . '<span class="list-card-top-left">' . $rankBadge
            . ($caseTypeLabel !== '' ? '<span class="list-card-product">' . Layout::escape($caseTypeLabel) . '</span>' : '')
            . '</span>'
            . '<span class="list-card-top-right">' . $statusBadge . '</span>'
            . '</div>'
            . '<div class="list-card-customer">' . Layout::escape($customerName !== '' ? $customerName : '（顧客未設定）') . '</div>'
            . '<div class="list-card-policy">案件名: ' . ($caseName !== '' ? Layout::escape($caseName) : '−') . '</div>'
            . '<div class="list-card-meta">'
            . '<span class="list-card-meta-item">' . $iconCalendar . '<span class="list-card-meta-value">' . ($expectedMonth !== '' ? Layout::escape($expectedMonth) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconTag . '<span class="list-card-meta-value">' . ($caseTypeLabel !== '' ? Layout::escape($caseTypeLabel) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconUser . '<span class="list-card-meta-value">' . ($staffName !== '' ? Layout::escape($staffName) : '−') . '</span></span>'
            . '</div>'
            . '</a>'
            . '</li>';
    }

    private static function rankBadge(string $rank): string
    {
        if ($rank === '') {
            return '<span class="muted">-</span>';
        }
        $class = match ($rank) {
            'A' => 'badge-danger',
            'B' => 'badge-warning',
            'C' => 'badge-info',
            default => '',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($rank) . '</span>';
    }

    /**
     * 有効ステータス一覧（DB から取得した配列）から value=name の選択肢リストを作る。
     * 表示名がそのまま DB 格納値を兼ねる。
     *
     * @param array<int, array<string, mixed>> $salesCaseStatuses
     * @return list<string>
     */
    private static function buildStatusOptions(array $salesCaseStatuses): array
    {
        $names = [];
        foreach ($salesCaseStatuses as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }

    private static function statusBadge(string $status): string
    {
        // 設定画面で自由に名前を変えられるため、個別色はつけず中立のバッジで表示する。
        return '<span class="badge">' . Layout::escape($status) . '</span>';
    }

    /**
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $productCategories
     * @param array<int, array<string, mixed>> $salesCaseStatuses
     */
    private static function renderCreateDialog(
        string $storeUrl,
        string $storeCsrf,
        array $customers,
        array $staffUsers,
        array $productCategories,
        int $loginStaffId,
        string $returnTo,
        array $salesCaseStatuses = []
    ): string {
        $custDatalist = '';
        foreach ($customers as $c) {
            $cid   = (int) ($c['id'] ?? 0);
            $cname = Layout::escape((string) ($c['customer_name'] ?? ''));
            if ($cid <= 0 || $cname === '') {
                continue;
            }
            $custDatalist .= '<option value="' . $cname . '" data-id="' . $cid . '">';
        }

        $statusOptions = '';
        // 新規登録ダイアログは先頭（display_order 最小）の有効ステータスをデフォルト選択にする
        $firstName = '';
        foreach (self::buildStatusOptions($salesCaseStatuses) as $name) {
            if ($firstName === '') {
                $firstName = $name;
            }
            $sel = $name === $firstName ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($name) . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        $rankOptions = '<option value="">— 未設定 —</option>';
        foreach (SalesCaseRepository::ALLOWED_PROSPECT_RANKS as $rank) {
            $rankOptions .= '<option value="' . Layout::escape($rank) . '">' . Layout::escape($rank) . '</option>';
        }

        $staffOptions = '<option value="">— 選択 —</option>';
        foreach ($staffUsers as $u) {
            $uid   = (int) ($u['id'] ?? 0);
            $uname = Layout::escape((string) ($u['staff_name'] ?? $u['name'] ?? ''));
            $sel   = ($loginStaffId > 0 && $uid === $loginStaffId) ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $sel . '>' . $uname . '</option>';
        }

        $productOptions = '<option value="">— 未選択 —</option>';
        foreach ($productCategories as $cat) {
            $catVal = Layout::escape((string) ($cat['name'] ?? ''));
            $productOptions .= '<option value="' . $catVal . '">' . $catVal . '</option>';
        }

        $req = '<strong class="required-mark">*</strong>';

        return '<dialog id="sales-case-create-dialog" class="modal-dialog">'
            . '<div class="modal-head"><h2>見込案件を登録</h2>'
            . '<button type="button" class="modal-close" onclick="document.getElementById(\'sales-case-create-dialog\').close()">×</button>'
            . '</div>'
            . '<form method="post" action="' . Layout::escape($storeUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($storeCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<section class="modal-form-section">'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<div class="list-filter-field modal-form-wide" id="sc-cust-field">'
            . '<span style="display:flex;gap:16px;align-items:center;margin-bottom:6px;">'
            . '<label style="font-weight:500;margin:0;cursor:pointer;"><input type="radio" name="_cust_type" value="existing" id="sc-cust-existing" style="margin-right:4px;">顧客（既存）</label>'
            . '<label style="font-weight:500;margin:0;cursor:pointer;"><input type="radio" name="_cust_type" value="new" id="sc-cust-new" style="margin-right:4px;">顧客（新規）</label>'
            . '</span>'
            . '<datalist id="sc-cust-datalist">' . $custDatalist . '</datalist>'
            . '<div id="sc-cust-existing-wrap">'
            . '<input type="text" id="sc-cust-text" list="sc-cust-datalist" autocomplete="off" placeholder="顧客名で検索">'
            . '<input type="hidden" name="customer_id" id="sc-cust-select">'
            . '</div>'
            . '<div id="sc-cust-new-wrap" style="display:none;"><input type="text" name="prospect_name" id="sc-cust-prospect-name" maxlength="200" placeholder="会社名・氏名など"></div>'
            . '</div>'
            . '<label class="list-filter-field modal-form-wide"><span>案件名 ' . $req . '</span>'
            . '<input type="text" name="case_name" required maxlength="200"></label>'
            . '<label class="list-filter-field"><span>対応状況 ' . $req . '</span>'
            . '<select name="status" required>' . $statusOptions . '</select></label>'
            . '<label class="list-filter-field"><span>種目</span>'
            . '<select name="product_type">' . $productOptions . '</select></label>'
            . '<label class="list-filter-field"><span>見込度</span>'
            . '<select name="prospect_rank">' . $rankOptions . '</select></label>'
            . '<label class="list-filter-field"><span>想定保険料（円）</span>'
            . '<input type="number" name="expected_premium" min="0"></label>'
            . '<label class="list-filter-field"><span>契約予定月</span>'
            . '<input type="month" name="expected_contract_month"></label>'
            . '<label class="list-filter-field"><span>担当者</span>'
            . '<select name="staff_id">' . $staffOptions . '</select></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>メモ</span>'
            . '<textarea name="memo" rows="3"></textarea></label>'
            . '<p class="muted" style="font-size:12px;margin-top:8px;">次回予定日・紹介元などの詳細項目は、登録後に編集できます。</p>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn btn-primary" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" onclick="document.getElementById(\'sales-case-create-dialog\').close()">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '<script>(function(){'
            . 'var radios=document.querySelectorAll(\'input[name="_cust_type"]\');'
            . 'var existWrap=document.getElementById("sc-cust-existing-wrap");'
            . 'var newWrap=document.getElementById("sc-cust-new-wrap");'
            . 'var txt=document.getElementById("sc-cust-text");'
            . 'var hid=document.getElementById("sc-cust-select");'
            . 'var dl=document.getElementById("sc-cust-datalist");'
            . 'var pname=document.getElementById("sc-cust-prospect-name");'
            . 'if(!radios.length||!existWrap||!newWrap){return;}'
            . 'document.getElementById("sc-cust-existing").checked=true;'
            . 'function syncId(){'
            . '  if(!txt||!hid||!dl){return;}'
            . '  var v=txt.value;var opts=dl.querySelectorAll("option");'
            . '  hid.value="";'
            . '  for(var i=0;i<opts.length;i++){if(opts[i].value===v){hid.value=opts[i].getAttribute("data-id")||"";break;}}'
            . '}'
            . 'if(txt){txt.addEventListener("input",syncId);txt.addEventListener("change",syncId);}'
            . 'function toggle(){'
            . '  var v=document.querySelector(\'input[name="_cust_type"]:checked\').value;'
            . '  if(v==="existing"){'
            . '    existWrap.style.display="";newWrap.style.display="none";'
            . '    if(pname){pname.removeAttribute("required");pname.value="";}'
            . '  } else {'
            . '    existWrap.style.display="none";newWrap.style.display="";'
            . '    if(txt){txt.value="";}if(hid){hid.value="";}if(pname){pname.setAttribute("required","");}'
            . '  }'
            . '}'
            . 'radios.forEach(function(r){r.addEventListener("change",toggle);});'
            . '})();</script>'
            . '</dialog>';
    }
}
