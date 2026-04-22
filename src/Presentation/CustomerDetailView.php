<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class CustomerDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $activities
     * @param array<int, array<string, mixed>> $accidentCases
     * @param array<int, array<string, mixed>> $salesCases
     * @param array<int, array<string, mixed>> $audits
     * @param array<string, mixed>|null $editDraft
     * @param array<int, string> $editErrors
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $detail,
        array $contracts,
        array $activities,
        array $accidentCases,
        array $salesCases,
        array $audits,
        string $listUrl,
        string $detailUrl,
        string $renewalDetailBaseUrl,
        string $activityListBaseUrl,
        string $activityDetailBaseUrl,
        string $salesCaseDetailBaseUrl,
        string $accidentDetailBaseUrl,
        string $updateUrl,
        string $editCsrf,
        ?array $editDraft,
        array $editErrors,
        ?string $errorMessage,
        ?string $successMessage,
        array $layoutOptions
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $successHtml = '';
        if (is_string($successMessage) && $successMessage !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($successMessage) . '</div>';
        }

        $customerId = (int) ($detail['id'] ?? 0);
        $today = date('Y-m-d');

        // 満期案件テーブル（満期レコード単位で1行）
        $contractsHtml = '';
        foreach ($contracts as $row) {
            $renewalCaseId = (int) ($row['renewal_case_id'] ?? 0);
            $policyNo = Layout::escape((string) ($row['policy_no'] ?? ''));
            $maturityDate = trim((string) ($row['maturity_date'] ?? ''));
            $endDate = $maturityDate !== '' ? $maturityDate : (string) ($row['policy_end_date'] ?? '');
            $endDateColor = $endDate !== '' && $endDate < $today ? 'color:var(--text-danger);font-weight:500;' : '';

            if ($renewalCaseId > 0) {
                $renewalUrl = Layout::escape($renewalDetailBaseUrl . '&id=' . $renewalCaseId . '&from=customer&customer_id=' . $customerId);
                $policyCell = '<a class="text-link" href="' . $renewalUrl . '">' . $policyNo . '</a>';
            } else {
                $policyCell = $policyNo;
            }

            $contractsHtml .= '<tr>'
                . '<td data-label="満期日／証券番号" style="white-space:nowrap;">'
                . '<span style="' . $endDateColor . '">' . Layout::escape($endDate) . '</span>'
                . '<span style="margin-left:12px;font-size:12px;color:#627d98;">' . $policyCell . '</span>'
                . '</td>'
                . '<td data-label="種目">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td data-label="状態">' . self::renderContractStatus((string) ($row['case_status'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($contractsHtml === '') {
            $contractsHtml = '<tr><td colspan="3">満期案件はありません。</td></tr>';
        }

        // 事故案件テーブル
        $accidentHtml = '';
        foreach ($accidentCases as $row) {
            $accidentId       = (int) ($row['id'] ?? 0);
            $accidentDetailUrl = $accidentId > 0
                ? Layout::escape($accidentDetailBaseUrl . '&id=' . $accidentId . '&from=customer&customer_id=' . $customerId)
                : '';
            $acceptedDate     = Layout::escape((string) ($row['accepted_date'] ?? ''));
            $acceptedDateCell = $accidentDetailUrl !== ''
                ? '<a class="text-link" href="' . $accidentDetailUrl . '">' . $acceptedDate . '</a>'
                : $acceptedDate;

            $accidentHtml .= '<tr>'
                . '<td data-label="受付日" style="white-space:nowrap;">' . $acceptedDateCell . '</td>'
                . '<td data-label="事故発生日" style="white-space:nowrap;">' . Layout::escape((string) ($row['accident_date'] ?? '')) . '</td>'
                . '<td data-label="保険種類" class="cell-ellipsis">' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '</td>'
                . '<td data-label="SC担当者">' . Layout::escape((string) ($row['sc_staff_name'] ?? '')) . '</td>'
                . '<td data-label="対応状況">' . self::renderAccidentStatus((string) ($row['status'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($accidentHtml === '') {
            $accidentHtml = '<tr><td colspan="5">事故案件はありません。</td></tr>';
        }

        // 営業活動テーブル（直近5件）
        $activitiesHtml = '';
        foreach ($activities as $row) {
            $subject  = trim((string) ($row['subject'] ?? '')) ?: trim((string) ($row['activity_type'] ?? ''));
            $actDate  = Layout::escape(trim((string) ($row['activity_date'] ?? '')));
            $actType  = Layout::escape(trim((string) ($row['activity_type'] ?? '')));
            $staffName = Layout::escape(trim((string) ($row['staff_name'] ?? '')));
            $actId    = (int) ($row['id'] ?? 0);
            $actDetailUrl = $actId > 0 ? Layout::escape($activityDetailBaseUrl . '&id=' . $actId . '&from=customer&customer_id=' . $customerId) : '';
            $subjectHtml  = $actDetailUrl !== ''
                ? '<a class="text-link" href="' . $actDetailUrl . '">' . Layout::escape($subject) . '</a>'
                : Layout::escape($subject);
            $activitiesHtml .= '<tr>'
                . '<td data-label="活動日" style="white-space:nowrap;">' . $actDate . '</td>'
                . '<td data-label="活動概要" class="cell-ellipsis">' . $subjectHtml . '</td>'
                . '<td data-label="担当者">' . $staffName . '</td>'
                . '<td data-label="活動種別">' . $actType . '</td>'
                . '</tr>';
        }
        if ($activitiesHtml === '') {
            $activitiesHtml = '<tr><td colspan="4">活動履歴はありません。</td></tr>';
        }

        // 編集フォーム初期値（draft があれば draft 優先）
        $d = $editDraft ?? $detail;
        $editErrorsHtml = '';
        if ($editErrors !== []) {
            $editErrorsHtml = '<div class="error" style="margin-bottom:12px;">' . Layout::escape(implode(' ', $editErrors)) . '</div>';
        }

        $draftName    = Layout::escape((string) ($d['customer_name'] ?? ''));
        $draftBirth   = Layout::escape((string) ($d['birth_date'] ?? ''));
        $draftType    = (string) ($d['customer_type'] ?? '');
        $draftPhone   = Layout::escape((string) ($d['phone'] ?? ''));
        $draftPostal  = Layout::escape((string) ($d['postal_code'] ?? ''));
        $draftAddress1 = Layout::escape((string) ($d['address1'] ?? ''));
        $draftAddress2 = Layout::escape((string) ($d['address2'] ?? ''));
        $draftNote    = Layout::escape((string) ($d['note'] ?? ''));

        $auditHtml = self::renderAuditTimeline($audits);

        $openModal = (string) ($_GET['open_modal'] ?? '');
        $openDialog = ($openModal === 'edit' || $editDraft !== null) ? 'true' : 'false';

        $content = $errorHtml
            . $successHtml
            . '<div class="page-header">'
            . '<div><h1 class="title">顧客詳細</h1>' . self::renderCustomerStatus((string) ($detail['status'] ?? '')) . '</div>'
            . '<div class="actions">'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . '</div>'
            . '</div>'
            // ── 左カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title" style="display:flex;justify-content:space-between;align-items:center;">基本情報<button type="button" class="btn btn-secondary" style="font-size:12px;padding:3px 10px;" onclick="(function(){var d=document.getElementById(\'customer-edit-dialog\');if(d&&typeof d.showModal===\'function\'&&!d.open){d.showModal();}})()">基本情報を編集</button></div>'
            . '<div class="kv"><span class="kv-key">顧客名</span><span class="kv-val">' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">顧客種別</span><span class="kv-val">' . Layout::escape(self::formatCustomerType((string) ($detail['customer_type'] ?? ''))) . '</span></div>'
            . '<div class="kv"><span class="kv-key">生年月日</span><span class="kv-val">' . Layout::escape((string) ($detail['birth_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">電話番号</span><span class="kv-val">' . Layout::escape((string) ($detail['phone'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">郵便番号</span><span class="kv-val">' . Layout::escape((string) ($detail['postal_code'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">住所1</span><span class="kv-val">' . Layout::escape((string) ($detail['address1'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">住所2</span><span class="kv-val">' . Layout::escape((string) ($detail['address2'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">備考</span><span class="kv-val" style="white-space:pre-wrap;">' . Layout::escape((string) ($detail['note'] ?? '')) . '</span></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">満期案件</div>'
            . '<div class="table-wrap"><table class="table-fixed table-card list-table list-table-cust-contract" style="margin:0;">'
            . '<colgroup><col><col class="list-col-type"><col class="list-col-status"></colgroup>'
            . '<thead><tr><th>満期日／証券番号</th><th>種目</th><th>状態</th></tr></thead>'
            . '<tbody>' . $contractsHtml . '</tbody>'
            . '</table></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">事故案件</div>'
            . '<div class="table-wrap"><table class="table-fixed table-card list-table list-table-cust-accident" style="margin:0;">'
            . '<colgroup><col class="list-col-date"><col class="list-col-date"><col><col class="list-col-product"><col class="list-col-status"></colgroup>'
            . '<thead><tr><th>受付日</th><th>事故発生日</th><th>保険種類</th><th>SC担当者</th><th>対応状況</th></tr></thead>'
            . '<tbody>' . $accidentHtml . '</tbody>'
            . '</table></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">営業活動</div>'
            . '<div class="table-wrap"><table class="table-fixed table-card list-table list-table-cust-activity" style="margin:0;">'
            . '<colgroup><col class="list-col-date"><col><col class="list-col-staff"><col class="list-col-type"></colgroup>'
            . '<thead><tr><th>活動日</th><th>活動概要</th><th>担当者</th><th>活動種別</th></tr></thead>'
            . '<tbody>' . $activitiesHtml . '</tbody>'
            . '</table></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">基本情報の変更履歴</div>'
            . $auditHtml
            . '</div>'
            . '</div>'
            /* 見込案件セクション: H-5判断#8 により非表示（コードは保持）
            . '<details class="card details-panel details-compact">'
            . '<summary><span>見込案件</span><span class="muted">' . count($salesCases) . '件</span></summary>'
            . '<div class="details-compact-body"><ul class="panel-list" style="list-style:none;padding:0;margin:0;"></ul></div>'
            . '</details>'
            */
            // ── 編集ダイアログ ──
            . '<dialog id="customer-edit-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '" id="customer-edit-form" class="customer-create-form">'
            . self::renderRouteInput($updateUrl)
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($editCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $customerId . '">'
            . '<h2 class="modal-title">基本情報を編集</h2>'
            . $editErrorsHtml
            . '<div class="customer-create-grid">'
            . '<label class="form-field"><span class="form-field-label">顧客名 <strong class="required-mark">*</strong></span>'
            . '<input type="text" name="customer_name" value="' . $draftName . '" maxlength="200" required></label>'
            . '<label class="form-field"><span class="form-field-label">生年月日</span>'
            . '<input type="date" name="birth_date" value="' . $draftBirth . '"></label>'
            . '<label class="form-field"><span class="form-field-label">顧客種別 <strong class="required-mark">*</strong></span>'
            . '<select name="customer_type">'
            . '<option value="">選択してください</option>'
            . '<option value="individual"' . ($draftType === 'individual' ? ' selected' : '') . '>個人</option>'
            . '<option value="corporate"' . ($draftType === 'corporate' ? ' selected' : '') . '>法人</option>'
            . '</select></label>'
            . '<label class="form-field"><span class="form-field-label">電話番号</span>'
            . '<input type="text" name="phone" value="' . $draftPhone . '" maxlength="20"></label>'
            . '<label class="form-field"><span class="form-field-label">郵便番号</span>'
            . '<input type="text" name="postal_code" value="' . $draftPostal . '" maxlength="8"></label>'
            . '<label class="form-field"><span class="form-field-label">住所1</span>'
            . '<input type="text" name="address1" value="' . $draftAddress1 . '" maxlength="200"></label>'
            . '<label class="form-field"><span class="form-field-label">住所2</span>'
            . '<input type="text" name="address2" value="' . $draftAddress2 . '" maxlength="200"></label>'
            . '<label class="form-field form-field--full"><span class="form-field-label">備考</span>'
            . '<textarea name="note" rows="6" maxlength="2000">' . $draftNote . '</textarea></label>'
            . '</div>'
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'customer-edit-dialog\').close()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">保存する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>'
            . '(function(){'
            . 'const dlg=document.getElementById("customer-edit-dialog");'
            . 'if(!dlg)return;'
            . 'if(' . $openDialog . '&&typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}'
            . '})();'
            . '</script>';

        return Layout::render('顧客詳細', $content, $layoutOptions);
    }

    private static function formatCustomerType(string $type): string
    {
        return match ($type) {
            'individual' => '個人',
            'corporate'  => '法人',
            default      => $type,
        };
    }

    private static function renderCustomerStatus(string $status): string
    {
        $label = match ($status) {
            'active'   => '有効',
            'prospect' => '見込',
            'inactive' => '休眠',
            'closed'   => '終了',
            default    => '未設定',
        };
        $class = match ($status) {
            'active'            => 'badge-success',
            'prospect'          => 'badge-info',
            'inactive', 'closed' => 'badge-gray',
            default             => 'badge-danger',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderContractStatus(string $status): string
    {
        if ($status === '') {
            return '<span class="muted">—</span>';
        }

        // m_case_status.name はDB格納値と表示名が同一のため、ラベルはそのまま使用
        $class = match ($status) {
            '完了'               => 'badge-success',
            '取り下げ'           => 'badge-gray',
            '未対応'             => 'badge-danger',
            default              => 'badge-info',
        };

        return '<span class="badge ' . $class . '" style="white-space:nowrap;">' . Layout::escape($status) . '</span>';
    }

    private static function renderAccidentStatus(string $status): string
    {
        [$label, $class] = match ($status) {
            'accepted'     => ['受付済',   'badge-info'],
            'linked'       => ['紐付済',   'badge-info'],
            'in_progress'  => ['対応中',   'badge-warn'],
            'waiting_docs' => ['書類待ち', 'badge-warn'],
            'resolved'     => ['解決済',   'badge-success'],
            'closed'       => ['完了',     'badge-gray'],
            default        => [$status,    'badge-danger'],
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    /**
     * @param array<int, array<string, mixed>> $audits
     */
    private static function renderAuditTimeline(array $audits): string
    {
        $html = '';
        foreach ($audits as $row) {
            if (!is_array($row)) {
                continue;
            }
            $details = $row['details'] ?? [];
            if (!is_array($details) || $details === []) {
                continue;
            }

            $diffHtml = '';
            foreach ($details as $detailRow) {
                if (!is_array($detailRow)) {
                    continue;
                }
                $fieldKey   = trim((string) ($detailRow['field_key'] ?? ''));
                $fieldLabel = trim((string) ($detailRow['field_label'] ?? ''));
                if ($fieldLabel === '') {
                    $fieldLabel = $fieldKey;
                }
                $beforeRaw = $detailRow['before_value_text'] ?? null;
                $afterRaw  = $detailRow['after_value_text']  ?? null;
                $before = self::translateAuditValue($fieldKey, $beforeRaw);
                $after  = self::translateAuditValue($fieldKey, $afterRaw);
                if ($before === '') { $before = '未設定'; }
                if ($after  === '') { $after  = '未設定'; }

                $diffHtml .= '<div style="display:flex;align-items:baseline;gap:6px;margin:3px 0;font-size:13px;">'
                    . '<span style="min-width:90px;color:var(--text-hint);">' . Layout::escape($fieldLabel) . '</span>'
                    . '<span style="color:var(--text-muted-cool);text-decoration:line-through;">' . Layout::escape($before) . '</span>'
                    . '<span style="color:var(--text-muted-cool);">→</span>'
                    . '<span style="font-weight:600;">' . Layout::escape($after) . '</span>'
                    . '</div>';
            }

            if ($diffHtml === '') {
                continue;
            }

            $changedAt = (string) ($row['changed_at'] ?? '');
            $changedBy = trim((string) ($row['changed_by_name'] ?? ''));
            if ($changedBy === '') {
                $changedBy = '不明なユーザー';
            }

            $html .= '<div style="border-left:3px solid var(--border-light);padding:8px 10px 8px 12px;margin-bottom:10px;background:var(--bg-primary);border-radius:0 4px 4px 0;">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape($changedAt) . '</span>'
                . '<span style="font-size:12px;color:var(--text-hint);">' . Layout::escape($changedBy) . '</span>'
                . '</div>'
                . $diffHtml
                . '</div>';
        }

        if ($html === '') {
            return '<div class="muted" style="font-size:13px;">変更履歴はありません。</div>';
        }
        return $html;
    }

    private static function translateAuditValue(string $fieldKey, mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if ($fieldKey === 'customer_type') {
            return self::formatCustomerType($text);
        }
        return $text;
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

}
