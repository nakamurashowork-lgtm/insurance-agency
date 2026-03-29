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

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $env[$key] = $value;
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

function runBatch(string $phpBin, string $script, array $args): array
{
    $parts = [escapeshellarg($phpBin), escapeshellarg($script)];
    foreach ($args as $arg) {
        $parts[] = escapeshellarg($arg);
    }

    $output = [];
    $exitCode = 0;
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $raw = implode("\n", $output);

    $pos = strpos($raw, '{');
    $json = null;
    if ($pos !== false) {
        $candidate = substr($raw, $pos);
        $json = json_decode($candidate, true);
    }

    return [
        'exit_code' => $exitCode,
        'raw' => $raw,
        'json' => is_array($json) ? $json : null,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function findTypeResult(array $results, string $type): ?array
{
    foreach ($results as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['notification_type'] ?? '') === $type) {
            return $row;
        }
    }

    return null;
}

function createManualRun(PDO $pdo, string $type, string $runDate, int $createdBy): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO t_notification_run (
            notification_type,
            run_date,
            started_at,
            finished_at,
            result,
            processed_count,
            success_count,
            skip_count,
            fail_count,
            error_message,
            created_by
         ) VALUES (
            :notification_type,
            :run_date,
            NOW(),
            NOW(),
            "failed",
            1,
            0,
            0,
            1,
            "seeded failed run",
            :created_by
         )'
    );
    $stmt->execute([
        'notification_type' => $type,
        'run_date' => $runDate,
        'created_by' => $createdBy,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return array<string, int>
 */
function findRenewalSeed(PDO $pdo): array
{
    $phaseRow = $pdo->query(
        'SELECT id AS phase_id
         FROM m_renewal_reminder_phase
         WHERE is_enabled = 1
           AND is_deleted = 0
         ORDER BY id ASC
         LIMIT 1'
    )->fetch();
    if (!is_array($phaseRow)) {
        $insertPhase = $pdo->prepare(
            'INSERT INTO m_renewal_reminder_phase (
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
                30,
                0,
                1,
                :display_order,
                0,
                :created_by,
                :updated_by
             )'
        );
        $codeSuffix = date('His');
        $insertPhase->execute([
            'phase_code' => 'PH6_' . $codeSuffix,
            'phase_name' => 'Phase6 Seed ' . $codeSuffix,
            'display_order' => 9000 + (int) date('s'),
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $phaseRow = ['phase_id' => (int) $pdo->lastInsertId()];
    }

    $row = $pdo->query(
        'SELECT id AS renewal_case_id
         FROM t_renewal_case
         WHERE is_deleted = 0
         ORDER BY id ASC
         LIMIT 1'
    )->fetch();

    if (!is_array($row)) {
        $customerMark = 'PH6POL_' . date('YmdHis');
        $insertCustomer = $pdo->prepare(
            'INSERT INTO m_customer (
                customer_type,
                customer_name,
                phone,
                email,
                address1,
                status,
                created_by,
                updated_by
             ) VALUES (
                "individual",
                :customer_name,
                :phone,
                :email,
                :address1,
                "active",
                :created_by,
                :updated_by
             )'
        );
        $insertCustomer->execute([
            'customer_name' => $customerMark,
            'phone' => '07060000001',
            'email' => strtolower($customerMark) . '@example.local',
            'address1' => 'Phase6 Policy Seed',
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $customerId = (int) $pdo->lastInsertId();

        $insertContract = $pdo->prepare(
            'INSERT INTO t_contract (
                customer_id,
                policy_no,
                insurer_name,
                product_type,
                policy_start_date,
                policy_end_date,
                premium_amount,
                payment_cycle,
                status,
                created_by,
                updated_by
             ) VALUES (
                :customer_id,
                :policy_no,
                "Phase6Ins",
                "auto",
                :policy_start_date,
                :policy_end_date,
                100000,
                "annual",
                "active",
                :created_by,
                :updated_by
             )'
        );
        $insertContract->execute([
            'customer_id' => $customerId,
            'policy_no' => $customerMark,
            'policy_start_date' => date('Y-m-d', strtotime('-1 year')),
            'policy_end_date' => date('Y-m-d', strtotime('+1 year')),
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $contractId = (int) $pdo->lastInsertId();

        $insertRenewal = $pdo->prepare(
            'INSERT INTO t_renewal_case (
                contract_id,
                maturity_date,
                case_status,
                created_by,
                updated_by
             ) VALUES (
                :contract_id,
                :maturity_date,
                "open",
                :created_by,
                :updated_by
             )'
        );
        $insertRenewal->execute([
            'contract_id' => $contractId,
            'maturity_date' => date('Y-m-d', strtotime('+30 days')),
            'created_by' => 1,
            'updated_by' => 1,
        ]);
        $row = ['renewal_case_id' => (int) $pdo->lastInsertId()];
    }

    return [
        'renewal_case_id' => (int) $row['renewal_case_id'],
        'phase_id' => (int) $phaseRow['phase_id'],
    ];
}

/**
 * @return array<string, int>
 */
function findAccidentSeed(PDO $pdo, string $runDate, int $createdBy): array
{
    $row = $pdo->query(
        'SELECT r.id AS rule_id,
                r.accident_case_id
         FROM t_accident_reminder_rule r
         INNER JOIN t_accident_case ac
                 ON ac.id = r.accident_case_id
                AND ac.is_deleted = 0
         WHERE r.is_enabled = 1
           AND r.is_deleted = 0
         ORDER BY r.id ASC
         LIMIT 1'
    )->fetch();

    if (is_array($row)) {
        return [
            'rule_id' => (int) $row['rule_id'],
            'accident_case_id' => (int) $row['accident_case_id'],
        ];
    }

    $caseRow = $pdo->query(
        'SELECT id AS accident_case_id
         FROM t_accident_case
         WHERE is_deleted = 0
         ORDER BY id ASC
         LIMIT 1'
    )->fetch();
    if (!is_array($caseRow)) {
        $customerMark = 'PH6ACC_' . date('YmdHis');
        $insertCustomer = $pdo->prepare(
            'INSERT INTO m_customer (
                customer_type,
                customer_name,
                phone,
                email,
                address1,
                status,
                created_by,
                updated_by
             ) VALUES (
                "individual",
                :customer_name,
                :phone,
                :email,
                :address1,
                "active",
                :created_by,
                :updated_by
             )'
        );
        $insertCustomer->execute([
            'customer_name' => $customerMark,
            'phone' => '07060000002',
            'email' => strtolower($customerMark) . '@example.local',
            'address1' => 'Phase6 Accident Seed',
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
        $customerId = (int) $pdo->lastInsertId();

        $insertContract = $pdo->prepare(
            'INSERT INTO t_contract (
                customer_id,
                policy_no,
                insurer_name,
                product_type,
                policy_start_date,
                policy_end_date,
                premium_amount,
                payment_cycle,
                status,
                created_by,
                updated_by
             ) VALUES (
                :customer_id,
                :policy_no,
                "Phase6Ins",
                "auto",
                :policy_start_date,
                :policy_end_date,
                100000,
                "annual",
                "active",
                :created_by,
                :updated_by
             )'
        );
        $insertContract->execute([
            'customer_id' => $customerId,
            'policy_no' => $customerMark,
            'policy_start_date' => date('Y-m-d', strtotime('-1 year')),
            'policy_end_date' => date('Y-m-d', strtotime('+1 year')),
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
        $contractId = (int) $pdo->lastInsertId();

        $insertCase = $pdo->prepare(
            'INSERT INTO t_accident_case (
                customer_id,
                contract_id,
                accident_no,
                accepted_date,
                accident_date,
                product_type,
                accident_type,
                accident_summary,
                accident_location,
                has_counterparty,
                status,
                priority,
                created_by,
                updated_by
             ) VALUES (
                :customer_id,
                :contract_id,
                :accident_no,
                :accepted_date,
                :accident_date,
                "auto",
                "collision",
                :accident_summary,
                "Tokyo",
                1,
                "accepted",
                "normal",
                :created_by,
                :updated_by
             )'
        );
        $insertCase->execute([
            'customer_id' => $customerId,
            'contract_id' => $contractId,
            'accident_no' => $customerMark,
            'accepted_date' => $runDate,
            'accident_date' => $runDate,
            'accident_summary' => $customerMark . '_SUMMARY',
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
        $caseRow = ['accident_case_id' => (int) $pdo->lastInsertId()];
    }

    $insertRule = $pdo->prepare(
        'INSERT INTO t_accident_reminder_rule (
            accident_case_id,
            is_enabled,
            interval_weeks,
            base_date,
            start_date,
            end_date,
            last_notified_on,
            is_deleted,
            created_by,
            updated_by
         ) VALUES (
            :accident_case_id,
            1,
            1,
            :base_date,
            :start_date,
            NULL,
            NULL,
            0,
            :created_by,
            :updated_by
         )'
    );
    $insertRule->execute([
        'accident_case_id' => (int) $caseRow['accident_case_id'],
        'base_date' => $runDate,
        'start_date' => $runDate,
        'created_by' => $createdBy,
        'updated_by' => $createdBy,
    ]);

    return [
        'rule_id' => (int) $pdo->lastInsertId(),
        'accident_case_id' => (int) $caseRow['accident_case_id'],
    ];
}

function insertSeededRenewalFailure(PDO $pdo, int $runId, int $renewalCaseId, int $phaseId, string $scheduledDate): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO t_notification_delivery (
            notification_run_id,
            notification_type,
            renewal_case_id,
            accident_case_id,
            renewal_reminder_phase_id,
            accident_reminder_rule_id,
            scheduled_date,
            notified_at,
            delivery_status,
            error_message
         ) VALUES (
            :run_id,
            "renewal",
            :renewal_case_id,
            NULL,
            :phase_id,
            NULL,
            :scheduled_date,
            NOW(),
            "failed",
            "[attempt:1] seeded renewal failure"
         )'
    );
    $stmt->execute([
        'run_id' => $runId,
        'renewal_case_id' => $renewalCaseId,
        'phase_id' => $phaseId,
        'scheduled_date' => $scheduledDate,
    ]);

    return (int) $pdo->lastInsertId();
}

function insertSeededAccidentFailure(PDO $pdo, int $runId, int $accidentCaseId, int $ruleId, string $scheduledDate): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO t_notification_delivery (
            notification_run_id,
            notification_type,
            renewal_case_id,
            accident_case_id,
            renewal_reminder_phase_id,
            accident_reminder_rule_id,
            scheduled_date,
            notified_at,
            delivery_status,
            error_message
         ) VALUES (
            :run_id,
            "accident",
            NULL,
            :accident_case_id,
            NULL,
            :rule_id,
            :scheduled_date,
            NOW(),
            "failed",
            "[attempt:3] seeded accident failure"
         )'
    );
    $stmt->execute([
        'run_id' => $runId,
        'accident_case_id' => $accidentCaseId,
        'rule_id' => $ruleId,
        'scheduled_date' => $scheduledDate,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return array<string, mixed>
 */
function fetchDelivery(PDO $pdo, int $deliveryId): array
{
    $stmt = $pdo->prepare(
        'SELECT id,
                notification_run_id,
                delivery_status,
                error_message,
                notified_at,
                created_at
         FROM t_notification_delivery
         WHERE id = :id'
    );
    $stmt->execute(['id' => $deliveryId]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        throw new RuntimeException('DELIVERY_NOT_FOUND');
    }

    return $row;
}

function findUnusedScheduledDate(PDO $pdo, string $type, int $entityId, int $ruleOrPhaseId, int $startOffsetDays): string
{
    if ($type === 'renewal') {
        $sql = 'SELECT COUNT(*)
                FROM t_notification_delivery
                WHERE notification_type = "renewal"
                  AND renewal_case_id = :entity_id
                  AND renewal_reminder_phase_id = :sub_id
                  AND scheduled_date = :scheduled_date';
    } else {
        $sql = 'SELECT COUNT(*)
                FROM t_notification_delivery
                WHERE notification_type = "accident"
                  AND accident_case_id = :entity_id
                  AND accident_reminder_rule_id = :sub_id
                  AND scheduled_date = :scheduled_date';
    }

    $stmt = $pdo->prepare($sql);
    for ($offset = $startOffsetDays; $offset < $startOffsetDays + 365; $offset++) {
        $scheduledDate = date('Y-m-d', strtotime('+' . $offset . ' days'));
        $stmt->execute([
            'entity_id' => $entityId,
            'sub_id' => $ruleOrPhaseId,
            'scheduled_date' => $scheduledDate,
        ]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $scheduledDate;
        }
    }

    throw new RuntimeException('NO_UNUSED_SCHEDULED_DATE');
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

$env = loadEnv($root . DIRECTORY_SEPARATOR . '.env');
$commonHost = (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1');
$commonPort = (int) ($env['COMMON_DB_PORT'] ?? 3306);
$commonDb = (string) ($env['COMMON_DB_NAME'] ?? '');
$commonUser = (string) ($env['COMMON_DB_USER'] ?? '');
$commonPass = (string) ($env['COMMON_DB_PASSWORD'] ?? '');
$tenantHost = (string) ($env['TENANT_DB_HOST'] ?? $commonHost);
$tenantPort = (int) ($env['TENANT_DB_PORT'] ?? $commonPort);
$tenantUser = (string) ($env['TENANT_DB_USER'] ?? $commonUser);
$tenantPass = (string) ($env['TENANT_DB_PASSWORD'] ?? $commonPass);

$commonPdo = pdoConn($commonHost, $commonPort, $commonDb, $commonUser, $commonPass);
$tenantStmt = $commonPdo->prepare('SELECT db_name FROM tenants WHERE tenant_code = :tenant_code AND status = 1 AND is_deleted = 0 LIMIT 1');
$tenantStmt->execute(['tenant_code' => 'TE001']);
$tenantRow = $tenantStmt->fetch();
if (!is_array($tenantRow)) {
    throw new RuntimeException('TENANT_NOT_FOUND');
}
$tenantPdo = pdoConn($tenantHost, $tenantPort, (string) $tenantRow['db_name'], $tenantUser, $tenantPass);

$checks = [];
$details = [];

$phpBin = PHP_BINARY;
$batchScript = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'batch' . DIRECTORY_SEPARATOR . 'run_renewal_notification.php';
$runDate = date('Y-m-d');
$tenantCode = 'TE001';
$executedBy = 1;

$fullRun = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=all',
]);

$checks['phase6_full_run_exit_zero'] = $fullRun['exit_code'] === 0;
$checks['phase6_full_run_has_json'] = is_array($fullRun['json']);
$checks['phase6_full_run_all_success'] = is_array($fullRun['json']) && (($fullRun['json']['all_success'] ?? false) === true);

$fullResults = is_array($fullRun['json']) && is_array($fullRun['json']['results'] ?? null)
    ? $fullRun['json']['results']
    : [];
$renewalResult = findTypeResult($fullResults, 'renewal');
$accidentResult = findTypeResult($fullResults, 'accident');

$checks['phase6_full_run_has_renewal_result'] = is_array($renewalResult);
$checks['phase6_full_run_has_accident_result'] = is_array($accidentResult);

$renewalRunId = is_array($renewalResult) ? (int) ($renewalResult['notification_run_id'] ?? 0) : 0;
$accidentRunId = is_array($accidentResult) ? (int) ($accidentResult['run_id'] ?? 0) : 0;
$checks['phase6_full_run_renewal_run_id_valid'] = $renewalRunId > 0;
$checks['phase6_full_run_accident_run_id_valid'] = $accidentRunId > 0;

$renewalSeed = findRenewalSeed($tenantPdo);
$renewalPolicySourceRunId = createManualRun($tenantPdo, 'renewal', $runDate, $executedBy);
$renewalScheduledDate = findUnusedScheduledDate(
    $tenantPdo,
    'renewal',
    $renewalSeed['renewal_case_id'],
    $renewalSeed['phase_id'],
    30
);
$renewalPolicyDeliveryId = insertSeededRenewalFailure(
    $tenantPdo,
    $renewalPolicySourceRunId,
    $renewalSeed['renewal_case_id'],
    $renewalSeed['phase_id'],
    $renewalScheduledDate
);

$renewalRetryBlocked = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=renewal',
    '--retry-failed-run-id=' . (string) $renewalPolicySourceRunId,
    '--retry-minutes=60',
]);
$renewalBlockedResults = is_array($renewalRetryBlocked['json']) && is_array($renewalRetryBlocked['json']['results'] ?? null)
    ? $renewalRetryBlocked['json']['results']
    : [];
$renewalBlockedResult = findTypeResult($renewalBlockedResults, 'renewal');
$renewalBlockedDelivery = fetchDelivery($tenantPdo, $renewalPolicyDeliveryId);

$checks['phase6_retry_renewal_backoff_exit_zero'] = $renewalRetryBlocked['exit_code'] === 0;
$checks['phase6_retry_renewal_backoff_skip_count'] = is_array($renewalBlockedResult)
    && (int) ($renewalBlockedResult['skip_count'] ?? 0) === 1;
$checks['phase6_retry_renewal_backoff_keeps_failed_state'] = (string) ($renewalBlockedDelivery['delivery_status'] ?? '') === 'failed'
    && (int) ($renewalBlockedDelivery['notification_run_id'] ?? 0) === $renewalPolicySourceRunId;

$renewalRetryAllowed = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=renewal',
    '--retry-failed-run-id=' . (string) $renewalPolicySourceRunId,
    '--retry-minutes=0',
    '--retry-max-attempts=3',
]);
$renewalAllowedResults = is_array($renewalRetryAllowed['json']) && is_array($renewalRetryAllowed['json']['results'] ?? null)
    ? $renewalRetryAllowed['json']['results']
    : [];
$renewalAllowedResult = findTypeResult($renewalAllowedResults, 'renewal');
$renewalAllowedDelivery = fetchDelivery($tenantPdo, $renewalPolicyDeliveryId);

$checks['phase6_retry_renewal_allowed_result_exists'] = is_array($renewalAllowedResult);
$checks['phase6_retry_renewal_allowed_success'] = is_array($renewalAllowedResult)
    && (int) ($renewalAllowedResult['success_count'] ?? 0) === 1;
$checks['phase6_retry_renewal_allowed_updates_delivery'] = (string) ($renewalAllowedDelivery['delivery_status'] ?? '') === 'success'
    && (int) ($renewalAllowedDelivery['notification_run_id'] ?? 0) === (int) ($renewalAllowedResult['notification_run_id'] ?? 0);

$accidentSeed = findAccidentSeed($tenantPdo, $runDate, $executedBy);
$accidentPolicySourceRunId = createManualRun($tenantPdo, 'accident', $runDate, $executedBy);
$accidentScheduledDate = findUnusedScheduledDate(
    $tenantPdo,
    'accident',
    $accidentSeed['accident_case_id'],
    $accidentSeed['rule_id'],
    60
);
$accidentPolicyDeliveryId = insertSeededAccidentFailure(
    $tenantPdo,
    $accidentPolicySourceRunId,
    $accidentSeed['accident_case_id'],
    $accidentSeed['rule_id'],
    $accidentScheduledDate
);

$accidentRetryBlocked = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=accident',
    '--retry-failed-run-id=' . (string) $accidentPolicySourceRunId,
    '--retry-max-attempts=3',
]);
$accidentBlockedResults = is_array($accidentRetryBlocked['json']) && is_array($accidentRetryBlocked['json']['results'] ?? null)
    ? $accidentRetryBlocked['json']['results']
    : [];
