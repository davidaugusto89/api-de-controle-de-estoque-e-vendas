<?php

declare(strict_types=1);

namespace App\Support\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait WithQueryOptimization
{
    /**
     * Aplica hints/otimizações leves e seguras.
     * Use em repositórios/queries específicas quando fizer sentido.
     */
    protected function readOnlyConnection(): void
    {
        // Se você tiver réplicas, poderia forçar "read" aqui.
        // DB::connection('read'); // exemplo
    }

    /**
     * Desabilita timestamps e events dentro de um escopo de leitura pesada.
     */
    protected function withoutModelEvents(callable $callback)
    {
        return \Illuminate\Database\Eloquent\Model::withoutEvents($callback);
    }

    /**
     * Evita N+1: utilitário para aplicar eager constraints somente quando necessário.
     */
    protected function maybeWith(Builder $qb, array $relations = []): Builder
    {
        if (! empty($relations)) {
            return $qb->with($relations);
        }

        return $qb;
    }

    /**
     * Força ANSI_QUOTES/SQL_MODE específicos para relatórios se necessário.
     */
    protected function withSafeSqlMode(callable $callback)
    {
        return DB::transaction(function () use ($callback) {
            $orig = DB::selectOne('SELECT @@sql_mode AS m');
            try {
                DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

                return $callback();
            } finally {
                if ($orig && isset($orig->m)) {
                    DB::statement('SET SESSION sql_mode = ?', [$orig->m]);
                }
            }
        }, 1);
    }
}
