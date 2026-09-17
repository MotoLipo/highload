<?php

namespace App\Jobs;

use App\DTO\EventDTO;
use App\Repositories\EventRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

class ProcessEventJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly EventDTO $event
    ) {}

    public function handle(): void
    {
        Redis::rpush(
            config('events.buffer_key'),
            json_encode($this->event->toArray())
        );
    }
}
