<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Sales\SalesPerformanceRepository;
use App\Domain\Sales\SalesCsvImportService;
use App\Http\Responses;
use App\Infra\TenantConnectionFactory;
use App\Presentation\SalesPerformanceListView;
use App\Security\AuthGuard;
use DateTimeImmutable;
use Throwable;

final class SalesPerformanceController
{
    private const ALLOWED_TYPES = ['new', 'renewal', 'addition', 'change', 'cancel_deduction'];

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
            'performance_date_from' => (string) ($_GET['performance_date_from'] ?? ''),
            'performance_date_to' => (string) ($_GET['performance_date_to'] ?? ''),
            'customer_name' => (string) ($_GET['customer_name'] ?? ''),
            'policy_no' => (string) ($_GET['policy_no'] ?? ''),
            'product_type' => (string) ($_GET['product_type'] ?? ''),
            'settlement_month' => (string) ($_GET['settlement_month'] ?? ''),
        ];

        $rows = [];
        $customers = [];
        $contracts = [];
        $renewalCases = [];
        $editing = null;
        $importBatch = null;
        $importRows = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $rows = $repository->search($criteria, 200);
            $customers = $repository->fetchCustomers(500);
            $contracts = $repository->fetchContracts(500);
            $renewalCases = $repository->fetchRenewalCases(500);

            $importBatchId = (int) ($_GET['import_batch_id'] ?? 0);
            if ($importBatchId > 0) {
                $importBatch = $repository->findImportBatchById($importBatchId);
                if ($importBatch !== null) {
                    $importRows = $repository->findImportRowsByBatchId($importBatchId, 500);
                }
            }

            $editId = (int) ($_GET['edit_id'] ?? 0);
            if ($editId > 0) {
                $editing = $repository->findById($editId);
                if ($editing === null) {
                    $this->guard->session()->setFlash('error', '編集対象の実績が見つかりません。');
                    Responses::redirect($this->config->routeUrl('sales/list'));
                }
            }
        } catch (Throwable) {
            $error = '実績一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(SalesPerformanceListView::render(
            $rows,
            $criteria,
            $customers,
            $contracts,
            $renewalCases,
            $editing,
            $this->config->routeUrl('sales/list'),
            $this->config->routeUrl('sales/create'),
            $this->config->routeUrl('sales/update'),
            $this->config->routeUrl('sales/delete'),
            $this->config->routeUrl('sales/import'),
            $this->config->routeUrl('dashboard'),
            $this->guard->session()->issueCsrfToken('sales_create'),
            $this->guard->session()->issueCsrfToken('sales_update'),
            $this->guard->session()->issueCsrfToken('sales_delete'),
            $this->guard->session()->issueCsrfToken('sales_import'),
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_TYPES,
            $importBatch,
            $importRows
        ));
    }

    public function import(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_import', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->guard->session()->setFlash('error', 'CSVファイルを指定してください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'upload.csv');
        if ($tmpName === '' || !is_uploaded_file($tmpName) && !is_file($tmpName)) {
            $this->guard->session()->setFlash('error', 'CSVファイルの読込に失敗しました。');
            Responses::redirect($this->config->routeUrl('sales/list'));
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

            Responses::redirect($this->config->routeUrl('sales/list') . '&import_batch_id=' . (int) ($result['batch_id'] ?? 0));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'CSV取込に失敗しました。内容を確認してください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }
    }

    public function create(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '実績を登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '実績の登録に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('sales/list'));
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '実績IDが不正です。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            Responses::redirect($this->config->routeUrl('sales/list') . '&edit_id=' . $id);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $updated = $repository->update($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '実績を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '実績の更新に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('sales/list'));
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('sales/list'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '実績IDが不正です。');
            Responses::redirect($this->config->routeUrl('sales/list'));
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
            'insurance_category' => $this->nullableText($_POST['insurance_category'] ?? null),
            'product_type' => $this->nullableText($_POST['product_type'] ?? null),
            'premium_amount' => trim((string) ($_POST['premium_amount'] ?? '0')),
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
}