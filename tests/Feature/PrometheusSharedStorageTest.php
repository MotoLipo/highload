<?php

namespace Tests\Feature;

use App\Support\Prometheus\SharedCacheAdapter;
use Illuminate\Support\Facades\Cache;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Spatie\Prometheus\Adapters\LaravelCacheAdapter;
use Tests\TestCase;

/**
 * Приложение работает на Octane (несколько воркер-процессов), у каждого свой
 * PHP-процесс и своя память. Эти тесты проверяют механизм, который решает
 * проблему "метрики каждого воркера видны только ему самому" -
 * App\Support\Prometheus\SharedCacheAdapter, используемый при
 * PROMETHEUS_CACHE_STORE=redis (см. config/prometheus.php,
 * AppServiceProvider::register()). Два раздельных экземпляра CollectorRegistry
 * здесь имитируют два разных воркера.
 */
class PrometheusSharedStorageTest extends TestCase
{
    public function testMetricsRecordedByOneRegistryInstanceAreVisibleFromAnother(): void
    {
        $store = Cache::store('array');

        $registryForWorkerA = new CollectorRegistry(new SharedCacheAdapter($store), false);
        $registryForWorkerB = new CollectorRegistry(new SharedCacheAdapter($store), false);

        $registryForWorkerA
            ->getOrRegisterCounter('app', 'shared_test_counter', 'help text', ['label'])
            ->incBy(3, ['value']);

        $rendered = (new RenderTextFormat)->render($registryForWorkerB->getMetricFamilySamples());

        $this->assertStringContainsString('app_shared_test_counter{label="value"} 3', $rendered);
    }

    /**
     * Контрольный тест: без общего хранилища (как было раньше, cache => null)
     * второй "воркер" вообще не видит счётчик первого - показывает, что фикс
     * выше действительно что-то меняет, а не просто и так работал бы.
     */
    public function testWithoutASharedStoreEachRegistryInstanceIsIsolated(): void
    {
        $registryForWorkerA = new CollectorRegistry(new InMemory, false);
        $registryForWorkerB = new CollectorRegistry(new InMemory, false);

        $registryForWorkerA
            ->getOrRegisterCounter('app', 'isolated_test_counter', 'help text', ['label'])
            ->incBy(3, ['value']);

        $rendered = (new RenderTextFormat)->render($registryForWorkerB->getMetricFamilySamples());

        $this->assertStringNotContainsString('isolated_test_counter', $rendered);
    }

    /**
     * Пин известного бага в spatie/laravel-prometheus 1.6.1 (см. докблок
     * SharedCacheAdapter): "родной" LaravelCacheAdapter корректно ЗАПИСЫВАЕТ
     * данные в кэш (это можно увидеть напрямую через Cache::get), но
     * collect() их не подгружает обратно, поэтому второй "воркер" при
     * рендере всё равно ничего не видит. Если апстрим когда-нибудь починит
     * этот баг, этот тест начнёт падать - будет сигналом, что SharedCacheAdapter
     * можно упростить обратно до LaravelCacheAdapter.
     */
    public function testUnpatchedVendorAdapterWritesToTheSharedStoreButFailsToReadItBackOnCollect(): void
    {
        $store = Cache::store('array');

        $registryForWorkerA = new CollectorRegistry(new LaravelCacheAdapter($store), false);
        $registryForWorkerB = new CollectorRegistry(new LaravelCacheAdapter($store), false);

        $registryForWorkerA
            ->getOrRegisterCounter('app', 'vendor_bug_counter', 'help text', ['label'])
            ->incBy(3, ['value']);

        // Запись реально произошла - в кэше данные есть.
        $this->assertNotEmpty($store->get('PROMETHEUS_counter_METRICS'));

        // Но collect() второго воркера их всё равно не подхватывает.
        $rendered = (new RenderTextFormat)->render($registryForWorkerB->getMetricFamilySamples());
        $this->assertStringNotContainsString('vendor_bug_counter', $rendered);
    }
}
