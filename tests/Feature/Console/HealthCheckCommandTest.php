<?php

namespace Tests\Feature\Console;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\TestCase;

class HealthCheckCommandTest extends TestCase
{
    public function testItPassesWhenTheHealthEndpointRespondsOk(): void
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('app:health-check')
            ->expectsOutput('Проверка здоровья пройдена успешно')
            ->assertExitCode(CommandAlias::SUCCESS);
    }

    public function testItFailsWhenTheStatusFieldIsNotOk(): void
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'degraded'], 200),
        ]);

        $this->artisan('app:health-check')
            ->expectsOutput('Проверка здоровья не пройдена')
            ->assertExitCode(CommandAlias::FAILURE);
    }

    public function testItFailsWhenTheHttpResponseIsNotSuccessful(): void
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'ok'], 500),
        ]);

        $this->artisan('app:health-check')
            ->expectsOutput('Проверка здоровья не пройдена')
            ->assertExitCode(CommandAlias::FAILURE);
    }

    public function testItFailsGracefullyWhenTheEndpointIsUnreachable(): void
    {
        // HealthCheckCommand теперь оборачивает Http::get() в try/catch, поэтому обрыв
        // соединения (например, приложение недоступно на localhost:8080) обрабатывается
        // так же, как и любой другой неуспешный health-check, а не падает необработанным
        // исключением.
        Http::fake([
            'http://localhost:8080/health' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->artisan('app:health-check')
            ->expectsOutput('Проверка здоровья не пройдена')
            ->assertExitCode(CommandAlias::FAILURE);
    }
}
