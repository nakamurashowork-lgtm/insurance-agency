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


        // 活動一覧（PC テーブル行 + スマホカードを同時生成）
        $activitiesHtml  = '';  // PC テーブル tbody 用
        $activityCardsHtml = ''; // スマホカード用
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
            $timeDisplay = $timeStr !== '' ? $timeStr : '<span style="color:var(--text-secondary);">時刻未設定</span>';

            $detailUrl = $actId > 0
                ? Layout::escape(ListViewHelper::buildUrl($activityDetailBaseUrl, ['id' => (string) $actId, 'from' => 'daily', 'date' => $date]))
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

            $deleteFormId = 'form-del-daily-' . $actId;
            $deleteFormHtml = ($deleteUrl !== '' && $actId > 0)
                ? '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:inline;">'
                  . '<input type="hidden" name="route" value="activity/delete">'
                  . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                  . '<input type="hidden" name="id" value="' . $actId . '">'
                  . '<input type="hidden" name="return_to" value="' . Layout::escape($dailyUrl) . '">'
                  . '<button type="button" class="btn-icon-delete" title="削除"'
                  . ' data-delete-form="' . $deleteFormId . '"'
                  . ' data-delete-label="' . Layout::escape($subject !== '' ? $subject : '活動') . '">'
                  . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                  . '</button>'
                  . '</form>'
                : '';

            // PC テーブル行
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
                . '<td>' . $deleteFormHtml . '</td>'
                . '</tr>';

            // スマホカード
            $activityCardsHtml .=
                '<div class="daily-act-card">'
                . '<div class="daily-act-card-header">'
                . '<span class="daily-act-time">' . $timeDisplay . '</span>'
                . '<span class="daily-act-badge">' . Layout::escape($typeLabel) . '</span>'
                . '</div>'
                . '<div class="daily-act-customer">' . $custHtml . '</div>'
                . '<div class="daily-act-body">'
                . ($subject !== '' ? '<div class="daily-act-subject">' . Layout::escape($subject) . '</div>' : '')
                . ($summary !== '' ? '<div class="daily-act-summary">' . Layout::escape(mb_strimwidth($summary, 0, 80, '…')) . '</div>' : '')
                . '</div>'
                . '<div class="daily-act-footer">'
                . ($nextDate2 !== '' ? '<div class="daily-act-next">次回予定：' . Layout::escape($nextDate2) . '</div>' : '<div></div>')
                . '<div class="daily-act-actions">'
                . ($detailUrl !== '' ? '<a href="' . $detailUrl . '" class="btn btn-small btn-ghost">詳細</a>' : '')
                . $deleteFormHtml
                . '</div>'
                . '</div>'
                . '</div>';
        }

        if ($activitiesHtml === '') {
            $activitiesHtml = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#627d98;">この日の活動記録はありません。</td></tr>';
        }
        if ($activityCardsHtml === '') {
            $activityCardsHtml = '<div class="daily-act-empty">この日の活動記録はありません。</div>';
        }

        $existingComment = (string) ($dailyReport['comment'] ?? '');

        $commentBody =
            '<form method="post" action="' . Layout::escape($commentUrl) . '">'
            . '<input type="hidden" name="route" value="activity/comment">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($commentCsrf) . '">'
            . '<input type="hidden" name="report_date" value="' . Layout::escape($date) . '">'
            . '<input type="hidden" name="staff_id" value="' . $loginUserId . '">'
            . '<textarea name="comment" rows="5" class="daily-comment-textarea" style="width:100%;padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;">'
            . Layout::escape($existingComment)
            . '</textarea>'
            . '<div class="actions" style="margin-top:10px;">'
            . '<button type="submit" class="btn btn-primary">コメントを保存</button>'
            . '</div>'
            . '</form>';

        $commentSectionHtml =
            '<div class="card">'
            . '<div class="detail-section-title">日報コメント</div>'
            . $commentBody
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

        $listUrlEscaped = Layout::escape($listUrl);

        $css =
            '<style>'
            // PC/スマホ切り替え
            . '.activity-table{display:table;width:100%;border-collapse:collapse;}'
            . '.activity-cards{display:none;}'
            // ページヘッダー
            . '.daily-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:16px;}'
            // 日付ナビ
            . '.daily-date-nav{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}'
            . '.daily-date-label{font-size:18px;font-weight:700;white-space:nowrap;}'
            . '.daily-staff-label{font-size:14px;color:var(--text-secondary);white-space:nowrap;}'
            // カードスタイル
            . '.daily-act-card{border:1px solid var(--border-light);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:10px;background:#fff;}'
            . '.daily-act-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}'
            . '.daily-act-time{font-size:13px;color:var(--text-secondary);font-weight:500;}'
            . '.daily-act-badge{display:inline-block;padding:2px 8px;background:var(--bg-secondary);border-radius:4px;font-size:11px;color:var(--text-primary);}'
            . '.daily-act-customer{font-size:14px;font-weight:600;margin-bottom:6px;}'
            . '.daily-act-customer a{color:var(--text-info);text-decoration:none;}'
            . '.daily-act-body{padding:6px 0;border-top:1px solid var(--border-light);margin-bottom:6px;}'
            . '.daily-act-subject{font-size:14px;font-weight:600;margin-bottom:3px;line-height:1.4;}'
            . '.daily-act-summary{font-size:13px;color:var(--text-secondary);line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}'
            . '.daily-act-footer{display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:1px solid var(--border-light);}'
            . '.daily-act-next{font-size:12px;color:var(--text-secondary);}'
            . '.daily-act-actions{display:flex;align-items:center;gap:6px;}'
            . '.daily-act-empty{text-align:center;padding:24px 12px;color:var(--text-secondary);font-size:14px;}'
            // ダイアログ登録フォームの font-size（iOS ズーム対策）
            . '#dlg-activity-new input,#dlg-activity-new select,#dlg-activity-new textarea{font-size:16px;}'
            // スマホ（600px 以下）
            . '@media(max-width:600px){'
            . '.activity-table{display:none;}'
            . '.activity-cards{display:block;}'
            . '.daily-page-header{flex-direction:column;align-items:stretch;}'
            . '.daily-page-header h1{margin-bottom:4px;}'
            . '.daily-page-header>a{width:100%;text-align:center;}'
            . '.daily-date-nav{justify-content:space-between;}'
            . '.daily-date-label{font-size:16px;}'
            . '.daily-act-card-header{flex-wrap:wrap;gap:4px;}'
            . '.daily-act-actions .btn{padding:8px 16px;min-height:36px;font-size:13px;}'
            . '#dlg-activity-new{max-width:100%;width:100%;height:100dvh;max-height:100dvh;border-radius:0;margin:0;border:none;}'
            . '.daily-comment-textarea{font-size:16px !important;}'
            . '}'
            . '</style>';

        $content =
            $css
            . '<div class="daily-page-header">'
            . '<h1 class="title" style="margin:0;">営業日報</h1>'
            . '<a href="' . $listUrlEscaped . '" class="btn btn-ghost btn-small">過去の活動を検索</a>'
            . '</div>'
            . $noticeHtml

            // 日付ナビゲーション
            . '<div class="card" style="margin-bottom:16px;">'
            . '<div class="daily-date-nav">'
            . '<a href="' . $prevUrl . '" class="btn btn-ghost btn-small">← 前日</a>'
            . '<span class="daily-date-label">' . Layout::escape($date) . '</span>'
            . '<a href="' . $nextUrl . '" class="btn btn-ghost btn-small">翌日 →</a>'
            . '<span class="daily-staff-label">担当：<strong>' . Layout::escape($staffDisplayName) . '</strong></span>'
            . '</div>'
            . '</div>'

            // 活動一覧（PC テーブル + スマホカード）
            . '<div class="card" style="padding:0;overflow:hidden;margin-bottom:16px;">'
            . '<div style="padding:14px 16px;border-bottom:1px solid #d9e2ec;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">'
            . '<div class="detail-section-title" style="margin:0;">当日の活動（' . count($activities) . '件）</div>'
            . '<button type="button" class="btn btn-primary btn-small" onclick="document.getElementById(\'dlg-activity-new\').showModal()">＋ 活動を登録</button>'
            . '</div>'
            // PC テーブル
            . '<div style="overflow-x:auto;">'
            . '<table class="list-table activity-table">'
            . '<thead><tr>'
            . '<th style="width:110px;">時刻</th><th style="width:80px;">種別</th><th style="width:18%;">顧客</th>'
            . '<th>件名 / 内容要約</th><th style="width:110px;">次回予定日</th><th style="width:60px;"></th><th style="width:44px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $activitiesHtml . '</tbody>'
            . '</table>'
            . '</div>'
            // スマホカード
            . '<div class="activity-cards" style="padding:10px 12px 4px;">'
            . $activityCardsHtml
            . '</div>'
            . '<div style="padding:8px 16px 12px;font-size:12px;color:var(--text-secondary);">'
            . '詳細編集は各行の詳細ボタンから行います'
            . '</div>'
            . '</div>'

            // 日報コメント
            . $commentSectionHtml

            // 活動登録ダイアログ
            . $registerDialog

            // 削除確認ダイアログ
            . ($deleteUrl !== ''
                ? '<dialog id="dlg-delete-daily-confirm" class="modal-dialog">'
                  . '<div class="modal-head"><h2>削除の確認</h2>'
                  . '<button type="button" class="modal-close" id="dlg-delete-daily-close">×</button>'
                  . '</div>'
                  . '<p id="dlg-delete-daily-msg" style="margin:16px 0;"></p>'
                  . '<div class="dialog-actions">'
                  . '<button type="button" id="dlg-delete-daily-ok" class="btn btn-danger">削除する</button>'
                  . '<button type="button" id="dlg-delete-daily-cancel" class="btn btn-ghost">キャンセル</button>'
                  . '</div>'
                  . '</dialog>'
                  . '<script>(function(){'
                  . 'var dlg=document.getElementById("dlg-delete-daily-confirm");'
                  . 'if(dlg&&typeof dlg.showModal==="function"){'
                  . 'var msg=document.getElementById("dlg-delete-daily-msg");'
                  . 'var pendingId=null;'
                  . 'document.querySelectorAll("[data-delete-form]").forEach(function(btn){'
                  . 'btn.addEventListener("click",function(){'
                  . 'pendingId=btn.getAttribute("data-delete-form");'
                  . 'var label=btn.getAttribute("data-delete-label")||"この活動";'
                  . 'msg.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";'
                  . 'if(!dlg.open){dlg.showModal();}});});'
                  . 'function closeDlg(){if(dlg.open){dlg.close();}pendingId=null;}'
                  . 'document.getElementById("dlg-delete-daily-ok").addEventListener("click",function(){'
                  . 'if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}'
                  . 'closeDlg();});'
                  . 'document.getElementById("dlg-delete-daily-cancel").addEventListener("click",closeDlg);'
                  . 'document.getElementById("dlg-delete-daily-close").addEventListener("click",closeDlg);'
                  . '}}());</script>'
                : '');

        return Layout::render('営業日報（' . $date . '）', $content, $layoutOptions);
    }
}
