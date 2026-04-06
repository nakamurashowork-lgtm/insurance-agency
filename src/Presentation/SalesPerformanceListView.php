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
        string $createCsrf,
        string $importCsrf,
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

        $activeModal = in_array($openModal, ['create', 'import'], true) ? $openModal : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = 'create';
        }

        $createForm = self::renderCreateForm(
            $createDraft,
            $customers,
            $staffUsers,
            $contracts,
            $renewalCases,
            $allowedTypes,
            $createUrl,
            $createCsrf,
            ListViewHelper::buildUrl($listUrl, ['open_modal' => 'create'])
        );

        $rowsHtml    = self::renderRows($rows, $criteria, $listState, $detailBaseUrl);
        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        $importReturnTo = ListViewHelper::buildUrl($listUrl, ['open_modal' => 'import']);

        $filterFormHtml = self::renderSearchForm($criteria, $listState, $staffUsers, $performanceMonths, $listUrl);

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table">'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('計上日', 'performance_date', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('契約者名', 'customer_name', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('担当者名', 'staff_id', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('業務区分', 'source_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('保険料', 'premium_amount', $listUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('実績管理一覧', '<button class="btn" type="button" data-open-dialog="sales-create-dialog">実績を追加</button>')
            . $noticeHtml
            . LP::filterCard($filterFormHtml, $filterOpen)
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="sales-create-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>実績を登録</h2></div>'
            . '<p class="muted">一覧で対象を探しながら、新規実績を登録します。</p>'
            . $createForm
            . '</dialog>'
            . '<dialog id="sales-import-dialog" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>CSV取込</h2></div>'
            . '<p class="muted">実績CSVを取り込みます。取込結果とエラー内容もここで確認できます。</p>'
            . self::renderImportResult($importBatch, $importRows)
            . self::renderImportForm($importUrl, $importCsrf, $importReturnTo)
            . '</dialog>'
            . '<script>'
            . '(function(){const dialogs=document.querySelectorAll("dialog[id]");dialogs.forEach((dlg)=>{const id=dlg.id;const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const r=dlg.getBoundingClientRect();const inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});});const initial=' . ($activeModal === '' ? '""' : '"sales-' . Layout::escape($activeModal) . '-dialog"') . ';if(initial!==""){const dlg=document.getElementById(initial);if(dlg&&typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}}})();'
            . '(function(){const dialog=document.getElementById("sales-create-dialog");if(!dialog){return;}const customer=document.querySelector("#sales-create-dialog select[name=\"customer_id\"]");const contract=document.querySelector("#sales-create-dialog select[name=\"contract_id\"]");const renewal=document.querySelector("#sales-create-dialog select[name=\"renewal_case_id\"]");const source=document.querySelector("#sales-create-dialog select[name=\"source_type\"]");const appField=document.querySelector("#sales-create-dialog [data-role=\"application-date-field\"]");const fillTargets={insurer_name:document.querySelector("#sales-create-dialog input[name=\"insurer_name\"]"),policy_no:document.querySelector("#sales-create-dialog input[name=\"policy_no\"]"),policy_start_date:document.querySelector("#sales-create-dialog input[name=\"policy_start_date\"]"),insurance_category:document.querySelector("#sales-create-dialog input[name=\"insurance_category\"]"),product_type:document.querySelector("#sales-create-dialog input[name=\"product_type\"]")};const toggleLifeField=()=>{if(!source||!appField){return;}appField.style.display=source.value==="life"?"":"none";};const filterContracts=()=>{if(!customer||!contract){return;}const cid=customer.value;Array.from(contract.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const owner=opt.getAttribute("data-customer-id")||"";opt.hidden=(cid!==""&&owner!==cid);});if(contract.selectedOptions[0]&&contract.selectedOptions[0].hidden){contract.value="";}};const filterRenewals=()=>{if(!renewal){return;}const cid=customer?customer.value:"";const contractId=contract?contract.value:"";Array.from(renewal.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const ownerContract=opt.getAttribute("data-contract-id")||"";const ownerCustomer=opt.getAttribute("data-customer-id")||"";let visible=true;if(contractId!==""){visible=ownerContract===contractId;}else if(cid!==""){visible=ownerCustomer===cid;}opt.hidden=!visible;});if(renewal.selectedOptions[0]&&renewal.selectedOptions[0].hidden){renewal.value="";}};const autofillFromContract=()=>{if(!contract){return;}const selected=contract.selectedOptions[0];if(!selected){return;}const map={insurer_name:selected.getAttribute("data-insurer-name")||"",policy_no:selected.getAttribute("data-policy-no")||"",policy_start_date:selected.getAttribute("data-policy-start-date")||"",insurance_category:selected.getAttribute("data-insurance-category")||"",product_type:selected.getAttribute("data-product-type")||""};Object.keys(map).forEach((key)=>{const target=fillTargets[key];if(!target){return;}if((target.value||"").trim()!==""){return;}target.value=map[key];});};if(customer){customer.addEventListener("change",()=>{filterContracts();filterRenewals();});}if(contract){contract.addEventListener("change",()=>{filterRenewals();autofillFromContract();});}if(source){source.addEventListener("change",toggleLifeField);}toggleLifeField();filterContracts();filterRenewals();})();'
            . '</script>';

        return Layout::render('実績管理一覧', $content, $layoutOptions);
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

        // 年度セレクト: DB実績月から年度を逆算（月>=4: その年、月<=3: 前年）
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

        return '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>年度</span><select name="performance_fiscal_year">' . $fyOptions . '</select></label>'
            . '<label class="list-filter-field"><span>月</span><select name="performance_month_num">' . $monthOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button>'
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($listUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
    * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<int, string> $allowedTypes
     */
    private static function renderCreateForm(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $contracts,
        array $renewalCases,
        array $allowedTypes,
        string $actionUrl,
        string $csrfToken,
        string $returnTo
    ): string {
        $customerId       = (int) ($record['customer_id'] ?? 0);
        $contractId       = (int) ($record['contract_id'] ?? 0);
        $renewalCaseId    = (int) ($record['renewal_case_id'] ?? 0);
        $performanceDate  = Layout::escape((string) ($record['performance_date'] ?? date('Y-m-d')));
        $performanceType  = (string) ($record['performance_type'] ?? 'new');
        $sourceType       = (string) ($record['source_type'] ?? '');
        $insurerName      = Layout::escape((string) ($record['insurer_name'] ?? ''));
        $policyNo         = Layout::escape((string) ($record['policy_no'] ?? ''));
        $policyStartDate  = Layout::escape((string) ($record['policy_start_date'] ?? ''));
        $applicationDate  = Layout::escape((string) ($record['application_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($record['insurance_category'] ?? ''));
        $productType      = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount    = Layout::escape((string) ($record['premium_amount'] ?? '0'));
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo        = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth  = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $staffUserId      = (int) ($record['staff_id'] ?? 0);
        $remark           = Layout::escape((string) ($record['remark'] ?? ''));

        $customerOptions = '<option value="">選択してください</option>';
        foreach ($customers as $row) {
            $id              = (int) ($row['id'] ?? 0);
            $selected        = $id === $customerId ? ' selected' : '';
            $label           = (string) ($row['customer_name'] ?? '');
            $customerOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

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

        $contractOptions = '<option value="">未設定</option>';
        foreach ($contracts as $row) {
            $id            = (int) ($row['id'] ?? 0);
            $selected      = $id === $contractId ? ' selected' : '';
            $policyNoText  = (string) ($row['policy_no'] ?? '');
            $customerName  = (string) ($row['customer_name'] ?? '');
            $contractOptions .= '<option value="' . $id . '"' . $selected
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . ' data-insurer-name="' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '"'
                . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                . '>' . Layout::escape($policyNoText . ' / ' . $customerName) . '</option>';
        }

        $renewalOptions = '<option value="">未設定</option>';
        foreach ($renewalCases as $row) {
            $id            = (int) ($row['id'] ?? 0);
            $selected      = $id === $renewalCaseId ? ' selected' : '';
            $policyNoText  = (string) ($row['policy_no'] ?? '');
            $maturityDate  = (string) ($row['maturity_date'] ?? '');
            $renewalOptions .= '<option value="' . $id . '"' . $selected
                . ' data-contract-id="' . (int) ($row['contract_id'] ?? 0) . '"'
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . '>' . Layout::escape('案件#' . $id . ' / ' . $policyNoText . ' / ' . $maturityDate) . '</option>';
        }

        $typeOptions = '';
        foreach ($allowedTypes as $type) {
            $selected    = $type === $performanceType ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($type) . '"' . $selected . '>' . Layout::escape(self::performanceTypeLabel($type)) . '</option>';
        }

        $sourceOptions = '<option value="">未選択</option>';
        foreach (['non_life' => '損保', 'life' => '生保'] as $value => $label) {
            $selected      = $value === $sourceType ? ' selected' : '';
            $sourceOptions .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>実績計上日 <strong class="required-mark">*</strong></span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
            . '<label class="list-filter-field"><span>実績区分 <strong class="required-mark">*</strong></span><select name="performance_type" required>' . $typeOptions . '</select></label>'
            . '<label class="list-filter-field"><span>業務区分</span><select name="source_type" data-role="source-type">' . $sourceOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><select name="customer_id" required>' . $customerOptions . '</select></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約</span><select name="contract_id">' . $contractOptions . '</select></label>'
            . '<label class="list-filter-field"><span>満期案件</span><select name="renewal_case_id">' . $renewalOptions . '</select></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">契約・保険情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>保険会社名</span><input type="text" name="insurer_name" data-contract-fill="insurer_name" value="' . $insurerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" data-contract-fill="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>始期日</span><input type="date" name="policy_start_date" data-contract-fill="policy_start_date" value="' . $policyStartDate . '"></label>'
            . '<label class="list-filter-field" data-role="application-date-field"><span>申込日</span><input type="date" name="application_date" value="' . $applicationDate . '"></label>'
            . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" data-contract-fill="insurance_category" value="' . $insuranceCategory . '"></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" data-contract-fill="product_type" value="' . $productType . '"></label>'
            . '</div>'
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
            . '<h3 class="modal-form-title">備考</h3>'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="5" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-create-dialog">キャンセル</button>'
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
    private static function renderRows(array $rows, array $criteria, array $listState, string $detailBaseUrl): string
    {
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
            $rowsHtml .= '<tr>'
                . '<td data-label="計上日"><a class="text-link" href="' . $detailUrl . '"><strong>' . Layout::escape((string) ($row['performance_date'] ?? '')) . '</strong></a></td>'
                . '<td data-label="契約者名"><span class="truncate" title="' . Layout::escape($customerName) . '">' . Layout::escape($customerName) . '</span></td>'
                . '<td data-label="担当者名">' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '</td>'
                . '<td data-label="業務区分">' . Layout::escape(self::sourceTypeLabel((string) ($row['source_type'] ?? ''))) . '</td>'
                . '<td data-label="種目"><span class="truncate" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</span></td>'
                . '<td data-label="保険料">' . $premiumHtml . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当データはありません。</td></tr>';
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
}
