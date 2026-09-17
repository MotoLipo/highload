<?php

namespace Tests\Feature\Console;

use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Mockery;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Tests\TestCase;

class ClickHouseMigrateCommandTest extends TestCase
{
    public function testItCreatesTheDatabaseAndRunsEverySqlMigrationFile(): void
    {
        $files = glob(database_path('clickhouse/*.sql'));
        $this->assertNotEmpty($files, 'Expected at least one *.sql file under database/clickhouse.');

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('write')
            ->once()
            ->with('CREATE DATABASE IF NOT EXISTS analytics')
            ->andReturn(Mockery::mock(Statement::class));

        foreach ($files as $file) {
            $client->shouldReceive('write')
                ->once()
                ->with(file_get_contents($file))
                ->andReturn(Mockery::mock(Statement::class));
        }

        $this->app->instance(Client::class, $client);

        $command = $this->artisan('clickhouse:migrate');

        // Ожидания expectsOutput() проверяются в том порядке, в котором они объявлены
        // здесь, поэтому они должны повторять реальный порядок вывода команды (сначала
        // миграции, "All done!" в конце).
        foreach ($files as $file) {
            $command->expectsOutput('Выполняется миграция: '.basename($file));
            $command->expectsOutput('Готово: '.basename($file));
        }

        $command->expectsOutput('Всё готово!');

        $command->assertExitCode(CommandAlias::SUCCESS);
    }
}
