<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Customer\CustomerRepository;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\CustomerDetailView;
use App\Presentation\CustomerListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use Throwable;

final class CustomerController
{
    private const ALLOWED_RETURN_PREFIXES = [
        'customer/list',
        'renewal/detail',
        'sales/detail',
        'accident/detail',
    ];

    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
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
        $returnToPath = $this->resolveReturnTo($_GET['return_to'] ?? null);
        $rtParts  = explode('?', $returnToPath, 2);
        $returnTo = $this->config->routeUrl($rtParts[0]);
        if (isset($rtParts[1])) {
            $returnTo .= '&' . $rtParts[1];
        }
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

            $contracts     = $repository->findContracts($customerId);
            $activities    = $repository->findActivities($customerId, 5);
            $accidentCases = $repository->findAccidentCases($customerId);
            $salesCases    = (new SalesCaseRepository($pdo))->findByCustomerId($customerId);
            $flashError   = $this->guard->session()->consumeFlash('error');
            $flashSuccess = $this->guard->session()->consumeFlash('success');
            $editDraft    = $this->consumeEditDraft($customerId);
            $editErrors   = $this->consumeEditErrors($customerId);
            $editCsrf     = $this->guard->session()->issueCsrfToken('customer_update_' . $customerId);
            $detailUrl    = $this->detailUrl($customerId, $criteria, $listState);

            Responses::html(CustomerDetailView::render(
                $detail,
                $contracts,
                $activities,
                $accidentCases,
                $salesCases,
                $listUrl,
                $detailUrl,
                $returnTo,
                $this->config->routeUrl('renewal/detail'),
                $this->config->routeUrl('activity/list'),
                $this->config->routeUrl('renewal/detail'),
                $this->config->routeUrl('sales-case/detail'),
                $this->config->routeUrl('customer/update'),
                $editCsrf,
                $editDraft,
                $editErrors,
                $flashError,
                $flashSuccess,
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

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $customerId = (int) ($_POST['id'] ?? 0);
        $criteria   = $this->extractCriteria($_POST);
        $listState  = $this->extractListState($_POST);
        $detailUrl  = $this->detailUrl($customerId, $criteria, $listState);

        if ($customerId <= 0) {
            $this->guard->session()->setFlash('error', '顧客IDが不正です。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('customer_update_' . $customerId, $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($detailUrl);
        }

        $input  = $this->collectUpdateInput();
        $errors = $this->validateUpdateInput($input);

        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeEditDraft($customerId, $input);
            $this->storeEditErrors($customerId, $errors);
            Responses::redirect(ListViewHelper::buildUrl($detailUrl, ['open_modal' => 'edit']));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new CustomerRepository($pdo);
            $repository->update($customerId, $input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '顧客情報を更新しました。');
            Responses::redirect($detailUrl);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '顧客情報の更新に失敗しました。');
            $this->storeEditDraft($customerId, $input);
            Responses::redirect(ListViewHelper::buildUrl($detailUrl, ['open_modal' => 'edit']));
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

    private function resolveReturnTo(?string $rawReturnTo): string
    {
        $default = 'customer/list';
        if ($rawReturnTo === null || $rawReturnTo === '') {
            return $default;
        }
        $decoded = urldecode($rawReturnTo);
        foreach (self::ALLOWED_RETURN_PREFIXES as $prefix) {
            if (str_starts_with($decoded, $prefix)) {
                return $decoded;
            }
        }
        return $default;
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
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function detailUrl(int $customerId, array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl(
            $this->config->routeUrl('customer/detail'),
            array_merge(['id' => (string) $customerId], $this->buildListQuery($criteria, $listState))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function collectUpdateInput(): array
    {
        return [
            'note' => $this->nullableText($_POST['note'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    private function validateUpdateInput(array $input): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeEditDraft(int $customerId, array $input): void
    {
        $this->guard->session()->setFlash('customer_edit_draft_' . $customerId, json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeEditDraft(int $customerId): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('customer_edit_draft_' . $customerId);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, string> $errors
     */
    private function storeEditErrors(int $customerId, array $errors): void
    {
        $this->guard->session()->setFlash('customer_edit_errors_' . $customerId, json_encode($errors, JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    /**
     * @return array<int, string>
     */
    private function consumeEditErrors(int $customerId): array
    {
        $raw = (string) $this->guard->session()->consumeFlash('customer_edit_errors_' . $customerId);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
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

}
