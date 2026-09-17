<?php

namespace Tests\Unit\Services;

use App\Repositories\AnalyticsRepository;
use App\Services\AnalyticsService;
use Mockery;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    public function testPageViewsClampsDaysBelowTheMinimumToOne(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('pageViews')->once()->with(1)->andReturn([]);

        (new AnalyticsService($repository))->pageViews(0);
    }

    public function testPageViewsClampsDaysAboveTheMaximumToNinety(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('pageViews')->once()->with(90)->andReturn([]);

        (new AnalyticsService($repository))->pageViews(365);
    }

    public function testPageViewsReturnsWhateverTheRepositoryReturns(): void
    {
        $rows = [['date' => '2026-01-01', 'views' => 10, 'unique_visitors' => 5]];

        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('pageViews')->once()->with(7)->andReturn($rows);

        $result = (new AnalyticsService($repository))->pageViews(7);

        $this->assertSame($rows, $result);
    }

    public function testPageViewsIsCachedForTheSameClampedDaysValue(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('pageViews')->once()->with(5)->andReturn([]);

        $service = new AnalyticsService($repository);
        $service->pageViews(5);
        $service->pageViews(5);
        $service->pageViews(5);
    }

    public function testConversionClampsDaysAboveTheMaximumToNinety(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('conversion')->once()->with(90)->andReturn([]);

        (new AnalyticsService($repository))->conversion(999);
    }

    public function testConversionComputesPercentagesPerEventType(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('conversion')->once()->with(30)->andReturn([
            ['event_type' => 'page_view', 'users' => 80],
            ['event_type' => 'purchase', 'users' => 20],
        ]);

        $result = (new AnalyticsService($repository))->conversion(30);

        $this->assertSame([
            'funnel' => [
                ['event_type' => 'page_view', 'users' => 80, 'percent' => 80.0],
                ['event_type' => 'purchase', 'users' => 20, 'percent' => 20.0],
            ],
            'total_users' => 100,
        ], $result);
    }

    public function testConversionAvoidsDivisionByZeroWhenThereAreNoUsers(): void
    {
        $repository = Mockery::mock(AnalyticsRepository::class);
        $repository->shouldReceive('conversion')->once()->andReturn([]);

        $result = (new AnalyticsService($repository))->conversion(7);

        $this->assertSame(['funnel' => [], 'total_users' => 0], $result);
    }
}
