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
     * 最小限の有効ルール（週次・全曜日許可）。
     * DDL: base_date は NOT NULL。start_date/end_date/last_notified_on は NULL 許容。
     * is_enabled=1 のフィルタは findEnabledRulesWithWeekdays() がDB側で行うため
     * isDue() の引数には is_enabled が存在しない（責務の分離）。
     *
     * weekdays_csv は「全曜日（w=0..6）」を既定値とし、曜日フィルタの影響を排除した上で
     * interval / start_date / end_date / last_notified_on など他次元のロジックを単独検証する。
     * 仕様上「weekdays_csv が空 → isDue()=false（曜日未設定のルールは発火しない）」のため、
     * 空文字を既定値にすると曜日以外の次元を検証できなくなる。
     * 空のケースは testNotDue_EmptyWeekdaysCsv で個別に検証する。
     */
    private function baseRule(array $overrides = []): array
    {
        return array_merge([
            'rule_id'          => 1,
            'accident_case_id' => 10,
            'weekdays_csv'     => '0,1,2,3,4,5,6',  // 全曜日許可（曜日フィルタ無効化）
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
        // 週次ルール、base=月曜、月曜のみ許可（weekdays_csv='1'）。
        // 同じ ISO 週内の木曜（2026-04-09）は曜日フィルタで発火しない。
        // [実装メモ] interval は weeksSinceBase（baseMonday → todayMonday の週差）で判定するため、
        // 全曜日許可だと同じ週内のあらゆる曜日が weeksSinceBase=0 で通ってしまう。
        // 「mid-week は通知しない」という制御は曜日フィルタ側で表現する設計。
        $rule = $this->baseRule(['interval_weeks' => 1, 'weekdays_csv' => '1']);
        $this->assertFalse($this->isDue($rule, '2026-04-09'));
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
        // 2026-04-06は月曜(w=1)、weekdays_csv='1'
        $rule = $this->baseRule(['weekdays_csv' => '1']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_WeekdayDoesNotMatchRunDate(): void
    {
        // 2026-04-06は月曜(w=1)、weekdays_csv='2'（火曜のみ）
        $rule = $this->baseRule(['weekdays_csv' => '2']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    public function testNotDue_EmptyWeekdaysCsv(): void
    {
        // 仕様: weekdays_csv が空（曜日未設定のルール）は発火させない。
        // 本番 SQL では LEFT JOIN + GROUP_CONCAT により、weekday 行が無いルールは
        // weekdays_csv が NULL → '' として渡る。これを「曜日未設定 = 通知しない」と扱う。
        $rule = $this->baseRule(['weekdays_csv' => '']);
        $this->assertFalse($this->isDue($rule, '2026-04-06'));
    }

    // =========================================================
    // 曜日コード規約: w 形式（0=日, 1=月, ..., 6=土）
    // DB 制約 (weekday_cd BETWEEN 0 AND 6) と UI (format('w')) と整合させる。
    // 旧実装は N 形式（1=月, ..., 7=日）を用いており、日曜だけ永遠に発火しないバグがあった。
    // =========================================================

    public function testDue_OnSunday_WithWeekdayCdZero(): void
    {
        // 日曜 (w=0) + weekdays_csv='0' で発火すること（日曜バグの回帰テスト）
        // base_date=2026-04-19（日曜）から 7 日後 = 2026-04-26（日曜）
        $rule = $this->baseRule([
            'weekdays_csv' => '0',
            'base_date'    => '2026-04-19',
        ]);
        $this->assertTrue($this->isDue($rule, '2026-04-26'));
    }

    public function testNotDue_OnSunday_WithWeekdayCdSeven(): void
    {
        // 旧 N 形式の値 7 が紛れ込んだ場合は発火しないこと（DB CHECK で本来挿入不可だが防御確認）
        $rule = $this->baseRule([
            'weekdays_csv' => '7',
            'base_date'    => '2026-04-19',
        ]);
        $this->assertFalse($this->isDue($rule, '2026-04-26'));
    }

    public function testNotDue_OnSunday_WhenSundayNotInWeekdays(): void
    {
        // 日曜実行 (w=0) で weekdays_csv='1'（月曜のみ）→ 不一致で発火しない
        $rule = $this->baseRule([
            'weekdays_csv' => '1',
            'base_date'    => '2026-04-19',
        ]);
        $this->assertFalse($this->isDue($rule, '2026-04-26'));
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
    // last_notified_on（情報のみ・isDue() は参照しない）
    //
    // 仕様: 同日再実行で再通知することを許容する。last_notified_on は最終通知日の
    // 記録用であって発火判定には用いない。重複防止が必要な場合は呼び出し側で別途制御する。
    // =========================================================

    public function testDue_AlreadyNotifiedToday(): void
    {
        // last_notified_on が今日付でも、他条件が揃えば発火する（同日再送信を許容）
        $rule = $this->baseRule(['last_notified_on' => '2026-04-06']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
    }

    public function testDue_AlreadyNotifiedFuture(): void
    {
        // last_notified_on が runDate より未来（異常系）でも isDue() は無視する
        $rule = $this->baseRule(['last_notified_on' => '2026-04-07']);
        $this->assertTrue($this->isDue($rule, '2026-04-06'));
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
