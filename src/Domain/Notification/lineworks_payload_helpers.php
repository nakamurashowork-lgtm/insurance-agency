<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use InvalidArgumentException;
use RuntimeException;

const LINEWORKS_MAX_DISPLAY_COUNT = 30;

function build_lineworks_absolute_url(string $appPublicUrl, string $route): string
{
    $baseUrl = rtrim(trim($appPublicUrl), '/');
    if ($baseUrl === '') {
        throw new RuntimeException('APP_PUBLIC_URL is not configured');
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts)) {
        throw new RuntimeException('APP_PUBLIC_URL is invalid');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($scheme === '' || $host === '') {
        throw new RuntimeException('APP_PUBLIC_URL must be an absolute URL');
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('APP_PUBLIC_URL must not use localhost');
    }

    return $baseUrl . '/?route=' . ltrim($route, '/');
}

function format_lineworks_short_date(string $date): string
{
    $normalized = trim($date);
    $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
    if ($dateTime === false) {
        return $normalized;
    }

    return $dateTime->format('n/j');
}

/**
 * @param array<int, array<string, mixed>> $targets
 */
function build_lineworks_renewal_alert_body_text(string $phaseName, array $targets, int $maxDisplayCount = LINEWORKS_MAX_DISPLAY_COUNT): string
{
    if (!in_array($phaseName, ['early', 'direct'], true)) {
        throw new InvalidArgumentException('unsupported renewal phase name: ' . $phaseName);
    }

    if (count($targets) === 0) {
        return '本日の対象案件はありません。';
    }

    $displayTargets = array_slice($targets, 0, $maxDisplayCount);
    $lines = [
        '要確認の満期案件があります。',
        '',
        sprintf('対象件数：%d件', count($targets)),
        '',
    ];

    foreach ($displayTargets as $target) {
        $customerName = trim(str_replace('（顧客未登録）', '', (string) ($target['customer_name'] ?? '')));
        $lines[] = sprintf(
            '%s %s様',
            format_lineworks_short_date((string) ($target['maturity_date'] ?? '')),
            $customerName
        );
    }

    $remaining = count($targets) - count($displayTargets);
    if ($remaining > 0) {
        $lines[] = sprintf('他%d件', $remaining);
    }

    return implode("\n", $lines);
}

/**
 * @param array<int, array<string, mixed>> $targets
 */
function build_lineworks_accident_reminder_body_text(array $targets, int $maxDisplayCount = LINEWORKS_MAX_DISPLAY_COUNT): string
{
    if (count($targets) === 0) {
        return '本日の対象案件はありません。';
    }

    $displayTargets = array_slice($targets, 0, $maxDisplayCount);
    $lines = [
        '要確認の事故案件があります。',
        '',
        sprintf('対象件数：%d件', count($targets)),
        '',
    ];

    foreach ($displayTargets as $target) {
        $customerName = trim(str_replace('（顧客未登録）', '', (string) ($target['customer_name'] ?? '')));
        $lines[] = sprintf(
            '%s %s様',
            format_lineworks_short_date((string) ($target['accident_date'] ?? '')),
            $customerName
        );
    }

    $remaining = count($targets) - count($displayTargets);
    if ($remaining > 0) {
        $lines[] = sprintf('他%d件', $remaining);
    }

    return implode("\n", $lines);
}

/**
 * @param array<int, array<string, mixed>> $targets
 * @return array<string, mixed>
 */
function build_lineworks_renewal_alert_payload(string $phaseName, string $appPublicUrl, array $targets, int $maxDisplayCount = LINEWORKS_MAX_DISPLAY_COUNT): array
{
    $title = match ($phaseName) {
        'early' => '【満期案件通知（早期）】',
        'direct' => '【満期案件通知（直前）】',
        default => throw new InvalidArgumentException('unsupported renewal phase name: ' . $phaseName),
    };

    return [
        'title' => $title,
        'body' => [
            'text' => build_lineworks_renewal_alert_body_text($phaseName, $targets, $maxDisplayCount),
        ],
        'button' => [
            'label' => '満期一覧を開く',
            'url' => build_lineworks_absolute_url($appPublicUrl, 'renewal/list'),
        ],
    ];
}

/**
 * @param array<int, array<string, mixed>> $targets
 * @return array<string, mixed>
 */
function build_lineworks_accident_reminder_payload(string $appPublicUrl, array $targets, int $maxDisplayCount = LINEWORKS_MAX_DISPLAY_COUNT): array
{
    return [
        'title' => '【事故対応リマインド】',
        'body' => [
            'text' => build_lineworks_accident_reminder_body_text($targets, $maxDisplayCount),
        ],
        'button' => [
            'label' => '事故案件一覧を開く',
            'url' => build_lineworks_absolute_url($appPublicUrl, 'accident/list'),
        ],
    ];
}