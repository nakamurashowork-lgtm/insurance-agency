<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class AccidentCaseDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $comments
     * @param array<int, array<string, mixed>> $audits
     * @param array<int, array{id: int, name: string}> $assignedUsers
     * @param array<string, mixed> $layoutOptions
     * @param array<int, array<string, mixed>> $allStatuses
     * @param array<string, mixed>|null $reminderRule
     * @param array<int, array<string, mixed>> $customers
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
        array $assignedUsers,
        string $updateUrl,
        string $commentUrl,
        string $reminderUrl,
        string $updateCsrf,
        string $commentCsrf,
        string $reminderCsrf,
        string $returnToUrl,
        string $customerDetailBaseUrl,
        string $renewalDetailBaseUrl,
        ?string $flashError,
        ?string $flashSuccess,
        array $layoutOptions,
        array $allStatuses = [],
        ?array $reminderRule = null,
        array $customers = [],
        string $updateBasicUrl = '',
        string $updateBasicCsrf = ''
    ): string {
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        // 表示名がそのまま DB 格納値。プルダウンは name のみで構成。
        $currentStatus = (string) ($detail['status'] ?? '');
        $statusHtml = '';
        $currentInMaster = false;
        foreach ($allStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name === '') { continue; }
            if ($name === $currentStatus) { $currentInMaster = true; }
            $selected = $name === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($name) . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }
        // 既存値がマスタに無い場合でも表示できるよう補完
        if ($currentStatus !== '' && !$currentInMaster) {
            $statusHtml = '<option value="' . Layout::escape($currentStatus) . '" selected>' . Layout::escape($currentStatus) . '</option>' . $statusHtml;
        }

        $priorityLabels = [
            'low'    => '低',
            'normal' => '中',
            'high'   => '高',
        ];
        $currentPriority = (string) ($detail['priority'] ?? 'normal');
        $priorityHtml = '';
        foreach ($priorityLabels as $value => $label) {
            $selected = $value === $currentPriority ? ' selected' : '';
            $priorityHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $assignedUserId = (int) ($detail['assigned_staff_id'] ?? 0);
        $assignedUserOptions = '<option value="">未設定</option>';
        $selectedExists = false;
        foreach ($assignedUsers as $user) {
            $uid = (int) ($user['id'] ?? 0);
            $uname = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($uid <= 0 || $uname === '') {
                continue;
            }
            $selected = $assignedUserId === $uid ? ' selected' : '';
            if ($selected !== '') {
                $selectedExists = true;
            }
            $assignedUserOptions .= '<option value="' . $uid . '"' . $selected . '>' . Layout::escape($uname) . '</option>';
        }
        if ($assignedUserId > 0 && !$selectedExists) {
            $assignedUserOptions .= '<option value="' . $assignedUserId . '" selected>現在の担当者（マスタ外）</option>';
        }

        // 担当者名（表示用）
        $assignedName = '';
        foreach ($assignedUsers as $user) {
            if ((int) ($user['id'] ?? 0) === $assignedUserId) {
                $assignedName = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
                break;
            }
        }

        // 住所
        $postalCode = trim((string) ($detail['postal_code'] ?? ''));
        $address1   = trim((string) ($detail['address1'] ?? ''));
        $address2   = trim((string) ($detail['address2'] ?? ''));
        $addressParts = array_filter([$postalCode !== '' ? '〒' . $postalCode : '', $address1, $address2]);
        $address = implode(' ', $addressParts);

        $statusBadge = '<span class="badge badge-gray">' . Layout::escape($currentStatus !== '' ? $currentStatus : '未設定') . '</span>';

        // タイトル（顧客名は表示用 COALESCE: customer_id がない場合は prospect_name を使う）
        $customerName = (string) ($detail['display_customer'] ?? $detail['customer_name'] ?? '');
        $prospectName = (string) ($detail['prospect_name'] ?? '');
        $productType = trim((string) ($detail['product_type'] ?? ''));
        $insuranceCategory = trim((string) ($detail['insurance_category'] ?? ''));
        $titleSuffix = $productType !== '' ? $productType : ($insuranceCategory !== '' ? $insuranceCategory : '');
        $pageTitle = $titleSuffix !== ''
            ? Layout::escape($customerName) . ' — ' . Layout::escape($titleSuffix)
            : Layout::escape($customerName);

        // 顧客リンク
        $customerId    = (int) ($detail['customer_id'] ?? 0);
        $accidentCaseId = (int) ($detail['id'] ?? 0);
        $customerCell = $customerId > 0
            ? '<a class="kv-link" href="' . Layout::escape($customerDetailBaseUrl . '&id=' . $customerId . '&return_to=' . urlencode('accident/detail?id=' . $accidentCaseId)) . '">' . Layout::escape($customerName) . '</a>'
            : Layout::escape($customerName);

        // 関連契約リンク
        $policyNo = trim((string) ($detail['policy_no'] ?? ''));
        $renewalCaseId = (int) ($detail['renewal_case_id'] ?? 0);
        if ($policyNo !== '') {
            if ($renewalCaseId > 0) {
                $contractCell = '<a class="kv-link" href="' . Layout::escape($renewalDetailBaseUrl . '&id=' . $renewalCaseId) . '">' . Layout::escape($policyNo) . '</a>';
            } else {
                $contractCell = Layout::escape($policyNo);
            }
        } else {
            $contractCell = '<span class="muted">未設定</span>';
        }

        // コメント
        $commentsHtml = '';
        foreach ($comments as $row) {
            $author = trim((string) ($row['author_name'] ?? '')) ?: '不明なユーザー';
            $postedAt = self::formatDate((string) ($row['created_at'] ?? ''));
            $commentsHtml .= '<li class="comment-item">'
                . '<div class="comment-meta"><span class="comment-meta-text">' . Layout::escape($author . ' ・ ' . $postedAt) . '</span></div>'
                . '<div class="comment-body">' . Layout::escape((string) ($row['comment_body'] ?? '')) . '</div>'
                . '</li>';
        }
        if ($commentsHtml === '') {
            $commentsHtml = '<li class="muted">0件</li>';
        }

        // 変更履歴 — タイムライン形式
        $statusTranslate = [
            'low' => '低', 'normal' => '中', 'high' => '高',
        ];

        $auditItems = [];
        foreach ($audits as $row) {
            $changedAt = self::formatDate((string) ($row['changed_at'] ?? ''));
            $changedBy = trim((string) ($row['changed_by_name'] ?? '')) ?: '不明なユーザー';

            $details = $row['details'] ?? [];
            $diffItems = [];
            $eventCategory = 'other';

            if (is_array($details)) {
                foreach ($details as $detailRow) {
                    if (!is_array($detailRow)) {
                        continue;
                    }
                    $fieldKey   = trim((string) ($detailRow['field_key'] ?? ''));
                    $fieldLabel = trim((string) ($detailRow['field_label'] ?? '')) ?: $fieldKey;
                    $beforeValue = trim((string) ($detailRow['before_value_text'] ?? ''));
                    $afterValue  = trim((string) ($detailRow['after_value_text'] ?? ''));

                    // 値を日本語ラベルに変換
                    if (isset($statusTranslate[$beforeValue])) { $beforeValue = $statusTranslate[$beforeValue]; }
                    if (isset($statusTranslate[$afterValue]))  { $afterValue  = $statusTranslate[$afterValue]; }

                    if ($beforeValue === '') { $beforeValue = '未設定'; }
                    if ($afterValue  === '') { $afterValue  = '未設定'; }

                    if ($fieldKey === 'status') {
                        $eventCategory = 'status';
                    } elseif ($fieldKey === 'assigned_staff_id' && $eventCategory !== 'status') {
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

            if ($diffItems === []) {
                continue;
            }

            $auditItems[] = [
                'changed_at' => $changedAt,
                'changed_by' => $changedBy,
                'category'   => $eventCategory,
                'diff_items' => $diffItems,
            ];
        }

        $auditsHtml = self::renderTimeline($auditItems);

        // 顧客情報エリア用 URL
        $customerUrl = $customerId > 0
            ? Layout::escape($customerDetailBaseUrl . '&id=' . $customerId)
            : '';

        // 基本情報編集ダイアログ用 datalist
        $basicEditDlId = 'dlg-basic-edit-customer-dl';
        $basicEditDatalist = '';
        $basicEditCustText = '';
        foreach ($customers as $cRow) {
            $cId   = (int) ($cRow['id'] ?? 0);
            $cName = trim((string) ($cRow['customer_name'] ?? ''));
            if ($cId <= 0 || $cName === '') { continue; }
            if ($cId === $customerId) { $basicEditCustText = $cName; }
            $basicEditDatalist .= '<option value="' . Layout::escape($cName) . '" data-id="' . $cId . '">';
        }
        // 既存顧客が紐付いていない場合は prospect_name を初期値として表示
        if ($basicEditCustText === '' && $prospectName !== '') {
            $basicEditCustText = $prospectName;
        }

        $content = $errorHtml
            . $successHtml
            // ── ページヘッダー ──
            . '<div class="page-header">'
            . '<div>'
            . '<h1 class="title">' . $pageTitle . '</h1>'
            . '<div style="margin-top:4px;">' . $statusBadge . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . '<button class="btn btn-primary" type="submit" form="accident-update-form">保存</button>'
            . '</div>'
            . '</div>'
            // ── 2カラム ──
            . '<div class="two-col">'
            // ── 左カラム ──
            . '<div>'
            // 事故基本情報
            . '<div class="card">'
            . '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:5px;border-bottom:0.5px solid var(--border-light);">'
            . '<div style="font-size:12px;font-weight:500;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;">事故基本情報</div>'
            . ($updateBasicUrl !== '' ? '<button type="button" class="btn btn-secondary btn-small" onclick="document.getElementById(\'dlg-basic-edit\').showModal()">編集</button>' : '')
            . '</div>'
            . '<div class="kv"><span class="kv-key">顧客名</span><span class="kv-val">' . ($customerUrl !== '' ? '<a class="kv-link" href="' . $customerUrl . '">' . Layout::escape($customerName) . '</a>' : Layout::escape($customerName) . ($prospectName !== '' && $customerId === 0 ? ' <span class="muted" style="font-size:11px;">（未登録顧客）</span>' : '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">受付日</span><span class="kv-val">' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">事故発生日</span><span class="kv-val">' . (trim((string) ($detail['accident_date'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_date']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険種類</span><span class="kv-val">' . (trim((string) ($detail['insurance_category'] ?? '')) !== '' ? Layout::escape((string) $detail['insurance_category']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">担当拠点</span><span class="kv-val">' . (trim((string) ($detail['accident_location'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_location']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">SC担当者</span><span class="kv-val">' . (trim((string) ($detail['sc_staff_name'] ?? '')) !== '' ? Layout::escape((string) $detail['sc_staff_name']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '</div>'
            // 対応状況更新フォーム
            . '<div class="card">'
            . '<div class="detail-section-title">対応状況を更新</div>'
            . '<form id="accident-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . (int) ($detail['id'] ?? 0) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<div class="form-row"><div class="form-label">対応状況 <strong class="required-mark">*</strong></div><select class="form-select" name="status" required>' . $statusHtml . '</select></div>'
            . '<div class="form-row"><div class="form-label">優先度</div><select class="form-select" name="priority">' . $priorityHtml . '</select></div>'
            . '<div class="form-row"><div class="form-label">担当者</div><select class="form-select" name="assigned_staff_id">' . $assignedUserOptions . '</select></div>'
            . '<div class="form-row"><div class="form-label">解決日</div><input class="form-input" type="date" name="resolved_date" value="' . Layout::escape((string) ($detail['resolved_date'] ?? '')) . '"></div>'
            . '<div class="dialog-actions"><button class="btn btn-primary" type="submit">保存する</button></div>'
            . '</form>'
            . '</div>'
            // リマインド設定エリア
            . self::renderReminderSection($reminderRule, $detail, $reminderUrl, $reminderCsrf, $returnToUrl)
            . '</div>'
            // ── 右カラム ──
            . '<div>'
            // コメント
            . '<details class="card details-panel details-compact">'
            . '<summary><span>コメント</span><span class="muted">' . count($comments) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '" style="margin:0 0 12px;">'
            . '<input type="hidden" name="id" value="' . (int) ($detail['id'] ?? 0) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<label style="display:block;">新規コメント<span style="font-size:11px;color:var(--text-secondary);margin-left:6px;">500文字以内</span><textarea name="comment_body" rows="3" style="width:100%;margin-top:6px;" maxlength="500" required></textarea></label>'
            . '<div class="actions" style="margin-top:10px;"><button class="btn btn-small" type="submit">コメント追加</button></div>'
            . '</form>'
            . '<ul class="panel-list">' . $commentsHtml . '</ul>'
            . '</div>'
            . '</details>'
            // 変更履歴
            . '<details class="card details-panel details-compact">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">' . $auditsHtml . '</div>'
            . '</details>'
            . '</div>'
            . '</div>'
            . self::renderBasicEditDialog(
                $accidentCaseId,
                $detail,
                $updateBasicUrl,
                $updateBasicCsrf,
                $returnToUrl,
                $basicEditDlId,
                $basicEditDatalist,
                $basicEditCustText,
                $customerId
            );

        return Layout::render('事故案件詳細', $content, $layoutOptions);
    }

    private static function renderBasicEditDialog(
        int $accidentCaseId,
        array $detail,
        string $updateBasicUrl,
        string $updateBasicCsrf,
        string $returnToUrl,
        string $dlId,
        string $datalist,
        string $custText,
        int $currentCustomerId
    ): string {
        if ($updateBasicUrl === '') {
            return '';
        }

        $acceptedDate      = Layout::escape((string) ($detail['accepted_date'] ?? ''));
        $accidentDate      = Layout::escape((string) ($detail['accident_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($detail['insurance_category'] ?? ''));
        $accidentLocation  = Layout::escape((string) ($detail['accident_location'] ?? ''));
        $scStaffName       = Layout::escape((string) ($detail['sc_staff_name'] ?? ''));
        $custIdVal         = $currentCustomerId > 0 ? $currentCustomerId : '';

        return ''
            . '<dialog id="dlg-basic-edit" class="modal-dialog">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>基本情報を編集</h2></div>'
            . '<form method="post" action="' . Layout::escape($updateBasicUrl) . '">'
            . '<input type="hidden" name="id" value="' . $accidentCaseId . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateBasicCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<input type="hidden" name="customer_id" id="dlg-basic-edit-customer-id" value="' . $custIdVal . '">'
            . '<input type="hidden" name="prospect_name" id="dlg-basic-edit-prospect-name" value="' . Layout::escape($currentCustomerId > 0 ? '' : $custText) . '">'
            . '<datalist id="' . $dlId . '">' . $datalist . '</datalist>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>お客さま <strong class="required-mark">*</strong></span><input type="text" list="' . $dlId . '" id="dlg-basic-edit-customer-text" autocomplete="off" value="' . Layout::escape($custText) . '" placeholder="既存顧客から選択 または 依頼者名を入力" required></label>'
            . '<label class="list-filter-field"><span>受付日 <strong class="required-mark">*</strong></span><input type="date" name="accepted_date" value="' . $acceptedDate . '" required></label>'
            . '<label class="list-filter-field"><span>事故発生日</span><input type="date" name="accident_date" value="' . $accidentDate . '"></label>'
            . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" value="' . $insuranceCategory . '" maxlength="50"></label>'
            . '<label class="list-filter-field"><span>担当拠点</span><input type="text" name="accident_location" value="' . $accidentLocation . '" maxlength="255"></label>'
            . '<label class="list-filter-field"><span>SC担当者</span><input type="text" name="sc_staff_name" value="' . $scStaffName . '" maxlength="100"></label>'
            . '</div>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn btn-primary" type="submit">保存する</button>'
            . '<button class="btn btn-secondary" type="button" onclick="document.getElementById(\'dlg-basic-edit\').close()">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '<script>(function(){'
            . 'var txt=document.getElementById("dlg-basic-edit-customer-text");'
            . 'var hid=document.getElementById("dlg-basic-edit-customer-id");'
            . 'var pros=document.getElementById("dlg-basic-edit-prospect-name");'
            . 'var dl=document.getElementById("' . $dlId . '");'
            . 'if(!txt||!hid||!pros||!dl){return;}'
            . 'function sync(){var v=txt.value;var opts=dl.querySelectorAll("option");var found=false;'
            . 'for(var i=0;i<opts.length;i++){if(opts[i].value===v){hid.value=opts[i].getAttribute("data-id")||"";found=true;break;}}'
            . 'if(found){pros.value="";}else{hid.value="";pros.value=v;}}'
            . 'txt.addEventListener("input",sync);txt.addEventListener("change",sync);'
            . '})();</script>'
            . '</dialog>';
    }

    /**
     * @param array<string, mixed>|null $rule
     * @param array<string, mixed> $detail
     */
    private static function renderReminderSection(
        ?array $rule,
        array $detail,
        string $reminderUrl,
        string $reminderCsrf,
        string $returnToUrl
    ): string {
        $accidentCaseId = (int) ($detail['id'] ?? 0);

        $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];

        if ($rule === null) {
            $formHtml = self::renderReminderForm(
                $accidentCaseId, null, $reminderUrl, $reminderCsrf, $returnToUrl, $weekdayLabels, false
            );
            return '<div class="card"><div class="detail-section-title">リマインド設定</div>'
                . '<p class="muted" style="font-size:13px;margin-bottom:8px;">リマインド未設定</p>'
                . '<details>'
                . '<summary style="cursor:pointer;color:var(--text-info);font-size:13px;padding:4px 0;list-style:none;">設定する ▸</summary>'
                . '<div style="margin-top:10px;">' . $formHtml . '</div>'
                . '</details>'
                . '</div>';
        }

        // 既存ルール
        $weekdays   = (array) ($rule['weekdays'] ?? []);
        $nextDate   = self::calcNextReminderDate($rule, $weekdays);
        $lastNotify = trim((string) ($rule['last_notified_on'] ?? ''));
        $lastNotifyDisplay = $lastNotify !== '' ? Layout::escape($lastNotify) : '—';

        $formHtml = self::renderReminderForm(
            $accidentCaseId, $rule, $reminderUrl, $reminderCsrf, $returnToUrl, $weekdayLabels, true
        );
        return '<div class="card"><div class="detail-section-title">リマインド設定</div>'
            . '<div class="kv"><span class="kv-key">次回通知予定</span><span class="kv-val">' . Layout::escape($nextDate) . '</span></div>'
            . '<div class="kv"><span class="kv-key">最終通知日</span><span class="kv-val">' . $lastNotifyDisplay . '</span></div>'
            . '<details open>'
            . '<summary style="cursor:pointer;font-size:13px;padding:4px 0;color:var(--text-secondary);">設定を編集</summary>'
            . '<div style="margin-top:10px;">' . $formHtml . '</div>'
            . '</details>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $rule
     * @param string[] $weekdayLabels
     */
    private static function renderReminderForm(
        int $accidentCaseId,
        ?array $rule,
        string $reminderUrl,
        string $reminderCsrf,
        string $returnToUrl,
        array $weekdayLabels,
        bool $hasExisting
    ): string {
        $isEnabled     = $rule !== null ? (int) ($rule['is_enabled'] ?? 0) : 1;
        $intervalWeeks = $rule !== null ? (int) ($rule['interval_weeks'] ?? 1) : 1;
        $startDate     = $rule !== null ? trim((string) ($rule['start_date'] ?? '')) : '';
        $endDate       = $rule !== null ? trim((string) ($rule['end_date'] ?? '')) : '';
        $savedWeekdays = $rule !== null ? (array) ($rule['weekdays'] ?? []) : [];

        $disabledAttr  = $isEnabled === 0 ? ' disabled' : '';

        $weekdayCheckboxes = '';
        for ($w = 0; $w <= 6; $w++) {
            $checked = in_array($w, $savedWeekdays, true) ? ' checked' : '';
            $weekdayCheckboxes .= '<label style="display:inline-flex;align-items:center;gap:4px;margin-right:10px;">'
                . '<input type="checkbox" name="weekdays[]" value="' . $w . '"' . $checked . $disabledAttr . '>'
                . '<span>' . $weekdayLabels[$w] . '</span>'
                . '</label>';
        }

        $enabledChecked = $isEnabled === 1 ? ' checked' : '';

        return '<form method="post" action="' . Layout::escape($reminderUrl) . '" id="reminder-form">'
            . '<input type="hidden" name="id" value="' . $accidentCaseId . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($reminderCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<div class="form-row">'
            . '<div class="form-label">リマインド有効/無効</div>'
            . '<label style="display:inline-flex;align-items:center;gap:8px;">'
            . '<input type="checkbox" name="is_enabled" value="1" id="reminder-enabled"' . $enabledChecked . ' onchange="toggleReminderFields(this.checked)">'
            . '<span>有効</span>'
            . '</label>'
            . '</div>'
            . '<div class="form-row"><div class="form-label">通知間隔（週）</div>'
            . '<input class="form-input" type="number" name="interval_weeks" value="' . $intervalWeeks . '" min="1" style="width:80px;"' . $disabledAttr . ' id="reminder-interval">'
            . '</div>'
            . '<div class="form-row"><div class="form-label">通知曜日</div>'
            . '<div id="reminder-weekdays">' . $weekdayCheckboxes . '</div>'
            . '</div>'
            . '<div class="form-row"><div class="form-label">通知開始日</div>'
            . '<input class="form-input" type="date" name="start_date" value="' . Layout::escape($startDate) . '"' . $disabledAttr . ' id="reminder-start">'
            . '</div>'
            . '<div class="form-row"><div class="form-label">通知終了日</div>'
            . '<input class="form-input" type="date" name="end_date" value="' . Layout::escape($endDate) . '"' . $disabledAttr . ' id="reminder-end">'
            . '</div>'
            . '<div class="dialog-actions">'
            . '<button class="btn btn-primary" type="submit">リマインド設定を保存</button>'
            . '</div>'
            . '</form>'
            . '<script>'
            . 'function toggleReminderFields(enabled){'
            . 'var ids=["reminder-interval","reminder-start","reminder-end"];'
            . 'ids.forEach(function(id){var el=document.getElementById(id);if(el){el.disabled=!enabled;}});'
            . 'var cbs=document.querySelectorAll("#reminder-weekdays input[type=checkbox]");'
            . 'cbs.forEach(function(cb){cb.disabled=!enabled;});'
            . '}'
            . '</script>';
    }

    /**
     * @param array<string, mixed> $rule
     * @param int[] $weekdays
     */
    private static function calcNextReminderDate(array $rule, array $weekdays): string
    {
        if ((int) ($rule['is_enabled'] ?? 0) === 0) {
            return '無効';
        }

        if ($weekdays === []) {
            return '—';
        }

        $today   = new \DateTimeImmutable(date('Y-m-d'));
        $endDate = trim((string) ($rule['end_date'] ?? '')) !== ''
            ? new \DateTimeImmutable((string) $rule['end_date'])
            : null;

        if ($endDate !== null && $endDate < $today) {
            return '終了済み';
        }

        $startDate = trim((string) ($rule['start_date'] ?? '')) !== ''
            ? new \DateTimeImmutable((string) $rule['start_date'])
            : null;

        $baseDate     = new \DateTimeImmutable((string) $rule['base_date']);
        $intervalDays = max(1, (int) ($rule['interval_weeks'] ?? 1)) * 7;

        // 今日以降で base_date から intervalDays の倍数かつ weekday_cd が一致する最初の日を探す
        $limit = $today->modify('+2 years');
        $date  = $today;

        while ($date <= $limit) {
            $weekdayCd = (int) $date->format('w');
            if (in_array($weekdayCd, $weekdays, true)) {
                $diff = $baseDate->diff($date);
                if ($diff->invert === 0) {
                    $daysDiff = (int) $diff->days;
                    if ($daysDiff % $intervalDays === 0) {
                        if ($startDate !== null && $date < $startDate) {
                            $date = $date->modify('+1 day');
                            continue;
                        }
                        if ($endDate !== null && $date > $endDate) {
                            return '終了済み';
                        }
                        return $date->format('Y-m-d');
                    }
                }
            }
            $date = $date->modify('+1 day');
        }

        return '—';
    }

    private static function formatDate(string $value): string
    {
        $value = trim($value);
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
    private static function renderTimeline(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $cat = (string) ($item['category'] ?? 'other');
            $diffItems = $item['diff_items'] ?? [];
            if ($diffItems === []) {
                continue;
            }

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

                $isStatus = $key === 'status';
                $isStaff  = $key === 'assigned_staff_id';

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
}
