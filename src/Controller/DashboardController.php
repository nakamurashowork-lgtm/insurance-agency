<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Dashboard\DashboardRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\DashboardView;
use App\Security\AuthGuard;
use DateTimeImmutable;
use Throwable;

final class DashboardController
{
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private CommonConnectionFactory $commonConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function show(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $userId     = (int) ($auth['user_id'] ?? 0);
        $permissions = $auth['permissions'] ?? [];
        $role       = is_array($permissions) ? (string) ($permissions['tenant_role'] ?? 'member') : 'member';
        $tenantCode = (string) ($auth['tenant_code'] ?? '');

        // 担当者選択ドロップダウン パラメータ（デフォルト: all = 全体）
        $renewalUserParam   = (string) ($_GET['renewal_user']    ?? 'all');
        $accidentUserParam  = (string) ($_GET['accident_user']   ?? 'all');
        $salesCaseUserParam = (string) ($_GET['sales_case_user'] ?? 'all');
        $salesUserParam     = (string) ($_GET['sales_user']      ?? 'all');

        $renewalBizFilter   = $this->resolveUserFilter($renewalUserParam,   $userId);
        $accidentBizFilter  = $this->resolveUserFilter($accidentUserParam,  $userId);
        $salesCaseBizFilter = $this->resolveUserFilter($salesCaseUserParam, $userId);
        $staffFilter        = $this->resolveUserFilter($salesUserParam,     $userId);

        // 年度計算（4月始まり）
        $today      = new DateTimeImmutable();
        $month      = (int) $today->format('n');
        $year       = (int) $today->format('Y');
        $currentFiscalYear = ($month >= 4) ? $year : $year - 1;

        // 年度パラメータ（直近3年の範囲内のみ許可）
        $requestedYear = (int) ($_GET['fiscal_year'] ?? $currentFiscalYear);
        $fiscalYear = max($currentFiscalYear - 2, min($currentFiscalYear, $requestedYear));

        $data = [];

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $commonPdo = $this->commonConnectionFactory->create();
            $repo = new DashboardRepository($tenantPdo, $commonPdo, $tenantCode);

            // テナント所属ユーザー一覧（ドロップダウン用）
            try {
                $data['tenant_users'] = $repo->fetchTenantUsers();
            } catch (Throwable) {
                $data['tenant_users'] = [];
            }

            // 要確認エリア: 常に全体（ドロップダウン非連動）
            try {
                $data['renewal'] = $repo->getRenewalAlertCounts(null);
            } catch (Throwable) {
                $data['renewal'] = ['error' => true];
            }
            try {
                $data['accident'] = $repo->getAccidentAlertCounts(null);
            } catch (Throwable) {
                $data['accident'] = ['error' => true];
            }

            // 業務入口カード: 担当者選択ドロップダウン連動
            try {
                $data['renewal_biz'] = $repo->getRenewalAlertCounts($renewalBizFilter);
            } catch (Throwable) {
                $data['renewal_biz'] = ['error' => true];
            }
            try {
                $data['accident_biz'] = $repo->getAccidentAlertCounts($accidentBizFilter);
            } catch (Throwable) {
                $data['accident_biz'] = ['error' => true];
            }
            try {
                $data['sales_case_biz'] = $repo->getSalesCaseAlertCounts($salesCaseBizFilter);
            } catch (Throwable) {
                $data['sales_case_biz'] = ['error' => true];
            }

            try {
                $data['perf_current'] = $repo->getPerformanceMonthlySummary($fiscalYear, $staffFilter);
                $data['perf_prev']    = $repo->getPerformanceMonthlySummary($fiscalYear - 1, $staffFilter);
                $data['targets']      = $repo->getTargetMonthlySummary($fiscalYear, $staffFilter);
            } catch (Throwable) {
                $data['perf_current'] = ['error' => true];
                $data['perf_prev']    = [];
                $data['targets']      = ['error' => true];
            }

            try {
                $data['activity'] = $repo->getActivitySummary($userId);
            } catch (Throwable) {
                $data['activity'] = ['error' => true];
            }

        } catch (Throwable) {
            $data['tenant_users']    = [];
            $data['renewal']         = ['error' => true];
            $data['accident']        = ['error' => true];
            $data['renewal_biz']     = ['error' => true];
            $data['accident_biz']    = ['error' => true];
            $data['sales_case_biz']  = ['error' => true];
            $data['perf_current'] = ['error' => true];
            $data['perf_prev']    = [];
            $data['targets']      = ['error' => true];
            $data['activity']     = ['error' => true];
        }

        $dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $data['fiscal_year']         = $fiscalYear;
        $data['available_years']     = range($currentFiscalYear, $currentFiscalYear - 2, -1);
        $data['current_month']       = $month;
        $data['renewal_user']        = $renewalUserParam;
        $data['accident_user']       = $accidentUserParam;
        $data['sales_case_user']     = $salesCaseUserParam;
        $data['sales_user']          = $salesUserParam;
        $data['role']                = $role;
        $data['today']               = $today->format('Y年n月j日（') . $dayNames[(int) $today->format('w')] . '）';

        $flashError = $this->guard->session()->consumeFlash('error');

        $layoutOptions = array_merge(
            ControllerLayoutHelper::build($this->guard, $this->config, 'dashboard'),
            ['dashboardData' => $data]
        );

        Responses::html(DashboardView::render(
            $auth,
            $flashError,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('sales-case/list'),
            $this->config->routeUrl('sales/list'),
            $this->config->routeUrl('accident/list'),
            $this->config->routeUrl('tenant/settings'),
            $this->config->routeUrl('activity/list'),
            $this->config->routeUrl('activity/daily'),
            $this->config->routeUrl('dashboard'),
            $this->config->appPublicUrl,
            $layoutOptions
        ));
    }

    // ─── ヘルパー ────────────────────────────────────────────────────────

    /**
     * user パラメータを ?int に変換する（テナント検証なし・初期表示用）。
     * 'self' / '' → ログインユーザー ID
     * 'all'       → null（全体）
     * 数値文字列   → その user_id（1以上のみ許可。不正値は self 扱い）
     */
    private function resolveUserFilter(string $param, int $loginUserId): ?int
    {
        if ($param === 'all') {
            return null;
        }
        if ($param === '' || $param === 'self') {
            return $loginUserId;
        }
        $num = filter_var($param, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $num !== false ? (int) $num : $loginUserId;
    }
}
