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

    /** @var array<int, string> */
    public const ALLOWED_PROSPECT_RANKS = ['A', 'B', 'C'];

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        'id', 'case_name', 'customer_name', 'case_type', 'product_type',
        'status', 'prospect_rank', 'expected_contract_month', 'expected_premium', 'created_at',
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
            $where[] = '(mc.customer_name LIKE :customer_name OR sc.prospect_name LIKE :customer_name)';
            $params[':customer_name'] = '%' . $customerName . '%';
        }

        $staffUserId = trim((string) ($criteria['staff_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId) && (int) $staffUserId > 0) {
            $where[] = 'sc.staff_id = :staff_id';
            $params[':staff_id'] = (int) $staffUserId;
        }

        $status = trim((string) ($criteria['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'sc.status = :status';
            $params[':status'] = $status;
        }

        $prospectRank = trim((string) ($criteria['prospect_rank'] ?? ''));
        if ($prospectRank !== '' && in_array($prospectRank, self::ALLOWED_PROSPECT_RANKS, true)) {
            $where[] = 'sc.prospect_rank = :prospect_rank';
            $params[':prospect_rank'] = $prospectRank;
        }

        $caseName = trim((string) ($criteria['case_name'] ?? ''));
        if ($caseName !== '') {
            $where[] = 'sc.case_name LIKE :case_name';
            $params[':case_name'] = '%' . $caseName . '%';
        }

        // クイックフィルタタブ (high_open / open / mine / completed / all)
        $quickFilter = trim((string) ($criteria['quick_filter'] ?? ''));
        $loginStaffId = (int) ($criteria['_login_staff_id'] ?? 0);
        if ($quickFilter === 'high_open') {
            $where[] = "sc.prospect_rank = 'A'";
            $where[] = "sc.status NOT IN ('成約','失注')";
        } elseif ($quickFilter === 'open') {
            $where[] = "sc.status NOT IN ('成約','失注')";
        } elseif ($quickFilter === 'mine' && $loginStaffId > 0) {
            $where[] = 'sc.staff_id = :qf_login_staff_id';
            $params[':qf_login_staff_id'] = $loginStaffId;
        } elseif ($quickFilter === 'completed') {
            $where[] = "sc.status = '成約'";
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

        $columnMap = [
            'id'                      => 'sc.id',
            'case_name'               => 'sc.case_name',
            'customer_name'           => 'mc.customer_name',
            'case_type'               => 'sc.case_type',
            'product_type'            => 'sc.product_type',
            'status'                  => 'sc.status',
            'prospect_rank'           => 'sc.prospect_rank',
            'expected_contract_month' => 'sc.expected_contract_month',
            'expected_premium'        => 'sc.expected_premium',
            'created_at'              => 'sc.created_at',
        ];
        $orderColumn  = $columnMap[$sort] ?? 'sc.id';
        $dir          = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $perPage = max(1, min(200, $perPage));
        $maxPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page    = max(1, min($maxPage, $page));
        $offset  = ($page - 1) * $perPage;

        $dataSql = "SELECT sc.id, sc.customer_id, sc.prospect_name, sc.case_name, sc.case_type, sc.product_type,
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
     * クイックフィルタタブの件数を一括取得。
     * quick_filter 以外の criteria は AND 結合で各件数に反映される。
     *
     * @param array<string, string> $criteria
     * @return array{all:int,high_open:int,open:int,mine:int,completed:int}
     */
    public function countByQuickFilters(array $criteria, int $loginStaffId): array
    {
        $base = $criteria;
        unset($base['quick_filter'], $base['_login_staff_id']);

        $where  = ['sc.is_deleted = 0'];
        $params = [];

        $customerName = trim((string) ($base['customer_name'] ?? ''));
        if ($customerName !== '') {
            $where[] = '(mc.customer_name LIKE :customer_name OR sc.prospect_name LIKE :customer_name)';
            $params[':customer_name'] = '%' . $customerName . '%';
        }
        $staffUserId = trim((string) ($base['staff_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId) && (int) $staffUserId > 0) {
            $where[] = 'sc.staff_id = :staff_id';
            $params[':staff_id'] = (int) $staffUserId;
        }
        $status = trim((string) ($base['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'sc.status = :status';
            $params[':status'] = $status;
        }
        $prospectRank = trim((string) ($base['prospect_rank'] ?? ''));
        if ($prospectRank !== '' && in_array($prospectRank, self::ALLOWED_PROSPECT_RANKS, true)) {
            $where[] = 'sc.prospect_rank = :prospect_rank';
            $params[':prospect_rank'] = $prospectRank;
        }
        $caseName = trim((string) ($base['case_name'] ?? ''));
        if ($caseName !== '') {
            $where[] = 'sc.case_name LIKE :case_name';
            $params[':case_name'] = '%' . $caseName . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT
            COUNT(*) AS all_cnt,
            SUM(CASE WHEN sc.prospect_rank = 'A' AND sc.status NOT IN ('成約','失注') THEN 1 ELSE 0 END) AS high_open_cnt,
            SUM(CASE WHEN sc.status NOT IN ('成約','失注') THEN 1 ELSE 0 END) AS open_cnt,
            SUM(CASE WHEN sc.staff_id = :qf_login_staff_id THEN 1 ELSE 0 END) AS mine_cnt,
            SUM(CASE WHEN sc.status = '成約' THEN 1 ELSE 0 END) AS completed_cnt
         FROM t_sales_case sc
         LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
         $whereClause";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':qf_login_staff_id', $loginStaffId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row = is_array($row) ? $row : [];

        return [
            'all'       => (int) ($row['all_cnt'] ?? 0),
            'high_open' => (int) ($row['high_open_cnt'] ?? 0),
            'open'      => (int) ($row['open_cnt'] ?? 0),
            'mine'      => (int) ($row['mine_cnt'] ?? 0),
            'completed' => (int) ($row['completed_cnt'] ?? 0),
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
     * For use in activity form dropdown. Excludes statuses marked is_completed=1.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchForDropdown(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sc.id, sc.case_name, sc.customer_id,
                    mc.customer_name,
                    sc.prospect_name,
                    COALESCE(mc.customer_name, sc.prospect_name, '') AS display_customer
             FROM t_sales_case sc
             LEFT JOIN m_customer mc ON mc.id = sc.customer_id AND mc.is_deleted = 0
             WHERE sc.is_deleted = 0
               AND sc.status NOT IN (SELECT name FROM m_sales_case_status WHERE is_completed = 1)
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
                (customer_id, prospect_name, contract_id, case_name, case_type, product_type, status,
                 prospect_rank, expected_premium, expected_contract_month,
                 next_action_date, lost_reason, memo, staff_id,
                 created_by, updated_by)
             VALUES
                (:customer_id, :prospect_name, :contract_id, :case_name, :case_type, :product_type, :status,
                 :prospect_rank, :expected_premium, :expected_contract_month,
                 :next_action_date, :lost_reason, :memo, :staff_id,
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
                prospect_name           = :prospect_name,
                contract_id             = :contract_id,
                case_name               = :case_name,
                case_type               = :case_type,
                product_type            = :product_type,
                status                  = :status,
                prospect_rank           = :prospect_rank,
                expected_premium        = :expected_premium,
                expected_contract_month = :expected_contract_month,
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
        $stmt->bindValue(':prospect_name', $nullableStr($input['prospect_name'] ?? null));
        $stmt->bindValue(':contract_id', $nullableInt($input['contract_id'] ?? null), PDO::PARAM_INT);
        $stmt->bindValue(':case_name', trim((string) ($input['case_name'] ?? '')));
        $stmt->bindValue(':case_type', trim((string) ($input['case_type'] ?? 'new')));
        $stmt->bindValue(':product_type', $nullableStr($input['product_type'] ?? null));
        $stmt->bindValue(':status', trim((string) ($input['status'] ?? '')));
        $stmt->bindValue(':prospect_rank', $nullableStr($input['prospect_rank'] ?? null));

        $premiumStr = trim((string) ($input['expected_premium'] ?? ''));
        $premium    = ($premiumStr !== '' && is_numeric($premiumStr)) ? (int) $premiumStr : null;
        $stmt->bindValue(':expected_premium', $premium, $premium !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $stmt->bindValue(':expected_contract_month', $nullableStr($input['expected_contract_month'] ?? null));
        $stmt->bindValue(':next_action_date', $nullableStr($input['next_action_date'] ?? null));
        $stmt->bindValue(':lost_reason', $nullableStr($input['lost_reason'] ?? null));
        $stmt->bindValue(':memo', $nullableStr($input['memo'] ?? null));
        $stmt->bindValue(':staff_id', $staffUserId, PDO::PARAM_INT);
    }
}
