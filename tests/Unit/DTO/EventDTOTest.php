<?php

namespace Tests\Unit\DTO;

use App\DTO\EventDTO;
use Error;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class EventDTOTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testConstructorSetsTheProvidedFields(): void
    {
        $dto = new EventDTO(eventType: 'purchase', userId: 42, payload: ['amount' => 9.99]);

        $this->assertSame('purchase', $dto->eventType);
        $this->assertSame(42, $dto->userId);
        $this->assertSame(['amount' => 9.99], $dto->payload);
    }

    public function testPayloadDefaultsToAnEmptyArray(): void
    {
        $dto = new EventDTO(eventType: 'signup', userId: 1);

        $this->assertSame([], $dto->payload);
    }

    public function testIdIsGeneratedAsAValidUuidV4(): void
    {
        $dto = new EventDTO('page_view', 1);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $dto->id
        );
    }

    public function testEachInstanceReceivesAUniqueId(): void
    {
        $first = new EventDTO('page_view', 1);
        $second = new EventDTO('page_view', 1);

        $this->assertNotSame($first->id, $second->id);
    }

    public function testCreatedAtReflectsTheCurrentTime(): void
    {
        Carbon::setTestNow('2026-01-15 10:30:00');

        $dto = new EventDTO('page_view', 1);

        $this->assertSame('2026-01-15 10:30:00', $dto->createdAt);
    }

    public function testToArrayReturnsTheExpectedShapeWithAJsonEncodedPayload(): void
    {
        Carbon::setTestNow('2026-01-15 10:30:00');

        $dto = new EventDTO('purchase', 7, ['amount' => 9.99, 'currency' => 'USD']);

        $this->assertSame([
            'id' => $dto->id,
            'event_type' => 'purchase',
            'user_id' => 7,
            'payload' => '{"amount":9.99,"currency":"USD"}',
            'created_at' => '2026-01-15 10:30:00',
        ], $dto->toArray());
    }

    public function testToArrayJsonEncodesAnEmptyPayloadAsAnEmptyJsonArray(): void
    {
        $dto = new EventDTO('signup', 3);

        $this->assertSame('[]', $dto->toArray()['payload']);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dto = new EventDTO('signup', 3);

        $this->expectException(Error::class);

        $dto->userId = 99;
    }
}
