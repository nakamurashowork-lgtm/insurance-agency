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

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
        'code' => $status,
        'headers' => $head,
        'body' => $body,
        'location' => $location,
    ];
}

function contains(string $haystack, string $needle): bool
{
    return mb_strpos($haystack, $needle) !== false;
}

function tokenByAction(string $html, string $actionPart): ?string
{
    $pattern = '#<form[^>]*action="[^"]*' . preg_quote($actionPart, '#') . '[^"]*"[^>]*>.*?<input[^>]*name="_csrf_token"[^>]*value="([^"]+)"#si';
    if (preg_match($pattern, $html, $m) === 1) {
        return (string) $m[1];
    }

    return null;
}

function hasAccidentDetailLink(string $html, int $id): bool
{
    if (preg_match('#route=accident/detail(?:&|&amp;)id=' . preg_quote((string) $id, '#') . '#i', $html) === 1) {
        return true;
    }

    return false;
}

/**
 * @return array<string, int>|null
 */
function parsePhaseForm(string $html): ?array
{
    $pattern = '#<form[^>]*action="[^"]*route=tenant/settings/phase[^"]*"[^>]*>(.*?)</form>#si';
    if (preg_match($pattern, $html, $m) !== 1) {
        return null;
    }

    $form = (string) $m[1];
    $fields = [
        'id' => null,
        'from_days_before' => null,
        'to_days_before' => null,
        'display_order' => null,
    ];

    foreach (array_keys($fields) as $name) {
        if (preg_match('#name="' . $name . '"[^>]*value="(-?\d+)"#i', $form, $mv) === 1) {
            $fields[$name] = (int) $mv[1];
        }
    }

    foreach ($fields as $v) {
        if (!is_int($v)) {
            return null;
        }
    }

    return [
        'id' => (int) $fields['id'],
        'from_days_before' => (int) $fields['from_days_before'],
        'to_days_before' => (int) $fields['to_days_before'],
        'display_order' => (int) $fields['display_order'],
    ];
}

/**
 * @param array<string, mixed> $auth
 */
function issueSession(string $sessionName, array $auth): string
{
    $sid = 'ph5acc' . bin2hex(random_bytes(8));
    session_name($sessionName);
    session_id($sid);
    session_start();
    $_SESSION['auth'] = $auth;
    session_write_close();
    return $sid;
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
    "SELECT u.id AS user_id, u.name AS user_name, u.is_system_admin, ut.role AS tenant_role, ut.tenant_code, t.tenant_name, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
       AND (u.is_system_admin = 1 OR ut.role = 'admin')
     ORDER BY u.id ASC
     LIMIT 1"
)->fetch();
if (!is_array($admin)) {
    throw new RuntimeException('NO_ADMIN_USER');
}

$memberStmt = $common->prepare(
    "SELECT u.id AS user_id, u.name AS user_name, u.is_system_admin, ut.role AS tenant_role, ut.tenant_code, t.tenant_name, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
       AND ut.tenant_code = :tenant_code
       AND u.is_system_admin = 0
       AND ut.role <> 'admin'
     ORDER BY u.id ASC
     LIMIT 1"
);
$memberStmt->execute(['tenant_code' => (string) $admin['tenant_code']]);
$member = $memberStmt->fetch();

if (!is_array($member)) {
    $seedEmail = 'phase5_member_' . date('YmdHis') . '@example.local';
    $seedSub = 'phase5_member_' . bin2hex(random_bytes(8));
    $insUser = $common->prepare(
        "INSERT INTO users (google_sub, email, name, is_system_admin, status, created_by, updated_by)
         VALUES (:google_sub, :email, :name, 0, 1, :uid, :uid)"
    );
    $insUser->execute([
        'google_sub' => $seedSub,
        'email' => $seedEmail,
        'name' => 'Phase5 Member',
        'uid' => (int) $admin['user_id'],
    ]);
    $memberUserId = (int) $common->lastInsertId();

    $insMembership = $common->prepare(
        "INSERT INTO user_tenants (user_id, tenant_code, role, status, created_by, updated_by)
         VALUES (:user_id, :tenant_code, 'member', 1, :uid, :uid)"
    );
    $insMembership->execute([
        'user_id' => $memberUserId,
        'tenant_code' => (string) $admin['tenant_code'],
        'uid' => (int) $admin['user_id'],
    ]);

    $memberStmt->execute(['tenant_code' => (string) $admin['tenant_code']]);
    $member = $memberStmt->fetch();
    if (!is_array($member)) {
        throw new RuntimeException('NO_MEMBER_USER');
    }
}

