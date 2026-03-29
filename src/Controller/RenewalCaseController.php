<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Renewal\RenewalCaseRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\RenewalCaseDetailView;
use App\Presentation\RenewalCaseListView;
use App\Security\AuthGuard;
use Throwable;

final class RenewalCaseController
{
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = [
            'customer_name' => (string) ($_GET['customer_name'] ?? ''),
            'policy_no' => (string) ($_GET['policy_no'] ?? ''),
            'case_status' => (string) ($_GET['case_status'] ?? ''),
            'maturity_date_from' => (string) ($_GET['maturity_date_from'] ?? ''),
            'maturity_date_to' => (string) ($_GET['maturity_date_to'] ?? ''),
        ];

        $rows = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $rows = $repository->search($criteria, 200);
        } catch (Throwable) {
            $error = '満期一覧の取得に失敗しました。接続設定を確認してください。';
        }

        Responses::html(RenewalCaseListView::render(
            $rows,
            $criteria,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('renewal/detail'),
            $error
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $renewalCaseId = (int) ($_GET['id'] ?? 0);
        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('renewal/list'));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $detail = $repository->findDetailById($renewalCaseId);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象案件が見つかりません。');
                Responses::redirect($this->config->routeUrl('renewal/list'));
            }

            $activities = $repository->findActivities($renewalCaseId);
            $comments = $repository->findComments($renewalCaseId);
            $audits = $repository->findAuditEvents($renewalCaseId);
            $flashError = $this->guard->session()->consumeFlash('error');
            $flashSuccess = $this->guard->session()->consumeFlash('success');
            $csrfToken = $this->guard->session()->issueCsrfToken('renewal_update_' . $renewalCaseId);

            Responses::html(RenewalCaseDetailView::render(
                $detail,
                $activities,
                $comments,
                $audits,
                $this->config->routeUrl('renewal/update'),
                $this->config->routeUrl('renewal/list'),
                $this->config->routeUrl('customer/detail'),
                $csrfToken,
                $flashError,
                $flashSuccess
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '満期詳細の取得に失敗しました。');
            Responses::redirect($this->config->routeUrl('renewal/list'));
        }
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $renewalCaseId = (int) ($_POST['id'] ?? 0);
        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('renewal/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_update_' . $renewalCaseId, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->detailUrl($renewalCaseId));
        }

        $caseStatus = trim((string) ($_POST['case_status'] ?? ''));
        $allowedStatuses = ['open', 'contacted', 'quoted', 'waiting', 'renewed', 'lost', 'closed'];
        if (!in_array($caseStatus, $allowedStatuses, true)) {
            $this->guard->session()->setFlash('error', '対応ステータスが不正です。');
            Responses::redirect($this->detailUrl($renewalCaseId));
        }

        $input = [
            'case_status' => $caseStatus,
            'next_action_date' => (string) ($_POST['next_action_date'] ?? ''),
            'renewal_result' => (string) ($_POST['renewal_result'] ?? ''),
            'lost_reason' => (string) ($_POST['lost_reason'] ?? ''),
            'remark' => (string) ($_POST['remark'] ?? ''),
        ];

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $updated = $repository->updateRenewalCase($renewalCaseId, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated) {
                $this->guard->session()->setFlash('success', '満期対応情報を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、変更がありません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '満期対応情報の更新に失敗しました。');
        }

        Responses::redirect($this->detailUrl($renewalCaseId));
    }

    private function detailUrl(int $renewalCaseId): string
    {
        return $this->config->routeUrl('renewal/detail') . '&id=' . $renewalCaseId;
    }
}