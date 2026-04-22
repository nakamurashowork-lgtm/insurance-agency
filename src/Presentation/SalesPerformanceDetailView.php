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
                . '<div class="actions"><button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button></div>'
                . '</div>'
                . '<div class="card"><div class="error">対象成績が見つかりません。</div></div>';
            return Layout::render('成績詳細', $content, $layoutOptions);
        }

        $id = (int) ($record['id'] ?? 0);
        $pdRaw = (string) ($record['performance_date'] ?? '');
        $performanceDate = Layout::escape($pdRaw);
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

        // Build option lists
        $contractOptions = '<option value="">未設定</option>';
        foreach ($contracts as $row) {
            $cid = (int) ($row['id'] ?? 0);
            $selected = $cid === $selectedContractId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $contractOptions .= '<option value="' . $cid . '"' . $selected
                . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                . '>' . Layout::escape($policyNoText) . '</option>';
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

        // form_type: fixed from existing record, cannot be changed in edit form
        $formType = $sourceType === 'life' ? 'life' : 'non_life';

        // Customer datalist for edit form
        $editDlId       = 'sales-edit-customers-list';
        $editCustomerDl = '<datalist id="' . $editDlId . '">';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            $editCustomerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $editCustomerDl .= '</datalist>';

        // 編集カード冒頭に表示する読み取り専用 KV（業務区分のみ）
        $readonlyKvHtml = ''
            . '<div class="kv"><span class="kv-key">業務区分</span><span class="kv-val">' . $sourceBadgeHtml . '</span></div>';

        // ─── インライン編集フォーム ──────────────────────────────
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

        $editFormHtml = '<form method="post" action="' . Layout::escape($updateUrl) . '" id="sales-detail-edit-form">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . $editCustomerDl
            . '<section class="modal-form-section">'
            . $readonlyKvHtml
            . '</section>'
            . $editFormInner
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="dialog-actions">'
            . '<button type="submit" class="btn btn-primary">保存する</button>'
            . '</div>'
            . '</form>';

        // ─── JS ──────────────────────────────────────────────────
        $jsBody = 'var form=document.getElementById("sales-detail-edit-form");if(!form){return;}'
            . 'var custText=form.querySelector("input[data-role=\"customer-text\"]");var custId=form.querySelector("input[name=\"customer_id\"]");'
            . 'var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};'
            . 'if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}';

        if ($formType === 'life') {
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
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . '</div>'
            . '</div>'
            . '<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">'
            . '<div class="card" style="flex:0 0 auto;width:min(520px,100%);">'
            . '<div class="detail-section-title">成績情報</div>'
            . $editFormHtml
            . '</div>'
            . '<details class="card details-panel details-compact" open style="flex:1 1 360px;min-width:0;">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . $auditsHtml
            . '</div>'
            . '</details>'
            . '</div>'
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

                $diffHtml .= '<div style="display:flex;align-items:baseline;gap:6px;margin:3px 0;font-size:13px;">'
                    . '<span style="min-width:90px;color:var(--text-hint);">' . $label . '</span>'
                    . '<span style="color:var(--text-muted-cool);text-decoration:line-through;">' . $before . '</span>'
                    . '<span style="color:var(--text-muted-cool);">→</span>'
                    . '<span style="font-weight:600;">' . $after . '</span>'
                    . '</div>';
            }

            $html .= '<div style="border-left:3px solid var(--border-light);padding:8px 10px 8px 12px;margin-bottom:10px;background:var(--bg-primary);border-radius:0 4px 4px 0;">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape((string) ($item['changed_at'] ?? '')) . '</span>'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape((string) ($item['changed_by'] ?? '')) . '</span>'
                . '</div>'
                . $diffHtml
                . '</div>';
        }

        if ($html === '') {
            $html = '<div class="muted" style="font-size:13px;">変更履歴はありません。</div>';
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

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'non_life' => '損保',
            'life' => '生保',
            default => '',
        };
    }
}
