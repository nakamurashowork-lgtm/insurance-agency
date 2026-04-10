<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class SalesPerformanceListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $customers
    * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<int, string> $performanceMonths
     * @param array<string, mixed>|null $createDraft
     * @param array<int, string> $allowedTypes
     * @param array<string, mixed>|null $importBatch
     * @param array<int, array<string, mixed>> $importRows
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        array $customers,
        array $staffUsers,
        array $contracts,
        array $renewalCases,
        array $performanceMonths,
        ?array $createDraft,
        string $openModal,
        string $listUrl,
        string $detailBaseUrl,
        string $createUrl,
        string $importUrl,
        string $deleteUrl,
        string $createCsrf,
        string $importCsrf,
        string $deleteCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $fatalError,
        array $allowedTypes,
        ?array $importBatch,
        array $importRows,
        bool $forceFilterOpen,
        array $layoutOptions
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($fatalError) && $fatalError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($fatalError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $perPage    = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort       = (string) ($listState['sort'] ?? '');
        $direction  = (string) ($listState['direction'] ?? 'asc');
        // performance_fiscal_year / performance_month_num はデフォルト値を持つため、アクティブフィルター判定から除外する
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters(array_diff_key($criteria, ['performance_fiscal_year' => true, 'performance_month_num' => true])) || $noticeHtml !== '';
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $activeModal = in_array($openModal, ['create_nonlife', 'create_life', 'import'], true) ? $openModal : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = ($createDraft['form_type'] ?? '') === 'life' ? 'create_life' : 'create_nonlife';
        }
        $activeDialogId = match ($activeModal) {
            'create_nonlife' => 'sales-create-nonlife-dialog',
            'create_life'    => 'sales-create-life-dialog',
            'import'         => 'sales-import-dialog',
            default          => '',
        };

        $nonlifeDraft  = ($createDraft !== null && ($createDraft['form_type'] ?? '') !== 'life') ? $createDraft : null;
        $lifeDraft     = ($createDraft !== null && ($createDraft['form_type'] ?? '') === 'life') ? $createDraft : null;
        $nonlifeForm   = self::renderNonlifeCreateForm(
            $nonlifeDraft, $customers, $staffUsers, $renewalCases,
            $createUrl, $createCsrf,
            $listUrl
        );
        $lifeForm      = self::renderLifeCreateForm(
            $lifeDraft, $customers, $staffUsers,
            $createUrl, $createCsrf,
            $listUrl
        );

        $rowsHtml    = self::renderRows($rows, $criteria, $listState, $detailBaseUrl, $deleteUrl, $deleteCsrf, $listUrl);
        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $importReturnTo = ListViewHelper::buildUrl($listUrl, ['open_modal' => 'import']);

        $filterFormHtml = self::renderSearchForm($criteria, $listState, $staffUsers, $performanceMonths, $listUrl);

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table">'
            . '<colgroup>'
            . '<col style="width:96px;">'
            . '<col style="width:auto;">'
            . '<col style="width:120px;">'
            . '<col style="width:80px;">'
            . '<col style="width:140px;">'
            . '<col style="width:100px;">'
            . '<col style="width:40px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('計上日', 'performance_date', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('契約者名', 'customer_name', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('担当者名', 'staff_id', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('業務区分', 'source_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('保険料', 'premium_amount', $listUrl, $criteria, $listState) . '</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('成績管理一覧',
                '<button class="btn btn-secondary" type="button" data-open-dialog="sales-create-nonlife-dialog">成績（損保）を追加</button>'
                . '<button class="btn" type="button" data-open-dialog="sales-create-life-dialog">成績（生保）を追加</button>'
            )
            . $noticeHtml
            . $filterFormHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="sales-create-nonlife-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>成績を登録（損保）</h2></div>'
            . $nonlifeForm
            . '</dialog>'
            . '<dialog id="sales-create-life-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>成績を登録（生保）</h2></div>'
            . $lifeForm
            . '</dialog>'
            . '<dialog id="sales-import-dialog" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>CSV取込</h2></div>'
            . '<p class="muted">成績CSVを取り込みます。取込結果とエラー内容もここで確認できます。</p>'
            . self::renderImportResult($importBatch, $importRows)
            . self::renderImportForm($importUrl, $importCsrf, $importReturnTo)
            . '</dialog>'
            . '<script>'
            . '(function(){const dialogs=document.querySelectorAll("dialog[id]");dialogs.forEach((dlg)=>{const id=dlg.id;const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const r=dlg.getBoundingClientRect();const inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});});const initial=' . ($activeDialogId === '' ? '""' : '"' . Layout::escape($activeDialogId) . '"') . ';if(initial!==""){const dlg=document.getElementById(initial);if(dlg&&typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}}})();'
            . '(function(){var dlg=document.getElementById("sales-create-nonlife-dialog");if(!dlg){return;}var renewalText=dlg.querySelector("input[data-role=\"renewal-case-text\"]");var renewalId=dlg.querySelector("input[name=\"renewal_case_id\"]");var renewalDlId=renewalText?renewalText.getAttribute("list"):null;var renewalDl=renewalDlId?document.getElementById(renewalDlId):null;var ptSec=dlg.querySelector("[data-section=\"perf-type-section\"]");var custText=dlg.querySelector("input[data-role=\"customer-text\"]");var custId=dlg.querySelector("input[name=\"customer_id\"]");var contrId=dlg.querySelector("input[name=\"contract_id\"]");var fillNodes={policy_no:dlg.querySelector("input[name=\"policy_no\"]"),product_type:dlg.querySelector("input[name=\"product_type\"]"),insurance_category:dlg.querySelector("input[name=\"insurance_category\"]"),policy_start_date:dlg.querySelector("input[name=\"policy_start_date\"]"),staff_id:dlg.querySelector("select[name=\"staff_id\"]"),premium_amount:dlg.querySelector("input[name=\"premium_amount\"]")};var applyRenewal=function(){if(!renewalText||!renewalDl){return;}var val=renewalText.value;var opts=renewalDl.querySelectorAll("option");var matchedOpt=null;for(var i=0;i<opts.length;i++){if(opts[i].value===val){matchedOpt=opts[i];break;}}if(renewalId){renewalId.value=matchedOpt?matchedOpt.getAttribute("data-id")||"":"";}