$tenantBStmt = $common->prepare(
    "SELECT tenant_code, tenant_name, db_name
     FROM tenants
     WHERE status = 1 AND is_deleted = 0 AND tenant_code <> :tenant_code
     ORDER BY id ASC
     LIMIT 1"
);
$tenantBStmt->execute(['tenant_code' => (string) $admin['tenant_code']]);
$tenantB = $tenantBStmt->fetch();
if (!is_array($tenantB)) {
    throw new RuntimeException('NO_TENANT_B');
}

$pdoA = pdoConn($tenantHost, $tenantPort, (string) $admin['db_name'], $tenantUser, $tenantPass);
$pdoB = pdoConn($tenantHost, $tenantPort, (string) $tenantB['db_name'], $tenantUser, $tenantPass);
$mark = 'PH5AT_' . date('Ymd_His');
$adminUserId = (int) $admin['user_id'];

$insCustomerA = $pdoA->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :uid, :uid)"
);
$insCustomerA->execute([
    'name' => $mark . '_A_CUST',
    'phone' => '07050000001',
    'email' => strtolower($mark) . '_a@example.com',
    'address1' => 'Phase5 Addr A',
    'uid' => $adminUserId,
]);
$aCustomerId = (int) $pdoA->lastInsertId();

$insContractA = $pdoA->prepare(
    "INSERT INTO t_contract (customer_id, policy_no, insurer_name, product_type, policy_start_date, policy_end_date, premium_amount, payment_cycle, status, created_by, updated_by)
     VALUES (:customer_id, :policy_no, 'Phase5Ins', 'auto', '2025-01-01', '2026-12-31', 100000, 'annual', 'active', :uid, :uid)"
);
$aPolicyNo = $mark . '_A_POL';
$insContractA->execute([
    'customer_id' => $aCustomerId,
    'policy_no' => $aPolicyNo,
    'uid' => $adminUserId,
]);
$aContractId = (int) $pdoA->lastInsertId();

$insAccidentA = $pdoA->prepare(
    "INSERT INTO t_accident_case (
        customer_id, contract_id, accident_no, accepted_date, accident_date, insurance_category, product_type,
        accident_type, accident_summary, accident_location, has_counterparty, status, priority,
        insurer_claim_no, resolved_date, assigned_user_id, remark, created_by, updated_by
     ) VALUES (
        :customer_id, :contract_id, :accident_no, :accepted_date, :accident_date, 'vehicle', :product_type,
        'collision', :summary, 'Tokyo', 1, 'accepted', 'normal',
        NULL, NULL, :assigned_user_id, :remark, :uid, :uid
     )"
);
$aAccidentNo = $mark . '_A_ACC';
$insAccidentA->execute([
    'customer_id' => $aCustomerId,
    'contract_id' => $aContractId,
    'accident_no' => $aAccidentNo,
    'accepted_date' => date('Y-m-d'),
    'accident_date' => date('Y-m-d'),
    'product_type' => 'auto',
    'summary' => $mark . '_SUMMARY',
    'assigned_user_id' => $adminUserId,
    'remark' => $mark . '_REMARK_BEFORE',
    'uid' => $adminUserId,
]);
$aAccidentId = (int) $pdoA->lastInsertId();

$insAuditA = $pdoA->prepare(
    "INSERT INTO t_audit_event (entity_type, entity_id, action_type, change_source, changed_by, note)
     VALUES ('accident_case', :entity_id, 'UPDATE', 'SCREEN', :changed_by, :note)"
);
$insAuditA->execute([
    'entity_id' => $aAccidentId,
    'changed_by' => $adminUserId,
    'note' => $mark . '_AUDIT_NOTE',
]);

