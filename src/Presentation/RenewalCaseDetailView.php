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
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
        string $updateUrl,
        string $commentUrl,
        string $listUrl,
        string $detailUrl,
        array $listStateParams,
        string $customerDetailBaseUrl,
        string $csrfToken,
        string $commentCsrfToken,
        ?string $errorMessage,
        ?string $successMessage,
        array $fieldErrors,
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

        $statusOptions = ['not_started', 'sj_requested', 'doc_prepared', 'waiting_return', 'quote_sent', 'waiting_payment', 'completed'];
        $statusHtml = '';
        $currentStatus = (string) ($detail['case_status'] ?? 'not_started');
        foreach ($statusOptions as $status) {
            $selected = $status === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($status) . '"' . $selected . '>' . Layout::escape(self::statusLabel($status)) . '</option>';
        }

        $resultOptions = ['', 'pending', 'renewed', 'cancelled', 'lost'];
        $resultHtml = '';
        $currentResult = (string) ($detail['renewal_result'] ?? '');
        foreach ($resultOptions as $result) {
            $selected = $result === $currentResult ? ' selected' : '';
            $label = self::resultLabel($result);
            $resultHtml .= '<option value="' . Layout::escape($result) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $renewalMethodOptions = ['', '対面', '郵送', '電話募集'];
        $renewalMethodHtml = '';
        $currentRenewalMethod = (string) ($detail['renewal_method'] ?? '');
        foreach ($renewalMethodOptions as $method) {
            $selected = $method === $currentRenewalMethod ? ' selected' : '';
            $renewalMethodHtml .= '<option value="' . Layout::escape($method) . '"' . $selected . '>' . Layout::escape($method === '' ? '未設定' : $method) . '</option>';
        }

        $procedureMethodOptions = ['', '対面', '対面ナビ', '電話ナビ', '電話募集', '署名・捺印', 'ケータイOR', 'マイページ'];
        $procedureMethodHtml = '';
        $currentProcedureMethod = (string) ($detail['procedure_method'] ?? '');
        foreach ($procedureMethodOptions as $method) {
            $selected = $method === $currentProcedureMethod ? ' selected' : '';
            $procedureMethodHtml .= '<option value="' . Layout::escape($method) . '"' . $selected . '>' . Layout::escape($method === '' ? '未設定' : $method) . '</option>';
        }

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

        $auditsHtml = '';
        foreach ($audits as $row) {
            $changedAt = self::formatAuditDate((string) ($row['changed_at'] ?? ''));
            $changedBy = trim((string) ($row['changed_by_name'] ?? ''));
            if ($changedBy === '') {
                $changedBy = '不明なユーザー';
            }

            $detailsHtml = '';
            $details = $row['details'] ?? [];
            if (is_array($details)) {
                foreach ($details as $detailRow) {
                    if (!is_array($detailRow)) {
                        continue;
                    }

                    $fieldLabel = trim((string) ($detailRow['field_label'] ?? ''));
                    if ($fieldLabel === '') {
                        $fieldLabel = (string) ($detailRow['field_key'] ?? '');
                    }

                    $valueType = strtoupper(trim((string) ($detailRow['value_type'] ?? '')));
                    if ($valueType === 'JSON') {
                        $beforeRaw = $detailRow['before_value_json'] ?? null;
                        $afterRaw  = $detailRow['after_value_json'] ?? null;
                        $beforeValue = $beforeRaw !== null ? (string) json_encode(json_decode((string) $beforeRaw), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
                        $afterValue  = $afterRaw  !== null ? (string) json_encode(json_decode((string) $afterRaw),  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
                    } else {
                        $beforeValue = trim((string) ($detailRow['before_value_text'] ?? ''));
                        $afterValue  = trim((string) ($detailRow['after_value_text'] ?? ''));
                    }
                    if ($beforeValue === '') {
                        $beforeValue = '未設定';
                    }
                    if ($afterValue === '') {
                        $afterValue = '未設定';
                    }

                    $detailsHtml .= '<tr>'
                        . '<td>' . Layout::escape($fieldLabel) . '</td>'
                        . '<td>' . Layout::escape($beforeValue) . '</td>'
                        . '<td>' . Layout::escape($afterValue) . '</td>'
                        . '</tr>';
                }
            }

            if ($detailsHtml !== '') {
                $detailsHtml = '<div class="history-detail-table-wrap"><table class="history-detail-table"><thead><tr><th>変更項目</th><th>変更前</th><th>変更後</th></tr></thead><tbody>' . $detailsHtml . '</tbody></table></div>';
            }

            $summary = trim((string) ($row['note'] ?? ''));
            if ($summary === '') {
                $summary = '満期対応情報を更新';
            }

            $auditsHtml .= '<li class="history-item">'
                . '<div class="history-meta">'
                . '<span>' . Layout::escape($changedAt) . '</span>'
                . '<span>変更者: ' . Layout::escape($changedBy) . '</span>'
                . '<span>変更種別: ' . Layout::escape(self::auditActionLabel((string) ($row['action_type'] ?? ''))) . '</span>'
                . '<span>変更元: ' . Layout::escape(self::auditSourceLabel((string) ($row['change_source'] ?? ''))) . '</span>'
                . '</div>'
                . '<div class="history-summary">変更内容: ' . Layout::escape($summary) . '</div>'
                . $detailsHtml
                . '</li>';
        }
        if ($auditsHtml === '') {
            $auditsHtml = '<li class="muted">0件</li>';
        }

        $renewalCaseId = (int) ($detail['renewal_case_id'] ?? 0);
        $customerUrl = Layout::escape(
            $customerDetailBaseUrl
            . '&id=' . (string) ($detail['customer_id'] ?? '0')
            . '&return_to=' . urlencode('renewal/detail?id=' . $renewalCaseId)
        );
        $statusBadge = self::renderStatusBadge((string) ($detail['case_status'] ?? 'open'));
        $nextActionHtml = self::renderNextAction((string) ($detail['next_action_date'] ?? ''), (string) ($detail['case_status'] ?? 'open'));
        $address = trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')));
        $contractStatus = self::contractStatusLabel((string) ($detail['contract_status'] ?? ''));
        $assignedUserId = trim((string) ($detail['assigned_user_id'] ?? ''));
        $assignedUserName = trim((string) ($detail['assigned_user_name'] ?? ''));

        $premiumRaw = (string) ($detail['premium_amount'] ?? '');
        $premiumText = $premiumRaw === '' || !is_numeric($premiumRaw)
            ? '未設定'
            : number_format((int) $premiumRaw) . ' 円';

        $statusClass = isset($fieldErrors['case_status']) ? ' input-error' : '';
        $nextActionClass = isset($fieldErrors['next_action_date']) ? ' input-error' : '';
        $resultClass = isset($fieldErrors['renewal_result']) ? ' input-error' : '';
        $renewalMethodClass = isset($fieldErrors['renewal_method']) ? ' input-error' : '';
        $procedureMethodClass = isset($fieldErrors['procedure_method']) ? ' input-error' : '';
        $lostReasonClass = isset($fieldErrors['lost_reason']) ? ' input-error' : '';
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
        $officeUserName = trim((string) ($detail['office_user_name'] ?? ''));
        $officeUserId   = trim((string) ($detail['office_user_id'] ?? ''));
        $officeUserHtml = $officeUserName !== '' ? Layout::escape($officeUserName) : ($officeUserId !== '' ? Layout::escape($officeUserId) : '<span class="muted">未設定</span>');

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
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '<a class="btn btn-ghost" href="' . $customerUrl . '">顧客詳細を見る</a>'
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
            . '<div class="kv"><span class="kv-key">種目</span><span class="kv-val">' . $productType . '</span></div>'
            . '<div class="kv"><span class="kv-key">満期日</span><span class="kv-val"' . $maturityDateStyle . '>' . Layout::escape($maturityDate) . '</span></div>'
            . '<div class="kv"><span class="kv-key">早期更改締切</span><span class="kv-val">' . $earlyDeadlineHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">始期日</span><span class="kv-val">' . Layout::escape((string) ($detail['policy_start_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険料</span><span class="kv-val">' . Layout::escape($premiumText) . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険会社</span><span class="kv-val">' . Layout::escape((string) ($detail['insurer_name'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">契約状態</span><span class="kv-val">' . Layout::escape($contractStatus) . '</span></div>'
            . '<div class="kv"><span class="kv-key">営業担当</span><span class="kv-val">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : ($assignedUserId !== '' ? Layout::escape($assignedUserId) : '<span class="muted">未設定</span>')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">事務担当</span><span class="kv-val">' . $officeUserHtml . '</span></div>'
            . '<div class="kv"><span class="kv-key">備考</span><span class="kv-val">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</span></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">対応状況の更新</div>'
            . '<form id="renewal-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . self::renderHiddenInputs($listStateParams)
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
            . '<div class="form-label">更改結果</div>'
            . '<select id="renewal-result-field" class="form-select' . $resultClass . '" name="renewal_result">' . $resultHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'renewal_result')
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
            . '<div class="form-row">'
            . '<div class="form-label">失注理由</div>'
            . '<input type="text" id="renewal-lost-reason" class="form-input' . $lostReasonClass . '" name="lost_reason" value="' . Layout::escape((string) ($detail['lost_reason'] ?? '')) . '">'
            . self::renderFieldError($fieldErrors, 'lost_reason')
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">備考</div>'
            . '<textarea id="renewal-remark" class="form-input" name="remark" rows="4">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea>'
            . '</div>'
            . '<button class="btn btn-primary" type="submit" style="width:100%;">更新を保存</button>'
            . '</form>'
            . '</div>'
            . '</div>'
            // ── 右カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title">顧客情報（参照）</div>'
            . '<div class="kv"><span class="kv-key">氏名</span><span class="kv-val"><a class="kv-link" href="' . $customerUrl . '">' . $customerName . '</a></span></div>'
            . '<div class="kv"><span class="kv-key">主担当者</span><span class="kv-val">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : ($assignedUserId !== '' ? Layout::escape($assignedUserId) : '<span class="muted">未設定</span>')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">電話</span><span class="kv-val">' . Layout::escape((string) ($detail['phone'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">メール</span><span class="kv-val">' . Layout::escape((string) ($detail['email'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">住所</span><span class="kv-val">' . Layout::escape($address) . '</span></div>'
            . '<div class="card-footer-link"><a class="kv-link" href="' . $customerUrl . '">顧客詳細を見る →</a></div>'
            . '</div>'
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
            . '<div class="details-compact-body"><ul class="panel-list">' . $auditsHtml . '</ul></div>'
            . '</details>'
            . '</div>'
            . '</div>';

        return Layout::render('満期詳細', $content, $layoutOptions);
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'not_started'    => '未対応',
            'sj_requested'   => 'SJ依頼中',
            'doc_prepared'   => '書類作成済',
            'waiting_return' => '返送待ち',
            'quote_sent'     => '見積送付済',
            'waiting_payment' => '入金待ち',
            'completed'      => '完了',
            default => '未設定',
        };
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

    private static function renderStatusBadge(string $status): string
    {
        $class = match ($status) {
            'completed' => 'badge-success',
            'sj_requested', 'doc_prepared', 'waiting_return', 'quote_sent', 'waiting_payment' => 'badge-info',
            default => 'badge-danger',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape(self::statusLabel($status)) . '</span>';
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

    private static function auditActionLabel(string $actionType): string
    {
        return match ($actionType) {
            'INSERT' => '登録',
            'UPDATE' => '更新',
            'DELETE' => '削除',
            'IMPORT' => '取込',
            'SYSTEM_UPDATE' => 'システム更新',
            default => $actionType,
        };
    }

    private static function auditSourceLabel(string $source): string
    {
        return match ($source) {
            'SCREEN' => '画面',
            'SJNET_IMPORT' => 'SJNET取込',
            'BATCH' => 'バッチ',
            'API' => 'API',
            default => $source,
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
