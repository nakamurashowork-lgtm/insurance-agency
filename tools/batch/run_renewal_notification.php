<?php
declare(strict_types=1);

use App\AppConfig;
use App\Domain\Notification\RenewalNotificationBatchRepository;
use App\Domain\Notification\RenewalNotificationBatchService;
use App\EnvLoader;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$root = dirname(__DIR__, 2);
EnvLoader::load($root . DIRECTORY_SEPARATOR . '.env');
$config = AppConfig::fromEnv();

$args = $argv;
array_shift($args);
$runDate = date('Y-m-d');
$tenantCodeFilter = null;
$executedBy = 1;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $runDate = substr($arg, strlen('--date='));
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantCodeFilter = substr($arg, strlen('--tenant='));
    } elseif (str_starts_with($arg, '--executed-by=')) {
        $executedBy = (int) substr($arg, strlen('--executed-by='));
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    fwrite(STDERR, "Invalid --date. expected YYYY-MM-DD\n");
    exit(2);
}
if ($executedBy <= 0) {
    fwrite(STDERR, "Invalid --executed-by. expected positive integer\n");
    exit(2);
}

$commonFactory = new CommonConnectionFactory($config);
$tenantFactory = new TenantConnectionFactory($config);
$commonPdo = $commonFactory->create();

$routeCheckStmt = $commonPdo->prepare(
        'SELECT r.is_enabled AS route_enabled,
                        t.is_enabled AS target_enabled,
                        t.webhook_url
         FROM tenant_notify_routes r
         INNER JOIN tenant_notify_targets t
                         ON t.id = r.destination_id
                        AND t.is_deleted = 0
         WHERE r.tenant_code = :tenant_code
             AND r.notification_type = "renewal"
             AND r.is_deleted = 0
         LIMIT 1'
);

$sql = 'SELECT tenant_code, db_name
        FROM tenants
        WHERE status = 1
          AND is_deleted = 0';
$params = [];
if (is_string($tenantCodeFilter) && $tenantCodeFilter !== '') {
    $sql .= ' AND tenant_code = :tenant_code';
    $params['tenant_code'] = $tenantCodeFilter;
}
$sql .= ' ORDER BY tenant_code ASC';

$stmt = $commonPdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();
if (!is_array($tenants) || $tenants === []) {
    echo json_encode([
        'run_date' => $runDate,
        'tenants' => [],
        'error' => 'NO_ACTIVE_TENANTS',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$results = [];
$allSuccess = true;
foreach ($tenants as $tenant) {
    $tenantCode = (string) ($tenant['tenant_code'] ?? '');
    $dbName = (string) ($tenant['db_name'] ?? '');
    try {
        $tenantPdo = $tenantFactory->createByDbName($dbName);
        $repository = new RenewalNotificationBatchRepository($tenantPdo);
        $service = new RenewalNotificationBatchService($repository);

        $routeCheckStmt->execute(['tenant_code' => $tenantCode]);
        $route = $routeCheckStmt->fetch();
        $routeEnabled = is_array($route)
            && (int) ($route['route_enabled'] ?? 0) === 1
            && (int) ($route['target_enabled'] ?? 0) === 1
            && trim((string) ($route['webhook_url'] ?? '')) !== '';

        $summary = $service->run($runDate, $executedBy, $routeEnabled);
        $summary['tenant_code'] = $tenantCode;
        $summary['tenant_db_name'] = $dbName;
        $summary['route_enabled'] = $routeEnabled;
        $results[] = $summary;

        if (!in_array((string) ($summary['result'] ?? ''), ['success', 'partial'], true)) {
            $allSuccess = false;
        }
    } catch (Throwable $e) {
        $results[] = [
            'tenant_code' => $tenantCode,
            'tenant_db_name' => $dbName,
            'result' => 'failed',
            'error_message' => $e->getMessage(),
        ];
        $allSuccess = false;
    }
}

$output = [
    'run_date' => $runDate,
    'type' => 'renewal',
    'executed_by' => $executedBy,
    'tenant_filter' => $tenantCodeFilter,
    'all_success' => $allSuccess,
    'results' => $results,
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($allSuccess ? 0 : 1);
