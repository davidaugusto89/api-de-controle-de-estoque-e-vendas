<?php

declare(strict_types=1);

namespace App\Support\Database;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Helper para gerenciar transações de banco de dados de forma elegante.
 *
 * Uso:
 *   $this->tx->run(function () {
 *       // operações transacionais
 *   });
 *
 * Vantagens:
 * - Centraliza o controle de commit/rollback
 * - Facilita testes e mocking
 * - Mantém consistência em casos de uso complexos (como CreateSale)
 */
final class Transactions
{
    /**
     * Executa o callback dentro de uma transação do banco.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
