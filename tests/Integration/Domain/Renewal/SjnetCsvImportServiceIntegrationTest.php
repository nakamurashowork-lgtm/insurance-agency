<?php
declare(strict_types=1);

namespace Tests\Integration\Domain\Renewal;

use App\Domain\Renewal\SjnetCsvImportService;
use DateTimeImmutable;
use Tests\Helpers\SjnetCsvBuilder;
use Tests\Integration\DatabaseTestCase;

/**
 * SjnetCsvImportService の統合テスト（DB あり）
 *
 * import() を実際の MySQL DB（xs000001_test）に対して実行し、
 * CSV 取込の全工程（顧客解決・契約登録・満期案件登録・バッチ記録）を検証する。
 *
 * 前提:
 *  - tests/db/setup_test_db.sh で xs000001_test スキーマが存在すること
 *  - setUp で全テーブルが TRUNCATE されること（DatabaseTestCase）
 */
final class SjnetCsvImportServiceIntegrationTest extends DatabaseTestCase
{
    /** テスト固定の取込日 */
    private const IMPORT_DATE = '2026-04-01';

    /** テスト固定のポリシー終期日 */
    private const MATURITY_DATE = '2026-04-30';

    /** テスト固定の始期日 */
    private const START_DATE = '2025-05-01';

    private function makeService(?DateTimeImmutable $importDate = null): SjnetCsvImportService
    {
        return new SjnetCsvImportService(
            $this->pdo,
            self::TEST_EXECUTED_BY,
            $importDate ?? new DateTimeImmutable(self::IMPORT_DATE)
        );
    }

    // =========================================================
    // Step 2: バッチ境界 (S1, S2)
    // =========================================================

    /**
     * S1: 空ファイル（0 バイト）を取込む → バッチ正常終了、行数 0
     */
    public function testImport_EmptyFile_BatchSucceedsWithZeroRows(): void
    {
        // Arrange
        $path    = $this->writeTempCsv('');
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'empty.csv');

        // Assert: カウンタがすべて 0
        $this->assertSame(0, $result['total'],  'total = 0');
        $this->assertSame(0, $result['insert'], 'insert = 0');
        $this->assertSame(0, $result['update'], 'update = 0');
        $this->assertSame(0, $result['error'],  'error = 0');
        $this->assertSame(0, $result['skip'],   'skip = 0');

        // Assert: バッチが success で完了
        $batch = $this->getLatestBatch();
        $this->assertNotNull($batch);
        $this->assertSame('success',    $batch['import_status'],  'import_status = success');
        $this->assertSame(0, (int)      $batch['total_row_count'], 'total_row_count = 0');