$accidentBlockedResult = findTypeResult($accidentBlockedResults, 'accident');
$accidentBlockedDelivery = fetchDelivery($tenantPdo, $accidentPolicyDeliveryId);

$checks['phase6_retry_accident_max_attempts_exit_zero'] = $accidentRetryBlocked['exit_code'] === 0;
$checks['phase6_retry_accident_max_attempts_skip_count'] = is_array($accidentBlockedResult)
    && (int) ($accidentBlockedResult['skip_count'] ?? 0) === 1;
$checks['phase6_retry_accident_max_attempts_keeps_failed_state'] = (string) ($accidentBlockedDelivery['delivery_status'] ?? '') === 'failed'
    && (int) ($accidentBlockedDelivery['notification_run_id'] ?? 0) === $accidentPolicySourceRunId;

$accidentRetryAllowed = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=accident',
    '--retry-failed-run-id=' . (string) $accidentPolicySourceRunId,
    '--retry-max-attempts=4',
]);
$accidentAllowedResults = is_array($accidentRetryAllowed['json']) && is_array($accidentRetryAllowed['json']['results'] ?? null)
    ? $accidentRetryAllowed['json']['results']
    : [];
$accidentAllowedResult = findTypeResult($accidentAllowedResults, 'accident');
$accidentAllowedDelivery = fetchDelivery($tenantPdo, $accidentPolicyDeliveryId);

