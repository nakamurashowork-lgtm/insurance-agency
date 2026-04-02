<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class ActivityDailyView
{
    /**
     * @param array<int, array<string, mixed>> $activities
     * @param array<string, mixed>|null $dailyReport
     * @param array<int, array{id:int, name:string}> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        string $date,
        int $staffUserId,
        string $staffDisplayName,
        string $prevDate,
        string $nextDate,
        array $activities,
        ?array $dailyReport,
        array $staffUsers,
        string $listUrl,
        string $dailyBaseUrl,
        string $commentUrl,
        string $activityDetailBaseUrl,
        string $customerDetailBaseUrl,
        string $commentCsrf,
        int $loginUserId,
        string $submitUrl,
        string $submitCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $allowedActivityTypes,
        array $layoutOptions
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }
        if (is_string($errorMessage) && $errorMessage !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $prevUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $prevDate, 'staff' => (string) $staffUserId]));
        $nextUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $nextDate, 'staff' => (string) $staffUserId]));

        // 担当者切替セレクト
        $staffOptionsHtml = '';
        foreach ($staffUsers as $user) {
            $uid   = (int) ($user['id'] ?? 0);
            $uname = (string) ($user['name'] ?? '');
            $sel   = $staffUserId === $uid ? ' selected' : '';
            $staffOptionsHtml .= '<option value="' . $uid . '"' . $sel . '>' . Layout::escape($uname) . '</option>';
        }

        // 活動一覧
        $activitiesHtml = '';
        foreach ($activities as $row) {
            $actId     = (int) ($row['id'] ?? 0);
            $custId    = (int) ($row['customer_id'] ?? 0);
            $custName  = (string) ($row['customer_name'] ?? '');
            $startTime = (string) ($row['start_time'] ?? '');
            $endTime   = (string) ($row['end_time'] ?? '');
            $type      = (string) ($row['activity_type'] ?? '');
            $typeLabel = $allowedActivityTypes[$type] ?? Layout::escape($type);
            $subject   = (string) ($row['subject'] ?? '');
            $summary   = (string) ($row['content_summary'] ?? '');
            $nextDate2 = (string) ($row['next_action_date'] ?? '');

            $timeStr = $startTime !== '' ? Layout::escape($startTime) : '';
            if ($timeStr !== '' && $endTime !== '') {
                $timeStr .= '〜' . Layout::escape($endTime);
            }

            $detailUrl = $actId > 0
                ? Layout::escape(ListViewHelper::buildUrl($activityDetailBaseUrl, ['id' => (string) $actId]))
                : '';
            $custUrl = $custId > 0
                ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId]))
                : '';

            $custHtml = $custUrl !== ''
                ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                : Layout::escape($custName);

            $activitiesHtml .=
                '<tr>'
                . '<td style="white-space:nowrap;">' . $timeStr . '</td>'
                . '<td>' . Layout::escape($typeLabel) . '</td>'
                . '<td>' . $custHtml . '</td>'
                . '<td><div class="list-row-stack">'
                . '<div class="list-row-primary">' . Layout::escape($subject) . '</div>'
                . '<div class="list-row-secondary">' . Layout::escape(mb_strimwidth($summary, 0, 60, '…')) . '</div>'
                . '</div></td>'
                . '<td>' . ($nextDate2 !== '' ? Layout::escape($nextDate2) : '') . '</td>'
                . '<td>' . ($detailUrl !== '' ? '<a href="' . $detailUrl . '" class="btn btn-small btn-ghost">詳細</a>' : '') . '</td>'
                . '</tr>';
        }

        if ($activitiesHtml === '') {
            $activitiesHtml = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#627d98;">この日の活動記録はありません。</td></tr>';
        }

        $existingComment = (string) ($dailyReport['comment'] ?? '');
        $isSubmitted     = (int) ($dailyReport['is_submitted'] ?? 0) === 1;
        $submittedAt     = (string) ($dailyReport['submitted_at'] ?? '');
        $isOwnReport     = ($loginUserId === $staffUserId);

        // 日報コメントセクション（提出済みは読み取り専用）
        if ($isSubmitted) {
            $commentSectionHtml =
                '<div class="card">'
                . '<h2 style="margin:0 0 12px;font-size:16px;">日報コメント</h2>'
                . '<textarea rows="5" readonly style="width:100%;padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;background:#f5f7fa;color:#52606d;">'
                . Layout::escape($existingComment)
                . '</textarea>'
                . '</div>';
        } else {
            $commentSectionHtml =
                '<div class="card">'
                . '<h2 style="margin:0 0 12px;font-size:16px;">日報コメント</h2>'
                . '<form method="post" action="' . Layout::escape($commentUrl) . '">'
                . '<input type="hidden" name="route" value="activity/comment">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
                . '<input type="hidden" name="report_date" value="' . Layout::escape($date) . '">'
                . '<input type="hidden" name="staff_user_id" value="' . $staffUserId . '">'
                . '<textarea name="comment" rows="5" style="width:100%;padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;">'
                . Layout::escape($existingComment)
                . '</textarea>'
                . '<div class="actions" style="margin-top:10px;">'
                . '<button type="submit" class="btn btn-primary">コメントを保存</button>'
                . '</div>'
                . '</form>'
                . '</div>';
        }

        // 提出セクション
        if ($isSubmitted) {
            $submitSectionHtml =
                '<div class="card" style="background:#f0fdf4;border-color:#86efac;">'
                . '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
                . '<span style="color:#166534;font-weight:700;font-size:15px;">✓ 提出済み</span>'
                . ($submittedAt !== '' ? '<span style="color:#52606d;font-size:13px;">' . Layout::escape($submittedAt) . '</span>' : '')
                . '</div>'
                . '</div>';
        } elseif ($isOwnReport && $submitUrl !== '' && $submitCsrf !== '') {
            $submitSectionHtml =
                '<div class="card">'
                . '<h2 style="margin:0 0 8px;font-size:15px;">日報の提出</h2>'
                . '<p style="margin:0 0 12px;font-size:13px;color:#52606d;">提出後はコメントの編集・再提出はできません。</p>'
                . '<form method="post" action="' . Layout::escape($submitUrl) . '">'
                . '<input type="hidden" name="route" value="activity/submit">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($submitCsrf) . '">'
                . '<input type="hidden" name="report_date" value="' . Layout::escape($date) . '">'
                . '<input type="hidden" name="staff_user_id" value="' . $staffUserId . '">'
                . '<button type="submit" class="btn btn-primary"'
                . ' onclick="return confirm(\'日報を提出します。提出後は取り消せません。よろしいですか？\')">日報を提出する</button>'
                . '</form>'
                . '</div>';
        } else {
            $submitSectionHtml = '';
        }

        $content =
            '<div style="max-width:900px;">'
            . '<div class="list-page-header" style="margin-bottom:16px;">'
            . '<h1 class="title">日報ビュー</h1>'
            . '<div class="list-page-header-actions">'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-ghost btn-small">活動一覧に戻る</a>'
            . '</div>'
            . '</div>'
            . $noticeHtml

            // 日付ナビゲーション
            . '<div class="card" style="margin-bottom:16px;">'
            . '<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">'
            . '<a href="' . $prevUrl . '" class="btn btn-ghost btn-small">← 前日</a>'
            . '<strong style="font-size:18px;">' . Layout::escape($date) . '</strong>'
            . '<a href="' . $nextUrl . '" class="btn btn-ghost btn-small">翌日 →</a>'
            . '<span style="color:#52606d;font-size:14px;">担当：</span>'
            . '<form method="get" action="" style="display:inline-flex;align-items:center;gap:8px;">'
            . '<input type="hidden" name="route" value="activity/daily">'
            . '<input type="hidden" name="date" value="' . Layout::escape($date) . '">'
            . '<select name="staff" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;">'
            . $staffOptionsHtml
            . '</select>'
            . '</form>'
            . '</div>'
            . '</div>'

            // 活動一覧
            . '<div class="card" style="padding:0;overflow:hidden;margin-bottom:16px;">'
            . '<div style="padding:14px 16px;border-bottom:1px solid #d9e2ec;">'
            . '<h2 style="margin:0;font-size:16px;">当日の活動（' . count($activities) . '件）</h2>'
            . '</div>'
            . '<div style="overflow-x:auto;">'
            . '<table class="list-table" style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="width:110px;">時刻</th><th style="width:80px;">種別</th><th style="width:18%;">顧客</th>'
            . '<th>件名 / 内容要約</th><th style="width:110px;">次回予定日</th><th style="width:60px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $activitiesHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'

            // 日報コメント
            . $commentSectionHtml

            // 提出セクション
            . $submitSectionHtml
            . '</div>';

        return Layout::render('日報ビュー（' . $date . '）', $content, $layoutOptions);
    }
}
