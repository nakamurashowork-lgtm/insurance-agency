<?php
declare(strict_types=1);

/**
 * 担当者マッピング & 満期通知フェーズ 初期データ投入
 *
 * 使い方: php tools/seed_staff_and_phases.php
 *
 * ※ 開発環境専用。
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$envPath = dirname(__DIR__) . '/.env';
if (!file_exists($envPath)) {
    echo "ERROR: .env not found at {$envPath}\n";
    exit(1);
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);
    if (strlen($v) >= 2 && $v[0] === '"' && $v[-1] === '"') {
        $v = substr($v, 1, -1);
    }
    $env[$k] = $v;
}

function pdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

$commonHost = (string) ($env['COMMON_DB_HOST'] ?? '127.0.0.1');
$commonPort = (int)   ($env['COMMON_DB_PORT'] ?? 3306);
$commonDb   = (string) ($env['COMMON_DB_NAME'] ?? '');
$commonUser = (string) ($env['COMMON_DB_USER'] ?? '');
$commonPass = (string) ($env['COMMON_DB_PASSWORD'] ?? '');

$tenantHost = (string) ($env['TENANT_DB_HOST'] ?? $commonHost);
$tenantPort = (int)   ($env['TENANT_DB_PORT'] ?? $commonPort);
$tenantUser = (string) ($env['TENANT_DB_USER'] ?? $commonUser);
$tenantPass = (string) ($env['TENANT_DB_PASSWORD'] ?? $commonPass);

$common = pdo($commonHost, $commonPort, $commonDb, $commonUser, $commonPass);

$admin = $common->query(
    "SELECT u.id AS user_id, u.name AS user_name, ut.tenant_code, t.db_name
     FROM users u
     INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.status = 1 AND ut.is_deleted = 0
     INNER JOIN tenants t ON t.tenant_code = ut.tenant_code AND t.status = 1 AND t.is_deleted = 0
     WHERE u.status = 1 AND u.is_deleted = 0
     ORDER BY (ut.role = 'admin') DESC, u.id ASC
     LIMIT 1"
)->fetch();

if (!is_array($admin)) {
    echo "ERROR: アクティブユーザーが見つかりません。\n";
    exit(1);
}

$userId = (int)    $admin['user_id'];
$dbName = (string) $admin['db_name'];
echo "ユーザー: {$admin['user_name']} (id={$userId}), DB: {$dbName}\n\n";

$tenant = pdo($tenantHost, $tenantPort, $dbName, $tenantUser, $tenantPass);

// ---- m_renewal_reminder_phase ----
echo "=== m_renewal_reminder_phase ===\n";

$existing = $tenant->query("SELECT COUNT(*) AS cnt FROM m_renewal_reminder_phase WHERE is_deleted = 0")->fetch();
if ((int) ($existing['cnt'] ?? 0) > 0) {
    echo "既にデータあり（{$existing['cnt']}件）。スキップします。\n\n";
} else {
    $stmt = $tenant->prepare(
        "INSERT INTO m_renewal_reminder_phase
           (phase_code, phase_name, from_days_before, to_days_before, is_enabled, display_order, created_by, updated_by)
         VALUES
           ('EARLY',  '早期通知', 90, 61, 1, 1, :uid, :uid),
           ('NORMAL', '通常通知', 60, 31, 1, 2, :uid, :uid),
           ('URGENT', '直前通知', 30,  0, 1, 3, :uid, :uid)"
    );
    $stmt->execute([':uid' => $userId]);
    echo "3件 挿入しました。\n\n";
}

// ---- m_staff_sjnet_mapping ----
echo "=== m_staff_sjnet_mapping ===\n";

$staffData = [
    ['code' => 'N8559007', 'name' => '飯田 光男'],
    ['code' => 'N8559020', 'name' => '横手 輝明'],
    ['code' => 'N8559031', 'name' => '森 雅人'],
];

$insertStmt = $tenant->prepare(
    "INSERT IGNORE INTO m_staff_sjnet_mapping (sjnet_code, staff_name, created_by, updated_by)
     VALUES (:code, :name, :uid, :uid)"
);

$inserted = 0;
foreach ($staffData as $row) {
    $insertStmt->execute([':code' => $row['code'], ':name' => $row['name'], ':uid' => $userId]);
    $count = $insertStmt->rowCount();
    $status = $count > 0 ? '挿入' : 'スキップ（重複）';
    echo "  {$row['code']} / {$row['name']} → {$status}\n";
    $inserted += $count;
}

echo "\n{$inserted}件 挿入しました。\n";
