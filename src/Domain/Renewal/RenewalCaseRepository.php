<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use PDO;

final class RenewalCaseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 100): array
    {
        $sql =
            'SELECT rc.id AS renewal_case_id,
                    c.id AS contract_id,
                    mc.customer_name,
                    c.policy_no,
                    c.insurer_name,
                    c.product_type,
                    rc.maturity_date,
                    rc.case_status,
                    rc.next_action_date,
                    rc.updated_at
             FROM t_renewal_case rc
             INNER JOIN t_contract c ON c.id = rc.contract_id AND c.is_deleted = 0
             INNER JOIN m_customer mc ON mc.id = c.customer_id AND mc.is_deleted = 0
             WHERE rc.is_deleted = 0';

        $params = [];

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

        $status = trim((string) ($criteria['case_status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND rc.case_status = :case_status';
            $params['case_status'] = $status;
        }

        $maturityDateFrom = trim((string) ($criteria['maturity_date_from'] ?? ''));
        if ($maturityDateFrom !== '') {
            $sql .= ' AND rc.maturity_date >= :maturity_date_from';
            $params['maturity_date_from'] = $maturityDateFrom;
        }

        $maturityDateTo = trim((string) ($criteria['maturity_date_to'] ?? ''));
        if ($maturityDateTo !== '') {
            $sql .= ' AND rc.maturity_date <= :maturity_date_to';
            $params['maturity_date_to'] = $maturityDateTo;
        }

        $sql .= ' ORDER BY rc.maturity_date ASC, rc.id ASC LIMIT :limit';

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
    public function findDetailById(int $renewalCaseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rc.id AS renewal_case_id,
                    rc.contract_id,
                    rc.maturity_date,
                    rc.case_status,
                    rc.next_action_date,
                    rc.renewal_result,
                    rc.lost_reason,
                    rc.remark,
                    c.policy_no,
                    c.insurer_name,
                    c.product_type,
                    c.policy_start_date,
                    c.policy_end_date,
                    c.premium_amount,
                    c.payment_cycle,
                    c.status AS contract_status,
                    c.remark AS contract_remark,
                    mc.id AS customer_id,
                    mc.customer_name,
                    mc.phone,
                    mc.email,
                    mc.address1,
                    mc.address2,
                    rc.updated_at
             FROM t_renewal_case rc
             INNER JOIN t_contract c ON c.id = rc.contract_id AND c.is_deleted = 0
             INNER JOIN m_customer mc ON mc.id = c.customer_id AND mc.is_deleted = 0
             WHERE rc.id = :renewal_case_id
               AND rc.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['renewal_case_id' => $renewalCaseId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActivities(int $renewalCaseId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT activity_at, activity_type, subject, detail, outcome
             FROM t_activity
             WHERE renewal_case_id = :renewal_case_id
               AND is_deleted = 0
             ORDER BY activity_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':renewal_case_id', $renewalCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findComments(int $renewalCaseId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT created_at, comment_body
             FROM t_case_comment
             WHERE target_type = "renewal_case"
               AND renewal_case_id = :renewal_case_id
               AND is_deleted = 0
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':renewal_case_id', $renewalCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAuditEvents(int $renewalCaseId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT changed_at, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "renewal_case"
               AND entity_id = :renewal_case_id
             ORDER BY changed_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':renewal_case_id', $renewalCaseId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, string> $input
     */
    public function updateRenewalCase(int $renewalCaseId, array $input, int $updatedBy): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_renewal_case
             SET case_status = :case_status,
                 next_action_date = :next_action_date,
                 renewal_result = :renewal_result,
                 lost_reason = :lost_reason,
                 remark = :remark,
                 updated_by = :updated_by
             WHERE id = :renewal_case_id
               AND is_deleted = 0'
        );

        $nextActionDate = trim((string) ($input['next_action_date'] ?? ''));
        $renewalResult = trim((string) ($input['renewal_result'] ?? ''));
        $lostReason = trim((string) ($input['lost_reason'] ?? ''));
        $remark = trim((string) ($input['remark'] ?? ''));

        $stmt->bindValue(':case_status', (string) ($input['case_status'] ?? 'open'));
        $stmt->bindValue(':next_action_date', $nextActionDate !== '' ? $nextActionDate : null);
        $stmt->bindValue(':renewal_result', $renewalResult !== '' ? $renewalResult : null);
        $stmt->bindValue(':lost_reason', $lostReason !== '' ? $lostReason : null);
        $stmt->bindValue(':remark', $remark !== '' ? $remark : null);
        $stmt->bindValue(':updated_by', $updatedBy, PDO::PARAM_INT);
        $stmt->bindValue(':renewal_case_id', $renewalCaseId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }
}