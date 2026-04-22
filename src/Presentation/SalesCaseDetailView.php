<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Domain\SalesCase\SalesCaseRepository;
use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class SalesCaseDetailView
{
    /**
     * 見込案件詳細（既存の確認・編集専用。削除 UI は撤去）
     *
     * 注記: $deleteUrl / $deleteCsrf は呼び出し側（Controller）との API 互換のため
     * 引数として受け取り続けるが、本 View では参照しない。削除エンドポイント自体は
     * 残しているため、将来 UI を戻す場合は再利用可能。
     *
     * @param array<string, mixed>|null             $record
     * @param array<int, array<string, mixed>>      $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>>      $activities
     * @param array<string, mixed>                  $layoutOptions
     * @param array<int, array<string, mixed>>      $salesCaseStatuses
     */
    public static function renderDetail(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $activities,
        string $detailUrl,
        string $updateUrl,
        string $deleteUrl,
        string $customerDetailBaseUrl,
        string $activityDetailBaseUrl,
        string $updateCsrf,
        string $deleteCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $layoutOptions,
        array $productCategories = [],
        array $salesCaseStatuses = []
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
                . '<div class="card"><p>見込案件が見つかりません。</p>'
                . '<button type="button" class="btn btn-ghost" onclick="history.back()">戻る</button></div>';
            return Layout::render('見込案件詳細', $content, $layoutOptions);
        }

        $id           = (int) ($record['id'] ?? 0);
        $custId       = (int) ($record['customer_id'] ?? 0);
        $custName     = (string) ($record['customer_name'] ?? '');
        $prospectName = (string) ($record['prospect_name'] ?? '');
        $createdAt    = (string) ($record['created_at'] ?? '');
        $updatedAt    = (string) ($record['updated_at'] ?? '');

        $custUrl      = $custId > 0
            ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId]))
            : '';
        $displayName  = $custId > 0 ? $custName : ($prospectName !== '' ? $prospectName : '');
        $custLinkHtml = $custId > 0 && $custUrl !== ''
            ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($displayName) . '</a>'
            : Layout::escape($displayName);

        $formHtml = self::buildForm($record, $customers, $staffUsers, $id, $productCategories, $salesCaseStatuses);

        // 削除ダイアログは撤去（UI から削除ボタンを除去）。
        // $deleteUrl / $deleteCsrf は現状の Controller→View シグネチャとの互換のために受け取るが、本 View では未使用。

        $activitiesHtml = self::renderLinkedActivities($activities, $activityDetailBaseUrl);

        $content =
            '<div class="card">'
            . '<div class="section-head">'
            . '<div>'
            . '<h1 class="title">見込案件詳細</h1>'
            . '<div class="meta-row">'
            . '<span class="muted" style="font-size:13px;">顧客：' . $custLinkHtml . '</span>'
            . ($createdAt !== '' ? '<span class="muted" style="font-size:13px;">登録：' . Layout::escape($createdAt) . '</span>' : '')
            . ($updatedAt !== '' ? '<span class="muted" style="font-size:13px;">更新：' . Layout::escape($updatedAt) . '</span>' : '')
            . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . '<button type="submit" class="btn" form="sales-case-update-form">保存</button>'
            . '</div>'
            . '</div>'
            . $noticeHtml
            . '</div>'
            . '<form id="sales-case-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . $formHtml
            . '<div class="actions" style="margin-top:8px;">'
            . '<button type="submit" class="btn btn-primary">保存</button>'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . '</div>'
            . '</form>'
            . $activitiesHtml;

        return Layout::render('見込案件詳細', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed>                  $data
     * @param array<int, array<string, mixed>>      $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>>      $productCategories
     * @param array<int, array<string, mixed>>      $salesCaseStatuses
     */
    private static function buildForm(
        array $data,
        array $customers,
        array $staffUsers,
        int $id = 0,
        array $productCategories = [],
        array $salesCaseStatuses = []
    ): string {
        $customerIdVal        = (string) ($data['customer_id'] ?? '');
        $prospectNameVal      = (string) ($data['prospect_name'] ?? '');
        $caseNameVal          = (string) ($data['case_name'] ?? '');
        $caseTypeVal          = (string) ($data['case_type'] ?? 'new');
        $productTypeVal       = (string) ($data['product_type'] ?? '');
        $statusVal            = (string) ($data['status'] ?? 'open');
        $prospectRankVal      = (string) ($data['prospect_rank'] ?? '');
        $premiumVal           = ($data['expected_premium'] ?? null) !== null ? (string) $data['expected_premium'] : '';
        $closeMonthVal        = (string) ($data['expected_contract_month'] ?? '');
        $nextActionDateVal    = (string) ($data['next_action_date'] ?? '');
        $lostReasonVal        = (string) ($data['lost_reason'] ?? '');
        $memoVal              = (string) ($data['memo'] ?? '');
        $staffUserIdVal       = (string) ($data['staff_id'] ?? '');

        $hasCustomerId    = ($customerIdVal !== '' && (int) $customerIdVal > 0);
        $useProspectInput = (!$hasCustomerId && $prospectNameVal !== '');

        $custOptions = '<option value="">-- 顧客を選択 --</option>';
        foreach ($customers as $cust) {
            $cid   = (int) ($cust['id'] ?? 0);
            $cname = (string) ($cust['customer_name'] ?? '');
            $sel   = $customerIdVal === (string) $cid ? ' selected' : '';
            $custOptions .= '<option value="' . $cid . '"' . $sel . '>' . Layout::escape($cname) . '</option>';
        }

        $caseTypeOptions = '';
        foreach (SalesCaseRepository::ALLOWED_CASE_TYPES as $val => $label) {
            $sel = $caseTypeVal === $val ? ' selected' : '';
            $caseTypeOptions .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        // m_sales_case_status の name（表示名=DB格納値）を選択肢にする。
        $statusNames = [];
        foreach ($salesCaseStatuses as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $statusNames[] = $name;
            }
        }
        // 既存値がマスタから削除・無効化されていても現レコードを表示できるよう補完
        if ($statusVal !== '' && !in_array($statusVal, $statusNames, true)) {
            $statusNames[] = $statusVal;
        }
        $statusOptions = '';
        foreach ($statusNames as $name) {
            $sel = $statusVal === $name ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($name) . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        $rankOptions = '<option value="">— 未設定 —</option>';
        foreach (SalesCaseRepository::ALLOWED_PROSPECT_RANKS as $rank) {
            $sel = $prospectRankVal === $rank ? ' selected' : '';
            $rankOptions .= '<option value="' . Layout::escape($rank) . '"' . $sel . '>' . Layout::escape($rank) . '</option>';
        }

        $staffOptions = '<option value="">-- 選択 --</option>';
        foreach ($staffUsers as $user) {
            $uid   = (int) ($user['id'] ?? 0);
            $uname = (string) ($user['staff_name'] ?? $user['name'] ?? '');
            $sel   = $staffUserIdVal === (string) $uid ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $sel . '>' . Layout::escape($uname) . '</option>';
        }

        $productOptions = '<option value="">— 未選択 —</option>';
        foreach ($productCategories as $cat) {
            $catVal  = Layout::escape((string) ($cat['name'] ?? ''));
            $sel     = $productTypeVal === (string) ($cat['name'] ?? '') ? ' selected' : '';
            $productOptions .= '<option value="' . $catVal . '"' . $sel . '>' . $catVal . '</option>';
        }
        // 既存値がマスタに存在しない場合は先頭に追加
        if ($productTypeVal !== '' && $productCategories !== []) {
            $exists = false;
            foreach ($productCategories as $cat) {
                if ((string) ($cat['name'] ?? '') === $productTypeVal) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $productOptions = '<option value="">— 未選択 —</option>'
                    . '<option value="' . Layout::escape($productTypeVal) . '" selected style="color:#999;">'
                    . Layout::escape('（旧値: ' . $productTypeVal . '）') . '</option>'
                    . substr($productOptions, strlen('<option value="">— 未選択 —</option>'));
            }
        }

        // 失注理由は「失注」名のステータス時のみ表示（$id=0 は新規なので非表示）
        $lostReasonHtml = '';
        if ($id > 0 && $statusVal === '失注') {
            $lostReasonHtml =
                '<label class="form-field form-field--full"><span class="form-field-label">失注理由</span>'
                . '<input type="text" name="lost_reason" value="' . Layout::escape($lostReasonVal) . '" maxlength="500"></label>';
        }

        return
            '<div class="card">'
            . '<div class="customer-create-grid">'

            . ($useProspectInput
                ? '<label class="form-field"><span class="form-field-label">顧客（新規）</span>'
                  . '<input type="text" name="prospect_name" value="' . Layout::escape($prospectNameVal) . '" maxlength="200" placeholder="会社名・氏名など"></label>'
                : '<label class="form-field"><span class="form-field-label">顧客（既存）</span>'
                  . '<select name="customer_id">' . $custOptions . '</select></label>')

            . '<label class="form-field form-field--required"><span class="form-field-label">案件名</span>'
            . '<input type="text" name="case_name" value="' . Layout::escape($caseNameVal) . '" required maxlength="200"></label>'

            . '<label class="form-field form-field--required"><span class="form-field-label">対応状況</span>'
            . '<select name="status" required>' . $statusOptions . '</select></label>'

            . '<label class="form-field"><span class="form-field-label">種目</span>'
            . '<select name="product_type">' . $productOptions . '</select></label>'

            . '<label class="form-field"><span class="form-field-label">見込度</span>'
            . '<select name="prospect_rank">' . $rankOptions . '</select></label>'

            . '<label class="form-field"><span class="form-field-label">想定保険料（円）</span>'
            . '<input type="number" name="expected_premium" value="' . Layout::escape($premiumVal) . '" min="0"></label>'

            . '<label class="form-field"><span class="form-field-label">契約予定月</span>'
            . '<input type="month" name="expected_contract_month" value="' . Layout::escape($closeMonthVal) . '"></label>'

            . '<label class="form-field"><span class="form-field-label">次回予定日</span>'
            . '<input type="date" name="next_action_date" value="' . Layout::escape($nextActionDateVal) . '"></label>'

            . '<label class="form-field"><span class="form-field-label">担当者</span>'
            . '<select name="staff_id">' . $staffOptions . '</select></label>'

            . $lostReasonHtml

            . '<label class="form-field form-field--full"><span class="form-field-label">メモ</span>'
            . '<textarea name="memo" rows="4">' . Layout::escape($memoVal) . '</textarea></label>'

            . '</div>'
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $activities
     */
    private static function renderLinkedActivities(array $activities, string $activityDetailBaseUrl): string
    {
        $itemsHtml = '';
        foreach ($activities as $act) {
            $actId    = (int) ($act['id'] ?? 0);
            $actDate  = (string) ($act['activity_date'] ?? '');
            $actType  = (string) ($act['activity_type'] ?? '');
            $subject  = trim((string) ($act['subject'] ?? '')) ?: '-';
            $summary  = trim((string) ($act['content_summary'] ?? ''));
            $nextDate = (string) ($act['next_action_date'] ?? '');
            $staffName = (string) ($act['staff_name'] ?? '');

            $detailUrl = $actId > 0
                ? Layout::escape(ListViewHelper::buildUrl($activityDetailBaseUrl, ['id' => (string) $actId]))
                : '';

            $nextHtml  = $nextDate !== ''
                ? '<span class="muted" style="font-size:12px;margin-left:8px;">次回：' . Layout::escape($nextDate) . '</span>'
                : '';

            $itemsHtml .= '<li style="padding:6px 0;border-bottom:1px solid #eef4f6;">'
                . '<div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">'
                . '<span style="font-size:13px;color:#334e68;">' . Layout::escape($actDate) . '</span>'
                . '<span style="font-size:12px;background:#eef4f6;padding:2px 6px;border-radius:4px;">' . Layout::escape($actType) . '</span>'
                . '<strong style="font-size:14px;">' . Layout::escape($subject) . '</strong>'
                . ($staffName !== '' ? '<span class="muted" style="font-size:12px;">' . Layout::escape($staffName) . '</span>' : '')
                . $nextHtml
                . ($detailUrl !== '' ? '<a href="' . $detailUrl . '" class="text-link" style="font-size:12px;margin-left:auto;">詳細</a>' : '')
                . '</div>'
                . ($summary !== '' ? '<div style="font-size:13px;color:#52606d;margin-top:2px;">' . Layout::escape(mb_strimwidth($summary, 0, 80, '…')) . '</div>' : '')
                . '</li>';
        }

        if ($itemsHtml === '') {
            $itemsHtml = '<li style="color:#627d98;padding:8px 0;">この案件に紐づく活動はありません。</li>';
        }

        return '<details class="card details-panel details-compact" style="margin-top:16px;">'
            . '<summary><span>紐づく活動履歴</span><span class="muted">' . count($activities) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<ul class="panel-list" style="list-style:none;padding:0;margin:0;">' . $itemsHtml . '</ul>'
            . '</div>'
            . '</details>';
    }
}
