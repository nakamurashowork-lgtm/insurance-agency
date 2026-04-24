<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Domain\Tenant\ProductCategoryRepository;
use App\Domain\Tenant\SalesCaseStatusRepository;
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
        $salesCaseStatuses = [];
        $loginStaffId      = 0;
        $quickFilterCounts = [];
        $error             = null;

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesCaseRepository($tenantPdo);

            // ログインユーザーに対応する m_staff を先に特定（searchPage / countByQuickFilters に渡す）
            // user_id リンクが優先、未設定なら staff_name と auth.display_name で突合
            $staffUsersPre  = (new StaffRepository($tenantPdo))->findActive();
            $loginUserId    = (int) ($auth['user_id'] ?? 0);
            $loginUserName  = trim((string) ($auth['display_name'] ?? ''));
            $fallbackByName = 0;
            foreach ($staffUsersPre as $u) {
                $uid   = (int) ($u['user_id'] ?? 0);
                $sid   = (int) ($u['id'] ?? 0);
                $sname = trim((string) ($u['staff_name'] ?? ''));
                if ($loginUserId > 0 && $uid === $loginUserId) {
                    $loginStaffId = $sid;
                    break;
                }
                if ($fallbackByName === 0 && $loginUserName !== '' && $sname === $loginUserName) {
                    $fallbackByName = $sid;
                }
            }
            if ($loginStaffId === 0) {
                $loginStaffId = $fallbackByName;
            }

            $searchCriteria = $criteria + ['_login_staff_id' => (string) $loginStaffId];

            $result     = $repository->searchPage(
                $searchCriteria,
                (int) $listState['page'],
                (int) $listState['per_page'],
                (string) $listState['sort'],
                (string) $listState['direction']
            );
            $rows  = $result['rows'];
            $total = (int) ($result['total'] ?? 0);
            $listState['page'] = (string) ($result['page'] ?? $listState['page']);
            $listUrl = $this->listUrl($criteria, $listState);

            $staffUsers   = $staffUsersPre;
            $staffNameMap = $this->buildUserNameMap($staffUsers);
            foreach ($rows as $i => $row) {
                $rows[$i]['staff_name'] = $staffNameMap[(int) ($row['staff_id'] ?? 0)] ?? '';
            }
            $customers         = $repository->fetchCustomers();
            $productCategories = (new ProductCategoryRepository($tenantPdo))->findActiveNames();
            $salesCaseStatuses = (new SalesCaseStatusRepository($tenantPdo))->findActive();

            $quickFilterCounts = $repository->countByQuickFilters($searchCriteria, $loginStaffId);
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
            $this->config->routeUrl('sales-case/list'),
            $this->config->routeUrl('sales-case/store'),
            $this->guard->session()->issueCsrfToken('sales_case_store'),
            $this->config->routeUrl('sales-case/detail'),
            $this->config->routeUrl('customer/detail'),
            $this->config->routeUrl('sales-case/delete'),
            $this->guard->session()->issueCsrfToken('sales_case_delete'),
            $flashError,
            $flashSuccess,
            $error,
            ControllerLayoutHelper::build($this->guard, $this->config, 'sales_case'),
            $salesCaseStatuses,
            $quickFilterCounts
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
        $salesCaseStatuses = [];
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
            $productCategories = (new ProductCategoryRepository($tenantPdo))->findActiveNames();
            $salesCaseStatuses = (new SalesCaseStatusRepository($tenantPdo))->findActive();

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
            $productCategories,
            $salesCaseStatuses
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

        try {
            $tenantPdo   = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $validStatus = $this->validStatusNames($tenantPdo);

            $errors = $this->validateInput($input, $validStatus);
            if ($errors !== []) {
                $this->guard->session()->setFlash('error', implode(' ', $errors));
                Responses::redirect($returnTo !== '' ? $returnTo : $fallback);
            }

            $repository = new SalesCaseRepository($tenantPdo);
            $newId      = $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '見込案件を登録しました。');
            Responses::redirect($returnTo !== '' ? $returnTo : $fallback);
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

        try {
            $tenantPdo   = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $validStatus = $this->validStatusNames($tenantPdo);

            $errors = $this->validateInput($input, $validStatus);
            if ($errors !== []) {
                $this->guard->session()->setFlash('error', implode(' ', $errors));
                Responses::redirect($this->detailUrl($id));
            }

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
        $quickFilter = trim((string) ($source['quick_filter'] ?? ''));
        if (!in_array($quickFilter, ['high_open', 'open', 'mine', 'completed'], true)) {
            $quickFilter = '';
        }

        return [
            'case_name'     => trim((string) ($source['case_name'] ?? '')),
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'staff_id'      => trim((string) ($source['staff_id'] ?? '')),
            'status'        => trim((string) ($source['status'] ?? '')),
            'prospect_rank' => trim((string) ($source['prospect_rank'] ?? '')),
            'quick_filter'  => $quickFilter,
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
            'next_action_date'        => trim((string) ($_POST['next_action_date'] ?? '')),
            'lost_reason'             => trim((string) ($_POST['lost_reason'] ?? '')),
            'memo'                    => trim((string) ($_POST['memo'] ?? '')),
            'staff_id'                => trim((string) ($_POST['staff_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string>         $validStatusNames 有効な status name の許容一覧（DB から動的取得）
     * @return string[]
     */
    private function validateInput(array $input, array $validStatusNames): array
    {
        $errors = [];

        $caseName = trim((string) ($input['case_name'] ?? ''));
        if ($caseName === '') {
            $errors[] = '案件名は必須です。';
        }

        $status = trim((string) ($input['status'] ?? ''));
        if ($status === '' || !in_array($status, $validStatusNames, true)) {
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
     * 見込案件ステータスマスタから有効な name 一覧を取得する。
     *
     * @return list<string>
     */
    private function validStatusNames(PDO $pdo): array
    {
        return (new SalesCaseStatusRepository($pdo))->findActiveNames();
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
