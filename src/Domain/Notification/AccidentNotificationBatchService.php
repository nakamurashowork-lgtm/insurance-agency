<?php
declare(strict_types=1);

namespace App\Domain\Notification;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lineworks_payload_helpers.php';

use DateTimeImmutable;
use Throwable;
use function App\Domain\Notification\build_lineworks_accident_reminder_payload;

final class AccidentNotificationBatchService
{
    public function __construct(private AccidentNotificationBatchRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $runDate,
        int $executedBy,
        bool $routeEnabled,
        ?int $retryFailedRunId = null,
        ?NotificationRetryPolicy $retryPolicy = null,
        ?WebhookNotificationSender $sender = null,
        ?array $endpoint = null,
        string $tenantCode = ''
    ): array
    {
        $runId = $this->repository->createRun($runDate, $executedBy);
        $retryPolicy ??= new NotificationRetryPolicy();
        $sender ??= new WebhookNotificationSender();
        $now = new DateTimeImmutable();
        $processedCount = 0;
        $successCount = 0;
        $skipCount = 0;
        $failCount = 0;
        $result = 'success';
        $errorMessage = null;
        $messageErrors = [];

        try {
            if ($retryFailedRunId !== null) {
                $failedRows = $this->repository->findFailedDeliveriesByRunId($retryFailedRunId);
                $retryTargets = [];

                foreach ($failedRows as $failedRow) {
                    $processedCount++;
                    $deliveryId = (int) ($failedRow['delivery_id'] ?? 0);
                    $accidentCaseId = (int) ($failedRow['accident_case_id'] ?? 0);
                    $ruleId = (int) ($failedRow['accident_reminder_rule_id'] ?? 0);

                    if ($deliveryId <= 0 || $accidentCaseId <= 0 || $ruleId <= 0) {
                        continue;
                    }

                    $decision = $retryPolicy->evaluate($failedRow, $now);
                    if (($decision['allowed'] ?? false) !== true) {
                        $skipCount++;
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

                        if ($this->repository->hasDeliveryForSchedule($accidentCaseId, $ruleId, $runDate)) {
                            $skipCount++;
                            continue;
                        }

                        $retryTargets[] = $failedRow;
                    } catch (Throwable $deliveryException) {
                        $this->repository->updateDeliveryForRetry(
                            $deliveryId,
                            $runId,
                            'failed',
                            $retryPolicy->buildFailureMessage(
                                $deliveryException->getMessage(),
                                (int) ($decision['next_attempt_count'] ?? 1)
                            ),
                            true
                        );
                        $failCount++;
                        $messageErrors[] = $deliveryException->getMessage();
                    }
                }

                if ($retryTargets !== []) {
                    try {
                        $this->sendAccidentNotification($sender, $endpoint, $retryTargets);

                        foreach ($retryTargets as $retryTarget) {
                            $deliveryId = (int) ($retryTarget['delivery_id'] ?? 0);
                            $ruleId = (int) ($retryTarget['accident_reminder_rule_id'] ?? 0);
                            if ($deliveryId <= 0 || $ruleId <= 0) {
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
                        }
                    } catch (Throwable $deliveryException) {
                        foreach ($retryTargets as $retryTarget) {
                            $deliveryId = (int) ($retryTarget['delivery_id'] ?? 0);
                            if ($deliveryId <= 0) {
                                continue;
                            }

                            $this->repository->updateDeliveryForRetry(
                                $deliveryId,
                                $runId,
                                'failed',
                                $retryPolicy->buildFailureMessage($deliveryException->getMessage(), 1),
                                true
                            );
                            $failCount++;
                        }
                        $messageErrors[] = $deliveryException->getMessage();
                    }
                }
            } else {
                $rules = $this->repository->findEnabledRulesWithWeekdays();
                $deliverableTargets = [];

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

                        if ($this->repository->hasDeliveryForSchedule($accidentCaseId, $ruleId, $runDate)) {
                            $skipCount++;
                            continue;
                        }

                        $deliverableTargets[] = $rule;
                    } catch (Throwable $deliveryException) {
                        $inserted = $this->repository->insertDeliveryFailed(
                            $runId,
                            $accidentCaseId,
                            $ruleId,
                            $runDate,
                            $retryPolicy->buildFailureMessage($deliveryException->getMessage(), 1)
                        );
                        if ($inserted) {
                            $failCount++;
                        }
                        $messageErrors[] = $deliveryException->getMessage();
                    }
                }

                if ($deliverableTargets !== []) {
                    try {
                        $this->sendAccidentNotification($sender, $endpoint, $deliverableTargets);

                        foreach ($deliverableTargets as $target) {
                            $accidentCaseId = (int) ($target['accident_case_id'] ?? 0);
                            $ruleId = (int) ($target['rule_id'] ?? 0);
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
                        }
                    } catch (Throwable $deliveryException) {
                        foreach ($deliverableTargets as $target) {
                            $inserted = $this->repository->insertDeliveryFailed(
                                $runId,
                                (int) ($target['accident_case_id'] ?? 0),
                                (int) ($target['rule_id'] ?? 0),
                                $runDate,
                                $retryPolicy->buildFailureMessage($deliveryException->getMessage(), 1)
                            );
                            if ($inserted) {
                                $failCount++;
                            }
                        }
                        $messageErrors[] = $deliveryException->getMessage();
                    }
                }
            }

            if ($failCount > 0 && $successCount > 0) {
                $result = 'partial';
            } elseif ($failCount > 0) {
                $result = 'failed';
            }
            if ($failCount > 0 && $messageErrors !== []) {
                $errorMessage = implode(' | ', array_slice($messageErrors, 0, 3));
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
            'retry_policy' => [
                'max_attempts' => $retryPolicy->maxAttempts(),
                'min_retry_minutes' => $retryPolicy->minRetryMinutes(),
            ],
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

    /**
     * @param array<string, mixed>|null $endpoint
     * @param array<int, array<string, mixed>> $targets
     */
    private function sendAccidentNotification(
        WebhookNotificationSender $sender,
        ?array $endpoint,
        array $targets
    ): void {
        $providerType = (string) ($endpoint['provider_type'] ?? '');
        $webhookUrl = (string) ($endpoint['webhook_url'] ?? '');
        $appPublicUrl = (string) ($endpoint['app_public_url'] ?? '');

        if ($providerType === '' || $webhookUrl === '') {
            throw new \RuntimeException('notification endpoint is incomplete');
        }

        $sender->send(
            $providerType,
            $webhookUrl,
            build_lineworks_accident_reminder_payload($appPublicUrl, $targets)
        );
    }
}
