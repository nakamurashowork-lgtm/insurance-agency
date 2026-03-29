<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function loadEnv(string $path): array
{
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2) {
            $f = $v[0];
            $l = $v[strlen($v) - 1];
            if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
                $v = substr($v, 1, -1);
            }
        }
        $env[$k] = $v;
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

function req(string $url, string $method = 'GET', ?array $post = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
    }

    if ($headers !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP_FAIL:' . $err);
    }

    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $head = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    $location = null;
    foreach (explode("\r\n", $head) as $line) {
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }
    }

    return [
        'code' => $code,
        'headers' => $head,
        'body' => $body,
        'location' => $location,
    ];
}

function contains(string $haystack, string $needle): bool
{
    return mb_strpos($haystack, $needle) !== false;
}

function extractTokenByAction(string $html, string $actionPart): ?string
{
    $pattern = '#<form[^>]*action="[^"]*' . preg_quote($actionPart, '#') . '[^"]*"[^>]*>.*?<input[^>]*name="_csrf_token"[^>]*value="([^"]+)"#si';
    if (preg_match($pattern, $html, $m) === 1) {
        return (string) $m[1];
    }
    return null;
}

$root = __DIR__;
for ($i = 0; $i < 8; $i++) {
    if (is_file($root . DIRECTORY_SEPARATOR . '.env')) {
        break;
    }
    $parent = dirname($root);
    if ($parent === $root) {
        break;
    }
    $root = $parent;
}
if (!is_file($root . DIRECTORY_SEPARATOR . '.env')) {
    throw new RuntimeException('ENV_NOT_FOUND');
}
$env = loadEnv($root . DIRECTORY_SEPARATOR . '.env');
$appUrl = rtrim((string) ($env['APP_URL'] ?? ''), '/');
$sessionName = (string) ($env['SESSION_COOKIE_NAME'] ?? 'INS_AGENCY_SESSID');

$commonHost = (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1');
$commonPort = (int) ($env['COMMON_DB_PORT'] ?? 3306);
$commonDb = (string) ($env['COMMON_DB_NAME'] ?? '');
$commonUser = (string) ($env['COMMON_DB_USER'] ?? '');
$commonPass = (string) ($env['COMMON_DB_PASSWORD'] ?? '');

$tenantHost = (string) ($env['TENANT_DB_HOST'] ?? $commonHost);
$tenantPort = (int) ($env['TENANT_DB_PORT'] ?? $commonPort);
$tenantUser = (string) ($env['TENANT_DB_USER'] ?? $commonUser);
$tenantPass = (string) ($env['TENANT_DB_PASSWORD'] ?? $commonPass);

$common = pdoConn($commonHost, $commonPort, $commonDb, $commonUser, $commonPass);
$tenantA = $common->query(
    "SELECT u.id AS user_id, u.name AS user_name, ut.role AS tenant_role, ut.tenant_code, t.tenant_name, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
     ORDER BY ut.id ASC
     LIMIT 1"
)->fetch();
if (!is_array($tenantA)) {
    throw new RuntimeException('NO_TENANT_A');
}

$stTenantB = $common->prepare(
    "SELECT tenant_code, tenant_name, db_name
     FROM tenants
     WHERE status = 1 AND is_deleted = 0 AND tenant_code <> :tenant_code
     ORDER BY id ASC
     LIMIT 1"
);
$stTenantB->execute(['tenant_code' => (string) $tenantA['tenant_code']]);
$tenantB = $stTenantB->fetch();
if (!is_array($tenantB)) {
    throw new RuntimeException('NO_TENANT_B');
}

$pdoA = pdoConn($tenantHost, $tenantPort, (string) $tenantA['db_name'], $tenantUser, $tenantPass);
$pdoB = pdoConn($tenantHost, $tenantPort, (string) $tenantB['db_name'], $tenantUser, $tenantPass);
$userId = (int) $tenantA['user_id'];
$mark = 'PH4AT_' . date('Ymd_His');

$insCustomer = $pdoA->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :uid, :uid)"
);
$insContract = $pdoA->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'AcceptanceIns', :product_type, :start_date, :end_date, :premium, 'annual', 'active', :uid, :uid)"
);
$insRenewal = $pdoA->prepare(
    "INSERT INTO t_renewal_case (contract_id, maturity_date, case_status, next_action_date, renewal_result, assigned_user_id, remark, created_by, updated_by)
     VALUES (:contract_id, :maturity_date, 'open', :next_action_date, 'pending', :assigned_user_id, :remark, :uid, :uid)"
);
$insSales = $pdoA->prepare(
    "INSERT INTO t_sales_performance (customer_id, contract_id, renewal_case_id, performance_date, performance_type, insurance_category, product_type, premium_amount, receipt_no, settlement_month, staff_user_id, remark, created_by, updated_by)
     VALUES (:customer_id, :contract_id, :renewal_case_id, :performance_date, :performance_type, :insurance_category, :product_type, :premium_amount, :receipt_no, :settlement_month, :staff_user_id, :remark, :uid, :uid)"
);

