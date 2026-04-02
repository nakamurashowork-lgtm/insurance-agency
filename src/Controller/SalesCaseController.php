<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
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
        private CommonConnectionFactory $commonConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth      = $this->guard->requireAuthenticated();
        $criteria  = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl   = $this->listUrl($criteria, $listState);

        $rows       = [];
        $total      = 0;
        $staffUsers = [];
        $error      = null;

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

            $staffUsers     = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $staffNameMap   = $this->buildUserNameMap($staffUsers);
            foreach ($rows as $i => $row) {
                $rows[$i]['staff_name'] = $staffNameMap[(int) ($row['staff_user_id'] ?? 0)] ?? '';
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
            $listUrl,
            $this->config->routeUrl('sales-case/new'),
            $this->config->routeUrl('sales-case/detail'),
            $this->config->routeUrl('customer/detail'),
            $flashError,
            $flashSuccess,
            $error,
            ControllerLayoutHelper::build($this->guard, $this->config, 'sales_case')
        ));
    }

    public function newForm(): void
    {
        $auth    = $this->guard->requireAuthenticated();
        $listUrl = $this->listUrl($this->extractCriteria($_GET), $this->extractListState($_GET));

        $customers  = [];
        $staffUsers = [];
        $error      = null;

        $prefill = [
            'staff_user_id' => (string) ($auth['user_id'] ?? ''),
            'status'        => 'open',
            'case_type'     => 'new',
        ];

        if (($_GET['customer_id'] ?? '') !== '') {
            $prefill['customer_id'] = (string) (int) ($_GET['customer_id'] ?? 0);
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $customers  = $repository->fetchCustomers();
            $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
        } catch (Throwable) {
            $error = '顧客・担当者情報の取得に失敗しました。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');

        Responses::html(SalesCaseDetailView::renderNew(
            $prefill,
            $customers,
            $staffUsers,
            $listUrl,
            $this->config->routeUrl('sales-case/store'),
            $this->guard->session()->issueCsrfToken('sales_case_store'),
            $flashError,
            $error,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'sales_case',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '見込案件一覧', 'url' => $listUrl],
                    ['label' => '見込案件登録'],
                ]
            )
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
        $customers  = [];
        $staffUsers = [];
        $activities = [];
        $error      = null;

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $record     = $repository->findById($id);

            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象の見込案件が見つかりません。');
                Responses::redirect($listUrl);
            }

            $customers  = $repository->fetchCustomers();
            $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $activities = $repository->fetchLinkedActivities($id);

            $staffNameMap = $this->buildUserNameMap($staffUsers);
            $record['staff_name'] = $staffNameMap[(int) ($record['staff_user_id'] ?? 0)] ?? '';
            foreach ($activities as $i => $act) {
                $activities[$i]['staff_name'] = $staffNameMap[(int) ($act['staff_user_id'] ?? 0)] ?? '';
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
            )
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
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            Responses::redirect($this->config->routeUrl('sales-case/new'));
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);
            $newId      = $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '見込案件を登録しました。');
            Responses::redirect($this->detailUrl($newId));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '見込案件の登録に失敗しました。');
            Responses::redirect($this->config->routeUrl('sales-case/new'));
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
            'staff_user_id' => trim((string) ($source['staff_user_id'] ?? '')),
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
            'contract_id'             => trim((string) ($_POST['contract_id'] ?? '')),
            'case_name'               => trim((string) ($_POST['case_name'] ?? '')),
            'case_type'               => trim((string) ($_POST['case_type'] ?? '')),
            'product_type'            => trim((string) ($_POST['product_type'] ?? '')),
            'status'                  => trim((string) ($_POST['status'] ?? '')),
            'prospect_rank'           => trim((string) ($_POST['prospect_rank'] ?? '')),
            'expected_premium'        => trim((string) ($_POST['expected_premium'] ?? '')),
            'expected_contract_month' => trim((string) ($_POST['expected_contract_month'] ?? '')),
            'referral_source'         => trim((string) ($_POST['referral_source'] ?? '')),
            'next_action_date'        => trim((string) ($_POST['next_action_date'] ?? '')),
            'lost_reason'             => trim((string) ($_POST['lost_reason'] ?? '')),
            'memo'                    => trim((string) ($_POST['memo'] ?? '')),
            'staff_user_id'           => trim((string) ($_POST['staff_user_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return string[]
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        $customerId = trim((string) ($input['customer_id'] ?? ''));
        if ($customerId === '' || !ctype_digit($customerId) || (int) $customerId <= 0) {
            $errors[] = '顧客を選択してください。';
        }

        $caseName = trim((string) ($input['case_name'] ?? ''));
        if ($caseName === '') {
            $errors[] = '案件名は必須です。';
        }

        $caseType = trim((string) ($input['case_type'] ?? ''));
        if (!array_key_exists($caseType, SalesCaseRepository::ALLOWED_CASE_TYPES)) {
            $errors[] = '案件種別を選択してください。';
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
     * @return array<int, array{id:int, name:string}>
     */
    private function fetchAssignableUsers(string $tenantCode): array
    {
        if ($tenantCode === '') {
            return [];
        }

        $pdo  = $this->commonConnectionFactory->create();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name
             FROM user_tenants ut
             INNER JOIN users u ON u.id = ut.user_id
             WHERE ut.tenant_code = :tenant_code
               AND ut.status = 1 AND ut.is_deleted = 0
               AND u.status = 1 AND u.is_deleted = 0
             ORDER BY u.name ASC, u.id ASC'
        );
        $stmt->bindValue(':tenant_code', $tenantCode);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id   = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $result[] = ['id' => $id, 'name' => $name];
            }
        }

        return $result;
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
            $name = trim((string) ($user['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }
}
