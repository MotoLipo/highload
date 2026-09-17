<?php

namespace Tests\Unit\Services;

use App\DTO\EventDTO;
use App\Jobs\ProcessEventJob;
use App\Services\EventService;
use Illuminate\Support\Facades\Queue;
use ReflectionProperty;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    public function testHandleDispatchesAProcessEventJobBuiltFromTheGivenData(): void
    {
        Queue::fake();

        (new EventService)->handle([
            'event_type' => 'purchase',
            'user_id' => 42,
            'payload' => ['amount' => 9.99],
        ]);

        Queue::assertPushed(ProcessEventJob::class, function (ProcessEventJob $job) {
            $event = $this->extractEvent($job);

            return $event->eventType === 'purchase'
                && $event->userId === 42
                && $event->payload === ['amount' => 9.99];
        });
    }

    public function testHandleDefaultsPayloadToAnEmptyArrayWhenMissing(): void
    {
        Queue::fake();

        (new EventService)->handle([
            'event_type' => 'signup',
            'user_id' => 7,
        ]);

        Queue::assertPushed(ProcessEventJob::class, function (ProcessEventJob $job) {
            return $this->extractEvent($job)->payload === [];
        });
    }

    public function testHandleDispatchesExactlyOneJob(): void
    {
        Queue::fake();

        (new EventService)->handle([
            'event_type' => 'page_view',
            'user_id' => 1,
        ]);

        Queue::assertPushed(ProcessEventJob::class, 1);
    }

    private function extractEvent(ProcessEventJob $job): EventDTO
    {
        $property = new ReflectionProperty(ProcessEventJob::class, 'event');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
