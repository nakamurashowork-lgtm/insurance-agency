<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use PDO;

final class RenewalCaseRepository
{
    /**
     * @var array<int, string>
     */
    public const SORTABLE_FIELDS = ['customer_name', 'policy_no', 'maturity_date', 'case_status', 'next_action_date'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 100): array
    {
        $result = $this->searchPage($criteria, 1, $limit);
        return $result['rows'];
    }

    /**
     * @param array<string, string> $criteria
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int}
     */
    public function searchPage(array $criteria, int $page = 1, int $perPage = 10, string $sort = '', string $direction = 'asc'): array
    {
        $total = $this->countSearch($criteria);
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $currentPage = 1;
        if ($total > 0) {
            $totalPages = (int) ceil($total / $perPage);
            $currentPage = max(1, min($page, $totalPages));
        }

        $offset = $total > 0 ? ($currentPage - 1) * $perPage : 0;
        $query = $this->buildSearchQuery($criteria);

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
                    rc.updated_at'
            . $query['sql']
            . ' ORDER BY ' . $this->buildOrderBy($sort, $direction)
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($query['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $currentPage,
        ];
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
                    mc.assigned_user_id,
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
    public function findComments(int $renewalCaseId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
                        'SELECT id, created_at, created_by, comment_body
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
            'SELECT id, changed_at, changed_by, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "renewal_case"
               AND entity_id = :renewal_case_id
             ORDER BY changed_at DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':renewal_case_id', $renewalCaseId, PDO::PARAM_INT);
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
     * @param array<string, string> $input
     */
    public function updateRenewalCase(int $renewalCaseId, array $input, int $updatedBy): bool
    {
        $before = $this->findRenewalCaseForAudit($renewalCaseId);
        if ($before === null) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
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

            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $after = $this->findRenewalCaseForAudit($renewalCaseId);
            if ($after === null) {
                $this->pdo->rollBack();
                return false;
            }

            $details = $this->buildAuditDetails($before, $after);
            $eventId = $this->insertAuditEvent($renewalCaseId, $updatedBy, '満期対応情報を更新');
            if ($details !== []) {
                $this->insertAuditEventDetails($eventId, $details);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<int, int> $eventIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function findAuditEventDetails(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT audit_event_id, field_key, field_label, value_type, before_value_text, after_value_text
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
     * @return array<string, string|null>|null
     */
    private function findRenewalCaseForAudit(int $renewalCaseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT case_status, next_action_date, renewal_result, lost_reason, remark
             FROM t_renewal_case
             WHERE id = :renewal_case_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['renewal_case_id' => $renewalCaseId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'case_status' => $this->normalizeAuditValue($row['case_status'] ?? null),
            'next_action_date' => $this->normalizeAuditValue($row['next_action_date'] ?? null),
            'renewal_result' => $this->normalizeAuditValue($row['renewal_result'] ?? null),
            'lost_reason' => $this->normalizeAuditValue($row['lost_reason'] ?? null),
            'remark' => $this->normalizeAuditValue($row['remark'] ?? null),
        ];
    }

    private function normalizeAuditValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     * @return array<int, array<string, string|null>>
     */
    private function buildAuditDetails(array $before, array $after): array
    {
        $fields = [
            'case_status' => ['label' => '対応ステータス', 'value_type' => 'STRING'],
            'next_action_date' => ['label' => '次回対応予定日', 'value_type' => 'DATE'],
            'renewal_result' => ['label' => '更改結果', 'value_type' => 'STRING'],
            'lost_reason' => ['label' => '失注理由', 'value_type' => 'STRING'],
            'remark' => ['label' => '備考', 'value_type' => 'STRING'],
        ];

        $details = [];
        foreach ($fields as $fieldKey => $meta) {
            $beforeValue = $before[$fieldKey] ?? null;
            $afterValue = $after[$fieldKey] ?? null;
            if ($beforeValue === $afterValue) {
                continue;
            }

            $details[] = [
                'field_key' => $fieldKey,
                'field_label' => (string) $meta['label'],
                'value_type' => (string) $meta['value_type'],
                'before_value_text' => $beforeValue,
                'after_value_text' => $afterValue,
            ];
        }

        return $details;
    }

    private function insertAuditEvent(int $renewalCaseId, int $updatedBy, string $note): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event (
                entity_type,
                entity_id,
                action_type,
                change_source,
                changed_by,
                note
            ) VALUES (
                "renewal_case",
                :entity_id,
                "UPDATE",
                "SCREEN",
                :changed_by,
                :note
            )'
        );
        $stmt->execute([
            'entity_id' => $renewalCaseId,
            'changed_by' => $updatedBy,
            'note' => $note,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<int, array<string, string|null>> $details
     */
    private function insertAuditEventDetails(int $auditEventId, array $details): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event_detail (
                audit_event_id,
                field_key,
                field_label,
                value_type,
                before_value_text,
                after_value_text
             ) VALUES (
                :audit_event_id,
                :field_key,
                :field_label,
                :value_type,
                :before_value_text,
                :after_value_text
             )'
        );

        foreach ($details as $detail) {
            $stmt->execute([
                'audit_event_id' => $auditEventId,
                'field_key' => (string) ($detail['field_key'] ?? ''),
                'field_label' => (string) ($detail['field_label'] ?? ''),
                'value_type' => (string) ($detail['value_type'] ?? 'STRING'),
                'before_value_text' => $detail['before_value_text'] ?? null,
                'after_value_text' => $detail['after_value_text'] ?? null,
            ]);
        }
    }

    public function createComment(int $renewalCaseId, string $commentBody, int $userId): void
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
                "renewal_case",
                :renewal_case_id,
                NULL,
                :comment_body,
                :created_by,
                :updated_by
             )'
        );
        $stmt->execute([
            'renewal_case_id' => $renewalCaseId,
            'comment_body' => $commentBody,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param array<string, string> $criteria
     * @return array{sql: string, params: array<string, string>}
     */
    private function buildSearchQuery(array $criteria): array
    {
        $sql =
            ' FROM t_renewal_case rc
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

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @param array<string, string> $criteria
     */
    private function countSearch(array $criteria): int
    {
        $query = $this->buildSearchQuery($criteria);
        $stmt = $this->pdo->prepare('SELECT COUNT(*)' . $query['sql']);
        foreach ($query['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $count = $stmt->fetchColumn();
        return is_numeric($count) ? (int) $count : 0;
    }

    private function buildOrderBy(string $sort, string $direction): string
    {
        $directionSql = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $statusPriority = $this->statusPriorityExpression();
        $nextActionNulls = 'CASE WHEN rc.next_action_date IS NULL THEN 1 ELSE 0 END';

        return match ($sort) {
            'customer_name' => 'mc.customer_name ' . $directionSql . ', rc.maturity_date ASC, rc.id ASC',
            'policy_no' => 'c.policy_no ' . $directionSql . ', rc.maturity_date ASC, rc.id ASC',
            'maturity_date' => 'rc.maturity_date ' . $directionSql . ', rc.id ASC',
            'case_status' => $statusPriority . ' ' . $directionSql . ', rc.maturity_date ASC, rc.id ASC',
            'next_action_date' => $nextActionNulls . ' ASC, rc.next_action_date ' . $directionSql . ', rc.maturity_date ASC, rc.id ASC',
            default => $this->defaultOrderBy(),
        };
    }

    private function defaultOrderBy(): string
    {
        $completedPriority = 'CASE WHEN rc.case_status = "renewed" THEN 1 ELSE 0 END';
        $overduePriority = 'CASE WHEN rc.case_status <> "renewed" AND rc.next_action_date IS NOT NULL AND rc.next_action_date < CURDATE() THEN 0 ELSE 1 END';
        $nextActionNulls = 'CASE WHEN rc.next_action_date IS NULL THEN 1 ELSE 0 END';

        return $completedPriority . ' ASC, '
            . $overduePriority . ' ASC, '
            . $this->statusPriorityExpression() . ' ASC, '
            . 'rc.maturity_date ASC, '
            . $nextActionNulls . ' ASC, '
            . 'rc.next_action_date ASC, '
            . 'rc.id ASC';
    }

    private function statusPriorityExpression(): string
    {
        return 'CASE rc.case_status '
            . 'WHEN "open" THEN 1 '
            . 'WHEN "contacted" THEN 2 '
            . 'WHEN "quoted" THEN 3 '
            . 'WHEN "waiting" THEN 4 '
            . 'WHEN "lost" THEN 5 '
            . 'WHEN "closed" THEN 6 '
            . 'WHEN "renewed" THEN 7 '
            . 'ELSE 8 END';
    }
}