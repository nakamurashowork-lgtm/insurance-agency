<?php
declare(strict_types=1);

namespace App\Domain\Auth;

use App\Infra\CommonConnectionFactory;

final class UserRepository
{
    public function __construct(private CommonConnectionFactory $commonFactory)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByGoogleSub(string $googleSub): ?array
    {
        $pdo = $this->commonFactory->create();
        $stmt = $pdo->prepare(
            'SELECT id, google_sub, email, name, is_system_admin
             FROM users
             WHERE google_sub = :google_sub
               AND status = 1
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['google_sub' => $googleSub]);

        $user = $stmt->fetch();
        return is_array($user) ? $user : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByEmail(string $email): array
    {
        $pdo = $this->commonFactory->create();
        $stmt = $pdo->prepare(
            'SELECT id, google_sub, email, name, is_system_admin
             FROM users
             WHERE email = :email
               AND status = 1
               AND is_deleted = 0'
        );
        $stmt->execute(['email' => $email]);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function bindGoogleSubIfEmpty(int $userId, string $googleSub): bool
    {
        $pdo = $this->commonFactory->create();
        $stmt = $pdo->prepare(
            'UPDATE users
             SET google_sub = :google_sub,
                 updated_by = :updated_by
             WHERE id = :user_id
               AND status = 1
               AND is_deleted = 0
               AND (google_sub IS NULL OR google_sub = "")'
        );

        $stmt->execute([
            'google_sub' => $googleSub,
            'user_id' => $userId,
            'updated_by' => $userId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function updateLastLoginAt(int $userId): void
    {
        $pdo = $this->commonFactory->create();
        $stmt = $pdo->prepare(
            'UPDATE users
             SET last_login_at = NOW(), updated_by = :updated_by
             WHERE id = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
