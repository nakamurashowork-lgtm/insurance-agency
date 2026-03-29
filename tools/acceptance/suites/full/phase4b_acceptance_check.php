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

function req(string $url, string $method = 'GET', mixed $post = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($post)) {
            $hasFile = false;
            foreach ($post as $value) {
                if ($value instanceof CURLFile) {
                    $hasFile = true;
                    break;
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $post : http_build_query($post));
        }
    }

    if ($headers !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $e = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP_FAIL:' . $e);
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
$mark = 'PH4BT_' . date('Ymd_His');

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

$insCustomer->execute([
    'name' => $mark . '_A_CUSTOMER_1',
    'phone' => '07011110001',
    'email' => strtolower($mark) . '_a1@example.com',
    'address1' => 'Addr_A1',
    'uid' => $userId,
]);
$aCustomer1 = (int) $pdoA->lastInsertId();

$insCustomer->execute([
    'name' => $mark . '_A_CUSTOMER_2',
    'phone' => '07011110002',
    'email' => strtolower($mark) . '_a2@example.com',
    'address1' => 'Addr_A2',
    'uid' => $userId,
]);
$aCustomer2 = (int) $pdoA->lastInsertId();

$policy1 = $mark . '_A_POL_1';
$insContract->execute([
    'customer_id' => $aCustomer1,
    'policy_no' => $policy1,
    'product_type' => 'csv-auto',
    'start_date' => '2025-01-01',
    'end_date' => '2026-12-31',
    'premium' => 120000,
    'uid' => $userId,
]);
$aContract1 = (int) $pdoA->lastInsertId();

$policy2 = $mark . '_A_POL_2';
$insContract->execute([
    'customer_id' => $aCustomer2,
    'policy_no' => $policy2,
    'product_type' => 'csv-fire',
    'start_date' => '2025-02-01',
    'end_date' => '2026-11-30',
    'premium' => 98000,
    'uid' => $userId,
]);
$aContract2 = (int) $pdoA->lastInsertId();

$maturity1 = '2026-12-31';
$maturity2 = '2026-11-30';
$insRenewal->execute([
    'contract_id' => $aContract1,
    'maturity_date' => $maturity1,
    'next_action_date' => '2026-10-01',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC1',
    'uid' => $userId,
]);
$aRenewal1 = (int) $pdoA->lastInsertId();
$insRenewal->execute([
    'contract_id' => $aContract2,
    'maturity_date' => $maturity2,
    'next_action_date' => '2026-09-15',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC2',
    'uid' => $userId,
]);
$aRenewal2 = (int) $pdoA->lastInsertId();

$existingReceipt = $mark . '_EXISTING_RCPT';
$insSales->execute([
    'customer_id' => $aCustomer1,
    'contract_id' => $aContract1,
    'renewal_case_id' => $aRenewal1,
    'performance_date' => '2026-05-01',
    'performance_type' => 'new',
    'insurance_category' => 'vehicle',
    'product_type' => 'before-import',
    'premium_amount' => 50000,
    'receipt_no' => $existingReceipt,
    'settlement_month' => '2026-05',
    'staff_user_id' => $userId,
    'remark' => $mark . '_EXISTING_BEFORE',
    'uid' => $userId,
]);
$existingSalesId = (int) $pdoA->lastInsertId();

$insCustomerB = $pdoB->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :uid, :uid)"
);
$insContractB = $pdoB->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'AcceptanceIns', 'b-only', :start_date, :end_date, :premium, 'annual', 'active', :uid, :uid)"
);
$insRenewalB = $pdoB->prepare(
    "INSERT INTO t_renewal_case (contract_id, maturity_date, case_status, next_action_date, renewal_result, assigned_user_id, remark, created_by, updated_by)
     VALUES (:contract_id, :maturity_date, 'open', :next_action_date, 'pending', :assigned_user_id, :remark, :uid, :uid)"
);
$bCountBefore = (int) $pdoB->query('SELECT COUNT(*) FROM t_sales_performance WHERE is_deleted = 0')->fetchColumn();
$insCustomerB->execute([
    'name' => $mark . '_B_CUSTOMER',
    'phone' => '07099990001',
    'email' => strtolower($mark) . '_b@example.com',
    'address1' => 'Addr_B1',
    'uid' => $userId,
]);
$bCustomer = (int) $pdoB->lastInsertId();
$bPolicy = $mark . '_B_POL_1';
$insContractB->execute([
    'customer_id' => $bCustomer,
    'policy_no' => $bPolicy,
    'start_date' => '2025-03-01',
    'end_date' => '2026-10-31',
    'premium' => 87000,
    'uid' => $userId,
]);
$bContract = (int) $pdoB->lastInsertId();
$insRenewalB->execute([
    'contract_id' => $bContract,
    'maturity_date' => '2026-10-31',
    'next_action_date' => '2026-08-20',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_B_RC',
    'uid' => $userId,
]);