$phaseCount = (int) $pdoA->query('SELECT COUNT(*) FROM m_renewal_reminder_phase WHERE is_deleted = 0')->fetchColumn();
if ($phaseCount === 0) {
    $seedPhase = $pdoA->prepare(
        "INSERT INTO m_renewal_reminder_phase (
            phase_code,
            phase_name,
            from_days_before,
            to_days_before,
            is_enabled,
            display_order,
            is_deleted,
            created_by,
            updated_by
         ) VALUES (
            :phase_code,
            :phase_name,
            90,
            30,
            1,
            100,
            0,
            :uid,
            :uid
         )"
    );
    $seedPhase->execute([
        'phase_code' => 'PH5_' . substr(md5($mark), 0, 8),
        'phase_name' => 'Phase5 Seed',
        'uid' => $adminUserId,
    ]);
}

$insCustomerB = $pdoB->prepare(
    "INSERT INTO m_customer (customer_type, customer_name, phone, email, address1, status, created_by, updated_by)
     VALUES ('individual', :name, :phone, :email, :address1, 'active', :uid, :uid)"
);
$insCustomerB->execute([
    'name' => $mark . '_B_CUST',
    'phone' => '07050000002',
    'email' => strtolower($mark) . '_b@example.com',
    'address1' => 'Phase5 Addr B',
    'uid' => $adminUserId,
]);
$bCustomerId = (int) $pdoB->lastInsertId();

$insAccidentB = $pdoB->prepare(
    "INSERT INTO t_accident_case (
        customer_id, contract_id, accident_no, accepted_date, accident_date, insurance_category, product_type,
        accident_type, accident_summary, accident_location, has_counterparty, status, priority,
        insurer_claim_no, resolved_date, assigned_user_id, remark, created_by, updated_by
     ) VALUES (
        :customer_id, NULL, :accident_no, :accepted_date, :accident_date, 'vehicle', :product_type,
        'collision', :summary, 'Osaka', 0, 'accepted', 'normal',
        NULL, NULL, :assigned_user_id, :remark, :uid, :uid
     )"
);
$bAccidentNo = $mark . '_B_ACC';
$insAccidentB->execute([
    'customer_id' => $bCustomerId,
    'accident_no' => $bAccidentNo,
    'accepted_date' => date('Y-m-d'),
    'accident_date' => date('Y-m-d'),
    'product_type' => 'fire',
    'summary' => $mark . '_B_SUMMARY',
    'assigned_user_id' => $adminUserId,
    'remark' => $mark . '_B_REMARK',
    'uid' => $adminUserId,
]);
$bAccidentId = (int) $pdoB->lastInsertId();
$crossTenantId = $bAccidentId;
$existsInAStmt = $pdoA->prepare('SELECT COUNT(*) FROM t_accident_case WHERE id = :id AND is_deleted = 0');
while (true) {
    $existsInAStmt->execute(['id' => $crossTenantId]);
    if ((int) $existsInAStmt->fetchColumn() === 0) {
        break;
    }
    $crossTenantId += 100000;
}

$adminSessionId = issueSession($sessionName, [
    'user_id' => $adminUserId,
    'display_name' => (string) $admin['user_name'],
    'tenant_id' => 0,
    'tenant_code' => (string) $admin['tenant_code'],
    'tenant_name' => (string) $admin['tenant_name'],
    'tenant_db_name' => (string) $admin['db_name'],
    'permissions' => [
        'is_system_admin' => ((int) $admin['is_system_admin']) === 1,
        'tenant_role' => (string) $admin['tenant_role'],
    ],
]);
$memberSessionId = issueSession($sessionName, [
    'user_id' => (int) $member['user_id'],
    'display_name' => (string) $member['user_name'],
    'tenant_id' => 0,
    'tenant_code' => (string) $member['tenant_code'],
    'tenant_name' => (string) $member['tenant_name'],
    'tenant_db_name' => (string) $member['db_name'],
    'permissions' => [
        'is_system_admin' => false,
        'tenant_role' => (string) $member['tenant_role'],
    ],
]);

$adminCookie = 'Cookie: ' . $sessionName . '=' . $adminSessionId;
$memberCookie = 'Cookie: ' . $sessionName . '=' . $memberSessionId;

