<?php
declare(strict_types=1);

namespace App\Domain\Notification;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lineworks_payload_helpers.php';

use DateTimeImmutable;
use Throwable;
use function App\Domain\Notification\build_lineworks_renewal_alert_payload;

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
        $messages = [];
        $notificationDefinitions = $this->notificationDefinitions();

        try {
            if ($retryFailedRunId !== null && $retryFailedRunId > 0) {
                $failedTargets = $this->repository->findFailedDeliveriesByRunId($retryFailedRunId);
                $retryGroups = [];

                foreach ($failedTargets as $target) {
                    $processedCount++;
                    $deliveryId = (int) ($target['delivery_id'] ?? 0);
                    $renewalCaseId = (int) ($target['renewal_case_id'] ?? 0);
                    $phaseId = (int) ($target['renewal_reminder_phase_id'] ?? 0);
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

                        if ($renewalCaseId <= 0 || $phaseId <= 0) {
                            $this->repository->updateDeliveryForRetry(
                                $deliveryId,
                                $runId,
                                'failed',
                                $retryPolicy->buildFailureMessage('INVALID_RENEWAL_RETRY_TARGET', (int) ($decision['next_attempt_count'] ?? 1)),
                                false
                            );
                            $failCount++;
                            continue;
                        }

                        $notificationKey = $this->resolveRetryNotificationKey((int) ($target['days_before'] ?? -1));
                        if ($notificationKey === null) {
                            $this->repository->updateDeliveryForRetry(
                                $deliveryId,
                                $runId,
                                'failed',
                                $retryPolicy->buildFailureMessage('UNSUPPORTED_RENEWAL_NOTIFICATION_DAY', (int) ($decision['next_attempt_count'] ?? 1)),
                                false
                            );
                            $failCount++;
                            continue;
                        }

                        $retryGroups[$notificationKey][] = $target;
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

                foreach ($notificationDefinitions as $definition) {
                    $notificationKey = (string) $definition['key'];
                    $targets = $retryGroups[$notificationKey] ?? [];
                    if ($targets === []) {
                        continue;
                    }

                    try {
                        $this->sendRenewalNotification(
                            $sender,
                            $endpoint,
                            $definition,
                            $targets
                        );

                        foreach ($targets as $target) {
                            $this->repository->updateDeliveryForRetry(
                                (int) $target['delivery_id'],
                                $runId,
                                'success',
                                null,
                                true
                            );
                            $successCount++;
                        }
                    } catch (Throwable $e) {
                        foreach ($targets as $target) {
                            $this->repository->updateDeliveryForRetry(
                                (int) $target['delivery_id'],
                                $runId,
                                'failed',
                                $retryPolicy->buildFailureMessage(
                                    $e->getMessage(),
                                    1
                                ),
                                true
                            );
                            $failCount++;
                        }
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

            $sortedDays = array_map(static fn($d) => (int) $d['days_before'], $notificationDefinitions);
            sort($sortedDays);
            $lowerBoundByDays = [];
            $prev = -1;
            foreach ($sortedDays as $d) {
                $lowerBoundByDays[$d] = $prev + 1;
                $prev = $d;
            }

            foreach ($notificationDefinitions as $definition) {
                $phase = $this->repository->findPhaseForDaysBefore((int) $definition['days_before']);
                if (!is_array($phase)) {
                    continue;
                }

                $phaseId = (int) ($phase['id'] ?? 0);
                if ($phaseId <= 0) {
                    continue;
                }

                $fromDaysBefore = (int) $definition['days_before'];
                $toDaysBefore   = $lowerBoundByDays[$fromDaysBefore] ?? 0;
                $targets = $this->repository->findRenewalTargetsByPhase($runDate, $fromDaysBefore, $toDaysBefore);
                $deliverableTargets = [];

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

                        $target['renewal_reminder_phase_id'] = $phaseId;
                        $deliverableTargets[] = $target;
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

                if ($deliverableTargets === []) {
                    continue;
                }

                try {
                    $this->sendRenewalNotification(
                        $sender,
                        $endpoint,
                        $definition,
                        $deliverableTargets
                    );

                    foreach ($deliverableTargets as $target) {
                        $inserted = $this->repository->insertDeliverySuccess(
                            $runId,
                            (int) $target['renewal_case_id'],
                            $phaseId,
                            $runDate
                        );
                        if ($inserted) {
                            $successCount++;
                        } else {
                            $skipCount++;
                        }
                    }
                } catch (Throwable $e) {
                    foreach ($deliverableTargets as $target) {
                        $this->repository->insertDeliveryFailed(
                            $runId,
                            (int) $target['renewal_case_id'],
                            $phaseId,
                            $runDate,
                            $retryPolicy->buildFailureMessage($e->getMessage(), 1)
                        );
                        $failCount++;
                    }
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

        if ($failCount > 0) {
            return 'failed';
        }

        // success also covers all-skipped and no-target runs for operational simplicity.
        return 'success';
    }

    /**
     * DBのm_renewal_reminder_phaseから通知トリガーフェーズを動的に取得する。
     * EARLY → 'early'（早期通知）、URGENT → 'direct'（直前通知）にマッピング。
     *
     * @return array<int, array{key:string,label:string,days_before:int}>
     */
    private function notificationDefinitions(): array
    {
        $phases = $this->repository->findNotificationTriggerPhases();
        $definitions = [];
        foreach ($phases as $phase) {
            $key = match ((string) ($phase['phase_code'] ?? '')) {
                'EARLY'  => 'early',
                'URGENT' => 'direct',
                default  => null,
            };
            if ($key === null) {
                continue;
            }
            $definitions[] = [
                'key'         => $key,
                'label'       => (string) ($phase['phase_name'] ?? $key),
                'days_before' => (int) ($phase['from_days_before'] ?? 0),
            ];
        }

        return $definitions;
    }

    private function resolveRetryNotificationKey(int $daysBefore): ?string
    {
        foreach ($this->notificationDefinitions() as $definition) {
            if ((int) ($definition['days_before'] ?? 0) === $daysBefore) {
                return (string) $definition['key'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $endpoint
     * @param array<string, mixed> $definition
     * @param array<int, array<string, mixed>> $targets
     */
    private function sendRenewalNotification(
        WebhookNotificationSender $sender,
        ?array $endpoint,
        array $definition,
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
            build_lineworks_renewal_alert_payload((string) $definition['key'], $appPublicUrl, $targets)
        );
    }
}
