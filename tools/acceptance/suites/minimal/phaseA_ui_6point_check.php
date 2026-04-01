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
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2) {
            $first = $v[0];
            $last = $v[strlen($v) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
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
    if ($ch === false) {
        throw new RuntimeException('CURL_INIT_FAIL');
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($post)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
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
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $head = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

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

function issueSession(string $sessionName, array $auth): string
{
    $sid = 'phAui' . bin2hex(random_bytes(8));
    session_name($sessionName);
    session_id($sid);
    session_start();
    $_SESSION['auth'] = $auth;
    session_write_close();
    return $sid;
}

function extractFirstDetailLink(string $html, string $route): ?string
{
    $pattern = '#href="([^"]*route=' . preg_quote($route, '#') . '[^"]*)"#i';
    if (preg_match($pattern, $html, $m) !== 1) {
        return null;
    }

    return html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function extractBackLink(string $html): ?string
{
    $pattern = '#href="([^"]*route=[^"]*)"[^>]*>一覧へ戻る<#i';
    if (preg_match($pattern, $html, $m) !== 1) {
        return null;
    }

    return html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
    throw new RuntimeException('NO_ACTIVE_USER');
}

$tenant = pdoConn($tenantHost, $tenantPort, (string) $admin['db_name'], $tenantUser, $tenantPass);
$mark = 'PHA_UI_' . date('Ymd_His');
$userId = (int) $admin['user_id'];
$tenantCode = (string) $admin['tenant_code'];

$tenantUsersStmt = $common->prepare(
    "SELECT u.id, u.name
     FROM user_tenants ut
     INNER JOIN users u ON u.id = ut.user_id
     WHERE ut.tenant_code = :tenant_code
       AND ut.status = 1
       AND ut.is_deleted = 0
       AND u.status = 1
       AND u.is_deleted = 0
     ORDER BY u.id ASC"
);
$tenantUsersStmt->execute(['tenant_code' => $tenantCode]);
$tenantUsers = $tenantUsersStmt->fetchAll();
if (!is_array($tenantUsers)) {
    $tenantUsers = [];
}

if (count($tenantUsers) < 2) {
    $seedEmail = strtolower($mark) . '_member@example.local';
    $seedSub = strtolower($mark) . '_member_sub';

    $insUser = $common->prepare(
        "INSERT INTO users (google_sub, email, name, is_system_admin, status, created_by, updated_by)
         VALUES (:google_sub, :email, :name, 0, 1, :uid, :uid)"
    );
    $insUser->execute([
        'google_sub' => $seedSub,
        'email' => $seedEmail,
        'name' => 'UI6 Member',
        'uid' => $userId,
    ]);
    $newUserId = (int) $common->lastInsertId();

    $insMembership = $common->prepare(
        "INSERT INTO user_tenants (user_id, tenant_code, role, status, created_by, updated_by)
         VALUES (:user_id, :tenant_code, 'member', 1, :uid, :uid)"
    );
    $insMembership->execute([
        'user_id' => $newUserId,
        'tenant_code' => $tenantCode,
        'uid' => $userId,
    ]);

    $tenantUsersStmt->execute(['tenant_code' => $tenantCode]);
    $tenantUsers = $tenantUsersStmt->fetchAll();
    if (!is_array($tenantUsers)) {
        $tenantUsers = [];
    }
}

$assignedUserId = $userId;
$assignedUserName = (string) ($admin['user_name'] ?? '');
foreach ($tenantUsers as $u) {
    if (!is_array($u)) {
        continue;
    }
    $candidateId = (int) ($u['id'] ?? 0);
    $candidateName = (string) ($u['name'] ?? '');
    if ($candidateId > 0 && $candidateName !== '') {
        $assignedUserId = $candidateId;
        $assignedUserName = $candidateName;
        break;
    }
}

$longCustomerName = $mark . '_LONG_' . str_repeat('顧客名', 12);
$longAddress = $mark . '_ADDR_' . str_repeat('長文', 24);
$longActivityDetail = $mark . '_ACT_' . str_repeat('詳細', 30);
$longAccidentSummary = $mark . '_ACC_' . str_repeat('要約', 22);

$insCustomer = $tenant->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, address2, status, assigned_user_id, note, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, :address2, 'active', :assigned_user_id, :note, :uid, :uid)"
);
$insCustomer->execute([
    'name' => $longCustomerName,
    'phone' => '09012345678',
    'email' => strtolower($mark) . '@example.com',
    'address1' => $longAddress,
    'address2' => 'Bldg 101',
    'assigned_user_id' => $assignedUserId,
    'note' => $mark . '_NOTE',
    'uid' => $userId,
]);
$customerId = (int) $tenant->lastInsertId();

$insCustomerEmpty = $tenant->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, assigned_user_id, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :assigned_user_id, :uid, :uid)"
);
$insCustomerEmpty->execute([
    'name' => $mark . '_EMPTY',
    'phone' => '08000001111',
    'email' => strtolower($mark) . '_empty@example.com',
    'address1' => 'No data',
    'assigned_user_id' => $assignedUserId,
    'uid' => $userId,
]);
$emptyCustomerId = (int) $tenant->lastInsertId();