$checks['phase6_retry_accident_allowed_result_exists'] = is_array($accidentAllowedResult);
$checks['phase6_retry_accident_allowed_success'] = is_array($accidentAllowedResult)
    && (int) ($accidentAllowedResult['success_count'] ?? 0) === 1;
$checks['phase6_retry_accident_allowed_updates_delivery'] = (string) ($accidentAllowedDelivery['delivery_status'] ?? '') === 'success'
    && (int) ($accidentAllowedDelivery['notification_run_id'] ?? 0) === (int) ($accidentAllowedResult['run_id'] ?? 0);

$renewalRetry = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=renewal',
    '--retry-failed-run-id=' . (string) max(1, $renewalRunId),
]);
$checks['phase6_retry_renewal_exit_zero'] = $renewalRetry['exit_code'] === 0;
$checks['phase6_retry_renewal_has_json'] = is_array($renewalRetry['json']);

$renewalRetryResults = is_array($renewalRetry['json']) && is_array($renewalRetry['json']['results'] ?? null)
    ? $renewalRetry['json']['results']
    : [];
$renewalRetryResult = findTypeResult($renewalRetryResults, 'renewal');

$checks['phase6_retry_renewal_result_exists'] = is_array($renewalRetryResult);
$checks['phase6_retry_renewal_run_id_differs'] = is_array($renewalRetryResult)
    && (int) ($renewalRetryResult['notification_run_id'] ?? 0) !== $renewalRunId;
