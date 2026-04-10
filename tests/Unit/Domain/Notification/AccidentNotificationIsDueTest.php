<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\Notification;

use App\Domain\Notification\AccidentNotificationBatchService;
use App\Domain\Notification\AccidentNotificationBatchRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AccidentNotificationBatchService::isDue() のロジックをテストする。
 *
 * isDue() は「今日この事故案件リマインドルールを通知すべきか」を判定する純粋ロジック。
 * 通知の重複・漏れに直結するため、境界値を網羅的に検証する。
 *
 * [設計上の注意]
 * isDue() は AccidentNotificationBatchService のメソッドだが、
 * 本来は AccidentReminderRule ドメインオブジェクトに isDue(DateTimeInterface): bool として
 * 持たせるべきロジック。現状は Service に置かれているため、リフレクションでテストする。
 * リファクタリング時にテスト構造も合わせて変更すること。
 */
final class AccidentNotificationIsDueTest extends TestCase
{
    private AccidentNotificationBatchService $service;
    private \ReflectionMethod $isDue;

    protected function setUp(): void
    {
        // AccidentNotificationBatchRepository は final のため createStub 不可。
        // PDO スタブを渡して直接インスタンス化する（isDue() は DB 非依存）。
        $pdoStub = $this->createStub(\PDO::class);
        $repo = new AccidentNotificationBatchRepository($pdoStub);
        $this->service = new AccidentNotificationBatchService($repo);

        $ref = new ReflectionClass($this->service);
        $this->isDue = $ref->getMethod('isDue');
        $this->isDue->setAccessible(true);
    }

    private function isDue(array $rule, string $runDate): bool
    {
        return (bool) $this->isDue->invoke($this->service, $rule, $runDate);
    }

    /**
     * 最小限の有効ルール（週次・曜日制限なし）。
     * DDL: base_date は NOT NULL。start_date/end_date/last_notified_on は NULL 許容。
     * is_enabled=1 のフィルタは findEnabledRulesWithWeekdays() がDB側で行うため
     * isDue() の引数には is_enabled が存在しない（責務の分離）。
     */
    private function baseRule(array $overrides = []): array
    {
        return array_merge([
            'rule_id'          => 1,
            'accident_case_id' => 10,
            'weekdays_csv'     => '',        // 曜日制限なし
            'start_date'       => '',        // DDL では NULL、ここでは空文字で統一
            'end_date'         => '',
            'interval_weeks'   => 1,
            'base_date'        => '2026-04-06',  // 月曜（DDL: NOT NULL）
            'last_notified_on' => '',
        ], $overrides);
    }

    // =========================================================
    // 基本: base_date と同日
    // =========================================================

    public function testDue_OnBaseDate(): void
    {
        // base_date と runDate が一致 → dayDiff=0 → 0 % 7 === 0 → true
        $this->assertTrue($this->isDue($this->baseRule(), '2026-04-06'));
    }

    // =========================================================
    // interval_weeks
    // =========================================================

    public function testDue_ExactlyOneWeekLater(): void
    {
        $this->assertTrue($this->isDue($this->baseRule(['interval_weeks' => 1]), '2026-04-13'));
    }

    public function testNotDue_MidweekOnWeeklyRule(): void
    {
        // base=月曜、3日後の木曜 → dayDiff=3、3 % 7 != 0
        $this->assertFalse($this->isDue($this->baseRule(['interval_weeks' => 1]), '2026-04-09'));
    }

    public function testDue_BiweeklyOnCorrectDay(): void
    {
        // 2週間後
        $this->assertTrue($this->isDue($this->baseRule(['interval_weeks' => 2]), '2026-04-20'));
    }

    public function testNotDue_BiweeklyOnOneWeekLater(): void
    {
        // 2週ルールなのに1週後 → dayDiff=7、7 % 14 != 0（境界値）
        $this->assertFalse($this->isDue($this->baseRule(['interval_weeks' => 2]), '2026-04-13'));
    }

    // =========================================================
    // 曜日フィルタ
    // =========================================================

