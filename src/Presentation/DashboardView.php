<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class DashboardView
{
    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $auth,
        bool $showAdminHelpers,
        ?string $flashError,
        string $renewalListUrl,
        string $renewalDetailBaseUrl,
        string $customerListUrl,
        string $salesListUrl,
        string $accidentListUrl,
        string $accidentDetailBaseUrl,
        string $tenantSettingsUrl,
        array $layoutOptions
    ): string {
        $summary = is_array($layoutOptions['dashboardSummary'] ?? null)
            ? (array) $layoutOptions['dashboardSummary']
            : [];
        $renewal  = is_array($summary['renewal'] ?? null)  ? (array) $summary['renewal']  : null;
        $accident = is_array($summary['accident'] ?? null) ? (array) $summary['accident'] : null;
        $salesMonthlyInputCount = array_key_exists('salesMonthlyInputCount', $summary)
            ? (is_int($summary['salesMonthlyInputCount']) ? $summary['salesMonthlyInputCount'] : null)
            : null;
        $renewalRows  = is_array($summary['renewalRows'] ?? null)  ? $summary['renewalRows']  : [];
        $accidentRows = is_array($summary['accidentRows'] ?? null) ? $summary['accidentRows'] : [];
        $activityRows = is_array($summary['activityRows'] ?? null) ? $summary['activityRows'] : [];

        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        // Header: date and user name
        $today = date('Y年n月j日');
        $dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $todayLabel = $today . '（' . $dayNames[(int) date('w')] . '）';
        $displayName = trim((string) ($auth['display_name'] ?? ''));

        // Metrics
        $thisMonthNotCompleted = self::summaryCount($renewal, 'this_month_not_completed');
        $earlyDeadlineOverdue  = self::summaryCount($renewal, 'early_deadline_overdue');
        $accidentOpenCount     = self::summaryCount($accident, 'open_count');
        $accidentNewCount      = self::summaryCount($accident, 'new_accepted_count');
        $activityTodayCount    = count($activityRows);

        // Alert: show when there are incomplete renewal cases this month
        $alertHtml = '';
        if ($thisMonthNotCompleted !== null && $thisMonthNotCompleted > 0) {
            $alertText = '⚠ 満期対応が未完了の契約が <strong>' . $thisMonthNotCompleted . '件</strong> あります。';
            if ($earlyDeadlineOverdue !== null && $earlyDeadlineOverdue > 0) {
                $alertText .= '早期更改締切日を過ぎているものが <strong>' . $earlyDeadlineOverdue . '件</strong> 含まれます。';
            }
            $alertHtml = '<div class="alert alert-warn">' . $alertText . '</div>';
        }

        // Metric grid
        $metricHtml = '<div class="metric-grid">'
            . self::renderMetric(
                '今月満期（未完了）',
                self::formatMetricValue($thisMonthNotCompleted),
                $earlyDeadlineOverdue !== null ? 'うち期限超過 ' . $earlyDeadlineOverdue . '件' : ''
            )
            . self::renderMetric(
                '対応中の事故案件',
                self::formatMetricValue($accidentOpenCount),
                $accidentNewCount !== null ? '新規受付 ' . $accidentNewCount . '件' : ''
            )
            . self::renderMetric(
                '今月実績（件数）',
                self::formatMetricValue($salesMonthlyInputCount),
                ''
            )
            . self::renderMetric(
                '本日の活動予定',
                (string) $activityTodayCount,
                ''
            )
            . '</div>';

        // Renewal upcoming table
        $renewalRowsHtml = '';
        foreach ($renewalRows as $row) {
            $id            = (int) ($row['renewal_case_id'] ?? 0);
            $customerName  = Layout::escape((string) ($row['customer_name'] ?? ''));
            $productType   = Layout::escape((string) ($row['product_type'] ?? ''));
            $maturityDate  = (string) ($row['maturity_date'] ?? '');
            $caseStatus    = (string) ($row['case_status'] ?? '');
            $detailUrl     = Layout::escape($renewalDetailBaseUrl . '&id=' . $id);
            $today         = date('Y-m-d');
            $dateClass     = $maturityDate !== '' && $maturityDate <= $today ? ' style="color:var(--text-danger);font-weight:500;"' : '';
            $renewalRowsHtml .= '<tr>'
                . '<td data-label="顧客名"><a class="text-link" href="' . $detailUrl . '">' . $customerName . '</a></td>'
                . '<td data-label="種目">' . $productType . '</td>'
                . '<td data-label="満期日"' . $dateClass . '>' . Layout::escape($maturityDate) . '</td>'
                . '<td data-label="対応状況">' . self::renderStatusBadge($caseStatus) . '</td>'
                . '</tr>';
        }
        if ($renewalRowsHtml === '') {
            $renewalRowsHtml = '<tr><td colspan="4" class="muted">対象データはありません。</td></tr>';
        }

        $renewalCardHtml = '<div class="card">'
            . '<div class="section-head"><h2 class="section-title">要対応：満期（今後30日）</h2></div>'
            . '<div class="table-wrap"><table class="table-fixed">'
            . '<thead><tr><th>顧客名</th><th>種目</th><th>満期日</th><th>対応状況</th></tr></thead>'
            . '<tbody>' . $renewalRowsHtml . '</tbody>'
            . '</table></div>'
            . '<div class="card-footer-link"><a class="btn" href="' . Layout::escape($renewalListUrl) . '">満期一覧を見る →</a></div>'
            . '</div>';

        // Accident open table
        $accidentRowsHtml = '';
        foreach ($accidentRows as $row) {
            $id           = (int) ($row['accident_case_id'] ?? 0);
            $customerName = Layout::escape((string) ($row['customer_name'] ?? ''));
            $accidentDate = Layout::escape((string) ($row['accident_date'] ?? ($row['accepted_date'] ?? '')));
            $status       = (string) ($row['status'] ?? '');
            $detailUrl    = Layout::escape($accidentDetailBaseUrl . '&id=' . $id);
            $accidentRowsHtml .= '<tr>'
                . '<td data-label="顧客名"><a class="text-link" href="' . $detailUrl . '">' . $customerName . '</a></td>'
                . '<td data-label="事故日">' . $accidentDate . '</td>'
                . '<td data-label="状態">' . self::renderAccidentStatusBadge($status) . '</td>'
                . '</tr>';
        }
        if ($accidentRowsHtml === '') {
            $accidentRowsHtml = '<tr><td colspan="3" class="muted">対象データはありません。</td></tr>';
        }

        $accidentCardHtml = '<div class="card">'
            . '<div class="section-head"><h2 class="section-title">対応中：事故案件</h2></div>'
            . '<div class="table-wrap"><table class="table-fixed">'
            . '<thead><tr><th>顧客名</th><th>事故日</th><th>状態</th></tr></thead>'
            . '<tbody>' . $accidentRowsHtml . '</tbody>'
            . '</table></div>'
            . '<div class="card-footer-link"><a class="btn" href="' . Layout::escape($accidentListUrl) . '">事故案件一覧を見る →</a></div>'
            . '</div>';

        // Activity table
        $activityRowsHtml = '';
        foreach ($activityRows as $row) {
            $startTime    = Layout::escape(self::formatTime((string) ($row['start_time'] ?? '')));
            $customerName = Layout::escape((string) ($row['customer_name'] ?? ''));
            $activityType = Layout::escape((string) ($row['activity_type'] ?? ''));
            $summary      = Layout::escape((string) ($row['content_summary'] ?? ''));
            $resultType   = trim((string) ($row['result_type'] ?? ''));
            $resultBadge  = $resultType !== ''
                ? '<span class="badge badge-success">' . Layout::escape($resultType) . '</span>'
                : '<span class="badge badge-warn">未記録</span>';
            $activityRowsHtml .= '<tr>'
                . '<td data-label="時間">' . $startTime . '</td>'
                . '<td data-label="顧客名">' . $customerName . '</td>'
                . '<td data-label="活動種別">' . $activityType . '</td>'
                . '<td data-label="内容">' . $summary . '</td>'
                . '<td data-label="状態">' . $resultBadge . '</td>'
                . '</tr>';
        }
        $activityTitle = '本日の活動' . ($displayName !== '' ? '（' . Layout::escape($displayName) . '）' : '');
        if ($activityRowsHtml === '') {
            $activityRowsHtml = '<tr><td colspan="5" class="muted">本日の活動記録はありません。</td></tr>';
        }

        $activityCardHtml = '<div class="card">'
            . '<div class="section-head"><h2 class="section-title">' . $activityTitle . '</h2></div>'
            . '<div class="table-wrap"><table class="table-fixed">'
            . '<thead><tr><th>時間</th><th>顧客名</th><th>活動種別</th><th>内容</th><th>状態</th></tr></thead>'
            . '<tbody>' . $activityRowsHtml . '</tbody>'
            . '</table></div>'
            . '</div>';

        $content = $errorHtml
            . '<div class="page-header">'
            . '<div><h1 class="title">ホーム</h1></div>'
            . '<div class="page-header-meta">' . Layout::escape($todayLabel) . ($displayName !== '' ? '&nbsp;&nbsp;' . Layout::escape($displayName) : '') . '</div>'
            . '</div>'
            . $alertHtml
            . $metricHtml
            . '<div class="two-col">'
            . $renewalCardHtml
            . $accidentCardHtml
            . '</div>'
            . $activityCardHtml;

        return Layout::render('ホーム', $content, $layoutOptions);
    }

    private static function renderMetric(string $label, string $value, string $sub): string
    {
        $subHtml = $sub !== '' ? '<div class="metric-sub">' . Layout::escape($sub) . '</div>' : '';
        return '<div class="metric">'
            . '<div class="metric-label">' . Layout::escape($label) . '</div>'
            . '<div class="metric-value">' . $value . '</div>'
            . $subHtml
            . '</div>';
    }

    private static function formatMetricValue(?int $count): string
    {
        return $count === null ? '<span class="metric-na">−</span>' : (string) $count;
    }

    private static function renderStatusBadge(string $status): string
    {
        [$label, $class] = match ($status) {
            'not_started'    => ['未対応',    'badge-gray'],
            'sj_requested'   => ['SJ依頼中',  'badge-info'],
            'doc_prepared'   => ['書類作成済', 'badge-info'],
            'waiting_return' => ['返送待ち',  'badge-warn'],
            'quote_sent'     => ['見積送付済', 'badge-info'],
            'waiting_payment' => ['入金待ち', 'badge-warn'],
            'completed'      => ['完了',      'badge-success'],
            default          => ['未設定',    'badge-gray'],
        };
        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderAccidentStatusBadge(string $status): string
    {
        [$label, $class] = match ($status) {
            'accepted'     => ['新規受付',   'badge-danger'],
            'linked'       => ['紐付済',     'badge-info'],
            'in_progress'  => ['対応中',     'badge-warn'],
            'waiting_docs' => ['書類待ち',   'badge-warn'],
            'resolved'     => ['解決済',     'badge-success'],
            'closed'       => ['完了',       'badge-gray'],
            default        => [$status,      'badge-gray'],
        };
        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function formatTime(string $time): string
    {
        $t = trim($time);
        if ($t === '') {
            return '−';
        }
        // HH:MM:SS → HH:MM
        if (preg_match('/^(\d{2}:\d{2})/', $t, $m)) {
            return $m[1];
        }
        return $t;
    }

    /**
     * @param array<string, mixed>|null $summary
     */
    private static function summaryCount(?array $summary, string $key): ?int
    {
        if (!is_array($summary)) {
            return null;
        }

        $value = $summary[$key] ?? null;
        return is_int($value) ? $value : null;
    }
}
