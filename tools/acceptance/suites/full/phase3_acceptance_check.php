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
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
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

function contains(string $h, string $n): bool
{
    return mb_strpos($h, $n) !== false;
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

$commonHost = (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1');
$commonPort = (int) ($env['COMMON_DB_PORT'] ?? 3306);
$commonDb = (string) ($env['COMMON_DB_NAME'] ?? '');
$commonUser = (string) ($env['COMMON_DB_USER'] ?? '');
$commonPass = (string) ($env['COMMON_DB_PASSWORD'] ?? '');

$tenantHost = (string) ($env['TENANT_DB_HOST'] ?? $commonHost);
$tenantPort = (int) ($env['TENANT_DB_PORT'] ?? $commonPort);
$tenantUser = (string) ($env['TENANT_DB_USER'] ?? $commonUser);
$tenantPass = (string) ($env['TENANT_DB_PASSWORD'] ?? $commonPass);

$sessionName = (string) ($env['SESSION_COOKIE_NAME'] ?? 'INS_AGENCY_SESSID');

$common = pdoConn($commonHost, $commonPort, $commonDb, $commonUser, $commonPass);
$members = $common->query(
    "SELECT u.id AS user_id, u.name AS user_name, ut.role AS tenant_role, ut.tenant_code, t.tenant_name, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
     ORDER BY ut.id ASC"
)->fetchAll();

if (!is_array($members) || $members === []) {
    throw new RuntimeException('NO_ACTIVE_MEMBERSHIP');
}

$tenantA = $members[0];
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
    throw new RuntimeException('NO_SECOND_TENANT');
}

$pdoA = pdoConn($tenantHost, $tenantPort, (string) $tenantA['db_name'], $tenantUser, $tenantPass);
$pdoB = pdoConn($tenantHost, $tenantPort, (string) $tenantB['db_name'], $tenantUser, $tenantPass);

$mark = 'PH3AT_' . date('Ymd_His');
$userId = (int) $tenantA['user_id'];

$insCustomer = $pdoA->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, :status, :uid, :uid)"
);
$insContract = $pdoA->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'AcceptanceIns', 'auto', :start_date, :end_date, :premium, 'annual', 'active', :uid, :uid)"
);
$insRenewal = $pdoA->prepare(
    "INSERT INTO t_renewal_case (contract_id, maturity_date, case_status, next_action_date, renewal_result, assigned_user_id, remark, created_by, updated_by)
     VALUES (:contract_id, :maturity_date, :case_status, :next_action_date, :renewal_result, :assigned_user_id, :remark, :uid, :uid)"
);
$insActivity = $pdoA->prepare(
    "INSERT INTO t_activity (customer_id, contract_id, activity_at, activity_type, subject, detail, outcome, staff_user_id, created_by, updated_by)
     VALUES (:customer_id, :contract_id, NOW(), 'call', 'phase3-check', :detail, 'ok', :uid, :uid, :uid)"
);

// A: main customer (contacts 2, contracts 2, activities 1)
$main = [
    'name' => $mark . '_A_MAIN',
    'phone' => '09011112222',
    'email' => strtolower($mark) . '_main@example.com',
    'status' => 'active',
];
$insCustomer->execute([
    'name' => $main['name'],
    'phone' => $main['phone'],
    'email' => $main['email'],
    'address1' => 'Addr_MAIN',
    'status' => $main['status'],
    'uid' => $userId,
]);
$mainCustomerId = (int) $pdoA->lastInsertId();

$policyA1 = $mark . '_POL_A1';
$insContract->execute([
    'customer_id' => $mainCustomerId,
    'policy_no' => $policyA1,
    'start_date' => date('Y-m-d', strtotime('-330 day')),
    'end_date' => date('Y-m-d', strtotime('+30 day')),
    'premium' => 120000,
    'uid' => $userId,
]);
$contractA1 = (int) $pdoA->lastInsertId();

$policyA2 = $mark . '_POL_A2';
$insContract->execute([
    'customer_id' => $mainCustomerId,
    'policy_no' => $policyA2,
    'start_date' => date('Y-m-d', strtotime('-300 day')),
    'end_date' => date('Y-m-d', strtotime('+70 day')),
    'premium' => 130000,
    'uid' => $userId,
]);
$contractA2 = (int) $pdoA->lastInsertId();

// contractA1: create multiple renewal cases to validate latest selection rule.
$insRenewal->execute([
    'contract_id' => $contractA1,
    'maturity_date' => date('Y-m-d', strtotime('+30 day')),
    'case_status' => 'open',
    'next_action_date' => date('Y-m-d', strtotime('+5 day')),
    'renewal_result' => 'pending',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC_OLDER',
    'uid' => $userId,
]);
$renewalOlder = (int) $pdoA->lastInsertId();

$insRenewal->execute([
    'contract_id' => $contractA1,
    'maturity_date' => date('Y-m-d', strtotime('+120 day')),
    'case_status' => 'contacted',
    'next_action_date' => date('Y-m-d', strtotime('+8 day')),
    'renewal_result' => 'pending',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC_LATEST',
    'uid' => $userId,
]);
$renewalLatest = (int) $pdoA->lastInsertId();

