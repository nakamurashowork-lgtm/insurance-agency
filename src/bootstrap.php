<?php
declare(strict_types=1);

use App\EnvLoader;
use App\AppConfig;
use App\Auth\AuthService;
use App\Auth\TenantResolver;
use App\Controller\AccidentCaseController;
use App\Controller\ActivityController;
use App\Controller\AuthController;
use App\Controller\CustomerController;
use App\Controller\DashboardApiController;
use App\Controller\DashboardController;
use App\Controller\RenewalCaseController;
use App\Controller\SalesCaseController;
use App\Controller\SalesPerformanceController;
use App\Controller\TenantSettingsController;
use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\UserRepository;
use App\Http\Responses;
use App\Http\Router;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\Controller\LoginController;
use App\SessionManager;
use App\Security\AuthGuard;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

EnvLoader::load(dirname(__DIR__) . '/.env');

$config = AppConfig::fromEnv();

$session = new SessionManager($config);
$session->start();

$commonFactory = new CommonConnectionFactory($config);
$tenantFactory = new TenantConnectionFactory($config);

$userRepository = new UserRepository($commonFactory);
$tenantResolver = new TenantResolver($commonFactory);
$authService = new AuthService($session, $userRepository, $tenantResolver);
$googleOAuthClient = new GoogleOAuthClient($config);

$authGuard = new AuthGuard($session, $config);

$loginController = new LoginController($session, $config);
$authController = new AuthController($config, $session, $googleOAuthClient, $authService, $authGuard, $userRepository, $tenantResolver);
$dashboardController    = new DashboardController($authGuard, $tenantFactory, $commonFactory, $config);
$dashboardApiController = new DashboardApiController($authGuard, $tenantFactory, $commonFactory, $config);
$renewalCaseController = new RenewalCaseController($authGuard, $tenantFactory, $commonFactory, $config);
$customerController = new CustomerController($authGuard, $tenantFactory, $commonFactory, $config);
$salesPerformanceController = new SalesPerformanceController($authGuard, $tenantFactory, $commonFactory, $config);
$accidentCaseController = new AccidentCaseController($authGuard, $tenantFactory, $commonFactory, $config);
$activityController = new ActivityController($authGuard, $tenantFactory, $config);
$tenantSettingsController = new TenantSettingsController($authGuard, $tenantFactory, $commonFactory, $config);
$salesCaseController = new SalesCaseController($authGuard, $tenantFactory, $config);

$router = new Router($config->appUrl);

$router->get('', static function () use ($session, $config): void {
    if ($session->isAuthenticated()) {
        Responses::redirect($config->routeUrl('dashboard'));
    }

    Responses::redirect($config->routeUrl('login'));
});

