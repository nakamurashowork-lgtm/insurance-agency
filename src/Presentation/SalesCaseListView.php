<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Domain\SalesCase\SalesCaseRepository;
use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class SalesCaseListView
{
    /**
     * @param array<int, array<string, mixed>>      $rows
     * @param array<string, string>                 $criteria
     * @param array<string, string>                 $listState
     * @param array<int, array{id:int,name:string}> $staffUsers
     * @param array<string, mixed>                  $layoutOptions
     */
    public static function render(
        array $rows,
        int $total,
        array $criteria,
        array $listState,
        array $staffUsers,
        string $listUrl,
        string $newUrl,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
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

        $filterHtml = self::renderFilter($criteria, $listUrl, $staffUsers, $listState);
        $tableHtml  = self::renderTable($rows, $detailBaseUrl, $customerDetailBaseUrl, $listUrl);
        $pager      = ListViewHelper::buildPager(
            (int) $listState['page'],
            (int) $listState['per_page'],
            $total
        );
        $pagerHtml  = self::renderPager($criteria, $listState, $pager, $listUrl);

        $content =
            '<div class="card">'
            . '<div class="section-head">'
            . '<div><h1 class="title">見込案件一覧</h1>'
            . '<span class="muted" style="font-size:13px;">全 ' . $total . ' 件</span></div>'
            . '<div class="actions"><a href="' . Layout::escape($newUrl) . '" class="btn btn-primary">＋ 見込案件登録</a></div>'
            . '</div>'
            . $noticeHtml
            . '</div>'
            . $filterHtml
            . '<div class="card">'
            . '<div class="table-wrap">' . $tableHtml . '</div>'
            . $pagerHtml
            . '</div>';

        return Layout::render('見込案件一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string>                 $criteria
     * @param array<int, array{id:int,name:string}> $staffUsers
     * @param array<string, string>                 $listState
     */
    private static function renderFilter(
        array $criteria,
        string $listUrl,
        array $staffUsers,
        array $listState
    ): string {
        $customerName = Layout::escape($criteria['customer_name'] ?? '');
        $selStatus    = $criteria['status'] ?? '';
        $selRank      = $criteria['prospect_rank'] ?? '';
        $selStaff     = $criteria['staff_user_id'] ?? '';

        $statusOptions = '<option value="">— ステータス —</option>';
        foreach (SalesCaseRepository::ALLOWED_STATUSES as $val => $label) {
            $sel = $selStatus === $val ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        $rankOptions = '<option value="">— 見込度 —</option>';
        foreach (SalesCaseRepository::ALLOWED_PROSPECT_RANKS as $rank) {
            $sel = $selRank === $rank ? ' selected' : '';
            $rankOptions .= '<option value="' . Layout::escape($rank) . '"' . $sel . '>' . Layout::escape($rank) . '</option>';
        }

        $staffOptions = '<option value="">— 担当者 —</option>';
        foreach ($staffUsers as $u) {
            $uid  = (int) ($u['id'] ?? 0);
            $name = Layout::escape((string) ($u['name'] ?? ''));
            $sel  = $selStaff === (string) $uid ? ' selected' : '';
            $staffOptions .= '<option value="' . $uid . '"' . $sel . '>' . $name . '</option>';
        }

        // Preserve sort/direction/per_page as hidden inputs
        $hiddens = '';
        foreach (['sort', 'direction', 'per_page'] as $key) {
            if (isset($listState[$key]) && $listState[$key] !== '') {
                $hiddens .= '<input type="hidden" name="' . $key . '" value="' . Layout::escape($listState[$key]) . '">';
            }
        }

        return '<form method="get" action="' . Layout::escape($listUrl) . '" class="card">'
            . $hiddens
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>顧客名</span>'
            . '<input type="text" name="customer_name" value="' . $customerName . '" placeholder="顧客名で絞り込み"></label>'
            . '<label class="list-filter-field"><span>担当者</span>'
            . '<select name="staff_user_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>ステータス</span>'
            . '<select name="status">' . $statusOptions . '</select></label>'
            . '<label class="list-filter-field"><span>見込度</span>'
            . '<select name="prospect_rank">' . $rankOptions . '</select></label>'
            . '</div>'
            . '<div style="margin-top:10px;display:flex;gap:8px;">'
            . '<button type="submit" class="btn btn-primary btn-small">絞り込む</button>'
            . '<a href="' . Layout::escape(ListViewHelper::buildUrl($listUrl, [])) . '" class="btn btn-ghost btn-small">リセット</a>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function renderTable(
        array $rows,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        string $listUrl
    ): string {
        $thead =
            '<thead><tr>'
            . '<th>顧客名</th>'
            . '<th>案件名 / 種別</th>'
            . '<th>種目</th>'
            . '<th>想定保険料</th>'
            . '<th>見込度</th>'
            . '<th>契約予定月</th>'
            . '<th>ステータス</th>'
            . '<th>担当者</th>'
            . '<th class="align-right">操作</th>'
            . '</tr></thead>';

        if ($rows === []) {
            return '<table class="table-fixed table-spacious">' . $thead
                . '<tbody><tr><td colspan="9">該当する見込案件はありません。</td></tr></tbody></table>';
        }

        $tbody = '<tbody>';
        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $customerId   = (int) ($row['customer_id'] ?? 0);
            $customerName = (string) ($row['customer_name'] ?? '');
            $caseName     = (string) ($row['case_name'] ?? '');
            $caseType     = (string) ($row['case_type'] ?? '');
            $productType  = (string) ($row['product_type'] ?? '');
            $premium      = $row['expected_premium'] !== null ? number_format((int) $row['expected_premium']) : '-';
            $rank         = (string) ($row['prospect_rank'] ?? '');
            $closeMonth   = (string) ($row['expected_contract_month'] ?? '');
            $status       = (string) ($row['status'] ?? '');
            $staffName    = (string) ($row['staff_name'] ?? '');

            $caseTypeLabel = SalesCaseRepository::ALLOWED_CASE_TYPES[$caseType] ?? $caseType;

            $custUrl    = $customerId > 0
                ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $customerId]))
                : '';
            $custLink   = $custUrl !== ''
                ? '<a class="text-link" href="' . $custUrl . '">' . Layout::escape($customerName) . '</a>'
                : Layout::escape($customerName);

            $detailUrl  = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, ['id' => (string) $id]));

            $tbody .= '<tr>'
                . '<td>' . $custLink . '</td>'
                . '<td><div class="cell-stack"><strong>' . Layout::escape($caseName) . '</strong>'
                . '<span class="muted">' . Layout::escape($caseTypeLabel) . '</span></div></td>'
                . '<td>' . Layout::escape($productType) . '</td>'
                . '<td style="text-align:right;">' . Layout::escape($premium) . '</td>'
                . '<td style="text-align:center;">' . self::rankBadge($rank) . '</td>'
                . '<td>' . Layout::escape($closeMonth) . '</td>'
                . '<td>' . self::statusBadge($status) . '</td>'
                . '<td>' . Layout::escape($staffName) . '</td>'
                . '<td class="cell-action"><a href="' . $detailUrl . '" class="btn btn-ghost btn-small">詳細</a></td>'
                . '</tr>';
        }
        $tbody .= '</tbody>';

        return '<table class="table-fixed table-spacious">' . $thead . $tbody . '</table>';
    }

    private static function rankBadge(string $rank): string
    {
        if ($rank === '') {
            return '<span class="muted">-</span>';
        }
        $class = match ($rank) {
            'A' => 'badge-danger',
            'B' => 'badge-warning',
            'C' => 'badge-info',
            default => '',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($rank) . '</span>';
    }

    private static function statusBadge(string $status): string
    {
        $label = SalesCaseRepository::ALLOWED_STATUSES[$status] ?? $status;
        $class = match ($status) {
            'won'         => 'status-done',
            'lost'        => 'status-inactive',
            'open'        => 'status-open',
            'negotiating' => 'status-progress',
            'on_hold'     => 'status-waiting',
            default       => '',
        };

        return '<span class="status-badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed>  $pager
     */
    private static function renderPager(array $criteria, array $listState, array $pager, string $listUrl): string
    {
        if ((int) ($pager['totalPages'] ?? 0) <= 1) {
            return '';
        }

        $links = '';
        if (!empty($pager['hasPrevious'])) {
            $links .= self::renderPagerLink('前へ', (int) ($pager['previousPage'] ?? 1), $criteria, $listState, $listUrl);
        }

        foreach ((array) ($pager['pages'] ?? []) as $pageNumber) {
            $page = (int) $pageNumber;
            if ($page === (int) ($pager['currentPage'] ?? 1)) {
                $links .= '<span class="list-pager-link is-current">' . $page . '</span>';
                continue;
            }
            $links .= self::renderPagerLink((string) $page, $page, $criteria, $listState, $listUrl);
        }

        if (!empty($pager['hasNext'])) {
            $links .= self::renderPagerLink('次へ', (int) ($pager['nextPage'] ?? 1), $criteria, $listState, $listUrl);
        }

        return '<nav class="list-pager" aria-label="ページャー">' . $links . '</nav>';
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderPagerLink(string $label, int $page, array $criteria, array $listState, string $listUrl): string
    {
        $params = array_merge($criteria, $listState, ['page' => (string) $page]);
        $url    = Layout::escape(ListViewHelper::buildUrl($listUrl, $params));

        return '<a class="list-pager-link" href="' . $url . '">' . Layout::escape($label) . '</a>';
    }
}
