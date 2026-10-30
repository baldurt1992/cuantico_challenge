<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

final class PrepareDemo extends Command
{
    protected $signature = 'app:prepare-demo';

    protected $description = 'Inicializa datos sintéticos una sola vez sin restablecer la base existente';

    public function handle(): int
    {
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        if ($this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $this->info('Entorno de demostración preparado. Los datos existentes se conservan.');

        return self::SUCCESS;
    }
}