// contractA2: one renewal case
$insRenewal->execute([
    'contract_id' => $contractA2,
    'maturity_date' => date('Y-m-d', strtotime('+70 day')),
    'case_status' => 'open',
    'next_action_date' => date('Y-m-d', strtotime('+10 day')),
    'renewal_result' => 'pending',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC_A2',
    'uid' => $userId,
]);
$renewalA2 = (int) $pdoA->lastInsertId();

$insActivity->execute([
    'customer_id' => $mainCustomerId,
    'contract_id' => $contractA1,
    'detail' => $mark . '_ACTIVITY_MAIN',
    'uid' => $userId,
]);

// A: no-contact customer (contracts 1, activity 0)
$noContact = [
    'name' => $mark . '_A_NOCONTACT',
    'phone' => '09022223333',
    'email' => strtolower($mark) . '_nocontact@example.com',
    'status' => 'inactive',
];
$insCustomer->execute([
    'name' => $noContact['name'],
    'phone' => $noContact['phone'],
    'email' => $noContact['email'],
    'address1' => 'Addr_NOCONTACT',
    'status' => $noContact['status'],
    'uid' => $userId,
]);
$noContactId = (int) $pdoA->lastInsertId();

$insContract->execute([
    'customer_id' => $noContactId,
    'policy_no' => $mark . '_POL_NC',
    'start_date' => date('Y-m-d', strtotime('-280 day')),
    'end_date' => date('Y-m-d', strtotime('+55 day')),
    'premium' => 100000,
    'uid' => $userId,
]);
$contractNC = (int) $pdoA->lastInsertId();
$insRenewal->execute([
    'contract_id' => $contractNC,
    'maturity_date' => date('Y-m-d', strtotime('+55 day')),
    'case_status' => 'open',
    'next_action_date' => date('Y-m-d', strtotime('+6 day')),
    'renewal_result' => 'pending',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC_NC',
    'uid' => $userId,
]);
$renewalNC = (int) $pdoA->lastInsertId();

// A: no-contract no-activity customer
$noContract = [
    'name' => $mark . '_A_NOCONTRACT',
    'phone' => '09033334444',
    'email' => strtolower($mark) . '_nocontract@example.com',
    'status' => 'active',
];
$insCustomer->execute([
    'name' => $noContract['name'],
    'phone' => $noContract['phone'],
    'email' => $noContract['email'],
    'address1' => 'Addr_NOCONTRACT',
    'status' => $noContract['status'],
    'uid' => $userId,
]);
$noContractId = (int) $pdoA->lastInsertId();

// B tenant customer for isolation
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
     VALUES (:contract_id, :maturity_date, :case_status, :next_action_date, :renewal_result, :assigned_user_id, :remark, :uid, :uid)"
);

$insCustomerB->execute([
    'name' => $mark . '_B_ONLY',
    'phone' => '09099990000',
    'email' => strtolower($mark) . '_bonly@example.com',
    'address1' => 'Addr_B_ONLY',
    'uid' => $userId,
]);
$customerBOnly = (int) $pdoB->lastInsertId();
$insContractB->execute([
    'customer_id' => $customerBOnly,
    'policy_no' => $mark . '_POL_BONLY',
    'start_date' => date('Y-m-d', strtotime('-200 day')),
    'end_date' => date('Y-m-d', strtotime('+40 day')),
    'premium' => 95000,
    'uid' => $userId,
]);
$contractBOnly = (int) $pdoB->lastInsertId();
$insRenewalB->execute([
    'contract_id' => $contractBOnly,
    'maturity_date' => date('Y-m-d', strtotime('+40 day')),
    'case_status' => 'open',
    'next_action_date' => date('Y-m-d', strtotime('+4 day')),
    'renewal_result' => 'pending',
    'assigned_user_id' => $userId,
    'remark' => $mark . '_RC_B',
    'uid' => $userId,
]);

// create B-only ID guaranteed not in A by exceeding A max id.
$maxAId = (int) $pdoA->query('SELECT COALESCE(MAX(id),0) AS m FROM m_customer')->fetchColumn();
$customerBOnlyForDirect = $customerBOnly;
while ($customerBOnlyForDirect <= $maxAId) {
    $insCustomerB->execute([
        'name' => $mark . '_B_PAD_' . ($customerBOnlyForDirect + 1),
        'phone' => '0908888' . sprintf('%04d', $customerBOnlyForDirect % 10000),
        'email' => strtolower($mark) . '_bpad_' . ($customerBOnlyForDirect + 1) . '@example.com',
        'address1' => 'Addr_B_PAD',
        'uid' => $userId,
    ]);
    $customerBOnlyForDirect = (int) $pdoB->lastInsertId();
}

// Auth session as tenantA
$sessionId = 'ph3acc' . bin2hex(random_bytes(8));
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

$customerListUrl = $appUrl . '/?route=customer/list';
$customerDetailBase = $appUrl . '/?route=customer/detail';
$renewalDetailBase = $appUrl . '/?route=renewal/detail';

