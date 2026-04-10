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
        'product_type',
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
                    ac.assigned_staff_id,
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

        $assignedUserId = (int) ($criteria['assigned_staff_id'] ?? 0);
        if ($assignedUserId > 0) {
            $sql .= ' AND ac.assigned_staff_id = :assigned_staff_id';
            $params['assigned_staff_id'] = $assignedUserId;
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
            'accident_no'   => 'ac.accident_no',
            'accepted_date' => 'ac.accepted_date',
            'customer_name' => 'mc.customer_name',
            'policy_no'     => 'c.policy_no',
            'product_type'  => 'ac.product_type',
            'status'        => 'ac.status',
            'priority'      => 'ac.priority',
            'resolved_date' => 'ac.resolved_date',
            default         => 'ac.accepted_date',
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
                    ac.assigned_staff_id,
                    ac.sc_staff_name,
                    ac.remark,
                    ac.updated_at,
                    mc.customer_name,
                    mc.phone,
                    mc.email,
                    mc.postal_code,
                    mc.address1,
                    mc.address2,
                    c.policy_no,
                    (SELECT rc.id
                       FROM t_renewal_case rc
                      WHERE rc.contract_id = ac.contract_id
                        AND rc.is_deleted = 0
                      ORDER BY rc.id DESC
                      LIMIT 1) AS renewal_case_id
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
        $before = $this->findAccidentCaseForAudit($id);
        if ($before === null) {
            return 0;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE t_accident_case
                 SET status = :status,
                     priority = :priority,
                     assigned_staff_id = :assigned_staff_id,
                     resolved_date = :resolved_date,
                     updated_by = :updated_by
                 WHERE id = :id
                   AND is_deleted = 0'
            );

            $stmt->execute([
                'id' => $id,
                'status' => $input['status'],
                'priority' => $input['priority'],
                'assigned_staff_id' => $input['assigned_staff_id'],
                'resolved_date' => $input['resolved_date'],
                'updated_by' => $updatedBy,
            ]);

            $affected = $stmt->rowCount();
            if ($affected === 0) {
                $this->pdo->rollBack();
                return 0;
            }

            $after = $this->findAccidentCaseForAudit($id);
            if ($after === null) {
                $this->pdo->rollBack();
                return 0;
            }

            $details = $this->buildAccidentAuditDetails($before, $after);
            $eventId = $this->insertAccidentAuditEvent($id, $updatedBy, '事故案件対応情報を更新');
            if ($details !== []) {
                $this->insertAccidentAuditEventDetails($eventId, $details);
            }

            // ステータスが closed になった場合はリマインドを自動無効化
            if ($input['status'] === 'closed') {
                $this->disableReminderOnClose($id, $updatedBy, $eventId);
            }

            $this->pdo->commit();
            return $affected;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function disableReminderOnClose(int $accidentCaseId, int $updatedBy, int $auditEventId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_accident_reminder_rule
             SET is_enabled = 0,
                 updated_by = :updated_by
             WHERE accident_case_id = :accident_case_id
               AND is_enabled = 1
               AND is_deleted = 0'
        );
        $stmt->execute([
            'accident_case_id' => $accidentCaseId,
            'updated_by'       => $updatedBy,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->insertAccidentAuditEventDetails($auditEventId, [[
                'field_key'         => 'reminder',
                'field_label'       => 'リマインド',
                'value_type'        => 'STRING',
                'before_value_text' => '有効',
                'after_value_text'  => '自動無効化（完了）',
            ]]);
        }
    }

    private function findAccidentCaseForAudit(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, priority, assigned_staff_id, resolved_date
             FROM t_accident_case
             WHERE id = :id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'status'           => $this->normalizeAuditValue($row['status'] ?? null),
            'priority'         => $this->normalizeAuditValue($row['priority'] ?? null),
            'assigned_staff_id' => $this->normalizeAuditValue($row['assigned_staff_id'] ?? null),
            'resolved_date'    => $this->normalizeAuditValue($row['resolved_date'] ?? null),
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
    private function buildAccidentAuditDetails(array $before, array $after): array
    {
        $fields = [
            'status'           => ['label' => '対応状況', 'value_type' => 'STRING'],
            'priority'         => ['label' => '優先度',   'value_type' => 'STRING'],
            'assigned_staff_id' => ['label' => '担当者',   'value_type' => 'STRING'],
            'resolved_date'    => ['label' => '解決日',   'value_type' => 'DATE'],
        ];

        $details = [];
        foreach ($fields as $fieldKey => $meta) {
            $beforeValue = $before[$fieldKey] ?? null;
            $afterValue  = $after[$fieldKey]  ?? null;
            if ($beforeValue === $afterValue) {
                continue;
            }
            $details[] = [
                'field_key'         => $fieldKey,
                'field_label'       => (string) $meta['label'],
                'value_type'        => (string) $meta['value_type'],
                'before_value_text' => $beforeValue,
                'after_value_text'  => $afterValue,
            ];
        }

        return $details;
    }

    private function insertAccidentAuditEvent(int $accidentCaseId, int $updatedBy, string $note): int
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
                "accident_case",
                :entity_id,
                "UPDATE",
                "SCREEN",
                :changed_by,
                :note
            )'
        );
        $stmt->execute([
            'entity_id'   => $accidentCaseId,
            'changed_by'  => $updatedBy,
            'note'        => $note,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<int, array<string, string|null>> $details
     */
    private function insertAccidentAuditEventDetails(int $auditEventId, array $details): void
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
                'audit_event_id'    => $auditEventId,
                'field_key'         => (string) ($detail['field_key'] ?? ''),
                'field_label'       => (string) ($detail['field_label'] ?? ''),
                'value_type'        => (string) ($detail['value_type'] ?? 'STRING'),
                'before_value_text' => $detail['before_value_text'] ?? null,
                'after_value_text'  => $detail['after_value_text'] ?? null,
            ]);
        }
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
                 assigned_staff_id,
                 sc_staff_name,
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
                 :assigned_staff_id,
                 :sc_staff_name,
                 :remark,
                 :created_by,
                 :updated_by
             )'
        );
        $stmt->bindValue(':customer_id', $input['customer_id'], PDO::PARAM_INT);
        $stmt->bindValue(':accepted_date', $input['accepted_date']);
        $stmt->bindValue(':accident_date', $input['accident_date']);
        $stmt->bindValue(':insurance_category', $input['insurance_category']);
        $stmt->bindValue(':accident_location', $input['accident_location']);
        $stmt->bindValue(':status', $input['status']);
        $stmt->bindValue(':priority', $input['priority']);
        $stmt->bindValue(':assigned_staff_id', $input['assigned_staff_id'], PDO::PARAM_INT);
        $stmt->bindValue(':sc_staff_name', $input['sc_staff_name'] ?? null);
        $stmt->bindValue(':remark', $input['remark']);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();

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

    /**
     * 案件IDリストに対して次回リマインド日（base_date + interval_weeks * 7）を取得する。
     * 複数ルールがある場合は最も近い日付を返す。
     *
     * @param int[] $caseIds
     * @return array<int, string>  [accident_case_id => 'Y-m-d']
     */
    public function findNextReminderDates(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($caseIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT r.accident_case_id,
                    MIN(DATE_ADD(r.base_date, INTERVAL r.interval_weeks * 7 DAY)) AS next_reminder_date
             FROM t_accident_reminder_rule r
             WHERE r.accident_case_id IN (' . $placeholders . ')
               AND r.is_enabled = 1
               AND r.is_deleted = 0
             GROUP BY r.accident_case_id'
        );

        foreach ($caseIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['accident_case_id'] ?? 0);
            $date = trim((string) ($row['next_reminder_date'] ?? ''));
            if ($id > 0 && $date !== '') {
                $result[$id] = $date;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null  rule + 'weekdays' => int[]
     */
    public function findReminderRuleByAccidentCaseId(int $accidentCaseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, accident_case_id, is_enabled, interval_weeks,
                    base_date, start_date, end_date, last_notified_on
             FROM t_accident_reminder_rule
             WHERE accident_case_id = :accident_case_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['accident_case_id' => $accidentCaseId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $rule = $row;
        $rule['weekdays'] = $this->findReminderWeekdays((int) ($rule['id'] ?? 0));

        return $rule;
    }

    /**
     * @return int[]
     */
    private function findReminderWeekdays(int $ruleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT weekday_cd
             FROM t_accident_reminder_rule_weekday
             WHERE accident_reminder_rule_id = :rule_id
             ORDER BY weekday_cd ASC'
        );
        $stmt->execute(['rule_id' => $ruleId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn($r) => (int) ($r['weekday_cd'] ?? 0), $rows);
    }

    /**
     * @param array<string, mixed> $input  keys: is_enabled, interval_weeks, weekdays[], start_date, end_date
     */
    public function saveReminderRule(int $accidentCaseId, array $input, int $userId): void
    {
        $existing = $this->findReminderRuleByAccidentCaseId($accidentCaseId);

        $isEnabled     = (int) ($input['is_enabled'] ?? 0);
        $intervalWeeks = max(1, (int) ($input['interval_weeks'] ?? 1));
        $startDate     = isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null;
        $endDate       = isset($input['end_date'])   && $input['end_date']   !== '' ? $input['end_date']   : null;
        $weekdays      = array_values(array_filter(
            array_map('intval', (array) ($input['weekdays'] ?? [])),
            fn($w) => $w >= 0 && $w <= 6
        ));

        $this->pdo->beginTransaction();
        try {
            if ($existing === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO t_accident_reminder_rule (
                        accident_case_id, is_enabled, interval_weeks, base_date,
                        start_date, end_date, created_by, updated_by
                     ) VALUES (
                        :accident_case_id, :is_enabled, :interval_weeks, CURDATE(),
                        :start_date, :end_date, :created_by, :updated_by
                     )'
                );
                $stmt->execute([
                    'accident_case_id' => $accidentCaseId,
                    'is_enabled'       => $isEnabled,
                    'interval_weeks'   => $intervalWeeks,
                    'start_date'       => $startDate,
                    'end_date'         => $endDate,
                    'created_by'       => $userId,
                    'updated_by'       => $userId,
                ]);
                $ruleId = (int) $this->pdo->lastInsertId();
            } else {
                $ruleId = (int) ($existing['id'] ?? 0);
                $stmt = $this->pdo->prepare(
                    'UPDATE t_accident_reminder_rule
                     SET is_enabled      = :is_enabled,
                         interval_weeks  = :interval_weeks,
                         start_date      = :start_date,
                         end_date        = :end_date,
                         updated_by      = :updated_by
                     WHERE id = :id
                       AND is_deleted = 0'
                );
                $stmt->execute([
                    'id'             => $ruleId,
                    'is_enabled'     => $isEnabled,
                    'interval_weeks' => $intervalWeeks,
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'updated_by'     => $userId,
                ]);
            }

            // 曜日: DELETE + INSERT
            $this->pdo->prepare(
                'DELETE FROM t_accident_reminder_rule_weekday WHERE accident_reminder_rule_id = :rule_id'
            )->execute(['rule_id' => $ruleId]);

            if ($weekdays !== []) {
                $wdStmt = $this->pdo->prepare(
                    'INSERT INTO t_accident_reminder_rule_weekday
                        (accident_reminder_rule_id, weekday_cd, created_by, updated_by)
                     VALUES
                        (:rule_id, :weekday_cd, :created_by, :updated_by)'
                );
                foreach ($weekdays as $wd) {
                    $wdStmt->execute([
                        'rule_id'    => $ruleId,
                        'weekday_cd' => $wd,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            // 監査ログ
            $beforeEnabled = $existing !== null ? (string) ($existing['is_enabled'] ?? '0') : null;
            $afterEnabled  = (string) $isEnabled;
            $eventId = $this->insertAccidentAuditEvent($accidentCaseId, $userId, 'リマインド設定を保存');
            $auditDetails = [];
            if ($existing === null || $beforeEnabled !== $afterEnabled) {
                $auditDetails[] = [
                    'field_key'         => 'reminder_is_enabled',
                    'field_label'       => 'リマインド有効/無効',
                    'value_type'        => 'STRING',
                    'before_value_text' => $existing === null ? null : ($beforeEnabled === '1' ? '有効' : '無効'),
                    'after_value_text'  => $afterEnabled === '1' ? '有効' : '無効',
                ];
            }
            if ($auditDetails !== []) {
                $this->insertAccidentAuditEventDetails($eventId, $auditDetails);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function softDelete(int $accidentCaseId, int $updatedBy): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_accident_case
             SET is_deleted = 1, updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->bindValue(':updated_by', $updatedBy, PDO::PARAM_INT);
        $stmt->bindValue(':id', $accidentCaseId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
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