// Tenant A seed
$insCustomer->execute([
    'name' => $mark . '_A_CUSTOMER_1',
    'phone' => '07000001111',
    'email' => strtolower($mark) . '_a1@example.com',
    'address1' => 'Addr_A1',
    'uid' => $userId,
]);
$aCustomer1 = (int) $pdoA->lastInsertId();

$insCustomer->execute([
    'name' => $mark . '_A_CUSTOMER_2',
    'phone' => '07000002222',
    'email' => strtolower($mark) . '_a2@example.com',
    'address1' => 'Addr_A2',
    'uid' => $userId,
]);
$aCustomer2 = (int) $pdoA->lastInsertId();

$policy1 = $mark . '_A_POL_1';
$insContract->execute([
    'customer_id' => $aCustomer1,
    'policy_no' => $policy1,
    'product_type' => 'auto',
    'start_date' => date('Y-m-d', strtotime('-100 day')),
    'end_date' => date('Y-m-d', strtotime('+200 day')),
    'premium' => 110000,
    'uid' => $userId,
]);
$aContract1 = (int) $pdoA->lastInsertId();

$policy2 = $mark . '_A_POL_2';
$insContract->execute([
    'customer_id' => $aCustomer2,
    'policy_no' => $policy2,
    'product_type' => 'fire',
    'start_date' => date('Y-m-d', strtotime('-120 day')),
    'end_date' => date('Y-m-d', strtotime('+240 day')),
    'premium' => 90000,
    'uid' => $userId,
]);
$aContract2 = (int) $pdoA->lastInsertId();

$insRenewal->execute([
    'contract_id' => $aContract1,
    'maturity_date' => date('Y-m-d', strtotime('+200 day')),
    'next_action_date' => date('Y-m-d', strtotime('+10 day')),
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC1',
    'uid' => $userId,
]);
$aRenewal1 = (int) $pdoA->lastInsertId();

$insRenewal->execute([
    'contract_id' => $aContract2,
    'maturity_date' => date('Y-m-d', strtotime('+240 day')),
    'next_action_date' => date('Y-m-d', strtotime('+20 day')),
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC2',
    'uid' => $userId,
]);
$aRenewal2 = (int) $pdoA->lastInsertId();

$insSales->execute([
    'customer_id' => $aCustomer1,
    'contract_id' => $aContract1,
    'renewal_case_id' => $aRenewal1,
    'performance_date' => '2026-03-01',
    'performance_type' => 'new',
    'insurance_category' => 'vehicle',
    'product_type' => 'typeA-main',
    'premium_amount' => 50000,
    'receipt_no' => $mark . '_A_R1',
    'settlement_month' => '2026-03',
    'staff_user_id' => $userId,
    'remark' => $mark . '_A_SEED_1',
    'uid' => $userId,
]);
$aSales1 = (int) $pdoA->lastInsertId();