$insContact = $tenant->prepare(
    "INSERT INTO m_customer_contact (customer_id, contact_name, department, position_name, phone, email, is_primary, sort_order, created_by, updated_by)
     VALUES (:customer_id, :contact_name, :department, '担当', :phone, :email, 1, 1, :uid, :uid)"
);
$insContact->execute([
    'customer_id' => $customerId,
    'contact_name' => $mark . '_CONTACT',
    'department' => '営業部',
    'phone' => '0312345678',
    'email' => strtolower($mark) . '_contact@example.com',
    'uid' => $userId,
]);

$insContract = $tenant->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'UI保険', 'auto', '2026-01-01', '2026-12-31', 120000, 'annual', 'active', :uid, :uid)"
);
$policyNo = $mark . '_POL';
$insContract->execute([
    'customer_id' => $customerId,
    'policy_no' => $policyNo,
    'uid' => $userId,
]);
$contractId = (int) $tenant->lastInsertId();

$insRenewal = $tenant->prepare(
    "INSERT INTO t_renewal_case (contract_id, maturity_date, case_status, assigned_user_id, created_by, updated_by)
     VALUES (:contract_id, '2026-12-31', 'open', :assigned_user_id, :uid, :uid)"
);
$insRenewal->execute([
    'contract_id' => $contractId,
    'assigned_user_id' => $assignedUserId,
    'uid' => $userId,
]);

$insActivity = $tenant->prepare(
    "INSERT INTO t_activity (customer_id, activity_at, activity_type, subject, detail, outcome, created_by, updated_by)
     VALUES (:customer_id, NOW(), 'phone', :subject, :detail, :outcome, :uid, :uid)"
);
$insActivity->execute([
    'customer_id' => $customerId,
    'subject' => $mark . '_SUBJECT',
    'detail' => $longActivityDetail,
    'outcome' => $mark . '_OUTCOME',
    'uid' => $userId,
]);

$insAccident = $tenant->prepare(
    "INSERT INTO t_accident_case (
        customer_id, contract_id, accident_no, accepted_date, accident_date, insurance_category, product_type,
        accident_type, accident_summary, accident_location, has_counterparty, status, priority,
        assigned_user_id, remark, created_by, updated_by
    ) VALUES (
        :customer_id, :contract_id, :accident_no, CURDATE(), CURDATE(), 'vehicle', 'auto',
        'collision', :summary, 'Tokyo', 0, 'accepted', 'normal',
        :assigned_user_id, :remark, :uid, :uid
    )"
);
$accidentNo = $mark . '_ACC';
$insAccident->execute([
    'customer_id' => $customerId,
    'contract_id' => $contractId,
    'accident_no' => $accidentNo,
    'summary' => $longAccidentSummary,
    'assigned_user_id' => $assignedUserId,
    'remark' => $mark . '_REMARK',
    'uid' => $userId,
]);
$accidentId = (int) $tenant->lastInsertId();

$sessionId = issueSession($sessionName, [
    'user_id' => $userId,
    'display_name' => (string) $admin['user_name'],
    'tenant_id' => (int) $admin['tenant_id'],
    'tenant_code' => $tenantCode,
    'tenant_name' => (string) $admin['tenant_name'],
    'tenant_db_name' => (string) $admin['db_name'],
    'permissions' => [
        'is_system_admin' => ((int) $admin['is_system_admin']) === 1,
        'tenant_role' => (string) $admin['tenant_role'],
    ],
]);

$cookie = 'Cookie: ' . $sessionName . '=' . $sessionId;
$checks = [];

$customerListStateUrl = $appUrl . '/?route=customer/list&customer_name=' . urlencode($mark)
    . '&phone=09012345678&email=' . urlencode(strtolower($mark) . '@example.com')
    . '&per_page=50&sort=customer_name&direction=desc&filter_open=1';
$customerList = req($customerListStateUrl, 'GET', null, [$cookie]);
$checks['customer_list_screen_load'] = $customerList['code'] === 200 && contains($customerList['body'], '顧客一覧');
$checks['customer_list_proxy_pc_mobile'] = contains($customerList['body'], 'width=device-width, initial-scale=1.0')
    && contains($customerList['body'], '@media (max-width: 900px)')
    && contains($customerList['body'], 'data-label="顧客名"');
