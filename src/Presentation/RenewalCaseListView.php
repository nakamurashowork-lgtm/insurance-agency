<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class RenewalCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     */
    public static function render(array $rows, array $criteria, string $searchUrl, string $detailBaseUrl, ?string $errorMessage): string
    {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $caseStatus = (string) ($criteria['case_status'] ?? '');
        $from = Layout::escape((string) ($criteria['maturity_date_from'] ?? ''));
        $to = Layout::escape((string) ($criteria['maturity_date_to'] ?? ''));

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id = (int) ($row['renewal_case_id'] ?? 0);
            $detailUrl = Layout::escape($detailBaseUrl . '&id=' . $id);
            $rowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['insurer_name'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['maturity_date'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['case_status'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['next_action_date'] ?? '')) . '</td>'
                . '<td><a class="btn" href="' . $detailUrl . '">詳細を見る</a></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="8">該当データはありません。</td></tr>';
        }

        $statuses = ['' => 'すべて', 'open' => 'open', 'contacted' => 'contacted', 'quoted' => 'quoted', 'waiting' => 'waiting', 'renewed' => 'renewed', 'lost' => 'lost', 'closed' => 'closed'];
        $statusOptions = '';
        foreach ($statuses as $value => $label) {
            $selected = $caseStatus === $value ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">満期一覧</h1>'
            . '<p class="muted">契約一覧を兼ねる画面です。一覧から満期詳細へ遷移します。</p>'
            . $errorHtml
            . '<form method="get" action="' . Layout::escape($searchUrl) . '">'
            . '<div class="grid">'
            . '<label>顧客名<input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label>証券番号<input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label>対応ステータス<select name="case_status">' . $statusOptions . '</select></label>'
            . '<label>満期日(From)<input type="date" name="maturity_date_from" value="' . $from . '"></label>'
            . '<label>満期日(To)<input type="date" name="maturity_date_to" value="' . $to . '"></label>'
            . '</div>'
            . '<div style="margin-top:12px;">'
            . '<button class="btn" type="submit">検索</button>'
            . '<a class="btn btn-secondary" href="' . Layout::escape($searchUrl) . '">条件クリア</a>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '<div class="card">'
            . '<p>件数: ' . count($rows) . '</p>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">顧客名</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">証券番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">保険会社</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">種目</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">満期日</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">対応ステータス</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">次回対応予定日</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';

        return Layout::render('満期一覧', $content);
    }
}