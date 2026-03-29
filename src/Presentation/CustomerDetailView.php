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
     */
    public static function render(
        array $detail,
        array $contacts,
        array $contracts,
        array $activities,
        string $listUrl,
        string $renewalDetailBaseUrl,
        ?string $errorMessage
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $contactsHtml = '';
        foreach ($contacts as $row) {
            $primary = ((int) ($row['is_primary'] ?? 0)) === 1 ? '（主）' : '';
            $contactsHtml .= '<li>'
                . Layout::escape((string) ($row['contact_name'] ?? ''))
                . Layout::escape($primary)
                . ' / '
                . Layout::escape((string) ($row['phone'] ?? ''))
                . ' / '
                . Layout::escape((string) ($row['email'] ?? ''))
                . '</li>';
        }
        if ($contactsHtml === '') {
            $contactsHtml = '<li>連絡先はありません。</li>';
        }

        $contractsHtml = '';
        foreach ($contracts as $row) {
            $renewalCaseId = (int) ($row['renewal_case_id'] ?? 0);
            $action = '満期案件未作成';
            if ($renewalCaseId > 0) {
                $url = Layout::escape($renewalDetailBaseUrl . '&id=' . $renewalCaseId);
                $action = '<a class="btn" href="' . $url . '">満期詳細を見る</a>';
            }

            $contractsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['policy_end_date'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['case_status'] ?? '-')) . '</td>'
                . '<td>' . $action . '</td>'
                . '</tr>';
        }
        if ($contractsHtml === '') {
            $contractsHtml = '<tr><td colspan="6">保有契約はありません。</td></tr>';
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

        $address = trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')));

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">顧客詳細</h1>'
            . '<p class="muted">顧客情報と保有契約を確認する画面です。</p>'
            . $errorHtml
            . '<p><strong>顧客名:</strong> ' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</p>'
            . '<p><strong>顧客種別:</strong> ' . Layout::escape((string) ($detail['customer_type'] ?? '')) . '</p>'
            . '<p><strong>電話:</strong> ' . Layout::escape((string) ($detail['phone'] ?? '')) . '</p>'
            . '<p><strong>メール:</strong> ' . Layout::escape((string) ($detail['email'] ?? '')) . '</p>'
            . '<p><strong>住所:</strong> ' . Layout::escape($address) . '</p>'
            . '<p><strong>状態:</strong> ' . Layout::escape((string) ($detail['status'] ?? '')) . '</p>'
            . '<p><strong>備考:</strong> ' . Layout::escape((string) ($detail['note'] ?? '')) . '</p>'
            . '<div style="margin-top:12px;">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">一覧へ戻る</a>'
            . '</div>'
            . '</div>'
            . '<div class="card"><h2>連絡先</h2><ul>' . $contactsHtml . '</ul></div>'
            . '<div class="card">'
            . '<h2>保有契約</h2>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">証券番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">保険会社</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">種目</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">満期日</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">対応ステータス</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $contractsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '<div class="card"><h2>活動履歴</h2><ul>' . $activitiesHtml . '</ul></div>';

        return Layout::render('顧客詳細', $content);
    }
}
