<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cria a aplicação usada pelos testes.
     *
     * Normalmente o trait CreatesApplication providencia isso, mas o projeto
     * usa um bootstrap customizado em `bootstrap/app.php`. Implementamos aqui
     * diretamente para garantir que os testes inicializem o container e o DB.
     */
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Bootstrap the kernel so that providers, routes and configuration
        // are carregados como em um ambiente normal.
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
