<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class CaseStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $caseType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_type, code, display_name, display_order, is_system, is_active
             FROM m_case_status
             WHERE case_type = :case_type
             ORDER BY is_active DESC, display_order ASC, id ASC'
        );
        $stmt->bindValue(':case_type', $caseType);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, case_type, code, display_name, display_order, is_system, is_active
             FROM m_case_status
             ORDER BY case_type ASC, is_active DESC, display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * code => display_name map for a given case_type
     * @return array<string, string>
     */
    public function codeNameMap(string $caseType): array
    {
        $rows = $this->findByType($caseType);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['code'] ?? '')] = (string) ($row['display_name'] ?? '');
        }
        return $map;
    }

    /**
     * @return list<string>
     */
    public function validCodes(string $caseType): array
    {
        $rows = $this->findByType($caseType);
        return array_values(array_map(
            fn($r) => (string) ($r['code'] ?? ''),
            array_filter($rows, fn($r) => (int) ($r['is_active'] ?? 1) === 1)
        ));
    }

    public function create(string $caseType, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(display_order), 0) FROM m_case_status WHERE case_type = :ct');
        $stmt->bindValue(':ct', $caseType);
        $stmt->execute();
        $maxOrder = (int) $stmt->fetchColumn();

        $code = $caseType . '_custom_' . uniqid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_case_status (case_type, code, display_name, display_order, is_system, created_by, updated_by)
             VALUES (:case_type, :code, :display_name, :display_order, 0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':case_type', $caseType);
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':display_order', $maxOrder + 10, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function updateDisplayName(int $id, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status
             SET display_name = :display_name, updated_by = :updated_by
             WHERE id = :id AND is_system = 0'
        );
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status SET is_active = :active WHERE id = :id AND is_system = 0'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
