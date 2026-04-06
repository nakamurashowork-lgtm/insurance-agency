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
                . '<div><div class="page-title">実績詳細</div></div>'
                . '<div class="actions"><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></div>'
                . '</div>'
                . '<div class="card"><div class="error">対象実績が見つかりません。</div></div>';
            return Layout::render('実績詳細', $content, $layoutOptions);
        }

        $id = (int) ($record['id'] ?? 0);
        $pdRaw = (string) ($record['performance_date'] ?? '');
        $performanceDate = Layout::escape($pdRaw);
        $performanceType = (string) ($record['performance_type'] ?? 'new');
        $sourceType = (string) ($record['source_type'] ?? '');
        $customerNameRaw = (string) ($record['customer_name'] ?? '');
        $customerName = Layout::escape($customerNameRaw);
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

        // Page title: 実績詳細 — 2026/4/1 上田 勇
        $titleDate = '';
        if ($pdRaw !== '') {
            $ts = strtotime($pdRaw);
            if ($ts !== false) {
                $titleDate = date('Y', $ts) . '/' . (int) date('n', $ts) . '/' . (int) date('j', $ts);
            }
        }
        $pageHeadTitle = '実績詳細'
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
            $rowContractId = (int) ($row['contract_id'] ?? 0);
            $selected = $rid === $selectedRenewalCaseId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $maturityDate = (string) ($row['maturity_date'] ?? '');
            $label = $maturityDate !== '' ? $policyNoText . ' / ' . $maturityDate : $policyNoText;
            if ($rid === $selectedRenewalCaseId && $label !== '') {
                $linkedRenewalInfo = $label;
            }
            if ($linkedContractRenewalCaseId === 0 && $rowContractId === $selectedContractId && $rid > 0) {
                $linkedContractRenewalCaseId = $rid;
            }
            $renewalOptions .= '<option value="' . $rid . '"' . $selected
                . ' data-contract-id="' . $rowContractId . '"'
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . '>' . Layout::escape($label) . '</option>';
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

        $sourceOptions = '<option value="">未選択</option>';
        foreach (['non_life' => '損保', 'life' => '生保'] as $value => $label) {
            $selected = $sourceType === $value ? ' selected' : '';
            $sourceOptions .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

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
            . '<div class="kv"><span class="kv-key">実績区分</span><span class="kv-val">' . Layout::escape(self::performanceTypeLabel($performanceType)) . '</span></div>'
            . '<div class="kv"><span class="kv-key">実績計上日</span><span class="kv-val">' . $performanceDate . '</span></div>'
            . '<div class="kv"><span class="kv-key">契約者名</span><span class="kv-val">' . $customerNameHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">担当者</span><span class="kv-val">' . ($staffUserName === '' ? '<span class="muted">未設定</span>' : $staffUserName) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険会社名</span><span class="kv-val">' . ($insurerName === '' ? '<span class="muted">未設定</span>' : $insurerName) . '</span></div>'
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
        $dialogHtml = '<dialog id="sales-edit-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '" id="sales-detail-edit-form">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . '<h2 class="modal-title">実績を編集</h2>'
            . '<div class="form-row"><label class="form-label">業務区分</label><select class="form-select" name="source_type" data-role="source-type">' . $sourceOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">実績区分 <strong class="required-mark">*</strong></label><select class="form-select" name="performance_type" required>' . $typeOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">実績計上日 <strong class="required-mark">*</strong></label><input class="form-input" type="date" name="performance_date" value="' . $performanceDate . '" required></div>'
            . '<div class="form-row"><label class="form-label">契約者名 <strong class="required-mark">*</strong></label><select class="form-select" name="customer_id" required>' . $customerOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">担当者</label><select class="form-select" name="staff_id">' . $staffOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">契約</label><select class="form-select" name="contract_id">' . $contractOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">満期案件</label><select class="form-select" name="renewal_case_id">' . $renewalOptions . '</select></div>'
            . '<div class="form-row"><label class="form-label">保険会社名</label><input class="form-input" type="text" name="insurer_name" data-contract-fill="insurer_name" value="' . $insurerName . '"></div>'
            . '<div class="form-row"><label class="form-label">証券番号</label><input class="form-input" type="text" name="policy_no" data-contract-fill="policy_no" value="' . $policyNo . '"></div>'
            . '<div class="form-row"><label class="form-label">始期日</label><input class="form-input" type="date" name="policy_start_date" data-contract-fill="policy_start_date" value="' . $policyStartDate . '"></div>'
            . '<div class="form-row" data-role="application-date-field"><label class="form-label">申込日</label><input class="form-input" type="date" name="application_date" value="' . $applicationDate . '"></div>'
            . '<div class="form-row"><label class="form-label">保険種類</label><input class="form-input" type="text" name="insurance_category" data-contract-fill="insurance_category" value="' . $insuranceCategory . '"></div>'
            . '<div class="form-row"><label class="form-label">種目</label><input class="form-input" type="text" name="product_type" data-contract-fill="product_type" value="' . $productType . '"></div>'
            . '<div class="form-row"><label class="form-label">保険料 <strong class="required-mark">*</strong></label><input class="form-input" type="number" step="1" name="premium_amount" value="' . $premiumAmountRaw . '" required></div>'
            . '<div class="form-row"><label class="form-label">精算月</label><input class="form-input" type="month" name="settlement_month" value="' . $settlementMonth . '"></div>'
            . '<div class="form-row"><label class="form-label">分割回数</label><input class="form-input" type="number" min="1" max="255" step="1" name="installment_count" value="' . $installmentCount . '"></div>'
            . '<div class="form-row"><label class="form-label">領収証番号</label><input class="form-input" type="text" name="receipt_no" value="' . $receiptNo . '"></div>'
            . '<div class="form-row"><label class="form-label">備考</label><textarea class="form-input" name="remark" rows="4" style="width:100%;">' . $remark . '</textarea></div>'
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
        $js = '<script>'
            . '(function(){'
            // Dialog backdrop click to close
            . 'const dlg=document.getElementById("sales-edit-dialog");'
            . 'if(!dlg){return;}'
            . 'dlg.addEventListener("click",function(e){const r=dlg.getBoundingClientRect();const inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});'
            // Form and field references (inside dialog)
            . 'const form=document.getElementById("sales-detail-edit-form");'
            . 'if(!form){return;}'
            . 'const customer=form.querySelector("select[name=\"customer_id\"]");'
            . 'const contract=form.querySelector("select[name=\"contract_id\"]");'
            . 'const renewal=form.querySelector("select[name=\"renewal_case_id\"]");'
            . 'const source=form.querySelector("select[name=\"source_type\"]");'
            . 'const appField=form.querySelector("[data-role=\"application-date-field\"]");'
            . 'const fillTargets={'
            . 'insurer_name:form.querySelector("input[name=\"insurer_name\"]"),'
            . 'policy_no:form.querySelector("input[name=\"policy_no\"]"),'
            . 'policy_start_date:form.querySelector("input[name=\"policy_start_date\"]"),'
            . 'insurance_category:form.querySelector("input[name=\"insurance_category\"]"),'
            . 'product_type:form.querySelector("input[name=\"product_type\"]")'
            . '};'
            // Helpers
            . 'const toggleLifeField=()=>{if(!source||!appField){return;}appField.style.display=source.value==="life"?"":"none";};'
            . 'const filterContracts=()=>{if(!customer||!contract){return;}const cid=customer.value;Array.from(contract.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const owner=opt.getAttribute("data-customer-id")||"";opt.hidden=(cid!==""&&owner!==cid);});if(contract.selectedOptions[0]&&contract.selectedOptions[0].hidden){contract.value="";}};'
            . 'const filterRenewals=()=>{if(!renewal){return;}const cid=customer?customer.value:"";const contractId=contract?contract.value:"";Array.from(renewal.options).forEach((opt,idx)=>{if(idx===0){opt.hidden=false;return;}const ownerContract=opt.getAttribute("data-contract-id")||"";const ownerCustomer=opt.getAttribute("data-customer-id")||"";let visible=true;if(contractId!==""){visible=ownerContract===contractId;}else if(cid!==""){visible=ownerCustomer===cid;}opt.hidden=!visible;});if(renewal.selectedOptions[0]&&renewal.selectedOptions[0].hidden){renewal.value="";}};'
            . 'const autofillFromContract=()=>{if(!contract){return;}const selected=contract.selectedOptions[0];if(!selected){return;}const map={insurer_name:selected.getAttribute("data-insurer-name")||"",policy_no:selected.getAttribute("data-policy-no")||"",policy_start_date:selected.getAttribute("data-policy-start-date")||"",insurance_category:selected.getAttribute("data-insurance-category")||"",product_type:selected.getAttribute("data-product-type")||""};Object.keys(map).forEach((key)=>{const target=fillTargets[key];if(!target){return;}if((target.value||"").trim()!==""){return;}target.value=map[key];});};'
            // Event listeners
            . 'if(customer){customer.addEventListener("change",()=>{filterContracts();filterRenewals();});}'
            . 'if(contract){contract.addEventListener("change",()=>{filterRenewals();autofillFromContract();});}'
            . 'if(source){source.addEventListener("change",toggleLifeField);}'
            // Open button: run state before showing modal
            . 'const openBtn=document.getElementById("sales-edit-open-btn");'
            . 'if(openBtn){openBtn.addEventListener("click",function(){toggleLifeField();filterContracts();filterRenewals();if(typeof dlg.showModal==="function"){dlg.showModal();}});}'
            . '})();'
            . '</script>';

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
            . '<div class="detail-section-title">実績情報</div>'
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

        return Layout::render('実績詳細', $content, $layoutOptions);
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