$router->get('login', static function () use ($loginController): void {
    $loginController->show();
});
$router->get('dashboard', static function () use ($dashboardController): void {
    $dashboardController->show();
});
$router->get('api/dashboard/renewal-summary', static function () use ($dashboardApiController): void {
    $dashboardApiController->renewalSummary();
});
$router->get('api/dashboard/accident-summary', static function () use ($dashboardApiController): void {
    $dashboardApiController->accidentSummary();
});
$router->get('api/dashboard/sales-case-summary', static function () use ($dashboardApiController): void {
    $dashboardApiController->salesCaseSummary();
});
$router->get('api/dashboard/sales-performance-summary', static function () use ($dashboardApiController): void {
    $dashboardApiController->salesPerformanceSummary();
});
$router->get('renewal/list', static function () use ($renewalCaseController): void {
    $renewalCaseController->list();
});
$router->get('customer/list', static function () use ($customerController): void {
    $customerController->list();
});
$router->get('customer/detail', static function () use ($customerController): void {
    $customerController->detail();
});
$router->post('customer/create', static function () use ($customerController): void {
    $customerController->create();
});
$router->post('customer/update', static function () use ($customerController): void {
    $customerController->update();
});
$router->get('sales/list', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->list();
});
$router->get('sales/detail', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->detail();
});
$router->get('sales/bulk', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->bulkView();
});
$router->post('sales/bulk/row-save', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->bulkRowSave();
});
$router->get('accident/list', static function () use ($accidentCaseController): void {
    $accidentCaseController->list();
});
$router->get('accident/detail', static function () use ($accidentCaseController): void {
    $accidentCaseController->detail();
});
$router->get('tenant/settings', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->show();
});
$router->get('renewal/detail', static function () use ($renewalCaseController): void {
    $renewalCaseController->detail();
});
$router->post('renewal/update', static function () use ($renewalCaseController): void {
    $renewalCaseController->update();
});
$router->post('renewal/comment', static function () use ($renewalCaseController): void {
    $renewalCaseController->comment();
});
$router->post('renewal/import', static function () use ($renewalCaseController): void {
    $renewalCaseController->import();
});
$router->post('renewal/link-customer', static function () use ($renewalCaseController): void {
    $renewalCaseController->linkCustomer();
});
$router->post('renewal/update-assigned-staff', static function () use ($renewalCaseController): void {
    $renewalCaseController->updateAssignedStaff();
});
$router->get('api/customer/search-for-link', static function () use ($customerController): void {
    $customerController->searchForLink();
});
$router->post('sales/create', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->create();
});
$router->post('sales/update', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->update();
});
$router->post('sales/delete', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->delete();
});
$router->post('accident/update', static function () use ($accidentCaseController): void {
    $accidentCaseController->update();
});
$router->post('accident/store', static function () use ($accidentCaseController): void {
    $accidentCaseController->store();
});
$router->post('accident/delete', static function () use ($accidentCaseController): void {
    $accidentCaseController->delete();
});
$router->post('accident/comment', static function () use ($accidentCaseController): void {
    $accidentCaseController->comment();
});
$router->post('accident/reminder', static function () use ($accidentCaseController): void {
    $accidentCaseController->reminder();
});
$router->post('accident/update_basic', static function () use ($accidentCaseController): void {
    $accidentCaseController->updateBasic();
});
$router->get('activity/list', static function () use ($activityController): void {
    $activityController->list();
});
$router->get('activity/detail', static function () use ($activityController): void {
    $activityController->detail();
});
$router->post('activity/store', static function () use ($activityController): void {
    $activityController->store();
});
$router->post('activity/update', static function () use ($activityController): void {
    $activityController->update();
});
$router->post('activity/delete', static function () use ($activityController): void {
    $activityController->delete();
});
$router->get('activity/daily', static function () use ($activityController): void {
    $activityController->daily();
});
$router->post('activity/comment', static function () use ($activityController): void {
    $activityController->saveComment();
});
$router->post('activity/submit', static function () use ($activityController): void {
    $activityController->submit();
});
$router->post('tenant/settings/notify', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->saveNotify();
});
$router->post('tenant/settings/notify-renewal', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->saveRenewalNotify();
});
$router->post('tenant/settings/notify-accident', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->saveAccidentNotify();
});
$router->post('tenant/settings/phase', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->savePhase();
});
$router->post('tenant/settings/all', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->saveAll();
});
$router->post('tenant/settings/procedure-method/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodCreate();
});
$router->post('tenant/settings/procedure-method/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodUpdate();
});
$router->post('tenant/settings/procedure-method/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodDeactivate();
});
$router->post('tenant/settings/procedure-method/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodActivate();
});
$router->post('tenant/settings/procedure-method/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodDelete();
});
$router->post('tenant/settings/procedure-method/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->procedureMethodReorder();
});
$router->post('tenant/settings/purpose-type/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeCreate();
});
$router->post('tenant/settings/purpose-type/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeUpdate();
});
$router->post('tenant/settings/purpose-type/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeDeactivate();
});
$router->post('tenant/settings/purpose-type/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeActivate();
});
$router->post('tenant/settings/purpose-type/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeDelete();
});
$router->post('tenant/settings/purpose-type/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->purposeTypeReorder();
});
$router->post('tenant/settings/activity-type/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeCreate();
});
$router->post('tenant/settings/activity-type/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeUpdate();
});
$router->post('tenant/settings/activity-type/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeDeactivate();
});
$router->post('tenant/settings/activity-type/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeActivate();
});
$router->post('tenant/settings/activity-type/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeDelete();
});
$router->post('tenant/settings/activity-type/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->activityTypeReorder();
});
$router->post('tenant/settings/renewal-method/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodCreate();
});
$router->post('tenant/settings/renewal-method/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodUpdate();
});
$router->post('tenant/settings/renewal-method/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodDeactivate();
});
$router->post('tenant/settings/renewal-method/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodActivate();
});
$router->post('tenant/settings/renewal-method/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodDelete();
});
$router->post('tenant/settings/renewal-method/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->renewalMethodReorder();
});
$router->post('tenant/settings/status/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusCreate();
});
$router->post('tenant/settings/status/update-name', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusUpdateDisplayName();
});
$router->post('tenant/settings/status/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusDeactivate();
});
$router->post('tenant/settings/status/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusActivate();
});
$router->post('tenant/settings/status/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusDelete();
});
$router->post('tenant/settings/status/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->statusReorder();
});
$router->post('tenant/settings/sales-case-status/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusCreate();
});
$router->post('tenant/settings/sales-case-status/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusUpdate();
});
$router->post('tenant/settings/sales-case-status/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusDeactivate();
});
$router->post('tenant/settings/sales-case-status/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusActivate();
});
$router->post('tenant/settings/sales-case-status/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusDelete();
});
$router->post('tenant/settings/sales-case-status/reorder', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesCaseStatusReorder();
});
$router->post('tenant/settings/category/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->categoryCreate();
});
$router->post('tenant/settings/category/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->categoryUpdate();
});
$router->post('tenant/settings/category/deactivate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->categoryDeactivate();
});
$router->post('tenant/settings/category/activate', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->categoryActivate();
});
$router->post('tenant/settings/category/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->categoryDelete();
});
$router->post('tenant/settings/user/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->userUpdate();
});
$router->post('tenant/sales-target/save', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesTargetSave();
});
$router->post('tenant/sales-target/bulk-save', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesTargetBulkSave();
});
$router->post('tenant/sales-target/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->salesTargetDelete();
});
$router->get('sales-case/list', static function () use ($salesCaseController): void {
    $salesCaseController->list();
});
$router->get('sales-case/detail', static function () use ($salesCaseController): void {
    $salesCaseController->detail();
});
$router->post('sales-case/store', static function () use ($salesCaseController): void {
    $salesCaseController->store();
});
$router->post('sales-case/update', static function () use ($salesCaseController): void {
    $salesCaseController->update();
});
$router->post('sales-case/delete', static function () use ($salesCaseController): void {
    $salesCaseController->delete();
});
$router->get('auth/google/start', static function () use ($authController): void {
    $authController->startGoogle();
});
$router->get('auth/google/callback', static function () use ($authController): void {
    $authController->handleGoogleCallback();
});
$router->get('auth/totp', static function () use ($authController): void {
    $authController->totpShow();
});
$router->post('auth/totp/verify', static function () use ($authController): void {
    $authController->totpVerify();
});
$router->get('auth/totp-setup', static function () use ($authController): void {
    $authController->totpSetupShow();
});
$router->post('auth/totp-setup/verify', static function () use ($authController): void {
    $authController->totpSetupVerify();
});
$router->post('logout', static function () use ($authController): void {
    $authController->logout();
});

