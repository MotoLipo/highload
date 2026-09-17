<?php

namespace App\Services;

use App\DTO\EventDTO;
use App\Jobs\ProcessEventJob;

class EventService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): void
    {
        $event = new EventDTO(
            eventType: $data['event_type'],
            userId: $data['user_id'],
            payload: $data['payload'] ?? []
        );

        ProcessEventJob::dispatch($event);
    }
}