$dashboardUrl = $appUrl . '/?route=dashboard';
$accidentListUrl = $appUrl . '/?route=accident/list';
$accidentDetailUrl = $appUrl . '/?route=accident/detail&id=' . $aAccidentId;
$tenantSettingsUrl = $appUrl . '/?route=tenant/settings';

$checks = [];

$dashAdmin = req($dashboardUrl, 'GET', null, [$adminCookie]);
$checks['admin_dashboard_status_200'] = $dashAdmin['code'] === 200;
$checks['admin_helper_links_visible'] = contains($dashAdmin['body'], '管理者向け補助導線')
    && contains($dashAdmin['body'], 'route=accident/list')
    && contains($dashAdmin['body'], 'route=tenant/settings');

$dashMember = req($dashboardUrl, 'GET', null, [$memberCookie]);
$checks['member_dashboard_status_200'] = $dashMember['code'] === 200;
$checks['member_helper_links_hidden'] = !contains($dashMember['body'], '管理者向け補助導線')
    && !contains($dashMember['body'], 'route=accident/list')
    && !contains($dashMember['body'], 'route=tenant/settings');

$accidentListMember = req($accidentListUrl, 'GET', null, [$memberCookie]);
$checks['member_accident_list_blocked'] = $accidentListMember['code'] === 302
    && is_string($accidentListMember['location'])
    && contains($accidentListMember['location'], 'route=dashboard');

$tenantMember = req($tenantSettingsUrl, 'GET', null, [$memberCookie]);
$checks['member_tenant_settings_blocked'] = $tenantMember['code'] === 302
    && is_string($tenantMember['location'])
    && contains($tenantMember['location'], 'route=dashboard');

$accidentListAdmin = req($accidentListUrl . '&customer_name=' . urlencode($mark), 'GET', null, [$adminCookie]);
$checks['admin_accident_list_works'] = $accidentListAdmin['code'] === 200
    && contains($accidentListAdmin['body'], '事故案件一覧')
    && contains($accidentListAdmin['body'], $aAccidentNo)
    && hasAccidentDetailLink($accidentListAdmin['body'], $aAccidentId);

$accidentDetailAdmin = req($accidentDetailUrl, 'GET', null, [$adminCookie]);
$checks['accident_list_to_detail_works'] = $accidentDetailAdmin['code'] === 200
    && contains($accidentDetailAdmin['body'], '事故案件詳細')
    && contains($accidentDetailAdmin['body'], $aAccidentNo);
$checks['accident_detail_comment_and_audit_visible'] = contains($accidentDetailAdmin['body'], 'コメント')
    && contains($accidentDetailAdmin['body'], '監査ログ')
    && contains($accidentDetailAdmin['body'], $mark . '_AUDIT_NOTE');

$updateToken = tokenByAction($accidentDetailAdmin['body'], 'route=accident/update');
$commentToken = tokenByAction($accidentDetailAdmin['body'], 'route=accident/comment');
$checks['accident_detail_csrf_tokens_present'] = is_string($updateToken) && $updateToken !== '' && is_string($commentToken) && $commentToken !== '';

if (is_string($updateToken) && $updateToken !== '') {
    $updateRes = req($appUrl . '/?route=accident/update', 'POST', [
        'id' => (string) $aAccidentId,
        '_csrf_token' => $updateToken,
        'status' => 'resolved',
        'priority' => 'high',
        'assigned_user_id' => (string) $adminUserId,
        'resolved_date' => date('Y-m-d'),
        'insurer_claim_no' => $mark . '_CLAIM',
        'remark' => $mark . '_UPDATED',
    ], [$adminCookie]);
    $checks['accident_update_post_redirects'] = $updateRes['code'] === 302
        && is_string($updateRes['location'])
        && contains($updateRes['location'], 'route=accident/detail&id=' . $aAccidentId);
} else {
    $checks['accident_update_post_redirects'] = false;
}

