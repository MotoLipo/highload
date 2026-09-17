<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Prometheus\CollectorRegistry;

class PrometheusMetricsMiddleware
{
    public function __construct(
        protected CollectorRegistry $collectorRegistry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->collectorRegistry
            ->getOrRegisterCounter(
                'app',
                'http_requests_total',
                'Общее количество HTTP-запросов',
                ['method', 'route', 'status'],
            )
            ->inc([
                $request->method(),
                $request->route()?->uri() ?? 'unknown',
                $response->getStatusCode(),
            ]);

        $this->collectorRegistry
            ->getOrRegisterHistogram(
                'app',
                'http_duration_ms',
                'Длительность HTTP-запроса в миллисекундах',
                ['method', 'route', 'status'],
                [5, 10, 25, 50, 100, 250, 500, 1000, 2500],
            )
            ->observe($duration, [
                $request->method(),
                $request->route()?->uri() ?? 'unknown',
                $response->getStatusCode(),
            ]);

        return $response;
    }
}