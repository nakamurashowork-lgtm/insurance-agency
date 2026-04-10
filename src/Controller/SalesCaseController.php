<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Domain\Tenant\ProductCategoryRepository;
use App\Domain\Tenant\StaffRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\SalesCaseDetailView;
use App\Presentation\SalesCaseListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use PDO;
use Throwable;

final class SalesCaseController
{
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth      = $this->guard->requireAuthenticated();
        $criteria  = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl   = $this->listUrl($criteria, $listState);

        $rows              = [];
        $total             = 0;
        $staffUsers        = [];
        $customers         = [];
        $productCategories = [];
        $loginStaffId      = 0;
        $error             = null;

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $result     = $repository->searchPage(
                $criteria,
                (int) $listState['page'],
                (int) $listState['per_page'],
                (string) $listState['sort'],
                (string) $listState['direction']
            );
            $rows  = $result['rows'];
            $total = (int) ($result['total'] ?? 0);
            $listState['page'] = (string) ($result['page'] ?? $listState['page']);
            $listUrl = $this->listUrl($criteria, $listState);

            $staffUsers   = (new StaffRepository($tenantPdo))->findActive();
            $staffNameMap = $this->buildUserNameMap($staffUsers);
            foreach ($rows as $i => $row) {
                $rows[$i]['staff_name'] = $staffNameMap[(int) ($row['staff_id'] ?? 0)] ?? '';
            }
            $customers         = $repository->fetchCustomers();
            $productCategories = (new ProductCategoryRepository($tenantPdo))->findAll();

