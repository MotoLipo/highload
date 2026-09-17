<?php

namespace App\Repositories;

use ClickHouseDB\Client;

class EventRepository
{
    public function __construct(private readonly Client $client) {}

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function bulkInsert(array $events): void
    {
        $this->client->insert('events', $events, [
            'id',
            'event_type',
            'user_id',
            'payload',
            'created_at',
        ]);
    }
}
