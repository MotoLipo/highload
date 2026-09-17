<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricsRouteTest extends TestCase
{
    public function testItExposesPrometheusMetricsInTheTextExpositionFormat(): void
    {
        $response = $this->get('/metrics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $this->assertStringContainsString('app_app_health', $response->getContent());
    }
}
