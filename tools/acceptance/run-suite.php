<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2);
$baseDir = __DIR__;

$argvCopy = $argv;
array_shift($argvCopy);
$suite = 'minimal';
foreach ($argvCopy as $arg) {
    if (str_starts_with($arg, '--suite=')) {
        $suite = substr($arg, strlen('--suite='));
    }
}

$configPath = $baseDir . DIRECTORY_SEPARATOR . 'acceptance-suites.json';
if (!is_file($configPath)) {
    fwrite(STDERR, "acceptance-suites.json not found\n");
    exit(2);
}

$config = json_decode((string) file_get_contents($configPath), true);
if (!is_array($config) || !isset($config[$suite]) || !is_array($config[$suite])) {
    fwrite(STDERR, "Unknown suite: {$suite}\n");
    exit(2);
}

$phpBin = PHP_BINARY;
$targets = $config[$suite];
$results = [];
$allPassed = true;

foreach ($targets as $relPath) {
    $script = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relPath);
    if (!is_file($script)) {
        $results[] = [
            'script' => $relPath,
            'ok' => false,
            'reason' => 'script_not_found',
        ];
        $allPassed = false;
        continue;
    }

    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($script);
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $joined = implode("\n", $output);

    $json = json_decode($joined, true);
    $passed = false;
    if (is_array($json) && array_key_exists('all_passed', $json)) {
        $passed = ($json['all_passed'] === true);
    } elseif (is_array($json) && isset($json['checks']) && is_array($json['checks'])) {
        $passed = true;
        foreach ($json['checks'] as $value) {
            if ($value !== true) {
                $passed = false;
                break;
            }
        }
    }

    if (!$passed) {
        $allPassed = false;
    }

    $results[] = [
        'script' => $relPath,
        'exit_code' => $code,
        'ok' => $passed,
        'raw' => $json ?? $joined,
    ];
}

$summary = [
    'suite' => $suite,
    'all_passed' => $allPassed,
    'results' => $results,
];

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($allPassed ? 0 : 1);
