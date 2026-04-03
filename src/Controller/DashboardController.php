<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Dashboard\DashboardRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\DashboardView;
use App\Security\AuthGuard;
use Throwable;

final class DashboardController
{
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function show(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $flashError = $this->guard->session()->consumeFlash('error');
        $dashboardSummary = $this->loadDashboardSummary($auth);

        $permissions = $auth['permissions'] ?? [];
        $isSystemAdmin = is_array($permissions) && !empty($permissions['is_system_admin']);
        $isTenantAdmin = is_array($permissions) && (($permissions['tenant_role'] ?? '') === 'admin');
        $showAdminHelpers = $isSystemAdmin || $isTenantAdmin;

        Responses::html(DashboardView::render(
            $auth,
            $showAdminHelpers,
            $flashError,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('renewal/detail'),
            $this->config->routeUrl('customer/list'),
            $this->config->routeUrl('sales/list'),
            $this->config->routeUrl('accident/list'),
            $this->config->routeUrl('accident/detail'),
            $this->config->routeUrl('tenant/settings'),
            array_merge(
                ControllerLayoutHelper::build($this->guard, $this->config, 'dashboard'),
                ['dashboardSummary' => $dashboardSummary]
            )
        ));
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    private function loadDashboardSummary(array $auth): array
    {
        $summary = [
            'renewal'              => null,
            'accident'             => null,
            'salesMonthlyInputCount' => null,
            'renewalRows'          => [],
            'accidentRows'         => [],
            'activityRows'         => [],
        ];

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new DashboardRepository($pdo);
            $summary['renewal']               = $repository->getRenewalSummary();
            $summary['accident']              = $repository->getAccidentSummary();
            $summary['salesMonthlyInputCount'] = $repository->getSalesMonthlyInputCount(date('Y-m'));
            $summary['renewalRows']           = $repository->getRenewalUpcomingRows(5);
            $summary['accidentRows']          = $repository->getAccidentOpenRows(5);
            $userId = (int) ($auth['user_id'] ?? 0);
            if ($userId > 0) {
                $summary['activityRows'] = $repository->getTodayActivityRows($userId, date('Y-m-d'), 10);
            }
        } catch (Throwable) {
            // Home should remain available even when summary retrieval fails.
        }

        return $summary;
    }
}
