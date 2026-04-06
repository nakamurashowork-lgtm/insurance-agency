<?php
declare(strict_types=1);

namespace App\Domain\SalesCase;

use PDO;
use PDOStatement;

final class SalesCaseRepository
{
    /** @var array<string, string> */
    public const ALLOWED_CASE_TYPES = [
        'new'        => '新規',
        'renewal'    => '更新',
        'cross_sell' => 'クロスセル',
        'up_sell'    => 'アップセル',
        'other'      => 'その他',
    ];

    /** @var array<string, string> */
    public const ALLOWED_STATUSES = [
        'open'        => '商談中',
        'negotiating' => '交渉中',
        'won'         => '成約',
        'lost'        => '失注',
        'on_hold'     => '保留',
    ];

    /** @var array<int, string> */
    public const ALLOWED_PROSPECT_RANKS = ['A', 'B', 'C'];

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        'id', 'case_name', 'status', 'prospect_rank',
        'expected_contract_month', 'expected_premium', 'created_at',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int}
     */
    public function searchPage(
        array $criteria,
        int $page,
        int $perPage,
        string $sort = '',
        string $direction = 'asc'
    ): array {
        $where  = ['sc.is_deleted = 0'];
        $params = [];

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $where[] = 'mc.customer_name LIKE :customer_name';
            $params[':customer_name'] = '%' . $customerName . '%';
        }

        $staffUserId = trim((string) ($criteria['staff_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId) && (int) $staffUserId > 0) {
            $where[] = 'sc.staff_id = :staff_id';
            $params[':staff_id'] = (int) $staffUserId;
        }

        $status = trim((string) ($criteria['status'] ?? ''));
        if ($status !== '' && array_key_exists($status, self::ALLOWED_STATUSES)) {
            $where[] = 'sc.status = :status';
            $params[':status'] = $status;
        }

        $prospectRank = trim((string) ($criteria['prospect_rank'] ?? ''));
        if ($prospectRank !== '' && in_array($prospectRank, self::ALLOWED_PROSPECT_RANKS, true)) {
            $where[] = 'sc.prospect_rank = :prospect_rank';
            $params[':prospect_rank'] = $prospectRank;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM t_sales_case sc
                     LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
                     $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $allowedSorts = array_flip(self::SORTABLE_FIELDS);
        $orderColumn  = isset($allowedSorts[$sort]) ? 'sc.' . $sort : 'sc.id';
        $dir          = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $perPage = max(1, min(200, $perPage));
        $maxPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page    = max(1, min($maxPage, $page));
        $offset  = ($page - 1) * $perPage;

        $dataSql = "SELECT sc.id, sc.customer_id, sc.case_name, sc.case_type, sc.product_type,
                           sc.status, sc.prospect_rank, sc.expected_premium,
                           sc.expected_contract_month, sc.staff_id, sc.next_action_date,
                           sc.created_at,
                           mc.customer_name
                    FROM t_sales_case sc
                    LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
                    $whereClause
                    ORDER BY $orderColumn $dir
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        return [
            'rows'  => is_array($rows) ? $rows : [],
            'total' => $total,
            'page'  => $page,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sc.*, mc.customer_name
             FROM t_sales_case sc
             LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
             WHERE sc.id = :id AND sc.is_deleted = 0'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCustomerId(int $customerId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_name, case_type, status, prospect_rank,
                    expected_premium, expected_contract_month, created_at
             FROM t_sales_case
             WHERE customer_id = :customer_id AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_name FROM m_customer WHERE is_deleted = 0 ORDER BY customer_name ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * For use in activity form dropdown. Excludes won/lost cases.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchForDropdown(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sc.id, sc.case_name, mc.customer_name
             FROM t_sales_case sc
             LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
             WHERE sc.is_deleted = 0 AND sc.status NOT IN ('won', 'lost')
             ORDER BY sc.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Activities linked to this sales case (for detail view).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLinkedActivities(int $salesCaseId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.activity_date, a.activity_type, a.subject, a.content_summary,
                    a.next_action_date, a.staff_id
             FROM t_activity a
             WHERE a.sales_case_id = :sales_case_id AND a.is_deleted = 0
             ORDER BY a.activity_date DESC, a.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':sales_case_id', $salesCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(array $input, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sales_case
                (customer_id, contract_id, case_name, case_type, product_type, status,
                 prospect_rank, expected_premium, expected_contract_month,
                 referral_source, next_action_date, lost_reason, memo, staff_id,
                 created_by, updated_by)
             VALUES
                (:customer_id, :contract_id, :case_name, :case_type, :product_type, :status,
                 :prospect_rank, :expected_premium, :expected_contract_month,
                 :referral_source, :next_action_date, :lost_reason, :memo, :staff_id,
                 :created_by, :updated_by)'
        );
        $this->bindInputValues($stmt, $input, $actorUserId);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $input, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_case SET
                customer_id             = :customer_id,
                contract_id             = :contract_id,
                case_name               = :case_name,
                case_type               = :case_type,
                product_type            = :product_type,
                status                  = :status,
                prospect_rank           = :prospect_rank,
                expected_premium        = :expected_premium,
                expected_contract_month = :expected_contract_month,
                referral_source         = :referral_source,
                next_action_date        = :next_action_date,
                lost_reason             = :lost_reason,
                memo                    = :memo,
                staff_id           = :staff_id,
                updated_by              = :updated_by
             WHERE id = :id AND is_deleted = 0'
        );
        $this->bindInputValues($stmt, $input, $actorUserId);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function softDelete(int $id, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_case SET is_deleted = 1, updated_by = :updated_by
             WHERE id = :id AND is_deleted = 0'
        );
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function bindInputValues(PDOStatement $stmt, array $input, int $actorUserId): void
    {
        $nullableInt = static function (mixed $v): ?int {
            $s = trim((string) $v);
            return ($s !== '' && ctype_digit($s) && (int) $s > 0) ? (int) $s : null;
        };
        $nullableStr = static function (mixed $v): ?string {
            $s = trim((string) $v);
            return $s !== '' ? $s : null;
        };

        $customerId  = $nullableInt($input['customer_id'] ?? null);
        $staffUserId = $nullableInt($input['staff_id'] ?? null) ?? $actorUserId;

        $stmt->bindValue(':customer_id', $customerId, $customerId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':contract_id', $nullableInt($input['contract_id'] ?? null), PDO::PARAM_INT);
        $stmt->bindValue(':case_name', trim((string) ($input['case_name'] ?? '')));
        $stmt->bindValue(':case_type', trim((string) ($input['case_type'] ?? 'new')));
        $stmt->bindValue(':product_type', $nullableStr($input['product_type'] ?? null));
        $stmt->bindValue(':status', trim((string) ($input['status'] ?? 'open')));
        $stmt->bindValue(':prospect_rank', $nullableStr($input['prospect_rank'] ?? null));

        $premiumStr = trim((string) ($input['expected_premium'] ?? ''));
        $premium    = ($premiumStr !== '' && is_numeric($premiumStr)) ? (int) $premiumStr : null;
        $stmt->bindValue(':expected_premium', $premium, $premium !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $stmt->bindValue(':expected_contract_month', $nullableStr($input['expected_contract_month'] ?? null));
        $stmt->bindValue(':referral_source', $nullableStr($input['referral_source'] ?? null));
        $stmt->bindValue(':next_action_date', $nullableStr($input['next_action_date'] ?? null));
        $stmt->bindValue(':lost_reason', $nullableStr($input['lost_reason'] ?? null));
        $stmt->bindValue(':memo', $nullableStr($input['memo'] ?? null));
        $stmt->bindValue(':staff_id', $staffUserId, PDO::PARAM_INT);
    }
}
