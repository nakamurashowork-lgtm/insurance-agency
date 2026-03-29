<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Customer\CustomerRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\CustomerDetailView;
use App\Presentation\CustomerListView;
use App\Security\AuthGuard;
use Throwable;

final class CustomerController
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
            'phone' => (string) ($_GET['phone'] ?? ''),
            'email' => (string) ($_GET['email'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];

        $rows = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);
            $rows = $repository->search($criteria, 200);
        } catch (Throwable) {
            $error = '顧客一覧の取得に失敗しました。接続設定を確認してください。';
        }

        Responses::html(CustomerListView::render(
            $rows,
            $criteria,
            $this->config->routeUrl('customer/list'),
            $this->config->routeUrl('customer/detail'),
            $error
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $customerId = (int) ($_GET['id'] ?? 0);
        if ($customerId <= 0) {
            $this->guard->session()->setFlash('error', '顧客IDが不正です。');
            Responses::redirect($this->config->routeUrl('customer/list'));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);

            $detail = $repository->findDetailById($customerId);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象顧客が見つかりません。');
                Responses::redirect($this->config->routeUrl('customer/list'));
            }

            $contacts = $repository->findContacts($customerId);
            $contracts = $repository->findContracts($customerId);
            $activities = $repository->findActivities($customerId);
            $flashError = $this->guard->session()->consumeFlash('error');

            Responses::html(CustomerDetailView::render(
                $detail,
                $contacts,
                $contracts,
                $activities,
                $this->config->routeUrl('customer/list'),
                $this->config->routeUrl('renewal/detail'),
                $flashError
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '顧客詳細の取得に失敗しました。');
            Responses::redirect($this->config->routeUrl('customer/list'));
        }
    }
}
