<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\PrometheusMetricsMiddleware;
use App\Models\User;
use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PrometheusMetricsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function testItPassesTheResponseThroughUnchanged(): void
    {
        $middleware = new PrometheusMetricsMiddleware(app(CollectorRegistry::class));
        $expected = new Response('ok', 200);

        $response = $middleware->handle($this->requestFor('GET', 'api/events'), fn () => $expected);

        $this->assertSame($expected, $response);
    }

    public function testItRecordsARequestDurationHistogramSampleWithMethodRouteAndStatusLabels(): void
    {
        $registry = app(CollectorRegistry::class);
        $middleware = new PrometheusMetricsMiddleware($registry);

        $middleware->handle($this->requestFor('POST', 'api/events'), fn () => new Response('', 202));

        $rendered = (new RenderTextFormat)->render($registry->getMetricFamilySamples());

        $this->assertStringContainsString('app_http_duration_ms', $rendered);
        $this->assertStringContainsString('method="POST"', $rendered);
        $this->assertStringContainsString('route="api/events"', $rendered);
        $this->assertStringContainsString('status="202"', $rendered);
    }

    public function testItFallsBackToTheUnknownRouteLabelWhenNoRouteMatched(): void
    {
        $registry = app(CollectorRegistry::class);
        $middleware = new PrometheusMetricsMiddleware($registry);

        // Route resolver не настроен, поэтому $request->route() возвращает null.
        $request = Request::create('/no-route-matched', 'GET');

        $middleware->handle($request, fn () => new Response('', 404));

        $rendered = (new RenderTextFormat)->render($registry->getMetricFamilySamples());

        $this->assertStringContainsString('route="unknown"', $rendered);
    }

    public function testItRecordsARequestCounterSampleWithMethodRouteAndStatusLabels(): void
    {
        $registry = app(CollectorRegistry::class);
        $middleware = new PrometheusMetricsMiddleware($registry);

        $middleware->handle($this->requestFor('POST', 'api/events'), fn () => new Response('', 202));

        $rendered = (new RenderTextFormat)->render($registry->getMetricFamilySamples());

        $this->assertStringContainsString(
            'app_http_requests_total{method="POST",route="api/events",status="202"} 1',
            $rendered
        );
    }

    public function testItAccumulatesCounterValueAcrossMultipleRequests(): void
    {
        $registry = app(CollectorRegistry::class);
        $middleware = new PrometheusMetricsMiddleware($registry);

        $middleware->handle($this->requestFor('GET', 'api/analytics/page-views'), fn () => new Response('', 200));
        $middleware->handle($this->requestFor('GET', 'api/analytics/page-views'), fn () => new Response('', 200));
        $middleware->handle($this->requestFor('GET', 'api/analytics/page-views'), fn () => new Response('', 200));

        $rendered = (new RenderTextFormat)->render($registry->getMetricFamilySamples());

        $this->assertStringContainsString(
            'app_http_requests_total{method="GET",route="api/analytics/page-views",status="200"} 3',
            $rendered
        );
    }

    public function testMetricsEndpointStaysHealthyAfterARequestGoesThroughTheMiddleware(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn([]);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')->once()->andReturn($statement);
        $this->app->instance(Client::class, $client);

        // /api/analytics/page-views проходит через реальный HTTP-стек, включая
        // PrometheusMetricsMiddleware. Раньше после такого запроса GET /metrics падал
        // с 500 (см. докблок PrometheusMetricsMiddleware - баг с "голым" начальным
        // значением счётчика без лейблов, уже неактуален после перехода на прямую
        // запись в CollectorRegistry).
        $this->getJson('/api/analytics/page-views')->assertOk();

        $response = $this->get('/metrics');

        $response->assertOk();
        $this->assertStringContainsString(
            'method="GET",route="api/analytics/page-views",status="200"',
            $response->getContent()
        );
    }

    public function testRateLimitedResponsesAreStillRecordedBecauseTheMiddlewareIsGlobal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(429);

        $rendered = $this->get('/metrics')->getContent();

        $this->assertStringContainsString(
            'app_http_requests_total{method="POST",route="api/login",status="429"} 1',
            $rendered
        );
    }

    private function requestFor(string $method, string $uri): Request
    {
        $request = Request::create('/'.ltrim($uri, '/'), $method);
        $request->setRouteResolver(fn () => new Route([$method], $uri, []));

        return $request;
    }
}
