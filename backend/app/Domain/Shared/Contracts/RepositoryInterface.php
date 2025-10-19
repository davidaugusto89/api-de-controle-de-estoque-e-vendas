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
     *
     * @param  int  $id  ID do registro
     * @return mixed Registro encontrado ou null se não existir.
     */
    public function findById(int $id): mixed;

    /**
     * Cria um novo registro.
     *
     * @param  array<string, mixed>  $data
     * @return mixed Registro criado.
     */
    public function create(array $data): mixed;

    /**
     * Atualiza um registro existente.
     *
     * @param  int  $id  ID do registro
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool;

    /**
     * Remove um registro.
     *
     * @param  int  $id  ID do registro
     */
    public function delete(int $id): bool;
}
