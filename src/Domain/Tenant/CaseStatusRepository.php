<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

/**
 * 対応状況マスタ（m_case_status）
 *
 * 表示名(name) が DB 格納値を兼ねる。設定画面では name・is_completed・
 * is_protected・is_active・display_order を編集する。
 * is_protected=1 のレコードは削除・無効化不可、is_completed=1 のレコードは
 * 完了扱いとしてダッシュボード集計やリマインダー処理から除外される。
 * case_type で renewal / accident を区別する複合マスタ。
 */
final class CaseStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    // ── 取得 ──────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $caseType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_type, name, display_order, is_active, is_completed, is_protected
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
            'SELECT id, case_type, name, display_order, is_active, is_completed, is_protected
             FROM m_case_status
             ORDER BY case_type ASC, is_active DESC, display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_type, name, display_order, is_active, is_completed, is_protected
             FROM m_case_status WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * 有効な name のみ（case_type 指定）。設定画面のプルダウン用。
     *
     * @return list<string>
     */
    public function activeNames(string $caseType): array
    {
        $rows = $this->findByType($caseType);
        $names = [];
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 1) === 1) {
                $names[] = (string) ($row['name'] ?? '');
            }
        }
        return array_values(array_filter($names, static fn (string $n) => $n !== ''));
    }

    /**
     * 指定 name が既に存在するか（case_type 内で）。
     */
    public function existsByName(string $caseType, string $name, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM m_case_status WHERE case_type = :ct AND name = :name'
            );
            $stmt->bindValue(':ct', $caseType);
            $stmt->bindValue(':name', $name);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM m_case_status WHERE case_type = :ct AND name = :name AND id <> :id'
            );
            $stmt->bindValue(':ct', $caseType);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    // ── 操作可否判定 ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $row */
    public function canRename(array $row): bool
    {
        return (int) ($row['is_protected'] ?? 0) === 0;
    }

    /** @param array<string, mixed> $row */
    public function canDisable(array $row): bool
    {
        return (int) ($row['is_protected'] ?? 0) === 0;
    }

    /** @param array<string, mixed> $row */
    public function canDelete(array $row): bool
    {
        return (int) ($row['is_protected'] ?? 0) === 0;
    }

    // ── 書き込み ──────────────────────────────────────────────────────────

    /**
     * 新規追加。case_type 内で重複する name は UNIQUE 制約で拒否される。
     *
     * @throws \DomainException
     */
    public function create(string $caseType, string $name, int $actorUserId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($caseType, $name)) {
            throw new \DomainException('同じ名前の対応状況が既に登録されています。');
        }

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_case_status WHERE case_type = :ct'
        );
        $stmt->bindValue(':ct', $caseType);
        $stmt->execute();
        $maxOrder = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_case_status
                (case_type, name, display_order, is_active, is_completed, is_protected, created_by, updated_by)
             VALUES (:case_type, :name, :display_order, 1, 0, 0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':case_type', $caseType);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':display_order', $maxOrder + 10, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * name を変更する。保護レコードは変更不可、重複は UNIQUE で拒否。
     *
     * @throws \DomainException
     */
    public function updateName(int $id, string $name, int $actorUserId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        $row = $this->findById($id);
        if ($row === null) {
            throw new \DomainException('対象が見つかりません。');
        }
        if (!$this->canRename($row)) {
            throw new \DomainException('このステータスは編集できません。');
        }
        $caseType = (string) ($row['case_type'] ?? '');
        if ($this->existsByName($caseType, $name, $id)) {
            throw new \DomainException('同じ名前の対応状況が既に登録されています。');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status
             SET name = :name, updated_by = :updated_by
             WHERE id = :id AND is_protected = 0'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setCompleted(int $id, int $completed, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status SET is_completed = :completed, updated_by = :actor WHERE id = :id'
        );
        $stmt->bindValue(':completed', $completed, PDO::PARAM_INT);
        $stmt->bindValue(':actor', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * 隣接行と display_order を入れ替える（up: 前の行と交換 / down: 次の行と交換）。
     * @return bool 交換が実行された場合 true
     */
    public function swapDisplayOrder(int $id, string $direction, int $actorUserId): bool
    {
        $self = $this->findById($id);
        if ($self === null) {
            return false;
        }

        $caseType    = (string) ($self['case_type'] ?? '');
        $selfOrder   = (int) ($self['display_order'] ?? 0);

        if ($direction === 'up') {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_case_status
                 WHERE case_type = :ct AND display_order < :order
                 ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_case_status
                 WHERE case_type = :ct AND display_order > :order
                 ORDER BY display_order ASC LIMIT 1'
            );
        }
        $stmt->bindValue(':ct', $caseType);
        $stmt->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $stmt->execute();
        $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($neighbor)) {
            return false;
        }

        $neighborId    = (int) ($neighbor['id'] ?? 0);
        $neighborOrder = (int) ($neighbor['display_order'] ?? 0);

        $upd = $this->pdo->prepare(
            'UPDATE m_case_status SET display_order = :order, updated_by = :actor WHERE id = :id'
        );
        $upd->bindValue(':actor', $actorUserId, PDO::PARAM_INT);

        $upd->bindValue(':order', $neighborOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $id, PDO::PARAM_INT);
        $upd->execute();

        $upd->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $neighborId, PDO::PARAM_INT);
        $upd->execute();

        return true;
    }

    /**
     * 有効・無効を切り替える。保護レコードは拒否（rowCount=0）。
     */
    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status SET is_active = :active WHERE id = :id AND is_protected = 0'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * 保護されていないレコードを物理削除する。
     *
     * @throws \DomainException
     */
    public function delete(int $id, int $actorUserId): void
    {
        $row = $this->findById($id);
        if ($row === null) {
            throw new \DomainException('対象が見つかりません。');
        }
        if (!$this->canDelete($row)) {
            throw new \DomainException('このステータスは削除できません。');
        }

        $stmt = $this->pdo->prepare('DELETE FROM m_case_status WHERE id = :id AND is_protected = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
