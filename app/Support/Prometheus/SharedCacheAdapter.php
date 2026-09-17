<?php

namespace App\Support\Prometheus;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Summary;
use Spatie\Prometheus\Adapters\LaravelCacheAdapter;

/**
 * Патч известного бага в spatie/laravel-prometheus 1.6.1:
 * Spatie\Prometheus\Adapters\LaravelCacheAdapter::collect() вызывает
 * $this->fetch($type) для каждого типа метрик, но результат никуда не
 * присваивает - родительский Prometheus\Storage\InMemory::collect() в итоге
 * читает пустые $this->counters/$this->gauges/$this->histograms/$this->summaries
 * вместо реально сохранённых в кэше данных.
 *
 * Из-за этого запись метрик (updateCounter/updateGauge/...) в Redis работает
 * корректно, а вот чтение при рендере /metrics - нет: каждый воркер видит
 * только то, что сам туда записал в рамках collect() (то есть обычно ничего,
 * это read-only вызов). Проверено тестами
 * (tests/Feature/PrometheusSharedStorageTest.php) и вживую через docker compose.
 */
class SharedCacheAdapter extends LaravelCacheAdapter
{
    public function collect(bool $sortMetrics = true): array
    {
        $this->counters = $this->fetch(Counter::TYPE);
        $this->gauges = $this->fetch(Gauge::TYPE);
        $this->histograms = $this->fetch(Histogram::TYPE);
        $this->summaries = $this->fetch(Summary::TYPE);

        return parent::collect($sortMetrics);
    }
}
