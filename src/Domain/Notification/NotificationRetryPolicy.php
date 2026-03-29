<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use DateTimeImmutable;
use InvalidArgumentException;

final class NotificationRetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $minRetryMinutes = 0
    ) {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1');
        }

        if ($this->minRetryMinutes < 0) {
            throw new InvalidArgumentException('minRetryMinutes must be zero or greater');
        }
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function minRetryMinutes(): int
    {
        return $this->minRetryMinutes;
    }

    /**
     * @param array<string, mixed> $delivery
     * @return array<string, mixed>
     */
    public function evaluate(array $delivery, DateTimeImmutable $now): array
    {
        $currentAttemptCount = $this->extractAttemptCount((string) ($delivery['error_message'] ?? ''));
        if ($currentAttemptCount >= $this->maxAttempts) {
            return [
                'allowed' => false,
                'reason' => 'max retry attempts reached',
                'current_attempt_count' => $currentAttemptCount,
                'next_attempt_count' => $currentAttemptCount,
            ];
        }

        $lastAttemptAt = $this->resolveLastAttemptAt($delivery);
        if ($lastAttemptAt !== null && $this->minRetryMinutes > 0) {
            $nextAllowedAt = $lastAttemptAt->modify('+' . $this->minRetryMinutes . ' minutes');
            if ($nextAllowedAt !== false && $nextAllowedAt > $now) {
                return [
                    'allowed' => false,
                    'reason' => 'retry backoff window not elapsed',
                    'current_attempt_count' => $currentAttemptCount,
                    'next_attempt_count' => $currentAttemptCount,
                    'next_allowed_at' => $nextAllowedAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        return [
            'allowed' => true,
            'current_attempt_count' => $currentAttemptCount,
            'next_attempt_count' => $currentAttemptCount + 1,
        ];
    }

    public function buildFailureMessage(string $message, int $attemptCount): string
    {
        $cleanMessage = trim((string) preg_replace('/^\[attempt:\d+\]\s*/', '', trim($message)));
        if ($cleanMessage === '') {
            $cleanMessage = 'delivery failed';
        }

        return sprintf('[attempt:%d] %s', max(1, $attemptCount), $cleanMessage);
    }

    public function extractAttemptCount(string $message): int
    {
        if (preg_match('/^\[attempt:(\d+)\]/', trim($message), $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function resolveLastAttemptAt(array $delivery): ?DateTimeImmutable
    {
        foreach (['notified_at', 'created_at'] as $field) {
            $value = trim((string) ($delivery[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $date = new DateTimeImmutable($value);
            return $date;
        }

        return null;
    }
}
