<?php
declare(strict_types=1);

namespace Tests\Helpers;

/**
 * SjnetCsvBuilder — SJNET 取込テスト用 CSV 文字列ビルダー
 *
 * SJNET 満期一覧 CSV は 44 列固定（0-indexed）。
 * デフォルト値を持ち、必要な列だけ上書きして行データを組み立てる。
 *
 * 使用例:
 *   // 1行だけの CSV 文字列（ヘッダ込み）
 *   $csv = SjnetCsvBuilder::row()
 *       ->withPolicyNo('P001')
 *       ->withCustomerName('山田太郎')
 *       ->withEndDate('2026/04/30')
 *       ->toCsvString();
 *
 *   // 複数行
 *   $csv = SjnetCsvBuilder::sheet([
 *       SjnetCsvBuilder::row()->withPolicyNo('P001')->withCustomerName('山田太郎'),
 *       SjnetCsvBuilder::row()->withPolicyNo('P002')->withCustomerName('佐藤花子'),
 *   ])->toCsvString();
 */
final class SjnetCsvBuilder
{
    // 列インデックス（SjnetCsvImportService の定数と同期）
    private const COL_CUSTOMER_NAME    = 3;
    private const COL_POSTAL_CODE      = 5;
    private const COL_ADDRESS1         = 6;
    private const COL_PHONE            = 7;
    private const COL_START_DATE       = 15;
    private const COL_END_DATE         = 16;
    private const COL_PRODUCT_TYPE     = 17;
    private const COL_POLICY_NO        = 18;
    private const COL_PAYMENT_CYCLE    = 19;
    private const COL_PREMIUM_AMOUNT   = 22;
    private const COL_SJNET_STAFF_NAME = 42;
    private const COL_SJNET_AGENCY_CODE = 43;

    private const TOTAL_COLUMNS = 44;

    /** @var array<int, string> */
    private array $cols;

    /** @var list<self> */
    private array $rows = [];

    private bool $isSheet = false;

    private string $encoding = 'UTF-8';

    private function __construct()
    {
        // デフォルト値で 44 列を埋める
        $this->cols = array_fill(0, self::TOTAL_COLUMNS, '');

        // よく使う列にデフォルト値を設定
        $this->cols[self::COL_CUSTOMER_NAME]     = '山田太郎';
        $this->cols[self::COL_POLICY_NO]         = 'DEFAULT-POLICY';
        $this->cols[self::COL_END_DATE]          = '2026/04/30';
        $this->cols[self::COL_START_DATE]        = '2025/05/01';
        $this->cols[self::COL_PRODUCT_TYPE]      = '自動車';
        $this->cols[self::COL_PAYMENT_CYCLE]     = '一時払';
        $this->cols[self::COL_PREMIUM_AMOUNT]    = '120,000';
        $this->cols[self::COL_SJNET_AGENCY_CODE] = '';
        $this->cols[self::COL_SJNET_STAFF_NAME]  = '';
    }

    // =========================================================
    // ファクトリ
    // =========================================================

    /** 1行ビルダーを生成する */
    public static function row(): self
    {
        return new self();
    }

    /**
     * 複数行をまとめたシートビルダーを生成する
     *
     * @param list<self> $rows
     */
    public static function sheet(array $rows): self
    {
        $instance = new self();
        $instance->isSheet = true;
        $instance->rows    = $rows;
        return $instance;
    }

    // =========================================================
    // 列セッタ（メソッドチェーン）
    // =========================================================

    public function withCustomerName(string $name): self
    {
        $this->cols[self::COL_CUSTOMER_NAME] = $name;
        return $this;
    }

    public function withPolicyNo(string $policyNo): self
    {
        $this->cols[self::COL_POLICY_NO] = $policyNo;
        return $this;
    }

    /** YYYY/MM/DD または YYYY-MM-DD 形式 */
    public function withEndDate(string $date): self
    {
        $this->cols[self::COL_END_DATE] = $date;
        return $this;
    }

