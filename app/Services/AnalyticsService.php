<?php

namespace App\Services;

use App\Repositories\AnalyticsRepository;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    public function __construct(private readonly AnalyticsRepository $repository) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pageViews(int $days): array
    {
        $days = $this->clampDays($days);

        return Cache::remember(
            "analytics:page-views:{$days}",
            60,
            fn () => $this->repository->pageViews($days)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function conversion(int $days): array
    {
        $days = $this->clampDays($days);

        return Cache::remember(
            "analytics:conversion:{$days}",
            60,
            function () use ($days) {
                $rows = $this->repository->conversion($days);
                $total = array_sum(array_column($rows, 'users'));

                $funnel = $total > 0
                    ? array_map(
                        static fn (array $row) => [
                            'event_type' => $row['event_type'],
                            'users' => (int) $row['users'],
                            'percent' => round($row['users'] / $total * 100, 2),
                        ],
                        $rows
                    )
                    : [];

                return ['funnel' => $funnel, 'total_users' => (int) $total];
            }
        );
    }

    private function clampDays(int $days): int
    {
        return min(max($days, 1), 90);
    }
}
