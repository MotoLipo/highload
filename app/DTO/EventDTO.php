<?php

namespace App\DTO;

use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

readonly class EventDTO
{
    public string $id;

    public string $eventType;

    public int $userId;

    /** @var array<string, mixed> */
    public array $payload;

    public string $createdAt;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $eventType,
        int $userId,
        array $payload = [],
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->eventType = $eventType;
        $this->userId = $userId;
        $this->payload = $payload;
        $this->createdAt = Carbon::now()->toDateTimeString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->eventType,
            'user_id' => $this->userId,
            'payload' => json_encode($this->payload),
            'created_at' => $this->createdAt,
        ];
    }
}
