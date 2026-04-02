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
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
          array $assignedUsers,
        string $listUrl,
        string $updateUrl,
        string $commentUrl,
        string $updateCsrf,
        string $commentCsrf,
        string $returnToUrl,
        ?string $flashError,
        ?string $flashSuccess,
        array $layoutOptions
    ): string {
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $statusLabels = [
            'accepted'     => '受付',
            'linked'       => '対応開始',
            'in_progress'  => '対応中',
            'waiting_docs' => '保留',
            'resolved'     => '解決',
            'closed'       => 'クローズ',
        ];
        $statusHtml = '';
        $currentStatus = (string) ($detail['status'] ?? 'accepted');
        foreach ($statusLabels as $value => $label) {
            $selected = $value === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $priorityLabels = [
            'low'    => '低',
            'normal' => '通常',
            'high'   => '高',
            'urgent' => '急',
        ];
        $priorityHtml = '';
        $currentPriority = (string) ($detail['priority'] ?? 'normal');
        foreach ($priorityLabels as $value => $label) {
            $selected = $value === $currentPriority ? ' selected' : '';
            $priorityHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $assignedUserId = (int) ($detail['assigned_user_id'] ?? 0);
        $assignedUserOptions = '<option value="">未設定</option>';

        $selectedExists = false;
        foreach ($assignedUsers as $user) {
            $id = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $selected = $assignedUserId === $id ? ' selected' : '';
            if ($selected !== '') {
                $selectedExists = true;
            }

            $assignedUserOptions .= '<option value="' . Layout::escape((string) $id) . '"' . $selected . '>'
                . Layout::escape($name)
                . '</option>';
        }

        if ($assignedUserId > 0 && !$selectedExists) {
            $assignedUserOptions .= '<option value="' . Layout::escape((string) $assignedUserId) . '" selected>'
                . '現在の担当者（マスタ外）'
                . '</option>';
        }

        $commentsHtml = '';
        foreach ($comments as $row) {
            $authorLabel = trim((string) ($row['author_name'] ?? ''));
            if ($authorLabel === '') {
                $authorLabel = '不明なユーザー';
            }
            $postedAt = self::formatDate((string) ($row['created_at'] ?? ''));
            $commentsHtml .= '<li class="comment-item">'
                . '<div class="comment-meta">'
                . '<span class="comment-meta-text">' . Layout::escape($authorLabel . ' ・ ' . $postedAt) . '</span>'
                . '</div>'
                . '<div class="comment-body">' . Layout::escape((string) ($row['comment_body'] ?? '')) . '</div>'
                . '</li>';
        }
        if ($commentsHtml === '') {
            $commentsHtml = '<li class="muted">0件</li>';
        }

        $actionLabels = [
            'INSERT'        => '登録',
            'UPDATE'        => '更新',
            'DELETE'        => '削除',
            'IMPORT'        => '取込',
            'SYSTEM_UPDATE' => 'システム更新',
        ];
        $sourceLabels = [
            'SCREEN'       => '画面操作',
            'SJNET_IMPORT' => 'SJネット取込',
            'BATCH'        => 'バッチ処理',
            'API'          => 'API',
        ];

        $auditsHtml = '';
        foreach ($audits as $row) {
            $changedAt = self::formatDate((string) ($row['changed_at'] ?? ''));
            $changedBy = trim((string) ($row['changed_by_name'] ?? ''));
            if ($changedBy === '') {
                $changedBy = '不明なユーザー';
            }
            $actionLabel = $actionLabels[strtoupper((string) ($row['action_type'] ?? ''))] ?? (string) ($row['action_type'] ?? '');
            $sourceLabel = $sourceLabels[strtoupper((string) ($row['change_source'] ?? ''))] ?? (string) ($row['change_source'] ?? '');
            $note = trim((string) ($row['note'] ?? ''));

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

            $auditsHtml .= '<li class="history-item">'
                . '<div class="history-meta">'
                . '<span>' . Layout::escape($changedAt) . '</span>'
                . '<span>変更者: ' . Layout::escape($changedBy) . '</span>'
                . '<span>変更種別: ' . Layout::escape($actionLabel) . '</span>'
                . '<span>変更元: ' . Layout::escape($sourceLabel) . '</span>'
                . '</div>'
                . ($note !== '' ? '<div class="history-summary">内容: ' . Layout::escape($note) . '</div>' : '')
                . $detailsHtml
                . '</li>';
        }
        if ($auditsHtml === '') {
            $auditsHtml = '<li class="muted">0件</li>';
        }

        $statusBadgeClass = match ($currentStatus) {
            'resolved', 'closed' => 'status-done',
            'linked', 'in_progress', 'waiting_docs' => 'status-progress',
            default => 'status-open',
        };
        $statusBadge = '<span class="status-badge ' . $statusBadgeClass . '">' . Layout::escape($statusLabels[$currentStatus] ?? $currentStatus) . '</span>';

        $content = ''
            . '<div class="card">'
            . '<div class="section-head">'
            . '<div>'
            . '<h1 class="title">事故案件詳細</h1>'
            . '<div class="meta-row">'
            . $statusBadge
            . '<span class="tag">事故受付日: ' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</span>'
            . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '<button class="btn" type="submit" form="accident-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . $errorHtml
            . $successHtml
            . '</div>'
            . '<div class="detail-top">'
            . '<div class="card">'
            . '<h2>案件情報</h2>'
            . '<dl class="kv-list">'
            . '<dt>事故管理番号</dt><dd>' . Layout::escape((string) ($detail['accident_no'] ?? '')) . '</dd>'
            . '<dt>顧客名</dt><dd>' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</dd>'
            . '<dt>証券番号</dt><dd>' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</dd>'
            . '<dt>種目</dt><dd>' . Layout::escape((string) ($detail['product_type'] ?? '')) . '</dd>'
            . '<dt>事故受付日</dt><dd>' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</dd>'
            . '<dt>事故発生日</dt><dd>' . Layout::escape((string) ($detail['accident_date'] ?? '')) . '</dd>'
            . '<dt>事故概要</dt><dd>' . Layout::escape((string) ($detail['accident_summary'] ?? '')) . '</dd>'
            . '<dt>保険会社受付番号</dt><dd>' . (((string) ($detail['insurer_claim_no'] ?? '')) === '' ? '<span class="muted">未設定</span>' : Layout::escape((string) ($detail['insurer_claim_no'] ?? ''))) . '</dd>'
            . '<dt>解決日</dt><dd>' . (((string) ($detail['resolved_date'] ?? '')) === '' ? '<span class="muted">未設定</span>' : Layout::escape((string) ($detail['resolved_date'] ?? ''))) . '</dd>'
            . '<dt>備考</dt><dd>' . (((string) ($detail['remark'] ?? '')) === '' ? '<span class="muted">未設定</span>' : Layout::escape((string) ($detail['remark'] ?? ''))) . '</dd>'
            . '</dl>'
            . '</div>'
            . '<div class="detail-side">'
            . '<div class="card">'
            . '<h2>対応状況更新</h2>'
            . '<form id="accident-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['id'] ?? '0')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<div class="renewal-update-grid">'
            . '<div class="update-field"><label for="accident-status">状態 <strong class="required-mark">*</strong></label><select id="accident-status" name="status" required>' . $statusHtml . '</select></div>'
            . '<div class="update-field"><label for="accident-priority">優先度</label><select id="accident-priority" name="priority">' . $priorityHtml . '</select></div>'
            . '<div class="update-field"><label for="accident-assigned">担当者</label><select id="accident-assigned" name="assigned_user_id">' . $assignedUserOptions . '</select></div>'
            . '<div class="update-field"><label for="accident-resolved-date">解決日</label><input type="date" id="accident-resolved-date" name="resolved_date" value="' . Layout::escape((string) ($detail['resolved_date'] ?? '')) . '"></div>'
            . '<div class="update-field"><label for="accident-insurer-no">保険会社受付番号</label><input type="text" id="accident-insurer-no" name="insurer_claim_no" value="' . Layout::escape((string) ($detail['insurer_claim_no'] ?? '')) . '"></div>'
            . '<div class="update-field update-field-full"><label for="accident-remark">備考</label><textarea id="accident-remark" name="remark" rows="4">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea></div>'
            . '</div>'
            . '<div class="renewal-update-actions"><button class="btn btn-primary" type="submit">更新を保存</button></div>'
            . '</form>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="section-stack">'
            . '<details class="card details-panel details-compact">'
            . '<summary><span>コメント</span><span class="muted">' . count($comments) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '" style="margin:0 0 12px;">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['id'] ?? '0')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<label style="display:block;">新規コメント<textarea name="comment_body" rows="3" style="width:100%;margin-top:6px;" required></textarea></label>'
            . '<div class="actions" style="margin-top:10px;"><button class="btn btn-small" type="submit">コメント追加</button></div>'
            . '</form>'
            . '<ul class="panel-list">' . $commentsHtml . '</ul>'
            . '</div>'
            . '</details>'
            . '<details class="card details-panel details-compact">'
            . '<summary><span>変更履歴（監査ログ）</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body"><ul class="panel-list">' . $auditsHtml . '</ul></div>'
            . '</details>'
            . '</div>';

        return Layout::render('事故案件詳細', $content, $layoutOptions);
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
}
