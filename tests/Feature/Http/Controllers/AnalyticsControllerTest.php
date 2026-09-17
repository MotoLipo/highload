<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Сквозные (end-to-end) тесты на весь стек: роут -> AnalyticsController ->
 * AnalyticsService -> AnalyticsRepository -> ClickHouseDB\Client -> JSON-ответ.
 * Детальная бизнес-логика (клэмпинг days, кэширование, расчёт процентов,
 * деление на ноль) уже покрыта юнит-тестами AnalyticsServiceTest - здесь её
 * заново не дублируем, только проверяем, что весь стек реально связан вместе.
 */
class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testPageViewsRequiresAuthentication(): void
    {
        $this->getJson('/api/analytics/page-views')->assertStatus(401);
    }

    public function testConversionRequiresAuthentication(): void
    {
        $this->getJson('/api/analytics/conversion')->assertStatus(401);
    }

    /**
     * Регрессионный тест на баг, реально пойманный на живом docker compose:
     * getJson() в тестах сам подставляет заголовок Accept: application/json,
     * из-за чего Laravel сразу отдаёт 401 JSON-ответом. Обычный curl (и вообще
     * любой клиент) без этого заголовка раньше улетал в 500, потому что
     * auth:sanctum пытался редиректнуть гостя на route('login'), которого
     * не существует (API-only приложение, страницы логина нет) - см.
     * bootstrap/app.php -> $middleware->redirectGuestsTo(null).
     */
    public function testUnauthenticatedRequestWithoutAcceptHeaderReturns401NotA500(): void
    {
        $this->get('/api/analytics/page-views')->assertStatus(401);
    }

    public function testPageViewsReturnsDataFromTheFullStack(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $rows = [
            ['date' => '2026-01-01', 'views' => 100, 'unique_visitors' => 40],
            ['date' => '2026-01-02', 'views' => 150, 'unique_visitors' => 55],
        ];

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn($rows);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn (string $sql) => str_contains($sql, 'INTERVAL 14 DAY')))
            ->andReturn($statement);
        $this->app->instance(Client::class, $client);

        $response = $this->getJson('/api/analytics/page-views?days=14');

        $response->assertOk()->assertExactJson(['data' => $rows]);
    }

    public function testPageViewsUsesTheDefaultOfSevenDaysWhenNotSpecified(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn([]);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn (string $sql) => str_contains($sql, 'INTERVAL 7 DAY')))
            ->andReturn($statement);
        $this->app->instance(Client::class, $client);

        $this->getJson('/api/analytics/page-views')->assertOk();
    }

    public function testConversionReturnsDataFromTheFullStack(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $rows = [
            ['event_type' => 'page_view', 'users' => 80],
            ['event_type' => 'purchase', 'users' => 20],
        ];

        $statement = Mockery::mock(Statement::class);
        $statement->shouldReceive('rows')->once()->andReturn($rows);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn (string $sql) => str_contains($sql, 'analytics.events')))
            ->andReturn($statement);
        $this->app->instance(Client::class, $client);

        $response = $this->getJson('/api/analytics/conversion?days=30');

        $response->assertOk()->assertExactJson([
            'data' => [
                'funnel' => [
                    ['event_type' => 'page_view', 'users' => 80, 'percent' => 80.0],
                    ['event_type' => 'purchase', 'users' => 20, 'percent' => 20.0],
                ],
                'total_users' => 100,
            ],
        ]);
    }
}
