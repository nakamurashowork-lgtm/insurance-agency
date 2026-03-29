<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use DateTimeImmutable;
use Throwable;

final class RenewalNotificationBatchService
{
    public function __construct(private RenewalNotificationBatchRepository $repository)
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
        ?NotificationRetryPolicy $retryPolicy = null
    ): array
    {
        $runId = $this->repository->createRun($runDate, $executedBy);
        $retryPolicy ??= new NotificationRetryPolicy();
        $now = new DateTimeImmutable();

        $processedCount = 0;
        $successCount = 0;
        $skipCount = 0;
        $failCount = 0;
        $messages = [];

        try {
            if ($retryFailedRunId !== null && $retryFailedRunId > 0) {
                $failedTargets = $this->repository->findFailedDeliveriesByRunId($retryFailedRunId);
                foreach ($failedTargets as $target) {
                    $processedCount++;
                    $deliveryId = (int) ($target['delivery_id'] ?? 0);
                    if ($deliveryId <= 0) {
                        $failCount++;
                        $messages[] = 'INVALID_DELIVERY_ID';
                        continue;
                    }

                    $decision = $retryPolicy->evaluate($target, $now);
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
                                '通知先未設定または無効のためスキップ',
                                false
                            );
                            $skipCount++;
                            continue;
                        }

                        $this->repository->updateDeliveryForRetry($deliveryId, $runId, 'success', null, true);
                        $successCount++;
                    } catch (Throwable $e) {
                        $this->repository->updateDeliveryForRetry(
                            $deliveryId,
                            $runId,
                            'failed',
                            $retryPolicy->buildFailureMessage(
                                $e->getMessage(),
                                (int) ($decision['next_attempt_count'] ?? 1)
                            ),
                            true
                        );
                        $failCount++;
                        $messages[] = $e->getMessage();
                    }
                }

                $result = $this->resolveResult($processedCount, $successCount, $skipCount, $failCount);
                $errorMessage = $failCount > 0 ? implode(' | ', array_slice($messages, 0, 3)) : null;
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
                    'notification_run_id' => $runId,
                    'retry_failed_run_id' => $retryFailedRunId,
                    'run_date' => $runDate,
                    'result' => $result,
                    'processed_count' => $processedCount,
                    'success_count' => $successCount,
                    'skip_count' => $skipCount,
                    'fail_count' => $failCount,
                    'error_message' => $errorMessage,
                    'retry_policy' => [
                        'max_attempts' => $retryPolicy->maxAttempts(),
                        'min_retry_minutes' => $retryPolicy->minRetryMinutes(),
                    ],
                ];
            }

            $phases = $this->repository->findEnabledPhases();
            foreach ($phases as $phase) {
                $phaseId = (int) ($phase['id'] ?? 0);
                $fromDaysBefore = (int) ($phase['from_days_before'] ?? -1);
                $toDaysBefore = (int) ($phase['to_days_before'] ?? -1);
                if ($phaseId <= 0 || $fromDaysBefore < 0 || $toDaysBefore < 0) {
                    continue;
                }

                $targets = $this->repository->findRenewalTargetsByPhase($runDate, $fromDaysBefore, $toDaysBefore);
                foreach ($targets as $target) {
                    $processedCount++;
                    $renewalCaseId = (int) ($target['renewal_case_id'] ?? 0);
                    if ($renewalCaseId <= 0) {
                        $failCount++;
                        $messages[] = 'INVALID_RENEWAL_CASE_ID';
                        continue;
                    }

                    try {
                        if (!$routeEnabled) {
                            $inserted = $this->repository->insertDeliverySkipped(
                                $runId,
                                $renewalCaseId,
                                $phaseId,
                                $runDate,
                                '通知先未設定または無効のためスキップ'
                            );
                            if ($inserted) {
                                $skipCount++;
                            } else {
                                // Idempotent duplicate, count as skip to keep reruns observable.
                                $skipCount++;
                            }
                            continue;
                        }

                        $inserted = $this->repository->insertDeliverySuccess($runId, $renewalCaseId, $phaseId, $runDate);
                        if ($inserted) {
                            $successCount++;
                        } else {
                            // Idempotent duplicate from rerun.
                            $skipCount++;
                        }
                    } catch (Throwable $e) {
                        $this->repository->insertDeliveryFailed(
                            $runId,
                            $renewalCaseId,
                            $phaseId,
                            $runDate,
                            $retryPolicy->buildFailureMessage($e->getMessage(), 1)
                        );
                        $failCount++;
                        $messages[] = $e->getMessage();
                    }
                }
            }

            $result = $this->resolveResult($processedCount, $successCount, $skipCount, $failCount);
            $errorMessage = $failCount > 0 ? implode(' | ', array_slice($messages, 0, 3)) : null;
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
                'notification_run_id' => $runId,
                'run_date' => $runDate,
                'result' => $result,
                'processed_count' => $processedCount,
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'fail_count' => $failCount,
                'error_message' => $errorMessage,
                'retry_policy' => [
                    'max_attempts' => $retryPolicy->maxAttempts(),
                    'min_retry_minutes' => $retryPolicy->minRetryMinutes(),
                ],
            ];
        } catch (Throwable $e) {
            $this->repository->finalizeRun(
                $runId,
                'failed',
                $processedCount,
                $successCount,
                $skipCount,
                $failCount,
                $e->getMessage()
            );

            return [
                'notification_run_id' => $runId,
                'run_date' => $runDate,
                'result' => 'failed',
                'processed_count' => $processedCount,
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'fail_count' => $failCount,
                'error_message' => $e->getMessage(),
                'retry_policy' => [
                    'max_attempts' => $retryPolicy->maxAttempts(),
                    'min_retry_minutes' => $retryPolicy->minRetryMinutes(),
                ],
            ];
        }
    }

    private function resolveResult(int $processedCount, int $successCount, int $skipCount, int $failCount): string
    {
        if ($failCount > 0 && $successCount > 0) {
            return 'partial';
        }

        if ($failCount > 0 && $successCount === 0 && $skipCount === 0) {
            return 'failed';
        }

        // success also covers all-skipped and no-target runs for operational simplicity.
        return 'success';
    }
}