$insSales->execute([
    'customer_id' => $aCustomer2,
    'contract_id' => $aContract2,
    'renewal_case_id' => $aRenewal2,
    'performance_date' => '2026-04-01',
    'performance_type' => 'renewal',
    'insurance_category' => 'house',
    'product_type' => 'typeA-sub',
    'premium_amount' => 62000,
    'receipt_no' => $mark . '_A_R2',
    'settlement_month' => '2026-04',
    'staff_user_id' => $userId,
    'remark' => $mark . '_A_SEED_2',
    'uid' => $userId,
]);
$aSales2 = (int) $pdoA->lastInsertId();

// Tenant B seed
$insCustomerB = $pdoB->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :uid, :uid)"
);
$insContractB = $pdoB->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'AcceptanceIns', 'auto', :start_date, :end_date, :premium, 'annual', 'active', :uid, :uid)"
);
$insRenewalB = $pdoB->prepare(
    "INSERT INTO t_renewal_case (contract_id, maturity_date, case_status, next_action_date, renewal_result, assigned_user_id, remark, created_by, updated_by)
     VALUES (:contract_id, :maturity_date, 'open', :next_action_date, 'pending', :assigned_user_id, :remark, :uid, :uid)"
);
$insSalesB = $pdoB->prepare(
    "INSERT INTO t_sales_performance (customer_id, contract_id, renewal_case_id, performance_date, performance_type, insurance_category, product_type, premium_amount, receipt_no, settlement_month, staff_user_id, remark, created_by, updated_by)
     VALUES (:customer_id, :contract_id, :renewal_case_id, :performance_date, :performance_type, :insurance_category, :product_type, :premium_amount, :receipt_no, :settlement_month, :staff_user_id, :remark, :uid, :uid)"
);

$insCustomerB->execute([
    'name' => $mark . '_B_CUSTOMER_1',
    'phone' => '07099991111',
    'email' => strtolower($mark) . '_b1@example.com',
    'address1' => 'Addr_B1',
    'uid' => $userId,
]);
$bCustomer1 = (int) $pdoB->lastInsertId();

$insContractB->execute([
    'customer_id' => $bCustomer1,
    'policy_no' => $mark . '_B_POL_1',
    'start_date' => date('Y-m-d', strtotime('-130 day')),
    'end_date' => date('Y-m-d', strtotime('+330 day')),
    'premium' => 88000,
    'uid' => $userId,
]);
$bContract1 = (int) $pdoB->lastInsertId();

$insRenewalB->execute([
    'contract_id' => $bContract1,
    'maturity_date' => date('Y-m-d', strtotime('+330 day')),
    'next_action_date' => date('Y-m-d', strtotime('+30 day')),
    'assigned_user_id' => $userId,
    'remark' => $mark . '_B_RC1',
    'uid' => $userId,
]);
$bRenewal1 = (int) $pdoB->lastInsertId();

$insSalesB->execute([
    'customer_id' => $bCustomer1,
    'contract_id' => $bContract1,
    'renewal_case_id' => $bRenewal1,
    'performance_date' => '2026-03-15',
    'performance_type' => 'addition',
    'insurance_category' => 'vehicle',
    'product_type' => 'typeB-only',
    'premium_amount' => 45000,
    'receipt_no' => $mark . '_B_R1',
    'settlement_month' => '2026-03',
    'staff_user_id' => $userId,
    'remark' => $mark . '_B_SEED_1',
    'uid' => $userId,
]);
$bSales1 = (int) $pdoB->lastInsertId();

