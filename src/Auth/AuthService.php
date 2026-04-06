<?php
declare(strict_types=1);

namespace App\Auth;

use App\Domain\Auth\AuthException;
use App\Domain\Auth\UserRepository;
use App\SessionManager;

final class AuthService
{
    public function __construct(
        private SessionManager $session,
        private UserRepository $userRepository,
        private TenantResolver $tenantResolver
    ) {
    }

    /**
     * Attempt login via Google identity.
     *
     * Returns a tagged result:
     *   ['status' => 'authenticated']            — system admin, session auth set
     *   ['status' => 'totp_setup_required']      — regular user, no TOTP secret yet
     *   ['status' => 'totp_verify_required']     — regular user, TOTP enabled
     *
     * For pending cases the pending user ID is stored in the session but the
     * full auth cookie is NOT set yet.
     *
     * @return array<string, mixed>
     */
    public function loginWithGoogleIdentity(string $googleSub, string $email = ''): array
    {
        $user = $this->userRepository->findActiveByGoogleSub($googleSub);
        if ($user === null) {
            $normalizedEmail = trim($email);
            if ($normalizedEmail === '') {
                throw new AuthException('利用権限なしのためログインできません。管理者へ連絡してください。');
            }

            $emailMatchedUsers = $this->userRepository->findActiveByEmail($normalizedEmail);
            if ($emailMatchedUsers === []) {
                throw new AuthException('利用権限なしのためログインできません。管理者へ連絡してください。');
            }

            if (count($emailMatchedUsers) > 1) {
                throw new AuthException('同一メールアドレスの利用者が複数存在するためログインできません。管理者へ連絡してください。');
            }

            $candidate = $emailMatchedUsers[0];
            $currentGoogleSub = trim((string) ($candidate['google_sub'] ?? ''));
            if ($currentGoogleSub !== '') {
                throw new AuthException('利用権限なしのためログインできません。管理者へ連絡してください。');
            }

            $bound = $this->userRepository->bindGoogleSubIfEmpty((int) $candidate['id'], $googleSub);
            if (!$bound) {
                throw new AuthException('ログイン情報の更新に失敗しました。管理者へ連絡してください。');
            }

            $user = $this->userRepository->findActiveByGoogleSub($googleSub);
            if ($user === null) {
                throw new AuthException('利用権限なしのためログインできません。管理者へ連絡してください。');
            }
        }

        $userId = (int) $user['id'];
        $tenant = $this->tenantResolver->resolvePrimaryTenantForUser($userId);

        $auth = [
            'user_id' => $userId,
            'display_name' => (string) ($user['display_name'] ?? '') !== '' ? (string) $user['display_name'] : (string) $user['name'],
            'tenant_id' => (int) $tenant['tenant_id'],
            'tenant_code' => (string) $tenant['tenant_code'],
            'tenant_name' => (string) $tenant['tenant_name'],
            'tenant_db_name' => (string) $tenant['db_name'],
            'permissions' => [
                'is_system_admin' => ((int) $user['is_system_admin']) === 1,
                'tenant_role' => (string) $tenant['role'],
            ],
        ];

        // System admins skip TOTP and are authenticated immediately.
        if (((int) $user['is_system_admin']) === 1) {
            $this->session->regenerate();
            $this->session->setAuth($auth);
            $this->userRepository->updateLastLoginAt($userId);

            return ['status' => 'authenticated'];
        }

        // Regular user: store pending state, do NOT set full auth yet.
        $this->session->regenerate();
        $this->session->setTotpPendingUserId($userId);

        if (((int) ($user['totp_enabled'] ?? 0)) === 1) {
            return ['status' => 'totp_verify_required'];
        }

        return ['status' => 'totp_setup_required'];
    }

    /**
     * Complete login after successful TOTP verification.
     * Called from AuthController once the OTP code has been verified.
     *
     * @param array<string, mixed> $user  Row from UserRepository::findActiveById()
     * @param array<string, mixed> $tenant Row from TenantResolver::resolvePrimaryTenantForUser()
     */
    public function completeLogin(array $user, array $tenant): void
    {
        $userId = (int) $user['id'];
        $auth   = [
            'user_id'      => $userId,
            'display_name' => (string) ($user['display_name'] ?? '') !== ''
                ? (string) $user['display_name']
                : (string) $user['name'],
            'tenant_id'      => (int) $tenant['tenant_id'],
            'tenant_code'    => (string) $tenant['tenant_code'],
            'tenant_name'    => (string) $tenant['tenant_name'],
            'tenant_db_name' => (string) $tenant['db_name'],
            'permissions'    => [
                'is_system_admin' => false,
                'tenant_role'     => (string) $tenant['role'],
            ],
        ];

        $this->session->clearTotpPending();
        $this->session->setAuth($auth);
        $this->userRepository->updateLastLoginAt($userId);
    }
}
