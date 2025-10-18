<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Support\Database\Contracts\Transactions as TransactionsContract;
use Illuminate\Database\DatabaseManager;
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
class Transactions implements TransactionsContract
{
    public function __construct(
        private readonly ?DatabaseManager $db = null
    ) {}

    /**
     * Executa o callback dentro de uma transação do banco.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    public function run(callable $callback): mixed
    {
        if ($this->db === null) {
            return $callback();
        }

        return $this->db->transaction($callback);
    }
}
