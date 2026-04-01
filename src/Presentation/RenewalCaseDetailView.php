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

        $statusOptions = ['open', 'contacted', 'quoted', 'waiting', 'renewed', 'lost', 'closed'];
        $statusHtml = '';
        $currentStatus = (string) ($detail['case_status'] ?? 'open');
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

                    $beforeValue = trim((string) ($detailRow['before_value_text'] ?? ''));
                    $afterValue = trim((string) ($detailRow['after_value_text'] ?? ''));
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

        $customerUrl = Layout::escape($customerDetailBaseUrl . '&id=' . (string) ($detail['customer_id'] ?? '0'));
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
        $lostReasonClass = isset($fieldErrors['lost_reason']) ? ' input-error' : '';

        $content = ''
            . '<div class="card">'
            . '<div class="section-head">'
            . '<div>'
            . '<h1 class="title">満期詳細</h1>'
            . '<div class="meta-row">'
            . $statusBadge
            . '<span class="tag">満期日: ' . Layout::escape((string) ($detail['maturity_date'] ?? '')) . '</span>'
            . '<span class="tag">' . $nextActionHtml . '</span>'
            . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '<a class="btn btn-ghost" href="' . $customerUrl . '">顧客詳細を見る</a>'
            . '<button class="btn" type="submit" form="renewal-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . $errorHtml
            . $successHtml
            . '</div>'
            . '<div class="detail-top">'
            . '<div class="card">'
            . '<div class="section-head"><h2>契約情報</h2><span class="tag">証券番号 ' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</span></div>'
            . '<dl class="kv-list">'
            . '<dt>保険会社</dt><dd>' . Layout::escape((string) ($detail['insurer_name'] ?? '')) . '</dd>'
            . '<dt>種目</dt><dd>' . Layout::escape((string) ($detail['product_type'] ?? '')) . '</dd>'
            . '<dt>始期日</dt><dd>' . Layout::escape((string) ($detail['policy_start_date'] ?? '')) . '</dd>'
            . '<dt>契約状態</dt><dd>' . Layout::escape($contractStatus) . '</dd>'
            . '<dt>保険料</dt><dd>' . Layout::escape($premiumText) . '</dd>'
            . '<dt>満期日</dt><dd>' . Layout::escape((string) ($detail['maturity_date'] ?? '')) . '</dd>'
            . '<dt>更改結果</dt><dd>' . Layout::escape(self::resultLabel((string) ($detail['renewal_result'] ?? ''))) . '</dd>'
            . '<dt>失注理由</dt><dd>' . Layout::escape((string) ($detail['lost_reason'] ?? '')) . '</dd>'
            . '<dt>備考</dt><dd>' . Layout::escape((string) ($detail['remark'] ?? '')) . '</dd>'
            . '</dl>'
            . '</div>'
            . '<div class="detail-side">'
            . '<div class="card">'
            . '<div class="section-head"><h2>顧客要約</h2></div>'
            . '<dl class="kv-list">'
            . '<dt>顧客名</dt><dd>' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</dd>'
            . '<dt>主担当者</dt><dd>' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : ($assignedUserId !== '' ? Layout::escape($assignedUserId) : '<span class="muted">未設定</span>')) . '</dd>'
            . '<dt>電話</dt><dd>' . Layout::escape((string) ($detail['phone'] ?? '')) . '</dd>'
            . '<dt>メール</dt><dd>' . Layout::escape((string) ($detail['email'] ?? '')) . '</dd>'
            . '<dt>住所</dt><dd>' . Layout::escape($address) . '</dd>'
            . '</dl>'
            . '</div>'
            . '<div class="card">'
            . '<div class="section-head"><h2>満期対応更新</h2></div>'
            . '<form id="renewal-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . self::renderHiddenInputs($listStateParams)
            . '<div class="renewal-update-grid">'
            . '<div class="update-field">'
            . '<label for="renewal-case-status">対応ステータス <strong class="required-mark">*</strong></label>'
            . '<select id="renewal-case-status" class="' . trim($statusClass) . '" name="case_status" required>' . $statusHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'case_status')
            . '</div>'
            . '<div class="update-field">'
            . '<label for="renewal-result-field">更改結果</label>'
            . '<select id="renewal-result-field" class="' . trim($resultClass) . '" name="renewal_result">' . $resultHtml . '</select>'
            . self::renderFieldError($fieldErrors, 'renewal_result')
            . '</div>'
            . '<div class="update-field">'
            . '<label for="renewal-next-action-date">次回対応予定日</label>'
            . '<input type="date" id="renewal-next-action-date" class="' . trim($nextActionClass) . '" name="next_action_date" value="' . Layout::escape((string) ($detail['next_action_date'] ?? '')) . '" placeholder="YYYY-MM-DD">'
            . self::renderFieldError($fieldErrors, 'next_action_date')
            . '</div>'
            . '<div class="update-field">'
            . '<label for="renewal-lost-reason">失注理由</label>'
            . '<input type="text" id="renewal-lost-reason" class="' . trim($lostReasonClass) . '" name="lost_reason" value="' . Layout::escape((string) ($detail['lost_reason'] ?? '')) . '">'
            . self::renderFieldError($fieldErrors, 'lost_reason')
            . '</div>'
            . '<div class="update-field update-field-full">'
            . '<label for="renewal-remark">備考</label>'
            . '<textarea id="renewal-remark" name="remark" rows="4">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea>'
            . '</div>'
            . '</div>'
            . '<div class="renewal-update-actions">'
            . '<button class="btn btn-primary" type="submit">更新を保存</button>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="section-stack">'
            . '<details class="card details-panel details-compact">'
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
            . '<details class="card details-panel details-compact">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body"><ul class="panel-list">' . $auditsHtml . '</ul></div>'
            . '</details>'
            . '</div>';

        return Layout::render('満期詳細', $content, $layoutOptions);
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => '未対応',
            'contacted' => '対応中',
            'quoted' => '見積提示',
            'waiting' => '回答待ち',
            'renewed' => '完了',
            'lost' => '失注',
            'closed' => '終了',
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
            'renewed', 'closed' => 'status-done',
            'contacted', 'quoted', 'waiting' => 'status-progress',
            'lost' => 'status-inactive',
            default => 'status-open',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape(self::statusLabel($status)) . '</span>';
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
        if (!in_array($status, ['renewed', 'lost', 'closed'], true) && $normalized < $today) {
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