$checks['customer_list_long_value'] = contains($customerList['body'], 'title="' . htmlspecialchars($longCustomerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"');

$customerDetailLink = extractFirstDetailLink($customerList['body'], 'customer/detail');
$checks['customer_list_to_detail_link_exists'] = is_string($customerDetailLink) && $customerDetailLink !== '';
$checks['customer_list_state_kept_in_detail_link'] = is_string($customerDetailLink)
    && contains($customerDetailLink, 'customer_name=' . rawurlencode($mark))
    && contains($customerDetailLink, 'per_page=50')
    && contains($customerDetailLink, 'sort=customer_name')
    && contains($customerDetailLink, 'direction=desc');

if (is_string($customerDetailLink) && $customerDetailLink !== '') {
    $customerDetail = req($appUrl . '/?' . ltrim(parse_url($customerDetailLink, PHP_URL_QUERY) ?? '', '?'), 'GET', null, [$cookie]);
    $checks['customer_list_to_detail_view_back'] = $customerDetail['code'] === 200 && contains($customerDetail['body'], '顧客詳細');
    $backLink = extractBackLink($customerDetail['body']);
    $checks['customer_detail_back_state_kept'] = is_string($backLink)
        && contains($backLink, 'route=customer/list')
        && contains($backLink, 'customer_name=' . rawurlencode($mark))
        && contains($backLink, 'per_page=50')
        && contains($backLink, 'sort=customer_name');
    $checks['customer_detail_long_activity_visible'] = contains($customerDetail['body'], $longActivityDetail)
        && contains($customerDetail['body'], $mark . '_SUBJECT')
        && contains($customerDetail['body'], $mark . '_OUTCOME');
} else {
    $checks['customer_list_to_detail_view_back'] = false;
    $checks['customer_detail_back_state_kept'] = false;
    $checks['customer_detail_long_activity_visible'] = false;
}

$customerListZero = req($appUrl . '/?route=customer/list&customer_name=' . urlencode('__NO_MATCH__' . $mark), 'GET', null, [$cookie]);
$checks['customer_list_zero_items'] = $customerListZero['code'] === 200
    && contains($customerListZero['body'], '該当データはありません。');

$customerEmptyDetail = req($appUrl . '/?route=customer/detail&id=' . $emptyCustomerId, 'GET', null, [$cookie]);
$checks['customer_detail_zero_items'] = $customerEmptyDetail['code'] === 200
    && contains($customerEmptyDetail['body'], '保有契約はありません。')
    && contains($customerEmptyDetail['body'], '活動履歴はありません。');

$accidentListStateUrl = $appUrl . '/?route=accident/list&customer_name=' . urlencode($mark)
    . '&status=accepted&per_page=50&sort=accepted_date&direction=desc';
$accidentList = req($accidentListStateUrl, 'GET', null, [$cookie]);
$checks['accident_list_screen_load'] = $accidentList['code'] === 200 && contains($accidentList['body'], '事故案件一覧');

$accidentDetailUrl = $appUrl . '/?route=accident/detail&id=' . $accidentId
    . '&customer_name=' . urlencode($mark)
    . '&status=accepted&per_page=50&sort=accepted_date&direction=desc';
$accidentDetail = req($accidentDetailUrl, 'GET', null, [$cookie]);
$checks['accident_detail_screen_load'] = $accidentDetail['code'] === 200 && contains($accidentDetail['body'], '事故案件詳細');
$checks['accident_detail_proxy_pc_mobile'] = contains($accidentDetail['body'], 'width=device-width, initial-scale=1.0')
    && contains($accidentDetail['body'], '@media (max-width: 900px)');
$checks['accident_detail_zero_items'] = (
        contains($accidentDetail['body'], 'コメントなし')
        || contains($accidentDetail['body'], 'コメント</span><span class="muted">0件</span>')
    )
    && (
        contains($accidentDetail['body'], '変更履歴なし')
        || contains($accidentDetail['body'], '変更履歴（監査ログ）</span><span class="muted">0件</span>')
    );
$checks['accident_detail_long_value'] = contains($accidentDetail['body'], $longAccidentSummary);
$checks['accident_detail_assignee_master_options'] = contains($accidentDetail['body'], 'name="assigned_user_id"')
    && contains($accidentDetail['body'], htmlspecialchars($assignedUserName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

$accidentBackLink = extractBackLink($accidentDetail['body']);
$checks['accident_detail_back_state_kept'] = is_string($accidentBackLink)
    && contains($accidentBackLink, 'route=accident/list')
    && contains($accidentBackLink, 'customer_name=' . rawurlencode($mark))
    && contains($accidentBackLink, 'status=accepted')
    && contains($accidentBackLink, 'per_page=50');

$failed = [];
foreach ($checks as $name => $ok) {
    if ($ok) {
        echo '[PASS] ' . $name . PHP_EOL;
        continue;
    }

    echo '[FAIL] ' . $name . PHP_EOL;
    $failed[] = $name;
}

if ($failed !== []) {
    echo PHP_EOL . 'FAILED: ' . implode(', ', $failed) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'All phase A UI 6-point proxy checks passed.' . PHP_EOL;
exit(0);
