<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Sales\SalesCsvImportService;
use App\Domain\Sales\SalesPerformanceRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\SalesPerformanceDetailView;
use App\Presentation\SalesPerformanceListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SalesPerformanceController
{
    private const ALLOWED_TYPES = ['new', 'renewal', 'addition', 'change', 'cancel_deduction'];
    private const ALLOWED_SOURCE_TYPES = ['non_life', 'life'];

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
        $listUrl = $this->listUrl($criteria, $listState);

        $rows = [];
        $total = 0;
        $customers = [];
        $staffUsers = [];
        $contracts = [];
        $renewalCases = [];
        $metrics = ['non_life_month' => 0, 'non_life_ytd' => 0, 'general_month' => 0, 'total_count_month' => 0];
        $createDraft = $this->consumeCreateDraft();
        $importBatch = null;
        $importRows = [];
        $error = null;

        $openModal = trim((string) ($_GET['open_modal'] ?? ''));
        if (!in_array($openModal, ['create', 'import'], true)) {
            $openModal = '';
        }
        if ($createDraft !== null && $openModal === '') {
            $openModal = 'create';
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($this->guard->requireAuthenticated());
            $repository = new SalesPerformanceRepository($pdo);
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
            $listUrl = $this->listUrl($criteria, $listState);
            $customers = $repository->fetchCustomers(500);
            $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $contracts = $repository->fetchContracts(500);
            $renewalCases = $repository->fetchRenewalCases(500);
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $rows = $this->attachStaffNamesToRows($rows, $staffUserNames);
            $now = new DateTimeImmutable();
            $metrics = $repository->fetchMonthlyMetrics((int) $now->format('Y'), (int) $now->format('n'));

            $importBatchId = (int) ($_GET['import_batch_id'] ?? 0);
            if ($importBatchId > 0) {
                $importBatch = $repository->findImportBatchById($importBatchId);
                if ($importBatch !== null) {
                    $importRows = $repository->findImportRowsByBatchId($importBatchId, 500);
                    if ($openModal === '') {
                        $openModal = 'import';
                    }
                }
            }
        } catch (Throwable) {
            $error = '実績一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(SalesPerformanceListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $customers,
            $staffUsers,
            $contracts,
            $renewalCases,
            $createDraft,
            $openModal,
            $listUrl,
            $this->config->routeUrl('sales/detail'),
            $this->config->routeUrl('sales/create'),
            $this->config->routeUrl('sales/import'),
            $this->guard->session()->issueCsrfToken('sales_create'),
            $this->guard->session()->issueCsrfToken('sales_import'),
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_TYPES,
            $importBatch,
            $importRows,
            $metrics,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'sales')
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl = $this->listUrl($criteria, $listState);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '実績IDが不正です。');
            Responses::redirect($listUrl);
        }

        $record = null;
        $customers = [];
        $staffUsers = [];
        $contracts = [];
        $renewalCases = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $record = $repository->findById($id);
            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象実績が見つかりません。');
                Responses::redirect($listUrl);
            }
            $customers = $repository->fetchCustomers(500);
            $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $contracts = $repository->fetchContracts(500);
            $renewalCases = $repository->fetchRenewalCases(500);
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $record = $this->attachStaffNameToRecord($record, $staffUserNames);

            $editDraft = $this->consumeEditDraft();
            if ($editDraft !== null && (int) ($editDraft['id'] ?? 0) === $id && is_array($editDraft['input'] ?? null)) {
                $record = array_merge($record, (array) $editDraft['input']);
            }
        } catch (Throwable) {
            $error = '実績詳細の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $detailUrl = $this->detailUrl($id, $criteria, $listState);

        Responses::html(SalesPerformanceDetailView::render(
            $record,
            $customers,
            $staffUsers,
            $contracts,
            $renewalCases,
            self::ALLOWED_TYPES,
            $listUrl,
            $detailUrl,
            $this->config->routeUrl('sales/update'),
            $this->config->routeUrl('sales/delete'),
            $this->guard->session()->issueCsrfToken('sales_update'),
            $this->guard->session()->issueCsrfToken('sales_delete'),
            $flashError,
            $flashSuccess,
            $error,
            $this->config->routeUrl('customer/detail'),
            $this->config->routeUrl('renewal/detail'),
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'sales',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '実績一覧', 'url' => $listUrl],
                    ['label' => '実績詳細'],
                ]
            )
        ));
    }

    public function import(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_import', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->guard->session()->setFlash('error', 'CSVファイルを指定してください。');
            Responses::redirect($returnTo);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'upload.csv');
        if ($tmpName === '' || !is_uploaded_file($tmpName) && !is_file($tmpName)) {
            $this->guard->session()->setFlash('error', 'CSVファイルの読込に失敗しました。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $service = new SalesCsvImportService($repository);
            $result = $service->importFile($tmpName, $originalName, (int) ($auth['user_id'] ?? 0));

            if (($result['status'] ?? '') === 'failed') {
                $this->guard->session()->setFlash('error', 'CSV取込に失敗しました。内容を確認してください。');
            } else {
                $this->guard->session()->setFlash('success', 'CSV取込が完了しました。');
            }

            Responses::redirect(ListViewHelper::buildUrl($returnTo, [
                'import_batch_id' => (string) ((int) ($result['batch_id'] ?? 0)),
                'open_modal' => 'import',
            ]));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'CSV取込に失敗しました。内容を確認してください。');
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'import']));
        }
    }

    public function create(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '実績を登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '実績の登録に失敗しました。');
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }

        Responses::redirect($returnTo);
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '実績IDが不正です。');
            Responses::redirect($returnTo);
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeEditDraft($id, $input);
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $updated = $repository->update($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '実績を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
                $this->storeEditDraft($id, $input);
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '実績の更新に失敗しました。');
            $this->storeEditDraft($id, $input);
        }

        Responses::redirect($returnTo);
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '実績IDが不正です。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $deleted = $repository->softDelete($id, (int) ($auth['user_id'] ?? 0));
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '実績を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '実績の削除に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('sales/list'));
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        return [
            'performance_date_from' => trim((string) ($source['performance_date_from'] ?? '')),
            'performance_date_to' => trim((string) ($source['performance_date_to'] ?? '')),
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'staff_user_id' => trim((string) ($source['staff_user_id'] ?? '')),
            'source_type' => trim((string) ($source['source_type'] ?? '')),
            'performance_type' => trim((string) ($source['performance_type'] ?? '')),
            'insurer_name' => trim((string) ($source['insurer_name'] ?? '')),
            'policy_no' => trim((string) ($source['policy_no'] ?? '')),
            'product_type' => trim((string) ($source['product_type'] ?? '')),
            'settlement_month' => trim((string) ($source['settlement_month'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', SalesPerformanceRepository::SORTABLE_FIELDS);

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
        return ListViewHelper::buildUrl($this->config->routeUrl('sales/list'), $this->buildListQuery($criteria, $listState));
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function detailUrl(int $id, array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl(
            $this->config->routeUrl('sales/detail'),
            array_merge(['id' => (string) $id], $this->buildListQuery($criteria, $listState))
        );
    }

    private function validateReturnTo(mixed $returnTo): string
    {
        $default = $this->config->routeUrl('sales/list');
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        if (str_contains($candidate, 'route=sales/list') || str_contains($candidate, 'route=sales/detail')) {
            return $candidate;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(): array
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $renewalCaseId = (int) ($_POST['renewal_case_id'] ?? 0);
        $staffUserId = (int) ($_POST['staff_user_id'] ?? 0);

        return [
            'customer_id' => $customerId,
            'contract_id' => $contractId > 0 ? $contractId : null,
            'renewal_case_id' => $renewalCaseId > 0 ? $renewalCaseId : null,
            'performance_date' => trim((string) ($_POST['performance_date'] ?? '')),
            'performance_type' => trim((string) ($_POST['performance_type'] ?? '')),
            'source_type' => $this->nullableText($_POST['source_type'] ?? null),
            'insurer_name' => $this->nullableText($_POST['insurer_name'] ?? null),
            'policy_no' => $this->nullableText($_POST['policy_no'] ?? null),
            'policy_start_date' => $this->nullableDate($_POST['policy_start_date'] ?? null),
            'application_date' => $this->nullableDate($_POST['application_date'] ?? null),
            'insurance_category' => $this->nullableText($_POST['insurance_category'] ?? null),
            'product_type' => $this->nullableText($_POST['product_type'] ?? null),
            'premium_amount' => trim((string) ($_POST['premium_amount'] ?? '0')),
            'installment_count' => $this->nullableInt($_POST['installment_count'] ?? null),
            'receipt_no' => $this->nullableText($_POST['receipt_no'] ?? null),
            'settlement_month' => $this->nullableText($_POST['settlement_month'] ?? null),
            'staff_user_id' => $staffUserId > 0 ? $staffUserId : null,
            'remark' => $this->nullableText($_POST['remark'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    private function validateInput(array &$input): array
    {
        $errors = [];

        if ((int) ($input['customer_id'] ?? 0) <= 0) {
            $errors[] = '顧客は必須です。';
        }

        $date = (string) ($input['performance_date'] ?? '');
        if (!$this->isValidDate($date)) {
            $errors[] = '実績計上日は YYYY-MM-DD 形式で指定してください。';
        }

        $type = (string) ($input['performance_type'] ?? '');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = '実績区分が不正です。';
        }

        $sourceType = (string) ($input['source_type'] ?? '');
        if ($sourceType !== '' && !in_array($sourceType, self::ALLOWED_SOURCE_TYPES, true)) {
            $errors[] = '業務区分が不正です。';
        }

        $installmentCount = $input['installment_count'] ?? null;
        if ($installmentCount !== null) {
            if (!is_int($installmentCount) || $installmentCount < 1 || $installmentCount > 255) {
                $errors[] = '分割回数は1-255の範囲で入力してください。';
            }
        }

        $policyStartDate = (string) ($input['policy_start_date'] ?? '');
        if ($policyStartDate !== '' && !$this->isValidDate($policyStartDate)) {
            $errors[] = '始期日は YYYY-MM-DD 形式で入力してください。';
        }

        $applicationDate = (string) ($input['application_date'] ?? '');
        if ($applicationDate !== '' && !$this->isValidDate($applicationDate)) {
            $errors[] = '申込日は YYYY-MM-DD 形式で入力してください。';
        }

        $premiumRaw = (string) ($input['premium_amount'] ?? '');
        if ($premiumRaw === '' || !is_numeric($premiumRaw)) {
            $errors[] = '保険料は数値で入力してください。';
        } else {
            $premium = (float) $premiumRaw;
            if ($premium < 0) {
                $errors[] = '保険料は0以上で入力してください。';
            }
            $input['premium_amount'] = (int) round($premium);
        }

        $settlementMonth = (string) ($input['settlement_month'] ?? '');
        if ($settlementMonth !== '' && !preg_match('/^\d{4}-\d{2}$/', $settlementMonth)) {
            $errors[] = '精算月は YYYY-MM 形式で入力してください。';
        }

        $staffUserId = $input['staff_user_id'] ?? null;
        if ($staffUserId !== null) {
            if (!is_int($staffUserId) || $staffUserId <= 0) {
                $errors[] = '担当者が不正です。';
            }
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return is_numeric($text) ? (int) $text : null;
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

            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $result[] = ['id' => $id, 'name' => $name];
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
            $id = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $staffUserNames
     * @return array<int, array<string, mixed>>
     */
    private function attachStaffNamesToRows(array $rows, array $staffUserNames): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $staffUserId = (int) ($row['staff_user_id'] ?? 0);
            $rows[$index]['staff_user_name'] = $staffUserId > 0 ? ($staffUserNames[$staffUserId] ?? '') : '';
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, string> $staffUserNames
     * @return array<string, mixed>|null
     */
    private function attachStaffNameToRecord(?array $record, array $staffUserNames): ?array
    {
        if (!is_array($record)) {
            return null;
        }

        $staffUserId = (int) ($record['staff_user_id'] ?? 0);
        $record['staff_user_name'] = $staffUserId > 0 ? ($staffUserNames[$staffUserId] ?? '') : '';

        return $record;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeCreateDraft(array $input): void
    {
        $this->guard->session()->setFlash('sales_create_form_input', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeCreateDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('sales_create_form_input');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeEditDraft(int $id, array $input): void
    {
        $payload = ['id' => $id, 'input' => $input];
        $this->guard->session()->setFlash('sales_edit_form_payload', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeEditDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('sales_edit_form_payload');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
