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
$dashboardController = new DashboardController($authGuard, $tenantFactory, $commonFactory, $config);
$renewalCaseController = new RenewalCaseController($authGuard, $tenantFactory, $commonFactory, $config);
$customerController = new CustomerController($authGuard, $tenantFactory, $config);
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
$router->post('renewal/delete', static function () use ($renewalCaseController): void {
    $renewalCaseController->delete();
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
$router->post('sales/import', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->import();
});
$router->post('accident/update', static function () use ($accidentCaseController): void {
    $accidentCaseController->update();
});
$router->post('accident/store', static function () use ($accidentCaseController): void {
    $accidentCaseController->store();
});
$router->post('accident/comment', static function () use ($accidentCaseController): void {
    $accidentCaseController->comment();
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
$router->post('tenant/settings/staff/create', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->staffCreate();
});
$router->post('tenant/settings/staff/update', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->staffUpdate();
});
$router->post('tenant/settings/staff/delete', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->staffDelete();
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
$router->post('tenant/settings/user/update-display-name', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->userUpdateDisplayName();
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

return $router;