// Build tenantB-only sales id for direct-hit check (must not exist in tenantA)
$maxASalesId = (int) $pdoA->query('SELECT COALESCE(MAX(id),0) FROM t_sales_performance')->fetchColumn();
$bSalesDirectOnly = $bSales1;
while ($bSalesDirectOnly <= $maxASalesId) {
    $insSalesB->execute([
        'customer_id' => $bCustomer1,
        'contract_id' => $bContract1,
        'renewal_case_id' => $bRenewal1,
        'performance_date' => '2026-03-16',
        'performance_type' => 'addition',
        'insurance_category' => 'vehicle',
        'product_type' => 'typeB-direct-only',
        'premium_amount' => 47000,
        'receipt_no' => $mark . '_B_R_PAD_' . ($bSalesDirectOnly + 1),
        'settlement_month' => '2026-03',
        'staff_user_id' => $userId,
        'remark' => $mark . '_B_SEED_PAD_' . ($bSalesDirectOnly + 1),
        'uid' => $userId,
    ]);
    $bSalesDirectOnly = (int) $pdoB->lastInsertId();
}

// Auth as tenant A
$sessionId = 'ph4acc' . bin2hex(random_bytes(8));
session_name($sessionName);
session_id($sessionId);
session_start();
$_SESSION['auth'] = [
    'user_id' => $userId,
    'display_name' => (string) $tenantA['user_name'],
    'tenant_id' => 0,
    'tenant_code' => (string) $tenantA['tenant_code'],
    'tenant_name' => (string) $tenantA['tenant_name'],
    'tenant_db_name' => (string) $tenantA['db_name'],
    'permissions' => [
        'is_system_admin' => false,
        'tenant_role' => (string) $tenantA['tenant_role'],
    ],
];
session_write_close();
$cookie = 'Cookie: ' . $sessionName . '=' . $sessionId;

$checks = [];

$listUrl = $appUrl . '/?route=sales/list';
$createUrl = $appUrl . '/?route=sales/create';
$updateUrl = $appUrl . '/?route=sales/update';
$deleteUrl = $appUrl . '/?route=sales/delete';

$list = req($listUrl, 'GET', null, [$cookie]);
$checks['sales_list_status_200'] = $list['code'] === 200 && contains($list['body'], '実績管理');
$checks['other_tenant_not_visible'] = !contains($list['body'], $mark . '_B_SEED_1');

// search checks
$sDate = req($listUrl . '&performance_date_from=2026-03-01&performance_date_to=2026-03-31&customer_name=' . rawurlencode($mark . '_A_CUSTOMER_1'), 'GET', null, [$cookie]);
$checks['search_date_from_to'] = contains($sDate['body'], $mark . '_A_SEED_1') && !contains($sDate['body'], $mark . '_A_SEED_2');

$sCustomer = req($listUrl . '&customer_name=' . rawurlencode($mark . '_A_CUSTOMER_2'), 'GET', null, [$cookie]);
$checks['search_contractor_name'] = contains($sCustomer['body'], $mark . '_A_SEED_2') && !contains($sCustomer['body'], $mark . '_A_SEED_1');

$sPolicy = req($listUrl . '&policy_no=' . rawurlencode($policy1), 'GET', null, [$cookie]);
$checks['search_policy_no'] = contains($sPolicy['body'], $mark . '_A_SEED_1') && !contains($sPolicy['body'], $mark . '_A_SEED_2');

$sProduct = req($listUrl . '&product_type=' . rawurlencode('typeA-sub'), 'GET', null, [$cookie]);
$checks['search_product_type'] = contains($sProduct['body'], $mark . '_A_SEED_2') && !contains($sProduct['body'], $mark . '_A_SEED_1');

$sSettlement = req($listUrl . '&settlement_month=2026-04', 'GET', null, [$cookie]);
$checks['search_settlement_month'] = contains($sSettlement['body'], $mark . '_A_SEED_2') && !contains($sSettlement['body'], $mark . '_A_SEED_1');

$sNoData = req($listUrl . '&customer_name=' . rawurlencode($mark . '_NOT_EXISTS'), 'GET', null, [$cookie]);
$checks['search_zero_result_safe'] = contains($sNoData['body'], '該当データはありません。');

