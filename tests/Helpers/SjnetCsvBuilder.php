<?php
declare(strict_types=1);

namespace Tests\Helpers;

/**
 * SjnetCsvBuilder — SJNET 取込テスト用 CSV 文字列ビルダー
 *
 * ヘッダ名をキーとする辞書型で列を管理する。
 * toCsvString() は実際の日本語ヘッダ名を1行目に出力するため、
 * SjnetCsvImportService のヘッダ名ベース解析と整合する。
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
    // ヘッダ名定数（SjnetCsvImportService の HDR_* と同期）
    private const HDR_CUSTOMER_NAME    = '顧客名';
    private const HDR_BIRTH_DATE       = '生年月日';
    private const HDR_POSTAL_CODE      = '郵便番号';
    private const HDR_ADDRESS1         = '住所';
    private const HDR_PHONE            = 'ＴＥＬ';
    private const HDR_START_DATE       = '保険始期';
    private const HDR_END_DATE         = '保険終期';
    private const HDR_PRODUCT_TYPE     = '種目種類';
    private const HDR_POLICY_NO        = '証券番号';
    private const HDR_PAYMENT_CYCLE    = '払込方法';
    private const HDR_PREMIUM_AMOUNT   = '合計保険料';
    private const HDR_STAFF_NAME       = '担当者';
    private const HDR_AGENCY_CODE      = '代理店ｺｰﾄﾞ'; // 半角カタカナ

    /** @var array<string, string> ヘッダ名 → 値 */
    private array $data;

    /** @var list<self> */
    private array $rows = [];

    private bool $isSheet = false;

    private string $encoding = 'UTF-8';

    private function __construct()
    {
        // デフォルト値（必須列 + よく使う任意列）
        $this->data = [
            self::HDR_CUSTOMER_NAME  => '山田太郎',
            self::HDR_BIRTH_DATE     => '',
            self::HDR_POSTAL_CODE    => '',
            self::HDR_ADDRESS1       => '',
            self::HDR_PHONE          => '',
            self::HDR_START_DATE     => '2025/05/01',
            self::HDR_END_DATE       => '2026/04/30',
            self::HDR_PRODUCT_TYPE   => '自動車',
            self::HDR_POLICY_NO      => 'DEFAULT-POLICY',
            self::HDR_PAYMENT_CYCLE  => '一時払',
            self::HDR_PREMIUM_AMOUNT => '120,000',
            self::HDR_STAFF_NAME     => '',
            self::HDR_AGENCY_CODE    => '',
        ];
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
        $this->data[self::HDR_CUSTOMER_NAME] = $name;
        return $this;
    }

    /** YYYY-MM-DD 形式、または空文字（生年月日なし） */
    public function withBirthDate(string $date): self
    {
        $this->data[self::HDR_BIRTH_DATE] = $date;
        return $this;
    }

    public function withPolicyNo(string $policyNo): self
    {
        $this->data[self::HDR_POLICY_NO] = $policyNo;
        return $this;
    }

    /** YYYY/MM/DD または YYYY-MM-DD 形式 */
    public function withEndDate(string $date): self
    {
        $this->data[self::HDR_END_DATE] = $date;
        return $this;
    }

    /** YYYY/MM/DD または YYYY-MM-DD 形式 */
    public function withStartDate(string $date): self
    {
        $this->data[self::HDR_START_DATE] = $date;
        return $this;
    }

    public function withProductType(string $type): self
    {
        $this->data[self::HDR_PRODUCT_TYPE] = $type;
        return $this;
    }

    public function withPaymentCycle(string $cycle): self
    {
        $this->data[self::HDR_PAYMENT_CYCLE] = $cycle;
        return $this;
    }

    public function withPremiumAmount(string $amount): self
    {
        $this->data[self::HDR_PREMIUM_AMOUNT] = $amount;
        return $this;
    }

    public function withPostalCode(string $postalCode): self
    {
        $this->data[self::HDR_POSTAL_CODE] = $postalCode;
        return $this;
    }

    public function withAddress1(string $address): self
    {
        $this->data[self::HDR_ADDRESS1] = $address;
        return $this;
    }

    public function withPhone(string $phone): self
    {
        $this->data[self::HDR_PHONE] = $phone;
        return $this;
    }

    public function withAgencyCode(string $code): self
    {
        $this->data[self::HDR_AGENCY_CODE] = $code;
        return $this;
    }

    public function withStaffName(string $name): self
    {
        $this->data[self::HDR_STAFF_NAME] = $name;
        return $this;
    }

    /** 任意のヘッダ名で列を直接セットする */
    public function withRawHeader(string $header, string $value): self
    {
        $this->data[$header] = $value;
        return $this;
    }

    /** 証券番号を空にしてスキップ対象行を生成する */
    public function asSkipRowNoPolicyNo(): self
    {
        $this->data[self::HDR_POLICY_NO] = '';
        return $this;
    }

    /** 顧客名を空にしてスキップ対象行を生成する */
    public function asSkipRowNoCustomerName(): self
    {
        $this->data[self::HDR_CUSTOMER_NAME] = '';
        return $this;
    }

    /** 保険終期を空にしてスキップ対象行を生成する */
    public function asSkipRowNoEndDate(): self
    {
        $this->data[self::HDR_END_DATE] = '';
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
     * この行のデータ辞書を返す（processRow に直接渡す用途など）
     *
     * @return array<string, string>
     */
    public function build(): array
    {
        return $this->data;
    }

    /**
     * ヘッダ行 + データ行の CSV 文字列を返す
     * シートビルダーの場合は rows を全て出力、行ビルダーの場合は self を1行として出力する
     */
    public function toCsvString(): string
    {
        // ヘッダキーは最初の行（シートなら rows[0]、単行なら self）から取得
        $headerKeys = $this->isSheet && count($this->rows) > 0
            ? array_keys($this->rows[0]->data)
            : array_keys($this->data);

        $lines = [$this->buildCsvLine($headerKeys)];

        if ($this->isSheet) {
            foreach ($this->rows as $row) {
                $lines[] = $this->buildCsvLine(array_values($row->data));
            }
        } else {
            $lines[] = $this->buildCsvLine(array_values($this->data));
        }

        $csv = implode("\n", $lines) . "\n";
        return $this->applyEncoding($csv);
    }

    /**
     * @param array<int, string> $values
     */
    private function buildCsvLine(array $values): string
    {
        return implode(',', array_map(
            static fn(string $v): string => str_contains($v, ',') ? '"' . str_replace('"', '""', $v) . '"' : $v,
            $values
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