// 開発/ステージング用裏口ログイン（APP_ENV=local / staging のみ有効）
$devLoginAllowedEmails = [
    'local'   => ['dev@local.test'],
    'staging' => ['staff1@te002.test', 'staff2@te002.test'],
];
$currentEnv = (string) ($config->appEnv ?? '');
if (isset($devLoginAllowedEmails[$currentEnv])) {
    $router->get('dev/login', static function () use ($session, $config, $commonFactory, $devLoginAllowedEmails, $currentEnv): void {
        $allowed = $devLoginAllowedEmails[$currentEnv];
        $email = isset($_GET['email']) && is_string($_GET['email']) ? $_GET['email'] : $allowed[0];
        if (!in_array($email, $allowed, true)) {
            http_response_code(400);
            echo '<h1>dev login error: email not allowed</h1>';
            return;
        }
        $pdo = $commonFactory->create();
        $stmt = $pdo->prepare(
            'SELECT u.id AS user_id, u.name, u.display_name, u.is_system_admin,
                    t.id AS tenant_id, t.tenant_code, t.tenant_name, t.db_name, ut.role
             FROM users u
             JOIN user_tenants ut ON ut.user_id = u.id
             JOIN tenants t ON t.tenant_code = ut.tenant_code
             WHERE u.email = :email AND u.is_deleted = 0 AND ut.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            http_response_code(500);
            echo '<h1>dev login error: user not found</h1>';
            return;
        }

        $session->setAuth([
            'user_id'        => (int) $row['user_id'],
            'display_name'   => (string) ($row['display_name'] !== '' ? $row['display_name'] : $row['name']),
            'tenant_id'      => (int) $row['tenant_id'],
            'tenant_code'    => (string) $row['tenant_code'],
            'tenant_name'    => (string) $row['tenant_name'],
            'tenant_db_name' => (string) $row['db_name'],
            'permissions'    => [
                'is_system_admin' => ((int) $row['is_system_admin']) === 1,
                'tenant_role'     => (string) $row['role'],
            ],
        ]);

        \App\Http\Responses::redirect($config->routeUrl('dashboard'));
    });
}

return $router;
