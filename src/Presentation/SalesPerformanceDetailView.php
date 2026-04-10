<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class SalesPerformanceDetailView
{
    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<int, array<string, mixed>> $audits
     * @param array<int, string> $allowedTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $contracts,
        array $renewalCases,
        array $audits,
        array $allowedTypes,
        string $listUrl,
        string $detailUrl,
        string $updateUrl,
        string $deleteUrl,
        string $updateCsrf,
        string $deleteCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $fatalError,
        string $customerDetailBaseUrl,
        string $renewalDetailBaseUrl,
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

        if (!is_array($record)) {
            $content = $errorHtml
                . '<div class="page-header">'
                . '<div><div class="page-title">成績詳細</div></div>'
                . '<div class="actions"><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></div>'
                . '</div>'
                . '<div class="card"><div class="error">対象成績が見つかりません。</div></div>';
            return Layout::render('成績詳細', $content, $layoutOptions);
        }

        $id = (int) ($record['id'] ?? 0);
        $pdRaw = (string) ($record['performance_date'] ?? '');
        $performanceDate = Layout::escape($pdRaw);
        $performanceType = (string) ($record['performance_type'] ?? 'new');
        $sourceType = (string) ($record['source_type'] ?? '');
        $customerNameRaw = (string) ($record['customer_name'] ?? '');
        $customerName = Layout::escape($customerNameRaw);
        $staffUserName = Layout::escape((string) ($record['staff_user_name'] ?? ''));
        $policyNoRaw = (string) ($record['policy_no'] ?? '');
        if ($policyNoRaw === '') {
            $policyNoRaw = (string) ($record['contract_policy_no'] ?? '');
        }
        $policyNo = Layout::escape($policyNoRaw);
        $policyStartDateRaw = (string) ($record['policy_start_date'] ?? '');
        if ($policyStartDateRaw === '') {
            $policyStartDateRaw = (string) ($record['contract_policy_start_date'] ?? '');
        }
        $policyStartDate = Layout::escape($policyStartDateRaw);
        $applicationDate = Layout::escape((string) ($record['application_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($record['insurance_category'] ?? ''));
        $productType = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumInt = (int) ($record['premium_amount'] ?? 0);
        $premiumAmountRaw = Layout::escape((string) $premiumInt);
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $remark = Layout::escape((string) ($record['remark'] ?? ''));

        $customerId = (int) ($record['customer_id'] ?? 0);
        $selectedContractId = (int) ($record['contract_id'] ?? 0);
        $selectedRenewalCaseId = (int) ($record['renewal_case_id'] ?? 0);
        $staffUserId = (int) ($record['staff_id'] ?? 0);

        // Page title: 成績詳細 — 2026/4/1 上田 勇
        $titleDate = '';
        if ($pdRaw !== '') {
            $ts = strtotime($pdRaw);
            if ($ts !== false) {
                $titleDate = date('Y', $ts) . '/' . (int) date('n', $ts) . '/' . (int) date('j', $ts);
            }
        }
        $pageHeadTitle = '成績詳細'
            . ($titleDate !== '' ? ' — ' . $titleDate : '')
            . ($customerNameRaw !== '' ? ' ' . $customerNameRaw : '');

        // Premium display
        if ($premiumInt < 0) {
            $premiumFormatted = '<span style="color:var(--text-danger);">▲' . number_format(abs($premiumInt)) . '円</span>';
        } else {
            $premiumFormatted = number_format($premiumInt) . '円';
        }

        // Source type badge
        $sourceTypeLabel = self::sourceTypeLabel($sourceType);
        $sourceBadgeClass = $sourceType === 'non_life' ? 'badge-info' : ($sourceType === 'life' ? 'badge-warn' : '');
        $sourceBadgeHtml = $sourceTypeLabel !== ''
            ? '<span class="badge ' . $sourceBadgeClass . '">' . Layout::escape($sourceTypeLabel) . '</span>'
            : '<span class="muted">未設定</span>';

        // Build option lists + resolve linked display values
        $linkedContractPolicyNo = '';
        $linkedContractRenewalCaseId = 0;
        $contractOptions = '<option value="">未設定</option>';
        foreach ($contracts as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $selected = $cid === $selectedContractId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            if ($cid === $selectedContractId && $policyNoText !== '') {
                $linkedContractPolicyNo = $policyNoText;
            }
            $contractOptions .= '<option value="' . $cid . '"' . $selected
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                . '>' . Layout::escape($policyNoText) . '</option>';
        }

        $linkedRenewalInfo = '';
        $renewalDlId = 'renewal-cases-edit-dl';
        $selectedRenewalCaseText = '';
        $renewalDatalist = '';
        foreach ($renewalCases as $row) {
            $rid = (int) ($row['id'] ?? 0);
            $rowContractId = (int) ($row['contract_id'] ?? 0);
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $maturityDate = (string) ($row['maturity_date'] ?? '');
            $customerNameRc = (string) ($row['customer_name'] ?? '');
            $displayText = $customerNameRc . ' / ' . $maturityDate . ' / ' . $policyNoText;
            if ($rid === $selectedRenewalCaseId && $rid > 0) {
                $selectedRenewalCaseText = $displayText;
                $linkedRenewalInfo = $displayText;
            }
            if ($linkedContractRenewalCaseId === 0 && $rowContractId === $selectedContractId && $rid > 0) {
                $linkedContractRenewalCaseId = $rid;
            }
            $renewalDatalist .= '<option value="' . Layout::escape($displayText) . '"'
                . ' data-id="' . $rid . '"'
                . ' data-contract-id="' . $rowContractId . '"'
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

        $customerOptions = '<option value="">選択してください</option>';
        foreach ($customers as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $selected = $cid === $customerId ? ' selected' : '';
            $label = (string) ($row['customer_name'] ?? '');
            $customerOptions .= '<option value="' . $cid . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $row) {
            $uid = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['staff_name'] ?? $row['name'] ?? ''));
            if ($uid <= 0 || $name === '') {
                continue;
            }
            $selected = $uid === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        $typeOptions = '';
        foreach ($allowedTypes as $type) {
            $selected = $type === $performanceType ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($type) . '"' . $selected . '>' . Layout::escape(self::performanceTypeLabel($type)) . '</option>';
        }

        // form_type: fixed from existing record, cannot be changed in edit form
        $formType = $sourceType === 'life' ? 'life' : 'non_life';

        // 損保：成績区分ラジオ（満期案件選択時は非表示）
        $editPtSecAttr = ($formType === 'non_life' && $selectedRenewalCaseId > 0) ? ' style="display:none;"' : '';
        $editPtRadios  = '';
        foreach (['new' => '新規契約', 'addition' => '追加引受', 'change' => '変更', 'cancel_deduction' => '解約・等級訂正'] as $v => $l) {
            $checked       = $v === $performanceType || ($performanceType === 'renewal' && $v === 'new') ? ' checked' : '';
            $editPtRadios .= '<label class="radio-inline"><input type="radio" name="performance_type_detail" value="' . $v . '"' . $checked . '> ' . $l . '</label>';
        }

        // Customer datalist for edit form
        $editDlId       = 'sales-edit-customers-list';
        $editCustomerDl = '<datalist id="' . $editDlId . '">';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            $editCustomerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $editCustomerDl .= '</datalist>';

        // KV: 契約者名 link
        $customerNameHtml = $customerName !== '' ? $customerName : '<span class="muted">未設定</span>';
        if ($customerId > 0 && $customerName !== '') {
            $customerNameHtml = '<a class="kv-link" href="' . Layout::escape($customerDetailBaseUrl . '&id=' . $customerId . '&return_to=' . urlencode('sales/detail?id=' . $id)) . '">' . $customerName . '</a>';
        }

        // KV: 関連契約 link (via renewal case matching the contract)
        $linkedContractHtml = '<span class="muted">未設定</span>';
        if ($linkedContractPolicyNo !== '') {
            if ($linkedContractRenewalCaseId > 0) {
                $linkedContractHtml = '<a class="kv-link" href="' . Layout::escape($renewalDetailBaseUrl) . '&amp;id=' . $linkedContractRenewalCaseId . '">' . Layout::escape($linkedContractPolicyNo) . '</a>';
            } else {
                $linkedContractHtml = Layout::escape($linkedContractPolicyNo);
            }
        }

        // KV: 関連満期案件 link
        $linkedRenewalHtml = '<span class="muted">未設定</span>';
        if ($selectedRenewalCaseId > 0 && $linkedRenewalInfo !== '') {
            $linkedRenewalHtml = '<a class="kv-link" href="' . Layout::escape($renewalDetailBaseUrl) . '&amp;id=' . $selectedRenewalCaseId . '">' . Layout::escape($linkedRenewalInfo) . '</a>';
        }

        // ─── KV display ──────────────────────────────────────────
        $kvHtml = ''
            . '<div class="kv"><span class="kv-key">業務区分</span><span class="kv-val">' . $sourceBadgeHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">成績区分</span><span class="kv-val">' . Layout::escape(self::performanceTypeLabel($performanceType)) . '</span></div>'
            . '<div class="kv"><span class="kv-key">成績計上日</span><span class="kv-val">' . $performanceDate . '</span></div>'
            . '<div class="kv"><span class="kv-key">契約者名</span><span class="kv-val">' . $customerNameHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">担当者</span><span class="kv-val">' . ($staffUserName === '' ? '<span class="muted">未設定</span>' : $staffUserName) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険種類</span><span class="kv-val">' . ($insuranceCategory === '' ? '<span class="muted">未設定</span>' : $insuranceCategory) . '</span></div>'
            . '<div class="kv"><span class="kv-key">種目</span><span class="kv-val">' . ($productType === '' ? '<span class="muted">未設定</span>' : $productType) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険料</span><span class="kv-val">' . $premiumFormatted . '</span></div>';

        if ($sourceType !== 'life') {
            $kvHtml .= '<div class="kv"><span class="kv-key">証券番号</span><span class="kv-val">' . ($policyNo === '' ? '<span class="muted">未設定</span>' : $policyNo) . '</span></div>'
                . '<div class="kv"><span class="kv-key">始期日</span><span class="kv-val">' . ($policyStartDate === '' ? '<span class="muted">未設定</span>' : $policyStartDate) . '</span></div>'
                . '<div class="kv"><span class="kv-key">領収証番号</span><span class="kv-val">' . ($receiptNo === '' ? '<span class="muted">未設定</span>' : $receiptNo) . '</span></div>'
                . '<div class="kv"><span class="kv-key">精算月</span><span class="kv-val">' . ($settlementMonth === '' ? '<span class="muted">未設定</span>' : $settlementMonth) . '</span></div>';
            if ($installmentCount !== '') {
                $kvHtml .= '<div class="kv"><span class="kv-key">分割回数</span><span class="kv-val">' . $installmentCount . '</span></div>';
            }
        }

        if ($sourceType === 'life') {
            $kvHtml .= '<div class="kv"><span class="kv-key">申込日</span><span class="kv-val">' . ($applicationDate === '' ? '<span class="muted">未設定</span>' : $applicationDate) . '</span></div>'
                . '<div class="kv"><span class="kv-key">始期日</span><span class="kv-val">' . ($policyStartDate === '' ? '<span class="muted">未設定</span>' : $policyStartDate) . '</span></div>';
        }

        $kvHtml .= '<div class="kv"><span class="kv-key">備考</span><span class="kv-val">' . ($remark === '' ? '<span class="muted">未設定</span>' : $remark) . '</span></div>'
            . '<div class="kv"><span class="kv-key">関連契約</span><span class="kv-val">' . $linkedContractHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">関連満期案件</span><span class="kv-val">' . $linkedRenewalHtml . '</span></div>';

        // ─── Dialog: edit form ────────────────────────────────────
        $editFormInner = $formType === 'life'
            ? (''
                . '<input type="hidden" name="form_type" value="life">'
                . '<input type="hidden" name="performance_date" value="' . $applicationDate . '" data-role="perf-date-mirror">'
                . '<section class="modal-form-section">'
                . '<h3 class="modal-form-title">基本情報</h3>'
                . '<div class="list-filter-grid modal-form-grid">'
                . '<label class="list-filter-field"><span>申込日 <strong class="required-mark">*</strong></span><input type="date" name="application_date" value="' . $applicationDate . '" required></label>'
                . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
                . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $editDlId . '" data-role="customer-text" autocomplete="off" value="' . $customerName . '" placeholder="顧客名で検索" required></label>'
                . '<label class="list-filter-field"><span>保険商品 <strong class="required-mark">*</strong></span><input type="text" name="product_type" value="' . $productType . '" required></label>'
                . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
                . '</div>'
                . '</section>'
                . '<section class="modal-form-section">'
                . '<h3 class="modal-form-title">金額情報</h3>'
                . '<div class="list-filter-grid modal-form-grid">'
                . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" step="1" name="premium_amount" value="' . $premiumAmountRaw . '" required></label>'
                . '</div>'
                . '</section>'
            )
            : (''
                . '<input type="hidden" name="form_type" value="non_life">'
                . '<input type="hidden" name="contract_id" value="' . $selectedContractId . '">'
                . '<section class="modal-form-section">'
                . '<h3 class="modal-form-title">満期案件</h3>'
                . '<p class="muted" style="margin:0 0 .5em;">満期案件を選択すると<strong>損保継続</strong>として登録されます。選択しない場合は<strong>損保新規</strong>として扱います。</p>'
                . '<div class="list-filter-grid modal-form-grid">'
                . '<label class="list-filter-field modal-form-wide"><span>満期案件</span>'
                . '<datalist id="' . $renewalDlId . '">' . $renewalDatalist . '</datalist>'
                . '<input type="text" list="' . $renewalDlId . '" data-role="renewal-case-text" autocomplete="off" value="' . Layout::escape($selectedRenewalCaseText) . '" placeholder="未設定（損保新規）">'
                . '<input type="hidden" name="renewal_case_id" value="' . ($selectedRenewalCaseId > 0 ? $selectedRenewalCaseId : '') . '">'
                . '</label>'
                . '</div>'
                . '</section>'
                . '<section class="modal-form-section">'
                . '<h3 class="modal-form-title">基本情報</h3>'
                . '<div class="list-filter-grid modal-form-grid">'
                . '<label class="list-filter-field"><span>成績計上日 <strong class="required-mark">*</strong></span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
                . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
                . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $editDlId . '" data-role="customer-text" autocomplete="off" value="' . $customerName . '" placeholder="顧客名で検索" required></label>'
                . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
                . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" value="' . $insuranceCategory . '"></label>'
                . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
                . '<label class="list-filter-field"><span>始期日</span><input type="date" name="policy_start_date" value="' . $policyStartDate . '"></label>'
                . '</div>'
                . '</section>'
                . '<section class="modal-form-section"' . $editPtSecAttr . ' data-section="perf-type-section">'
                . '<h3 class="modal-form-title">成績区分 <strong class="required-mark">*</strong></h3>'
                . '<div class="radio-group" style="margin-top:6px;">' . $editPtRadios . '</div>'
                . '</section>'
                . '<section class="modal-form-section">'
                . '<h3 class="modal-form-title">金額・精算情報</h3>'
                . '<div class="list-filter-grid modal-form-grid">'
                . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" step="1" name="premium_amount" value="' . $premiumAmountRaw . '" required></label>'
                . '<label class="list-filter-field"><span>精算月</span><input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
                . '<label class="list-filter-field"><span>分割回数</span><input type="number" min="1" max="255" step="1" name="installment_count" value="' . $installmentCount . '"></label>'
                . '<label class="list-filter-field"><span>領収証番号</span><input type="text" name="receipt_no" value="' . $receiptNo . '"></label>'
                . '</div>'
                . '</section>'
            );

        $dialogHtml = '<dialog id="sales-edit-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '" id="sales-detail-edit-form">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . $editCustomerDl
            . '<div class="modal-head"><h2>成績を編集</h2></div>'
            . $editFormInner
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'sales-edit-dialog\').close()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">保存する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';

        // ─── Hidden delete form ───────────────────────────────────
        $deleteFormHtml = '<form id="sales-delete-form" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:none;">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
            . '</form>';

        // ─── JS ──────────────────────────────────────────────────
        $jsBody = 'var dlg=document.getElementById("sales-edit-dialog");if(!dlg){return;}'
            . 'dlg.addEventListener("click",function(e){var r=dlg.getBoundingClientRect();var inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});'
            . 'var form=document.getElementById("sales-detail-edit-form");if(!form){return;}'
            . 'var custText=form.querySelector("input[data-role=\"customer-text\"]");var custId=form.querySelector("input[name=\"customer_id\"]");'
            . 'var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};'
            . 'if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}'
            . 'var openBtn=document.getElementById("sales-edit-open-btn");'
            . 'if(openBtn){openBtn.addEventListener("click",function(){if(typeof dlg.showModal==="function"){dlg.showModal();}});}';

        if ($formType === 'non_life') {
            $jsBody .= 'var renewalText=form.querySelector("input[data-role=\"renewal-case-text\"]");'
                . 'var renewalId=form.querySelector("input[name=\"renewal_case_id\"]");'
                . 'var renewalDlId=renewalText?renewalText.getAttribute("list"):null;'
                . 'var renewalDl=renewalDlId?document.getElementById(renewalDlId):null;'
                . 'var ptSec=form.querySelector("[data-section=\"perf-type-section\"]");'
                . 'var contrId=form.querySelector("input[name=\"contract_id\"]");'
                . 'var fillNodes={policy_no:form.querySelector("input[name=\"policy_no\"]"),product_type:form.querySelector("input[name=\"product_type\"]"),insurance_category:form.querySelector("input[name=\"insurance_category\"]"),policy_start_date:form.querySelector("input[name=\"policy_start_date\"]"),staff_id:form.querySelector("select[name=\"staff_id\"]"),premium_amount:form.querySelector("input[name=\"premium_amount\"]")};'
                . 'var applyRenewal=function(){if(!renewalText||!renewalDl){return;}var val=renewalText.value;var opts=renewalDl.querySelectorAll("option");var matchedOpt=null;for(var i=0;i<opts.length;i++){if(opts[i].value===val){matchedOpt=opts[i];break;}}if(renewalId){renewalId.value=matchedOpt?matchedOpt.getAttribute("data-id")||"":"";}
var hasVal=matchedOpt!==null;if(ptSec){ptSec.style.display=hasVal?"none":"";}if(!hasVal){return;}var f=function(t,a){var v=matchedOpt.getAttribute(a)||"";if(t&&v!==""){t.value=v;}};f(fillNodes.policy_no,"data-policy-no");f(fillNodes.product_type,"data-product-type");f(fillNodes.insurance_category,"data-insurance-category");f(fillNodes.policy_start_date,"data-policy-start-date");f(fillNodes.staff_id,"data-assigned-staff-id");var pp=matchedOpt.getAttribute("data-prev-premium-amount")||"";if(fillNodes.premium_amount&&pp!==""&&pp!=="0"){fillNodes.premium_amount.value=pp;}var cid=matchedOpt.getAttribute("data-customer-id")||"";var cname=matchedOpt.getAttribute("data-customer-name")||"";if(custId&&cid!==""){custId.value=cid;}if(custText&&cname!==""){custText.value=cname;}if(contrId){contrId.value=matchedOpt.getAttribute("data-contract-id")||"";}};'
                . 'if(renewalText){renewalText.addEventListener("change",applyRenewal);renewalText.addEventListener("input",applyRenewal);}';
        } else {
            $jsBody .= 'var appDate=form.querySelector("input[name=\"application_date\"]");var perfDate=form.querySelector("input[data-role=\"perf-date-mirror\"]");'
                . 'var mirror=function(){if(appDate&&perfDate){perfDate.value=appDate.value;}};'
                . 'if(appDate){appDate.addEventListener("change",mirror);appDate.addEventListener("input",mirror);}';
        }

        $js = '<script>(function(){' . $jsBody . '})();</script>';

        // ─── 変更履歴タイムライン ─────────────────────────────────
        $auditItems = [];
        foreach ($audits as $row) {
            $changedAt = self::formatAuditDate((string) ($row['changed_at'] ?? ''));
            $changedBy = trim((string) ($row['changed_by_name'] ?? ''));
            if ($changedBy === '') {
                $changedBy = '不明なユーザー';
            }
            $details = $row['details'] ?? [];
            $diffItems = [];
            if (is_array($details)) {
                foreach ($details as $detailRow) {
                    if (!is_array($detailRow)) {
                        continue;
                    }
                    $fieldKey   = trim((string) ($detailRow['field_key'] ?? ''));
                    $fieldLabel = trim((string) ($detailRow['field_label'] ?? ''));
                    if ($fieldLabel === '') {
                        $fieldLabel = $fieldKey;
                    }
                    $beforeValue = trim((string) ($detailRow['before_value_text'] ?? ''));
                    $afterValue  = trim((string) ($detailRow['after_value_text']  ?? ''));
                    $beforeValue = self::translateAuditValue($fieldKey, $beforeValue);
                    $afterValue  = self::translateAuditValue($fieldKey, $afterValue);
                    if ($beforeValue === '') { $beforeValue = '未設定'; }
                    if ($afterValue  === '') { $afterValue  = '未設定'; }
                    $diffItems[] = [
                        'label'  => $fieldLabel,
                        'before' => $beforeValue,
                        'after'  => $afterValue,
                    ];
                }
            }
            $auditItems[] = [
                'changed_at' => $changedAt,
                'changed_by' => $changedBy,
                'diff_items' => $diffItems,
            ];
        }
        $auditsHtml = self::renderTimeline($auditItems);

        $content = $errorHtml . $successHtml
            . '<div class="page-header">'
            . '<div><div class="page-title">' . Layout::escape($pageHeadTitle) . '</div></div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '<button class="btn" id="sales-edit-open-btn" type="button">編集</button>'
            . '</div>'
            . '</div>'
            . '<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">'
            . '<div class="card" style="flex:0 0 auto;width:min(520px,100%);">'
            . '<div class="detail-section-title">成績情報</div>'
            . $kvHtml
            . '</div>'
            . '<details class="card details-panel details-compact" open style="flex:1 1 360px;min-width:0;">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . $auditsHtml
            . '</div>'
            . '</details>'
            . '</div>'
            . $deleteFormHtml
            . $dialogHtml
            . $js;

        return Layout::render('成績詳細', $content, $layoutOptions);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function renderTimeline(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $diffItems = $item['diff_items'] ?? [];
            if ($diffItems === []) {
                continue;
            }

            $diffHtml = '';
            foreach ($diffItems as $d) {
                $label  = Layout::escape((string) ($d['label']  ?? ''));
                $before = Layout::escape((string) ($d['before'] ?? ''));
                $after  = Layout::escape((string) ($d['after']  ?? ''));

                $diffHtml .= '<div style="display:flex;align-items:baseline;gap:6px;margin:3px 0;font-size:12.5px;">'
                    . '<span style="min-width:90px;color:var(--text-muted,#6b7280);">' . $label . '</span>'
                    . '<span style="color:var(--text-muted,#9ca3af);text-decoration:line-through;">' . $before . '</span>'
                    . '<span style="color:var(--text-muted,#9ca3af);">→</span>'
                    . '<span style="font-weight:600;">' . $after . '</span>'
                    . '</div>';
            }

            $html .= '<div style="border-left:3px solid var(--border-color,#d1d5db);padding:8px 10px 8px 12px;margin-bottom:10px;background:var(--bg-card,#fff);border-radius:0 4px 4px 0;">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                . '<span style="font-size:12px;color:var(--text-muted,#6b7280);">' . Layout::escape((string) ($item['changed_at'] ?? '')) . '</span>'
                . '<span style="font-size:12px;color:var(--text-muted,#6b7280);">' . Layout::escape((string) ($item['changed_by'] ?? '')) . '</span>'
                . '</div>'
                . $diffHtml
                . '</div>';
        }

        if ($html === '') {
            $html = '<div class="muted" style="font-size:12.5px;">変更履歴はありません。</div>';
        }

        return $html;
    }

    private static function formatAuditDate(string $changedAt): string
    {
        $value = trim($changedAt);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i', $ts) : $value;
    }

    private static function translateAuditValue(string $fieldKey, string $value): string
    {
        if ($value === '') {
            return '';
        }

        return match ($fieldKey) {
            'performance_type' => match ($value) {
                'new'             => '新規',
                'renewal'         => '更改',
                'addition'        => '追加',
                'change'          => '異動',
                'cancel_deduction' => '解約控除',
                default => $value,
            },
            'source_type' => match ($value) {
                'non_life' => '損保',
                'life'     => '生保',
                default    => $value,
            },
            default => $value,
        };
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
