<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[Signature('app:health-check')]
#[Description('Проверка работоспособности приложения')]
class HealthCheckCommand extends Command
{
    public function handle(): int
    {
        try {
            $response = Http::get('http://localhost:8080/health');
        } catch (ConnectionException) {
            $this->error('Проверка здоровья не пройдена');

            return CommandAlias::FAILURE;
        }

        if ($response->ok() && $response->json('status') === 'ok') {
            $this->info('Проверка здоровья пройдена успешно');

            return CommandAlias::SUCCESS;
        }

        $this->error('Проверка здоровья не пройдена');

        return CommandAlias::FAILURE;
    }
}
