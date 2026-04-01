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
     * @param array<int, string> $allowedTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $contracts,
        array $renewalCases,
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
            $content = ''
                . '<div class="card">'
                . '<h1 class="title">実績詳細</h1>'
                . '<div class="error">対象実績が見つかりません。</div>'
                . '<div class="actions"><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></div>'
                . '</div>';

            return Layout::render('実績詳細', $content, $layoutOptions);
        }

        $id = (int) ($record['id'] ?? 0);
        $performanceDate = Layout::escape((string) ($record['performance_date'] ?? ''));
        $performanceType = (string) ($record['performance_type'] ?? 'new');
        $sourceType = (string) ($record['source_type'] ?? '');
        $customerName = Layout::escape((string) ($record['customer_name'] ?? ''));
        $staffUserName = Layout::escape((string) ($record['staff_user_name'] ?? ''));
        $insurerNameRaw = (string) ($record['insurer_name'] ?? '');
        if ($insurerNameRaw === '') {
            $insurerNameRaw = (string) ($record['contract_insurer_name'] ?? '');
        }
        $insurerName = Layout::escape($insurerNameRaw);
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
        $premiumAmount = Layout::escape((string) ($record['premium_amount'] ?? '0'));
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $remark = Layout::escape((string) ($record['remark'] ?? ''));

        $customerId = (int) ($record['customer_id'] ?? 0);
        $selectedContractId = (int) ($record['contract_id'] ?? 0);
        $selectedRenewalCaseId = (int) ($record['renewal_case_id'] ?? 0);
        $staffUserId = (int) ($record['staff_user_id'] ?? 0);

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
            $name = trim((string) ($row['name'] ?? ''));
            if ($uid <= 0 || $name === '') {
                continue;
            }

            $selected = $uid === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        $linkedContractPolicyNo = '';
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
                . ' data-insurer-name="' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '"'
                . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                . '>' . Layout::escape($policyNoText) . '</option>';
        }

        $linkedRenewalInfo = '';
        $renewalOptions = '<option value="">未設定</option>';
        foreach ($renewalCases as $row) {
            $rid = (int) ($row['id'] ?? 0);
            $selected = $rid === $selectedRenewalCaseId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $maturityDate = (string) ($row['maturity_date'] ?? '');
            $label = $maturityDate !== '' ? $policyNoText . ' / ' . $maturityDate : $policyNoText;
            if ($rid === $selectedRenewalCaseId && $label !== '') {
                $linkedRenewalInfo = $label;
            }
            $renewalOptions .= '<option value="' . $rid . '"' . $selected
                . ' data-contract-id="' . (int) ($row['contract_id'] ?? 0) . '"'
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . '>' . Layout::escape($label) . '</option>';
        }

        $typeOptions = '';
        foreach ($allowedTypes as $type) {
            $selected = $type === $performanceType ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($type) . '"' . $selected . '>' . Layout::escape(self::performanceTypeLabel($type)) . '</option>';
        }

        $sourceOptions = '<option value="">未選択</option>';
        foreach (['non_life' => '損保', 'life' => '生保'] as $value => $label) {
            $selected = $sourceType === $value ? ' selected' : '';
            $sourceOptions .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $content = ''
            . '<div class="card">'
            . '<div class="section-head">'
            . '<div><h1 class="title">実績詳細</h1></div>'
            . '<div class="actions"><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></div>'
            . '</div>'
            . $errorHtml
            . $successHtml
            . '<div class="split-columns">'
            . '<div class="card">'
            . '<h2>実績情報</h2>'
            . '<dl class="kv-list">'
            . '<dt>実績計上日</dt><dd>' . $performanceDate . '</dd>'
            . '<dt>実績区分</dt><dd>' . Layout::escape(self::performanceTypeLabel($performanceType)) . '</dd>'
            . '<dt>業務区分</dt><dd>' . Layout::escape(self::sourceTypeLabel($sourceType)) . '</dd>'
            . '<dt>契約者名</dt><dd>' . $customerName . '</dd>'
            . '<dt>担当者名</dt><dd>' . ($staffUserName === '' ? '<span class="muted">未設定</span>' : $staffUserName) . '</dd>'
            . '<dt>保険会社名</dt><dd>' . ($insurerName === '' ? '<span class="muted">未設定</span>' : $insurerName) . '</dd>'
            . '<dt>証券番号</dt><dd>' . $policyNo . '</dd>'
            . '<dt>始期日</dt><dd>' . $policyStartDate . '</dd>'
            . '<dt>申込日</dt><dd>' . ($applicationDate === '' ? '<span class="muted">未設定</span>' : $applicationDate) . '</dd>'
            . '<dt>保険種類</dt><dd>' . $insuranceCategory . '</dd>'
            . '<dt>種目</dt><dd>' . $productType . '</dd>'
            . '<dt>保険料</dt><dd>' . $premiumAmount . '</dd>'
            . '<dt>分割回数</dt><dd>' . ($installmentCount === '' ? '<span class="muted">未設定</span>' : $installmentCount) . '</dd>'
            . '<dt>領収証番号</dt><dd>' . $receiptNo . '</dd>'
            . '<dt>精算月</dt><dd>' . $settlementMonth . '</dd>'
            . '<dt>備考</dt><dd>' . ($remark === '' ? '<span class="muted">未設定</span>' : $remark) . '</dd>'
            . '<dt>関連契約</dt><dd>' . ($linkedContractPolicyNo !== '' ? Layout::escape($linkedContractPolicyNo) : '<span class="muted">未設定</span>') . '</dd>'
            . '<dt>満期案件</dt><dd>' . ($linkedRenewalInfo !== '' ? Layout::escape($linkedRenewalInfo) : '<span class="muted">未設定</span>') . '</dd>'
            . '</dl>'
            . '</div>'
            . '<div class="card">'
            . '<h2>編集</h2>'
            . '<form id="sales-detail-edit-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>実績計上日 <strong class="required-mark">*</strong></span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
            . '<label class="list-filter-field"><span>実績区分 <strong class="required-mark">*</strong></span><select name="performance_type" required>' . $typeOptions . '</select></label>'
            . '<label class="list-filter-field"><span>業務区分</span><select name="source_type" data-role="source-type">' . $sourceOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><select name="customer_id" required>' . $customerOptions . '</select></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_user_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約</span><select name="contract_id">' . $contractOptions . '</select></label>'
            . '<label class="list-filter-field"><span>満期案件</span><select name="renewal_case_id">' . $renewalOptions . '</select></label>'
            . '<label class="list-filter-field"><span>保険会社名</span><input type="text" name="insurer_name" data-contract-fill="insurer_name" value="' . $insurerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" data-contract-fill="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>始期日</span><input type="date" name="policy_start_date" data-contract-fill="policy_start_date" value="' . $policyStartDate . '"></label>'
            . '<label class="list-filter-field" data-role="application-date-field"><span>申込日</span><input type="date" name="application_date" value="' . $applicationDate . '"></label>'
            . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" data-contract-fill="insurance_category" value="' . $insuranceCategory . '"></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" data-contract-fill="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '<label class="list-filter-field"><span>精算月</span><input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '<label class="list-filter-field"><span>分割回数</span><input type="number" min="1" max="255" step="1" name="installment_count" value="' . $installmentCount . '"></label>'
            . '<label class="list-filter-field"><span>領収証番号</span><input type="text" name="receipt_no" value="' . $receiptNo . '"></label>'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="4" style="width:100%;">' . $remark . '</textarea></label>'
            . '</div>'
            . '<div class="actions">'
            . '<button class="btn" type="submit">更新する</button>'
            . '</div>'
            . '</form>'
            . '<hr style="border:none;border-top:1px solid #d9e2ec;margin:16px 0;">'
            . '<h3>削除</h3>'
            . '<form method="post" action="' . Layout::escape($deleteUrl) . '" onsubmit="return confirm(\'この実績を削除します。よろしいですか？\');">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
            . '<button class="btn btn-danger" type="submit">削除する</button>'
            . '</form>'
            . '</div>'
            . '</div>'
            . '<script>'
            . '(function(){const form=document.getElementById("sales-detail-edit-form");if(!form){return;}const customer=form.querySelector("select[name=\"customer_id\"]");const contract=form.querySelector("select[name=\"contract_id\"]");const renewal=form.querySelector("select[name=\"renewal_case_id\"]");const source=form.querySelector("select[name=\"source_type\"]");const appField=form.querySelector("[data-role=\"application-date-field\"]");const fillTargets={insurer_name:form.querySelector("input[name=\"insurer_name\"]"),policy_no:form.querySelector("input[name=\"policy_no\"]"),policy_start_date:form.querySelector("input[name=\"policy_start_date\"]"),insurance_category:form.querySelector("input[name=\"insurance_category\"]"),product_type:form.querySelector("input[name=\"product_type\"]")};const toggleLifeField=()=>{if(!source||!appField){return;}appField.style.display=source.value==="life"?"":"none";};const filterContracts=()=>{if(!customer||!contract){return;}const cid=customer.value;Array.from(contract.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const owner=opt.getAttribute("data-customer-id")||"";opt.hidden=(cid!==""&&owner!==cid);});if(contract.selectedOptions[0]&&contract.selectedOptions[0].hidden){contract.value="";}};const filterRenewals=()=>{if(!renewal){return;}const cid=customer?customer.value:"";const contractId=contract?contract.value:"";Array.from(renewal.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const ownerContract=opt.getAttribute("data-contract-id")||"";const ownerCustomer=opt.getAttribute("data-customer-id")||"";let visible=true;if(contractId!==""){visible=ownerContract===contractId;}else if(cid!==""){visible=ownerCustomer===cid;}opt.hidden=!visible;});if(renewal.selectedOptions[0]&&renewal.selectedOptions[0].hidden){renewal.value="";}};const autofillFromContract=()=>{if(!contract){return;}const selected=contract.selectedOptions[0];if(!selected){return;}const map={insurer_name:selected.getAttribute("data-insurer-name")||"",policy_no:selected.getAttribute("data-policy-no")||"",policy_start_date:selected.getAttribute("data-policy-start-date")||"",insurance_category:selected.getAttribute("data-insurance-category")||"",product_type:selected.getAttribute("data-product-type")||""};Object.keys(map).forEach((key)=>{const target=fillTargets[key];if(!target){return;}if((target.value||"").trim()!==""){return;}target.value=map[key];});};if(customer){customer.addEventListener("change",()=>{filterContracts();filterRenewals();});}if(contract){contract.addEventListener("change",()=>{filterRenewals();autofillFromContract();});}if(source){source.addEventListener("change",toggleLifeField);}toggleLifeField();filterContracts();filterRenewals();})();'
            . '</script>'
            . '</div>';

        return Layout::render('実績詳細', $content, $layoutOptions);
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
