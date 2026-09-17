<?php

namespace Tests\Feature\Http\Controllers;

use App\DTO\EventDTO;
use App\Jobs\ProcessEventJob;
use Illuminate\Support\Facades\Queue;
use ReflectionProperty;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    public function testItAcceptsAValidEventAndDispatchesAJob(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', [
            'event_type' => 'page_view',
            'user_id' => 5,
            'payload' => ['url' => '/home'],
        ]);

        $response->assertStatus(202)
            ->assertExactJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessEventJob::class, function (ProcessEventJob $job) {
            $event = $this->extractEvent($job);

            return $event->eventType === 'page_view'
                && $event->userId === 5
                && $event->payload === ['url' => '/home'];
        });
    }

    public function testItDefaultsThePayloadToAnEmptyArrayWhenOmitted(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', [
            'event_type' => 'signup',
            'user_id' => 1,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(ProcessEventJob::class, function (ProcessEventJob $job) {
            return $this->extractEvent($job)->payload === [];
        });
    }

    public function testItRejectsAMissingEventType(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', ['user_id' => 1]);

        $response->assertStatus(422)->assertJsonValidationErrors(['event_type']);
        Queue::assertNotPushed(ProcessEventJob::class);
    }

    public function testItRejectsAnUnknownEventType(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', ['event_type' => 'not-a-real-type', 'user_id' => 1]);

        $response->assertStatus(422)->assertJsonValidationErrors(['event_type']);
        Queue::assertNotPushed(ProcessEventJob::class);
    }

    public function testItRejectsAMissingUserId(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', ['event_type' => 'page_view']);

        $response->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        Queue::assertNotPushed(ProcessEventJob::class);
    }

    public function testItRejectsANonPositiveUserId(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', ['event_type' => 'page_view', 'user_id' => 0]);

        $response->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        Queue::assertNotPushed(ProcessEventJob::class);
    }

    public function testItRejectsANonArrayPayload(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/events', [
            'event_type' => 'page_view',
            'user_id' => 1,
            'payload' => 'not-an-array',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payload']);
        Queue::assertNotPushed(ProcessEventJob::class);
    }

    public function testItAllowsCrossOriginRequestsFromAnyOriginBecauseItsAPublicBeaconEndpoint(): void
    {
        Queue::fake();

        $response = $this->withHeader('Origin', 'https://example-third-party-site.com')
            ->postJson('/api/events', ['event_type' => 'page_view', 'user_id' => 1]);

        $response->assertStatus(202)
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    private function extractEvent(ProcessEventJob $job): EventDTO
    {
        $property = new ReflectionProperty(ProcessEventJob::class, 'event');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
