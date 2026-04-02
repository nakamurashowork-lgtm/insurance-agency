<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class SjnetStaffMappingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 全件取得。有効レコードを先に、無効を後に表示するため is_active DESC, id ASC でソート。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, sjnet_agency_code, sjnet_staff_name, user_id, is_active, note, created_at, updated_at
             FROM m_sjnet_staff_mapping
             ORDER BY is_active DESC, id ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sjnet_agency_code, sjnet_staff_name, user_id, is_active, note, created_at, updated_at
             FROM m_sjnet_staff_mapping
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(
        string $sjnetAgencyCode,
        ?string $sjnetStaffName,
        int $userId,
        ?string $note,
        int $actorUserId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_sjnet_staff_mapping
                (sjnet_agency_code, sjnet_staff_name, user_id, is_active, note, created_by, updated_by)
             VALUES
                (:sjnet_agency_code, :sjnet_staff_name, :user_id, 1, :note, :created_by, :updated_by)'
        );
        $stmt->bindValue(':sjnet_agency_code', $sjnetAgencyCode);
        $stmt->bindValue(':sjnet_staff_name', $sjnetStaffName);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $sjnetAgencyCode,
        ?string $sjnetStaffName,
        int $userId,
        ?string $note,
        int $actorUserId
    ): int {
        $stmt = $this->pdo->prepare(
            'UPDATE m_sjnet_staff_mapping
             SET sjnet_agency_code = :sjnet_agency_code,
                 sjnet_staff_name  = :sjnet_staff_name,
                 user_id           = :user_id,
                 note              = :note,
                 updated_by        = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':sjnet_agency_code', $sjnetAgencyCode);
        $stmt->bindValue(':sjnet_staff_name', $sjnetStaffName);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function deactivate(int $id, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_sjnet_staff_mapping
             SET is_active  = 0,
                 updated_by = :updated_by
             WHERE id = :id AND is_active = 1'
        );
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
