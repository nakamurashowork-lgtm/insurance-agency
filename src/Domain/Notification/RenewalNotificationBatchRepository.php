<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use PDO;

final class RenewalNotificationBatchRepository
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
                "renewal",
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
    public function findEnabledPhases(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, phase_code, phase_name, from_days_before, to_days_before
             FROM m_renewal_reminder_phase
             WHERE is_enabled = 1
               AND is_deleted = 0
             ORDER BY display_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRenewalTargetsByPhase(string $runDate, int $fromDaysBefore, int $toDaysBefore): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rc.id AS renewal_case_id,
                    rc.contract_id,
                    rc.maturity_date,
                    rc.case_status,
                    DATEDIFF(rc.maturity_date, :run_date) AS days_before
             FROM t_renewal_case rc
             WHERE rc.is_deleted = 0
               AND rc.case_status IN ("open", "contacted", "quoted", "waiting")
               AND DATEDIFF(rc.maturity_date, :run_date) BETWEEN :to_days_before AND :from_days_before
             ORDER BY rc.maturity_date ASC, rc.id ASC'
        );
        $stmt->bindValue(':run_date', $runDate);
        $stmt->bindValue(':to_days_before', $toDaysBefore, PDO::PARAM_INT);
        $stmt->bindValue(':from_days_before', $fromDaysBefore, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns true when inserted, false when idempotent duplicate.
     */
    public function insertDeliverySuccess(int $runId, int $renewalCaseId, int $phaseId, string $runDate): bool
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
                "renewal",
                :renewal_case_id,
                NULL,
                :phase_id,
                NULL,
                :scheduled_date,
                NOW(),
                "success",
                NULL
             )'
        );
        $stmt->execute([
            'run_id' => $runId,
            'renewal_case_id' => $renewalCaseId,
            'phase_id' => $phaseId,
            'scheduled_date' => $runDate,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Returns true when inserted, false when idempotent duplicate.
     */
    public function insertDeliverySkipped(int $runId, int $renewalCaseId, int $phaseId, string $runDate, string $message): bool
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
                "renewal",
                :renewal_case_id,
                NULL,
                :phase_id,
                NULL,
                :scheduled_date,
                NULL,
                "skipped",
                :error_message
             )'
        );
        $stmt->execute([
            'run_id' => $runId,
            'renewal_case_id' => $renewalCaseId,
            'phase_id' => $phaseId,
            'scheduled_date' => $runDate,
            'error_message' => mb_substr($message, 0, 1000),
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Returns true when inserted, false when idempotent duplicate.
     */
    public function insertDeliveryFailed(int $runId, int $renewalCaseId, int $phaseId, string $runDate, string $message): bool
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
                "renewal",
                :renewal_case_id,
                NULL,
                :phase_id,
                NULL,
                :scheduled_date,
                NULL,
                "failed",
                :error_message
             )'
        );
        $stmt->execute([
            'run_id' => $runId,
            'renewal_case_id' => $renewalCaseId,
            'phase_id' => $phaseId,
            'scheduled_date' => $runDate,
            'error_message' => mb_substr($message, 0, 1000),
        ]);

        return $stmt->rowCount() === 1;
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
