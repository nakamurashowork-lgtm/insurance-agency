<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Domain\SalesCase\SalesCaseRepository;
use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class SalesCaseListView
{
    /**
     * @param array<int, array<string, mixed>>      $rows
     * @param array<string, string>                 $criteria
     * @param array<string, string>                 $listState
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, mixed>                  $layoutOptions
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
        $filterOpen = ListViewHelper::hasActiveFilters($criteria);
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $total);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $total, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $filterFormHtml = self::renderFilterForm($criteria, $listUrl, $staffUsers, $listState);
        $tableHtml = '<div class="table-wrap">'
            . self::renderTable($rows, $detailBaseUrl, $customerDetailBaseUrl, $deleteUrl, $deleteCsrf, $listUrl, $criteria, $listState)
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('見込案件一覧', '<button class="btn btn-primary" type="button" data-open-dialog="sales-case-create-dialog">＋ 見込案件登録</button>')
            . $noticeHtml
            . $filterFormHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="dlg-delete-scase-confirm" class="modal-dialog">'
            . '<div class="modal-head"><h2>削除の確認</h2>'
            . '<button type="button" class="modal-close" id="dlg-delete-scase-close">×</button>'
            . '</div>'
            . '<p id="dlg-delete-scase-msg" style="margin:16px 0;"></p>'
            . '<div class="dialog-actions">'
            . '<button type="button" id="dlg-delete-scase-ok" class="btn btn-danger">削除する</button>'
            . '<button type="button" id="dlg-delete-scase-cancel" class="btn btn-ghost">キャンセル</button>'
            . '</div>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlgD=document.getElementById("dlg-delete-scase-confirm");'
            . 'if(dlgD&&typeof dlgD.showModal==="function"){'
            . 'var msgD=document.getElementById("dlg-delete-scase-msg");'
            . 'var pendingId=null;'
            . 'document.querySelectorAll("[data-delete-form]").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'pendingId=btn.getAttribute("data-delete-form");'
            . 'var label=btn.getAttribute("data-delete-label")||"この件";'
            . 'msgD.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";'
            . 'if(!dlgD.open){dlgD.showModal();}});});'
            . 'function closeDlgD(){if(dlgD.open){dlgD.close();}pendingId=null;}'
            . 'document.getElementById("dlg-delete-scase-ok").addEventListener("click",function(){'
            . 'if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}'
            . 'closeDlgD();});'
            . 'document.getElementById("dlg-delete-scase-cancel").addEventListener("click",closeDlgD);'
            . 'document.getElementById("dlg-delete-scase-close").addEventListener("click",closeDlgD);'
            . '}}());</script>'
            . self::renderCreateDialog($storeUrl, $storeCsrf, $customers, $staffUsers, $productCategories, $loginStaffId, $listUrl)
            . '<script>(function(){'
            . 'var dlg=document.getElementById("sales-case-create-dialog");'
            . 'if(!dlg)return;'
            . 'document.querySelectorAll("[data-open-dialog=\'sales-case-create-dialog\']").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\'sales-case-create-dialog\']").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){if(dlg.open){dlg.close();}});});'
            . 'dlg.addEventListener("click",function(e){var r=dlg.getBoundingClientRect();'
            . 'if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom){if(dlg.open){dlg.close();}}});'
            . '})();</script>';

        return Layout::render('見込案件一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string>                 $criteria
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, string>                 $listState
     */
    private static function renderFilterForm(
        array $criteria,
        string $listUrl,
        array $staffUsers,
        array $listState
    ): string {
        $customerName = Layout::escape($criteria['customer_name'] ?? '');
        $selStatus    = $criteria['status'] ?? '';
        $selRank      = $criteria['prospect_rank'] ?? '';
        $selStaff     = $criteria['staff_id'] ?? '';

        $statusOptions = '<option value="">— ステータス —</option>';
        foreach (SalesCaseRepository::ALLOWED_STATUSES as $val => $label) {
            $sel = $selStatus === $val ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
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

        return '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false))
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">顧客名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">担当者</span><select name="staff_id" class="compact-input w-md">' . $staffOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">ステータス</span><select name="status" class="compact-input w-md">' . $statusOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">見込度</span><select name="prospect_rank" class="compact-input w-sm">' . $rankOptions . '</select></div>'
            . '<div class="search-actions">'
            . '<button type="submit" class="btn btn-small">検索</button>'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-small btn-secondary">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    /**
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
        array $listState = []
    ): string {
        $thead =
            '<thead><tr>'
            . '<th>' . LP::sortLink('案件名', 'case_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種別', 'case_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('見込度', 'prospect_rank', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('ステータス', 'status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>担当者</th>'
            . '<th style="width:48px;"></th>'
            . '</tr></thead>';

        if ($rows === []) {
            return '<table class="table-fixed table-card list-table">' . $thead
                . '<tbody><tr><td colspan="8">該当する見込案件はありません。</td></tr></tbody></table>';
        }

        $tbody = '<tbody>';
        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $customerId   = (int) ($row['customer_id'] ?? 0);
            $customerName = (string) ($row['customer_name'] ?? '');
            $caseName     = (string) ($row['case_name'] ?? '');
            $caseType     = (string) ($row['case_type'] ?? '');
            $productType  = (string) ($row['product_type'] ?? '');
            $rank         = (string) ($row['prospect_rank'] ?? '');
            $closeMonth   = (string) ($row['expected_contract_month'] ?? '');
            $status       = (string) ($row['status'] ?? '');
            $staffName    = (string) ($row['staff_name'] ?? '');

            $caseTypeLabel = SalesCaseRepository::ALLOWED_CASE_TYPES[$caseType] ?? $caseType;

            $custUrl  = $customerId > 0
                ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $customerId]))
                : '';
            $custLink = $custUrl !== ''
                ? '<a class="text-link" href="' . $custUrl . '">' . Layout::escape($customerName) . '</a>'
                : Layout::escape($customerName);

            $detailUrl  = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));
            $deleteFormId = 'form-del-scase-' . $id;
            $deleteForm = '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="button" class="btn-icon-delete" title="削除"'
                . ' data-delete-form="' . $deleteFormId . '"'
                . ' data-delete-label="' . Layout::escape($caseName) . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                . '</button>'
                . '</form>';

            $tbody .= '<tr>'
                . '<td class="cell-ellipsis" data-label="案件名" title="' . Layout::escape($caseName) . '"><a class="text-link" href="' . $detailUrl . '">' . Layout::escape($caseName) . '</a></td>'
                . '<td class="cell-ellipsis" data-label="顧客名" title="' . Layout::escape($customerName) . '">' . $custLink . '</td>'
                . '<td data-label="種別">' . Layout::escape($caseTypeLabel) . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape($productType) . '">' . Layout::escape($productType) . '</td>'
                . '<td data-label="見込度" style="text-align:center;">' . self::rankBadge($rank) . '</td>'
                . '<td data-label="ステータス">' . self::statusBadge($status) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当者" title="' . Layout::escape($staffName) . '">' . Layout::escape($staffName) . '</td>'
                . '<td style="text-align:center;">' . $deleteForm . '</td>'
                . '</tr>';
        }
        $tbody .= '</tbody>';

        $colgroup = '<colgroup>'
            . '<col>'
            . '<col style="width:180px">'
            . '<col style="width:92px">'
            . '<col style="width:140px">'
            . '<col style="width:82px">'
            . '<col style="width:108px">'
            . '<col style="width:100px">'
            . '<col style="width:40px">'
            . '</colgroup>';

        return '<table class="table-fixed table-card list-table">' . $colgroup . $thead . $tbody . '</table>';
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

    private static function statusBadge(string $status): string
    {
        $label = SalesCaseRepository::ALLOWED_STATUSES[$status] ?? $status;
        $class = match ($status) {
            'won'         => 'badge-success',
            'lost'        => 'badge-gray',
            'open'        => 'badge-danger',
            'negotiating' => 'badge-info',
            'on_hold'     => 'badge-gray',
            default       => '',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    /**
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $productCategories
     */
    private static function renderCreateDialog(
        string $storeUrl,
        string $storeCsrf,
        array $customers,
        array $staffUsers,
        array $productCategories,
        int $loginStaffId,
        string $returnTo
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
        foreach (SalesCaseRepository::ALLOWED_STATUSES as $val => $label) {
            $sel = $val === 'open' ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
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
            $catVal = Layout::escape((string) ($cat['display_name'] ?? ''));
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
            . '<small class="muted" style="display:block;margin-top:4px;">既存顧客に紐づける場合のみ選択。</small></div>'
            . '<div id="sc-cust-new-wrap" style="display:none;"><input type="text" name="prospect_name" id="sc-cust-prospect-name" maxlength="200" placeholder="会社名・氏名など"></div>'
            . '</div>'
            . '<label class="list-filter-field modal-form-wide"><span>案件名 ' . $req . '</span>'
            . '<input type="text" name="case_name" required maxlength="200"></label>'
            . '<label class="list-filter-field"><span>ステータス ' . $req . '</span>'
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