$sessionId = 'ph4bacc' . bin2hex(random_bytes(8));
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

$csvDir = __DIR__;
$successCsv = $csvDir . DIRECTORY_SEPARATOR . 'phase4b_success_' . $mark . '.csv';
$partialCsv = $csvDir . DIRECTORY_SEPARATOR . 'phase4b_partial_' . $mark . '.csv';

$successContent = implode("\n", [
    'receipt_no,policy_no,customer_name,maturity_date,performance_date,performance_type,insurance_category,product_type,premium_amount,settlement_month,remark',
    $existingReceipt . ',' . $policy1 . ',' . $mark . '_A_CUSTOMER_1,' . $maturity1 . ',2026-06-01,renewal,vehicle,csv-updated,65432,2026-06,' . $mark . '_UPDATED_BY_CSV',
    $mark . '_NEW_RCPT,' . $policy2 . ',' . $mark . '_A_CUSTOMER_2,' . $maturity2 . ',2026-06-15,addition,house,csv-inserted,76543,2026-06,' . $mark . '_INSERTED_BY_CSV',
]) . "\n";
file_put_contents($successCsv, $successContent);

$partialContent = implode("\n", [
    'receipt_no,policy_no,customer_name,maturity_date,performance_date,performance_type,insurance_category,product_type,premium_amount,settlement_month,remark',
    $mark . '_PARTIAL_OK,' . $policy1 . ',' . $mark . '_A_CUSTOMER_1,' . $maturity1 . ',2026-07-01,change,vehicle,csv-partial-ok,33333,2026-07,' . $mark . '_PARTIAL_OK',
    $mark . '_PARTIAL_BAD,' . $mark . '_NOT_FOUND,' . $mark . '_A_CUSTOMER_1,2026-07-31,change,vehicle,csv-partial-bad,11111,2026-07,' . $mark . '_PARTIAL_BAD',
]) . "\n";
file_put_contents($partialCsv, $partialContent);

$checks = [];
$listUrl = $appUrl . '/?route=sales/list';
$importUrl = $appUrl . '/?route=sales/import';
$createUrl = $appUrl . '/?route=sales/create';
$updateUrl = $appUrl . '/?route=sales/update';
$deleteUrl = $appUrl . '/?route=sales/delete';

$list = req($listUrl, 'GET', null, [$cookie]);
$checks['sales_list_status_200'] = $list['code'] === 200 && contains($list['body'], 'CSV取込');

$importToken = extractTokenByAction($list['body'], 'route=sales/import');
if (!is_string($importToken) || $importToken === '') {
    throw new RuntimeException('IMPORT_TOKEN_NOT_FOUND');
}

$successImport = req($importUrl, 'POST', [
    '_csrf_token' => $importToken,
    'csv_file' => new CURLFile($successCsv, 'text/csv', basename($successCsv)),
], [$cookie]);
$checks['success_import_redirect'] = $successImport['code'] === 302 && is_string($successImport['location']) && contains($successImport['location'], 'import_batch_id=');

preg_match('/import_batch_id=(\d+)/', (string) ($successImport['location'] ?? ''), $m1);
$successBatchId = (int) ($m1[1] ?? 0);
$stBatch = $pdoA->prepare('SELECT import_status, total_row_count, insert_count, update_count, error_count FROM t_sjnet_import_batch WHERE id = :id LIMIT 1');
$stBatch->execute(['id' => $successBatchId]);
$successBatch = $stBatch->fetch();
$checks['success_batch_summary'] = is_array($successBatch)
    && (string) ($successBatch['import_status'] ?? '') === 'success'
    && (int) ($successBatch['total_row_count'] ?? 0) === 2
    && (int) ($successBatch['insert_count'] ?? 0) === 1
    && (int) ($successBatch['update_count'] ?? 0) === 1
    && (int) ($successBatch['error_count'] ?? 0) === 0;

