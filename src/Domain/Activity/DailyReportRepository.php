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
     * 提出済み（is_submitted=1）の場合はコメントを更新しない。
     */
    public function upsertComment(string $reportDate, int $staffUserId, string $comment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_daily_report (report_date, staff_user_id, comment, is_submitted)
             VALUES (:report_date, :staff_user_id, :comment, 0)
             ON DUPLICATE KEY UPDATE
                comment    = IF(is_submitted = 0, VALUES(comment), comment),
                updated_at = IF(is_submitted = 0, CURRENT_TIMESTAMP, updated_at)'
        );
        $stmt->bindValue(':report_date', $reportDate);
        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->bindValue(':comment', $comment);
        $stmt->execute();
    }

    /**
     * 日報を提出済みにする（is_submitted=1、submitted_at=NOW()）。
     * 冪等。既に提出済みの場合は submitted_at を変更しない。
     */
    public function submit(string $reportDate, int $staffUserId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_daily_report (report_date, staff_user_id, is_submitted, submitted_at)
             VALUES (:report_date, :staff_user_id, 1, NOW())
             ON DUPLICATE KEY UPDATE
                is_submitted = 1,
                submitted_at = IF(submitted_at IS NULL, NOW(), submitted_at),
                updated_at   = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':report_date', $reportDate);
        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
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
             LEFT JOIN m_customer mc
                     ON mc.id = a.customer_id
                    AND mc.is_deleted = 0
             WHERE a.activity_date = :report_date
               AND a.staff_id = :staff_user_id
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
