<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Accident\AccidentCaseRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\AccidentCaseDetailView;
use App\Presentation\AccidentCaseListView;
use App\Security\AuthGuard;
use Throwable;

final class AccidentCaseController
{
    private const ALLOWED_STATUS = ['accepted', 'linked', 'in_progress', 'waiting_docs', 'resolved', 'closed'];
    private const ALLOWED_PRIORITY = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $criteria = [
            'accepted_date_from' => (string) ($_GET['accepted_date_from'] ?? ''),
            'accepted_date_to' => (string) ($_GET['accepted_date_to'] ?? ''),
            'customer_name' => (string) ($_GET['customer_name'] ?? ''),
            'policy_no' => (string) ($_GET['policy_no'] ?? ''),
            'product_type' => (string) ($_GET['product_type'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];

        $rows = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $rows = $repository->search($criteria, 200);
        } catch (Throwable) {
            $error = '事故案件一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(AccidentCaseListView::render(
            $rows,
            $criteria,
            $this->config->routeUrl('accident/list'),
            $this->config->routeUrl('accident/detail'),
            $this->config->routeUrl('dashboard'),
            $error,
            $flashError,
            $flashSuccess
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $detail = $repository->findDetailById($id);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象案件が見つかりません。');
                Responses::redirect($this->config->routeUrl('accident/list'));
            }

            $comments = $repository->findComments($id, 30);
            $audits = $repository->findAuditEvents($id, 30);
            $flashError = $this->guard->session()->consumeFlash('error');
            $flashSuccess = $this->guard->session()->consumeFlash('success');

            Responses::html(AccidentCaseDetailView::render(
                $detail,
                $comments,
                $audits,
                $this->config->routeUrl('accident/list'),
                $this->config->routeUrl('accident/update'),
                $this->config->routeUrl('accident/comment'),
                $this->guard->session()->issueCsrfToken('accident_update_' . $id),
                $this->guard->session()->issueCsrfToken('accident_comment_' . $id),
                $flashError,
                $flashSuccess
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故案件詳細の取得に失敗しました。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_update_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
        }

        $status = trim((string) ($_POST['status'] ?? ''));
        $priority = trim((string) ($_POST['priority'] ?? ''));
        if (!in_array($status, self::ALLOWED_STATUS, true) || !in_array($priority, self::ALLOWED_PRIORITY, true)) {
            $this->guard->session()->setFlash('error', '状態または優先度が不正です。');
            Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
        }

        $input = [
            'status' => $status,
            'priority' => $priority,
            'assigned_user_id' => $this->nullableInt($_POST['assigned_user_id'] ?? null),
            'resolved_date' => $this->nullableDate($_POST['resolved_date'] ?? null),
            'insurer_claim_no' => $this->nullableText($_POST['insurer_claim_no'] ?? null),
            'remark' => $this->nullableText($_POST['remark'] ?? null),
        ];

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $updated = $repository->updateAccidentCase($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '事故案件を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、変更がありません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故案件の更新に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
    }

    public function comment(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_comment_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正なコメント要求を検出しました。');
            Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
        }

        $commentBody = trim((string) ($_POST['comment_body'] ?? ''));
        if ($commentBody === '') {
            $this->guard->session()->setFlash('error', 'コメント本文を入力してください。');
            Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $repository->createComment($id, $commentBody, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', 'コメントを登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'コメントの登録に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('accident/detail') . '&id=' . $id);
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function assertAdmin(array $auth): void
    {
        $permissions = $auth['permissions'] ?? [];
        $isSystemAdmin = is_array($permissions) && !empty($permissions['is_system_admin']);
        $isTenantAdmin = is_array($permissions) && (($permissions['tenant_role'] ?? '') === 'admin');
        if (!$isSystemAdmin && !$isTenantAdmin) {
            $this->guard->session()->setFlash('error', '管理者向け機能のため利用できません。');
            Responses::redirect($this->config->routeUrl('dashboard'));
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '' || !ctype_digit($text)) {
            return null;
        }

        return (int) $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return null;
        }

        return $text;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
