<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Customer\CustomerRepository;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\CustomerDetailView;
use App\Presentation\CustomerListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use PDO;
use Throwable;

final class CustomerController
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
        $auth = $this->guard->requireAuthenticated();

        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $openModal  = trim((string) ($_GET['open_modal'] ?? ''));
        $createDraft = $this->consumeCreateDraft();
        if ($createDraft !== null && $openModal === '') {
            $openModal = 'create';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess  = $this->guard->session()->consumeFlash('success');
        $createCsrf   = $this->guard->session()->issueCsrfToken('customer_create');

        $rows = [];
        $total = 0;
        $listError = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);
            $result = $repository->searchPage(
                $criteria,
                (int) $listState['page'],
                (int) $listState['per_page'],
                (string) $listState['sort'],
                (string) $listState['direction']
            );
            $rows = $result['rows'];
            $total = (int) ($result['total'] ?? 0);
            $listState['page'] = (string) ($result['page'] ?? $listState['page']);
        } catch (Throwable) {
            $listError = '顧客一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));

        Responses::html(CustomerListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $this->config->routeUrl('customer/list'),
            $this->config->routeUrl('customer/detail'),
            $listError,
            (string) ($_GET['filter_open'] ?? '') === '1',
            $this->config->routeUrl('customer/create'),
            $createCsrf,
            $flashError,
            $flashSuccess,
            $openModal,
            $createDraft,
            $staffUsers,
            ControllerLayoutHelper::build($this->guard, $this->config, 'customer')
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl = $this->listUrl($criteria, $listState);
        $customerId = (int) ($_GET['id'] ?? 0);
        if ($customerId <= 0) {
            $this->guard->session()->setFlash('error', '顧客IDが不正です。');
            Responses::redirect($listUrl);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);

            $detail = $repository->findDetailById($customerId);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象顧客が見つかりません。');
                Responses::redirect($listUrl);
            }

            $contacts = $repository->findContacts($customerId);
            $contracts = $repository->findContracts($customerId);
            $activities = $repository->findActivities($customerId);
            $salesCases = (new SalesCaseRepository($pdo))->findByCustomerId($customerId);
            $flashError = $this->guard->session()->consumeFlash('error');

            Responses::html(CustomerDetailView::render(
                $detail,
                $contacts,
                $contracts,
                $activities,
                $salesCases,
                $listUrl,
                $this->config->routeUrl('renewal/detail'),
                $this->config->routeUrl('activity/new'),
                $this->config->routeUrl('activity/detail'),
                $this->config->routeUrl('sales-case/detail'),
                $flashError,
                ControllerLayoutHelper::build(
                    $this->guard,
                    $this->config,
                    'customer',
                    [
                        ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                        ['label' => '顧客一覧', 'url' => $listUrl],
                        ['label' => '顧客詳細'],
                    ]
                )
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '顧客詳細の取得に失敗しました。');
            Responses::redirect($listUrl);
        }
    }

    public function create(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('customer_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $input  = $this->collectCreateInput();
        $errors = $this->validateCreateInput($input);
        // assigned_user_id をテナント所属ユーザーのみに限定（他テナントIDの直打ち防止）
        if ($input['assigned_user_id'] !== null) {
            $tenantCode = (string) ($auth['tenant_code'] ?? '');
            $validIds   = array_column($this->fetchAssignableUsers($tenantCode), 'id');
            if (!in_array($input['assigned_user_id'], $validIds, true)) {
                $input['assigned_user_id'] = null;
            }
        }

        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);
            $newId = $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '顧客を登録しました。');
            Responses::redirect(ListViewHelper::buildUrl(
                $this->config->routeUrl('customer/detail'),
                array_merge(['id' => (string) $newId], $this->buildListQuery([], []))
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '顧客の登録に失敗しました。');
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        return [
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'phone' => trim((string) ($source['phone'] ?? '')),
            'email' => trim((string) ($source['email'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', CustomerRepository::SORTABLE_FIELDS);

        return [
            'page' => (string) ListViewHelper::normalizePage($source['page'] ?? 1),
            'per_page' => (string) ListViewHelper::normalizePerPage($source['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE),
            'sort' => $sort,
            'direction' => $sort === '' ? 'asc' : ListViewHelper::normalizeDirection($source['direction'] ?? 'asc'),
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @return array<string, string>
     */
    private function buildListQuery(array $criteria, array $listState): array
    {
        $params = $criteria;

        if ((int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }

        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }

        if (($listState['sort'] ?? '') !== '') {
            $params['sort'] = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return $params;
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function listUrl(array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl($this->config->routeUrl('customer/list'), $this->buildListQuery($criteria, $listState));
    }

    private function validateReturnTo(mixed $returnTo): string
    {
        $default = $this->config->routeUrl('customer/list');
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        if (str_contains($candidate, 'route=customer/list') || str_contains($candidate, 'route=customer/detail')) {
            return $candidate;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectCreateInput(): array
    {
        return [
            'customer_type'      => trim((string) ($_POST['customer_type'] ?? '')),
            'customer_name'      => trim((string) ($_POST['customer_name'] ?? '')),
            'customer_name_kana' => $this->nullableText($_POST['customer_name_kana'] ?? null),
            'phone'              => $this->nullableText($_POST['phone'] ?? null),
            'email'              => $this->nullableText($_POST['email'] ?? null),
            'postal_code'        => $this->nullableText($_POST['postal_code'] ?? null),
            'address1'           => $this->nullableText($_POST['address1'] ?? null),
            'address2'           => $this->nullableText($_POST['address2'] ?? null),
            'assigned_user_id'   => $this->nullableInt($_POST['assigned_user_id'] ?? null),
            'note'               => $this->nullableText($_POST['note'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    private function validateCreateInput(array $input): array
    {
        $errors = [];

        $customerType = (string) ($input['customer_type'] ?? '');
        if (!in_array($customerType, ['individual', 'corporate'], true)) {
            $errors[] = '顧客区分を選択してください。';
        }

        $customerName = (string) ($input['customer_name'] ?? '');
        if ($customerName === '') {
            $errors[] = '顧客名は必須です。';
        } elseif (mb_strlen($customerName) > 200) {
            $errors[] = '顧客名は200文字以内で入力してください。';
        }

        $email = $input['email'] ?? null;
        if ($email !== null && $email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'メールアドレスの形式が正しくありません。';
            }
        }

        return $errors;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeCreateDraft(array $input): void
    {
        $this->guard->session()->setFlash('customer_create_draft', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeCreateDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('customer_create_draft');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function fetchAssignableUsers(string $tenantCode): array
    {
        $tenantCode = trim($tenantCode);
        if ($tenantCode === '') {
            return [];
        }

        try {
            $pdo = $this->commonConnectionFactory->create();
            $stmt = $pdo->prepare(
                'SELECT u.id, u.name
                 FROM user_tenants ut
                 INNER JOIN users u ON u.id = ut.user_id
                 WHERE ut.tenant_code = :tenant_code
                   AND ut.status = 1
                   AND ut.is_deleted = 0
                   AND u.status = 1
                   AND u.is_deleted = 0
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
        } catch (Throwable) {
            return [];
        }
    }
}
