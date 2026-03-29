<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use DateTimeImmutable;
use Throwable;

final class AccidentNotificationBatchService
{
    public function __construct(private AccidentNotificationBatchRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $runDate, int $executedBy, bool $routeEnabled, ?int $retryFailedRunId = null): array
    {
        $runId = $this->repository->createRun($runDate, $executedBy);
        $processedCount = 0;
        $successCount = 0;
        $skipCount = 0;
        $failCount = 0;
        $result = 'success';
        $errorMessage = null;

        try {
            if ($retryFailedRunId !== null) {
                $failedRows = $this->repository->findFailedDeliveriesByRunId($retryFailedRunId);

                foreach ($failedRows as $failedRow) {
                    $processedCount++;
                    $deliveryId = (int) ($failedRow['delivery_id'] ?? 0);
                    $accidentCaseId = (int) ($failedRow['accident_case_id'] ?? 0);
                    $ruleId = (int) ($failedRow['accident_reminder_rule_id'] ?? 0);

                    if ($deliveryId <= 0 || $accidentCaseId <= 0 || $ruleId <= 0) {
                        continue;
                    }

                    try {
                        if (!$routeEnabled) {
                            $this->repository->updateDeliveryForRetry(
                                $deliveryId,
                                $runId,
                                'skipped',
                                'retry skipped because route is disabled',
                                false
                            );
                            $skipCount++;
                            continue;
                        }

                        $this->repository->updateDeliveryForRetry(
                            $deliveryId,
                            $runId,
                            'success',
                            null,
                            true
                        );
                        $this->repository->updateRuleLastNotifiedOn($ruleId, $runDate);
                        $successCount++;
                    } catch (Throwable $deliveryException) {
                        $this->repository->updateDeliveryForRetry(
                            $deliveryId,
                            $runId,
                            'failed',
                            $deliveryException->getMessage(),
                            false
                        );
                        $failCount++;
                    }
                }
            } else {
                $rules = $this->repository->findEnabledRulesWithWeekdays();

                foreach ($rules as $rule) {
                    if (!$this->isDue($rule, $runDate)) {
                        continue;
                    }

                    $processedCount++;
                    $accidentCaseId = (int) ($rule['accident_case_id'] ?? 0);
                    $ruleId = (int) ($rule['rule_id'] ?? 0);

                    try {
                        if (!$routeEnabled) {
                            $inserted = $this->repository->insertDeliverySkipped(
                                $runId,
                                $accidentCaseId,
                                $ruleId,
                                $runDate,
                                'route disabled'
                            );
                            if ($inserted) {
                                $skipCount++;
                            }
                            continue;
                        }

                        $inserted = $this->repository->insertDeliverySuccess(
                            $runId,
                            $accidentCaseId,
                            $ruleId,
                            $runDate
                        );
                        if ($inserted) {
                            $successCount++;
                            $this->repository->updateRuleLastNotifiedOn($ruleId, $runDate);
                        } else {
                            $skipCount++;
                        }
                    } catch (Throwable $deliveryException) {
                        $inserted = $this->repository->insertDeliveryFailed(
                            $runId,
                            $accidentCaseId,
                            $ruleId,
                            $runDate,
                            $deliveryException->getMessage()
                        );
                        if ($inserted) {
                            $failCount++;
                        }
                    }
                }
            }

            if ($failCount > 0 && $successCount > 0) {
                $result = 'partial';
            } elseif ($failCount > 0) {
                $result = 'failed';
            }
        } catch (Throwable $exception) {
            $result = 'failed';
            $errorMessage = $exception->getMessage();
        }

        $this->repository->finalizeRun(
            $runId,
            $result,
            $processedCount,
            $successCount,
            $skipCount,
            $failCount,
            $errorMessage
        );

        return [
            'run_id' => $runId,
            'notification_type' => 'accident',
            'run_date' => $runDate,
            'result' => $result,
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'fail_count' => $failCount,
            'error_message' => $errorMessage,
            'retry_failed_run_id' => $retryFailedRunId,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function isDue(array $rule, string $runDate): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $runDate);
        if ($date === false) {
            return false;
        }

        $todayWeekday = (int) $date->format('N');
        $weekdaysCsv = (string) ($rule['weekdays_csv'] ?? '');
        if ($weekdaysCsv !== '') {
            $weekdays = array_map('intval', explode(',', $weekdaysCsv));
            if (!in_array($todayWeekday, $weekdays, true)) {
                return false;
            }
        }

        $startDate = (string) ($rule['start_date'] ?? '');
        if ($startDate !== '' && $runDate < $startDate) {
            return false;
        }

        $endDate = (string) ($rule['end_date'] ?? '');
        if ($endDate !== '' && $runDate > $endDate) {
            return false;
        }

        $intervalWeeks = max(1, (int) ($rule['interval_weeks'] ?? 1));
        $baseDate = (string) ($rule['base_date'] ?? '');
        if ($baseDate === '') {
            return false;
        }

        $base = DateTimeImmutable::createFromFormat('Y-m-d', $baseDate);
        if ($base === false) {
            return false;
        }

        $dayDiff = (int) $base->diff($date)->format('%r%a');
        if ($dayDiff < 0) {
            return false;
        }

        $intervalDays = $intervalWeeks * 7;
        if ($dayDiff % $intervalDays !== 0) {
            return false;
        }

        $lastNotifiedOn = (string) ($rule['last_notified_on'] ?? '');
        if ($lastNotifiedOn !== '' && $lastNotifiedOn >= $runDate) {
            return false;
        }

        return true;
    }
}
