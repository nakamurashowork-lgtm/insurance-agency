<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class AccidentCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     */
    public static function render(
        array $rows,
        array $criteria,
        string $searchUrl,
        string $detailBaseUrl,
        string $dashboardUrl,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $acceptedDateFrom = Layout::escape((string) ($criteria['accepted_date_from'] ?? ''));
        $acceptedDateTo = Layout::escape((string) ($criteria['accepted_date_to'] ?? ''));
        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $productType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $status = (string) ($criteria['status'] ?? '');

        $statusOptions = [
            '' => 'すべて',
            'accepted' => 'accepted',
            'linked' => 'linked',
            'in_progress' => 'in_progress',
            'waiting_docs' => 'waiting_docs',
            'resolved' => 'resolved',
            'closed' => 'closed',
        ];
        $statusHtml = '';
        foreach ($statusOptions as $value => $label) {
            $selected = $status === $value ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $detailUrl = Layout::escape($detailBaseUrl . '&id=' . $id);
            $rowsHtml .= '<tr>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;"><a class="btn" href="' . $detailUrl . '">詳細</a></td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['accident_no'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['accepted_date'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['status'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['priority'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['resolved_date'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="9" style="padding:8px;">該当する事故案件はありません。</td></tr>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">事故案件一覧</h1>'
            . '<p class="muted">管理者向け補助導線です。一覧から詳細へ進みます。</p>'
            . '<p><a class="btn btn-secondary" href="' . Layout::escape($dashboardUrl) . '">ダッシュボードへ戻る</a></p>'
            . $errorHtml
            . $successHtml
            . '<form method="get" action="' . Layout::escape($searchUrl) . '">'
            . '<div class="grid">'
            . '<label>事故受付日From<input type="date" name="accepted_date_from" value="' . $acceptedDateFrom . '"></label>'
            . '<label>事故受付日To<input type="date" name="accepted_date_to" value="' . $acceptedDateTo . '"></label>'
            . '<label>契約者名<input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label>証券番号<input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label>種目<input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label>状態<select name="status">' . $statusHtml . '</select></label>'
            . '</div>'
            . '<div style="margin-top:12px;">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . Layout::escape($searchUrl) . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '<div class="card">'
            . '<p>件数: ' . count($rows) . '</p>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">操作</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">事故管理番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">契約者名</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">証券番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">種目</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">事故受付日</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">状態</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">優先度</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">完了日</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';

        return Layout::render('事故案件一覧', $content);
    }
}