    public function testDue_WeekdayMatchesRunDate(): void
    {
        // 2026-04-06は月曜(N=1)、weekdays_csv='1'
        $rule = $this->baseRule(['weekdays_csv' => '1']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_WeekdayDoesNotMatchRunDate(): void
    {
        // 2026-04-06は月曜(N=1)、weekdays_csv='2'（火曜のみ）
        $rule = $this->baseRule(['weekdays_csv' => '2']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testDue_MultipleWeekdays(): void
    {
        // 月・水を許可、runDateは月曜
        $rule = $this->baseRule(['weekdays_csv' => '1,3']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_IntervalAndWeekdayConflict(): void
    {
        // interval_weeks=2 かつ weekdays_csv='1'（月曜のみ）。
        // base_date=2026-04-06（月曜）から14日後=2026-04-20（月曜）→ 両条件クリア → true
        // base_date から7日後=2026-04-13（月曜）→ 曜日は一致するが interval=14日ズレ → false
        $rule = $this->baseRule(['interval_weeks' => 2, 'weekdays_csv' => '1']);
        $this->assertFalse($this->isDue($rule, '2026-04-13')); // 曜日○・interval×
        $this->assertTrue($this->isDue($rule, '2026-04-20'));  // 曜日○・interval○

        // base_date から14日後が水曜にあたる場合（base=水曜=2026-04-08）
        $ruleWed = $this->baseRule([
            'interval_weeks' => 2,
            'weekdays_csv'   => '1',          // 月曜のみ許可
            'base_date'      => '2026-04-08', // 水曜
        ]);
        // 14日後=2026-04-22（水曜） → interval は合うが曜日不一致 → false
        $this->assertFalse($this->isDue($ruleWed, '2026-04-22'));
    }

    // =========================================================
    // start_date / end_date フィルタ
    // =========================================================

    public function testNotDue_BeforeStartDate(): void
    {
        $rule = $this->baseRule(['start_date' => '2026-04-07']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testDue_OnStartDate(): void
    {
        // 境界値: start_date 当日は通知する
        $rule = $this->baseRule(['start_date' => '2026-04-06']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_AfterEndDate(): void
    {
        $rule = $this->baseRule(['end_date' => '2026-04-05']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testDue_OnEndDate(): void
    {
        // 境界値: end_date 当日は通知する
        $rule = $this->baseRule(['end_date' => '2026-04-06']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    // =========================================================
    // last_notified_on ガード（重複送信防止）
    // =========================================================

    public function testNotDue_AlreadyNotifiedToday(): void
    {
        $rule = $this->baseRule(['last_notified_on' => '2026-04-06']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_AlreadyNotifiedFuture(): void
    {
        // last_notified_on が runDate より未来（異常系）でもガード
        $rule = $this->baseRule(['last_notified_on' => '2026-04-07']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testDue_NotifiedYesterday(): void
    {
        // 前回は昨日、今日は週次タイミング（base_date から 7 日後）
        $rule = $this->baseRule([
            'base_date'        => '2026-03-30',
            'last_notified_on' => '2026-03-30',
            'interval_weeks'   => 1,
        ]);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    // =========================================================
    // base_date 未来・未設定
    // =========================================================

    public function testNotDue_RunDateBeforeBaseDate(): void
    {
        // runDate < base_date → dayDiff 負数 → false
        $rule = $this->baseRule(['base_date' => '2026-04-13']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_EmptyBaseDate(): void
    {
        // [注意] DDL では base_date は NOT NULL のため、本来この入力はありえない。
        // 万一 NULL が渡された場合（型変換で空文字になった場合）の防御的確認。
        $rule = $this->baseRule(['base_date' => '']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    // =========================================================
    // is_enabled の責務確認
    // =========================================================

    public function testIsEnabledIsNotCheckedByIsDue(): void
    {
        // is_enabled=0 のルールは findEnabledRulesWithWeekdays() の SQL（WHERE is_enabled=1）で
        // 除外されるため、isDue() には渡らない。
        // isDue() は is_enabled キーを参照しない設計なので、
        // is_enabled=0 を渡しても他の条件が揃えば true を返す。
        // これは isDue() の責務外（= 正しい設計）であることを明示するテスト。
        $rule = $this->baseRule(['is_enabled' => 0]);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    // =========================================================
    // 無効な runDate
    // =========================================================

    public function testNotDue_InvalidRunDate(): void
    {
        $this->assertFalse($this->isDue($this->baseRule(), 'not-a-date'));
    }
}
