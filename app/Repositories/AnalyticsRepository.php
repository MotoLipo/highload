<?php

namespace App\Repositories;

use ClickHouseDB\Client;

class AnalyticsRepository
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pageViews(int $days): array
    {
        return $this->client->select(
            "SELECT
                date,
                countMerge(views) AS views,
                uniqMerge(unique_visitors) AS unique_visitors
             FROM analytics.page_views_daily
             WHERE date >= today() - INTERVAL {$days} DAY
             GROUP BY date
             ORDER BY date"
        )->rows();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function conversion(int $days): array
    {
        return $this->client->select(
            "SELECT
                event_type,
                uniqExact(user_id) AS users
             FROM analytics.events
             WHERE created_at >= now() - INTERVAL {$days} DAY
             GROUP BY event_type"
        )->rows();
    }
}
