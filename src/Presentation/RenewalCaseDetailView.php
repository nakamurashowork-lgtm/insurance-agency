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
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
        string $updateUrl,
        string $commentUrl,
        string $backUrl,
        string $backLabel,
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
        array $procedureMethods = []
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $successHtml = '';
        if (is_string($successMessage) && $successMessage !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($successMessage) . '</div>';
        }

        $currentStatus = (string) ($detail['case_status'] ?? 'not_started');
        // Build status map from master data or fallback to static method
        $statusNameMap = [];
        foreach ($renewalStatuses as $sRow) {
            $statusNameMap[(string) ($sRow['code'] ?? '')] = (string) ($sRow['display_name'] ?? '');
        }
        $statusHtml = '';
        foreach ($renewalStatuses as $sRow) {
            $code = (string) ($sRow['code'] ?? '');
            $label = (string) ($sRow['display_name'] ?? $code);
            $selected = $code === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($code) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $renewalMethodOptions = ['', '対面', '郵送', '電話募集'];
        $renewalMethodHtml = '';
        $currentRenewalMethod = (string) ($detail['renewal_method'] ?? '');
        foreach ($renewalMethodOptions as $method) {
            $selected = $method === $currentRenewalMethod ? ' selected' : '';
            $renewalMethodHtml .= '<option value="' . Layout::escape($method) . '"' . $selected . '>' . Layout::escape($method === '' ? '未設定' : $method) . '</option>';
        }

        $currentProcedureMethod = (string) ($detail['procedure_method'] ?? '');
        $pmActiveOptions = '';
        $pmCurrentOption = '';
        $foundInMaster   = false;

        foreach ($procedureMethods as $pmRow) {
            $pmLabel     = (string) ($pmRow['label'] ?? '');
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
                    $beforeValue = self::translateFieldValue($fieldKey, $beforeValue, $statusNameMap);
                    $afterValue  = self::translateFieldValue($fieldKey, $afterValue, $statusNameMap);

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

        $renewalCaseId = (int) ($detail['renewal_case_id'] ?? 0);
        $customerUrl = Layout::escape(
            $customerDetailBaseUrl
            . '&id=' . (string) ($detail['customer_id'] ?? '0')
            . '&return_to=' . urlencode('renewal/detail?id=' . $renewalCaseId)
        );
        $statusBadge = self::renderStatusBadge((string) ($detail['case_status'] ?? 'open'), $statusNameMap);
        $nextActionHtml = self::renderNextAction((string) ($detail['next_action_date'] ?? ''), (string) ($detail['case_status'] ?? 'open'));
        $address = trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')));
        $contractStatus = self::contractStatusLabel((string) ($detail['contract_status'] ?? ''));
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
        $isEarlyDeadlineOverdue = $earlyDeadline !== '' && $earlyDeadline < $today && $currentStatus !== 'completed';
        $alertHtml = '';
        if ($isEarlyDeadlineOverdue) {
            $alertHtml = '<div class="alert alert-warn">⚠ 早期更改締切日（' . Layout::escape($earlyDeadline) . '）を超過しています。</div>';
        }
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

        $customerName = Layout::escape(trim((string) ($detail['customer_name'] ?? '')));
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
            . '<a class="btn btn-secondary" href="' . Layout::escape($backUrl) . '">' . Layout::escape($backLabel) . '</a>'
            . '<button class="btn btn-primary" type="submit" form="renewal-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . $alertHtml
            . '<div class="two-col">'
            // ── 左カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title">契約情報</div>'
            . '<div class="kv"><span class="kv-key">証券番号</span><span class="kv-val">' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">満期日</span><span class="kv-val"' . $maturityDateStyle . '>' . Layout::escape($maturityDate) . '</span></div>'
            . '<div class="kv"><span class="kv-key">契約者名</span><span class="kv-val"><a class="kv-link" href="' . $customerUrl . '">' . $customerName . '</a></span></div>'
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
            . '<select name="office_staff_id" class="form-select">' . $officeStaffOptions . '</select>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">対応状況 <span class="req">*</span></div>'
            . '<select id="renewal-case-status" class="form-select' . $statusClass . '" name="case_status" required>' . $statusHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'case_status')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">更改方法</div>'
            . '<select id="renewal-method-field" class="form-select' . $renewalMethodClass . '" name="renewal_method">' . $renewalMethodHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'renewal_method')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">手続方法</div>'
            . '<select id="procedure-method-field" class="form-select' . $procedureMethodClass . '" name="procedure_method">' . $procedureMethodHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'procedure_method')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">完了日</div>'
            . '<input type="date" id="renewal-completed-date" class="form-input' . $completedDateClass . '" name="completed_date" value="' . Layout::escape((string) ($detail['completed_date'] ?? '')) . '">'
            . self::renderFieldError($fieldErrors, 'completed_date')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">次回対応予定日</div>'
            . '<input type="date" id="renewal-next-action-date" class="form-input' . $nextActionClass . '" name="next_action_date" value="' . Layout::escape((string) ($detail['next_action_date'] ?? '')) . '">'
            . self::renderFieldError($fieldErrors, 'next_action_date')
            . '</div>'
            . '<button class="btn btn-primary" type="submit" style="width:100%;">更新を保存</button>'
            . '</form>'
            . '</div>'
            . '</div>'
            // ── 右カラム ──
            . '<div>'
            . '<details class="card details-panel details-compact" open>'
            . '<summary><span>コメント</span><span class="muted">' . count($comments) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '" style="margin:0 0 12px;">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . self::renderHiddenInputs($listStateParams)
            . '<label style="display:block;">新規コメント<textarea name="comment_body" rows="3" style="width:100%;margin-top:6px;" required></textarea></label>'
            . '<div class="actions" style="margin-top:10px;"><button class="btn btn-small" type="submit">コメント追加</button></div>'
            . '</form>'
            . '<ul class="panel-list">' . $commentsHtml . '</ul>'
            . '</div>'
            . '</details>'
            . '<details class="card details-panel details-compact" open>'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . $auditsHtml
            . '</div>'
            . '</details>'
            . '</div>'
            . '</div>';

        return Layout::render('満期詳細', $content, $layoutOptions);
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

    private static function contractStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => '有効',
            'renewal_pending' => '更改待ち',
            'expired' => '満期',
            'cancelled' => '解約',
            'inactive' => '無効',
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
                'status' => 'var(--color-primary, #2563eb)',
                'staff'  => 'var(--color-info, #0891b2)',
                default  => 'var(--border-color, #d1d5db)',
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
                    ? 'font-weight:700;color:var(--color-primary,#2563eb);'
                    : ($isStaff ? 'font-weight:600;color:var(--color-info,#0891b2);' : 'font-weight:600;');

                $diffHtml .= '<div style="display:flex;align-items:baseline;gap:6px;margin:3px 0;font-size:12.5px;">'
                    . '<span style="min-width:90px;color:var(--text-muted,#6b7280);">' . $label . '</span>'
                    . '<span style="color:var(--text-muted,#9ca3af);text-decoration:line-through;">' . $before . '</span>'
                    . '<span style="color:var(--text-muted,#9ca3af);">→</span>'
                    . '<span style="' . $afterStyle . '">' . $after . '</span>'
                    . '</div>';
            }

            $html .= '<div style="border-left:3px solid ' . $borderColor . ';padding:8px 10px 8px 12px;margin-bottom:10px;background:var(--bg-card,#fff);border-radius:0 4px 4px 0;">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
                . '<span style="font-size:12px;color:var(--text-muted,#6b7280);">' . Layout::escape((string) ($item['changed_at'] ?? '')) . '</span>'
                . '<span style="font-size:12px;color:var(--text-muted,#6b7280);">' . Layout::escape((string) ($item['changed_by'] ?? '')) . '</span>'
                . '</div>'
                . $diffHtml
                . '</div>';
        }

        if ($html === '') {
            $html = '<div class="muted" style="font-size:12.5px;">該当する変更履歴はありません。</div>';
        }

        return $html;
    }

    /**
     * @param array<string, string> $statusNameMap  code => display_name from master (may be empty)
     */
    private static function translateFieldValue(string $fieldKey, string $value, array $statusNameMap = []): string
    {
        if ($value === '') {
            return '';
        }

        return match ($fieldKey) {
            'case_status' => $statusNameMap[$value] ?? $value,
            default       => $value,
        };
    }

    private static function renderNextAction(string $nextActionDate, string $status): string
    {
        $normalized = trim($nextActionDate);
        if ($normalized === '') {
            return '次回対応予定日: 未設定';
        }

        $today = date('Y-m-d');
        if ($status !== 'completed' && $normalized < $today) {
            return '次回対応予定日: 期限超過 ' . Layout::escape($normalized);
        }

        return '次回対応予定日: ' . Layout::escape($normalized);
    }

    /**
     * @param array<string, string> $fieldErrors
     */
    private static function renderFieldError(array $fieldErrors, string $field): string
    {
        $message = trim((string) ($fieldErrors[$field] ?? ''));
        if ($message === '') {
            return '';
        }

        return '<span class="field-error">' . Layout::escape($message) . '</span>';
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
