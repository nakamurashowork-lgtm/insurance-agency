<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
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
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($fatalError) && $fatalError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($fatalError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $perPage = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort = (string) ($listState['sort'] ?? '');
        $direction = (string) ($listState['direction'] ?? 'asc');
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $errorHtml !== '';
        $pager = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $activeModal = in_array($openModal, ['create', 'import'], true) ? $openModal : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = 'create';
        }

        $searchForm = self::renderSearchForm($criteria, $listState, $staffUsers, $listUrl, $filterOpen);
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

        $rowsHtml = self::renderRows($rows, $criteria, $listState, $detailBaseUrl);
        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar = self::renderToolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = self::renderBottomPager($listUrl, $criteria, $listState, $pager);
        $importReturnTo = ListViewHelper::buildUrl($listUrl, ['open_modal' => 'import']);

        $content = ''
            . '<div class="list-page-frame">'
            . '<div class="list-page-header">'
            . '<h1 class="title">実績一覧</h1>'
            . '<div class="list-page-header-actions">'
            . '<button class="btn" type="button" data-open-dialog="sales-create-dialog">実績を追加</button>'
            . '</div>'
            . '</div>'
            . $errorHtml
            . $successHtml
            . $searchForm
            . '<div class="card">'
            . $topToolbar
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table">'
            . '<thead><tr>'
            . '<th>' . self::renderSortLink('計上日', 'performance_date', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('実績区分', 'performance_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('業務区分', 'source_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('契約者名', 'customer_name', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('担当者名', 'staff_user_id', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('保険会社名', 'insurer_name', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('証券番号', 'policy_no', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('種目', 'product_type', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('保険料', 'premium_amount', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . self::renderSortLink('精算月', 'settlement_month', $listUrl, $criteria, $listState) . '</th>'
            . '<th class="align-right">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $bottomPager
            . '</div>'
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

        return Layout::render('実績一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderSearchForm(array $criteria, array $listState, array $staffUsers, string $listUrl, bool $filterOpen): string
    {
        $dateFrom = Layout::escape((string) ($criteria['performance_date_from'] ?? ''));
        $dateTo = Layout::escape((string) ($criteria['performance_date_to'] ?? ''));
        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $staffUserId = (string) ($criteria['staff_user_id'] ?? '');
        $sourceType = (string) ($criteria['source_type'] ?? '');
        $performanceType = (string) ($criteria['performance_type'] ?? '');
        $insurerName = Layout::escape((string) ($criteria['insurer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $productType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $settlementMonth = Layout::escape((string) ($criteria['settlement_month'] ?? ''));

        $staffOptions = '<option value="">すべて</option>';
        foreach ($staffUsers as $user) {
            $id = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $selected = $staffUserId !== '' && (int) $staffUserId === $id ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        $sourceOptions = '';
        $sourceMap = [
            '' => 'すべて',
            'non_life' => '損保',
            'life' => '生保',
        ];
        foreach ($sourceMap as $value => $label) {
            $selected = $sourceType === $value ? ' selected' : '';
            $sourceOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $performanceOptions = '';
        $performanceMap = [
            '' => 'すべて',
            'new' => '新規',
            'renewal' => '更改',
            'addition' => '追加',
            'change' => '異動',
            'cancel_deduction' => '解約控除',
        ];
        foreach ($performanceMap as $value => $label) {
            $selected = $performanceType === $value ? ' selected' : '';
            $performanceOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        return ''
            . '<details class="card details-panel list-filter-card"' . ($filterOpen ? ' open' : '') . '>'
            . '<summary class="list-filter-toggle"><span class="list-filter-toggle-label is-closed">検索条件を開く</span><span class="list-filter-toggle-label is-open">検索条件を閉じる</span></summary>'
            . '<form method="get" action="' . Layout::escape(self::buildFormAction($listUrl)) . '">'
            . self::renderRouteInput($listUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . self::renderHiddenInputs(self::buildListQueryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>実績計上日From</span><input type="date" name="performance_date_from" value="' . $dateFrom . '"></label>'
            . '<label class="list-filter-field"><span>実績計上日To</span><input type="date" name="performance_date_to" value="' . $dateTo . '"></label>'
            . '<label class="list-filter-field"><span>契約者名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_user_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>業務区分</span><select name="source_type">' . $sourceOptions . '</select></label>'
            . '<label class="list-filter-field"><span>実績区分</span><select name="performance_type">' . $performanceOptions . '</select></label>'
            . '<label class="list-filter-field"><span>保険会社名</span><input type="text" name="insurer_name" value="' . $insurerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>精算月</span><input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button>'
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($listUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</details>';
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
        $customerId = (int) ($record['customer_id'] ?? 0);
        $contractId = (int) ($record['contract_id'] ?? 0);
        $renewalCaseId = (int) ($record['renewal_case_id'] ?? 0);
        $performanceDate = Layout::escape((string) ($record['performance_date'] ?? date('Y-m-d')));
        $performanceType = (string) ($record['performance_type'] ?? 'new');
        $sourceType = (string) ($record['source_type'] ?? '');
        $insurerName = Layout::escape((string) ($record['insurer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($record['policy_no'] ?? ''));
        $policyStartDate = Layout::escape((string) ($record['policy_start_date'] ?? ''));
        $applicationDate = Layout::escape((string) ($record['application_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($record['insurance_category'] ?? ''));
        $productType = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount = Layout::escape((string) ($record['premium_amount'] ?? '0'));
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $staffUserId = (int) ($record['staff_user_id'] ?? 0);
        $remark = Layout::escape((string) ($record['remark'] ?? ''));

        $customerOptions = '<option value="">選択してください</option>';
        foreach ($customers as $row) {
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $customerId ? ' selected' : '';
            $label = (string) ($row['customer_name'] ?? '');
            $customerOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $selected = $id === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        $contractOptions = '<option value="">未設定</option>';
        foreach ($contracts as $row) {
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $contractId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $customerName = (string) ($row['customer_name'] ?? '');
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
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $renewalCaseId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $maturityDate = (string) ($row['maturity_date'] ?? '');
            $renewalOptions .= '<option value="' . $id . '"' . $selected
                . ' data-contract-id="' . (int) ($row['contract_id'] ?? 0) . '"'
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . '>' . Layout::escape('案件#' . $id . ' / ' . $policyNoText . ' / ' . $maturityDate) . '</option>';
        }

        $typeOptions = '';
        foreach ($allowedTypes as $type) {
            $selected = $type === $performanceType ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($type) . '"' . $selected . '>' . Layout::escape(self::performanceTypeLabel($type)) . '</option>';
        }

        $sourceOptions = '<option value="">未選択</option>';
        foreach (['non_life' => '損保', 'life' => '生保'] as $value => $label) {
            $selected = $value === $sourceType ? ' selected' : '';
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
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_user_id">' . $staffOptions . '</select></label>'
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
            $id = (int) ($row['id'] ?? 0);
            $params = self::buildListQueryParams($criteria, $listState);
            $detailUrl = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $params)));

            $rowsHtml .= '<tr>'
                . '<td data-label="計上日">' . Layout::escape((string) ($row['performance_date'] ?? '')) . '</td>'
                . '<td data-label="実績区分">' . Layout::escape(self::performanceTypeLabel((string) ($row['performance_type'] ?? ''))) . '</td>'
                . '<td data-label="業務区分">' . Layout::escape(self::sourceTypeLabel((string) ($row['source_type'] ?? ''))) . '</td>'
                . '<td data-label="契約者名"><span class="truncate" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</span></td>'
                . '<td data-label="担当者名">' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '</td>'
                . '<td data-label="保険会社名"><span class="truncate" title="' . Layout::escape((string) (($row['insurer_name'] ?? '') !== '' ? $row['insurer_name'] : ($row['contract_insurer_name'] ?? ''))) . '">' . Layout::escape((string) (($row['insurer_name'] ?? '') !== '' ? $row['insurer_name'] : ($row['contract_insurer_name'] ?? ''))) . '</span></td>'
                . '<td data-label="証券番号"><span class="truncate" title="' . Layout::escape((string) (($row['policy_no_display'] ?? '') !== '' ? $row['policy_no_display'] : ($row['policy_no'] ?? ''))) . '">' . Layout::escape((string) (($row['policy_no_display'] ?? '') !== '' ? $row['policy_no_display'] : ($row['policy_no'] ?? ''))) . '</span></td>'
                . '<td data-label="種目"><span class="truncate" title="' . Layout::escape((string) ($row['product_type'] ?? '')) . '">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</span></td>'
                . '<td data-label="保険料">' . Layout::escape((string) ($row['premium_amount'] ?? '0')) . '</td>'
                . '<td data-label="精算月">' . Layout::escape((string) ($row['settlement_month'] ?? '')) . '</td>'
                . '<td data-label="操作" class="cell-action"><a class="text-link" href="' . $detailUrl . '">詳細を開く</a></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="11">該当データはありません。</td></tr>';
        }

        return $rowsHtml;
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @return array<string, string>
     */
    private static function buildListQueryParams(array $criteria, array $listState, bool $includePage = true, bool $includeSort = true): array
    {
        $params = $criteria;

        if ($includePage && (int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }

        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }

        if ($includeSort && ($listState['sort'] ?? '') !== '') {
            $params['sort'] = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return $params;
    }

    /**
     * @param array<string, string> $params
     */
    private static function renderHiddenInputs(array $params): string
    {
        $html = '';
        foreach ($params as $name => $value) {
            if (trim($value) === '') {
                continue;
            }

            $html .= '<input type="hidden" name="' . Layout::escape($name) . '" value="' . Layout::escape($value) . '">';
        }

        return $html;
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderToolbar(string $listUrl, array $criteria, array $listState, array $pager, int $totalCount, int $perPage, string $sortSummary): string
    {
        return '<div class="list-toolbar">'
            . '<div class="list-summary">'
            . '<p class="summary-count">' . Layout::escape(self::renderSummaryText($totalCount, $pager)) . '</p>'
            . '</div>'
            . '<div class="list-toolbar-actions">'
            . '<p class="muted list-sort-summary">' . Layout::escape($sortSummary) . '</p>'
            . self::renderPerPageForm($listUrl, $criteria, $listState, $perPage)
            . self::renderPager($listUrl, $criteria, $listState, $pager)
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderBottomPager(string $listUrl, array $criteria, array $listState, array $pager): string
    {
        $pagerHtml = self::renderPager($listUrl, $criteria, $listState, $pager);
        if ($pagerHtml === '') {
            return '';
        }

        return '<div class="list-toolbar list-toolbar-bottom"><div class="list-toolbar-actions">' . $pagerHtml . '</div></div>';
    }

    private static function renderSummaryText(int $totalCount, array $pager): string
    {
        if ($totalCount <= 0) {
            return '0件';
        }

        return $totalCount . '件中 ' . (int) ($pager['start'] ?? 0) . '-' . (int) ($pager['end'] ?? 0) . '件を表示';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPerPageForm(string $listUrl, array $criteria, array $listState, int $perPage): string
    {
        $optionsHtml = '';
        foreach ([10, 50, 100] as $option) {
            $selected = $perPage === $option ? ' selected' : '';
            $optionsHtml .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }

        return '<form method="get" action="' . Layout::escape(self::buildFormAction($listUrl)) . '" class="list-per-page-form">'
            . self::renderRouteInput($listUrl)
            . self::renderHiddenInputs(self::buildListQueryParams($criteria, $listState, false))
            . '<label class="list-select-inline"><span>表示件数</span><select name="per_page" onchange="this.form.submit()">' . $optionsHtml . '</select></label>'
            . '<noscript><button class="btn btn-ghost btn-small" type="submit">更新</button></noscript>'
            . '</form>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed> $pager
     */
    private static function renderPager(string $listUrl, array $criteria, array $listState, array $pager): string
    {
        if ((int) ($pager['totalPages'] ?? 0) <= 1) {
            return '';
        }

        $links = '';
        if (!empty($pager['hasPrevious'])) {
            $links .= self::renderPagerLink('前へ', (int) ($pager['previousPage'] ?? 1), $listUrl, $criteria, $listState);
        }

        foreach ((array) ($pager['pages'] ?? []) as $pageNumber) {
            $page = (int) $pageNumber;
            if ($page === (int) ($pager['currentPage'] ?? 1)) {
                $links .= '<span class="list-pager-link is-current">' . $page . '</span>';
                continue;
            }

            $links .= self::renderPagerLink((string) $page, $page, $listUrl, $criteria, $listState);
        }

        if (!empty($pager['hasNext'])) {
            $links .= self::renderPagerLink('次へ', (int) ($pager['nextPage'] ?? 1), $listUrl, $criteria, $listState);
        }

        return '<nav class="list-pager" aria-label="ページャー">' . $links . '</nav>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPagerLink(string $label, int $page, string $listUrl, array $criteria, array $listState): string
    {
        $params = self::buildListQueryParams($criteria, array_merge($listState, ['page' => (string) $page]));
        $url = Layout::escape(ListViewHelper::buildUrl($listUrl, $params));

        return '<a class="list-pager-link" href="' . $url . '">' . Layout::escape($label) . '</a>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderSortLink(string $label, string $column, string $listUrl, array $criteria, array $listState): string
    {
        $isCurrent = ($listState['sort'] ?? '') === $column;
        $nextDirection = $isCurrent && ($listState['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        $params = self::buildListQueryParams($criteria, array_merge($listState, ['sort' => $column, 'direction' => $nextDirection]));
        $url = Layout::escape(ListViewHelper::buildUrl($listUrl, $params));
        $indicator = '';
        if ($isCurrent) {
            $indicator = '<span class="list-sort-indicator">' . (($listState['direction'] ?? 'asc') === 'asc' ? '&#9650;' : '&#9660;') . '</span>';
        }

        return '<a class="list-sort-link' . ($isCurrent ? ' is-active' : '') . '" href="' . $url . '">' . Layout::escape($label) . $indicator . '</a>';
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 計上日';
        }

        $label = match ($sort) {
            'performance_date' => '計上日',
            'performance_type' => '実績区分',
            'source_type' => '業務区分',
            'customer_name' => '契約者名',
            'staff_user_id' => '担当者名',
            'insurer_name' => '保険会社名',
            'policy_no' => '証券番号',
            'product_type' => '種目',
            'premium_amount' => '保険料',
            'settlement_month' => '精算月',
            default => '計上日',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    private static function buildFormAction(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        return $path !== '' ? $path : '';
    }

    private static function renderRouteInput(string $url): string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $route = trim((string) ($params['route'] ?? ''));
        if ($route === '') {
            return '';
        }

        return '<input type="hidden" name="route" value="' . Layout::escape($route) . '">';
    }

    private static function performanceTypeLabel(string $type): string
    {
        return match ($type) {
            'new' => '新規',
            'renewal' => '更改',
            'addition' => '追加',
            'change' => '異動',
            'cancel_deduction' => '解約控除',
            default => $type,
        };
    }

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'non_life' => '損保',
            'life' => '生保',
            default => '',
        };
    }
}
