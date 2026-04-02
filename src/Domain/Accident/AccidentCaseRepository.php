<?php
declare(strict_types=1);

namespace App\Domain\Accident;

use PDO;

final class AccidentCaseRepository
{
    public const SORTABLE_FIELDS = [
        'accident_no',
        'accepted_date',
        'customer_name',
        'policy_no',
        'status',
        'priority',
        'resolved_date',
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
            'SELECT ac.id,
                    ac.accident_no,
                    ac.accepted_date,
                    ac.accident_date,
                    ac.product_type,
                    ac.status,
                    ac.priority,
                    ac.resolved_date,
                    ac.updated_at,
                    ac.assigned_user_id,
                    mc.customer_name,
                    c.policy_no
             FROM t_accident_case ac
             INNER JOIN m_customer mc
                     ON mc.id = ac.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = ac.contract_id
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
        $sql = ' WHERE ac.is_deleted = 0';

        $acceptedFrom = trim((string) ($criteria['accepted_date_from'] ?? ''));
        if ($acceptedFrom !== '') {
            $sql .= ' AND ac.accepted_date >= :accepted_date_from';
            $params['accepted_date_from'] = $acceptedFrom;
        }

        $acceptedTo = trim((string) ($criteria['accepted_date_to'] ?? ''));
        if ($acceptedTo !== '') {
            $sql .= ' AND ac.accepted_date <= :accepted_date_to';
            $params['accepted_date_to'] = $acceptedTo;
        }

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $policyNo = trim((string) ($criteria['policy_no'] ?? ''));
        if ($policyNo !== '') {
            $sql .= ' AND c.policy_no LIKE :policy_no';
            $params['policy_no'] = '%' . $policyNo . '%';
        }

        $productType = trim((string) ($criteria['product_type'] ?? ''));
        if ($productType !== '') {
            $sql .= ' AND ac.product_type LIKE :product_type';
            $params['product_type'] = '%' . $productType . '%';
        }

        $status = trim((string) ($criteria['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND ac.status = :status';
            $params['status'] = $status;
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
             FROM t_accident_case ac
             INNER JOIN m_customer mc
                     ON mc.id = ac.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = ac.contract_id
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
            'accident_no' => 'ac.accident_no',
            'accepted_date' => 'ac.accepted_date',
            'customer_name' => 'mc.customer_name',
            'policy_no' => 'c.policy_no',
            'status' => 'ac.status',
            'priority' => 'ac.priority',
            'resolved_date' => 'ac.resolved_date',
            default => 'ac.accepted_date',
        };

        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $fallbackDir = $dir === 'DESC' ? 'DESC' : 'ASC';

        return ' ORDER BY ' . $column . ' ' . $dir . ', ac.id ' . $fallbackDir;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetailById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ac.id,
                    ac.customer_id,
                    ac.contract_id,
                    ac.accident_no,
                    ac.accepted_date,
                    ac.accident_date,
                    ac.insurance_category,
                    ac.product_type,
                    ac.accident_type,
                    ac.accident_summary,
                    ac.accident_location,
                    ac.has_counterparty,
                    ac.status,
                    ac.priority,
                    ac.insurer_claim_no,
                    ac.resolved_date,
                    ac.assigned_user_id,
                    ac.remark,
                    ac.updated_at,
                    mc.customer_name,
                    mc.phone,
                    mc.email,
                    c.policy_no
             FROM t_accident_case ac
             INNER JOIN m_customer mc
                     ON mc.id = ac.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = ac.contract_id
                   AND c.is_deleted = 0
             WHERE ac.id = :id
               AND ac.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findComments(int $accidentCaseId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at, created_by, comment_body
             FROM t_case_comment
             WHERE target_type = "accident_case"
               AND accident_case_id = :accident_case_id
               AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':accident_case_id', $accidentCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAuditEvents(int $accidentCaseId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, changed_at, changed_by, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "accident_case"
               AND entity_id = :entity_id
             ORDER BY changed_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':entity_id', $accidentCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll();
        if (!is_array($events) || $events === []) {
            return [];
        }

        $eventIds = [];
        foreach ($events as $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = (int) ($row['id'] ?? 0);
            if ($eventId > 0) {
                $eventIds[] = $eventId;
            }
        }

        $detailsByEventId = $this->findAuditEventDetails($eventIds);

        foreach ($events as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = (int) ($row['id'] ?? 0);
            $events[$index]['details'] = $detailsByEventId[$eventId] ?? [];
        }

        return $events;
    }

    /**
     * @param int[] $eventIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function findAuditEventDetails(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT audit_event_id, field_key, field_label, value_type,
                    before_value_text, after_value_text,
                    before_value_json, after_value_json
             FROM t_audit_event_detail
             WHERE audit_event_id IN (' . $placeholders . ')
             ORDER BY id ASC'
        );

        foreach ($eventIds as $index => $eventId) {
            $stmt->bindValue($index + 1, $eventId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $auditEventId = (int) ($row['audit_event_id'] ?? 0);
            if ($auditEventId <= 0) {
                continue;
            }
            $grouped[$auditEventId][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateAccidentCase(int $id, array $input, int $updatedBy): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_accident_case
             SET status = :status,
                 priority = :priority,
                 assigned_user_id = :assigned_user_id,
                 resolved_date = :resolved_date,
                 insurer_claim_no = :insurer_claim_no,
                 remark = :remark,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );

        $stmt->execute([
            'id' => $id,
            'status' => $input['status'],
            'priority' => $input['priority'],
            'assigned_user_id' => $input['assigned_user_id'],
            'resolved_date' => $input['resolved_date'],
            'insurer_claim_no' => $input['insurer_claim_no'],
            'remark' => $input['remark'],
            'updated_by' => $updatedBy,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createAccidentCase(array $input, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_accident_case (
                 customer_id,
                 accepted_date,
                 accident_date,
                 insurance_category,
                 accident_location,
                 status,
                 priority,
                 assigned_user_id,
                 remark,
                 created_by,
                 updated_by
             ) VALUES (
                 :customer_id,
                 :accepted_date,
                 :accident_date,
                 :insurance_category,
                 :accident_location,
                 :status,
                 :priority,
                 :assigned_user_id,
                 :remark,
                 :created_by,
                 :updated_by
             )'
        );
        $stmt->execute([
            'customer_id' => $input['customer_id'],
            'accepted_date' => $input['accepted_date'],
            'accident_date' => $input['accident_date'],
            'insurance_category' => $input['insurance_category'],
            'accident_location' => $input['accident_location'],
            'status' => $input['status'],
            'priority' => $input['priority'],
            'assigned_user_id' => $input['assigned_user_id'],
            'remark' => $input['remark'],
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
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

    public function createComment(int $accidentCaseId, string $commentBody, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_case_comment (
                target_type,
                renewal_case_id,
                accident_case_id,
                comment_body,
                created_by,
                updated_by
             ) VALUES (
                "accident_case",
                NULL,
                :accident_case_id,
                :comment_body,
                :created_by,
                :updated_by
             )'
        );
        $stmt->execute([
            'accident_case_id' => $accidentCaseId,
            'comment_body' => $commentBody,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
