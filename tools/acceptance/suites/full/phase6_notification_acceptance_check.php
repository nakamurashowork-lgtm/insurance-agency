<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

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

$details['full_run'] = $fullRun;
$details['renewal_retry'] = $renewalRetry;
$details['accident_retry'] = $accidentRetry;

$allPassed = !in_array(false, $checks, true);

echo json_encode([
    'all_passed' => $allPassed,
    'checks' => $checks,
    'details' => $details,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($allPassed ? 0 : 1);
