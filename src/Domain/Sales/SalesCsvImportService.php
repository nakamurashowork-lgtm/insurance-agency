<?php
declare(strict_types=1);

namespace App\Domain\Sales;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class SalesCsvImportService
{
    private const REQUIRED_HEADERS = [
        'receipt_no',
        'policy_no',
        'customer_name',
        'maturity_date',
        'performance_date',
        'performance_type',
        'insurance_category',
        'product_type',
        'premium_amount',
        'settlement_month',
        'remark',
    ];

    private const ALLOWED_TYPES = ['new', 'renewal', 'addition', 'change', 'cancel_deduction'];

    public function __construct(private SalesPerformanceRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function importFile(string $filePath, string $originalFileName, int $executedBy): array
    {
        $raw = @file_get_contents($filePath);
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('CSV_READ_FAILED');
        }

        $sourceEncoding = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'CP932'], true) ?: 'UTF-8';
        $normalized = $sourceEncoding === 'UTF-8' ? $raw : mb_convert_encoding($raw, 'UTF-8', $sourceEncoding);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $lines = preg_split('/\r\n|\n|\r/', $normalized) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        if ($lines === []) {
            throw new RuntimeException('CSV_EMPTY');
        }

        $header = array_map('trim', str_getcsv(array_shift($lines)) ?: []);
        $this->assertHeaders($header);
        $headerMap = array_flip($header);

        $batchId = $this->repository->createImportBatch($originalFileName, $sourceEncoding, $executedBy);
        $summary = [
            'total_row_count' => 0,
            'valid_row_count' => 0,
            'duplicate_skip_count' => 0,
            'insert_count' => 0,
            'update_count' => 0,
            'error_count' => 0,
        ];

        foreach ($lines as $index => $line) {
            $rowNo = $index + 2;
            $summary['total_row_count']++;
            $columns = str_getcsv($line) ?: [];
            $payload = [];
            foreach ($header as $pos => $name) {
                $payload[$name] = isset($columns[$pos]) ? trim((string) $columns[$pos]) : '';
            }

            $importRow = [
                'row_no' => $rowNo,
                'raw_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'policy_no' => $payload['policy_no'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'maturity_date' => $payload['maturity_date'] !== '' ? $payload['maturity_date'] : null,
                'matched_contract_id' => null,
                'matched_renewal_case_id' => null,
                'row_status' => 'error',
                'error_message' => null,
            ];

            try {
                $input = $this->buildSalesInput($payload, $headerMap);
                $contract = $this->repository->findContractByPolicyNo((string) $payload['policy_no']);
                if ($contract === null) {
                    throw new RuntimeException('契約が見つかりません。');
                }

                $importRow['matched_contract_id'] = (int) ($contract['id'] ?? 0);
                $renewal = $this->repository->findRenewalCaseByContractAndMaturityDate(
                    (int) $contract['id'],
                    $payload['maturity_date'] !== '' ? (string) $payload['maturity_date'] : null
                );
                if ($renewal !== null) {
                    $importRow['matched_renewal_case_id'] = (int) ($renewal['id'] ?? 0);
                }

                $input['customer_id'] = (int) ($contract['customer_id'] ?? 0);
                $input['contract_id'] = (int) ($contract['id'] ?? 0);
                $input['renewal_case_id'] = $renewal !== null ? (int) ($renewal['id'] ?? 0) : null;

                $summary['valid_row_count']++;

                $existing = $this->repository->findActiveByReceiptNo((string) $payload['receipt_no']);
                if ($existing === null) {
                    $this->repository->create($input, $executedBy);
                    $summary['insert_count']++;
                    $importRow['row_status'] = 'insert';
                } else {
                    $this->repository->update((int) $existing['id'], $input, $executedBy);
                    $summary['update_count']++;
                    $importRow['row_status'] = 'update';
                }
            } catch (Throwable $e) {
                $summary['error_count']++;
                $importRow['row_status'] = 'error';
                $importRow['error_message'] = $e->getMessage();
            }

            $this->repository->createImportRow($batchId, $importRow);
        }

        $status = 'success';
        if ($summary['total_row_count'] === 0 || $summary['error_count'] === $summary['total_row_count']) {
            $status = 'failed';
        } elseif ($summary['error_count'] > 0) {
            $status = 'partial';
        }

        $this->repository->finalizeImportBatch($batchId, $summary, $status);

        return [
            'batch_id' => $batchId,
            'status' => $status,
        ];
    }

    /**
     * @param array<int, string> $header
     */
    private function assertHeaders(array $header): void
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $header, true)) {
                throw new RuntimeException('CSVヘッダが不足しています: ' . $required);
            }
        }
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, int> $headerMap
     * @return array<string, mixed>
     */
    private function buildSalesInput(array $payload, array $headerMap): array
    {
        unset($headerMap);

        $receiptNo = trim((string) ($payload['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            throw new RuntimeException('receipt_no は必須です。');
        }

        $performanceDate = trim((string) ($payload['performance_date'] ?? ''));
        if (!$this->isValidDate($performanceDate)) {
            throw new RuntimeException('performance_date が不正です。');
        }

        $performanceType = trim((string) ($payload['performance_type'] ?? ''));
        if (!in_array($performanceType, self::ALLOWED_TYPES, true)) {
            throw new RuntimeException('performance_type が不正です。');
        }

        $maturityDate = trim((string) ($payload['maturity_date'] ?? ''));
        if ($maturityDate !== '' && !$this->isValidDate($maturityDate)) {
            throw new RuntimeException('maturity_date が不正です。');
        }

        $premiumRaw = trim((string) ($payload['premium_amount'] ?? ''));
        if ($premiumRaw === '' || !is_numeric($premiumRaw) || (float) $premiumRaw < 0) {
            throw new RuntimeException('premium_amount が不正です。');
        }

        $settlementMonth = trim((string) ($payload['settlement_month'] ?? ''));
        if ($settlementMonth !== '' && !preg_match('/^\d{4}-\d{2}$/', $settlementMonth)) {
            throw new RuntimeException('settlement_month が不正です。');
        }

        $policyNo = trim((string) ($payload['policy_no'] ?? ''));
        if ($policyNo === '') {
            throw new RuntimeException('policy_no は必須です。');
        }

        return [
            'customer_id' => 0,
            'contract_id' => null,
            'renewal_case_id' => null,
            'performance_date' => $performanceDate,
            'performance_type' => $performanceType,
            'insurance_category' => $this->nullableText($payload['insurance_category'] ?? ''),
            'product_type' => $this->nullableText($payload['product_type'] ?? ''),
            'premium_amount' => (int) round((float) $premiumRaw),
            'receipt_no' => $receiptNo,
            'settlement_month' => $this->nullableText($settlementMonth),
            'staff_user_id' => null,
            'remark' => $this->nullableText($payload['remark'] ?? ''),
        ];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function nullableText(string $value): ?string
    {
        $text = trim($value);
        return $text === '' ? null : $text;
    }
}