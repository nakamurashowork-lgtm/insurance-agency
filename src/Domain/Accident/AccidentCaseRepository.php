<?php
declare(strict_types=1);

namespace App\Domain\Accident;

use PDO;

final class AccidentCaseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 200): array
    {
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
                    mc.customer_name,
                    c.policy_no
             FROM t_accident_case ac
             INNER JOIN m_customer mc
                     ON mc.id = ac.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = ac.contract_id
                   AND c.is_deleted = 0
             WHERE ac.is_deleted = 0';

        $params = [];

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

        $sql .= ' ORDER BY ac.accepted_date DESC, ac.id DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
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
            'SELECT id, created_at, comment_body
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
            'SELECT changed_at, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "accident_case"
               AND entity_id = :entity_id
             ORDER BY changed_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':entity_id', $accidentCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
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
