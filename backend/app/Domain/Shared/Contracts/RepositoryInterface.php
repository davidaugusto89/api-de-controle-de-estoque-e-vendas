<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface RepositoryInterface
{
    public function findById(int $id): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
