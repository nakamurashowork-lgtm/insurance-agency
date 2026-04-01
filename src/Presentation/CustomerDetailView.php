<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class CustomerDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $contacts
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $activities
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $detail,
        array $contacts,
        array $contracts,
        array $activities,
        string $listUrl,
        string $renewalDetailBaseUrl,
        string $activityNewBaseUrl,
        string $activityDetailBaseUrl,
        ?string $errorMessage,
        array $layoutOptions
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $primaryContactHtml = '<p class="section-note">主連絡先は未登録です。</p>';
        $otherContactsHtml = '';
        foreach ($contacts as $row) {
            $contactCard = '<div class="contact-card' . (((int) ($row['is_primary'] ?? 0)) === 1 ? ' contact-card-primary' : '') . '">'
                . '<strong>' . Layout::escape((string) ($row['contact_name'] ?? '')) . '</strong>'
                . '<span>' . Layout::escape((string) ($row['department_name'] ?? ($row['department'] ?? ''))) . '</span>'
                . '<span>' . Layout::escape((string) ($row['phone'] ?? '')) . '</span>'
                . '<span>' . Layout::escape((string) ($row['email'] ?? '')) . '</span>'
                . '</div>';

            if (((int) ($row['is_primary'] ?? 0)) === 1) {
                $primaryContactHtml = $contactCard;
                continue;
            }

            $otherContactsHtml .= $contactCard;
        }
        if ($otherContactsHtml === '') {
            $otherContactsHtml = '<p class="section-note">その他連絡先はありません。</p>';
        }

        $contractsHtml = '';
        foreach ($contracts as $row) {
            $renewalCaseId = (int) ($row['renewal_case_id'] ?? 0);
            $action = '<span class="muted">満期案件未作成</span>';
            if ($renewalCaseId > 0) {
                $url = Layout::escape($renewalDetailBaseUrl . '&id=' . $renewalCaseId);
                $action = '<a class="text-link" href="' . $url . '">満期詳細を見る</a>';
            }

            $contractsHtml .= '<tr>'
                . '<td><div class="cell-stack"><strong class="truncate" title="' . Layout::escape((string) ($row['policy_no'] ?? '')) . '">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</strong><span class="muted truncate" title="' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '">' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '</span></div></td>'
                . '<td>' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['policy_end_date'] ?? '')) . '</td>'
                . '<td>' . self::renderContractStatus((string) ($row['case_status'] ?? '')) . '</td>'
                . '<td class="cell-action">' . $action . '</td>'
                . '</tr>';
        }
        if ($contractsHtml === '') {
            $contractsHtml = '<tr><td colspan="5">保有契約はありません。</td></tr>';
        }

        $customerId = (int) ($detail['id'] ?? 0);
        $activityNewUrl = Layout::escape($activityNewBaseUrl . '&customer_id=' . $customerId);

        $activitiesHtml = '';
        foreach ($activities as $row) {
            $actId   = (int) ($row['id'] ?? 0);
            $actDate = trim((string) ($row['activity_date'] ?? ''));
            $actType = trim((string) ($row['activity_type'] ?? ''));
            $subject = trim((string) ($row['subject'] ?? '')) ?: '-';
            $summary = trim((string) ($row['content_summary'] ?? ''));
            $nextDate = trim((string) ($row['next_action_date'] ?? ''));

            $detailUrl = $actId > 0
                ? Layout::escape($activityDetailBaseUrl . '&id=' . $actId)
                : '';

            $nextDateHtml = $nextDate !== '' ? '<span class="muted" style="font-size:12px;margin-left:8px;">次回：' . Layout::escape($nextDate) . '</span>' : '';

            $activitiesHtml .= '<li style="padding:6px 0;border-bottom:1px solid #eef4f6;">'
                . '<div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">'
                . '<span style="font-size:13px;color:#334e68;">' . Layout::escape($actDate) . '</span>'
                . '<span style="font-size:12px;background:#eef4f6;padding:2px 6px;border-radius:4px;">' . Layout::escape($actType) . '</span>'
                . '<strong style="font-size:14px;">' . Layout::escape($subject) . '</strong>'
                . $nextDateHtml
                . ($detailUrl !== '' ? '<a href="' . $detailUrl . '" class="text-link" style="font-size:12px;margin-left:auto;">詳細</a>' : '')
                . '</div>'
                . ($summary !== '' ? '<div style="font-size:13px;color:#52606d;margin-top:2px;">' . Layout::escape(mb_strimwidth($summary, 0, 80, '…')) . '</div>' : '')
                . '</li>';
        }
        if ($activitiesHtml === '') {
            $activitiesHtml = '<li style="color:#627d98;padding:8px 0;">活動履歴はありません。</li>';
        }

        $address = trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')));

        $content = ''
            . '<div class="card">'
            . '<div class="section-head">'
            . '<div><h1 class="title">顧客詳細</h1></div>'
            . '<div class="actions"><a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a></div>'
            . '</div>'
            . $errorHtml
            . '</div>'
            . '<div class="detail-top">'
            . '<div class="card">'
            . '<div class="section-head"><h2>基本情報</h2>' . self::renderCustomerStatus((string) ($detail['status'] ?? '')) . '</div>'
            . '<dl class="kv-list">'
            . '<dt>顧客名</dt><dd>' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</dd>'
            . '<dt>顧客種別</dt><dd>' . Layout::escape((string) ($detail['customer_type'] ?? '')) . '</dd>'
            . '<dt>電話</dt><dd>' . Layout::escape((string) ($detail['phone'] ?? '')) . '</dd>'
            . '<dt>メール</dt><dd>' . Layout::escape((string) ($detail['email'] ?? '')) . '</dd>'
            . '<dt>住所</dt><dd>' . Layout::escape($address) . '</dd>'
            . '<dt>備考</dt><dd>' . Layout::escape((string) ($detail['note'] ?? '')) . '</dd>'
            . '</dl>'
            . '</div>'
            . '<div class="detail-side">'
            . '<div class="card">'
            . '<div class="section-head"><h2>主連絡先</h2><span class="tag">優先連絡先</span></div>'
            . $primaryContactHtml
            . '</div>'
            . '<div class="card">'
            . '<div class="section-head"><h2>その他連絡先</h2><span class="tag">必要時に参照</span></div>'
            . '<div class="section-stack">' . $otherContactsHtml . '</div>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="card">'
            . '<h2>保有契約</h2>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-spacious">'
            . '<thead><tr>'
            . '<th>証券番号 / 保険会社</th>'
            . '<th>種目</th>'
            . '<th>満期日</th>'
            . '<th>対応ステータス</th>'
            . '<th class="align-right">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $contractsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '<details class="card details-panel details-compact">'
            . '<summary><span>活動履歴</span><span class="muted">' . count($activities) . '件</span></summary>'
            . '<div class="details-compact-body">'
            . '<div style="display:flex;justify-content:flex-end;margin-bottom:10px;">'
            . '<a href="' . $activityNewUrl . '" class="btn btn-primary btn-small">＋ 活動登録</a>'
            . '</div>'
            . '<ul class="panel-list" style="list-style:none;padding:0;margin:0;">' . $activitiesHtml . '</ul>'
            . '</div>'
            . '</details>';

        return Layout::render('顧客詳細', $content, $layoutOptions);
    }

    private static function renderCustomerStatus(string $status): string
    {
        $label = match ($status) {
            'active' => '有効',
            'prospect' => '見込',
            'inactive' => '休眠',
            'closed' => '終了',
            default => '未設定',
        };
        $class = match ($status) {
            'active' => 'status-done',
            'prospect' => 'status-progress',
            'inactive', 'closed' => 'status-inactive',
            default => 'status-open',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderContractStatus(string $status): string
    {
        if ($status === '' || $status === '-') {
            return '<span class="muted">未作成</span>';
        }

        $label = match ($status) {
            'open' => '未対応',
            'contacted' => '対応中',
            'quoted' => '見積提示',
            'waiting' => '回答待ち',
            'renewed' => '完了',
            'lost' => '失注',
            'closed' => '終了',
            default => '未設定',
        };
        $class = match ($status) {
            'renewed', 'closed' => 'status-done',
            'contacted', 'quoted', 'waiting' => 'status-progress',
            'lost' => 'status-inactive',
            default => 'status-open',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }
}
