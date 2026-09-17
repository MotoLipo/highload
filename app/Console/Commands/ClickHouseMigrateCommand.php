<?php

namespace App\Console\Commands;

use ClickHouseDB\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[Signature('clickhouse:migrate')]
#[Description('Выполнение миграций ClickHouse')]
class ClickHouseMigrateCommand extends Command
{
    public function __construct(private readonly Client $clickhouse)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->clickhouse->write('CREATE DATABASE IF NOT EXISTS analytics');

        $files = glob(database_path('clickhouse/*.sql')) ?: [];

        foreach ($files as $file) {
            $this->info('Выполняется миграция: '.basename($file));

            $sql = file_get_contents($file);

            if ($sql === false) {
                $this->error("Не удалось прочитать файл миграции: {$file}");

                return CommandAlias::FAILURE;
            }

            $this->clickhouse->write($sql);

            $this->info('Готово: '.basename($file));
        }

        $this->info('Всё готово!');

        return CommandAlias::SUCCESS;
    }
}