// create reflection and csrf reject
$createPage = req($listUrl, 'GET', null, [$cookie]);
$createToken = extractTokenByAction($createPage['body'], 'route=sales/create');
if (!is_string($createToken) || $createToken === '') {
    throw new RuntimeException('CREATE_TOKEN_NOT_FOUND');
}

$beforeCreateCount = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE is_deleted = 0')->fetchColumn();
$beforeCreateMaxId = (int) $pdoA->query('SELECT COALESCE(MAX(id),0) FROM t_sales_performance')->fetchColumn();
$create = req($createUrl, 'POST', [
    '_csrf_token' => $createToken,
    'customer_id' => (string) $aCustomer1,
    'contract_id' => (string) $aContract1,
    'renewal_case_id' => (string) $aRenewal1,
    'performance_date' => '2026-05-01',
    'performance_type' => 'change',
    'insurance_category' => 'vehicle',
    'product_type' => 'typeA-created',
    'premium_amount' => '77777',
    'receipt_no' => $mark . '_A_CREATE_RCPT',
    'settlement_month' => '2026-05',
    'staff_user_id' => (string) $userId,
    'remark' => $mark . '_A_CREATED',
], [$cookie]);
$afterCreateCount = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE is_deleted = 0')->fetchColumn();
$afterCreateMaxId = (int) $pdoA->query('SELECT COALESCE(MAX(id),0) FROM t_sales_performance')->fetchColumn();
$checks['create_reflected'] = $create['code'] === 302 && $afterCreateCount === $beforeCreateCount + 1;

$createNoCsrfBefore = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE remark = ' . $pdoA->quote($mark . '_NO_CSRF_CREATE') . ' AND is_deleted = 0')->fetchColumn();
$createNoCsrf = req($createUrl, 'POST', [
    'customer_id' => (string) $aCustomer1,
    'performance_date' => '2026-05-02',
    'performance_type' => 'new',
    'premium_amount' => '1000',
    'remark' => $mark . '_NO_CSRF_CREATE',
], [$cookie]);
$createNoCsrfAfter = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE remark = ' . $pdoA->quote($mark . '_NO_CSRF_CREATE') . ' AND is_deleted = 0')->fetchColumn();
$checks['create_without_csrf_rejected'] = $createNoCsrf['code'] === 302 && $createNoCsrfAfter === $createNoCsrfBefore;

// invalid create
$invalidBefore = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE remark = ' . $pdoA->quote($mark . '_INVALID') . ' AND is_deleted = 0')->fetchColumn();
$invalidCreate = req($createUrl, 'POST', [
    '_csrf_token' => extractTokenByAction(req($listUrl, 'GET', null, [$cookie])['body'], 'route=sales/create') ?? '',
    'customer_id' => (string) $aCustomer1,
    'performance_date' => '2026/05/03',
    'performance_type' => 'bad_type',
    'premium_amount' => '-1',
    'remark' => $mark . '_INVALID',
], [$cookie]);
$invalidAfter = (int) $pdoA->query('SELECT COUNT(*) FROM t_sales_performance WHERE remark = ' . $pdoA->quote($mark . '_INVALID') . ' AND is_deleted = 0')->fetchColumn();
$checks['invalid_value_not_saved'] = $invalidCreate['code'] === 302 && $invalidAfter === $invalidBefore;

// update reflection, csrf reject, other tenant direct hit not updated
$createdId = 0;
if ($afterCreateMaxId > $beforeCreateMaxId) {
    $createdId = $afterCreateMaxId;
} else {
    $stCreated = $pdoA->prepare('SELECT id FROM t_sales_performance WHERE receipt_no = :receipt_no AND is_deleted = 0 ORDER BY id DESC LIMIT 1');
    $stCreated->execute(['receipt_no' => $mark . '_A_CREATE_RCPT']);
    $createdId = (int) ($stCreated->fetchColumn() ?: 0);
}

