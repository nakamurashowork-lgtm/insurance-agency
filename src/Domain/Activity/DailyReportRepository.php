<?php
declare(strict_types=1);

namespace App\Domain\Activity;

use PDO;

final class DailyReportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDateAndStaff(string $reportDate, int $staffUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, report_date, staff_user_id, comment, is_submitted, submitted_at, created_at, updated_at
             FROM t_daily_report
             WHERE report_date = :report_date
               AND staff_user_id = :staff_user_id
               AND is_deleted = 0'
        );
        $stmt->bindValue(':report_date', $reportDate);
        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * INSERT ON DUPLICATE KEY UPDATE でコメントを保存する。
     * UNIQUE KEY(report_date, staff_user_id) を前提とする。
     * is_submitted は現時点では常に 0 のまま（将来フェーズ対応）。
     */
    public function upsertComment(string $reportDate, int $staffUserId, string $comment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_daily_report (report_date, staff_user_id, comment, is_submitted)
             VALUES (:report_date, :staff_user_id, :comment, 0)
             ON DUPLICATE KEY UPDATE
                comment    = VALUES(comment),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':report_date', $reportDate);
        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->bindValue(':comment', $comment);
        $stmt->execute();
    }

    /**
     * 指定日・担当者の活動を時刻順で取得する。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActivitiesForDay(string $reportDate, int $staffUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,
                    a.customer_id,
                    a.activity_date,
                    a.start_time,
                    a.end_time,
                    a.activity_type,
                    a.subject,
                    a.content_summary,
                    a.next_action_date,
                    mc.customer_name
             FROM t_activity a
             INNER JOIN m_customer mc
                     ON mc.id = a.customer_id
                    AND mc.is_deleted = 0
             WHERE a.activity_date = :report_date
               AND a.staff_user_id = :staff_user_id
               AND a.is_deleted = 0
             ORDER BY a.start_time ASC, a.id ASC'
        );
        $stmt->bindValue(':report_date', $reportDate);
        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
