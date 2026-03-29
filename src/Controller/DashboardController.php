<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\DashboardView;
use App\Security\AuthGuard;

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
        $logoutCsrfToken = $this->guard->session()->issueCsrfToken('logout');
        $flashError = $this->guard->session()->consumeFlash('error');

        $tenantDbName = null;
        $warning = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $stmt = $pdo->query('SELECT DATABASE() AS db_name');
            $row = $stmt->fetch();
            $tenantDbName = is_array($row) && isset($row['db_name'])
                ? (string) $row['db_name']
                : null;
        } catch (\Throwable) {
            $warning = 'tenant DBの接続確認に失敗しました。接続設定を確認してください。';
        }

        $permissions = $auth['permissions'] ?? [];
        $isSystemAdmin = is_array($permissions) && !empty($permissions['is_system_admin']);
        $isTenantAdmin = is_array($permissions) && (($permissions['tenant_role'] ?? '') === 'admin');
        $showAdminHelpers = $isSystemAdmin || $isTenantAdmin;

        Responses::html(DashboardView::render(
            $auth,
            $showAdminHelpers,
            $tenantDbName,
            $warning,
            $logoutCsrfToken,
            $flashError,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('customer/list'),
            $this->config->routeUrl('sales/list'),
            $this->config->routeUrl('accident/list'),
            $this->config->routeUrl('tenant/settings'),
            $this->config->routeUrl('logout')
        ));
    }
}
