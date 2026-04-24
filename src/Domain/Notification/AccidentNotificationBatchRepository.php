<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use PDO;

final class AccidentNotificationBatchRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createRun(string $runDate, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_notification_run (
                notification_type,
                run_date,
                result,
                created_by
             ) VALUES (
                "accident",
                :run_date,
                "running",
                :created_by
             )'
        );
        $stmt->execute([
            'run_date' => $runDate,
            'created_by' => $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findEnabledRulesWithWeekdays(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.id AS rule_id,
                    r.accident_case_id,
                    r.interval_weeks,
                    r.base_date,
                    r.start_date,
                    r.end_date,
                    r.last_notified_on,
                          ac.accident_date,
                    ac.status AS accident_status,
                          COALESCE(mc.customer_name, ac.prospect_name, "") AS customer_name,
                    GROUP_CONCAT(w.weekday_cd ORDER BY w.weekday_cd SEPARATOR ",") AS weekdays_csv
             FROM t_accident_reminder_rule r
             INNER JOIN t_accident_case ac
                     ON ac.id = r.accident_case_id
                    AND ac.is_deleted = 0
             LEFT JOIN m_customer mc
                    ON mc.id = ac.customer_id
                   AND mc.is_deleted = 0
             LEFT JOIN t_accident_reminder_rule_weekday w
                    ON w.accident_reminder_rule_id = r.id
             WHERE r.is_enabled = 1
               AND r.is_deleted = 0
               AND ac.status IN ("受付", "対応中", "書類待ち", "保険会社連絡済み")
                      GROUP BY r.id, r.accident_case_id, r.interval_weeks, r.base_date, r.start_date, r.end_date, r.last_notified_on, ac.accident_date, ac.status, mc.customer_name, ac.prospect_name
             ORDER BY r.id ASC'
        );
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findFailedDeliveriesByRunId(int $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id AS delivery_id,
                    d.accident_case_id,
                    d.accident_reminder_rule_id,
                    d.scheduled_date,
                    d.notified_at,
                    d.created_at,
                        d.error_message,
                        ac.accident_date,
                        c.customer_name
             FROM t_notification_delivery d
             INNER JOIN t_notification_run r
                     ON r.id = d.notification_run_id
                     INNER JOIN t_accident_case ac
                         ON ac.id = d.accident_case_id
                        AND ac.is_deleted = 0
                     INNER JOIN t_contract ct
                         ON ct.id = ac.contract_id
                        AND ct.is_deleted = 0
                     INNER JOIN m_customer c
                         ON c.id = ct.customer_id
                        AND c.is_deleted = 0
             WHERE r.id = :run_id
               AND r.notification_type = "accident"
               AND d.notification_type = "accident"
               AND d.delivery_status = "failed"
             ORDER BY d.id ASC'
        );
        $stmt->execute(['run_id' => $runId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function insertDeliverySuccess(int $runId, int $accidentCaseId, int $ruleId, string $runDate): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_notification_delivery (
                notification_run_id,
                notification_type,
                renewal_case_id,
                accident_case_id,
                renewal_reminder_phase_id,
                accident_reminder_rule_id,
                scheduled_date,
                notified_at,
                delivery_status,
                error_message
             ) VALUES (
                :run_id,
                "accident",
                NULL,
                :accident_case_id,
                NULL,
                :rule_id,
                :scheduled_date,
                NOW(),
                "success",
                NULL
             )
             ON DUPLICATE KEY UPDATE
                notification_run_id = VALUES(notification_run_id),
                notified_at         = NOW(),
                delivery_status     = "success",
                error_message       = NULL'
        );
        $stmt->execute([
            'run_id' => $runId,
            'accident_case_id' => $accidentCaseId,
            'rule_id' => $ruleId,
            'scheduled_date' => $runDate,
        ]);

        return true;
    }

    public function hasDeliveryForSchedule(int $accidentCaseId, int $ruleId, string $runDate): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM t_notification_delivery
             WHERE notification_type = "accident"
               AND accident_case_id = :accident_case_id
               AND accident_reminder_rule_id = :rule_id
               AND scheduled_date = :scheduled_date
             LIMIT 1'
        );
        $stmt->execute([
            'accident_case_id' => $accidentCaseId,
            'rule_id' => $ruleId,
            'scheduled_date' => $runDate,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function insertDeliverySkipped(int $runId, int $accidentCaseId, int $ruleId, string $runDate, string $message): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO t_notification_delivery (
                notification_run_id,
                notification_type,
                renewal_case_id,
                accident_case_id,
                renewal_reminder_phase_id,
                accident_reminder_rule_id,
                scheduled_date,
                notified_at,
                delivery_status,
                error_message
             ) VALUES (
                :run_id,
                "accident",
                NULL,
                :accident_case_id,
                NULL,
                :rule_id,
                :scheduled_date,
                NULL,
                "skipped",
                :error_message
             )'
        );
        $stmt->execute([
            'run_id' => $runId,
            'accident_case_id' => $accidentCaseId,
            'rule_id' => $ruleId,
            'scheduled_date' => $runDate,
            'error_message' => mb_substr($message, 0, 1000),
        ]);

        return $stmt->rowCount() === 1;
    }

    public function insertDeliveryFailed(int $runId, int $accidentCaseId, int $ruleId, string $runDate, string $message): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO t_notification_delivery (
                notification_run_id,
                notification_type,
                renewal_case_id,
                accident_case_id,
                renewal_reminder_phase_id,
                accident_reminder_rule_id,
                scheduled_date,
                notified_at,
                delivery_status,
                error_message
             ) VALUES (
                :run_id,
                "accident",
                NULL,
                :accident_case_id,
                NULL,
                :rule_id,
                :scheduled_date,
                     NOW(),
                "failed",
                :error_message
             )'
        );
        $stmt->execute([
            'run_id' => $runId,
            'accident_case_id' => $accidentCaseId,
            'rule_id' => $ruleId,
            'scheduled_date' => $runDate,
            'error_message' => mb_substr($message, 0, 1000),
        ]);

        return $stmt->rowCount() === 1;
    }

    public function updateDeliveryForRetry(
        int $deliveryId,
        int $runId,
        string $deliveryStatus,
        ?string $errorMessage,
        bool $markNotified
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE t_notification_delivery
             SET notification_run_id = :run_id,
                 delivery_status = :delivery_status,
                 notified_at = :notified_at,
                 error_message = :error_message
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $deliveryId,
            'run_id' => $runId,
            'delivery_status' => $deliveryStatus,
            'notified_at' => $markNotified ? date('Y-m-d H:i:s') : null,
            'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 1000) : null,
        ]);
    }

    public function updateRuleLastNotifiedOn(int $ruleId, string $runDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_accident_reminder_rule
             SET last_notified_on = :run_date
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $ruleId,
            'run_date' => $runDate,
        ]);
    }

    public function finalizeRun(
        int $runId,
        string $result,
        int $processedCount,
        int $successCount,
        int $skipCount,
        int $failCount,
        ?string $errorMessage
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE t_notification_run
             SET finished_at = NOW(),
                 result = :result,
                 processed_count = :processed_count,
                 success_count = :success_count,
                 skip_count = :skip_count,
                 fail_count = :fail_count,
                 error_message = :error_message
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $runId,
            'result' => $result,
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'fail_count' => $failCount,
            'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 1000) : null,
        ]);
    }
}