if ($createdId <= 0) {
    throw new RuntimeException('CREATE_NOT_REFLECTED:code=' . $create['code'] . ':location=' . (string) ($create['location'] ?? ''));
}

$editPage = req($listUrl . '&edit_id=' . $createdId, 'GET', null, [$cookie]);
$updateToken = extractTokenByAction($editPage['body'], 'route=sales/update');
if (!is_string($updateToken) || $updateToken === '') {
    throw new RuntimeException('UPDATE_TOKEN_NOT_FOUND:created_id=' . $createdId . ':code=' . $editPage['code']);
}

$update = req($updateUrl, 'POST', [
    '_csrf_token' => $updateToken,
    'id' => (string) $createdId,
    'customer_id' => (string) $aCustomer1,
    'contract_id' => (string) $aContract1,
    'renewal_case_id' => (string) $aRenewal1,
    'performance_date' => '2026-05-10',
    'performance_type' => 'cancel_deduction',
    'insurance_category' => 'vehicle',
    'product_type' => 'typeA-updated',
    'premium_amount' => '88888',
    'receipt_no' => $mark . '_A_UPDATE_RCPT',
    'settlement_month' => '2026-05',
    'staff_user_id' => (string) $userId,
    'remark' => $mark . '_A_UPDATED',
], [$cookie]);
$stUpdated = $pdoA->prepare('SELECT remark, performance_type, product_type FROM t_sales_performance WHERE id = :id LIMIT 1');
$stUpdated->execute(['id' => $createdId]);
$updated = $stUpdated->fetch();
$checks['update_reflected'] = $update['code'] === 302 && is_array($updated)
    && (string) ($updated['remark'] ?? '') === $mark . '_A_UPDATED'
    && (string) ($updated['performance_type'] ?? '') === 'cancel_deduction'
    && (string) ($updated['product_type'] ?? '') === 'typeA-updated';

$updateNoCsrfBefore = (string) ($updated['remark'] ?? '');
$updateNoCsrf = req($updateUrl, 'POST', [
    'id' => (string) $createdId,
    'customer_id' => (string) $aCustomer1,
    'performance_date' => '2026-05-11',
    'performance_type' => 'new',
    'premium_amount' => '99999',
    'remark' => $mark . '_A_UPDATE_NO_CSRF',
], [$cookie]);
$stUpdateNoCsrf = $pdoA->prepare('SELECT remark FROM t_sales_performance WHERE id = :id LIMIT 1');
$stUpdateNoCsrf->execute(['id' => $createdId]);
$updateNoCsrfAfter = (string) ($stUpdateNoCsrf->fetchColumn() ?: '');
$checks['update_without_csrf_rejected'] = $updateNoCsrf['code'] === 302 && $updateNoCsrfAfter === $updateNoCsrfBefore;

$stBTarget = $pdoB->prepare('SELECT remark, is_deleted FROM t_sales_performance WHERE id = :id LIMIT 1');
$stBTarget->execute(['id' => $bSalesDirectOnly]);
$bBefore = $stBTarget->fetch();
$updateOtherTenant = req($updateUrl, 'POST', [
    '_csrf_token' => extractTokenByAction(req($listUrl . '&edit_id=' . $createdId, 'GET', null, [$cookie])['body'], 'route=sales/update') ?? '',
    'id' => (string) $bSalesDirectOnly,
    'customer_id' => (string) $aCustomer1,
    'performance_date' => '2026-05-12',
    'performance_type' => 'new',
    'premium_amount' => '12345',
    'remark' => $mark . '_OTHER_TENANT_HIT',
], [$cookie]);
$stBTarget->execute(['id' => $bSalesDirectOnly]);
$bAfter = $stBTarget->fetch();
$checks['other_tenant_update_direct_not_applied'] = $updateOtherTenant['code'] === 302 && is_array($bBefore) && is_array($bAfter)
    && (string) ($bBefore['remark'] ?? '') === (string) ($bAfter['remark'] ?? '')
    && (int) ($bAfter['is_deleted'] ?? 0) === 0;

