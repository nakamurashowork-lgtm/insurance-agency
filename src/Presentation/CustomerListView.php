<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class CustomerListView
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
        $phone = Layout::escape((string) ($criteria['phone'] ?? ''));
        $email = Layout::escape((string) ($criteria['email'] ?? ''));
        $status = (string) ($criteria['status'] ?? '');

        $statusOptions = ['' => 'すべて', 'prospect' => 'prospect', 'active' => 'active', 'inactive' => 'inactive', 'closed' => 'closed'];
        $statusHtml = '';
        foreach ($statusOptions as $value => $label) {
            $selected = $status === $value ? ' selected' : '';
            $statusHtml .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $detailUrl = Layout::escape($detailBaseUrl . '&id=' . $id);
            $address = trim((string) (($row['address1'] ?? '') . ' ' . ($row['address2'] ?? '')));

            $rowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['phone'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['email'] ?? '')) . '</td>'
                . '<td>' . Layout::escape($address) . '</td>'
                . '<td>' . Layout::escape((string) ($row['status'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['contract_count'] ?? '0')) . '</td>'
                . '<td><a class="btn" href="' . $detailUrl . '">詳細を見る</a></td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当データはありません。</td></tr>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">顧客一覧</h1>'
            . '<p class="muted">顧客を検索し、顧客詳細へ遷移する画面です。</p>'
            . $errorHtml
            . '<form method="get" action="' . Layout::escape($searchUrl) . '">'
            . '<div class="grid">'
            . '<label>顧客名<input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label>電話番号<input type="text" name="phone" value="' . $phone . '"></label>'
            . '<label>メール<input type="text" name="email" value="' . $email . '"></label>'
            . '<label>ステータス<select name="status">' . $statusHtml . '</select></label>'
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
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">顧客名</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">電話番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">メール</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">住所</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">状態</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">保有契約件数</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">操作</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';

        return Layout::render('顧客一覧', $content);
    }
}