var hasVal=matchedOpt!==null;if(ptSec){ptSec.style.display=hasVal?"none":"";}if(!hasVal){return;}var f=function(t,a){var v=matchedOpt.getAttribute(a)||"";if(t&&v!==""){t.value=v;}};f(fillNodes.policy_no,"data-policy-no");f(fillNodes.product_type,"data-product-type");f(fillNodes.insurance_category,"data-insurance-category");f(fillNodes.policy_start_date,"data-policy-start-date");f(fillNodes.staff_id,"data-assigned-staff-id");var pp=matchedOpt.getAttribute("data-prev-premium-amount")||"";if(fillNodes.premium_amount&&pp!==""&&pp!=="0"){fillNodes.premium_amount.value=pp;}var cid=matchedOpt.getAttribute("data-customer-id")||"";var cname=matchedOpt.getAttribute("data-customer-name")||"";if(custId&&cid!==""){custId.value=cid;}if(custText&&cname!==""){custText.value=cname;}if(contrId){contrId.value=matchedOpt.getAttribute("data-contract-id")||"";}};var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};if(renewalText){renewalText.addEventListener("change",applyRenewal);renewalText.addEventListener("input",applyRenewal);}if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}})();'
            . '(function(){var dlg=document.getElementById("sales-create-life-dialog");if(!dlg){return;}var custText=dlg.querySelector("input[data-role=\"customer-text\"]");var custId=dlg.querySelector("input[name=\"customer_id\"]");var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};var appDate=dlg.querySelector("input[name=\"application_date\"]");var perfDate=dlg.querySelector("input[data-role=\"perf-date-mirror\"]");var mirror=function(){if(appDate&&perfDate){perfDate.value=appDate.value;}};if(appDate){appDate.addEventListener("change",mirror);appDate.addEventListener("input",mirror);mirror();}if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}})();'
            . '</script>'
            . '<dialog id="dlg-delete-sales-confirm" class="modal-dialog">'
            . '<div class="modal-head"><h2>削除の確認</h2>'
            . '<button type="button" class="modal-close" id="dlg-delete-sales-close">×</button>'
            . '</div>'
            . '<p id="dlg-delete-sales-msg" style="margin:16px 0;"></p>'
            . '<div class="dialog-actions">'
            . '<button type="button" id="dlg-delete-sales-ok" class="btn btn-danger">削除する</button>'
            . '<button type="button" id="dlg-delete-sales-cancel" class="btn btn-ghost">キャンセル</button>'
            . '</div>'
            . '</dialog>'
            . '<script>(function(){var dlg=document.getElementById("dlg-delete-sales-confirm");if(!dlg||typeof dlg.showModal!=="function"){return;}var msg=document.getElementById("dlg-delete-sales-msg");var pendingId=null;document.querySelectorAll("[data-delete-form]").forEach(function(btn){btn.addEventListener("click",function(){pendingId=btn.getAttribute("data-delete-form");var label=btn.getAttribute("data-delete-label")||"この件";if(msg){msg.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";}if(!dlg.open){dlg.showModal();}});});function closeDlg(){if(dlg.open){dlg.close();}pendingId=null;}document.getElementById("dlg-delete-sales-ok").addEventListener("click",function(){if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}closeDlg();});document.getElementById("dlg-delete-sales-cancel").addEventListener("click",closeDlg);document.getElementById("dlg-delete-sales-close").addEventListener("click",closeDlg);})();</script>';

        return Layout::render('成績管理一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, string> $performanceMonths
     */
    private static function renderSearchForm(array $criteria, array $listState, array $staffUsers, array $performanceMonths, string $listUrl): string
    {
        $selectedFY       = (string) ($criteria['performance_fiscal_year'] ?? '');
        $selectedMonthNum = (string) ($criteria['performance_month_num'] ?? '');
        $customerName     = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $staffUserId      = (string) ($criteria['staff_id'] ?? '');
        $productType      = Layout::escape((string) ($criteria['product_type'] ?? ''));

        // 年度セレクト: DB成績月から年度を逆算（月>=4: その年、月<=3: 前年）
        $fiscalYears = [];
        foreach ($performanceMonths as $ym) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
                continue;
            }
            $fy = (int) $m[2] >= 4 ? (int) $m[1] : (int) $m[1] - 1;
            $fyStr = (string) $fy;
            if (!in_array($fyStr, $fiscalYears, true)) {
                $fiscalYears[] = $fyStr;
            }
        }
        if ($selectedFY !== '' && !in_array($selectedFY, $fiscalYears, true)) {
            $fiscalYears[] = $selectedFY;
        }
        rsort($fiscalYears);
        $fyOptions = '<option value="">すべて</option>';
        foreach ($fiscalYears as $fy) {
            $sel       = $fy === $selectedFY ? ' selected' : '';
            $fyOptions .= '<option value="' . Layout::escape($fy) . '"' . $sel . '>' . Layout::escape($fy . '年度') . '</option>';
        }

        // 月セレクト: 年度の流れに合わせ 4〜3月 の順で表示
        $monthOptions = '<option value="">すべて</option>';
        foreach ([4,5,6,7,8,9,10,11,12,1,2,3] as $mn) {
            $sel           = (string) $mn === $selectedMonthNum ? ' selected' : '';
            $monthOptions .= '<option value="' . $mn . '"' . $sel . '>' . $mn . '月</option>';
        }

        $staffOptions = '<option value="">すべて</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $sel          = $staffUserId !== '' && (int) $staffUserId === $id ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        return '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">年度</span><select name="performance_fiscal_year" class="compact-input w-sm">' . $fyOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">月</span><select name="performance_month_num" class="compact-input w-sm">' . $monthOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">契約者名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">担当者</span><select name="staff_id" class="compact-input w-md">' . $staffOptions . '</select></div>'
            . '</div>'
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">種目</span><input type="text" name="product_type" class="compact-input w-md" value="' . $productType . '"></div>'
            . '<div class="search-actions">'
            . '<button class="btn btn-small" type="submit">検索</button>'
            . '<a class="btn btn-small btn-secondary" href="' . Layout::escape($listUrl) . '">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $renewalCases
     */
    private static function renderNonlifeCreateForm(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $renewalCases,
        string $actionUrl,
        string $csrfToken,
        string $returnTo
    ): string {
        $customerId       = (int) ($record['customer_id'] ?? 0);
        $contractId       = (int) ($record['contract_id'] ?? 0);
        $renewalCaseId    = (int) ($record['renewal_case_id'] ?? 0);
        $performanceDate  = Layout::escape((string) ($record['performance_date'] ?? date('Y-m-d')));
        $performanceType  = (string) ($record['performance_type'] ?? '');
        $policyNo         = Layout::escape((string) ($record['policy_no'] ?? ''));
        $policyStartDate  = Layout::escape((string) ($record['policy_start_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($record['insurance_category'] ?? ''));
        $productType      = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount    = Layout::escape((string) ($record['premium_amount'] ?? ''));
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo        = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth  = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $staffUserId      = (int) ($record['staff_id'] ?? 0);
        $remark           = Layout::escape((string) ($record['remark'] ?? ''));

        // 成績区分ラジオ（損保新規時のみ表示）
        $ptSecAttr = $renewalCaseId > 0 ? ' style="display:none;"' : '';
        $ptRadios  = '';
        foreach (['new' => '新規契約', 'addition' => '追加引受', 'change' => '変更', 'cancel_deduction' => '解約・等級訂正'] as $v => $l) {
            $checked   = $v === $performanceType || ($performanceType === '' && $v === 'new') ? ' checked' : '';
            $ptRadios .= '<label class="radio-inline"><input type="radio" name="performance_type_detail" value="' . $v . '"' . $checked . '> ' . $l . '</label>';
        }

        // 顧客 datalist
        $dlId          = 'sales-create-nonlife-customers-list';
        $customerDl    = '<datalist id="' . $dlId . '">';
        $customerNameVal = '';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            if ($cid === $customerId) {
                $customerNameVal = $cname;
            }
            $customerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $customerDl .= '</datalist>';
        $customerNameEsc = Layout::escape($customerNameVal);

        // 担当者選択肢
        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $selected     = $id === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        // 満期案件 datalist（コンボボックス用）
        $renewalDlId = 'renewal-cases-nonlife-dl';
        $renewalCaseText = '';
        $renewalDatalist = '';
        foreach ($renewalCases as $row) {
            $id             = (int) ($row['id'] ?? 0);
            $policyNoText   = (string) ($row['policy_no'] ?? '');
            $maturityDate   = (string) ($row['maturity_date'] ?? '');
            $customerNameRc = (string) ($row['customer_name'] ?? '');
            $displayText    = $customerNameRc . ' / ' . $maturityDate . ' / ' . $policyNoText;
            if ($id === $renewalCaseId && $renewalCaseId > 0) {
                $renewalCaseText = $displayText;
            }
            $renewalDatalist .= '<option value="' . Layout::escape($displayText) . '"'
                . ' data-id="' . $id . '"'
                . ' data-contract-id="' . (int) ($row['contract_id'] ?? 0) . '"'
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . ' data-customer-name="' . Layout::escape($customerNameRc) . '"'
                . ' data-assigned-staff-id="' . (int) ($row['assigned_staff_id'] ?? 0) . '"'
                . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                . ' data-prev-premium-amount="' . (int) ($row['prev_premium_amount'] ?? 0) . '"'
                . '>';
        }

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<input type="hidden" name="form_type" value="non_life">'
            . '<input type="hidden" name="contract_id" value="' . $contractId . '">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . $customerDl
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">満期案件</h3>'
            . '<p class="muted" style="margin:0 0 .5em;">満期案件を選択すると<strong>損保継続</strong>として登録されます。選択しない場合は<strong>損保新規</strong>として扱います。</p>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field modal-form-wide"><span>満期案件</span>'
            . '<datalist id="' . $renewalDlId . '">' . $renewalDatalist . '</datalist>'
            . '<input type="text" list="' . $renewalDlId . '" data-role="renewal-case-text" autocomplete="off" value="' . Layout::escape($renewalCaseText) . '" placeholder="未設定（損保新規）">'
            . '<input type="hidden" name="renewal_case_id" value="' . ($renewalCaseId > 0 ? $renewalCaseId : '') . '">'
            . '</label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>成績計上日 <strong class="required-mark">*</strong></span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $dlId . '" data-role="customer-text" autocomplete="off" value="' . $customerNameEsc . '" placeholder="顧客名で検索" required></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" value="' . $insuranceCategory . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>始期日</span><input type="date" name="policy_start_date" value="' . $policyStartDate . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section"' . $ptSecAttr . ' data-section="perf-type-section">'
            . '<h3 class="modal-form-title">成績区分 <strong class="required-mark">*</strong></h3>'
            . '<div class="radio-group" style="margin-top:6px;">' . $ptRadios . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">金額・精算情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '<label class="list-filter-field"><span>精算月</span><input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '<label class="list-filter-field"><span>分割回数</span><input type="number" min="1" max="255" step="1" name="installment_count" value="' . $installmentCount . '"></label>'
            . '<label class="list-filter-field"><span>領収証番号</span><input type="text" name="receipt_no" value="' . $receiptNo . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-create-nonlife-dialog">キャンセル</button>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     */
    private static function renderLifeCreateForm(
        ?array $record,
        array $customers,
        array $staffUsers,
        string $actionUrl,
        string $csrfToken,
        string $returnTo
    ): string {
        $customerId       = (int) ($record['customer_id'] ?? 0);
        $applicationDate  = Layout::escape((string) ($record['application_date'] ?? date('Y-m-d')));
        $policyNo         = Layout::escape((string) ($record['policy_no'] ?? ''));
        $productType      = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount    = Layout::escape((string) ($record['premium_amount'] ?? ''));
        $staffUserId      = (int) ($record['staff_id'] ?? 0);
        $remark           = Layout::escape((string) ($record['remark'] ?? ''));

        // 顧客 datalist
        $dlId          = 'sales-create-life-customers-list';
        $customerDl    = '<datalist id="' . $dlId . '">';
        $customerNameVal = '';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            if ($cid === $customerId) {
                $customerNameVal = $cname;
            }
            $customerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $customerDl .= '</datalist>';
        $customerNameEsc = Layout::escape($customerNameVal);

        // 担当者選択肢
        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $selected     = $id === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<input type="hidden" name="form_type" value="life">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . '<input type="hidden" name="performance_date" value="' . $applicationDate . '" data-role="perf-date-mirror">'
            . $customerDl
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>申込日 <strong class="required-mark">*</strong></span><input type="date" name="application_date" value="' . $applicationDate . '" required></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $dlId . '" data-role="customer-text" autocomplete="off" value="' . $customerNameEsc . '" placeholder="顧客名で検索" required></label>'
            . '<label class="list-filter-field"><span>保険商品 <strong class="required-mark">*</strong></span><input type="text" name="product_type" value="' . $productType . '" required></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">金額情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-create-life-dialog">キャンセル</button>'
            . '</div>'
            . '</form>';
    }

    private static function renderImportForm(string $importUrl, string $importCsrf, string $returnTo): string
    {
        return ''
            . '<form method="post" action="' . Layout::escape($importUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($importCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<label class="list-filter-field"><span>CSVファイル</span><input type="file" name="csv_file" accept=".csv,text/csv" required></label>'
            . '<details class="details-panel modal-help"><summary>必須ヘッダを確認</summary><p class="muted">receipt_no, policy_no, customer_name, maturity_date, performance_date, performance_type, insurance_category, product_type, premium_amount, settlement_month, remark</p></details>'
            . '<div class="actions">'
            . '<button class="btn" type="submit">CSV取込を実行</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-import-dialog">閉じる</button>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<string, mixed>|null $importBatch
     * @param array<int, array<string, mixed>> $importRows
     */
    private static function renderImportResult(?array $importBatch, array $importRows): string
    {
        if ($importBatch === null) {
            return '';
        }

        $rowsHtml = '';
        foreach ($importRows as $row) {
            $rowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['row_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['row_status'] ?? '')) . '</td>'
                . '<td><span class="truncate" title="' . Layout::escape((string) ($row['policy_no'] ?? '')) . '">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</span></td>'
                . '<td><span class="truncate" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</span></td>'
                . '<td><span class="truncate" title="' . Layout::escape((string) ($row['error_message'] ?? '')) . '">' . Layout::escape((string) ($row['error_message'] ?? '')) . '</span></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5">取込結果はありません。</td></tr>';
        }

        return ''
            . '<div class="modal-result">'
            . '<p>ファイル名: ' . Layout::escape((string) ($importBatch['file_name'] ?? '')) . '</p>'
            . '<p>状態: ' . Layout::escape((string) ($importBatch['import_status'] ?? ''))
            . ' / 総行数: ' . Layout::escape((string) ($importBatch['total_row_count'] ?? '0'))
            . ' / 有効: ' . Layout::escape((string) ($importBatch['valid_row_count'] ?? '0'))
            . ' / 新規: ' . Layout::escape((string) ($importBatch['insert_count'] ?? '0'))
            . ' / 更新: ' . Layout::escape((string) ($importBatch['update_count'] ?? '0'))
            . ' / エラー: ' . Layout::escape((string) ($importBatch['error_count'] ?? '0'))
            . '</p>'
            . '</div>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card">'
            . '<thead><tr><th>行</th><th>判定</th><th>証券番号</th><th>契約者名</th><th>エラー</th></tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderRows(
        array $rows,
        array $criteria,
        array $listState,
        string $detailBaseUrl,
        string $deleteUrl,
        string $deleteCsrf,
        string $listUrl
    ): string {
        $rowsHtml = '';

        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $params       = LP::queryParams($criteria, $listState);
            $detailUrl    = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $params)));
            $customerName = (string) ($row['customer_name'] ?? '');
            $premium      = (int) ($row['premium_amount'] ?? 0);
            $premiumFormatted = number_format($premium) . '円';
            $premiumHtml  = $premium < 0
                ? '<span style="color:var(--text-danger);">' . Layout::escape($premiumFormatted) . '</span>'
                : Layout::escape($premiumFormatted);

            $deleteFormId = 'form-del-sales-' . $id;
            $deleteLabel  = Layout::escape($customerName !== '' ? $customerName : ('ID: ' . $id));
            $deleteForm   = '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:inline;">'
                . LP::routeInput($deleteUrl)
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
                . LP::hiddenInputs($params)
                . '<button type="button" class="btn-icon-delete" title="削除"'
                . ' data-delete-form="' . $deleteFormId . '"'
                . ' data-delete-label="' . $deleteLabel . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                . '</button>'
                . '</form>';

            $rowsHtml .= '<tr>'
                . '<td data-label="計上日" style="white-space:nowrap;"><a class="text-link" href="' . $detailUrl . '">' . Layout::escape((string) ($row['performance_date'] ?? '')) . '</a></td>'
                . '<td class="cell-ellipsis" data-label="契約者名" title="' . Layout::escape($customerName) . '">' . Layout::escape($customerName) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当者名" title="' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '">' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '</td>'
                . '<td data-label="業務区分">' . Layout::escape(self::businessLabel((string) ($row['source_type'] ?? ''), (string) ($row['performance_type'] ?? ''))) . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td data-label="保険料" style="white-space:nowrap;text-align:right;">' . $premiumHtml . '</td>'
                . '<td>' . $deleteForm . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当データはありません。</td></tr>';
        }

        return $rowsHtml;
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 計上日';
        }

        $label = match ($sort) {
            'performance_date' => '計上日',
            'customer_name'    => '契約者名',
            'staff_id'         => '担当者名',
            'source_type'      => '業務区分',
            'product_type'     => '種目',
            'premium_amount'   => '保険料',
            default            => '計上日',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    private static function performanceTypeLabel(string $type): string
    {
        return match ($type) {
            'new'              => '新規',
            'renewal'          => '更改',
            'addition'         => '追加',
            'change'           => '異動',
            'cancel_deduction' => '解約控除',
            default            => $type,
        };
    }

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'non_life' => '損保',
            'life'     => '生保',
            default    => '',
        };
    }

    private static function businessLabel(string $sourceType, string $performanceType): string
    {
        return match ($sourceType) {
            'life'     => '生保',
            'non_life' => '損保',
            default    => '',
        };
    }
}
