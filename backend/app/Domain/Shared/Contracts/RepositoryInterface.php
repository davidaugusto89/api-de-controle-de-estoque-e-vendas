<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Contrato base para repositórios do domínio.
 */
interface RepositoryInterface
{
    /**
     * Retorna um registro pelo ID.
     */
    public function findById(int $id): mixed;

    /**
     * Cria um novo registro.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): mixed;

    /**
     * Atualiza um registro existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool;

    /**
     * Remove um registro.
     */
    public function delete(int $id): bool;
}
