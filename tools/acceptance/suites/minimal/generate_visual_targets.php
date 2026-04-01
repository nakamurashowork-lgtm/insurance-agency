<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function loadEnv(string $path): array
{
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('ENV_READ_FAIL');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }

    return $env;
}

function pdoConn(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function issueSession(string $sessionName, array $auth): string
{
    $sid = 'vis' . bin2hex(random_bytes(8));
    session_name($sessionName);
    session_id($sid);
    session_start();
    $_SESSION['auth'] = $auth;
    session_write_close();

    return $sid;
}

$root = dirname(__DIR__, 4);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    throw new RuntimeException('ENV_NOT_FOUND');
}

$env = loadEnv($envPath);
$appUrl = rtrim((string) ($env['APP_URL'] ?? ''), '/');
$sessionName = (string) ($env['SESSION_COOKIE_NAME'] ?? 'INS_AGENCY_SESSID');

$common = pdoConn(
    (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1'),
    (int) ($env['COMMON_DB_PORT'] ?? 3306),
    (string) ($env['COMMON_DB_NAME'] ?? ''),
    (string) ($env['COMMON_DB_USER'] ?? ''),
    (string) ($env['COMMON_DB_PASSWORD'] ?? '')
);

$admin = $common->query(
    "SELECT u.id AS user_id, u.name AS user_name, u.is_system_admin, ut.role AS tenant_role, ut.tenant_code, t.id AS tenant_id, t.tenant_name, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
     ORDER BY (u.is_system_admin = 1) DESC, (ut.role = 'admin') DESC, u.id ASC
     LIMIT 1"
)->fetch();

if (!is_array($admin)) {
    throw new RuntimeException('NO_ACTIVE_ADMIN');
}

$tenant = pdoConn(
    (string) ($env['TENANT_DB_HOST'] ?? $env['COMMON_DB_HOST'] ?? '127.0.0.1'),
    (int) ($env['TENANT_DB_PORT'] ?? $env['COMMON_DB_PORT'] ?? 3306),
    (string) ($admin['db_name'] ?? ''),
    (string) ($env['TENANT_DB_USER'] ?? $env['COMMON_DB_USER'] ?? ''),
    (string) ($env['TENANT_DB_PASSWORD'] ?? $env['COMMON_DB_PASSWORD'] ?? '')
);

$getId = static function (PDO $pdo, string $sql): int {
    $value = $pdo->query($sql)->fetchColumn();
    return is_numeric($value) ? (int) $value : 0;
};

$customerId = $getId($tenant, 'SELECT id FROM m_customer WHERE is_deleted = 0 ORDER BY id DESC LIMIT 1');
$renewalCaseId = $getId($tenant, 'SELECT id FROM t_renewal_case WHERE is_deleted = 0 ORDER BY id DESC LIMIT 1');
$salesId = $getId($tenant, 'SELECT id FROM t_sales_performance WHERE is_deleted = 0 ORDER BY id DESC LIMIT 1');
$accidentId = $getId($tenant, 'SELECT id FROM t_accident_case WHERE is_deleted = 0 ORDER BY id DESC LIMIT 1');

if ($customerId <= 0 || $renewalCaseId <= 0 || $salesId <= 0 || $accidentId <= 0) {
    throw new RuntimeException('DETAIL_ID_NOT_FOUND');
}

$sessionId = issueSession($sessionName, [
    'user_id' => (int) $admin['user_id'],
    'display_name' => (string) $admin['user_name'],
    'tenant_id' => (int) $admin['tenant_id'],
    'tenant_code' => (string) $admin['tenant_code'],
    'tenant_name' => (string) $admin['tenant_name'],
    'tenant_db_name' => (string) $admin['db_name'],
    'permissions' => [
        'is_system_admin' => ((int) $admin['is_system_admin']) === 1,
        'tenant_role' => (string) $admin['tenant_role'],
    ],
]);

$routes = [
    'login' => $appUrl . '/?route=login',
    'dashboard' => $appUrl . '/?route=dashboard',
    'renewal_list' => $appUrl . '/?route=renewal/list',
    'renewal_detail' => $appUrl . '/?route=renewal/detail&id=' . $renewalCaseId,
    'customer_list' => $appUrl . '/?route=customer/list',
    'customer_detail' => $appUrl . '/?route=customer/detail&id=' . $customerId,
    'sales_list' => $appUrl . '/?route=sales/list',
    'sales_detail' => $appUrl . '/?route=sales/detail&id=' . $salesId,
    'accident_list' => $appUrl . '/?route=accident/list',
    'accident_detail' => $appUrl . '/?route=accident/detail&id=' . $accidentId,
    'tenant_settings' => $appUrl . '/?route=tenant/settings',
];

$result = [
    'app_url' => $appUrl,
    'session_cookie_name' => $sessionName,
    'session_id' => $sessionId,
    'routes' => $routes,
];

$outPath = $root . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'visual_targets.json';
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}

file_put_contents($outPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo $outPath . PHP_EOL;
