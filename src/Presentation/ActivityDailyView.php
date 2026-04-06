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
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $salesCases
     * @param array<int, array<string, mixed>> $purposeTypes
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
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $allowedActivityTypes,
        string $storeUrl,
        string $storeCsrf,
        string $dailyUrl,
        array $customers,
        array $salesCases,
        array $purposeTypes,
        array $layoutOptions,
        string $submitUrl = '',
        string $submitCsrf = '',
        int $loginUserId = 0,
        string $deleteUrl = '',
        string $deleteCsrf = ''
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

        $prevUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $prevDate]));
        $nextUrl = Layout::escape(ListViewHelper::buildUrl($dailyBaseUrl, ['date' => $nextDate]));


        // 活動一覧
        $activitiesHtml = '';
        foreach ($activities as $row) {
            $actId     = (int) ($row['id'] ?? 0);
            $custId    = (int) ($row['customer_id'] ?? 0);
            $custName  = (string) ($row['customer_name'] ?? '');
            $startTime = substr((string) ($row['start_time'] ?? ''), 0, 5);
            $endTime   = substr((string) ($row['end_time'] ?? ''), 0, 5);
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

            $isNullCustomer = ($row['customer_id'] === null || $row['customer_id'] === '');
            $custHtml = $isNullCustomer
                ? '<span style="color:#888;font-size:12px;">（顧客なし）</span>'
                : ($custUrl !== ''
                    ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                    : Layout::escape($custName));

            $deleteBtn = ($deleteUrl !== '' && $actId > 0)
                ? '<button type="button" class="btn btn-small btn-danger" style="padding:4px 7px;line-height:1;"'
                  . ' onclick="dailyDeleteActivity(' . $actId . ')" title="削除">'
                  . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
                  . '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>'
                  . '<path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>'
                  . '</svg></button>'
                : '';

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
                . '<td>' . $deleteBtn . '</td>'
                . '</tr>';
        }

        if ($activitiesHtml === '') {
            $activitiesHtml = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#627d98;">この日の活動記録はありません。</td></tr>';
        }

        $existingComment = (string) ($dailyReport['comment'] ?? '');

        $isSubmitted = (int) ($dailyReport['is_submitted'] ?? 0) === 1;
        $submittedAt = (string) ($dailyReport['submitted_at'] ?? '');

        if ($isSubmitted) {
            $submitBlockHtml =
                '<div style="margin-top:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                . '<span style="color:#15803d;font-size:14px;font-weight:600;">✓ 提出済み</span>'
                . ($submittedAt !== '' ? '<span style="color:#52606d;font-size:12px;">' . Layout::escape($submittedAt) . '</span>' : '')
                . '</div>';
        } elseif ($submitUrl !== '' && $submitCsrf !== '') {
            $submitBlockHtml =
                '<form method="post" action="' . Layout::escape($submitUrl) . '" style="margin-top:16px;" onsubmit="return confirm(\'日報を提出します。提出後は取り消しできません。よろしいですか？\');">'
                . '<input type="hidden" name="route" value="activity/submit">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($submitCsrf) . '">'
                . '<input type="hidden" name="report_date" value="' . Layout::escape($date) . '">'
                . '<input type="hidden" name="staff_id" value="' . $loginUserId . '">'
                . '<button type="submit" class="btn btn-primary">日報を提出</button>'
                . '</form>';
        } else {
            $submitBlockHtml = '';
        }

        if ($isSubmitted) {
            $commentBody =
                '<div style="padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;background:#f8fafc;min-height:80px;white-space:pre-wrap;">'
                . ($existingComment !== '' ? Layout::escape($existingComment) : '<span style="color:#888;">（コメントなし）</span>')
                . '</div>'
                . '<p style="margin-top:6px;font-size:12px;color:#627d98;">提出済みのためコメントは変更できません。</p>';
        } else {
            $commentBody =
                '<form method="post" action="' . Layout::escape($commentUrl) . '">'
                . '<input type="hidden" name="route" value="activity/comment">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
                . '<input type="hidden" name="report_date" value="' . Layout::escape($date) . '">'
                . '<input type="hidden" name="staff_id" value="' . $loginUserId . '">'
                . '<textarea name="comment" rows="5" style="width:100%;padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;">'
                . Layout::escape($existingComment)
                . '</textarea>'
                . '<div class="actions" style="margin-top:10px;">'
                . '<button type="submit" class="btn btn-primary">コメントを保存</button>'
                . '</div>'
                . '</form>';
        }

        $commentSectionHtml =
            '<div class="card">'
            . '<div class="detail-section-title">日報コメント</div>'
            . $commentBody
            . $submitBlockHtml
            . '</div>';

        // 活動登録ダイアログ（最小7項目）
        $prefill = ['activity_date' => $date, 'staff_id' => (string) $staffUserId];
        $formBodyHtml = ActivityDetailView::buildDialogForm(
            $prefill,
            $customers,
            $allowedActivityTypes,
            $purposeTypes
        );

        $registerDialog =
            '<dialog id="dlg-activity-new" class="modal-dialog" style="max-width:600px;width:95%;">'
            . '<div class="modal-head">'
            . '<h2>活動を登録</h2>'
            . '<button type="button" class="modal-close" onclick="document.getElementById(\'dlg-activity-new\').close()">×</button>'
            . '</div>'
            . '<form id="activity-new-form" method="post" action="' . Layout::escape($storeUrl) . '">'
            . '<input type="hidden" name="route" value="activity/store">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($storeCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($dailyUrl) . '">'
            . $formBodyHtml
            . '<div class="dialog-actions" style="margin-top:12px;">'
            . '<button type="submit" class="btn btn-primary">登録</button>'
            . '<button type="button" class="btn btn-ghost" onclick="document.getElementById(\'dlg-activity-new\').close()">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';

        $content =
            '<div class="list-page-header" style="margin-bottom:16px;">'
            . '<h1 class="title">営業日報</h1>'
            . '</div>'
            . $noticeHtml

            // 日付ナビゲーション
            . '<div class="card" style="margin-bottom:16px;">'
            . '<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">'
            . '<a href="' . $prevUrl . '" class="btn btn-ghost btn-small">← 前日</a>'
            . '<strong style="font-size:18px;">' . Layout::escape($date) . '</strong>'
            . '<a href="' . $nextUrl . '" class="btn btn-ghost btn-small">翌日 →</a>'
            . '<span style="color:#52606d;font-size:14px;">担当：</span>'
            . '<span style="font-size:14px;font-weight:600;">' . Layout::escape($staffDisplayName) . '</span>'
            . '</div>'
            . '</div>'

            // 活動一覧
            . '<div class="card" style="padding:0;overflow:hidden;margin-bottom:16px;">'
            . '<div style="padding:14px 16px;border-bottom:1px solid #d9e2ec;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
            . '<div class="detail-section-title" style="margin:0;">当日の活動（' . count($activities) . '件）</div>'
            . '<button type="button" class="btn btn-primary btn-small" onclick="document.getElementById(\'dlg-activity-new\').showModal()">＋ 活動を登録</button>'
            . '</div>'
            . '<div style="overflow-x:auto;">'
            . '<table class="list-table" style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="width:110px;">時刻</th><th style="width:80px;">種別</th><th style="width:18%;">顧客</th>'
            . '<th>件名 / 内容要約</th><th style="width:110px;">次回予定日</th><th style="width:60px;"></th><th style="width:44px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $activitiesHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '<div style="padding:8px 16px 12px;font-size:12px;color:var(--text-secondary);">'
            . '詳細編集は各行の詳細ボタンから行います'
            . '</div>'
            . '</div>'

            // 日報コメント
            . $commentSectionHtml

            // 活動登録ダイアログ
            . $registerDialog

            // 削除用共有フォーム
            . ($deleteUrl !== ''
                ? '<form id="daily-delete-form" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:none;">'
                  . '<input type="hidden" name="route" value="activity/delete">'
                  . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                  . '<input type="hidden" name="id" id="daily-delete-id" value="">'
                  . '<input type="hidden" name="return_to" value="' . Layout::escape($dailyUrl) . '">'
                  . '</form>'
                  . '<script>function dailyDeleteActivity(id){if(!confirm("この活動を削除しますか？この操作は取り消せません。")){return;}'
                  . 'document.getElementById("daily-delete-id").value=id;'
                  . 'document.getElementById("daily-delete-form").submit();}</script>'
                : '');

        return Layout::render('営業日報（' . $date . '）', $content, $layoutOptions);
    }
}
