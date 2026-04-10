<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Dashboard\DashboardRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Security\AuthGuard;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * ホーム画面 部分更新用 API コントローラー
 *
 * 各エンドポイントは JSON のみ返す。
 * user パラメータ: self | all | {user_id}
 * 他テナントの user_id を渡した場合は 403 を返す。
 */
final class DashboardApiController
{
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private CommonConnectionFactory $commonConnectionFactory,
        private AppConfig $config
    ) {
    }

    // ─── エンドポイント ───────────────────────────────────────────────

    public function renewalSummary(): void
    {
        $auth       = $this->guard->requireAuthenticated();
        $loginId    = (int) ($auth['user_id']    ?? 0);
        $tenantCode = (string) ($auth['tenant_code'] ?? '');

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo       = new DashboardRepository($tenantPdo, $commonPdo, $tenantCode);

            $userParam   = (string) ($_GET['user'] ?? 'all');
            $staffFilter = $this->resolveUserFilter($userParam, $loginId, $tenantCode, $commonPdo);
            $counts      = $repo->getRenewalAlertCounts($staffFilter);
        } catch (Throwable $e) {
            Responses::json(['error' => $e->getMessage()], 500);
        }

        Responses::json([
            'user_id'         => $staffFilter,
            'user_label'      => $this->buildUserLabel($userParam, $loginId, $staffFilter, $auth, $commonPdo ?? null, $tenantCode),
            'within_14_days'  => $counts['within_14d'],
            'within_28_days'  => $counts['within_28d'],
            'within_60_days'  => $counts['within_60d'],
        ]);
    }

    public function accidentSummary(): void
    {
        $auth       = $this->guard->requireAuthenticated();
        $loginId    = (int) ($auth['user_id']    ?? 0);
        $tenantCode = (string) ($auth['tenant_code'] ?? '');

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo       = new DashboardRepository($tenantPdo, $commonPdo, $tenantCode);

            $userParam   = (string) ($_GET['user'] ?? 'all');
            $staffFilter = $this->resolveUserFilter($userParam, $loginId, $tenantCode, $commonPdo);
            $counts      = $repo->getAccidentAlertCounts($staffFilter);
        } catch (Throwable $e) {
            Responses::json(['error' => $e->getMessage()], 500);
        }

        Responses::json([
            'user_id'         => $staffFilter,
            'user_label'      => $this->buildUserLabel($userParam, $loginId, $staffFilter, $auth, $commonPdo ?? null, $tenantCode),
            'high_priority'   => $counts['high_priority'],
            'normal_priority' => $counts['mid_priority'],
            'low_priority'    => $counts['low_priority'],
        ]);
    }

    public function salesCaseSummary(): void
    {
        $auth       = $this->guard->requireAuthenticated();
        $loginId    = (int) ($auth['user_id']    ?? 0);
        $tenantCode = (string) ($auth['tenant_code'] ?? '');

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo       = new DashboardRepository($tenantPdo, $commonPdo, $tenantCode);

            $userParam   = (string) ($_GET['user'] ?? 'all');
            $staffFilter = $this->resolveUserFilter($userParam, $loginId, $tenantCode, $commonPdo);
            $counts      = $repo->getSalesCaseAlertCounts($staffFilter);
        } catch (Throwable $e) {
            Responses::json(['error' => $e->getMessage()], 500);
        }

        Responses::json([
            'user_id'            => $staffFilter,
            'user_label'         => $this->buildUserLabel($userParam, $loginId, $staffFilter, $auth, $commonPdo ?? null, $tenantCode),
            'prospect_a'         => $counts['rank_a'],
            'prospect_b'         => $counts['rank_b'],
            'expected_this_month'=> $counts['closing_this_month'],
        ]);
    }

    public function salesPerformanceSummary(): void
    {
        $auth       = $this->guard->requireAuthenticated();
        $loginId    = (int) ($auth['user_id']    ?? 0);
        $tenantCode = (string) ($auth['tenant_code'] ?? '');

        $today             = new DateTimeImmutable();
        $currentMonth      = (int) $today->format('n');
        $currentYear       = (int) $today->format('Y');
        $currentFiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

        $requestedYear = (int) ($_GET['fiscal_year'] ?? $currentFiscalYear);
        $fiscalYear    = max($currentFiscalYear - 2, min($currentFiscalYear, $requestedYear));

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo       = new DashboardRepository($tenantPdo, $commonPdo, $tenantCode);

            $userParam   = (string) ($_GET['user'] ?? 'all');
            $staffFilter  = $this->resolveUserFilter($userParam, $loginId, $tenantCode, $commonPdo);
            $perfCurrent  = $repo->getPerformanceMonthlySummary($fiscalYear, $staffFilter);
            $perfPrev     = $repo->getPerformanceMonthlySummary($fiscalYear - 1, $staffFilter);
            $targets      = $repo->getTargetMonthlySummary($fiscalYear, $staffFilter);
        } catch (Throwable $e) {
            Responses::json(['error' => $e->getMessage()], 500);
        }

        $targetMonthly = is_array($targets['monthly'] ?? null) ? (array) $targets['monthly'] : [];
        $targetAnnual  = isset($targets['annual']) ? (int) $targets['annual'] : null;

        // 年度累計
        // 現在年度: 4月〜今月。過去年度: 4月〜3月（全12ヶ月）
        $fiscalMonths  = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        $cutoffMonth   = ($fiscalYear < $currentFiscalYear) ? 3 : $currentMonth;
        $annualCurrent = 0;
        $annualPrev    = 0;
        foreach ($fiscalMonths as $m) {
            $annualCurrent += (int) ($perfCurrent[$m]['premium'] ?? 0);
            $annualPrev    += (int) ($perfPrev[$m]['premium']    ?? 0);
            if ($m === $cutoffMonth) {
                break;
            }
        }

        // 前年比
        $yoyPct = ($annualPrev > 0) ? (int) floor($annualCurrent / $annualPrev * 100) : null;

        // 月次推移
        $monthlyTrend    = [];
        $apiCumCurrent   = 0;
        $apiTargetThou   = ($targets['annual'] ?? null) !== null && (int) ($targets['annual'] ?? 0) > 0
            ? (int) floor((int) $targets['annual'] / 1000) : null;
        foreach ($fiscalMonths as $m) {
            // 未来月（当年度で今月より後）は current を null にする
            $isFuture = ($fiscalYear === $currentFiscalYear) && self::isAfterCurrentMonth($m, $currentMonth);
            $cur      = $isFuture ? null : (int) floor(($perfCurrent[$m]['premium'] ?? 0) / 1000);
            $rawPrev  = (int) ($perfPrev[$m]['premium'] ?? 0);
            $prev     = (!$isFuture && $rawPrev > 0) ? (int) floor($rawPrev / 1000) : null;
            $diff     = (!$isFuture && ($cur !== null || $prev !== null))
                ? ($cur ?? 0) - ($prev ?? 0)
                : null;
            $cumAchRate = null;
            if (!$isFuture) {
                $apiCumCurrent += ($cur ?? 0);
                if ($apiTargetThou !== null && $apiTargetThou > 0) {
                    $cumAchRate = round($apiCumCurrent / $apiTargetThou * 100, 1);
                }
            }
            $monthlyTrend[] = [
                'month'                          => $m,
                'current'                        => $isFuture ? null : ($cur === 0 ? null : $cur),
                'previous'                       => $prev,
                'diff'                           => $diff,
                'cumulative_achievement_rate_pct'=> $cumAchRate,
                'is_future'                      => $isFuture,
            ];
        }

        $userLabel = $this->buildUserLabel($userParam, $loginId, $staffFilter, $auth, $commonPdo ?? null, $tenantCode);

        $monthNames  = [1=>'1月',2=>'2月',3=>'3月',4=>'4月',5=>'5月',6=>'6月',
                        7=>'7月',8=>'8月',9=>'9月',10=>'10月',11=>'11月',12=>'12月'];
        $periodLabel = ($fiscalYear < $currentFiscalYear)
            ? $fiscalYear . '年度（4月〜3月）'
            : $fiscalYear . '年度 4月〜' . ($monthNames[$currentMonth] ?? '');

        $annualAchievementRatePct = ($apiTargetThou !== null && $apiTargetThou > 0)
            ? round(floor($annualCurrent / 1000) / $apiTargetThou * 100, 1)
            : null;

        Responses::json([
            'user_id'          => $staffFilter,
            'user_label'       => $userLabel,
            'fiscal_year'      => $fiscalYear,
            'is_current_fy'    => ($fiscalYear === $currentFiscalYear),
            'current_month'    => $currentMonth,
            'year_total'  => [
                'amount_thousand_yen'      => (int) floor($annualCurrent / 1000),
                'period_label'             => $periodLabel,
                'previous_year_same_period'=> $annualPrev > 0 ? (int) floor($annualPrev / 1000) : null,
                'year_over_year_pct'       => $yoyPct,
                'achievement_rate_pct'     => $annualAchievementRatePct,
            ],
            'monthly_trend' => $monthlyTrend,
            'annual_total'  => [
                'current'  => (int) floor($annualCurrent / 1000),
                'previous' => $annualPrev > 0 ? (int) floor($annualPrev / 1000) : null,
                'diff'     => ($annualCurrent > 0 || $annualPrev > 0)
                    ? (int) floor(($annualCurrent - $annualPrev) / 1000)
                    : null,
            ],
            'target'        => [
                'yearly' => $targetAnnual !== null ? (int) floor($targetAnnual / 1000) : null,
            ],
        ]);
    }

    // ─── ヘルパー ────────────────────────────────────────────────────────

    /**
     * user パラメータを ?int に変換する。他テナントの user_id は 403 で拒否。
     *
     * @param string $param  'self' | 'all' | '{user_id}'
     * @throws \RuntimeException DB エラー時
     */
    private function resolveUserFilter(string $param, int $loginId, string $tenantCode, PDO $commonPdo): ?int
    {
        if ($param === 'all') {
            return null;
        }
        if ($param === '' || $param === 'self') {
            return $loginId;
        }
        $num = filter_var($param, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($num === false) {
            return $loginId;
        }
        $userId = (int) $num;
        if ($userId === $loginId) {
            return $loginId;
        }

        // 他ユーザーの場合: テナント所属確認（403 or return）
        $stmt = $commonPdo->prepare(
            'SELECT 1
             FROM user_tenants ut
             JOIN users u ON u.id = ut.user_id
             WHERE ut.user_id     = :user_id
               AND ut.tenant_code = :tenant_code
               AND ut.is_deleted  = 0
               AND ut.status      = 1
               AND u.is_deleted   = 0
             LIMIT 1'
        );
        $stmt->bindValue(':user_id',     $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':tenant_code', $tenantCode);
        $stmt->execute();

        if (!$stmt->fetchColumn()) {
            Responses::json(['error' => 'forbidden'], 403);
        }

        return $userId;
    }

    /**
     * レスポンス用ユーザーラベルを生成する。
     *
     * @param array<string, mixed> $auth
     */
    private function buildUserLabel(
        string $param,
        int $loginId,
        ?int $resolvedId,
        array $auth,
        ?PDO $commonPdo,
        string $tenantCode
    ): string {
        if ($resolvedId === null) {
            return '全体';
        }

        $loginDisplayName = (string) ($auth['display_name'] ?? $auth['name'] ?? '');

        if ($resolvedId === $loginId) {
            return '自分（' . $loginDisplayName . '）';
        }

        // 他ユーザーの display_name を取得
        if ($commonPdo === null) {
            return (string) $resolvedId;
        }
        try {
            $stmt = $commonPdo->prepare(
                'SELECT COALESCE(NULLIF(display_name, ""), name) AS display_name
                 FROM users WHERE id = :id AND is_deleted = 0 LIMIT 1'
            );
            $stmt->bindValue(':id', $resolvedId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? (string) ($row['display_name'] ?? (string) $resolvedId) : (string) $resolvedId;
        } catch (Throwable) {
            return (string) $resolvedId;
        }
    }

    /**
     * 当年度において $month が今月より後（未来）かどうかを判定する。
     * 年度は 4 月始まりなので月の順序は [4,5,6,7,8,9,10,11,12,1,2,3]。
     */
    private static function isAfterCurrentMonth(int $month, int $currentMonth): bool
    {
        $order = [4 => 0, 5 => 1, 6 => 2, 7 => 3, 8 => 4, 9 => 5,
                  10 => 6, 11 => 7, 12 => 8, 1 => 9, 2 => 10, 3 => 11];
        return ($order[$month] ?? 0) > ($order[$currentMonth] ?? 0);
    }
}