$stExisting = $pdoA->prepare('SELECT product_type, premium_amount, remark FROM t_sales_performance WHERE id = :id LIMIT 1');
$stExisting->execute(['id' => $existingSalesId]);
$existingAfterImport = $stExisting->fetch();
$checks['existing_row_updated_by_csv'] = is_array($existingAfterImport)
    && (string) ($existingAfterImport['product_type'] ?? '') === 'csv-updated'
    && (string) ($existingAfterImport['remark'] ?? '') === $mark . '_UPDATED_BY_CSV';

$stInserted = $pdoA->prepare('SELECT id FROM t_sales_performance WHERE receipt_no = :receipt_no AND is_deleted = 0 LIMIT 1');
$stInserted->execute(['receipt_no' => $mark . '_NEW_RCPT']);
$insertedId = (int) ($stInserted->fetchColumn() ?: 0);
$checks['new_row_inserted_by_csv'] = $insertedId > 0;

$successPage = req($listUrl . '&import_batch_id=' . $successBatchId, 'GET', null, [$cookie]);
$checks['success_result_readable'] = contains($successPage['body'], '直近取込結果') && contains($successPage['body'], 'success') && contains($successPage['body'], 'insert') && contains($successPage['body'], 'update');
$checks['list_reflects_import'] = contains($successPage['body'], $mark . '_INSERTED_BY_CSV') || contains($successPage['body'], 'csv-inserted');

$partialToken = extractTokenByAction(req($listUrl, 'GET', null, [$cookie])['body'], 'route=sales/import');
if (!is_string($partialToken) || $partialToken === '') {
    throw new RuntimeException('PARTIAL_IMPORT_TOKEN_NOT_FOUND');
}
$partialImport = req($importUrl, 'POST', [
    '_csrf_token' => $partialToken,
    'csv_file' => new CURLFile($partialCsv, 'text/csv', basename($partialCsv)),
], [$cookie]);
$checks['partial_import_redirect'] = $partialImport['code'] === 302 && is_string($partialImport['location']) && contains($partialImport['location'], 'import_batch_id=');
preg_match('/import_batch_id=(\d+)/', (string) ($partialImport['location'] ?? ''), $m2);
$partialBatchId = (int) ($m2[1] ?? 0);
$stBatch->execute(['id' => $partialBatchId]);
$partialBatch = $stBatch->fetch();
$checks['partial_batch_summary'] = is_array($partialBatch)
    && (string) ($partialBatch['import_status'] ?? '') === 'partial'
    && (int) ($partialBatch['insert_count'] ?? 0) === 1
    && (int) ($partialBatch['error_count'] ?? 0) === 1;

$stRow = $pdoA->prepare('SELECT row_status, error_message FROM t_sjnet_import_row WHERE sjnet_import_batch_id = :batch_id ORDER BY row_no ASC');
$stRow->execute(['batch_id' => $partialBatchId]);
$partialRows = $stRow->fetchAll();
$partialErrorMessage = '';
if (is_array($partialRows) && isset($partialRows[1]['error_message']) && is_string($partialRows[1]['error_message'])) {
    $partialErrorMessage = $partialRows[1]['error_message'];
}
$checks['failed_row_identifiable'] = is_array($partialRows)
    && count($partialRows) === 2
    && (string) ($partialRows[0]['row_status'] ?? '') === 'insert'
    && (string) ($partialRows[1]['row_status'] ?? '') === 'error'
    && (string) ($partialRows[1]['error_message'] ?? '') !== '';

$partialPage = req($listUrl . '&import_batch_id=' . $partialBatchId, 'GET', null, [$cookie]);
$checks['error_aggregate_readable'] = contains($partialPage['body'], 'partial')
    && $partialErrorMessage !== ''
    && contains($partialPage['body'], $partialErrorMessage);