$accRow = $pdoA->prepare('SELECT status, priority, insurer_claim_no, remark FROM t_accident_case WHERE id = :id');
$accRow->execute(['id' => $aAccidentId]);
$accUpdated = $accRow->fetch();
$checks['accident_update_reflected'] = is_array($accUpdated)
    && (string) ($accUpdated['status'] ?? '') === 'resolved'
    && (string) ($accUpdated['priority'] ?? '') === 'high'
    && (string) ($accUpdated['insurer_claim_no'] ?? '') === $mark . '_CLAIM'
    && (string) ($accUpdated['remark'] ?? '') === $mark . '_UPDATED';

if (is_string($commentToken) && $commentToken !== '') {
    $commentRes = req($appUrl . '/?route=accident/comment', 'POST', [
        'id' => (string) $aAccidentId,
        '_csrf_token' => $commentToken,
        'comment_body' => $mark . '_COMMENT_BODY',
    ], [$adminCookie]);
    $checks['accident_comment_post_redirects'] = $commentRes['code'] === 302
        && is_string($commentRes['location'])
        && contains($commentRes['location'], 'route=accident/detail&id=' . $aAccidentId);
} else {
    $checks['accident_comment_post_redirects'] = false;
}

$commentCount = $pdoA->prepare(
    "SELECT COUNT(*) FROM t_case_comment
     WHERE target_type = 'accident_case'
       AND accident_case_id = :id
       AND comment_body = :body
       AND is_deleted = 0"
);
$commentCount->execute([
    'id' => $aAccidentId,
    'body' => $mark . '_COMMENT_BODY',
]);
$checks['accident_comment_reflected'] = (int) $commentCount->fetchColumn() >= 1;

$otherAccidentTry = req($appUrl . '/?route=accident/detail&id=' . $crossTenantId, 'GET', null, [$adminCookie]);
$checks['other_tenant_accident_direct_access_blocked'] = $otherAccidentTry['code'] === 302
    && is_string($otherAccidentTry['location'])
    && contains($otherAccidentTry['location'], 'route=accident/list');

$tenantSettingsAdmin = req($tenantSettingsUrl, 'GET', null, [$adminCookie]);
$checks['tenant_settings_page_works'] = $tenantSettingsAdmin['code'] === 200
    && contains($tenantSettingsAdmin['body'], 'テナント設定')
    && contains($tenantSettingsAdmin['body'], '通知設定');

$notifyToken = tokenByAction($tenantSettingsAdmin['body'], 'route=tenant/settings/notify');
$checks['tenant_notify_csrf_present'] = is_string($notifyToken) && $notifyToken !== '';

$beforeBRouteCountStmt = $common->prepare('SELECT COUNT(*) FROM tenant_notify_routes WHERE tenant_code = :tenant_code');
$beforeBRouteCountStmt->execute(['tenant_code' => (string) $tenantB['tenant_code']]);
$bRouteCountBefore = (int) $beforeBRouteCountStmt->fetchColumn();

if (is_string($notifyToken) && $notifyToken !== '') {
    $notifyRes = req($appUrl . '/?route=tenant/settings/notify', 'POST', [
        '_csrf_token' => $notifyToken,
        'renewal_is_enabled' => '1',
        'renewal_provider_type' => 'lineworks',
        'renewal_destination_name' => $mark . '_renewal_dest',
        'renewal_webhook_url' => 'https://example.invalid/' . strtolower($mark) . '/renewal',
        'accident_is_enabled' => '1',
        'accident_provider_type' => 'slack',
        'accident_destination_name' => $mark . '_accident_dest',
        'accident_webhook_url' => 'https://example.invalid/' . strtolower($mark) . '/accident',
    ], [$adminCookie]);
    $checks['tenant_notify_post_redirects'] = $notifyRes['code'] === 302
        && is_string($notifyRes['location'])
        && contains($notifyRes['location'], 'route=tenant/settings&tab=notify');
} else {
    $checks['tenant_notify_post_redirects'] = false;
}

$notifyVerifyStmt = $common->prepare(
    "SELECT COUNT(*)
     FROM tenant_notify_routes r
     INNER JOIN tenant_notify_targets t ON t.id = r.destination_id
     WHERE r.tenant_code = :tenant_code
       AND r.is_deleted = 0
       AND t.is_deleted = 0
       AND (
         (r.notification_type = 'renewal' AND t.destination_name = :renewal_dest)
         OR
         (r.notification_type = 'accident' AND t.destination_name = :accident_dest)
       )"
);
$notifyVerifyStmt->execute([
    'tenant_code' => (string) $admin['tenant_code'],
    'renewal_dest' => $mark . '_renewal_dest',
    'accident_dest' => $mark . '_accident_dest',
]);
$checks['tenant_notify_reflected_on_own_tenant'] = (int) $notifyVerifyStmt->fetchColumn() === 2;

