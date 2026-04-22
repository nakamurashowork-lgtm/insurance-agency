<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use PDO;

final class SjnetImportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * バッチレコードを新規作成し、IDを返す
     */
    public function createBatch(string $fileName, string $encoding, int $executedBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_batch
               (file_name, source_encoding, import_status, executed_by)
             VALUES
               (:file_name, :source_encoding, \'running\', :executed_by)'
        );
        $stmt->bindValue(':file_name', $fileName);
        $stmt->bindValue(':source_encoding', $encoding);
        $stmt->bindValue(':executed_by', $executedBy, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * バッチを完了状態に更新する
     *
     * @param array{total: int, valid: int, skip: int, insert: int, update: int, customer_insert: int, unlinked: int, error: int} $counters
     */
    public function finishBatch(int $batchId, array $counters): void
    {
        $insertCount   = (int) ($counters['insert'] ?? 0);
        $updateCount   = (int) ($counters['update'] ?? 0);
        $errorCount    = (int) ($counters['error'] ?? 0);

        if ($errorCount === 0) {
            $status = 'success';
        } elseif ($insertCount + $updateCount > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        $stmt = $this->pdo->prepare(
            'UPDATE t_sjnet_import_batch
             SET import_status         = :status,
                 total_row_count       = :total,
                 valid_row_count       = :valid,
                 duplicate_skip_count  = :skip,
                 insert_count          = :insert,
                 update_count          = :update,
                 customer_insert_count = :customer_insert,
                 unlinked_count        = :unlinked,
                 error_count           = :error,
                 finished_at           = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':total', (int) ($counters['total'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':valid', (int) ($counters['valid'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':skip', (int) ($counters['skip'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':insert', $insertCount, PDO::PARAM_INT);
        $stmt->bindValue(':update', $updateCount, PDO::PARAM_INT);
        $stmt->bindValue(':customer_insert', (int) ($counters['customer_insert'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':unlinked', (int) ($counters['unlinked'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':error', $errorCount, PDO::PARAM_INT);
        $stmt->bindValue(':id', $batchId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * バッチを失敗状態に更新する（例外発生時など）
     */
    public function failBatch(int $batchId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sjnet_import_batch
             SET import_status = \'failed\', finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $batchId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * 取込行を記録する
     *
     * @param array<string, mixed> $data
     */
    public function insertRow(int $batchId, int $rowNo, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_row
               (sjnet_import_batch_id, row_no, raw_payload_json,
                policy_no, customer_name, maturity_date,
                sjnet_agency_code, sjnet_staff_name,
                resolved_staff_id, staff_mapping_status,
                matched_contract_id, matched_renewal_case_id,
                row_status, error_message)
             VALUES
               (:batch_id, :row_no, :raw_json,
                :policy_no, :customer_name, :maturity_date,
                :agency_code, :staff_name,
                :resolved_uid, :mapping_status,
                :contract_id, :renewal_case_id,
                :row_status, :error_message)'
        );

        $rawJson = json_encode($data['raw'] ?? [], JSON_UNESCAPED_UNICODE) ?: '{}';
        $maturityDate = $data['maturity_date'] ?? null;
        $resolvedUid  = isset($data['resolved_staff_id']) && $data['resolved_staff_id'] > 0
            ? (int) $data['resolved_staff_id'] : null;
        $contractId   = isset($data['matched_contract_id']) && $data['matched_contract_id'] > 0
            ? (int) $data['matched_contract_id'] : null;
        $renewalId    = isset($data['matched_renewal_case_id']) && $data['matched_renewal_case_id'] > 0
            ? (int) $data['matched_renewal_case_id'] : null;

        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->bindValue(':row_no', $rowNo, PDO::PARAM_INT);
        $stmt->bindValue(':raw_json', $rawJson);
        $stmt->bindValue(':policy_no', $data['policy_no'] ?? null);
        $stmt->bindValue(':customer_name', $data['customer_name'] ?? null);
        $stmt->bindValue(':maturity_date', $maturityDate);
        $stmt->bindValue(':agency_code', $data['sjnet_agency_code'] ?? null);
        $stmt->bindValue(':staff_name', $data['sjnet_staff_name'] ?? null);
        $stmt->bindValue(':resolved_uid', $resolvedUid, $resolvedUid !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':mapping_status', $data['staff_mapping_status'] ?? null);
        $stmt->bindValue(':contract_id', $contractId, $contractId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':renewal_case_id', $renewalId, $renewalId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':row_status', $data['row_status'] ?? 'error');
        $stmt->bindValue(':error_message', $data['error_message'] ?? null);
        $stmt->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBatchById(int $batchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, file_name, source_encoding, import_status,
                    total_row_count, valid_row_count, duplicate_skip_count,
                    insert_count, update_count, customer_insert_count, unlinked_count, error_count,
                    started_at, finished_at
             FROM t_sjnet_import_batch
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $batchId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRowsByBatchId(int $batchId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT row_no, policy_no, customer_name, maturity_date,
                    sjnet_agency_code, sjnet_staff_name,
                    resolved_staff_id, staff_mapping_status,
                    matched_contract_id, matched_renewal_case_id,
                    row_status, error_message
             FROM t_sjnet_import_row
             WHERE sjnet_import_batch_id = :batch_id
             ORDER BY row_no ASC
             LIMIT :lim'
        );
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