// delete reflection, csrf reject, other tenant direct delete not applied
$listForDelete = req($listUrl, 'GET', null, [$cookie]);
$deleteToken = extractTokenByAction($listForDelete['body'], 'route=sales/delete');
if (!is_string($deleteToken) || $deleteToken === '') {
    throw new RuntimeException('DELETE_TOKEN_NOT_FOUND');
}

$delete = req($deleteUrl, 'POST', [
    '_csrf_token' => $deleteToken,
    'id' => (string) $createdId,
], [$cookie]);
$stDeleted = $pdoA->prepare('SELECT is_deleted FROM t_sales_performance WHERE id = :id LIMIT 1');
$stDeleted->execute(['id' => $createdId]);
$deletedFlag = (int) ($stDeleted->fetchColumn() ?: 0);
$checks['logical_delete_reflected'] = $delete['code'] === 302 && $deletedFlag === 1;

$afterDeleteList = req($listUrl . '&customer_name=' . rawurlencode($mark . '_A_CUSTOMER_1') . '&product_type=' . rawurlencode('typeA-updated'), 'GET', null, [$cookie]);
$checks['deleted_hidden_in_list'] = !contains($afterDeleteList['body'], $mark . '_A_UPDATED');

$noCsrfDeleteTarget = $aSales2;
$stNoCsrfDel = $pdoA->prepare('SELECT is_deleted FROM t_sales_performance WHERE id = :id LIMIT 1');
$stNoCsrfDel->execute(['id' => $noCsrfDeleteTarget]);
$beforeNoCsrfDelete = (int) ($stNoCsrfDel->fetchColumn() ?: 0);
$deleteNoCsrf = req($deleteUrl, 'POST', [
    'id' => (string) $noCsrfDeleteTarget,
], [$cookie]);
$stNoCsrfDel->execute(['id' => $noCsrfDeleteTarget]);
$afterNoCsrfDelete = (int) ($stNoCsrfDel->fetchColumn() ?: 0);
$checks['delete_without_csrf_rejected'] = $deleteNoCsrf['code'] === 302 && $beforeNoCsrfDelete === 0 && $afterNoCsrfDelete === 0;

$stBTarget->execute(['id' => $bSalesDirectOnly]);
$bBeforeDelete = $stBTarget->fetch();
$deleteOtherTenant = req($deleteUrl, 'POST', [
    '_csrf_token' => extractTokenByAction(req($listUrl, 'GET', null, [$cookie])['body'], 'route=sales/delete') ?? '',
    'id' => (string) $bSalesDirectOnly,
], [$cookie]);
$stBTarget->execute(['id' => $bSalesDirectOnly]);
$bAfterDelete = $stBTarget->fetch();
$checks['other_tenant_delete_direct_not_applied'] = $deleteOtherTenant['code'] === 302 && is_array($bBeforeDelete) && is_array($bAfterDelete)
    && (int) ($bBeforeDelete['is_deleted'] ?? 0) === (int) ($bAfterDelete['is_deleted'] ?? 0);

$allPassed = true;
foreach ($checks as $ok) {
    if ($ok !== true) {
        $allPassed = false;
        break;
    }
}

echo json_encode([
    'mark' => $mark,
    'tenantA' => [
        'tenant_code' => (string) $tenantA['tenant_code'],
        'tenant_db' => (string) $tenantA['db_name'],
        'sales_ids' => [
            'seed_1' => $aSales1,
            'seed_2' => $aSales2,
            'created' => $createdId,
        ],
    ],
    'tenantB' => [
        'tenant_code' => (string) $tenantB['tenant_code'],
        'tenant_db' => (string) $tenantB['db_name'],
        'sales_direct_check_id' => $bSalesDirectOnly,
    ],
    'checks' => $checks,
    'all_passed' => $allPassed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
