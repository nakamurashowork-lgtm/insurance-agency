<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class ListViewHelper
{
    public const DEFAULT_PER_PAGE = 10;

    /**
     * @var array<int, int>
     */
    private const ALLOWED_PER_PAGE = [10, 50, 100];

    public static function normalizePage(mixed $value): int
    {
        $page = (int) $value;
        return $page > 0 ? $page : 1;
    }

    public static function normalizePerPage(mixed $value): int
    {
        $perPage = (int) $value;
        return in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * @param array<int, string> $allowed
     */
    public static function normalizeSort(mixed $value, array $allowed): string
    {
        $sort = trim((string) $value);
        return in_array($sort, $allowed, true) ? $sort : '';
    }

    public static function normalizeDirection(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function buildUrl(string $baseUrl, array $params = []): string
    {
        $query = http_build_query(self::filterQueryParams($params));
        if ($query === '') {
            return $baseUrl;
        }

        return $baseUrl . '&' . $query;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public static function hasActiveFilters(array $criteria): bool
    {
        foreach ($criteria as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPager(int $page, int $perPage, int $total): array
    {
        if ($perPage <= 0) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($totalPages === 0) {
            return [
                'currentPage' => 1,
                'totalPages' => 0,
                'start' => 0,
                'end' => 0,
                'pages' => [],
                'hasPrevious' => false,
                'hasNext' => false,
                'previousPage' => 1,
                'nextPage' => 1,
            ];
        }

        $currentPage = max(1, min($page, $totalPages));
        $start = (($currentPage - 1) * $perPage) + 1;
        $end = min($currentPage * $perPage, $total);

        $windowStart = max(1, $currentPage - 2);
        $windowEnd = min($totalPages, $windowStart + 4);
        $windowStart = max(1, $windowEnd - 4);

        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'start' => $start,
            'end' => $end,
            'pages' => range($windowStart, $windowEnd),
            'hasPrevious' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'previousPage' => $currentPage > 1 ? $currentPage - 1 : 1,
            'nextPage' => $currentPage < $totalPages ? $currentPage + 1 : $totalPages,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    private static function filterQueryParams(array $params): array
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || is_array($value)) {
                continue;
            }

            if (is_bool($value)) {
                $filtered[$key] = $value ? '1' : '0';
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            $filtered[$key] = $normalized;
        }

        return $filtered;
    }
}