$afterBRouteCountStmt = $common->prepare('SELECT COUNT(*) FROM tenant_notify_routes WHERE tenant_code = :tenant_code');
$afterBRouteCountStmt->execute(['tenant_code' => (string) $tenantB['tenant_code']]);
$bRouteCountAfter = (int) $afterBRouteCountStmt->fetchColumn();
$checks['other_tenant_notify_not_affected'] = $bRouteCountBefore === $bRouteCountAfter;

$phasePage = req($tenantSettingsUrl . '&tab=master', 'GET', null, [$adminCookie]);
$phaseToken = tokenByAction($phasePage['body'], 'route=tenant/settings/phase');
$phaseForm = parsePhaseForm($phasePage['body']);
$checks['tenant_phase_form_visible'] = $phasePage['code'] === 200
    && is_string($phaseToken)
    && $phaseToken !== ''
    && is_array($phaseForm);

$checks['tenant_phase_post_redirects'] = false;
$checks['tenant_phase_reflected_on_own_tenant'] = false;
if (is_array($phaseForm) && is_string($phaseToken) && $phaseToken !== '') {
    $phaseId = (int) $phaseForm['id'];
    $phaseBeforeStmt = $pdoA->prepare('SELECT is_enabled FROM m_renewal_reminder_phase WHERE id = :id');
    $phaseBeforeStmt->execute(['id' => $phaseId]);
    $beforeRow = $phaseBeforeStmt->fetch();
    $beforeEnabled = is_array($beforeRow) ? (int) ($beforeRow['is_enabled'] ?? 0) : 0;
    $newEnabled = $beforeEnabled === 1 ? 0 : 1;

    $phasePost = [
        '_csrf_token' => $phaseToken,
        'id' => (string) $phaseId,
        'from_days_before' => (string) $phaseForm['from_days_before'],
        'to_days_before' => (string) $phaseForm['to_days_before'],
        'display_order' => (string) $phaseForm['display_order'],
    ];
    if ($newEnabled === 1) {
        $phasePost['is_enabled'] = '1';
    }

    $phaseRes = req($appUrl . '/?route=tenant/settings/phase', 'POST', $phasePost, [$adminCookie]);

    $checks['tenant_phase_post_redirects'] = $phaseRes['code'] === 302
        && is_string($phaseRes['location'])
        && contains($phaseRes['location'], 'route=tenant/settings&tab=master');

    $phaseAfterStmt = $pdoA->prepare('SELECT is_enabled FROM m_renewal_reminder_phase WHERE id = :id');
    $phaseAfterStmt->execute(['id' => $phaseId]);
    $afterRow = $phaseAfterStmt->fetch();
    $afterEnabled = is_array($afterRow) ? (int) ($afterRow['is_enabled'] ?? -1) : -1;
    $checks['tenant_phase_reflected_on_own_tenant'] = $afterEnabled === $newEnabled;
}

$memberDirectNotify = req($appUrl . '/?route=tenant/settings/notify', 'POST', [
    '_csrf_token' => 'invalid',
    'renewal_provider_type' => 'lineworks',
], [$memberCookie]);
$checks['member_direct_post_tenant_settings_blocked'] = $memberDirectNotify['code'] === 302
    && is_string($memberDirectNotify['location'])
    && contains($memberDirectNotify['location'], 'route=dashboard');

$allPassed = true;
foreach ($checks as $ok) {
    if ($ok !== true) {
        $allPassed = false;
        break;
    }
}

$result = [
    'phase' => 'phase5',
    'mark' => $mark,
    'admin_tenant_code' => (string) $admin['tenant_code'],
    'other_tenant_code' => (string) $tenantB['tenant_code'],
    'checks' => $checks,
    'all_passed' => $allPassed,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
