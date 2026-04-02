<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Security\AuthGuard;

final class ControllerLayoutHelper
{
    /**
     * @param array<int, array{label:string,url?:string}> $breadcrumbs
     * @return array<string, mixed>
     */
    public static function build(
        AuthGuard $guard,
        AppConfig $config,
        string $activeNav,
        array $breadcrumbs = []
    ): array {
        $navLinks = [
            'dashboard'  => $config->routeUrl('dashboard'),
            'renewal'    => $config->routeUrl('renewal/list'),
            'sales'      => $config->routeUrl('sales/list'),
            'customer'   => $config->routeUrl('customer/list'),
            'activity'   => $config->routeUrl('activity/list'),
            'sales_case' => $config->routeUrl('sales-case/list'),
            'accident'   => $config->routeUrl('accident/list'),
            'settings'   => $config->routeUrl('tenant/settings'),
        ];

        return [
            'showHeader' => true,
            'activeNav' => $activeNav,
            'activeAdmin' => $activeNav === 'settings' ? 'settings' : '',
            'breadcrumbs' => $breadcrumbs,
            'logoutAction' => $config->routeUrl('logout'),
            'logoutCsrfToken' => $guard->session()->issueCsrfToken('logout'),
            'navLinks' => $navLinks,
        ];
    }
}