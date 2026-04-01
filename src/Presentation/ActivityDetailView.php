<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class ActivityDetailView
{
    /**
     * 活動登録（新規専用）
     *
     * @param array<string, mixed> $prefill
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array{id:int, name:string}> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function renderNew(
        array $prefill,
        array $customers,
        array $staffUsers,
        string $listUrl,
        string $storeUrl,
        string $storeCsrf,
        ?string $flashError,
        ?string $errorMessage,
        array $allowedActivityTypes,
        array $layoutOptions
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($errorMessage) && $errorMessage !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $formHtml = self::buildForm($prefill, $customers, $staffUsers, $allowedActivityTypes);

        $content =
            '<div class="card">'
            . '<div class="section-head">'
            . '<div><h1 class="title">活動登録</h1></div>'
            . '<div class="actions">'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-ghost">キャンセル</a>'
            . '<button type="submit" class="btn btn-primary" form="activity-new-form">登録</button>'
            . '</div>'
            . '</div>'
            . $noticeHtml
            . '</div>'
            . '<form id="activity-new-form" method="post" action="' . Layout::escape($storeUrl) . '">'
            . '<input type="hidden" name="route" value="activity/store">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($storeCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
            . $formHtml
            . '<div class="actions" style="margin-top:4px;">'
            . '<button type="submit" class="btn btn-primary">登録</button>'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-ghost">キャンセル</a>'
            . '</div>'
            . '</form>';

        return Layout::render('活動登録', $content, $layoutOptions);
    }

    /**
     * 活動詳細（既存の確認・編集専用）
     *
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array{id:int, name:string}> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function renderDetail(
        ?array $record,
        array $customers,
        array $staffUsers,
        string $listUrl,
        string $detailUrl,
        string $updateUrl,
        string $deleteUrl,
        string $customerDetailBaseUrl,
        string $updateCsrf,
        string $deleteCsrf,
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

        if ($record === null) {
            $content =
                $noticeHtml
                . '<div class="card"><p>活動が見つかりません。</p>'
                . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-ghost">一覧に戻る</a></div>';
            return Layout::render('活動詳細', $content, $layoutOptions);
        }

        $id        = (int) ($record['id'] ?? 0);
        $custId    = (int) ($record['customer_id'] ?? 0);
        $custName  = (string) ($record['customer_name'] ?? '');
        $custUrl   = $custId > 0 ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId])) : '';
        $createdAt = (string) ($record['created_at'] ?? '');
        $updatedAt = (string) ($record['updated_at'] ?? '');

        $custLinkHtml = $custUrl !== ''
            ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
            : Layout::escape($custName);

        $formHtml = self::buildForm($record, $customers, $staffUsers, $allowedActivityTypes, $id);

        $deleteDialog =
            '<dialog id="dlg-delete" class="modal-dialog">'
            . '<div class="modal-head"><h2>活動の削除</h2>'
            . '<button type="button" class="modal-close" onclick="document.getElementById(\'dlg-delete\').close()">×</button>'
            . '</div>'
            . '<p>この活動を削除しますか？この操作は取り消せません。</p>'
            . '<form method="post" action="' . Layout::escape($deleteUrl) . '">'
            . '<input type="hidden" name="route" value="activity/delete">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
            . '<div class="dialog-actions">'
            . '<button type="submit" class="btn btn-danger">削除する</button>'
            . '<button type="button" class="btn btn-ghost" onclick="document.getElementById(\'dlg-delete\').close()">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';

        $content =
            '<div class="card">'
            . '<div class="section-head">'
            . '<div>'
            . '<h1 class="title">活動詳細</h1>'
            . '<div class="meta-row">'
            . '<span class="muted" style="font-size:13px;">顧客：' . $custLinkHtml . '</span>'
            . ($createdAt !== '' ? '<span class="muted" style="font-size:13px;">登録：' . Layout::escape($createdAt) . '</span>' : '')
            . ($updatedAt !== '' ? '<span class="muted" style="font-size:13px;">更新：' . Layout::escape($updatedAt) . '</span>' : '')
            . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-secondary">一覧に戻る</a>'
            . '<button type="button" class="btn btn-danger btn-small" onclick="document.getElementById(\'dlg-delete\').showModal()">削除</button>'
            . '<button type="submit" class="btn" form="activity-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . $noticeHtml
            . '</div>'
            . '<form id="activity-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="route" value="activity/update">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
            . $formHtml
            . '<div class="actions" style="margin-top:4px;">'
            . '<button type="submit" class="btn btn-primary">保存</button>'
            . '<a href="' . Layout::escape($listUrl) . '" class="btn btn-ghost">一覧に戻る</a>'
            . '</div>'
            . '</form>'
            . $deleteDialog;

        return Layout::render('活動詳細', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array{id:int, name:string}> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     */
    private static function buildForm(
        array $data,
        array $customers,
        array $staffUsers,
        array $allowedActivityTypes,
        int $id = 0
    ): string {
        $customerIdVal    = (string) ($data['customer_id'] ?? '');
        $activityDateVal  = (string) ($data['activity_date'] ?? '');
        $startTimeVal     = (string) ($data['start_time'] ?? '');
        $endTimeVal       = (string) ($data['end_time'] ?? '');
        $activityTypeVal  = (string) ($data['activity_type'] ?? '');
        $purposeTypeVal   = (string) ($data['purpose_type'] ?? '');
        $visitPlaceVal    = (string) ($data['visit_place'] ?? '');
        $intervieweeVal   = (string) ($data['interviewee_name'] ?? '');
        $subjectVal       = (string) ($data['subject'] ?? '');
        $summaryVal       = (string) ($data['content_summary'] ?? '');
        $detailTextVal    = (string) ($data['detail_text'] ?? '');
        $nextDateVal      = (string) ($data['next_action_date'] ?? '');
        $nextNoteVal      = (string) ($data['next_action_note'] ?? '');
        $resultTypeVal    = (string) ($data['result_type'] ?? '');
        $renewalIdVal     = (string) ($data['renewal_case_id'] ?? '');
        $accidentIdVal    = (string) ($data['accident_case_id'] ?? '');
        $staffUserIdVal   = (string) ($data['staff_user_id'] ?? '');

        $custOptionsHtml = '<option value="">-- 顧客を選択 --</option>';
        foreach ($customers as $cust) {
            $cid   = (int) ($cust['id'] ?? 0);
            $cname = (string) ($cust['customer_name'] ?? '');
            $sel   = $customerIdVal === (string) $cid ? ' selected' : '';
            $custOptionsHtml .= '<option value="' . $cid . '"' . $sel . '>' . Layout::escape($cname) . '</option>';
        }

        $typeOptionsHtml = '<option value="">-- 選択 --</option>';
        foreach ($allowedActivityTypes as $val => $label) {
            $sel = $activityTypeVal === $val ? ' selected' : '';
            $typeOptionsHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        $staffOptionsHtml = '<option value="">-- 選択 --</option>';
        foreach ($staffUsers as $user) {
            $uid   = (int) ($user['id'] ?? 0);
            $uname = (string) ($user['name'] ?? '');
            $sel   = $staffUserIdVal === (string) $uid ? ' selected' : '';
            $staffOptionsHtml .= '<option value="' . $uid . '"' . $sel . '>' . Layout::escape($uname) . '</option>';
        }

        $req = '<strong class="required-mark"> *</strong>';

        return
            '<div class="card">'
            . '<div class="list-filter-grid modal-form-grid">'

            . '<label class="list-filter-field modal-form-wide"><span>顧客' . $req . '</span>'
            . '<select name="customer_id" required>' . $custOptionsHtml . '</select></label>'

            . '<label class="list-filter-field" style="grid-column:span 4;"><span>活動日' . $req . '</span>'
            . '<input type="date" name="activity_date" value="' . Layout::escape($activityDateVal) . '" required></label>'
            . '<label class="list-filter-field" style="grid-column:span 4;"><span>開始時刻</span>'
            . '<input type="time" name="start_time" value="' . Layout::escape($startTimeVal) . '"></label>'
            . '<label class="list-filter-field" style="grid-column:span 4;"><span>終了時刻</span>'
            . '<input type="time" name="end_time" value="' . Layout::escape($endTimeVal) . '"></label>'

            . '<label class="list-filter-field"><span>活動種別' . $req . '</span>'
            . '<select name="activity_type" required>' . $typeOptionsHtml . '</select></label>'
            . '<label class="list-filter-field"><span>用件区分</span>'
            . '<input type="text" name="purpose_type" value="' . Layout::escape($purposeTypeVal) . '" maxlength="50"></label>'

            . '<label class="list-filter-field"><span>訪問先</span>'
            . '<input type="text" name="visit_place" value="' . Layout::escape($visitPlaceVal) . '" maxlength="200"></label>'
            . '<label class="list-filter-field"><span>面談者</span>'
            . '<input type="text" name="interviewee_name" value="' . Layout::escape($intervieweeVal) . '" maxlength="100"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>件名</span>'
            . '<input type="text" name="subject" value="' . Layout::escape($subjectVal) . '" maxlength="200"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>内容要約' . $req . '</span>'
            . '<textarea name="content_summary" required maxlength="500" rows="3" style="width:100%;resize:vertical;">'
            . Layout::escape($summaryVal) . '</textarea></label>'

            . '<label class="list-filter-field modal-form-wide"><span>詳細内容</span>'
            . '<textarea name="detail_text" rows="4" style="width:100%;resize:vertical;">'
            . Layout::escape($detailTextVal) . '</textarea></label>'

            . '<label class="list-filter-field"><span>次回予定日</span>'
            . '<input type="date" name="next_action_date" value="' . Layout::escape($nextDateVal) . '"></label>'
            . '<label class="list-filter-field"><span>結果区分</span>'
            . '<input type="text" name="result_type" value="' . Layout::escape($resultTypeVal) . '" maxlength="50"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>次回アクション</span>'
            . '<textarea name="next_action_note" maxlength="500" rows="2" style="width:100%;resize:vertical;">'
            . Layout::escape($nextNoteVal) . '</textarea></label>'

            . '<label class="list-filter-field" style="grid-column:span 4;"><span>担当者</span>'
            . '<select name="staff_user_id">' . $staffOptionsHtml . '</select></label>'
            . '<label class="list-filter-field" style="grid-column:span 4;"><span>関連満期案件ID</span>'
            . '<input type="text" name="renewal_case_id" value="' . Layout::escape($renewalIdVal) . '" maxlength="20" inputmode="numeric"></label>'
            . '<label class="list-filter-field" style="grid-column:span 4;"><span>関連事故案件ID</span>'
            . '<input type="text" name="accident_case_id" value="' . Layout::escape($accidentIdVal) . '" maxlength="20" inputmode="numeric"></label>'

            . '</div>'
            . '</div>';
    }
}
