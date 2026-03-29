<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class RenewalCaseDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $activities
     * @param array<int, array<string, mixed>> $comments
     * @param array<int, array<string, mixed>> $audits
     */
    public static function render(
        array $detail,
        array $activities,
        array $comments,
        array $audits,
        string $updateUrl,
        string $listUrl,
        string $customerDetailBaseUrl,
        string $csrfToken,
        ?string $errorMessage,
        ?string $successMessage
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
            $statusHtml .= '<option value="' . Layout::escape($status) . '"' . $selected . '>' . Layout::escape($status) . '</option>';
        }

        $resultOptions = ['', 'pending', 'renewed', 'cancelled', 'lost'];
        $resultHtml = '';
        $currentResult = (string) ($detail['renewal_result'] ?? '');
        foreach ($resultOptions as $result) {
            $selected = $result === $currentResult ? ' selected' : '';
            $label = $result === '' ? '未設定' : $result;
            $resultHtml .= '<option value="' . Layout::escape($result) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $activitiesHtml = '';
        foreach ($activities as $row) {
            $activitiesHtml .= '<li>'
                . Layout::escape((string) ($row['activity_at'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['activity_type'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['detail'] ?? ''))
                . '</li>';
        }
        if ($activitiesHtml === '') {
            $activitiesHtml = '<li>活動履歴はありません。</li>';
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
            $commentsHtml = '<li>コメントはありません。</li>';
        }

        $auditsHtml = '';
        foreach ($audits as $row) {
            $auditsHtml .= '<li>'
                . Layout::escape((string) ($row['changed_at'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['action_type'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['change_source'] ?? ''))
                . '</li>';
        }
        if ($auditsHtml === '') {
            $auditsHtml = '<li>監査ログはありません。</li>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">満期詳細</h1>'
            . '<p class="muted">契約詳細を兼ねる画面です。</p>'
            . $errorHtml
            . $successHtml
            . '<p><strong>顧客名:</strong> ' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</p>'
            . '<p><strong>証券番号:</strong> ' . Layout::escape((string) ($detail['policy_no'] ?? '')) . '</p>'
            . '<p><strong>保険会社:</strong> ' . Layout::escape((string) ($detail['insurer_name'] ?? '')) . '</p>'
            . '<p><strong>種目:</strong> ' . Layout::escape((string) ($detail['product_type'] ?? '')) . '</p>'
            . '<p><strong>満期日:</strong> ' . Layout::escape((string) ($detail['maturity_date'] ?? '')) . '</p>'
            . '<p><strong>電話:</strong> ' . Layout::escape((string) ($detail['phone'] ?? '')) . '</p>'
            . '<p><strong>メール:</strong> ' . Layout::escape((string) ($detail['email'] ?? '')) . '</p>'
            . '<p><strong>住所:</strong> ' . Layout::escape(trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')))) . '</p>'
            . '<div style="margin-top:12px;">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . ' <a class="btn" href="' . Layout::escape($customerDetailBaseUrl . '&id=' . (string) ($detail['customer_id'] ?? '0')) . '">顧客詳細を見る</a>'
            . '</div>'
            . '</div>'
            . '<div class="card">'
            . '<h2>満期対応更新</h2>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="id" value="' . Layout::escape((string) ($detail['renewal_case_id'] ?? '')) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<div class="grid">'
            . '<label>対応ステータス<select name="case_status">' . $statusHtml . '</select></label>'
            . '<label>次回対応予定日<input type="date" name="next_action_date" value="' . Layout::escape((string) ($detail['next_action_date'] ?? '')) . '"></label>'
            . '<label>更改結果<select name="renewal_result">' . $resultHtml . '</select></label>'
            . '<label>失注理由<input type="text" name="lost_reason" value="' . Layout::escape((string) ($detail['lost_reason'] ?? '')) . '"></label>'
            . '</div>'
            . '<label style="display:block;margin-top:12px;">備考<textarea name="remark" rows="4" style="width:100%;">' . Layout::escape((string) ($detail['remark'] ?? '')) . '</textarea></label>'
            . '<div style="margin-top:12px;"><button class="btn" type="submit">保存</button></div>'
            . '</form>'
            . '</div>'
            . '<div class="card"><h2>活動履歴</h2><ul>' . $activitiesHtml . '</ul></div>'
            . '<div class="card"><h2>コメント</h2><ul>' . $commentsHtml . '</ul></div>'
            . '<div class="card"><h2>監査ログ</h2><ul>' . $auditsHtml . '</ul></div>';

        return Layout::render('満期詳細', $content);
    }
}