$checks['phase6_retry_renewal_reference_kept'] = is_array($renewalRetryResult)
    && (int) ($renewalRetryResult['retry_failed_run_id'] ?? 0) === $renewalRunId;
$checks['phase6_retry_renewal_policy_echoed'] = is_array($renewalRetryResult)
    && (int) (($renewalRetryResult['retry_policy']['max_attempts'] ?? 0)) === 3
    && (int) (($renewalRetryResult['retry_policy']['min_retry_minutes'] ?? 0)) === 0;

$accidentRetry = runBatch($phpBin, $batchScript, [
    '--date=' . $runDate,
    '--tenant=' . $tenantCode,
    '--executed-by=' . (string) $executedBy,
    '--type=accident',
    '--retry-failed-run-id=' . (string) max(1, $accidentRunId),
]);
$checks['phase6_retry_accident_exit_zero'] = $accidentRetry['exit_code'] === 0;
$checks['phase6_retry_accident_has_json'] = is_array($accidentRetry['json']);

$accidentRetryResults = is_array($accidentRetry['json']) && is_array($accidentRetry['json']['results'] ?? null)
    ? $accidentRetry['json']['results']
    : [];
$accidentRetryResult = findTypeResult($accidentRetryResults, 'accident');

$checks['phase6_retry_accident_result_exists'] = is_array($accidentRetryResult);
$checks['phase6_retry_accident_run_id_differs'] = is_array($accidentRetryResult)
    && (int) ($accidentRetryResult['run_id'] ?? 0) !== $accidentRunId;
$checks['phase6_retry_accident_reference_kept'] = is_array($accidentRetryResult)
    && (int) ($accidentRetryResult['retry_failed_run_id'] ?? 0) === $accidentRunId;
$checks['phase6_retry_accident_policy_echoed'] = is_array($accidentRetryResult)
    && (int) (($accidentRetryResult['retry_policy']['max_attempts'] ?? 0)) === 3
    && (int) (($accidentRetryResult['retry_policy']['min_retry_minutes'] ?? 0)) === 0;

$details['full_run'] = $fullRun;
$details['renewal_retry_blocked'] = $renewalRetryBlocked;
$details['renewal_retry_allowed'] = $renewalRetryAllowed;
$details['renewal_retry'] = $renewalRetry;
$details['accident_retry_blocked'] = $accidentRetryBlocked;
$details['accident_retry_allowed'] = $accidentRetryAllowed;
$details['accident_retry'] = $accidentRetry;

$allPassed = !in_array(false, $checks, true);

echo json_encode([
    'all_passed' => $allPassed,
    'checks' => $checks,
    'details' => $details,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($allPassed ? 0 : 1);
