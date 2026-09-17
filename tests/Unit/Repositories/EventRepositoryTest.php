<?php

namespace Tests\Unit\Repositories;

use App\Repositories\EventRepository;
use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class EventRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testBulkInsertForwardsEventsToTheClickhouseClientWithTheExpectedColumns(): void
    {
        $events = [
            [
                'id' => 'b3b7c6d0-1234-4d56-8a90-abcdefabcdef',
                'event_type' => 'page_view',
                'user_id' => 1,
                'payload' => '[]',
                'created_at' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 'c4c8d7e1-2345-4e67-9ba1-bcdefabcdef0',
                'event_type' => 'purchase',
                'user_id' => 2,
                'payload' => '{"amount":9.99}',
                'created_at' => '2026-01-01 00:00:01',
            ],
        ];

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('insert')
            ->once()
            ->with('events', $events, ['id', 'event_type', 'user_id', 'payload', 'created_at'])
            ->andReturn(Mockery::mock(Statement::class));

        (new EventRepository($client))->bulkInsert($events);
    }

    public function testBulkInsertForwardsAnEmptyBatchAsIs(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('insert')
            ->once()
            ->with('events', [], ['id', 'event_type', 'user_id', 'payload', 'created_at'])
            ->andReturn(Mockery::mock(Statement::class));

        (new EventRepository($client))->bulkInsert([]);
    }
}
