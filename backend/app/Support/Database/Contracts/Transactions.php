<?php

declare(strict_types=1);

namespace App\Support\Database\Contracts;

/**
 * Abstração de transações para facilitar testes e inversão de dependência.
 */
interface Transactions
{
    /**
     * Executa o callback dentro de uma transação e retorna seu resultado.
     *
     * @template TReturn
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed;
}
