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
     */
    public static function render(
        array $detail,
        array $comments,
        array $audits,
        string $listUrl,
        string $updateUrl,
        string $commentUrl,
        string $updateCsrf,
        string $commentCsrf,
        ?string $flashError,
        ?string $flashSuccess
    ): string {
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $statusOptions = ['accepted', 'linked', 'in_progress', 'waiting_docs', 'resolved', 'closed'];
        $statusHtml = '';
        $currentStatus = (string) ($detail['status'] ?? 'accepted');
        foreach ($statusOptions as $status) {
            $selected = $status === $currentStatus ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($status) . '"' . $selected . '>' . Layout::escape($status) . '</option>';
        }

        $priorityOptions = ['low', 'normal', 'high', 'urgent'];
        $priorityHtml = '';
        $currentPriority = (string) ($detail['priority'] ?? 'normal');
        foreach ($priorityOptions as $priority) {
            $selected = $priority === $currentPriority ? ' selected' : '';
            $priorityHtml .= '<option value="' . Layout::escape($priority) . '"' . $selected . '>' . Layout::escape($priority) . '</option>';
        }

        $commentsHtml = '';
        foreach ($comments as $row) {
            $commentsHtml .= '<li>'
                . Layout::escape((string) ($row['created_at'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['comment_body'] ?? ''))
                . '</li>';
        }
        if ($commentsHtml === '') {
            $commentsHtml = '<li>コメントなし</li>';
        }

        $auditsHtml = '';
        foreach ($audits as $row) {
            $auditsHtml .= '<li>'
                . Layout::escape((string) ($row['changed_at'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['action_type'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['change_source'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['note'] ?? ''))
                . '</li>';
        }
        if ($auditsHtml === '') {
            $auditsHtml = '<li>監査ログなし</li>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">事故案件詳細</h1>'
            . '<p class="muted">管理者向け補助導線です。詳細で確認・更新します。</p>'
            . '<p><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></p>'
            . $errorHtml
            . $successHtml
            . '<p><strong>事故管理番号:</strong> ' . Layout::escape((string) ($detail['accident_no'] ?? '')) . '</p>'
            . '<p><strong>顧客名:</strong> ' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</p>'
            . '<p><strong>証券番号:</strong> ' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</p>'
            . '<p><strong>事故受付日:</strong> ' . Layout::escape((string) ($detail['accepted_date'] ?? '')) . '</p>'
            . '<p><strong>事故発生日:</strong> ' . Layout::escape((string) ($detail['accident_date'] ?? '')) . '</p>'
            . '<p><strong>種目:</strong> ' . Layout::escape((string) ($detail['product_type'] ?? '')) . '</p>'
            . '<p><strong>事故概要:</strong> ' . Layout::escape((string) ($detail['accident_summary'] ?? '')) . '</p>'
            . '</div>'
            . '<div class="card">'
            . '<h2>案件更新</h2>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['id'] ?? '0')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<div class="grid">'
            . '<label>状態<select name="status">' . $statusHtml . '</select></label>'
            . '<label>優先度<select name="priority">' . $priorityHtml . '</select></label>'
            . '<label>主担当者ID<input type="number" min="1" step="1" name="assigned_user_id" value="' . Layout::escape((string) ($detail['assigned_user_id'] ?? '')) . '"></label>'
            . '<label>解決日<input type="date" name="resolved_date" value="' . Layout::escape((string) ($detail['resolved_date'] ?? '')) . '"></label>'
            . '<label>保険会社事故受付番号<input type="text" name="insurer_claim_no" value="' . Layout::escape((string) ($detail['insurer_claim_no'] ?? '')) . '"></label>'
            . '</div>'
            . '<label style="display:block;margin-top:12px;">備考<textarea name="remark" rows="4" style="width:100%;">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea></label>'
            . '<div style="margin-top:12px;"><button class="btn" type="submit">保存</button></div>'
            . '</form>'
            . '</div>'
            . '<div class="card">'
            . '<h2>コメント</h2>'
            . '<form method="post" action="' . Layout::escape($commentUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['id'] ?? '0')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
            . '<label style="display:block;">コメント本文<textarea name="comment_body" rows="3" style="width:100%;" required></textarea></label>'
            . '<div style="margin-top:12px;"><button class="btn" type="submit">コメント登録</button></div>'
            . '</form>'
            . '<ul>' . $commentsHtml . '</ul>'
            . '</div>'
            . '<div class="card"><h2>監査ログ</h2><ul>' . $auditsHtml . '</ul></div>';

        return Layout::render('事故案件詳細', $content);
    }
}
