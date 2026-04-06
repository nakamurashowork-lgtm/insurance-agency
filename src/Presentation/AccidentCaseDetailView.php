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
        array $layoutOptions,
        array $allStatuses = []
    ): string {
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        // Build status labels map from master data or fallback
        if ($allStatuses !== []) {
            $statusLabels = [];
            foreach ($allStatuses as $sRow) {
                $statusLabels[(string) ($sRow['code'] ?? '')] = (string) ($sRow['display_name'] ?? '');
            }
        } else {
            $statusLabels = [
                'accepted'     => '受付',
                'linked'       => '対応開始',
                'in_progress'  => '対応中',
                'waiting_docs' => '保留',
                'resolved'     => '解決',
                'closed'       => 'クローズ',
            ];
        }
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

        // 変更履歴 — タイムライン形式
        $statusTranslate = array_merge($statusLabels, [
            'low' => '低', 'normal' => '通常', 'high' => '高', 'urgent' => '急',
        ]);

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
            . '<div class="kv"><span class="kv-key">お客さま名</span><span class="kv-val">' . ($customerUrl !== '' ? '<a class="kv-link" href="' . $customerUrl . '">' . Layout::escape($customerName) . '</a>' : Layout::escape($customerName)) . '</span></div>'
            . '<div class="kv"><span class="kv-key">受付日</span><span class="kv-val">' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">事故発生日</span><span class="kv-val">' . (trim((string) ($detail['accident_date'] ?? '')) !== '' ? Layout::escape((string) $detail['accident_date']) : '<span class="muted">未設定</span>') . '</span></div>'
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
            . '<details class="card details-panel details-compact" open>'
            . '<summary><span>変更履歴</span><span class="muted">' . count($audits) . '件</span></summary>'
            . '<div class="details-compact-body">' . $auditsHtml . '</div>'
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

                $isStatus = $key === 'status';
                $isStaff  = $key === 'assigned_staff_id';

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
}