    /** YYYY/MM/DD または YYYY-MM-DD 形式 */
    public function withStartDate(string $date): self
    {
        $this->cols[self::COL_START_DATE] = $date;
        return $this;
    }

    public function withProductType(string $type): self
    {
        $this->cols[self::COL_PRODUCT_TYPE] = $type;
        return $this;
    }

    public function withPaymentCycle(string $cycle): self
    {
        $this->cols[self::COL_PAYMENT_CYCLE] = $cycle;
        return $this;
    }

    public function withPremiumAmount(string $amount): self
    {
        $this->cols[self::COL_PREMIUM_AMOUNT] = $amount;
        return $this;
    }

    public function withPostalCode(string $postalCode): self
    {
        $this->cols[self::COL_POSTAL_CODE] = $postalCode;
        return $this;
    }

    public function withAddress1(string $address): self
    {
        $this->cols[self::COL_ADDRESS1] = $address;
        return $this;
    }

    public function withPhone(string $phone): self
    {
        $this->cols[self::COL_PHONE] = $phone;
        return $this;
    }

    public function withAgencyCode(string $code): self
    {
        $this->cols[self::COL_SJNET_AGENCY_CODE] = $code;
        return $this;
    }

    public function withStaffName(string $name): self
    {
        $this->cols[self::COL_SJNET_STAFF_NAME] = $name;
        return $this;
    }

    /** 任意の列を直接セットする（テスト用） */
    public function withColumn(int $index, string $value): self
    {
        $this->cols[$index] = $value;
        return $this;
    }

    /** 証券番号を空にしてスキップ対象行を生成する */
    public function asSkipRowNoPolicyNo(): self
    {
        $this->cols[self::COL_POLICY_NO] = '';
        return $this;
    }

    /** 顧客名を空にしてスキップ対象行を生成する */
    public function asSkipRowNoCustomerName(): self
    {
        $this->cols[self::COL_CUSTOMER_NAME] = '';
        return $this;
    }

    /** 保険終期を空にしてスキップ対象行を生成する */
    public function asSkipRowNoEndDate(): self
    {
        $this->cols[self::COL_END_DATE] = '';
        return $this;
    }

    // =========================================================
    // 文字コード設定
    // =========================================================

    public function withEncoding(string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    // =========================================================
    // 出力
    // =========================================================

    /**
     * この行の列配列を返す（processRow に直接渡す用）
     *
     * @return array<int, string>
     */
    public function build(): array
    {
        return $this->cols;
    }

    /**
     * ヘッダ行 + データ行の CSV 文字列を返す
     * シートビルダーの場合は rows を全て出力、行ビルダーの場合は self を1行として出力する
     */
    public function toCsvString(): string
    {
        $lines = [$this->buildHeaderLine()];

        if ($this->isSheet) {
            foreach ($this->rows as $row) {
                $lines[] = $this->buildCsvLine($row->cols);
            }
        } else {
            $lines[] = $this->buildCsvLine($this->cols);
        }

        $csv = implode("\n", $lines) . "\n";
        return $this->applyEncoding($csv);
    }

    private function buildHeaderLine(): string
    {
        // SJNET CSV のヘッダは実際の列名だが、テストでは空ヘッダで十分
        $headers = array_fill(0, self::TOTAL_COLUMNS, 'col');
        return implode(',', $headers);
    }

    /**
     * @param array<int, string> $cols
     */
    private function buildCsvLine(array $cols): string
    {
        return implode(',', array_map(
            static fn(string $v): string => str_contains($v, ',') ? '"' . str_replace('"', '""', $v) . '"' : $v,
            $cols
        ));
    }

    private function applyEncoding(string $utf8Content): string
    {
        return match ($this->encoding) {
            'UTF-8-BOM' => "\xEF\xBB\xBF" . $utf8Content,
            'SJIS', 'CP932' => (string) mb_convert_encoding($utf8Content, 'CP932', 'UTF-8'),
            default     => $utf8Content,
        };
    }
}
