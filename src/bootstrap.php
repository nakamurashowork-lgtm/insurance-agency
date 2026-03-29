<?php
declare(strict_types=1);

use App\EnvLoader;
use App\AppConfig;
use App\Auth\AuthService;
use App\Auth\TenantResolver;
use App\Controller\AccidentCaseController;
use App\Controller\AuthController;
use App\Controller\CustomerController;
use App\Controller\DashboardController;
use App\Controller\RenewalCaseController;
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
$authController = new AuthController($config, $session, $googleOAuthClient, $authService);
$dashboardController = new DashboardController($authGuard, $tenantFactory, $config);
$renewalCaseController = new RenewalCaseController($authGuard, $tenantFactory, $config);
$customerController = new CustomerController($authGuard, $tenantFactory, $config);
$salesPerformanceController = new SalesPerformanceController($authGuard, $tenantFactory, $config);
$accidentCaseController = new AccidentCaseController($authGuard, $tenantFactory, $config);
$tenantSettingsController = new TenantSettingsController($authGuard, $tenantFactory, $commonFactory, $config);

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
$router->get('sales/list', static function () use ($salesPerformanceController): void {
    $salesPerformanceController->list();
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
$router->post('accident/comment', static function () use ($accidentCaseController): void {
    $accidentCaseController->comment();
});
$router->post('tenant/settings/notify', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->saveNotify();
});
$router->post('tenant/settings/phase', static function () use ($tenantSettingsController): void {
    $tenantSettingsController->savePhase();
});
$router->get('auth/google/start', static function () use ($authController): void {
    $authController->startGoogle();
});
$router->get('auth/google/callback', static function () use ($authController): void {
    $authController->handleGoogleCallback();
});
$router->post('logout', static function () use ($authController): void {
    $authController->logout();
});

return $router;
