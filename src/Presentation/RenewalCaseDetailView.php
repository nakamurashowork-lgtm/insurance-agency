<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class RenewalCaseDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $comments
     * @param array<int, array<string, mixed>> $audits
     * @param array<string, string> $listStateParams
     * @param array<string, string> $fieldErrors
     * @param array<string, mixed> $layoutOptions
     * @param array<int, array<string, mixed>> $renewalStatuses
     * @param array<int, array<string, mixed>> $procedureMethods
     * @param array<int, array<string, mixed>> $renewalMethods
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
        string $updateUrl,
        string $commentUrl,
        string $detailUrl,
        array $listStateParams,
        string $customerDetailBaseUrl,
        string $csrfToken,
        string $commentCsrfToken,
        ?string $errorMessage,
        ?string $successMessage,
        array $fieldErrors,
        array $layoutOptions,
        array $officeStaffList = [],
        array $renewalStatuses = [],
        array $procedureMethods = [],
        array $renewalMethods = [],
        string $linkCustomerUrl = '',
        string $linkCustomerCsrfToken = '',
        string $updateAssignedStaffUrl = '',
        string $updateAssignedStaffCsrfToken = '',
        array $salesStaffList = [],
        array $staffNameMap = []
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $successHtml = '';
        if (is_string($successMessage) && $successMessage !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($successMessage) . '</div>';
        }

        $currentStatus = (string) ($detail['case_status'] ?? '');
        // is_completed フラグを収集（期限表示の色判定・変更履歴表示に使用）
        $completedNames = [];
        $statusNameMap  = []; // 表示名=DB格納値。translateFieldValue の互換性のため name=>name を渡す
        $statusHtml = '';
        $currentInMaster = false;
        foreach ($renewalStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name === '') { continue; }
            $statusNameMap[$name] = $name;
            if ((int) ($sRow['is_completed'] ?? 0) === 1) {
                $completedNames[$name] = true;
            }
            $selected = $name === $currentStatus ? ' selected' : '';
            if ($name === $currentStatus) { $currentInMaster = true; }
            $statusHtml .= '<option value="' . Layout::escape($name) . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }
        // 既存値がマスタに無い（無効化・削除済み）場合も現値を選択できるよう補完
        if ($currentStatus !== '' && !$currentInMaster) {
            $statusHtml = '<option value="' . Layout::escape($currentStatus) . '" selected>' . Layout::escape($currentStatus) . '</option>' . $statusHtml;
        }
        $currentIsCompleted = isset($completedNames[$currentStatus]);

        $currentRenewalMethod = (string) ($detail['renewal_method'] ?? '');
        $rmActiveOptions = '';
        $rmCurrentOption = '';
        $rmFoundInMaster = false;

        foreach ($renewalMethods as $rmRow) {
            $rmLabel    = (string) ($rmRow['name'] ?? '');
            $rmActive   = (int) ($rmRow['is_active'] ?? 1);
            $isCurrentValue = $rmLabel === $currentRenewalMethod;

            if ($rmActive === 1) {
                $selected = $isCurrentValue ? ' selected' : '';
                $rmActiveOptions .= '<option value="' . Layout::escape($rmLabel) . '"' . $selected . '>' . Layout::escape($rmLabel) . '</option>';
                if ($isCurrentValue) {
                    $rmFoundInMaster = true;
                }
            } elseif ($isCurrentValue) {
                $rmCurrentOption = '<option value="' . Layout::escape($rmLabel) . '" selected>' . Layout::escape($rmLabel) . '（無効）</option>';
                $rmFoundInMaster = true;
            }
        }

        if (!$rmFoundInMaster && $currentRenewalMethod !== '') {
            $rmCurrentOption = '<option value="' . Layout::escape($currentRenewalMethod) . '" selected>' . Layout::escape($currentRenewalMethod) . '（不明）</option>';
        }

        $renewalMethodHtml = '<option value=""' . ($currentRenewalMethod === '' ? ' selected' : '') . '>未設定</option>'
            . $rmCurrentOption
            . $rmActiveOptions;

        $currentProcedureMethod = (string) ($detail['procedure_method'] ?? '');
        $pmActiveOptions = '';
        $pmCurrentOption = '';
        $foundInMaster   = false;

        foreach ($procedureMethods as $pmRow) {
            $pmLabel     = (string) ($pmRow['name'] ?? '');
            $pmActive    = (int) ($pmRow['is_active'] ?? 1);
            $isCurrentValue = $pmLabel === $currentProcedureMethod;

            if ($pmActive === 1) {
                $selected = $isCurrentValue ? ' selected' : '';
                $pmActiveOptions .= '<option value="' . Layout::escape($pmLabel) . '"' . $selected . '>' . Layout::escape($pmLabel) . '</option>';
                if ($isCurrentValue) {
                    $foundInMaster = true;
                }
            } elseif ($isCurrentValue) {
                $pmCurrentOption = '<option value="' . Layout::escape($pmLabel) . '" selected>' . Layout::escape($pmLabel) . '（無効）</option>';
                $foundInMaster   = true;
            }
        }

        if (!$foundInMaster && $currentProcedureMethod !== '') {
            $pmCurrentOption = '<option value="' . Layout::escape($currentProcedureMethod) . '" selected>' . Layout::escape($currentProcedureMethod) . '（不明）</option>';
        }

        $procedureMethodHtml = '<option value=""' . ($currentProcedureMethod === '' ? ' selected' : '') . '>未設定</option>'
            . $pmCurrentOption
            . $pmActiveOptions;

        $commentsHtml = '';
        foreach ($comments as $row) {
            $authorLabel = self::commentAuthorLabel($row);
            $postedAt = self::formatCommentDate((string) ($row['created_at'] ?? ''));
            $commentsHtml .= '<li class="comment-item">'
                . '<div class="comment-meta">'
                . '<span class="comment-meta-text">' . Layout::escape($authorLabel . ' ・ ' . $postedAt) . '</span>'
                . '</div>'
                . '<div class="comment-body">'
                . Layout::escape((string) ($row['comment_body'] ?? ''))
                . '</div>'
                . '</li>';
        }
        if ($commentsHtml === '') {
            $commentsHtml = '<li class="muted">0件</li>';
        }

        $auditItems = [];
        foreach ($audits as $row) {
            $changedAt = self::formatAuditDate((string) ($row['changed_at'] ?? ''));
            $changedBy = trim((string) ($row['changed_by_name'] ?? ''));
            if ($changedBy === '') {
                $changedBy = '不明なユーザー';
            }

            $details = $row['details'] ?? [];
            $diffItems = [];
            $eventCategory = 'other'; // 'status' | 'staff' | 'other'

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

                    $valueType = strtoupper(trim((string) ($detailRow['value_type'] ?? '')));
                    if ($valueType === 'JSON') {
                        $beforeRaw   = $detailRow['before_value_json'] ?? null;
                        $afterRaw    = $detailRow['after_value_json'] ?? null;
                        $beforeValue = $beforeRaw !== null ? (string) json_encode(json_decode((string) $beforeRaw), JSON_UNESCAPED_UNICODE) : '';
                        $afterValue  = $afterRaw  !== null ? (string) json_encode(json_decode((string) $afterRaw),  JSON_UNESCAPED_UNICODE) : '';
                    } else {
                        $beforeValue = trim((string) ($detailRow['before_value_text'] ?? ''));
                        $afterValue  = trim((string) ($detailRow['after_value_text'] ?? ''));
                    }

                    // 値を日本語ラベルに変換
                    $beforeValue = self::translateFieldValue($fieldKey, $beforeValue, $statusNameMap, $staffNameMap);
                    $afterValue  = self::translateFieldValue($fieldKey, $afterValue, $statusNameMap, $staffNameMap);

                    if ($beforeValue === '') { $beforeValue = '未設定'; }
                    if ($afterValue  === '') { $afterValue  = '未設定'; }

                    // カテゴリ判定
                    if ($fieldKey === 'case_status') {
                        $eventCategory = 'status';
                    } elseif (in_array($fieldKey, ['office_staff_id', 'assigned_staff_id'], true) && $eventCategory !== 'status') {
                        $eventCategory = 'staff';
                    }

                    $diffItems[] = [
                        'label'  => $fieldLabel,
                        'key'    => $fieldKey,
                        'before' => $beforeValue,
                        'after'  => $afterValue,
                    ];
                }
            }

            $auditItems[] = [
                'changed_at' => $changedAt,
                'changed_by' => $changedBy,
                'category'   => $eventCategory,
                'diff_items' => $diffItems,
            ];
        }

        // タイムラインHTML生成（全件・ステータスのみ・担当者のみ）
        $auditsHtml = self::renderTimeline($auditItems, 'all');

        $renewalCaseId  = (int) ($detail['renewal_case_id'] ?? 0);
        $contractId     = (int) ($detail['contract_id'] ?? 0);
        $linkedCustomerId = isset($detail['customer_id']) && $detail['customer_id'] !== null ? (int) $detail['customer_id'] : null;
        $isLinked       = $linkedCustomerId !== null && $linkedCustomerId > 0;
        $linkedName     = trim((string) ($detail['customer_name'] ?? ''));
        $sjnetName      = trim((string) ($detail['sjnet_customer_name'] ?? ''));
        $displayName    = $isLinked ? $linkedName : ($sjnetName !== '' ? $sjnetName : '（顧客未設定）');

        $customerUrl = $isLinked
            ? Layout::escape(
                $customerDetailBaseUrl
                . '&id=' . $linkedCustomerId
                . '&return_to=' . urlencode('renewal/detail?id=' . $renewalCaseId)
              )
            : '';
        $statusBadge = self::renderStatusBadge((string) ($detail['case_status'] ?? 'open'), $statusNameMap);
        $nextActionHtml = self::renderNextAction((string) ($detail['next_action_date'] ?? ''), $currentIsCompleted);
        $assignedUserId = trim((string) ($detail['assigned_staff_id'] ?? ''));
        $assignedUserName = trim((string) ($detail['assigned_user_name'] ?? ''));

        $premiumRaw = (string) ($detail['premium_amount'] ?? '');
        $premiumText = $premiumRaw === '' || !is_numeric($premiumRaw)
            ? '未設定'
            : number_format((int) $premiumRaw) . ' 円';

        $statusClass = isset($fieldErrors['case_status']) ? ' input-error' : '';
        $nextActionClass = isset($fieldErrors['next_action_date']) ? ' input-error' : '';
        $renewalMethodClass = isset($fieldErrors['renewal_method']) ? ' input-error' : '';
        $procedureMethodClass = isset($fieldErrors['procedure_method']) ? ' input-error' : '';
        $completedDateClass = isset($fieldErrors['completed_date']) ? ' input-error' : '';

        $today = date('Y-m-d');
        $earlyDeadline = trim((string) ($detail['early_renewal_deadline'] ?? ''));
        $earlyDeadlineHtml = $earlyDeadline !== ''
            ? ($earlyDeadline < $today ? '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($earlyDeadline) . '</span>' : Layout::escape($earlyDeadline))
            : '未設定';
        $currentOfficeStaffId = (string) ($detail['office_staff_id'] ?? '');
        $officeStaffOptions = '<option value="">未設定</option>';
        foreach ($officeStaffList as $s) {
            $sid = (string) ($s['id'] ?? '');
            $sel = $sid === $currentOfficeStaffId ? ' selected' : '';
            $officeStaffOptions .= '<option value="' . Layout::escape($sid) . '"' . $sel . '>' . Layout::escape((string) ($s['staff_name'] ?? '')) . '</option>';
        }

        $customerName = Layout::escape($displayName);
        $productType  = Layout::escape(trim((string) ($detail['product_type'] ?? '')));
        $maturityDate = (string) ($detail['maturity_date'] ?? '');
        $maturityDateStyle = $maturityDate !== '' && $maturityDate < $today ? ' style="color:var(--text-danger);font-weight:500;"' : '';

        $content = $errorHtml
            . $successHtml
            . '<div class="page-header">'
            . '<div>'
            . '<h1 class="title">' . $customerName . ($productType !== '' ? ' — ' . $productType : '') . '</h1>'
            . '<div class="meta-row">' . $statusBadge . '<span class="tag">満期日: ' . Layout::escape($maturityDate) . '</span><span class="tag">' . $nextActionHtml . '</span></div>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . ($linkCustomerUrl !== ''
                ? '<button type="button" class="btn btn-secondary" data-open-dialog="customer-link-modal">' . ($isLinked ? '顧客を変更' : '顧客を紐づける') . '</button>'
                : '')
            . ($updateAssignedStaffUrl !== ''
                ? '<button type="button" class="btn btn-secondary" data-open-dialog="assigned-staff-modal">営業担当を変更</button>'
                : '')
            . '<button class="btn btn-primary" type="submit" form="renewal-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . '<div class="two-col">'
            // ── 左カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title">契約情報</div>'
            . '<div class="kv"><span class="kv-key">証券番号</span><span class="kv-val">' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">満期日</span><span class="kv-val"' . $maturityDateStyle . '>' . Layout::escape($maturityDate) . '</span></div>'
            . '<div class="kv"><span class="kv-key">顧客名</span><span class="kv-val">' . ($isLinked ? '<a class="kv-link" href="' . $customerUrl . '">' . $customerName . '</a>' : $customerName . ' <span class="badge badge-warn" style="font-size:10px;margin-left:4px;">未紐づけ</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">種目</span><span class="kv-val">' . $productType . '</span></div>'
            . '<div class="kv"><span class="kv-key">早期更改締切</span><span class="kv-val">' . $earlyDeadlineHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">始期日</span><span class="kv-val">' . Layout::escape((string) ($detail['policy_start_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険料</span><span class="kv-val">' . Layout::escape($premiumText) . '</span></div>'
            . '<div class="kv"><span class="kv-key">営業担当</span><span class="kv-val">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : ($assignedUserId !== '' ? Layout::escape($assignedUserId) : '<span class="muted">未設定</span>')) . '</span></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">対応状況の更新</div>'
            . '<form id="renewal-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . self::renderHiddenInputs($listStateParams)
            . '<div class="form-row">'
            . '<div class="form-label">事務担当</div>'
            . '<select name="office_staff_id" class="form-select"'
            . Layout::fieldAria($fieldErrors, 'office_staff_id') . '>' . $officeStaffOptions . '</select>'
            . self::renderFieldError($fieldErrors, 'office_staff_id')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">対応状況 <strong class="required-mark">*</strong></div>'
            . '<select id="renewal-case-status" class="form-select' . $statusClass . '" name="case_status" required'
            . Layout::fieldAria($fieldErrors, 'case_status', true) . '>' . $statusHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'case_status')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">更改方法</div>'
            . '<select id="renewal-method-field" class="form-select' . $renewalMethodClass . '" name="renewal_method"'
            . Layout::fieldAria($fieldErrors, 'renewal_method') . '>' . $renewalMethodHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'renewal_method')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">手続き方法</div>'
            . '<select id="procedure-method-field" class="form-select' . $procedureMethodClass . '" name="procedure_method"'
            . Layout::fieldAria($fieldErrors, 'procedure_method') . '>' . $procedureMethodHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'procedure_method')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">完了日</div>'
            . '<input type="date" id="renewal-completed-date" class="form-input' . $completedDateClass . '" name="completed_date" value="' . Layout::escape((string) ($detail['completed_date'] ?? '')) . '"'
            . Layout::fieldAria($fieldErrors, 'completed_date') . '>'
            . self::renderFieldError($fieldErrors, 'completed_date')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">次回対応予定日</div>'
            . '<input type="date" id="renewal-next-action-date" class="form-input' . $nextActionClass . '" name="next_action_date" value="' . Layout::escape((string) ($detail['next_action_date'] ?? '')) . '"'
            . Layout::fieldAria($fieldErrors, 'next_action_date') . '>'
            . self::renderFieldError($fieldErrors, 'next_action_date')
            . '</div>'
            . '<button class="btn btn-primary" type="submit" style="width:100%;">更新を保存</button>'
            . '</form>'
            . '</div>'
            . '</div>'
            // ── 右カラム ──
            . '<div>'
            . self::renderCustomerLinkSection($contractId, $renewalCaseId, $linkCustomerUrl, $linkCustomerCsrfToken, $listStateParams)
            . self::renderAssignedStaffSection($renewalCaseId, $updateAssignedStaffUrl, $updateAssignedStaffCsrfToken, $salesStaffList, $assignedUserId, $listStateParams)
            . '<details class="card details-panel details-compact">'
            . '<summary><span>コメント</span><span class="muted">' . count($comments) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '" style="margin:0 0 12px;">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . self::renderHiddenInputs($listStateParams)
            . '<label style="display:block;">新規コメント<span style="font-size:11px;color:var(--text-secondary);margin-left:6px;">500文字以内</span><textarea name="comment_body" rows="3" style="width:100%;margin-top:6px;" maxlength="500" required></textarea></label>'
            . '<div class="actions" style="margin-top:10px;"><button class="btn btn-small" type="submit">コメント追加</button></div>'
            . '</form>'
            . '<ul class="panel-list">' . $commentsHtml . '</ul>'
            . '</div>'
            . '</details>'
            . '<details class="card details-panel details-compact">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . $auditsHtml
            . '</div>'
            . '</details>'
            . '</div>'
            . '</div>';

        return Layout::render('満期詳細', $content, $layoutOptions);
    }

    /**
     * 顧客紐づけモーダル（ヘッダ「顧客を変更／顧客を紐づける」ボタンから開く）
     *
     * @param array<string, string> $listStateParams
     */
    private static function renderCustomerLinkSection(
        int $contractId,
        int $renewalCaseId,
        string $linkCustomerUrl,
        string $linkCustomerCsrfToken,
        array $listStateParams
    ): string {
        if ($linkCustomerUrl === '') {
            return '';
        }

        $hiddenInputs = self::renderHiddenInputs($listStateParams)
            . '<input type="hidden" name="contract_id" value="' . $contractId . '">'
            . '<input type="hidden" name="renewal_case_id" value="' . $renewalCaseId . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($linkCustomerCsrfToken) . '">';

        return self::renderCustomerLinkModal($linkCustomerUrl, $hiddenInputs);
    }

    /**
     * 顧客選択モーダル（紐づけ・変更）
     */
    private static function renderCustomerLinkModal(string $linkCustomerUrl, string $hiddenInputs): string
    {
        if ($linkCustomerUrl === '') {
            return '';
        }

        return '<dialog id="customer-link-modal" class="modal-dialog modal-dialog-wide">'
            . '<form id="customer-link-form" method="post" action="' . Layout::escape($linkCustomerUrl) . '">'
            . '<div class="modal-head"><h2>顧客を選択</h2>'
            . '<button type="button" class="modal-close" id="link-modal-close">×</button>'
            . '</div>'
            . $hiddenInputs
            . '<input type="hidden" id="customer-link-selected-id" name="customer_id" value="">'
            . '<div style="margin-bottom:12px;">'
            . '<input type="text" id="customer-link-search" class="compact-input" style="width:100%;" placeholder="顧客名で検索..." autocomplete="off">'
            . '</div>'
            . '<div id="customer-link-results" style="min-height:60px;max-height:300px;overflow-y:auto;">'
            . '<p class="muted" style="font-size:13px;">顧客名を入力すると候補が表示されます</p>'
            . '</div>'
            . '<div id="customer-link-selected" class="muted" style="margin-top:12px;font-size:13px;">選択中: <span id="customer-link-selected-name">（未選択）</span></div>'
            . '<div class="actions" style="justify-content:flex-end;gap:8px;margin-top:12px;">'
            . '<button type="button" class="btn btn-secondary" id="customer-link-cancel">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary" id="customer-link-save" disabled>変更する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("customer-link-modal");'
            . 'if(!dlg||typeof dlg.showModal!=="function")return;'
            . 'var searchInput=document.getElementById("customer-link-search");'
            . 'var resultsEl=document.getElementById("customer-link-results");'
            . 'var hiddenId=document.getElementById("customer-link-selected-id");'
            . 'var selectedNameEl=document.getElementById("customer-link-selected-name");'
            . 'var saveBtn=document.getElementById("customer-link-save");'
            . 'var placeholderHtml="<p class=\"muted\" style=\"font-size:13px;\">顧客名を入力すると候補が表示されます</p>";'
            . 'function resetSelection(){hiddenId.value="";selectedNameEl.textContent="（未選択）";saveBtn.disabled=true;}'
            . 'document.querySelectorAll("[data-open-dialog=\"customer-link-modal\"]").forEach(function(b){'
            . 'b.addEventListener("click",function(){if(!dlg.open)dlg.showModal();searchInput.value="";resultsEl.innerHTML=placeholderHtml;resetSelection();});'
            . '});'
            . 'document.getElementById("link-modal-close").addEventListener("click",function(){if(dlg.open)dlg.close();});'
            . 'document.getElementById("customer-link-cancel").addEventListener("click",function(){if(dlg.open)dlg.close();});'
            . 'var timer=null;'
            . 'searchInput.addEventListener("input",function(){'
            . 'clearTimeout(timer);'
            . 'var q=this.value.trim();'
            . 'if(q.length<1){resultsEl.innerHTML=placeholderHtml;return;}'
            . 'timer=setTimeout(function(){'
            . 'fetch("?route=api%2Fcustomer%2Fsearch-for-link&q="+encodeURIComponent(q))'
            . '.then(function(r){return r.json();})'
            . '.then(function(data){'
            . 'var list=data.customers||[];'
            . 'var html="";'
            . 'if(list.length===0){html="<p class=\"muted\" style=\"font-size:13px;\">該当する顧客がいません</p>";}'
            . 'else{html="<table class=\"table-fixed table-card\" style=\"width:100%;\"><thead><tr><th>顧客名</th><th>生年月日</th><th>電話番号</th><th></th></tr></thead><tbody>";'
            . 'list.forEach(function(c){'
            . 'var safeName=String(c.customer_name||"").replace(/"/g,"&quot;");'
            . 'html+=\'<tr><td>\'+(c.customer_name||"")+\'</td><td>\'+(c.birth_date||\'−\')+\'</td><td>\'+(c.phone||\'−\')+\'</td><td><button type="button" class="btn btn-small btn-secondary" data-cid="\'+c.id+\'" data-cname="\'+safeName+\'">選択</button></td></tr>\';'
            . '});'
            . 'html+="</tbody></table>";}'
            . 'resultsEl.innerHTML=html;'
            . 'resultsEl.querySelectorAll("[data-cid]").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'hiddenId.value=this.getAttribute("data-cid");'
            . 'selectedNameEl.textContent=this.getAttribute("data-cname")||"";'
            . 'saveBtn.disabled=false;'
            . 'resultsEl.querySelectorAll("[data-cid]").forEach(function(b){b.classList.remove("btn-primary");b.classList.add("btn-secondary");});'
            . 'this.classList.remove("btn-secondary");this.classList.add("btn-primary");'
            . '});});'
            . '});'
            . '},300);'
            . '});'
            . '})();</script>';
    }

    /**
     * 営業担当変更モーダル。
     *
     * @param array<int, array<string, mixed>> $salesStaffList
     * @param array<string, string>            $listStateParams
     */
    private static function renderAssignedStaffSection(
        int $renewalCaseId,
        string $updateUrl,
        string $csrfToken,
        array $salesStaffList,
        string $currentAssignedStaffId,
        array $listStateParams
    ): string {
        if ($updateUrl === '') {
            return '';
        }

        $optionsHtml = '<option value="">未設定</option>';
        foreach ($salesStaffList as $s) {
            $sid = (string) ($s['id'] ?? '');
            $name = (string) ($s['staff_name'] ?? '');
            if ($sid === '' || $name === '') {
                continue;
            }
            $sel = $sid === $currentAssignedStaffId ? ' selected' : '';
            $optionsHtml .= '<option value="' . Layout::escape($sid) . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        $hiddenInputs = self::renderHiddenInputs($listStateParams)
            . '<input type="hidden" name="renewal_case_id" value="' . $renewalCaseId . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">';

        return '<dialog id="assigned-staff-modal" class="modal-dialog">'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<div class="modal-head"><h2>営業担当を変更</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="assigned-staff-modal">×</button>'
            . '</div>'
            . $hiddenInputs
            . '<div style="margin:12px 0;">'
            . '<label style="display:block;">営業担当'
            . '<select name="assigned_staff_id" class="compact-input" style="width:100%;margin-top:6px;">' . $optionsHtml . '</select>'
            . '</label>'
            . '</div>'
            . '<div class="actions" style="justify-content:flex-end;gap:8px;">'
            . '<button type="button" class="btn btn-secondary" data-close-dialog="assigned-staff-modal">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">変更する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("assigned-staff-modal");'
            . 'if(!dlg||typeof dlg.showModal!=="function")return;'
            . 'document.querySelectorAll("[data-open-dialog=\"assigned-staff-modal\"]").forEach(function(b){'
            . 'b.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});'
            . '});'
            . 'document.querySelectorAll("[data-close-dialog=\"assigned-staff-modal\"]").forEach(function(b){'
            . 'b.addEventListener("click",function(){if(dlg.open)dlg.close();});'
            . '});'
            . '})();</script>';
    }

    private static function resultLabel(string $result): string
    {
        return match ($result) {
            'pending' => '検討中',
            'renewed' => '更改完了',
            'cancelled' => '中止',
            'lost' => '失注',
            default => '未設定',
        };
    }

    /**
     * @param array<string, string> $statusNameMap  code => display_name from master (may be empty)
     */
    private static function renderStatusBadge(string $status, array $statusNameMap = []): string
    {
        // Badge CSS class mapping (visual, hardcoded fallback)
        $class = match ($status) {
            'completed' => 'badge-success',
            'withdrawn' => 'badge-gray',
            'sj_requested', 'doc_prepared', 'waiting_return', 'quote_sent', 'waiting_payment' => 'badge-info',
            default => 'badge-danger',
        };

        $label = $statusNameMap[$status] ?? $status;

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function commentAuthorLabel(array $row): string
    {
        $authorName = trim((string) ($row['author_name'] ?? ''));
        if ($authorName !== '') {
            return $authorName;
        }

        return '不明なユーザー';
    }

    private static function formatCommentDate(string $createdAt): string
    {
        $value = trim($createdAt);
        if ($value === '') {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('Y-m-d H:i', $ts);
    }

    private static function formatAuditDate(string $changedAt): string
    {
        $value = trim($changedAt);
        if ($value === '') {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }

        return date('Y-m-d H:i', $ts);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function renderTimeline(array $items, string $filterCategory): string
    {
        $html = '';
        foreach ($items as $item) {
            $cat = (string) ($item['category'] ?? 'other');
            if ($filterCategory !== 'all' && $cat !== $filterCategory) {
                continue;
            }

            $diffItems = $item['diff_items'] ?? [];
            if ($diffItems === []) {
                continue;
            }

            // カテゴリ別スタイル
            $borderColor = match ($cat) {
                'status' => 'var(--border-info)',
                'staff'  => 'var(--border-info)',
                default  => 'var(--border-light)',
            };

            $diffHtml = '';
            foreach ($diffItems as $d) {
                $key    = (string) ($d['key'] ?? '');
                $label  = Layout::escape((string) ($d['label'] ?? ''));
                $before = Layout::escape((string) ($d['before'] ?? ''));
                $after  = Layout::escape((string) ($d['after'] ?? ''));

                $isStatus = $key === 'case_status';
                $isStaff  = in_array($key, ['office_staff_id', 'assigned_staff_id'], true);

                $afterStyle = $isStatus
                    ? 'font-weight:700;color:var(--text-info);'
                    : ($isStaff ? 'font-weight:600;color:var(--text-info);' : 'font-weight:600;');

                $diffHtml .= '<div style="display:flex;align-items:baseline;gap:6px;margin:3px 0;font-size:13px;">'
                    . '<span style="min-width:90px;color:var(--text-hint);">' . $label . '</span>'
                    . '<span style="color:var(--text-muted-cool);text-decoration:line-through;">' . $before . '</span>'
                    . '<span style="color:var(--text-muted-cool);">→</span>'
                    . '<span style="' . $afterStyle . '">' . $after . '</span>'
                    . '</div>';
            }

            $html .= '<div style="border-left:3px solid ' . $borderColor . ';padding:8px 10px 8px 12px;margin-bottom:10px;background:var(--bg-primary);border-radius:0 4px 4px 0;">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape((string) ($item['changed_at'] ?? '')) . '</span>'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape((string) ($item['changed_by'] ?? '')) . '</span>'
                . '</div>'
                . $diffHtml
                . '</div>';
        }

        if ($html === '') {
            $html = '<div class="muted" style="font-size:13px;">該当する変更履歴はありません。</div>';
        }

        return $html;
    }

    /**
     * @param array<string, string> $statusNameMap  code => display_name from master (may be empty)
     */
    /**
     * @param array<string, string> $statusNameMap  code => display_name
     * @param array<string, string> $staffNameMap   id => staff_name
     */
    private static function translateFieldValue(string $fieldKey, string $value, array $statusNameMap = [], array $staffNameMap = []): string
    {
        if ($value === '') {
            return '';
        }

        return match ($fieldKey) {
            'case_status' => $statusNameMap[$value] ?? $value,
            'assigned_staff_id', 'office_staff_id' => $staffNameMap[$value] ?? $value,
            default       => $value,
        };
    }

    private static function renderNextAction(string $nextActionDate, bool $isCompleted): string
    {
        $normalized = trim($nextActionDate);
        if ($normalized === '') {
            return '次回対応予定日: 未設定';
        }

        $today = date('Y-m-d');
        if (!$isCompleted && $normalized < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">⚠ 期限超過: ' . Layout::escape($normalized) . '</span>';
        }

        return '次回対応予定日: ' . Layout::escape($normalized);
    }

    /**
     * @param array<string, string> $fieldErrors
     */
    private static function renderFieldError(array $fieldErrors, string $field): string
    {
        // Layout::fieldError に委譲（id 付き span を生成、aria-describedby 参照先）
        return Layout::fieldError($fieldErrors, $field);
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
}
