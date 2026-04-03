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
        string $customerDetailBaseUrl,
        string $renewalDetailBaseUrl,
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
        $currentStatus = (string) ($detail['status'] ?? 'accepted');
        $statusHtml = '';
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
        $currentPriority = (string) ($detail['priority'] ?? 'normal');
        $priorityHtml = '';
        foreach ($priorityLabels as $value => $label) {
            $selected = $value === $currentPriority ? ' selected' : '';
            $priorityHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $assignedUserId = (int) ($detail['assigned_user_id'] ?? 0);
        $assignedUserOptions = '<option value="">未設定</option>';
        $selectedExists = false;
        foreach ($assignedUsers as $user) {
            $uid = (int) ($user['id'] ?? 0);
            $uname = trim((string) ($user['name'] ?? ''));
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
                $assignedName = (string) ($user['name'] ?? '');
                break;
            }
        }

        // ステータスバッジ
        $statusBadgeClass = match ($currentStatus) {
            'resolved', 'closed' => 'badge-success',
            'linked', 'in_progress' => 'badge-info',
            'waiting_docs' => 'badge-danger',
            default => 'badge-danger',
        };
        $statusBadge = '<span class="badge ' . $statusBadgeClass . '">' . Layout::escape($statusLabels[$currentStatus] ?? $currentStatus) . '</span>';

        // タイトル
        $customerName = (string) ($detail['customer_name'] ?? '');
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

        // 変更履歴
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
            $changedBy = trim((string) ($row['changed_by_name'] ?? '')) ?: '不明なユーザー';
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
                    $fieldLabel = trim((string) ($detailRow['field_label'] ?? '')) ?: (string) ($detailRow['field_key'] ?? '');
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
                    $beforeValue = $beforeValue !== '' ? $beforeValue : '未設定';
                    $afterValue  = $afterValue  !== '' ? $afterValue  : '未設定';

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

        $content = $errorHtml
            . $successHtml
            // ── ページヘッダー ──
            . '<div class="page-header">'
            . '<div>'
            . '<h1 class="title">' . $pageTitle . '</h1>'
            . '<div style="margin-top:4px;">' . $statusBadge . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '<button class="btn btn-primary" type="submit" form="accident-update-form">保存</button>'
            . '</div>'
            . '</div>'
            // ── 2カラム ──
            . '<div class="two-col">'
            // ── 左カラム ──
            . '<div>'
            // 事故基本情報
            . '<div class="card">'
            . '<div class="detail-section-title">事故基本情報</div>'
            . '<div class="kv"><span class="kv-key">顧客</span><span class="kv-val">' . $customerCell . '</span></div>'
            . '<div class="kv"><span class="kv-key">関連契約</span><span class="kv-val">' . $contractCell . '</span></div>'
            . '<div class="kv"><span class="kv-key">受付日</span><span class="kv-val">' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">事故発生日</span><span class="kv-val">' . (trim((string) ($detail['accident_date'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_date']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険種類</span><span class="kv-val">' . ($insuranceCategory !== '' ? Layout::escape($insuranceCategory) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">種目</span><span class="kv-val">' . ($productType !== '' ? Layout::escape($productType) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">事故種別</span><span class="kv-val">' . (trim((string) ($detail['accident_type'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_type']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">事故場所</span><span class="kv-val">' . (trim((string) ($detail['accident_location'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_location']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">担当者</span><span class="kv-val">' . ($assignedName !== '' ? Layout::escape($assignedName) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">保険会社受付番号</span><span class="kv-val">' . (trim((string) ($detail['insurer_claim_no'] ?? '')) !== '' ? Layout::escape((string) $detail['insurer_claim_no']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">解決日</span><span class="kv-val">' . (trim((string) ($detail['resolved_date'] ?? '')) !== '' ? Layout::escape((string) $detail['resolved_date']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">備考</span><span class="kv-val">' . (trim((string) ($detail['remark'] ?? '')) !== '' ? Layout::escape((string) $detail['remark']) : '<span class="muted">未設定</span>') . '</span></div>'
            . '</div>'
            // 対応状況更新フォーム
            . '<div class="card">'
            . '<div class="detail-section-title">対応状況を更新</div>'
            . '<form id="accident-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . (int) ($detail['id'] ?? 0) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<div class="form-row"><div class="form-label">状態 <strong class="required-mark">*</strong></div><select class="form-select" name="status" required>' . $statusHtml . '</select></div>'
            . '<div class="form-row"><div class="form-label">優先度</div><select class="form-select" name="priority">' . $priorityHtml . '</select></div>'
            . '<div class="form-row"><div class="form-label">担当者</div><select class="form-select" name="assigned_user_id">' . $assignedUserOptions . '</select></div>'
            . '<div class="form-row"><div class="form-label">解決日</div><input class="form-input" type="date" name="resolved_date" value="' . Layout::escape((string) ($detail['resolved_date'] ?? '')) . '"></div>'
            . '<div class="form-row"><div class="form-label">保険会社受付番号</div><input class="form-input" type="text" name="insurer_claim_no" value="' . Layout::escape((string) ($detail['insurer_claim_no'] ?? '')) . '" maxlength="100"></div>'
            . '<div class="form-row"><div class="form-label">備考</div><textarea class="form-input" name="remark" rows="4">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea></div>'
            . '<div class="dialog-actions"><button class="btn btn-primary" type="submit">保存する</button></div>'
            . '</form>'
            . '</div>'
            . '</div>'
            // ── 右カラム ──
            . '<div>'
            // コメント
            . '<details class="card details-panel details-compact" open>'
            . '<summary><span>コメント</span><span class="muted">' . count($comments) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '" style="margin:0 0 12px;">'
            . '<input type="hidden" name="id" value="' . (int) ($detail['id'] ?? 0) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnToUrl) . '">'
            . '<label style="display:block;">新規コメント<textarea name="comment_body" rows="3" style="width:100%;margin-top:6px;" required></textarea></label>'
            . '<div class="actions" style="margin-top:10px;"><button class="btn btn-small" type="submit">コメント追加</button></div>'
            . '</form>'
            . '<ul class="panel-list">' . $commentsHtml . '</ul>'
            . '</div>'
            . '</details>'
            // 変更履歴
            . '<details class="card details-panel details-compact">'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body"><ul class="panel-list">' . $auditsHtml . '</ul></div>'
            . '</details>'
            . '</div>'
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