$bCountAfter = (int) $pdoB->query('SELECT COUNT(*) FROM t_sales_performance WHERE is_deleted = 0')->fetchColumn();
$checks['other_tenant_not_mixed'] = $bCountAfter === $bCountBefore;

// Phase 4A CRUD regression after import
$createToken = extractTokenByAction(req($listUrl, 'GET', null, [$cookie])['body'], 'route=sales/create');
if (!is_string($createToken) || $createToken === '') {
    throw new RuntimeException('CREATE_TOKEN_NOT_FOUND');
}
$create = req($createUrl, 'POST', [
    '_csrf_token' => $createToken,
    'customer_id' => (string) $aCustomer1,
    'contract_id' => (string) $aContract1,
    'renewal_case_id' => (string) $aRenewal1,
    'performance_date' => '2026-08-01',
    'performance_type' => 'new',
    'insurance_category' => 'vehicle',
    'product_type' => 'after-import-crud',
    'premium_amount' => '12345',
    'receipt_no' => $mark . '_CRUD_RCPT',
    'settlement_month' => '2026-08',
    'staff_user_id' => (string) $userId,
    'remark' => $mark . '_CRUD_CREATE',
], [$cookie]);
$stCrud = $pdoA->prepare('SELECT id, remark, is_deleted FROM t_sales_performance WHERE receipt_no = :receipt_no ORDER BY id DESC LIMIT 1');
$stCrud->execute(['receipt_no' => $mark . '_CRUD_RCPT']);
$crudRow = $stCrud->fetch();
$crudId = is_array($crudRow) ? (int) ($crudRow['id'] ?? 0) : 0;
$checks['crud_create_still_works'] = $create['code'] === 302 && $crudId > 0;

$updateToken = extractTokenByAction(req($listUrl . '&edit_id=' . $crudId, 'GET', null, [$cookie])['body'], 'route=sales/update');
if (!is_string($updateToken) || $updateToken === '') {
    throw new RuntimeException('UPDATE_TOKEN_NOT_FOUND');
}
$update = req($updateUrl, 'POST', [
    '_csrf_token' => $updateToken,
    'id' => (string) $crudId,
    'customer_id' => (string) $aCustomer1,
    'contract_id' => (string) $aContract1,
    'renewal_case_id' => (string) $aRenewal1,
    'performance_date' => '2026-08-02',
    'performance_type' => 'change',
    'insurance_category' => 'vehicle',
    'product_type' => 'after-import-crud-updated',
    'premium_amount' => '22345',
    'receipt_no' => $mark . '_CRUD_RCPT',
    'settlement_month' => '2026-08',
    'staff_user_id' => (string) $userId,
    'remark' => $mark . '_CRUD_UPDATE',
], [$cookie]);
$stCrud->execute(['receipt_no' => $mark . '_CRUD_RCPT']);
$crudUpdated = $stCrud->fetch();
$checks['crud_update_still_works'] = $update['code'] === 302 && is_array($crudUpdated) && (string) ($crudUpdated['remark'] ?? '') === $mark . '_CRUD_UPDATE';

$deleteToken = extractTokenByAction(req($listUrl, 'GET', null, [$cookie])['body'], 'route=sales/delete');
if (!is_string($deleteToken) || $deleteToken === '') {
    throw new RuntimeException('DELETE_TOKEN_NOT_FOUND');
}
$delete = req($deleteUrl, 'POST', [
    '_csrf_token' => $deleteToken,
    'id' => (string) $crudId,
], [$cookie]);
$stCrud->execute(['receipt_no' => $mark . '_CRUD_RCPT']);
$crudDeleted = $stCrud->fetch();
$checks['crud_delete_still_works'] = $delete['code'] === 302 && is_array($crudDeleted) && (int) ($crudDeleted['is_deleted'] ?? 0) === 1;

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
        'success_batch_id' => $successBatchId,
        'partial_batch_id' => $partialBatchId,
    ],
    'tenantB' => [
        'tenant_code' => (string) $tenantB['tenant_code'],
        'tenant_db' => (string) $tenantB['db_name'],
    ],
    'checks' => $checks,
    'all_passed' => $allPassed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
