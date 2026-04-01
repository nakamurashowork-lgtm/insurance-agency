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
    public static function render(array $auth, bool $showAdminHelpers, ?string $flashError, string $renewalListUrl, string $customerListUrl, string $salesListUrl, string $accidentListUrl, string $tenantSettingsUrl, array $layoutOptions): string
    {
        $renewalListLink = Layout::escape($renewalListUrl);
        $salesListLink = Layout::escape($salesListUrl);
        $accidentListLink = Layout::escape($accidentListUrl);

        $summary = is_array($layoutOptions['dashboardSummary'] ?? null)
            ? (array) $layoutOptions['dashboardSummary']
            : [];
        $renewal = is_array($summary['renewal'] ?? null) ? (array) $summary['renewal'] : null;
        $accident = is_array($summary['accident'] ?? null) ? (array) $summary['accident'] : null;
        $salesMonthlyInputCount = array_key_exists('salesMonthlyInputCount', $summary)
            ? (is_int($summary['salesMonthlyInputCount']) ? $summary['salesMonthlyInputCount'] : null)
            : null;

        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $renewalDueTodayCount = self::summaryCount($renewal, 'due_today');
        $renewalUpcoming30Count = self::summaryCount($renewal, 'upcoming_30');
        $accidentOpenCount = self::summaryCount($accident, 'open_count');
        $accidentHighPriorityCount = self::summaryCount($accident, 'high_priority_open_count');
        $accidentResolvedThisMonthCount = self::summaryCount($accident, 'resolved_this_month');

        $content = ''
            . $errorHtml
            . '<div class="card">'
            . '<h2>通常業務の入口</h2>'
            . '<div class="grid">'
            . '<div class="nav-item nav-item-primary">'
            . '<strong>今月の満期フォロー</strong><br>'
            . '<span class="muted">今日期限: ' . self::formatCount($renewalDueTodayCount) . '</span><br>'
            . '<span class="muted">もうすぐ満期: ' . self::formatCount($renewalUpcoming30Count) . '</span><br>'
            . '<a class="text-link" href="' . $renewalListLink . '">満期一覧で確認する</a>'
            . '</div>'
            . '<div class="nav-item nav-item-primary">'
            . '<strong>進行中の事故対応</strong><br>'
            . '<span class="muted">未完了: ' . self::formatCount($accidentOpenCount) . '</span><br>'
            . '<span class="muted">高優先度: ' . self::formatCount($accidentHighPriorityCount) . '</span><br>'
            . '<span class="muted">今月解決: ' . self::formatCount($accidentResolvedThisMonthCount) . '</span><br>'
            . '<a class="text-link" href="' . $accidentListLink . '">事故案件一覧で確認する</a>'
            . '</div>'
            . '<div class="nav-item nav-item-primary">'
            . '<strong>実績の確認と入力</strong><br>'
            . '<span class="muted">今月入力件数: ' . self::formatCount($salesMonthlyInputCount) . '</span><br>'
            . '<a class="text-link" href="' . $salesListLink . '">実績管理一覧を開く</a>'
            . '</div>'
            . '</div>'
            . '</div>';

        return Layout::render('ホーム', $content, $layoutOptions);
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

    private static function formatCount(?int $count): string
    {
        return $count === null
            ? '−（取得失敗）'
            : ((string) $count . '件');
    }
}
