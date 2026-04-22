<?php
declare(strict_types=1);

// オートローダー
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) require_once $path;
});

$pdo = new PDO('mysql:host=127.0.0.1;dbname=xs000001_te001;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csvPath = __DIR__ . '/fixtures/phase_a_test.csv';
$service = new App\Domain\Renewal\SjnetCsvImportService($pdo, 1, new DateTimeImmutable('today'));

echo "=== CSV取込実行 ===\n";
$result = $service->import($csvPath, 'phase_a_test.csv');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

$batchId = (int) $result['batch_id'];

// 取込行ステータスの確認
echo "=== 取込行ステータス ===\n";
$stmt = $pdo->prepare(
    'SELECT row_no, policy_no, customer_name, row_status, error_message
     FROM t_sjnet_import_row
     WHERE sjnet_import_batch_id = :bid
     ORDER BY row_no'
);
$stmt->execute([':bid' => $batchId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $err = $r['error_message'] ? ' ERR:' . $r['error_message'] : '';
    echo sprintf("  row%d [%s] policy=%s customer=%s%s\n",
        $r['row_no'], $r['row_status'], $r['policy_no'] ?? '(null)', $r['customer_name'] ?? '(null)', $err);
}

// 顧客マッチング確認
echo "\n=== 顧客マッチング結果 ===\n";
$scenarios = [
    'A（新規）' => 'フェーズAテスト花子',
    'B（1件ヒット）' => 'フェーズBテスト太郎',
    'C（複数ヒット）' => 'フェーズC同姓同名',
    'D（生年月日区別）' => 'フェーズDテスト山田',
];
foreach ($scenarios as $label => $name) {
    $stmt = $pdo->prepare(
        'SELECT id, customer_name, birth_date, phone, address1, note
         FROM m_customer WHERE customer_name = :name AND is_deleted = 0 ORDER BY id'
    );
    $stmt->execute([':name' => $name]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  シナリオ{$label}: {$name} → " . count($customers) . "件\n";
    foreach ($customers as $c) {
        echo sprintf("    id=%d birth=%s phone=%s note=%s\n",
            $c['id'], $c['birth_date'] ?? 'NULL', $c['phone'] ?? 'NULL', $c['note'] ?? '');
    }
}

// シナリオC 契約のcustomer_id確認
echo "\n=== シナリオC 契約のcustomer_id ===\n";
$stmt = $pdo->prepare(
    'SELECT id, customer_id, sjnet_customer_name, policy_no
     FROM t_contract WHERE policy_no = :pno AND is_deleted = 0'
);
$stmt->execute([':pno' => 'TEST-PHASE-C-001']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo sprintf("  contract_id=%d customer_id=%s sjnet_name=%s\n",
        $row['id'], $row['customer_id'] ?? 'NULL', $row['sjnet_customer_name'] ?? '');
} else {
    echo "  契約レコードが見つかりません\n";
}
