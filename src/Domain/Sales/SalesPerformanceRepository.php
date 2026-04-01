<?php
declare(strict_types=1);

namespace App\Domain\Sales;

use PDO;

final class SalesPerformanceRepository
{
    public const SORTABLE_FIELDS = [
        'performance_date',
        'performance_type',
        'source_type',
        'customer_name',
        'staff_user_id',
        'insurer_name',
        'policy_no',
        'product_type',
        'premium_amount',
        'settlement_month',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 200): array
    {
        $result = $this->searchPage($criteria, 1, $limit, '', 'asc');
        return $result['rows'];
    }

    /**
     * @param array<string, string> $criteria
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function searchPage(
        array $criteria,
        int $page,
        int $perPage,
        string $sort,
        string $direction
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 500));

        $params = [];
        $whereSql = $this->buildWhereClause($criteria, $params);
        $total = $this->countSearch($whereSql, $params);
        $maxPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $maxPage);
        $offset = ($page - 1) * $perPage;

        $sql =
            'SELECT sp.id,
                    sp.customer_id,
                    sp.contract_id,
                    sp.renewal_case_id,
                    sp.performance_date,
                    sp.performance_type,
                    sp.source_type,
                    sp.insurer_name,
                    sp.policy_no,
                    sp.policy_start_date,
                    sp.application_date,
                    sp.insurance_category,
                    sp.product_type,
                    sp.premium_amount,
                    sp.installment_count,
                    sp.receipt_no,
                    sp.settlement_month,
                    sp.staff_user_id,
                    sp.remark,
                    sp.updated_at,
                    mc.customer_name,
                    c.policy_no AS contract_policy_no,
                    c.policy_start_date AS contract_policy_start_date,
                    c.insurer_name AS contract_insurer_name,
                    c.insurance_category AS contract_insurance_category,
                    c.product_type AS contract_product_type,
                    COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) AS policy_no_display
             FROM t_sales_performance sp
             INNER JOIN m_customer mc
                     ON mc.id = sp.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = sp.contract_id
                   AND c.is_deleted = 0'
            . $whereSql
            . $this->buildOrderBy($sort, $direction)
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, mixed> $params
     */
    private function buildWhereClause(array $criteria, array &$params): string
    {
        $sql = ' WHERE sp.is_deleted = 0';

        $dateFrom = trim((string) ($criteria['performance_date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND sp.performance_date >= :performance_date_from';
            $params['performance_date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($criteria['performance_date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND sp.performance_date <= :performance_date_to';
            $params['performance_date_to'] = $dateTo;
        }

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $staffUserId = trim((string) ($criteria['staff_user_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId)) {
            $sql .= ' AND sp.staff_user_id = :staff_user_id';
            $params['staff_user_id'] = (int) $staffUserId;
        }

        $sourceType = trim((string) ($criteria['source_type'] ?? ''));
        if ($sourceType !== '') {
            $sql .= ' AND sp.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        $performanceType = trim((string) ($criteria['performance_type'] ?? ''));
        if ($performanceType !== '') {
            $sql .= ' AND sp.performance_type = :performance_type';
            $params['performance_type'] = $performanceType;
        }

        $insurerName = trim((string) ($criteria['insurer_name'] ?? ''));
        if ($insurerName !== '') {
            $sql .= ' AND COALESCE(NULLIF(sp.insurer_name, ""), c.insurer_name) LIKE :insurer_name';
            $params['insurer_name'] = '%' . $insurerName . '%';
        }

        $policyNo = trim((string) ($criteria['policy_no'] ?? ''));
        if ($policyNo !== '') {
            $sql .= ' AND COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) LIKE :policy_no';
            $params['policy_no'] = '%' . $policyNo . '%';
        }

        $productType = trim((string) ($criteria['product_type'] ?? ''));
        if ($productType !== '') {
            $sql .= ' AND sp.product_type LIKE :product_type';
            $params['product_type'] = '%' . $productType . '%';
        }

        $settlementMonth = trim((string) ($criteria['settlement_month'] ?? ''));
        if ($settlementMonth !== '') {
            $sql .= ' AND sp.settlement_month = :settlement_month';
            $params['settlement_month'] = $settlementMonth;
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countSearch(string $whereSql, array $params): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM t_sales_performance sp
             INNER JOIN m_customer mc
                     ON mc.id = sp.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = sp.contract_id
                   AND c.is_deleted = 0'
            . $whereSql
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function buildOrderBy(string $sort, string $direction): string
    {
        $column = match ($sort) {
            'performance_type' => 'sp.performance_type',
            'source_type' => 'sp.source_type',
            'customer_name' => 'mc.customer_name',
            'staff_user_id' => 'sp.staff_user_id',
            'insurer_name' => 'COALESCE(NULLIF(sp.insurer_name, ""), c.insurer_name)',
            'policy_no' => 'COALESCE(NULLIF(sp.policy_no, ""), c.policy_no)',
            'product_type' => 'sp.product_type',
            'premium_amount' => 'sp.premium_amount',
            'settlement_month' => 'sp.settlement_month',
            'performance_date' => 'sp.performance_date',
            default => 'sp.performance_date',
        };

        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $fallbackDir = $dir === 'DESC' ? 'DESC' : 'ASC';

        return ' ORDER BY ' . $column . ' ' . $dir . ', sp.id ' . $fallbackDir;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id,
                  sp.customer_id,
                  sp.contract_id,
                  sp.renewal_case_id,
                  sp.performance_date,
                  sp.performance_type,
                sp.source_type,
                sp.insurer_name,
                sp.policy_no,
                sp.policy_start_date,
                sp.application_date,
                  sp.insurance_category,
                  sp.product_type,
                  sp.premium_amount,
                sp.installment_count,
                  sp.receipt_no,
                  sp.settlement_month,
                  sp.staff_user_id,
                  sp.remark,
                  mc.customer_name,
                c.policy_no AS contract_policy_no,
                c.policy_start_date AS contract_policy_start_date,
                c.insurer_name AS contract_insurer_name,
                c.insurance_category AS contract_insurance_category,
                c.product_type AS contract_product_type,
                COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) AS policy_no_display
              FROM t_sales_performance sp
              INNER JOIN m_customer mc
                ON mc.id = sp.customer_id
                  AND mc.is_deleted = 0
              LEFT JOIN t_contract c
                  ON c.id = sp.contract_id
                 AND c.is_deleted = 0
              WHERE sp.id = :id
             AND sp.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_name
             FROM m_customer
             WHERE is_deleted = 0
             ORDER BY customer_name ASC, id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchContracts(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id,
                    c.customer_id,
                    c.policy_no,
                    c.insurer_name,
                    c.insurance_category,
                    c.product_type,
                    c.policy_start_date,
                    c.policy_end_date,
                    mc.customer_name
             FROM t_contract c
             INNER JOIN m_customer mc
                     ON mc.id = c.customer_id
                    AND mc.is_deleted = 0
             WHERE c.is_deleted = 0
             ORDER BY c.policy_end_date DESC, c.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRenewalCases(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rc.id,
                    rc.contract_id,
                    c.customer_id,
                    rc.maturity_date,
                    rc.case_status,
                    c.policy_no
             FROM t_renewal_case rc
             LEFT JOIN t_contract c
                    ON c.id = rc.contract_id
                   AND c.is_deleted = 0
             WHERE rc.is_deleted = 0
             ORDER BY rc.maturity_date DESC, rc.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sales_performance (
                customer_id,
                contract_id,
                renewal_case_id,
                performance_date,
                performance_type,
                source_type,
                insurer_name,
                policy_no,
                policy_start_date,
                application_date,
                insurance_category,
                product_type,
                premium_amount,
                installment_count,
                receipt_no,
                settlement_month,
                staff_user_id,
                remark,
                created_by,
                updated_by
             ) VALUES (
                :customer_id,
                :contract_id,
                :renewal_case_id,
                :performance_date,
                :performance_type,
                :source_type,
                :insurer_name,
                :policy_no,
                :policy_start_date,
                :application_date,
                :insurance_category,
                :product_type,
                :premium_amount,
                :installment_count,
                :receipt_no,
                :settlement_month,
                :staff_user_id,
                :remark,
                :created_by,
                :updated_by
             )'
        );

        $stmt->execute([
            'customer_id' => $input['customer_id'] ?? 0,
            'contract_id' => $input['contract_id'] ?? null,
            'renewal_case_id' => $input['renewal_case_id'] ?? null,
            'performance_date' => $input['performance_date'] ?? null,
            'performance_type' => $input['performance_type'] ?? null,
            'source_type' => $input['source_type'] ?? null,
            'insurer_name' => $input['insurer_name'] ?? null,
            'policy_no' => $input['policy_no'] ?? null,
            'policy_start_date' => $input['policy_start_date'] ?? null,
            'application_date' => $input['application_date'] ?? null,
            'insurance_category' => $input['insurance_category'] ?? null,
            'product_type' => $input['product_type'] ?? null,
            'premium_amount' => $input['premium_amount'] ?? 0,
            'installment_count' => $input['installment_count'] ?? null,
            'receipt_no' => $input['receipt_no'] ?? null,
            'settlement_month' => $input['settlement_month'] ?? null,
            'staff_user_id' => $input['staff_user_id'] ?? null,
            'remark' => $input['remark'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_performance
             SET customer_id = :customer_id,
                 contract_id = :contract_id,
                 renewal_case_id = :renewal_case_id,
                 performance_date = :performance_date,
                 performance_type = :performance_type,
                 source_type = :source_type,
                 insurer_name = :insurer_name,
                 policy_no = :policy_no,
                 policy_start_date = :policy_start_date,
                 application_date = :application_date,
                 insurance_category = :insurance_category,
                 product_type = :product_type,
                 premium_amount = :premium_amount,
                 installment_count = :installment_count,
                 receipt_no = :receipt_no,
                 settlement_month = :settlement_month,
                 staff_user_id = :staff_user_id,
                 remark = :remark,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );

        $stmt->execute([
            'id' => $id,
            'customer_id' => $input['customer_id'] ?? 0,
            'contract_id' => $input['contract_id'] ?? null,
            'renewal_case_id' => $input['renewal_case_id'] ?? null,
            'performance_date' => $input['performance_date'] ?? null,
            'performance_type' => $input['performance_type'] ?? null,
            'source_type' => $input['source_type'] ?? null,
            'insurer_name' => $input['insurer_name'] ?? null,
            'policy_no' => $input['policy_no'] ?? null,
            'policy_start_date' => $input['policy_start_date'] ?? null,
            'application_date' => $input['application_date'] ?? null,
            'insurance_category' => $input['insurance_category'] ?? null,
            'product_type' => $input['product_type'] ?? null,
            'premium_amount' => $input['premium_amount'] ?? 0,
            'installment_count' => $input['installment_count'] ?? null,
            'receipt_no' => $input['receipt_no'] ?? null,
            'settlement_month' => $input['settlement_month'] ?? null,
            'staff_user_id' => $input['staff_user_id'] ?? null,
            'remark' => $input['remark'] ?? null,
            'updated_by' => $userId,
        ]);

        return $stmt->rowCount();
    }

    public function softDelete(int $id, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_performance
             SET is_deleted = 1,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContractByPolicyNo(string $policyNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, policy_no
             FROM t_contract
             WHERE policy_no = :policy_no
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['policy_no' => $policyNo]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRenewalCaseByContractAndMaturityDate(int $contractId, ?string $maturityDate): ?array
    {
        if ($maturityDate === null || $maturityDate === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, contract_id, maturity_date
             FROM t_renewal_case
             WHERE contract_id = :contract_id
               AND maturity_date = :maturity_date
               AND is_deleted = 0
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'contract_id' => $contractId,
            'maturity_date' => $maturityDate,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByReceiptNo(string $receiptNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    customer_id,
                    contract_id,
                    renewal_case_id,
                    performance_date,
                    performance_type,
                    insurance_category,
                    product_type,
                    premium_amount,
                    receipt_no,
                    settlement_month,
                    staff_user_id,
                    remark
             FROM t_sales_performance
             WHERE receipt_no = :receipt_no
               AND is_deleted = 0
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['receipt_no' => $receiptNo]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createImportBatch(string $fileName, string $sourceEncoding, int $executedBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_batch (
                file_name,
                source_encoding,
                import_status,
                executed_by
             ) VALUES (
                :file_name,
                :source_encoding,
                :import_status,
                :executed_by
             )'
        );
        $stmt->execute([
            'file_name' => $fileName,
            'source_encoding' => $sourceEncoding,
            'import_status' => 'running',
            'executed_by' => $executedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function finalizeImportBatch(int $batchId, array $summary, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sjnet_import_batch
             SET import_status = :import_status,
                 total_row_count = :total_row_count,
                 valid_row_count = :valid_row_count,
                 duplicate_skip_count = :duplicate_skip_count,
                 insert_count = :insert_count,
                 update_count = :update_count,
                 error_count = :error_count,
                 finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $batchId,
            'import_status' => $status,
            'total_row_count' => (int) ($summary['total_row_count'] ?? 0),
            'valid_row_count' => (int) ($summary['valid_row_count'] ?? 0),
            'duplicate_skip_count' => (int) ($summary['duplicate_skip_count'] ?? 0),
            'insert_count' => (int) ($summary['insert_count'] ?? 0),
            'update_count' => (int) ($summary['update_count'] ?? 0),
            'error_count' => (int) ($summary['error_count'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function createImportRow(int $batchId, array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_row (
                sjnet_import_batch_id,
                row_no,
                raw_payload_json,
                policy_no,
                customer_name,
                maturity_date,
                matched_contract_id,
                matched_renewal_case_id,
                row_status,
                error_message
             ) VALUES (
                :batch_id,
                :row_no,
                :raw_payload_json,
                :policy_no,
                :customer_name,
                :maturity_date,
                :matched_contract_id,
                :matched_renewal_case_id,
                :row_status,
                :error_message
             )'
        );
        $stmt->execute([
            'batch_id' => $batchId,
            'row_no' => (int) ($row['row_no'] ?? 0),
            'raw_payload_json' => (string) ($row['raw_payload_json'] ?? '{}'),
            'policy_no' => $row['policy_no'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'maturity_date' => $row['maturity_date'] ?? null,
            'matched_contract_id' => $row['matched_contract_id'] ?? null,
            'matched_renewal_case_id' => $row['matched_renewal_case_id'] ?? null,
            'row_status' => (string) ($row['row_status'] ?? 'error'),
            'error_message' => $row['error_message'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findImportBatchById(int $batchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM t_sjnet_import_batch
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $batchId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findImportRowsByBatchId(int $batchId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    row_no,
                    policy_no,
                    customer_name,
                    maturity_date,
                    matched_contract_id,
                    matched_renewal_case_id,
                    row_status,
                    error_message
             FROM t_sjnet_import_row
             WHERE sjnet_import_batch_id = :batch_id
             ORDER BY row_no ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}