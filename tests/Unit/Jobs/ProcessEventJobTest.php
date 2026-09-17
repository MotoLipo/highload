<?php

namespace Tests\Unit\Jobs;

use App\DTO\EventDTO;
use App\Jobs\ProcessEventJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ProcessEventJobTest extends TestCase
{
    public function testItImplementsShouldQueue(): void
    {
        $job = new ProcessEventJob(new EventDTO('page_view', 1));

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function testHandlePushesTheJsonEncodedEventOntoTheConfiguredRedisBuffer(): void
    {
        config(['events.buffer_key' => 'analytics:events:buffer']);

        $event = new EventDTO('purchase', 55, ['amount' => 19.99]);

        Redis::shouldReceive('rpush')
            ->once()
            ->with('analytics:events:buffer', json_encode($event->toArray()))
            ->andReturn(1);

        (new ProcessEventJob($event))->handle();
    }

    public function testHandleReadsTheBufferKeyFromConfigInsteadOfHardcodingIt(): void
    {
        config(['events.buffer_key' => 'custom:buffer:key']);

        $event = new EventDTO('signup', 3);

        Redis::shouldReceive('rpush')
            ->once()
            ->with('custom:buffer:key', json_encode($event->toArray()))
            ->andReturn(1);

        (new ProcessEventJob($event))->handle();
    }

    public function testHandleDoesNotTouchRedisMoreThanOnce(): void
    {
        $event = new EventDTO('page_view', 9);

        Redis::shouldReceive('rpush')->once()->andReturn(1);

        (new ProcessEventJob($event))->handle();
    }
}
