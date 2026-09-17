<?php

namespace Tests\Unit\Repositories;

use App\Repositories\AnalyticsRepository;
use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AnalyticsRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testPageViewsQueriesThePageViewsDailyMaterializedViewAndReturnsRawRows(): void
    {
        $rows = [
            ['date' => '2026-01-01', 'views' => 100, 'unique_visitors' => 40],
            ['date' => '2026-01-02', 'views' => 150, 'unique_visitors' => 55],
        ];

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn($rows);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')
            ->once()
            ->with(Mockery::on(function (string $sql) {
                return str_contains($sql, 'page_views_daily')
                    && str_contains($sql, 'INTERVAL 14 DAY');
            }))
            ->andReturn($statement);

        $result = (new AnalyticsRepository($client))->pageViews(14);

        $this->assertSame($rows, $result);
    }

    public function testConversionQueriesTheEventsTableAndReturnsRawRows(): void
    {
        $rows = [
            ['event_type' => 'page_view', 'users' => 80],
            ['event_type' => 'purchase', 'users' => 20],
        ];

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn($rows);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')
            ->once()
            ->with(Mockery::on(function (string $sql) {
                return str_contains($sql, 'analytics.events')
                    && str_contains($sql, 'INTERVAL 30 DAY');
            }))
            ->andReturn($statement);

        $result = (new AnalyticsRepository($client))->conversion(30);

        $this->assertSame($rows, $result);
    }
}
