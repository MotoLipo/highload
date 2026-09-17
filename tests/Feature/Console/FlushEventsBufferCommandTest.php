<?php

namespace Tests\Feature\Console;

use App\Repositories\EventRepository;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\TestCase;

class FlushEventsBufferCommandTest extends TestCase
{
    private const string KEY = 'analytics:events:buffer';

    private const string PROCESSING_KEY = 'analytics:events:bufferprocessing';

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.buffer_key' => self::KEY]);
    }

    public function testItReportsAnEmptyBufferAndNeverTouchesTheRepository(): void
    {
        $this->expectNoLeftovers();

        // На пустом списке phpredis возвращает bool(false), а не null - это
        // подтверждено вживую через docker compose, поэтому мок повторяет
        // именно такое поведение, а не "теоретическое".
        Redis::shouldReceive('rpoplpush')
            ->once()
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn(false);
        Redis::shouldReceive('del')->never();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldNotReceive('bulkInsert');
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutput('Буфер пуст')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    public function testItFlushesAllBufferedEventsIncludingTheFirstPoppedOneToTheRepository(): void
    {
        config(['events.batch_size' => 5000]);
        $this->expectNoLeftovers();

        $itemA = json_encode(['id' => 'a', 'event_type' => 'page_view', 'user_id' => 1, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:00']);
        $itemB = json_encode(['id' => 'b', 'event_type' => 'purchase', 'user_id' => 2, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:01']);

        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn($itemA, $itemB, false);

        Redis::shouldReceive('del')->once()->with(self::PROCESSING_KEY);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')
            ->once()
            ->with([json_decode($itemA, true), json_decode($itemB, true)]);
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutput('Отправлено событий: 2')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    public function testItStopsCollectingOnceTheConfiguredBatchSizeIsReachedWithoutAnExtraRedisCall(): void
    {
        config(['events.batch_size' => 2]);
        $this->expectNoLeftovers();

        $itemA = json_encode(['id' => 'a']);
        $itemB = json_encode(['id' => 'b']);

        // A -> B: после второго элемента размер батча (2) достигает batch_size (2),
        // третьего вызова rpoplpush быть не должно.
        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->times(2)
            ->andReturn($itemA, $itemB);

        Redis::shouldReceive('del')->once()->with(self::PROCESSING_KEY);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')
            ->once()
            ->with([json_decode($itemA, true), json_decode($itemB, true)]);
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutput('Отправлено событий: 2')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    public function testItRetriesLeftoverEventsFromAPreviousFailedRunBeforeTakingNewOnes(): void
    {
        config(['events.batch_size' => 5000]);

        $leftoverItem = json_encode(['id' => 'leftover', 'event_type' => 'page_view', 'user_id' => 9, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:00']);
        $newItem = json_encode(['id' => 'new', 'event_type' => 'signup', 'user_id' => 10, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:01']);

        // processing-ключ не пуст - это "хвост" от упавшего предыдущего запуска.
        Redis::shouldReceive('lrange')
            ->once()
            ->with(self::PROCESSING_KEY, 0, -1)
            ->andReturn([$leftoverItem]);

        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn($newItem, false);

        // Два отдельных вызова bulkInsert: сначала "хвост", потом обычный батч.
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')->once()->with([json_decode($leftoverItem, true)]);
        $repository->shouldReceive('bulkInsert')->once()->with([json_decode($newItem, true)]);
        $this->app->instance(EventRepository::class, $repository);

        Redis::shouldReceive('del')->twice()->with(self::PROCESSING_KEY);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutputToContain('1 событий осталось с предыдущего запуска')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    public function testItKeepsEventsInProcessingAndReturnsFailureWhenClickhouseKeepsFailing(): void
    {
        config(['events.batch_size' => 5000]);
        $this->expectNoLeftovers();

        $item = json_encode(['id' => 'a', 'event_type' => 'page_view', 'user_id' => 1, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:00']);

        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn($item, false);

        // processing НЕ должен очищаться, если вставка так и не удалась.
        Redis::shouldReceive('del')->never();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')
            ->times(3) // retry(3, ...) - все три попытки проваливаются
            ->andThrow(new RuntimeException('ClickHouse is down'));
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutputToContain('Не удалось отправить батч: ClickHouse is down')
            ->assertExitCode(CommandAlias::FAILURE);
    }

    public function testItRecoversAfterATransientClickhouseFailureViaRetry(): void
    {
        config(['events.batch_size' => 5000]);
        $this->expectNoLeftovers();

        $item = json_encode(['id' => 'a', 'event_type' => 'page_view', 'user_id' => 1, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:00']);

        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn($item, false);

        Redis::shouldReceive('del')->once()->with(self::PROCESSING_KEY);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')
            ->once()
            ->andThrow(new RuntimeException('temporary network blip'));
        $repository->shouldReceive('bulkInsert')
            ->once()
            ->andReturn(null); // вторая попытка retry() успешна
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutput('Отправлено событий: 1')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    /**
     * Регрессионный тест на баг, реально пойманный на живом docker compose:
     * Redis::rpoplpush() на пустом списке возвращает bool(false), а не null.
     * Из-за неверной проверки (=== null) "false" ошибочно попадал в батч,
     * приводя к json_decode(false) -> JsonException -> падению всего процесса
     * без возможности восстановления (processing никогда не чистился).
     */
    public function testACorruptedNonJsonItemIsDroppedInsteadOfCrashingTheProcess(): void
    {
        config(['events.batch_size' => 5000]);
        $this->expectNoLeftovers();

        $validItem = json_encode(['id' => 'a', 'event_type' => 'page_view', 'user_id' => 1, 'payload' => '[]', 'created_at' => '2026-01-01 00:00:00']);

        Redis::shouldReceive('rpoplpush')
            ->with(self::KEY, self::PROCESSING_KEY)
            ->andReturn($validItem, 'not-valid-json{{{', false);

        Redis::shouldReceive('del')->once()->with(self::PROCESSING_KEY);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('bulkInsert')
            ->once()
            ->with([json_decode($validItem, true)]); // повреждённый элемент отброшен, не крашит остальное
        $this->app->instance(EventRepository::class, $repository);

        $this->artisan('events:flush-buffer --once')
            ->expectsOutputToContain('Событие отброшено')
            ->expectsOutput('Отправлено событий: 1')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    private function expectNoLeftovers(): void
    {
        Redis::shouldReceive('lrange')
            ->once()
            ->with(self::PROCESSING_KEY, 0, -1)
            ->andReturn([]);
    }
}