$listRes = req($customerListUrl, 'GET', null, [$cookie]);
$checks['customer_list_status_200'] = ($listRes['code'] === 200);
$checks['customer_list_contains_main'] = contains($listRes['body'], $main['name']);
$checks['other_tenant_not_visible_in_list'] = !contains($listRes['body'], $mark . '_B_ONLY');

$resName = req($customerListUrl . '&customer_name=' . rawurlencode($main['name']), 'GET', null, [$cookie]);
$checks['search_customer_name'] = contains($resName['body'], $main['name']) && !contains($resName['body'], $noContact['name']);

$resPhone = req($customerListUrl . '&phone=' . rawurlencode($main['phone']), 'GET', null, [$cookie]);
$checks['search_phone'] = contains($resPhone['body'], $main['name']) && !contains($resPhone['body'], $noContact['name']);

$resEmail = req($customerListUrl . '&email=' . rawurlencode($main['email']), 'GET', null, [$cookie]);
$checks['search_email'] = contains($resEmail['body'], $main['name']) && !contains($resEmail['body'], $noContact['name']);

$resStatus = req($customerListUrl . '&status=inactive&customer_name=' . rawurlencode($mark . '_A_'), 'GET', null, [$cookie]);
$checks['search_status'] = contains($resStatus['body'], $noContact['name']) && !contains($resStatus['body'], $main['name']);

$checks['contract_count_correct_main'] = contains($listRes['body'], '<td>2</td>');

$detailMainUrl = $customerDetailBase . '&id=' . $mainCustomerId;
$detailMain = req($detailMainUrl, 'GET', null, [$cookie]);
$checks['customer_detail_status_200'] = ($detailMain['code'] === 200);
$checks['customer_detail_basic_info'] = contains($detailMain['body'], $main['name']) && contains($detailMain['body'], $main['email']) && contains($detailMain['body'], '顧客詳細');
$checks['contacts_multiple_display'] = contains($detailMain['body'], $mark . '_MAIN_CONTACT_1') && contains($detailMain['body'], $mark . '_MAIN_CONTACT_2');
$checks['activities_present_display'] = contains($detailMain['body'], $mark . '_ACTIVITY_MAIN');
$checks['contracts_multiple_display'] = contains($detailMain['body'], $policyA1) && contains($detailMain['body'], $policyA2);

$detailNoContact = req($customerDetailBase . '&id=' . $noContactId, 'GET', null, [$cookie]);
$checks['contacts_zero_safe'] = contains($detailNoContact['body'], '連絡先はありません。');
$checks['activities_none_safe'] = contains($detailNoContact['body'], '活動履歴はありません。');

$detailNoContract = req($customerDetailBase . '&id=' . $noContractId, 'GET', null, [$cookie]);
$checks['contracts_zero_safe'] = contains($detailNoContract['body'], '保有契約はありません。');
$checks['activities_zero_safe'] = contains($detailNoContract['body'], '活動履歴はありません。');

// customer -> renewal link check and latest rule check
preg_match_all('/route=renewal\/detail&amp;id=(\d+)/', $detailMain['body'], $m);
$linkIds = array_map('intval', $m[1] ?? []);
$checks['customer_to_renewal_link_exists'] = $linkIds !== [];
$checks['latest_renewal_selection_rule'] = in_array($renewalLatest, $linkIds, true) && !in_array($renewalOlder, $linkIds, true);

// renewal -> customer link check
$renewalDetail = req($renewalDetailBase . '&id=' . $renewalLatest, 'GET', null, [$cookie]);
$checks['renewal_to_customer_link_exists'] = contains($renewalDetail['body'], 'route=customer/detail') && contains($renewalDetail['body'], (string) $mainCustomerId);

// direct id block by B-only id that does not exist in A
$directOtherTenant = req($customerDetailBase . '&id=' . $customerBOnlyForDirect, 'GET', null, [$cookie]);
$checks['other_tenant_direct_id_blocked'] = ($directOtherTenant['code'] === 302 && is_string($directOtherTenant['location']) && contains($directOtherTenant['location'], 'route=customer/list'));

// search no data safe
$noData = req($customerListUrl . '&customer_name=' . rawurlencode($mark . '_NO_MATCH'), 'GET', null, [$cookie]);
$checks['list_no_data_safe'] = contains($noData['body'], '該当データはありません。');

echo json_encode([
    'mark' => $mark,
    'tenantA' => [
        'tenant_code' => (string) $tenantA['tenant_code'],
        'tenant_db' => (string) $tenantA['db_name'],
        'main_customer_id' => $mainCustomerId,
        'no_contact_customer_id' => $noContactId,
        'no_contract_customer_id' => $noContractId,
        'renewal_ids' => [
            'older' => $renewalOlder,
            'latest' => $renewalLatest,
            'a2' => $renewalA2,
            'no_contact' => $renewalNC,
        ],
        'policies' => [$policyA1, $policyA2],
    ],
    'tenantB' => [
        'tenant_code' => (string) $tenantB['tenant_code'],
        'tenant_db' => (string) $tenantB['db_name'],
        'customer_direct_check_id' => $customerBOnlyForDirect,
    ],
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