            // ログインユーザーに対応するm_staffレコードを特定
            $loginUserId = (int) ($auth['user_id'] ?? 0);
            foreach ($staffUsers as $u) {
                if ($loginUserId > 0 && (int) ($u['user_id'] ?? 0) === $loginUserId) {
                    $loginStaffId = (int) ($u['id'] ?? 0);
                    break;
                }
            }
        } catch (Throwable) {
            $error = '見込案件一覧の取得に失敗しました。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(SalesCaseListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $staffUsers,
            $customers,
            $productCategories,
            $loginStaffId,
            $listUrl,
            $this->config->routeUrl('sales-case/store'),
            $this->guard->session()->issueCsrfToken('sales_case_store'),
            $this->config->routeUrl('sales-case/detail'),
            $this->config->routeUrl('customer/detail'),
            $this->config->routeUrl('sales-case/delete'),
            $this->guard->session()->issueCsrfToken('sales_case_delete'),
            $flashError,
            $flashSuccess,
            $error,
            ControllerLayoutHelper::build($this->guard, $this->config, 'sales_case')
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $id   = (int) ($_GET['id'] ?? 0);

        $listUrl = $this->listUrl($this->extractCriteria($_GET), $this->extractListState($_GET));

        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($listUrl);
        }

        $record     = null;
        $customers         = [];
        $staffUsers        = [];
        $activities        = [];
        $productCategories = [];
        $error             = null;

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $record     = $repository->findById($id);

            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象の見込案件が見つかりません。');
                Responses::redirect($listUrl);
            }

            $customers         = $repository->fetchCustomers();
            $staffUsers        = (new StaffRepository($tenantPdo))->findActive();
            $activities        = $repository->fetchLinkedActivities($id);
            $productCategories = (new ProductCategoryRepository($tenantPdo))->findAll();

            $staffNameMap = $this->buildUserNameMap($staffUsers);
            $record['staff_name'] = $staffNameMap[(int) ($record['staff_id'] ?? 0)] ?? '';
            foreach ($activities as $i => $act) {
                $activities[$i]['staff_name'] = $staffNameMap[(int) ($act['staff_id'] ?? 0)] ?? '';
            }
        } catch (Throwable) {
            $error = '見込案件詳細の取得に失敗しました。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $detailUrl    = $this->detailUrl($id);

        Responses::html(SalesCaseDetailView::renderDetail(
            $record,
            $customers,
            $staffUsers,
            $activities,
            $listUrl,
            $detailUrl,
            $this->config->routeUrl('sales-case/update'),
            $this->config->routeUrl('sales-case/delete'),
            $this->config->routeUrl('customer/detail'),
            $this->config->routeUrl('activity/detail'),
            $this->guard->session()->issueCsrfToken('sales_case_update'),
            $this->guard->session()->issueCsrfToken('sales_case_delete'),
            $flashError,
            $flashSuccess,
            $error,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'sales_case',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '見込案件一覧', 'url' => $listUrl],
                    ['label' => '見込案件詳細'],
                ]
            ),
            $productCategories
        ));
    }

    public function store(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_case_store', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales-case/list'));
        }

        $input  = $this->collectInput();
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        $fallback = $this->config->routeUrl('sales-case/list');

        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            Responses::redirect($returnTo !== '' ? $returnTo : $fallback);
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $newId      = $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '見込案件を登録しました。');
            Responses::redirect($this->detailUrl($newId));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '見込案件の登録に失敗しました。');
            Responses::redirect($returnTo !== '' ? $returnTo : $fallback);
        }
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_case_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales-case/list'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('sales-case/list'));
        }

        $input  = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            Responses::redirect($this->detailUrl($id));
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $updated    = $repository->update($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '見込案件を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '見込案件の更新に失敗しました。');
        }

        Responses::redirect($this->detailUrl($id));
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_case_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales-case/list'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('sales-case/list'));
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $deleted    = $repository->softDelete($id, (int) ($auth['user_id'] ?? 0));
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '見込案件を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '見込案件の削除に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('sales-case/list'));
    }

    // ---- private helpers ----

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function listUrl(array $criteria, array $listState): string
    {
        $params = $criteria;
        if ((int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }
        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }
        if (($listState['sort'] ?? '') !== '') {
            $params['sort']      = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return ListViewHelper::buildUrl($this->config->routeUrl('sales-case/list'), $params);
    }

    private function detailUrl(int $id): string
    {
        return ListViewHelper::buildUrl($this->config->routeUrl('sales-case/detail'), ['id' => (string) $id]);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        return [
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'staff_id' => trim((string) ($source['staff_id'] ?? '')),
            'status'        => trim((string) ($source['status'] ?? '')),
            'prospect_rank' => trim((string) ($source['prospect_rank'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort(
            $source['sort'] ?? '',
            SalesCaseRepository::SORTABLE_FIELDS
        );

        return [
            'page'      => (string) ListViewHelper::normalizePage($source['page'] ?? 1),
            'per_page'  => (string) ListViewHelper::normalizePerPage($source['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE),
            'sort'      => $sort,
            'direction' => $sort === '' ? 'asc' : ListViewHelper::normalizeDirection($source['direction'] ?? 'asc'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(): array
    {
        return [
            'customer_id'             => trim((string) ($_POST['customer_id'] ?? '')),
            'prospect_name'           => trim((string) ($_POST['prospect_name'] ?? '')),
            'contract_id'             => trim((string) ($_POST['contract_id'] ?? '')),
            'case_name'               => trim((string) ($_POST['case_name'] ?? '')),
            'case_type'               => 'other',
            'product_type'            => trim((string) ($_POST['product_type'] ?? '')),
            'status'                  => trim((string) ($_POST['status'] ?? '')),
            'prospect_rank'           => trim((string) ($_POST['prospect_rank'] ?? '')),
            'expected_premium'        => trim((string) ($_POST['expected_premium'] ?? '')),
            'expected_contract_month' => trim((string) ($_POST['expected_contract_month'] ?? '')),
            'referral_source'         => trim((string) ($_POST['referral_source'] ?? '')),
            'next_action_date'        => trim((string) ($_POST['next_action_date'] ?? '')),
            'lost_reason'             => trim((string) ($_POST['lost_reason'] ?? '')),
            'memo'                    => trim((string) ($_POST['memo'] ?? '')),
            'staff_id'                => trim((string) ($_POST['staff_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return string[]
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        $caseName = trim((string) ($input['case_name'] ?? ''));
        if ($caseName === '') {
            $errors[] = '案件名は必須です。';
        }

        $status = trim((string) ($input['status'] ?? ''));
        if (!array_key_exists($status, SalesCaseRepository::ALLOWED_STATUSES)) {
            $errors[] = 'ステータスを選択してください。';
        }

        $prospectRank = trim((string) ($input['prospect_rank'] ?? ''));
        if ($prospectRank !== '' && !in_array($prospectRank, SalesCaseRepository::ALLOWED_PROSPECT_RANKS, true)) {
            $errors[] = '見込度の値が不正です。';
        }

        $premiumStr = trim((string) ($input['expected_premium'] ?? ''));
        if ($premiumStr !== '' && (!is_numeric($premiumStr) || (int) $premiumStr < 0)) {
            $errors[] = '想定保険料は0以上の数値で入力してください。';
        }

        $month = trim((string) ($input['expected_contract_month'] ?? ''));
        if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $errors[] = '契約予定月は YYYY-MM 形式で入力してください。';
        }

        return $errors;
    }

    /**
     * @param array<int, array{id:int, name:string}> $users
     * @return array<int, string>
     */
    private function buildUserNameMap(array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }
}