        // Assert: DB に顧客・契約が INSERT されていないこと
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());
    }

    /**
     * S2: ヘッダ行のみ（データ 0 行）を取込む → バッチ正常終了、行数 0
     */
    public function testImport_HeaderOnly_BatchSucceedsWithZeroRows(): void
    {
        // Arrange: ヘッダ行のみの CSV（データ行なし）
        $path = $this->writeTempCsv(SjnetCsvBuilder::sheet([])->toCsvString());
        $service    = $this->makeService();

        // Act
        $result = $service->import($path, 'header_only.csv');

        // Assert: カウンタがすべて 0
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['insert']);
        $this->assertSame(0, $result['error']);

        // Assert: バッチが success で完了
        $batch = $this->getLatestBatch();
        $this->assertNotNull($batch);
        $this->assertSame('success', $batch['import_status']);
        $this->assertSame(0, (int) $batch['total_row_count']);
    }

    // =========================================================
    // Step 3: スキップ・エラー条件 (SK1-SK4)
    // =========================================================

    /**
     * SK1: 証券番号（S列）が空の行 → row_status='skip'、顧客・契約 INSERT なし
     */
    public function testProcessRow_EmptyPolicyNo_Skipped(): void
    {
        // Arrange
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()->asSkipRowNoPolicyNo()->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'skip_no_policy.csv');

        // Assert: カウンタ
        $this->assertSame(1, $result['total'],   'total = 1');
        $this->assertSame(1, $result['skip'],    'skip = 1');
        $this->assertSame(0, $result['error'],   'error = 0');
        $this->assertSame(0, $result['insert'],  'insert = 0');

        // Assert: DB に何も INSERT されていないこと
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());

        // Assert: import_row に skip として記録されること
        $batch = $this->getLatestBatch();
        $rows  = $this->getRowsByBatch((int) $batch['id']);
        $this->assertCount(1, $rows);
        $this->assertSame('skip', $rows[0]['row_status']);
        $this->assertStringContainsString('証券番号', (string) $rows[0]['error_message']);
    }

    /**
     * SK2: 顧客名（D列）が空の行 → row_status='skip'、証券番号は記録される
     */
    public function testProcessRow_EmptyCustomerName_Skipped(): void
    {
        // Arrange
        $policyNo = 'SK2-P001';
        $path     = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo($policyNo)
                ->asSkipRowNoCustomerName()
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'skip_no_customer.csv');

        // Assert: カウンタ
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['skip']);
        $this->assertSame(0, $result['error']);

        // Assert: import_row に証券番号が記録され、skip であること
        $rows = $this->getRowsByBatch((int) $this->getLatestBatch()['id']);
        $this->assertSame('skip',   $rows[0]['row_status']);
        $this->assertSame($policyNo, $rows[0]['policy_no']);
    }

    /**
     * SK3: 保険終期（Q列）が空の行 → row_status='skip'
     */
    public function testProcessRow_EmptyEndDate_Skipped(): void
    {
        // Arrange
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()->asSkipRowNoEndDate()->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'skip_no_enddate.csv');

        // Assert
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['skip']);
        $this->assertSame(0, $result['error']);

        $rows = $this->getRowsByBatch((int) $this->getLatestBatch()['id']);
        $this->assertSame('skip', $rows[0]['row_status']);
        $this->assertStringContainsString('保険終期', (string) $rows[0]['error_message']);
    }

    /**
     * SK4: 保険終期（Q列）が不正日付（'2026/13/01'）→ row_status='error'、counters['error']++
     *
     * スキップではなくエラーとして記録される（valid++ 後に parseDate が null を返すため）。
     */
    public function testProcessRow_InvalidEndDate_RecordedAsError(): void
    {
        // Arrange
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('SK4-P001')
                ->withCustomerName('テスト顧客')
                ->withEndDate('2026/13/01') // 月=13 は不正
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'invalid_date.csv');

        // Assert: error カウンタが増える（skip ではなく error）
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['error']);
        $this->assertSame(0, $result['skip']);

        // Assert: import_row が error として記録される
        $rows = $this->getRowsByBatch((int) $this->getLatestBatch()['id']);
        $this->assertSame('error', $rows[0]['row_status']);
        $this->assertStringContainsString('日付', (string) $rows[0]['error_message']);

        // Assert: 顧客・契約は INSERT されていないこと
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());
    }

    // =========================================================
    // Step 4: resolveStaff (R1-R3)
    // =========================================================

    /**
     * R1: m_staff に sjnet_code が存在 → assigned_staff_id にそのスタッフの id が入る
     */
    public function testResolveStaff_ValidAgencyCode_SetsAssignedStaffId(): void
    {
        // Arrange: m_staff にスタッフを登録
        $agencyCode = 'A001';
        $staffId    = $this->createStaff(['sjnet_code' => $agencyCode, 'is_active' => 1]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('R1-P001')
                ->withCustomerName('担当者テスト顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->withAgencyCode($agencyCode)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'staff_resolved.csv');

        // Assert: 取込成功
        $this->assertSame(0, $result['error']);
        $this->assertSame(1, $result['insert']);

        // Assert: t_renewal_case.assigned_staff_id にスタッフ id が入ること
        $renewalCase = $this->pdo->query('SELECT assigned_staff_id FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertIsArray($renewalCase);
        $this->assertSame($staffId, (int) $renewalCase['assigned_staff_id']);

        // Assert: import_row の staff_mapping_status が 'resolved'
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertSame('resolved', $rows[0]['staff_mapping_status']);
    }

    /**
     * R2: sjnet_code が m_staff に存在しない → staff_mapping_status='unresolved'、契約は INSERT される
     */
    public function testResolveStaff_UnknownAgencyCode_ContractStillInserted(): void
    {
        // Arrange: m_staff には登録がない代理店コード
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('R2-P001')
                ->withCustomerName('未解決スタッフ顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->withAgencyCode('UNKNOWN999')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'staff_unresolved.csv');

        // Assert: エラーにならず insert される
        $this->assertSame(0, $result['error']);
        $this->assertSame(1, $result['insert']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());

        // Assert: import_row の staff_mapping_status が 'unresolved'
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertSame('unresolved', $rows[0]['staff_mapping_status']);

        // Assert: assigned_staff_id は NULL
        $renewalCase = $this->pdo->query('SELECT assigned_staff_id FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertNull($renewalCase['assigned_staff_id']);
    }

    /**
     * R3: 代理店コード（AR列）が空 → staff_mapping_status=null、契約は正常 INSERT
     */
    public function testResolveStaff_EmptyAgencyCode_ContractStillInserted(): void
    {
        // Arrange: 代理店コードなし
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('R3-P001')
                ->withCustomerName('コードなし顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->withAgencyCode('')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'staff_empty_code.csv');

        // Assert: エラーにならず insert される
        $this->assertSame(0, $result['error']);
        $this->assertSame(1, $result['insert']);

        // Assert: import_row の staff_mapping_status が null
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertNull($rows[0]['staff_mapping_status']);
    }

    /**
     * R4: sjnet_code が存在するが is_active=0 のスタッフ → staff_mapping_status='inactive'、
     *     assigned_staff_id=NULL、契約は正常 INSERT される
     */
    public function testResolveStaff_InactiveStaff_MappingStatusIsInactive(): void
    {
        // Arrange: is_active=0 のスタッフを登録
        $this->createStaff(['sjnet_code' => 'INACTIVE001', 'is_active' => 0]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('R4-P001')
                ->withCustomerName('非アクティブスタッフ顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->withAgencyCode('INACTIVE001')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'staff_inactive.csv');

        // Assert: エラーにならず INSERT される
        $this->assertSame(0, $result['error'],  'エラーにならない');
        $this->assertSame(1, $result['insert'], 'INSERT される');

        // Assert: import_row の staff_mapping_status が 'inactive'
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertSame('inactive', $rows[0]['staff_mapping_status']);

        // Assert: assigned_staff_id は NULL
        $renewalCase = $this->pdo->query('SELECT assigned_staff_id FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertIsArray($renewalCase);
        $this->assertNull($renewalCase['assigned_staff_id'],
            '非アクティブスタッフの assigned_staff_id は NULL');
    }

    // =========================================================
    // Step 5: 顧客解決 (A1, A2, A5-1, A5-2)
    // =========================================================

    /**
     * A1: 顧客が存在しない → m_customer に INSERT される。全フィールドを検証
     */
    public function testResolveCustomer_NewCustomer_InsertsCorrectFields(): void
    {
        // Arrange
        $customerName = '山田太郎';
        $postalCode   = '100-0001';
        $address1     = '東京都千代田区千代田1-1';
        $phone        = '03-1234-5678';

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('A1-P001')
                ->withCustomerName($customerName)
                ->withEndDate(self::MATURITY_DATE)
                ->withPostalCode($postalCode)
                ->withAddress1($address1)
                ->withPhone($phone)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'new_customer.csv');

        // Assert: customer_insert カウンタ
        $this->assertSame(1, $result['customer_insert']);

        // Assert: m_customer の各フィールド
        $stmt = $this->pdo->query('SELECT * FROM m_customer LIMIT 1');
        $customer = $stmt->fetch();
        $this->assertIsArray($customer);
        $this->assertSame($customerName,  $customer['customer_name']);
        $this->assertSame('individual',   $customer['customer_type']);
        $this->assertSame('active',       $customer['status']);
        $this->assertSame($postalCode,    $customer['postal_code']);
        $this->assertSame($address1,      $customer['address1']);
        $this->assertSame($phone,         $customer['phone']);
        $this->assertSame(0,              (int) $customer['is_deleted']);
        $this->assertSame(self::TEST_EXECUTED_BY, (int) $customer['created_by']);
    }

    /**
     * A2: 同名顧客が 1 件存在 → INSERT せず既存 id を再利用する
     */
    public function testResolveCustomer_ExistingCustomer_ReusesId(): void
    {
        // Arrange: 事前に顧客を登録
        $customerName = '既存顧客太郎';
        $existingId   = $this->createCustomer(['customer_name' => $customerName]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('A2-P001')
                ->withCustomerName($customerName)
                ->withEndDate(self::MATURITY_DATE)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'existing_customer.csv');

        // Assert: customer_insert カウンタが増えていないこと
        $this->assertSame(0, $result['customer_insert']);

        // Assert: m_customer が 1 件のままで、id が同じであること
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());
        $contract = $this->pdo->query('SELECT customer_id FROM t_contract LIMIT 1')->fetch();
        $this->assertSame($existingId, (int) $contract['customer_id']);
    }

    /**
     * A5-1: 同名顧客が 2 件以上存在 → row_status='unlinked'、契約・満期案件は customer_id=NULL で INSERT される
     *
     * 仕様 §9 廃止後の現行挙動: ambiguous_customer はエラーではなく未リンク取込に変更された。
     * - customer_id=NULL のまま契約・満期案件を INSERT する
     * - unlinked カウンタが増加する（error カウンタは増加しない）
     */
    public function testResolveCustomer_AmbiguousCustomer_UnlinkedNotError(): void
    {
        // Arrange: 同名顧客を 2 件登録
        $duplicateName = '重複田一郎';
        $this->createCustomer(['customer_name' => $duplicateName]);
        $this->createCustomer(['customer_name' => $duplicateName]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('A5-P001')
                ->withCustomerName($duplicateName)
                ->withEndDate(self::MATURITY_DATE)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'ambiguous.csv');

        // Assert: unlinked カウンタが増え、error カウンタは増えない
        $this->assertSame(1, $result['unlinked'], 'unlinked カウンタが 1 増える');
        $this->assertSame(0, $result['error'],    'error カウンタは増えない');

        // Assert: 契約・満期案件は customer_id=NULL で INSERT されること
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn(),
            '契約は INSERT される');
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM t_renewal_case')->fetchColumn(),
            '満期案件は INSERT される');
        $contract = $this->pdo->query('SELECT customer_id FROM t_contract LIMIT 1')->fetch();
        $this->assertNull($contract['customer_id'], 'customer_id は NULL（未リンク）');

        // Assert: import_row の row_status が 'unlinked'
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertSame('unlinked', $rows[0]['row_status']);
    }

    /**
     * A5-2: 同一 CSV 内で同名 2 行 → 1 行目が顧客 INSERT、2 行目が再利用
     *
     * autocommit ON のため 1 行目の INSERT は即座にコミットされ、
     * 2 行目の SELECT で 1 件ヒットして再利用される。
     */
    public function testResolveCustomer_SameNameTwiceInCsv_SecondRowReusesFirstCustomer(): void
    {
        // Arrange: 同名顧客 2 行（証券番号は異なる）
        $customerName = '重複テスト次郎';
        $csv          = SjnetCsvBuilder::sheet([
            SjnetCsvBuilder::row()->withPolicyNo('A52-P001')->withCustomerName($customerName)->withEndDate('2026/04/30'),
            SjnetCsvBuilder::row()->withPolicyNo('A52-P002')->withCustomerName($customerName)->withEndDate('2026/05/31'),
        ])->toCsvString();
        $path    = $this->writeTempCsv($csv);
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'same_name_twice.csv');

        // Assert: customer_insert=1（2 行目は再利用のため増えない）
        $this->assertSame(1, $result['customer_insert']);
        $this->assertSame(2, $result['insert']);
        $this->assertSame(0, $result['error']);

        // Assert: m_customer は 1 件のみ
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());

        // Assert: t_contract は 2 件、両方とも同じ customer_id を参照
        $contracts = $this->pdo->query('SELECT customer_id FROM t_contract ORDER BY id')->fetchAll();
        $this->assertCount(2, $contracts);
        $this->assertSame($contracts[0]['customer_id'], $contracts[1]['customer_id']);
    }

    /**
     * A6: CSV 生年月日なし ＋ DB に同名・birth_date NULL 顧客 → 1件一致（新規 INSERT されない）
     *
     * 顧客照合は「顧客名 + birth_date IS NULL」で行われる。
     * CSV に生年月日が入っていない場合でも、DB 側が birth_date=NULL の顧客に正しく一致する。
     */
    public function testResolveCustomer_EmptyBirthDateInCsv_MatchesNullBirthDateCustomer(): void
    {
        // Arrange: birth_date=NULL の既存顧客を登録
        $existingId = $this->createCustomer(['customer_name' => 'A6テスト顧客']);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('A6-P001')
                ->withCustomerName('A6テスト顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->toCsvString() // birth_date 列は空のまま
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'a6_null_birth.csv');

        // Assert: 新規 INSERT されない
        $this->assertSame(0, $result['customer_insert'], '既存顧客（birth_date=NULL）に一致するため INSERT されない');
        $this->assertSame(1, $result['insert']);

        // Assert: 契約が既存顧客に紐づいている
        $contract = $this->pdo->query('SELECT customer_id FROM t_contract LIMIT 1')->fetch();
        $this->assertSame($existingId, (int) $contract['customer_id']);
    }

    /**
     * A7: CSV 生年月日なし ＋ DB に同名・birth_date 有り顧客 → 0件一致（新規 INSERT される）
     *
     * CSV の生年月日が空 = birth_date IS NULL で照合するため、
     * DB 側に birth_date が設定されている同名顧客は一致しない → 新規 INSERT。
     */
    public function testResolveCustomer_EmptyBirthDateInCsv_DoesNotMatchNonNullBirthDateCustomer(): void
    {
        // Arrange: birth_date=1990-01-01 の既存顧客を登録
        $this->createCustomer(['customer_name' => 'A7テスト顧客', 'birth_date' => '1990-01-01']);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('A7-P001')
                ->withCustomerName('A7テスト顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->toCsvString() // birth_date 列は空のまま
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'a7_birth_mismatch.csv');

        // Assert: 新規 INSERT される（DB の birth_date=1990-01-01 とは一致しない）
        $this->assertSame(1, $result['customer_insert'], '既存顧客の birth_date が非 NULL なため一致せず、新規 INSERT される');

        // Assert: m_customer が 2 件（既存 + 新規）
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());

        // Assert: 新規顧客の birth_date は NULL
        $newCustomer = $this->pdo->query(
            "SELECT birth_date FROM m_customer ORDER BY id DESC LIMIT 1"
        )->fetch();
        $this->assertNull($newCustomer['birth_date'], '生年月日なし CSV で新規登録された顧客の birth_date は NULL');
    }

    // =========================================================
    // Step 6: 契約 (B1, B2, B3-1〜3, B5)
    // =========================================================

    /**
     * B1: policy_no + policy_end_date が存在しない → t_contract に INSERT される。全フィールドを検証
     */
    public function testUpsertContract_NewContract_InsertsWithCorrectFields(): void
    {
        // Arrange
        $policyNo    = 'B1-P001';
        $productType = '自動車';
        $premium     = '120,000';

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo($policyNo)
                ->withCustomerName('新規契約顧客')
                ->withStartDate(self::START_DATE)
                ->withEndDate(self::MATURITY_DATE)
                ->withProductType($productType)
                ->withPremiumAmount($premium)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'new_contract.csv');

        // Assert: insert カウンタ
        $this->assertSame(1, $result['insert']);
        $this->assertSame(0, $result['update']);

        // Assert: t_contract の各フィールド
        $contract = $this->pdo->query('SELECT * FROM t_contract LIMIT 1')->fetch();
        $this->assertIsArray($contract);
        $this->assertSame($policyNo,                     $contract['policy_no']);
        $this->assertSame('2025-05-01',                  $contract['policy_start_date']);
        $this->assertSame('2026-04-30',                  $contract['policy_end_date']);
        $this->assertSame($productType,                  $contract['product_type']);
        $this->assertSame(120000,                        (int) $contract['premium_amount']);
        $this->assertSame('active',                      $contract['status']);
        $this->assertNotNull($contract['last_sjnet_imported_at']);
        $this->assertSame(self::TEST_EXECUTED_BY,        (int) $contract['created_by']);
    }

    /**
     * B2: policy_no + policy_end_date が既存 → UPDATE される。更新対象フィールドを検証
     *
     * 注意: カウンタを update=1 にするには満期案件も事前登録が必要。
     * contract=UPDATE かつ renewalCase=UPDATE → update++ のまま。
     * contract=UPDATE かつ renewalCase=INSERT → insert++ に変換される（Phase 0 文書済み）。
     */
    public function testUpsertContract_ExistingContract_UpdatesMutableFields(): void
    {
        // Arrange: 契約と満期案件を事前登録（両方 UPDATE にするため）
        $policyNo   = 'B2-P001';
        $maturity   = '2026-04-30';
        $customerId = $this->createCustomer(['customer_name' => '更新テスト顧客']);
        $contractId = $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => $policyNo,
            'policy_end_date' => $maturity,
            'product_type'    => '火災',
            'premium_amount'  => 50000,
        ]);
        // 満期案件も事前登録 → 取込時に renewalCase=UPDATE になり、counter が update のまま
        $this->createRenewalCase([
            'contract_id'  => $contractId,
            'maturity_date' => $maturity,
        ]);

        // 1回目と異なる値で CSV を組む
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo($policyNo)
                ->withCustomerName('更新テスト顧客')
                ->withStartDate('2025-06-01')  // 変更
                ->withEndDate($maturity)        // 同じ終期 = 同一年度
                ->withProductType('自動車')     // 変更
                ->withPremiumAmount('99,000')  // 変更
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'update_contract.csv');

        // Assert: update カウンタ（contract=UPDATE + renewalCase=UPDATE）
        $this->assertSame(0, $result['insert']);
        $this->assertSame(1, $result['update']);

        // Assert: 更新されたフィールドが変わっていること
        $contract = $this->pdo->query('SELECT * FROM t_contract LIMIT 1')->fetch();
        $this->assertSame('2025-06-01', $contract['policy_start_date'], 'policy_start_date が更新される');
        $this->assertSame('自動車',      $contract['product_type'],       'product_type が更新される');
        $this->assertSame(99000,        (int) $contract['premium_amount'], 'premium_amount が更新される');
        $this->assertNotNull($contract['last_sjnet_imported_at'],         'last_sjnet_imported_at が更新される');
    }

    /**
     * B3-1: UPDATE 時に customer_id は変わらない（保護カラム）
     */
    public function testUpsertContract_Update_DoesNotChangeCustomerId(): void
    {
        // Arrange: 元の顧客と契約を登録
        $originalCustomerId = $this->createCustomer(['customer_name' => '元の顧客']);
        $this->createContract([
            'customer_id'     => $originalCustomerId,
            'policy_no'       => 'B31-P001',
            'policy_end_date' => '2026-04-30',
        ]);
        // 同名の別顧客を登録（CSV には別顧客が来た想定）
        $this->createCustomer(['customer_name' => '別の顧客']);

        // CSV には「別の顧客」の名前で来るが、policy_no + policy_end_date でヒット → UPDATE
        // ただし実際の挙動は「CSV の customer_name で resolveCustomer する」→ 別顧客 id が返る
        // そして upsertContract UPDATE では customer_id を使わない（元の id を保持）
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('B31-P001')
                ->withCustomerName('別の顧客')    // 別の顧客名で来た
                ->withEndDate('2026-04-30')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $service->import($path, 'protect_customer_id.csv');

        // Assert: t_contract.customer_id が元の顧客 id のまま
        $contract = $this->pdo->query('SELECT customer_id FROM t_contract LIMIT 1')->fetch();
        $this->assertSame($originalCustomerId, (int) $contract['customer_id'],
            'UPDATE 時に customer_id は変わらない');
    }

    /**
     * B5: parsePremium が負値で null を返した場合 → premium_amount に 0 が入る（エラーにならない）
     *
     * 仕様上の意図: parsePremium コメントに「呼び出し側で 0 へフォールバックさせる」と明記。
     * ただし UI にも import_row にも警告が出ない（サイレントなデータ変換）。
     * Phase 5 指摘事項: 「負値保険料フォールバックの通知なし問題」
     */
    public function testUpsertContract_NegativePremium_StoredAsZero_NoError(): void
    {
        // Arrange: 負値の保険料
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('B5-P001')
                ->withCustomerName('負保険料顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->withPremiumAmount('-5,000') // 負値
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'negative_premium.csv');

        // Assert: エラーにならず insert される（row_status='insert'）
        $this->assertSame(0, $result['error'],  'エラーにならない');
        $this->assertSame(1, $result['insert'], 'insert される');

        // Assert: premium_amount = 0 に格納される（null ではなく 0）
        $contract = $this->pdo->query('SELECT premium_amount FROM t_contract LIMIT 1')->fetch();
        $this->assertSame(0, (int) $contract['premium_amount'],
            '負値は 0 にフォールバックされる（サイレント変換）');

        // Assert: import_row の row_status が 'insert'（警告なし）
        $rows = $this->getRowsByBatch((int) $result['batch_id']);
        $this->assertSame('insert', $rows[0]['row_status'], 'import_row も insert として記録される');
        $this->assertNull($rows[0]['error_message'], 'error_message は null（警告なし）');
    }

    // =========================================================
    // Step 7: 満期案件 (C1, C3-1〜3)
    // =========================================================

    /**
     * C1: contract_id + maturity_date が存在しない → t_renewal_case に INSERT される
     *     case_status='not_started'、office_staff_id が t_contract から引き継がれる
     */
    public function testUpsertRenewalCase_NewCase_InsertsWithNotStartedStatus(): void
    {
        // Arrange: office_staff_id を持つ契約を事前登録
        $officeStaffId = $this->createStaff(['is_office' => 1]);
        $customerId    = $this->createCustomer(['customer_name' => '満期案件新規顧客']);
        $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => 'C1-P001',
            'policy_end_date' => '2026-04-30',
            'office_staff_id' => $officeStaffId,
        ]);

        // CSV 取込で同じ policy_no + policy_end_date → contract UPDATE → 満期案件 INSERT
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('C1-P001')
                ->withCustomerName('満期案件新規顧客')
                ->withEndDate('2026/04/30')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'new_renewal_case.csv');

        // Assert: 満期案件が INSERT されたため insert カウンタが立つ
        $this->assertSame(1, $result['insert'], '契約 update だが満期案件 insert → insert カウンタ');

        // Assert: t_renewal_case のフィールド
        $renewalCase = $this->pdo->query('SELECT * FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertIsArray($renewalCase);
        $this->assertSame('not_started',  $renewalCase['case_status'],     'case_status = not_started');
        $this->assertSame('2026-04-30',   $renewalCase['maturity_date'],    'maturity_date が正しい');
        $this->assertSame($officeStaffId, (int) $renewalCase['office_staff_id'], 'office_staff_id が契約から引き継がれる');
    }

    /**
     * C3-1: 既存の満期案件 → UPDATE 時に case_status は変更されない（保護カラム）
     */
    public function testUpsertRenewalCase_ExistingCase_DoesNotOverwriteCaseStatus(): void
    {
        // Arrange: case_status='sj_requested' の満期案件を事前登録
        $customerId = $this->createCustomer(['customer_name' => 'ステータス保護顧客']);
        $contractId = $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => 'C31-P001',
            'policy_end_date' => '2026-04-30',
        ]);
        $this->createRenewalCase([
            'contract_id'  => $contractId,
            'maturity_date' => '2026-04-30',
            'case_status'   => 'sj_requested', // 進行中ステータス
        ]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('C31-P001')
                ->withCustomerName('ステータス保護顧客')
                ->withEndDate('2026/04/30')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $service->import($path, 'protect_case_status.csv');

        // Assert: case_status が 'sj_requested' のまま変わっていないこと
        $renewalCase = $this->pdo->query('SELECT case_status FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertSame('sj_requested', $renewalCase['case_status'],
            'UPDATE 時に case_status は変更されない');
    }

    /**
     * C3-2: 既存案件に assigned_staff_id が設定済みでも、resolvedUserId があれば常に上書きする
     *       （1年ごとの再取込で担当者が変わりうるため）
     */
    public function testUpsertRenewalCase_ExistingCase_OverwritesAssignedStaffIfResolved(): void
    {
        // Arrange
        $existingStaffId = $this->createStaff(['sjnet_code' => null]);
        $newStaffId      = $this->createStaff(['sjnet_code' => 'C32STAFF']);
        $customerId      = $this->createCustomer(['customer_name' => '担当上書き顧客']);
        $contractId      = $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => 'C32-P001',
            'policy_end_date' => '2026-04-30',
        ]);
        $this->createRenewalCase([
            'contract_id'       => $contractId,
            'maturity_date'     => '2026-04-30',
            'assigned_staff_id' => $existingStaffId, // 既存担当者
        ]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('C32-P001')
                ->withCustomerName('担当上書き顧客')
                ->withEndDate('2026/04/30')
                ->withAgencyCode('C32STAFF') // 新しい担当者コード
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $service->import($path, 'overwrite_assigned_staff.csv');

        // Assert: assigned_staff_id が新しいスタッフ id に更新されていること
        $renewalCase = $this->pdo->query('SELECT assigned_staff_id FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertSame($newStaffId, (int) $renewalCase['assigned_staff_id'],
            'resolvedUserId がある場合は既存 assigned_staff_id を上書きする');
        $this->assertNotSame($existingStaffId, (int) $renewalCase['assigned_staff_id']);
    }

    /**
     * C3-3: 既存案件の assigned_staff_id が NULL → resolvedUserId で上書きされる
     */
    public function testUpsertRenewalCase_ExistingCase_SetsAssignedStaffIfNull(): void
    {
        // Arrange
        $newStaffId = $this->createStaff(['sjnet_code' => 'C33STAFF']);
        $customerId = $this->createCustomer(['customer_name' => 'NULL担当案件顧客']);
        $contractId = $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => 'C33-P001',
            'policy_end_date' => '2026-04-30',
        ]);
        $this->createRenewalCase([
            'contract_id'       => $contractId,
            'maturity_date'     => '2026-04-30',
            'assigned_staff_id' => null, // NULL
        ]);

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('C33-P001')
                ->withCustomerName('NULL担当案件顧客')
                ->withEndDate('2026/04/30')
                ->withAgencyCode('C33STAFF')
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $service->import($path, 'set_assigned_staff.csv');

        // Assert: assigned_staff_id が新スタッフ id に更新されていること
        $renewalCase = $this->pdo->query('SELECT assigned_staff_id FROM t_renewal_case LIMIT 1')->fetch();
        $this->assertSame($newStaffId, (int) $renewalCase['assigned_staff_id'],
            'NULL の assigned_staff_id は resolved_user_id で上書きされる');
    }

    // =========================================================
    // =========================================================
    // Step 9: 既存顧客の不更新（仕様確認済み）
    // =========================================================

    /**
     * A3: 既存顧客に対して CSV の住所・連絡先は更新されない（確定仕様）
     *
     * 仕様: 既存顧客の住所変更は画面から営業が対応する。
     *       CSV 取込で既存顧客情報を上書きしない。
     *       新規登録時のみ CSV の値（郵便番号・住所・電話番号）が反映される。
     */
    public function testResolveCustomer_ExistingCustomer_DoesNotUpdateAddress(): void
    {
        // Arrange: 既存顧客を登録（CSV とは異なる住所・電話番号を設定）
        $customerName       = '住所保護テスト顧客';
        $originalPostal     = '100-0001';
        $originalAddress1   = '東京都千代田区千代田1-1（元の住所）';
        $originalPhone      = '03-0000-0000';

        $existingCustomerId = $this->createCustomer([
            'customer_name' => $customerName,
            'postal_code'   => $originalPostal,
            'address1'      => $originalAddress1,
            'phone'         => $originalPhone,
        ]);

        // CSV には異なる住所・電話番号を持つ同名顧客が来る
        $csvPostal   = '900-0001';
        $csvAddress1 = '沖縄県那覇市泊1-1（CSV の新しい住所）';
        $csvPhone    = '098-999-9999';

        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('YA3-P001')
                ->withCustomerName($customerName)
                ->withEndDate(self::MATURITY_DATE)
                ->withPostalCode($csvPostal)
                ->withAddress1($csvAddress1)
                ->withPhone($csvPhone)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'ya3_address_not_updated.csv');

        // Assert: customer_insert カウンタが増えないこと（新規 INSERT されていない）
        $this->assertSame(0, $result['customer_insert'], '既存顧客は INSERT されない');

        // Assert: m_customer が 1 件のまま
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn());

        // Assert: 各保護列が元の値のまま変わっていないこと
        $stmt = $this->pdo->prepare('SELECT * FROM m_customer WHERE id = :id');
        $stmt->execute(['id' => $existingCustomerId]);
        $customer = $stmt->fetch();

        $this->assertIsArray($customer);
        $this->assertSame(
            $originalPostal,
            $customer['postal_code'],
            'postal_code は CSV の値で上書きされない（住所変更は画面から対応）'
        );
        $this->assertSame(
            $originalAddress1,
            $customer['address1'],
            'address1 は CSV の値で上書きされない（住所変更は画面から対応）'
        );
        $this->assertSame(
            $originalPhone,
            $customer['phone'],
            'phone は CSV の値で上書きされない（住所変更は画面から対応）'
        );
    }

    /**
     * Y-D2（修正後）: 契約 INSERT が UNIQUE 違反で失敗しても orphan customer は残らず、
     *                  エラー行として記録してバッチは継続する
     *
     * 失敗の引き起こし方:
     *   soft-deleted 契約（is_deleted=1）を同一 (policy_no, policy_end_date) で事前作成。
     *   CSV 取込時に upsertContract の SELECT は is_deleted=0 でフィルタするためミス判定し、
     *   INSERT を試みる → UNIQUE KEY uq_t_contract_02(policy_no, policy_end_date) で
     *   PDOException 発生 → 行単位ロールバック → 顧客 INSERT も取り消される。
     */
    public function testImport_ContractInsertFails_RollsBackCustomer_ContinuesBatch(): void
    {
        // Arrange: soft-deleted 契約（同一 policy_no + policy_end_date）を事前作成
        $policyNo     = 'YD2-P001';
        $maturityDate = '2026-04-30';

        $dummyCustomerId = $this->createCustomer(['customer_name' => 'ダミー既存顧客']);
        $this->createContract([
            'customer_id'     => $dummyCustomerId,
            'policy_no'       => $policyNo,
            'policy_end_date' => $maturityDate,
            'is_deleted'      => 1, // soft-deleted: is_deleted=0 フィルタをすり抜けて UNIQUE 違反を起こす
        ]);

        // CSV: 失敗行（UNIQUE 違反） + 正常行
        $newCustomerName = '孤立顧客テスト太郎';
        $path = $this->writeTempCsv(
            SjnetCsvBuilder::sheet([
                SjnetCsvBuilder::row()
                    ->withPolicyNo($policyNo)
                    ->withCustomerName($newCustomerName)
                    ->withEndDate($maturityDate),  // UNIQUE 違反 → error
                SjnetCsvBuilder::row()
                    ->withPolicyNo('YD2-P002')
                    ->withCustomerName('正常顧客')
                    ->withEndDate($maturityDate),  // 正常 → insert
            ])->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'yd2_rollback.csv');

        // Assert: 例外はスローされずに返ること
        $this->assertSame(2, $result['total'],  'total = 2');
        $this->assertSame(1, $result['error'],  'エラー行 = 1');
        $this->assertSame(1, $result['insert'], '正常行 = 1');

        // Assert: orphan customer が残っていないこと（ロールバックで取り消し）
        $stmt = $this->pdo->prepare('SELECT id FROM m_customer WHERE customer_name = :name');
        $stmt->execute(['name' => $newCustomerName]);
        $this->assertFalse($stmt->fetch(), 'orphan customer は残らない（行単位ロールバック）');

        // Assert: エラー行が t_sjnet_import_row に記録されていること
        $batch = $this->getLatestBatch();
        $this->assertNotNull($batch);
        $rows = $this->getRowsByBatch((int) $batch['id']);
        $this->assertCount(2, $rows, '2行ともログに記録されること');
        $this->assertSame('error',  $rows[0]['row_status'], '1行目は error');
        $this->assertSame('insert', $rows[1]['row_status'], '2行目は insert');

        // Assert: バッチは partial（エラーあり・成功あり）
        $this->assertSame('partial', $batch['import_status'], 'バッチは partial で完了');
    }

    // =========================================================
    // Step 8: バッチ全体 (D1, D3, D4-1, D4-2)
    // =========================================================

    /**
     * D1: 100 行中 1 行が不正日付エラー → 残り 99 行が処理される
     *
     * ambiguous_customer は仕様 §9 廃止後に unlinked に変更されたため、
     * エラー行の生成には SK4 相当の不正日付を使用する。
     */
    public function testImport_OneErrorRow_OtherRowsContinue(): void
    {
        // 1 行目がエラー（不正日付）、残り 99 行は正常
        $rows = [
            SjnetCsvBuilder::row()
                ->withPolicyNo('D1-ERR')
                ->withCustomerName('エラー顧客')
                ->withEndDate('2026/13/01'), // 月=13 は不正 → error
        ];
        for ($i = 1; $i <= 99; $i++) {
            $rows[] = SjnetCsvBuilder::row()
                ->withPolicyNo(sprintf('D1-P%03d', $i))
                ->withCustomerName(sprintf('正常顧客%03d', $i))
                ->withEndDate('2026/04/30');
        }
        $path    = $this->writeTempCsv(SjnetCsvBuilder::sheet($rows)->toCsvString());
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'd1_100rows.csv');

        // Assert: 1 件エラー、99 件は insert/update
        $this->assertSame(100, $result['total']);
        $this->assertSame(1,   $result['error']);
        $this->assertSame(99,  $result['insert'] + $result['update']);

        // Assert: バッチは partial（エラーあり、かつ成功もある）
        $batch = $this->getLatestBatch();
        $this->assertSame('partial', $batch['import_status']);
    }

    /**
     * D3: 同一 CSV を 2 回取込 → 2 回目は全件 update、新 batch_id が生成される
     *
     * 単発実行下の冪等性のみ検証。並行実行下の冪等性は Q2（UNIQUE 制約追加可否）の回答待ち。
     */
    public function testImport_Idempotency_SecondImportProducesAllUpdates(): void
    {
        // Arrange: 2 行の CSV
        $csv  = SjnetCsvBuilder::sheet([
            SjnetCsvBuilder::row()->withPolicyNo('D3-P001')->withCustomerName('冪等テスト顧客A')->withEndDate('2026/04/30'),
            SjnetCsvBuilder::row()->withPolicyNo('D3-P002')->withCustomerName('冪等テスト顧客B')->withEndDate('2026/04/30'),
        ])->toCsvString();
        $path    = $this->writeTempCsv($csv);
        $service = $this->makeService();

        // Act: 1 回目
        $first = $service->import($path, 'd3_first.csv');

        // Act: 2 回目（同一 CSV、同一ファイルパス）
        $second = $service->import($path, 'd3_second.csv');

        // Assert: 1 回目は insert
        $this->assertSame(2, $first['insert']);
        $this->assertSame(0, $first['update']);

        // Assert: 2 回目は update（同一 policy_no + policy_end_date でヒット）
        $this->assertSame(0, $second['insert']);
        $this->assertSame(2, $second['update']);

        // Assert: 異なる batch_id が生成されていること
        $this->assertNotSame($first['batch_id'], $second['batch_id']);

        // Assert: t_contract は 2 件のまま（重複 INSERT なし）
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());
    }

    /**
     * D4-1: counters の insert / update / customer_insert / skip / error が実際の DB 状態と一致する
     */
    public function testImport_SummaryCounters_MatchActualDbState(): void
    {
        // Arrange: 正常 2 行 + スキップ 1 行
        $existingCustomerName = '既存D4顧客';
        $this->createCustomer(['customer_name' => $existingCustomerName]);

        $csv  = SjnetCsvBuilder::sheet([
            SjnetCsvBuilder::row()->withPolicyNo('D41-NEW')->withCustomerName('新規D4顧客')->withEndDate('2026/04/30'),
            SjnetCsvBuilder::row()->withPolicyNo('D41-EXI')->withCustomerName($existingCustomerName)->withEndDate('2026/04/30'),
            SjnetCsvBuilder::row()->asSkipRowNoPolicyNo(), // スキップ
        ])->toCsvString();
        $path    = $this->writeTempCsv($csv);
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'd41_counters.csv');

        // Assert: カウンタ
        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['insert']);          // 2 件 insert
        $this->assertSame(0, $result['update']);
        $this->assertSame(1, $result['customer_insert']); // 新規顧客 1 件のみ INSERT
        $this->assertSame(1, $result['skip']);
        $this->assertSame(0, $result['error']);

        // Assert: DB 状態と一致
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM m_customer')->fetchColumn(),
            'm_customer は既存 1 + 新規 1 = 2 件');
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM t_contract')->fetchColumn());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM t_renewal_case')->fetchColumn());
    }

    /**
     * D4-2: 正常完了時に t_sjnet_import_batch.import_status = 'success' になること
     */
    public function testImport_BatchFinishedWithSuccessStatus(): void
    {
        // Arrange
        $path    = $this->writeTempCsv(
            SjnetCsvBuilder::row()
                ->withPolicyNo('D42-P001')
                ->withCustomerName('バッチ成功顧客')
                ->withEndDate(self::MATURITY_DATE)
                ->toCsvString()
        );
        $service = $this->makeService();

        // Act
        $result = $service->import($path, 'd42_success.csv');

        // Assert: import_status が success
        $batch = $this->getLatestBatch();
        $this->assertNotNull($batch);
        $this->assertSame('success', $batch['import_status']);
        $this->assertNotNull($batch['finished_at'], 'finished_at が記録されていること');

        // Assert: バッチのカウンタが result と一致
        $this->assertSame($result['total'],  (int) $batch['total_row_count']);
        $this->assertSame($result['insert'], (int) $batch['insert_count']);
        $this->assertSame($result['update'], (int) $batch['update_count']);
        $this->assertSame($result['error'],  (int) $batch['error_count']);
    }
}
