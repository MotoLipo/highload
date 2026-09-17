<?php

namespace App\Providers;

use App\Support\Prometheus\SharedCacheAdapter;
use ClickHouseDB\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Spatie\Prometheus\Facades\Prometheus;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $config = config('database.connections.clickhouse');

            return tap(new Client([
                'host'     => $config['host'],
                'port'     => $config['port'],
                'username' => $config['username'],
                'password' => (string) $config['password'],
                'database' => $config['database'],
            ]), function (Client $client) use ($config) {
                $client->database($config['database']);
            });
        });

        $this->app->scoped(CollectorRegistry::class, function () {
            $store = config('prometheus.cache');

            $adapter = $store === null
                ? new InMemory
                : new SharedCacheAdapter(Cache::store($store));

            return new CollectorRegistry($adapter, false);
        });
    }

    /**
     * Инициализация (бутстрап) сервисов приложения.
     */
    public function boot(): void
    {
        RateLimiter::for('events', function (Request $request) {
            return Limit::perMinute(60000)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Prometheus::addGauge(
            label: 'app_health',
            value: fn () => 1,
            helpText: 'Работоспособность приложения (1 = живо)'
        );
    }
}
