<?php

namespace App\Console\Commands;

use App\Repositories\EventRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use JsonException;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

#[Signature('events:flush-buffer
    {--once : Обработать один батч и выйти (для тестов и разового запуска)}
    {--max-time=0 : Остановиться через N секунд, чтобы процесс мог быть перезапущен (0 = работать бесконечно)}')]
#[Description('Непрерывно переносит буферизированные события из Redis в ClickHouse батчами')]
class FlushEventsBufferCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly EventRepository $repository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->trap([SIGTERM, SIGINT], function () {
            $this->shouldStop = true;
        });

        $key = config('events.buffer_key');
        $processingKey = $key.'processing';
        $batchSize = (int) config('events.batch_size');
        $maxTime = (int) $this->option('max-time');
        $startedAt = microtime(true);
        $hadFailure = false;

        if (! $this->recoverLeftovers($processingKey)) {
            $hadFailure = true;
        }

        do {
            $batch = $this->collectBatch($key, $processingKey, $batchSize);

            if (! empty($batch)) {
                if (! $this->flushBatch($batch, $processingKey)) {
                    $hadFailure = true;
                }
            } else {
                $this->handleEmptyBuffer();
            }
        } while (
            ! $this->option('once')
            && ! $this->shouldStop
            && ! $this->maxTimeReached($maxTime, $startedAt)
        );

        return $hadFailure ? CommandAlias::FAILURE : CommandAlias::SUCCESS;
    }

    /**
     * Если предыдущий запуск упал в flushBatch() и не успел очистить processing-ключ,
     * эти события никуда не делись - забираем и пробуем отправить их снова, прежде
     * чем брать новые из основного буфера.
     */
    private function recoverLeftovers(string $processingKey): bool
    {
        $leftover = Redis::lrange($processingKey, 0, -1);

        if (! is_array($leftover) || empty($leftover)) {
            return true;
        }

        $this->warn(count($leftover).' событий осталось с предыдущего запуска, повторяем отправку');

        return $this->flushBatch(array_values($leftover), $processingKey);
    }

    /**
     * Тянет из буфера, пока не наберёт $batchSize элементов либо буфер не опустеет.
     * Никаких искусственных пауз в ожидании "добора" батча - под нагрузкой буфер и
     * так почти всегда полон, а под низкой нагрузкой мелкие батчи не проблема.
     *
     * @return list<string>
     */
    private function collectBatch(string $key, string $processingKey, int $batchSize): array
    {
        $batch = [];

        while (count($batch) < $batchSize) {
            $item = Redis::rpoplpush($key, $processingKey);

            if ($item === false || $item === null) {
                break;
            }

            $batch[] = $item;
        }

        return $batch;
    }

    /**
     * @param  list<string>  $batch
     */
    private function flushBatch(array $batch, string $processingKey): bool
    {
        $events = $this->decodeEvents($batch);

        if (empty($events)) {
            Redis::del($processingKey);

            return true;
        }

        try {
            retry(3, fn () => $this->repository->bulkInsert($events), 200);
        } catch (Throwable $e) {
            Log::error('Не удалось отправить буфер событий в ClickHouse', [
                'events_count' => count($events),
                'exception' => $e->getMessage(),
            ]);
            $this->error("Не удалось отправить батч: {$e->getMessage()}. Событий в буфере для повтора: ".count($events));

            return false;
        }

        Redis::del($processingKey);
        $this->info('Отправлено событий: '.count($events));

        return true;
    }

    /**
     * json_decode() отдельного элемента не должен ронять весь батч/процесс:
     * повреждённые записи логируются и отбрасываются, остальные обрабатываются
     * как обычно.
     *
     * @param  list<string>  $batch
     * @return list<array<string, mixed>>
     */
    private function decodeEvents(array $batch): array
    {
        $events = [];

        foreach ($batch as $item) {
            try {
                $events[] = json_decode($item, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                Log::error('Отбрасываем повреждённое событие из буфера (невалидный JSON)', [
                    'raw' => $item,
                    'exception' => $e->getMessage(),
                ]);
                $this->warn('Событие отброшено, не удалось раскодировать JSON: '.$e->getMessage());
            }
        }

        return $events;
    }

    private function handleEmptyBuffer(): void
    {
        if ($this->option('once')) {
            $this->info('Буфер пуст');

            return;
        }

        usleep(50_000);
    }

    private function maxTimeReached(int $maxTime, float $startedAt): bool
    {
        if ($maxTime <= 0) {
            return false;
        }

        if ((microtime(true) - $startedAt) < $maxTime) {
            return false;
        }

        $this->info('Достигнут лимит времени работы, завершаемся для перезапуска');

        return true;
    }
}
