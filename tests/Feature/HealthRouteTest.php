<?php

namespace Tests\Feature;

use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HealthRouteTest extends TestCase
{
    public function testItReturnsOkWhenCacheAndClickhouseBothSucceed(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')->once()->with('SELECT 1')->andReturn(Mockery::mock(Statement::class));
        $this->app->instance(Client::class, $client);

        $response = $this->getJson('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function testItReturnsDegradedWhenClickhouseThrows(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')->once()->andThrow(new RuntimeException('ClickHouse is down'));
        $this->app->instance(Client::class, $client);

        $response = $this->getJson('/health');

        $response->assertStatus(503)->assertExactJson([
            'status' => 'degraded',
            'error' => 'ClickHouse is down',
        ]);
    }

    public function testItReturnsDegradedWhenTheCacheBackendThrows(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('health-check')
            ->andThrow(new RuntimeException('Redis is down'));

        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('select');
        $this->app->instance(Client::class, $client);

        $response = $this->getJson('/health');

        $response->assertStatus(503)->assertExactJson([
            'status' => 'degraded',
            'error' => 'Redis is down',
        ]);
    }
}
